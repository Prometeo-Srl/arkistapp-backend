<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Resources\ChecklistQuestionResource;
use App\Http\Resources\ChecklistSectionResource;
use App\Models\Checklist;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use Illuminate\Http\Request;

class ChecklistSectionController extends Controller
{
    public function store(StoreSectionRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);

        $section = $checklist->sections()->create([
            ...$request->validated(),
            'position' => $checklist->sections()->count(),
        ]);

        return (new ChecklistSectionResource($section))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreSectionRequest $request, ChecklistSection $section)
    {
        $this->authorize('manage', $section->checklist);

        $section->update($request->validated());

        return new ChecklistSectionResource($section->fresh());
    }

    public function destroy(Request $request, ChecklistSection $section)
    {
        $this->authorize('manage', $section->checklist);

        $section->delete();

        return response()->noContent();
    }

    public function storeQuestion(StoreQuestionRequest $request, ChecklistSection $section)
    {
        $this->authorize('manage', $section->checklist);

        $question = $section->questions()->create([
            'label' => $request->validated('label'),
            'help_text' => $request->validated('help_text'),
            'type' => $request->validated('type'),
            'is_required' => $request->boolean('is_required'),
            'allows_attachment' => $request->boolean('allows_attachment'),
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

        $question->fill($request->safe()->only(['label', 'help_text', 'type', 'position']));
        $question->is_required = $request->boolean('is_required');
        $question->allows_attachment = $request->boolean('allows_attachment');
        $question->save();

        if ($request->has('options')) {
            $this->syncOptions($question, $request->validated('options'));
        }

        return new ChecklistQuestionResource($question->fresh(['options']));
    }

    public function destroyQuestion(Request $request, ChecklistQuestion $question)
    {
        $this->authorize('manage', $question->section->checklist);

        $question->delete();

        return response()->noContent();
    }

    /** Options belong to a question, so an update replaces the set wholesale. */
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
                'is_non_conformity' => $option['is_non_conformity'] ?? false,
            ]);
        }
    }
}
