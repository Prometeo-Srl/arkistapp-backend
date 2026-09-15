<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsChecklistDrafts;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Resources\ChecklistQuestionResource;
use App\Http\Resources\ChecklistSectionResource;
use App\Models\Checklist;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use App\Support\RichText;
use Illuminate\Http\Request;

/**
 * The granular write path, one node at a time. Not deprecated by the whole-tree
 * reconciler and not a duplicate of it (ADR-0004): it stays the right shape for
 * anything that changes one thing, and shares the reconciler's validation rules
 * and its bozza guard rather than keeping a second copy of the invariants.
 */
class ChecklistSectionController extends Controller
{
    use GuardsChecklistDrafts;

    public function store(StoreSectionRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);
        $this->guardDraft($checklist);

        $section = $checklist->sections()->create([
            'title' => RichText::sanitize($request->validated('title')),
            'position' => $checklist->sections()->count(),
        ]);

        return (new ChecklistSectionResource($section))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreSectionRequest $request, ChecklistSection $section)
    {
        $this->authorize('manage', $section->checklist);
        $this->guardDraft($section->checklist);

        $section->update(['title' => RichText::sanitize($request->validated('title'))]);

        return new ChecklistSectionResource($section->fresh());
    }

    /** Hard delete, questions and options with it: only ever a bozza (ADR-0005). */
    public function destroy(Request $request, ChecklistSection $section)
    {
        $this->authorize('manage', $section->checklist);
        $this->guardDraft($section->checklist);

        $section->delete();

        return response()->noContent();
    }

    public function storeQuestion(StoreQuestionRequest $request, ChecklistSection $section)
    {
        $this->authorize('manage', $section->checklist);
        $this->guardDraft($section->checklist);

        $question = $section->questions()->create([
            'label' => RichText::sanitize($request->validated('label')),
            'help_text' => $request->validated('help_text'),
            'type' => $request->validated('type'),
            'image_path' => $request->validated('image_path'),
            'is_required' => $request->boolean('is_required'),
            'allows_attachment' => $request->boolean('allows_attachment'),
            'allows_note' => $request->boolean('allows_note'),
            'position' => $request->validated('position') ?? $section->questions()->count(),
        ]);

        $this->syncOptions($question, $request->validated('options'));

        return (new ChecklistQuestionResource($question->load('options')))
            ->response()
            ->setStatusCode(201);
    }

    public function updateQuestion(UpdateQuestionRequest $request, ChecklistQuestion $question)
    {
        $this->authorize('manage', $question->section->checklist);
        $this->guardDraft($question->section->checklist);

        $question->fill($request->safe()->only(['help_text', 'type', 'image_path', 'position']));

        if ($request->has('label')) {
            $question->label = RichText::sanitize($request->validated('label'));
        }

        $question->is_required = $request->boolean('is_required');
        $question->allows_attachment = $request->boolean('allows_attachment');
        $question->allows_note = $request->boolean('allows_note');
        $question->save();

        if ($request->has('options')) {
            $this->syncOptions($question, $request->validated('options'));
        }

        return new ChecklistQuestionResource($question->fresh(['options']));
    }

    public function destroyQuestion(Request $request, ChecklistQuestion $question)
    {
        $this->authorize('manage', $question->section->checklist);
        $this->guardDraft($question->section->checklist);

        $question->delete();

        return response()->noContent();
    }

    /**
     * A near-identical question costs the DDL one edit rather than a retype. Done
     * server-side so the question's images are not re-uploaded, and placed in the
     * same section immediately after the original.
     */
    public function duplicateQuestion(Request $request, ChecklistQuestion $question)
    {
        $this->authorize('manage', $question->section->checklist);
        $this->guardDraft($question->section->checklist);

        $question->load('options');

        // Everything after the original shifts down, so the copy has a position of
        // its own rather than tying with the question below it.
        $question->section->questions()
            ->where('position', '>', $question->position)
            ->increment('position');

        $copy = $question->copyInto($question->checklist_section_id, $question->position + 1);

        return (new ChecklistQuestionResource($copy->load('options')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Options belong to a question, so an update replaces the set wholesale.
     *
     * @param  array<int, array<string, mixed>>|null  $options
     */
    private function syncOptions(ChecklistQuestion $question, ?array $options): void
    {
        $question->options()->delete();

        if ($options === null) {
            return;
        }

        foreach ($options as $index => $option) {
            $question->options()->create([
                'label' => $option['label'],
                'image_path' => $option['image_path'] ?? null,
                'position' => $option['position'] ?? $index,
            ]);
        }
    }
}
