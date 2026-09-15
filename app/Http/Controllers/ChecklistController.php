<?php

namespace App\Http\Controllers;

use App\Enums\ChecklistStatus;
use App\Http\Controllers\Concerns\GuardsChecklistDrafts;
use App\Http\Requests\StoreChecklistRequest;
use App\Http\Requests\UpdateChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    use GuardsChecklistDrafts;

    /**
     * The author's list: every checklist of the workspace, bozze included. The
     * people who merely answer one get /my/checklists instead - a different
     * dataset behind a different authorization, rather than this endpoint
     * returning something else per caller.
     */
    public function index(Request $request, Company $company)
    {
        $this->authorize('manage', Checklist::make(['company_id' => $company->getKey()]));

        return ChecklistResource::collection(
            $company->checklists()
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
                ->when($request->filled('q'), fn (Builder $query) => $query->whereLike('title', '%'.$request->query('q').'%'))
                ->with(['sections.questions.options', 'createdBy'])
                ->orderByDesc('updated_at')
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

        return (new ChecklistResource($checklist->load(['sections.questions.options', 'createdBy'])))
            ->response()
            ->setStatusCode(201);
    }

    /** Authors read it to edit it, assignees to answer it. */
    public function show(Request $request, Checklist $checklist)
    {
        $this->authorize('view', $checklist);

        return new ChecklistResource($checklist->load(['sections.questions.options', 'createdBy']));
    }

    public function update(UpdateChecklistRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);
        $this->guardDraft($checklist);

        $checklist->update($request->validated());

        return new ChecklistResource($checklist->fresh(['sections.questions.options', 'createdBy']));
    }

    /** Soft delete: the checklist disappears but its assegnazioni and compilazioni survive. */
    public function destroy(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $checklist->delete();

        return response()->noContent();
    }

    /**
     * Deep clone into a fresh bozza owned by the caller. This is the whole of the
     * "start from a template" requirement - the prototype's template checklist is
     * a checklist somebody named that, not an entity - and it is also the only way
     * to correct a shared one, which is frozen (ADR-0005).
     */
    public function duplicate(Request $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $checklist->load('sections.questions.options');

        $copy = DB::transaction(function () use ($checklist, $request) {
            $copy = $checklist->company->checklists()->create([
                'title' => $checklist->title.' (copia)',
                'description' => $checklist->description,
                'status' => ChecklistStatus::Draft,
                'created_by_id' => $request->user()->getKey(),
            ]);

            foreach ($checklist->sections as $section) {
                $sectionCopy = $copy->sections()->create([
                    'title' => $section->title,
                    'position' => $section->position,
                ]);

                foreach ($section->questions as $question) {
                    $question->copyInto($sectionCopy->getKey(), $question->position);
                }
            }

            return $copy;
        });

        return (new ChecklistResource($copy->load(['sections.questions.options', 'createdBy'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The blank questionnaire, rendered on demand and never stored: nothing can go
     * stale, and there is no stored copy to keep in step. Open to anyone who may
     * see the checklist, authors and assignees alike.
     */
    public function pdf(Request $request, Checklist $checklist)
    {
        $this->authorize('view', $checklist);

        $checklist->load(['sections.questions.options', 'company', 'createdBy']);

        $pdf = Pdf::loadView('pdf.checklist', ['checklist' => $checklist])->output();

        return response()->streamDownload(
            fn () => print ($pdf),
            'checklist-'.$checklist->getKey().'.pdf',
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
