<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\MediaKind;
use App\Http\Requests\StoreIncidentAttachmentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Resources\IncidentAttachmentResource;
use App\Http\Resources\IncidentResource;
use App\Models\Company;
use App\Models\IncidentReport;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('viewAny', [IncidentReport::class, $company]);

        $incidents = IncidentReport::query()
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
        $this->authorize('view', $incident);

        $path = $request->file('file')->store('incidents');

        $attachment = $incident->attachments()->create([
            'storage_path' => $path,
            'media_kind' => $request->validated('media_kind') ?? MediaKind::Image,
            'caption' => $request->validated('caption'),
        ]);

        return (new IncidentAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }
}
