<?php

namespace App\Http\Controllers;

use App\Enums\ChecklistStatus;
use App\Http\Requests\StoreChecklistRequest;
use App\Http\Requests\UpdateChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('manage', Checklist::make(['company_id' => $company->getKey()]));

        return ChecklistResource::collection(
            $company->checklists()
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
                ->with(['sections.questions.options'])
                ->latest()
                ->get()
        );
    }

    public function store(StoreChecklistRequest $request, Company $company)
    {
        $this->authorize('manage', Checklist::make(['company_id' => $company->getKey()]));

        $checklist = $company->checklists()->create([
            ...$request->validated(),
            'status' => ChecklistStatus::Draft,
            'created_by_id' => $request->user()->getKey(),
        ]);

        return (new ChecklistResource($checklist->load('sections.questions.options')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        return new ChecklistResource($checklist->load(['sections.questions.options', 'createdBy']));
    }

    public function update(UpdateChecklistRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $checklist->update($request->validated());

        return new ChecklistResource($checklist->fresh(['sections.questions.options']));
    }

    /** Soft delete: the template disappears but its history (assignments, submissions) survives. */
    public function destroy(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $checklist->delete();

        return response()->noContent();
    }

    /** Draft → published, stamping published_at. Idempotent: republishing is a no-op. */
    public function publish(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        if ($checklist->status !== ChecklistStatus::Published) {
            $checklist->update([
                'status' => ChecklistStatus::Published,
                'published_at' => now(),
            ]);
        }

        return new ChecklistResource($checklist->fresh(['sections.questions.options']));
    }
}
