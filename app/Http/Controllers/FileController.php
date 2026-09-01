<?php

namespace App\Http\Controllers;

use App\Enums\FileVisibility;
use App\Enums\MediaKind;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFileVersionRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Http\Resources\AcknowledgementResource;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\DocumentTypeResource;
use App\Http\Resources\FileResource;
use App\Models\Acknowledgement;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Support\EffectiveAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FileController extends Controller
{
    /** Twice the widest the archive grid draws a card, so it stays sharp on a 2x screen. */
    private const THUMBNAIL_WIDTH = 400;

    /** Past this an image is served plain rather than decoded into memory. */
    private const THUMBNAIL_SOURCE_MAX_BYTES = 40 * 1024 * 1024;

    public function documentTypes(): AnonymousResourceCollection
    {
        return DocumentTypeResource::collection(
            DocumentType::query()->orderBy('label')->get()
        );
    }

    public function index(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
            'expiring' => ['nullable', 'integer', 'min:1'],
            'media_kind' => ['nullable', Rule::enum(MediaKind::class)],
            'shared' => ['nullable', 'boolean'],
        ]);

        $files = File::query()
            ->whereHas('folder.category', fn (Builder $q) => $q->where('company_id', $company->getKey()))
            ->when($validated['folder_id'] ?? null, fn (Builder $q, $id) => $q->where('folder_id', $id))
            ->when($validated['media_kind'] ?? null, fn (Builder $q, $kind) => $q->where('media_kind', $kind))
            ->when($validated['expiring'] ?? null, function (Builder $q) use ($validated) {
                $q->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [
                        now()->toDateString(),
                        now()->addDays((int) $validated['expiring'])->toDateString(),
                    ]);
            })
            ->when(
                $validated['shared'] ?? false,
                fn (Builder $q) => $this->scopeSharedWithMe($q, $request->user(), $company),
                fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $this->scopeVisibleFolders($inner, $request->user(), $company)
                )
            )
            ->with(['currentVersion', 'documentType', 'folder.category.company', 'uploadedBy'])
            ->latest()
            // An archive grows without bound: paginate like the other list endpoints.
            ->paginate($request->integer('per_page', 50));

        return FileResource::collection($files);
    }

    /** Multipart upload: stores the blob and creates the first version. */
    public function store(StoreFileRequest $request)
    {
        $folder = Folder::query()->findOrFail($request->validated('folder_id'));
        $this->authorize('create', [File::class, $folder]);

        $upload = $request->file('file');
        $companyId = $folder->category->company_id;
        $storagePath = Storage::putFile("documents/{$companyId}", $upload);

        $documentType = $request->input('document_type_id')
            ? DocumentType::query()->find($request->input('document_type_id'))
            : null;

        // One transaction: a file row without its first version is unusable, and the
        // caller would have no way to tell that half the write landed.
        $file = DB::transaction(function () use ($folder, $request, $upload, $documentType, $storagePath) {
            $file = $folder->files()->create([
                'name' => $request->input('name') ?? $upload->getClientOriginalName(),
                'media_kind' => $request->input('media_kind') ?? $this->inferMediaKind($upload->getMimeType()),
                'mime_type' => $upload->getMimeType(),
                'size_bytes' => $upload->getSize(),
                'document_type_id' => $documentType?->getKey(),
                'issued_at' => $request->date('issued_at'),
                'requires_acknowledgement' => $request->has('requires_acknowledgement')
                    ? $request->boolean('requires_acknowledgement')
                    : ($documentType?->requires_acknowledgement_default ?? false),
                'requires_signature' => $request->boolean('requires_signature'),
                // "gestisci accesso", picked in "configura file" before the upload.
                'visibility' => $request->input('visibility') ?? FileVisibility::Inherited,
                // Only when the form set one by hand: an expires_at written here is
                // what stops the model deriving its own from the document type.
                ...($request->filled('expires_at')
                    ? ['expires_at' => $request->date('expires_at')]
                    : []),
                'owner_user_id' => $folder->is_personal_of_user_id,
                'uploaded_by_id' => $request->user()->getKey(),
            ]);

            $version = $file->versions()->create([
                'version_no' => 1,
                'storage_path' => $storagePath,
                'size_bytes' => $upload->getSize(),
                'checksum' => hash_file('sha256', $upload->getRealPath()),
                'uploaded_by_id' => $request->user()->getKey(),
            ]);

            $file->update(['current_version_id' => $version->getKey()]);

            return $file;
        });

        return (new FileResource($file->fresh(['currentVersion', 'documentType', 'folder.category.company', 'uploadedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    /** JSON metadata; the binary payload lives under /download. */
    public function show(Request $request, File $file)
    {
        $this->authorize('view', $file);

        return new FileResource($file->load(['currentVersion', 'documentType', 'folder.category.company', 'uploadedBy']));
    }

    /** Streams the current version; non-members need a valid access grant. */
    public function download(Request $request, File $file)
    {
        $this->authorize('download', $file);

        $version = $file->currentVersion;
        abort_unless($version, 404, 'File has no version.');
        abort_unless(Storage::exists($version->storage_path), 404);

        // Never inline: an uploaded document must not be rendered on the API origin,
        // where the caller's Sanctum token lives.
        return Storage::download($version->storage_path, $file->name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A preview of an image document, for the cards of the archive grid.
     *
     * Rendered on first request and kept beside the original: there is one per
     * version and a version never changes, so nothing has to invalidate it.
     *
     * Images only. A PDF first page is the same three lines with Ghostscript
     * behind them, and handing Ghostscript a user-supplied file to paint a card
     * in a grid is not a door worth opening.
     */
    public function thumbnail(File $file)
    {
        $this->authorize('download', $file);

        abort_unless($file->media_kind === MediaKind::Image, 404);

        $version = $file->currentVersion;
        abort_unless($version && Storage::exists($version->storage_path), 404);

        $path = 'thumbnails/'.$version->getKey().'.jpg';

        if (! Storage::exists($path)) {
            // A decoded bitmap costs multiples of the file on disk; past this the
            // grid goes without rather than the request going down.
            abort_if($version->size_bytes > self::THUMBNAIL_SOURCE_MAX_BYTES, 404);

            try {
                Storage::put($path, $this->renderThumbnail(Storage::get($version->storage_path)));
            } catch (\Throwable) {
                // A format Imagick cannot read is not an error worth a 500: the app
                // falls back to the plain card.
                abort(404);
            }
        }

        // Inline, unlike download(): these bytes are a JPEG this server encoded
        // itself, not the payload somebody uploaded, so there is nothing left in
        // them to run.
        return Storage::response($path, 'thumbnail.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=604800',
        ], 'inline');
    }

    private function renderThumbnail(string $bytes): string
    {
        $image = new \Imagick;

        try {
            $image->readImageBlob($bytes);
            // A multi-frame source (an animated webp) still yields one card.
            $image->setIteratorIndex(0);
            // Phone photos carry their rotation in EXIF, which stripImage drops.
            $image->autoOrient();
            $image->thumbnailImage(self::THUMBNAIL_WIDTH, 0);
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(72);
            // Strips the GPS coordinates along with the rest of the metadata.
            $image->stripImage();

            return $image->getImageBlob();
        } finally {
            $image->clear();
        }
    }

    /** Metadata only. A hand-set expires_at wins over the model's derived one. */
    public function update(UpdateFileRequest $request, File $file)
    {
        $this->authorize('update', $file);

        $data = $request->safe()->only(['name', 'document_type_id', 'issued_at', 'expires_at', 'visibility']);

        // "posizione": a move stays inside the workspace. The policy gates the
        // file, not the destination, so the target folder is checked here.
        if ($request->filled('folder_id')) {
            $target = Folder::with('category')->findOrFail($request->integer('folder_id'));
            abort_unless(
                $target->category?->company_id === $file->folder?->category?->company_id,
                403,
                'La cartella di destinazione appartiene a un altro spazio di lavoro.'
            );
            $data['folder_id'] = $target->getKey();
        }
        if ($request->has('requires_acknowledgement')) {
            $data['requires_acknowledgement'] = $request->boolean('requires_acknowledgement');
        }
        if ($request->has('requires_signature')) {
            $data['requires_signature'] = $request->boolean('requires_signature');
        }

        $file->update($data);

        return new FileResource($file->fresh(['currentVersion', 'documentType', 'folder.category.company', 'uploadedBy']));
    }

    public function destroy(Request $request, File $file)
    {
        $this->authorize('delete', $file);

        $file->delete();

        return response()->noContent();
    }

    /** New blob, version_no bumped; the file points at the new current version. */
    public function storeVersion(StoreFileVersionRequest $request, File $file)
    {
        $this->authorize('replaceVersion', $file);

        $upload = $request->file('file');
        $storagePath = Storage::putFile(
            "documents/{$file->folder->category->company_id}",
            $upload
        );

        // (file_id, version_no) is unique, so two simultaneous uploads would collide on
        // max()+1. Locking the file row serialises them instead of failing one with a 500.
        DB::transaction(function () use ($file, $request, $upload, $storagePath) {
            File::query()->whereKey($file->getKey())->lockForUpdate()->first();

            $version = $file->versions()->create([
                'version_no' => (int) $file->versions()->max('version_no') + 1,
                'storage_path' => $storagePath,
                'size_bytes' => $upload->getSize(),
                'checksum' => hash_file('sha256', $upload->getRealPath()),
                'uploaded_by_id' => $request->user()->getKey(),
                'replaced_reason' => $request->input('replaced_reason'),
            ]);

            $file->update([
                'current_version_id' => $version->getKey(),
                'size_bytes' => $upload->getSize(),
                'mime_type' => $upload->getMimeType(),
                // A replacement may be of another kind entirely — a PDF swapped for a
                // photo — and both of these describe the bytes, not the document.
                'media_kind' => $this->inferMediaKind($upload->getMimeType()),
                'name' => $this->withExtensionOf($file->name, $upload),
            ]);
        });

        return (new FileResource($file->fresh(['currentVersion', 'documentType', 'folder.category.company', 'uploadedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    /** Read receipt, unique per version: a new version demands a new confirmation. */
    public function acknowledge(Request $request, File $file)
    {
        $this->authorize('download', $file);

        $versionId = $file->current_version_id;
        abort_unless($versionId, 422, 'File has no version.');

        // required_at records when the receipt became due, so it is written once and
        // then left alone: re-confirming must not rewrite the moment it was requested.
        $ack = Acknowledgement::firstOrNew([
            'file_version_id' => $versionId,
            'user_id' => $request->user()->getKey(),
        ]);

        $isFirstConfirmation = ! $ack->exists;

        if ($isFirstConfirmation) {
            $ack->file_id = $file->getKey();
            $ack->required_at = $file->requires_acknowledgement ? now() : null;
        }

        $ack->fill([
            'viewed_at' => now(),
            'confirmed_at' => now(),
            'ip_address' => $request->ip(),
        ])->save();

        return (new AcknowledgementResource($ack))->response()
            ->setStatusCode($isFirstConfirmation ? 201 : 200);
    }

    public function acknowledgements(Request $request, File $file)
    {
        $this->authorize('view', $file);

        return AcknowledgementResource::collection(
            $file->acknowledgements()->with(['user', 'fileVersion'])->latest()->get()
        );
    }

    /**
     * "cronologia" (prototype 234): what happened to this one document, newest
     * first, each row naming the person behind it.
     *
     * Gated on `view` rather than on the company-wide audit log's admin check: the
     * trail of a document is scoped to that document, and anyone allowed to open it
     * is allowed to see who touched it.
     */
    public function history(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $entries = AuditLog::query()
            ->where('auditable_type', $file->getMorphClass())
            ->where('auditable_id', $file->getKey())
            ->with('user')
            // Bulk mutations land in the same second and `created_at` is the only
            // timestamp on the table, so the id has to break the tie or the trail
            // comes back in an arbitrary order.
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        return AuditLogResource::collection($entries);
    }

    /**
     * The document keeps its title, but not an extension that lies about its bytes.
     *
     * Downloads are served under $file->name, so a JPEG still called ".pdf" reaches
     * the user as a PDF that no reader can open. The extension comes from the sniffed
     * type, not from the name the client sent.
     */
    private function withExtensionOf(string $name, UploadedFile $upload): string
    {
        $extension = (string) $upload->extension();
        if ($extension === '') {
            return $name;
        }

        $stem = pathinfo($name, PATHINFO_FILENAME);

        // A name that is nothing but an extension (".pdf") has no title to keep.
        return $stem === '' ? $name : "{$stem}.{$extension}";
    }

    private function inferMediaKind(?string $mime): MediaKind
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/') => MediaKind::Image,
            str_starts_with((string) $mime, 'audio/') => MediaKind::Audio,
            str_starts_with((string) $mime, 'video/') => MediaKind::Video,
            default => MediaKind::Document,
        };
    }

    /**
     * A worker sees the files of the folders shared with them and of their own personal
     * branch, plus the single files shared with them directly.
     */
    private function scopeVisibleFolders(Builder $q, User $user, Company $company): Builder
    {
        if ($user->isOperator() || $user->isAdminOf($company)) {
            return $q;
        }

        return $q->whereIn('folder_id', EffectiveAccess::visibleFolderIds($user, $company))
            ->orWhereIn('id', EffectiveAccess::grantedFileIds($user, $company));
    }

    /**
     * "condivisi con me" (prototype 095): only what somebody else handed over.
     *
     * Grants alone, so an admin sees this list too — their own archive is the other
     * tab. The user's personal branch is left out (it is theirs, not shared with
     * them) and so is anything they uploaded themselves.
     */
    private function scopeSharedWithMe(Builder $q, User $user, Company $company): Builder
    {
        return $q
            ->where(fn (Builder $inner) => $inner
                ->whereIn('folder_id', EffectiveAccess::visibleFolderIds($user, $company, includePersonal: false))
                ->orWhereIn('id', EffectiveAccess::grantedFileIds($user, $company)))
            ->where(fn (Builder $inner) => $inner
                ->whereNull('uploaded_by_id')
                ->orWhere('uploaded_by_id', '!=', $user->getKey()));
    }
}
