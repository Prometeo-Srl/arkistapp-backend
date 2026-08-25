<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Http\Resources\FolderResource;
use App\Models\Company;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Support\EffectiveAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class FolderController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        $folders = Folder::query()
            ->whereHas('category', fn (Builder $q) => $q->where('company_id', $company->getKey()))
            ->when(
                $request->filled('category_id'),
                fn (Builder $q) => $q->where('category_id', $request->integer('category_id'))
            )
            ->where(fn (Builder $q) => $this->scopeVisibleFolders($q, $request->user(), $company))
            ->with(['parent', 'children', 'createdBy'])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return FolderResource::collection($folders);
    }

    public function store(StoreFolderRequest $request, Company $company)
    {
        // The parent decides whether a non-admin may create here: inside their own
        // personal branch, or wherever they hold an editor grant.
        $parent = $request->validated('parent_folder_id')
            ? Folder::query()->findOrFail($request->validated('parent_folder_id'))
            : null;

        $this->authorize('create', [Folder::class, $company, $parent]);

        $folder = $company->categories()
            ->findOrFail($request->validated('category_id'))
            ->folders()
            ->create([
                ...$request->validated(),
                'created_by_id' => $request->user()->getKey(),
            ]);

        return (new FolderResource($folder->load(['parent', 'children', 'createdBy'])))->response()->setStatusCode(201);
    }

    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        $this->authorize('update', $folder);

        $folder->update($request->safe()->only(['name', 'icon', 'position', 'parent_folder_id']));

        return new FolderResource($folder->fresh(['parent', 'children', 'createdBy']));
    }

    public function destroy(Request $request, Folder $folder)
    {
        $this->authorize('delete', $folder);

        // The subtree cascade lives on the model; one transaction so a half-deleted
        // branch can never be left behind.
        DB::transaction(fn () => $folder->delete());

        return response()->noContent();
    }

    /**
     * A worker sees their own personal branch and whatever the company shared with
     * them; the admins see the whole archive.
     */
    /**
     * The whole subtree as one zip: the app has no way to walk a branch itself, and
     * a file-by-file download over mobile data is one request per document.
     *
     * Authorization is re-run per node rather than trusted from the root. A viewer grant
     * cascades down the branch, so in practice the whole subtree comes along — but the
     * archive is built from what the policies actually allow, never from the entry point.
     */
    public function download(Request $request, Folder $folder)
    {
        $this->authorize('view', $folder);

        $path = tempnam(sys_get_temp_dir(), 'folder-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500, 'Cannot create the archive.');

        $this->addFolderToZip($zip, $folder, $request->user(), '');
        $zip->close();

        return response()
            ->download($path, Str::slug($folder->name).'.zip', ['X-Content-Type-Options' => 'nosniff'])
            ->deleteFileAfterSend();
    }

    /**
     * One folder and everything under it. The directory entry is always written, so a
     * branch holding nothing readable still closes as a valid (if empty) archive.
     */
    private function addFolderToZip(ZipArchive $zip, Folder $folder, User $user, string $prefix): void
    {
        $directory = $prefix.$this->zipSafe($folder->name);
        $zip->addEmptyDir($directory);

        foreach ($folder->files as $file) {
            if (! Gate::forUser($user)->allows('download', $file)) {
                continue;
            }

            $version = $file->currentVersion;
            if (! $version || ! Storage::exists($version->storage_path)) {
                continue;
            }

            $zip->addFile(Storage::path($version->storage_path), $this->uniqueEntry($zip, $directory, $file));
        }

        foreach ($folder->children as $child) {
            if (Gate::forUser($user)->allows('view', $child)) {
                $this->addFolderToZip($zip, $child, $user, $directory.'/');
            }
        }
    }

    /** Two documents may share a name in one folder; the id breaks the tie. */
    private function uniqueEntry(ZipArchive $zip, string $directory, File $file): string
    {
        $entry = $directory.'/'.$this->zipSafe($file->name);

        return $zip->locateName($entry) === false
            ? $entry
            : $directory.'/'.$file->getKey().'-'.$this->zipSafe($file->name);
    }

    /** A name is user input: a separator in it would place the entry outside its folder. */
    private function zipSafe(string $name): string
    {
        return trim(str_replace(['/', '\\', "\0"], '-', $name)) ?: 'senza-nome';
    }

    private function scopeVisibleFolders(Builder $q, User $user, Company $company): Builder
    {
        if ($user->isOperator() || $user->isAdminOf($company)) {
            return $q;
        }

        return $q->whereIn('id', EffectiveAccess::visibleFolderIds($user, $company));
    }
}
