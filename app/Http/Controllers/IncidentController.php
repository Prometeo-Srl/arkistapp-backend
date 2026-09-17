<?php

namespace App\Http\Controllers;

use App\Enums\IncidentKind;
use App\Enums\IncidentStatus;
use App\Enums\MediaKind;
use App\Http\Requests\StoreIncidentAttachmentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Resources\IncidentAttachmentResource;
use App\Http\Resources\IncidentResource;
use App\Models\Company;
use App\Models\IncidentReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class IncidentController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('viewAny', [IncidentReport::class, $company]);

        // An injury is only visible to the DDL, the RSPP and the medico competente;
        // for everybody else the tab is the near miss list and nothing more.
        $seesInjuries = $request->user()->can('viewInjuries', [IncidentReport::class, $company]);

        $incidents = IncidentReport::query()
            ->unless($seesInjuries, fn ($q) => $q->where('kind', '!=', IncidentKind::Injury))
            ->where('company_id', $company->getKey())
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity_bucket', $request->query('severity')))
            ->with('attachments')
            ->latest('reported_at')
            ->get();

        return IncidentResource::collection($incidents);
    }

    public function store(StoreIncidentRequest $request, Company $company)
    {
        $this->authorize('create', [IncidentReport::class, $company]);

        if ($request->enum('kind', IncidentKind::class) === IncidentKind::Injury) {
            $this->authorize('createInjury', [IncidentReport::class, $company]);
        }

        $incident = IncidentReport::create([
            ...$request->validated(),
            'company_id' => $company->getKey(),
            'reported_by_id' => $request->boolean('is_anonymous') ? null : $request->user()->getKey(),
            'reported_at' => now(),
        ]);

        // The saving observer nulls reported_by_id and derives severity_bucket.
        return (new IncidentResource($incident->load('attachments')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, IncidentReport $incident)
    {
        $this->authorize('view', $incident);

        return new IncidentResource($incident->load(['reportedBy', 'reviewedBy', 'attachments']));
    }

    /**
     * Two roles on one endpoint; the reviewer wins.
     * Reviewers drive status/causes/actions/inail_ref, the reporter polishes
     * their own draft's factual fields.
     */
    public function update(UpdateIncidentRequest $request, IncidentReport $incident)
    {
        $this->authorize('update', $incident);

        $user = $request->user();

        if ($user->isOperator() || $user->isAdminOf($incident->company)) {
            $data = $request->safe()->only(['status', 'causes', 'actions_taken', 'inail_ref']);
            if (($data['status'] ?? null) === IncidentStatus::Closed->value) {
                $data['closed_at'] = now();
            }
            $incident->update($data + ['reviewed_by_id' => $user->getKey()]);
        } else {
            $incident->update($request->safe()->only(['description', 'causes', 'actions_taken', 'location', 'department']));
        }

        return new IncidentResource($incident->fresh()->load(['reportedBy', 'reviewedBy', 'attachments']));
    }

    public function destroy(Request $request, IncidentReport $incident)
    {
        $this->authorize('delete', $incident);

        $incident->delete();

        return response()->noContent();
    }

    public function storeAttachment(StoreIncidentAttachmentRequest $request, IncidentReport $incident)
    {
        // Appending an attachment writes to a D.Lgs 81/08 record and ships inside
        // the official PDF, so it follows the same admin-or-draft-reporter rule as
        // every other write. `view` let any member of the tenant amend a report
        // someone else filed and closed.
        $this->authorize('update', $incident);

        $upload = $request->file('file');
        $path = $upload->store('incidents');

        $attachment = $incident->attachments()->create([
            'storage_path' => $path,
            // `storage_path` is hashed: the uploaded name is the only label the
            // detail screen (290) can put on the row, as `FileController` does.
            'name' => $request->validated('name') ?? $upload->getClientOriginalName(),
            'media_kind' => $request->validated('media_kind') ?? MediaKind::Image,
            'caption' => $request->validated('caption'),
        ]);

        return (new IncidentAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The report as a document: the PDF on its own, or a zip holding the PDF and
     * every attachment when the report carries any.
     *
     * The client cannot build the PDF itself, and one request per attachment over
     * mobile data is what the archive spares — same trade as `FolderController::download`.
     */
    public function download(Request $request, IncidentReport $incident)
    {
        $this->authorize('view', $incident);

        $incident->load(['company', 'reportedBy', 'attachments']);

        $stem = 'segnalazione-'.$incident->getKey();
        $pdf = Pdf::loadView('pdf.incident', ['incident' => $incident])->output();

        if ($incident->attachments->isEmpty()) {
            return response()->streamDownload(
                fn () => print ($pdf),
                $stem.'.pdf',
                ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'incident-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500, 'Cannot create the archive.');

        $zip->addFromString($stem.'.pdf', $pdf);

        foreach ($incident->attachments as $attachment) {
            if (! Storage::exists($attachment->storage_path)) {
                continue;
            }

            $name = $this->zipSafe($attachment->name ?: basename($attachment->storage_path));
            // Two attachments may carry the same uploaded name; the id breaks the tie.
            $entry = 'allegati/'.$name;
            $zip->addFile(
                Storage::path($attachment->storage_path),
                $zip->locateName($entry) === false ? $entry : 'allegati/'.$attachment->getKey().'-'.$name,
            );
        }

        $zip->close();

        return response()
            ->download($path, $stem.'.zip', ['X-Content-Type-Options' => 'nosniff'])
            ->deleteFileAfterSend();
    }

    /** A name is user input: a separator in it would place the entry outside its folder. */
    private function zipSafe(string $name): string
    {
        return trim(str_replace(['/', '\\', "\0"], '-', $name)) ?: 'senza-nome';
    }
}
