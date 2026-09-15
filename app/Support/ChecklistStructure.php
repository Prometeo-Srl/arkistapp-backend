<?php

namespace App\Support;

use App\Models\Checklist;
use App\Models\ChecklistOption;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves a whole questionnaire tree in one go (ADR-0004).
 *
 * The payload is authoritative: rows it carries are upserted, rows it omits are
 * deleted. Matching is on the uuid the builder generated, never on array order
 * or on a server id, which is what makes a retried save idempotent - the same
 * payload twice is the same tree, not two.
 *
 * Only ever called for a bozza. The caller enforces that; a shared checklist is
 * frozen (ADR-0005).
 */
final class ChecklistStructure
{
    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public static function save(Checklist $checklist, array $sections): void
    {
        self::guardForeignUuids($checklist, $sections);

        DB::transaction(function () use ($checklist, $sections) {
            $existing = [
                'sections' => $checklist->sections()->get()->keyBy('uuid'),
                // Keyed across the whole checklist rather than per section: a question
                // dragged into another section arrives under its new parent and must be
                // recognised as the same row, or the move would lose its id.
                'questions' => self::questionsOf($checklist)->keyBy('uuid'),
                'options' => self::optionsOf($checklist)->keyBy('uuid'),
            ];

            $kept = ['sections' => [], 'questions' => [], 'options' => []];

            foreach ($sections as $sectionInput) {
                $section = $existing['sections']->get($sectionInput['uuid'])
                    ?? $checklist->sections()->make(['uuid' => $sectionInput['uuid']]);

                $section->fill([
                    'title' => RichText::sanitize($sectionInput['title']),
                    'position' => $sectionInput['position'],
                ])->save();

                $kept['sections'][] = $section->getKey();

                foreach ($sectionInput['questions'] ?? [] as $questionInput) {
                    $question = $existing['questions']->get($questionInput['uuid'])
                        ?? new ChecklistQuestion(['uuid' => $questionInput['uuid']]);

                    $question->fill([
                        'checklist_section_id' => $section->getKey(),
                        'label' => RichText::sanitize($questionInput['label']),
                        'help_text' => $questionInput['help_text'] ?? null,
                        'type' => $questionInput['type'],
                        'image_path' => $questionInput['image_path'] ?? null,
                        'is_required' => $questionInput['is_required'] ?? false,
                        'allows_attachment' => $questionInput['allows_attachment'] ?? false,
                        'allows_note' => $questionInput['allows_note'] ?? false,
                        'position' => $questionInput['position'],
                    ])->save();

                    $kept['questions'][] = $question->getKey();

                    foreach ($questionInput['options'] ?? [] as $optionInput) {
                        $option = $existing['options']->get($optionInput['uuid'])
                            ?? new ChecklistOption(['uuid' => $optionInput['uuid']]);

                        $option->fill([
                            'checklist_question_id' => $question->getKey(),
                            'label' => $optionInput['label'],
                            'image_path' => $optionInput['image_path'] ?? null,
                            'position' => $optionInput['position'],
                        ])->save();

                        $kept['options'][] = $option->getKey();
                    }
                }
            }

            // One sweep at the end, never per section: a question moved from the first
            // section to the third is absent from the first section's payload, and a
            // sweep running as each section is written would delete it before the third
            // section ever claimed it.
            self::optionsQuery($checklist)->whereNotIn('id', $kept['options'] ?: [0])->delete();
            self::questionsQuery($checklist)->whereNotIn('id', $kept['questions'] ?: [0])->delete();
            $checklist->sections()->whereNotIn('id', $kept['sections'] ?: [0])->delete();

            // The tree changed even though no column on the checklist row did, and the
            // list sorts on it.
            $checklist->touch();
        });
    }

    /**
     * A uuid already held by another checklist cannot be adopted: the columns are
     * unique, so the insert would fail as a 500 rather than tell the caller what is
     * wrong - and a client that sent one is confused about which tree it is editing.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    private static function guardForeignUuids(Checklist $checklist, array $sections): void
    {
        $sent = ['sections' => collect(), 'questions' => collect(), 'options' => collect()];

        foreach ($sections as $section) {
            $sent['sections']->push($section['uuid']);

            foreach ($section['questions'] ?? [] as $question) {
                $sent['questions']->push($question['uuid']);

                foreach ($question['options'] ?? [] as $option) {
                    $sent['options']->push($option['uuid']);
                }
            }
        }

        $strangers = [
            ChecklistSection::whereIn('uuid', $sent['sections'])
                ->where('checklist_id', '!=', $checklist->getKey()),
            self::questionsQuery($checklist, negate: true)->whereIn('uuid', $sent['questions']),
            self::optionsQuery($checklist, negate: true)->whereIn('uuid', $sent['options']),
        ];

        foreach ($strangers as $query) {
            abort_if(
                $query->exists(),
                422,
                'The payload carries a uuid that belongs to another checklist.'
            );
        }
    }

    /** @return Collection<int, ChecklistQuestion> */
    private static function questionsOf(Checklist $checklist): Collection
    {
        return self::questionsQuery($checklist)->get();
    }

    /** @return Collection<int, ChecklistOption> */
    private static function optionsOf(Checklist $checklist): Collection
    {
        return self::optionsQuery($checklist)->get();
    }

    /** @return Builder<ChecklistQuestion> */
    private static function questionsQuery(Checklist $checklist, bool $negate = false): Builder
    {
        return ChecklistQuestion::query()->{$negate ? 'whereDoesntHave' : 'whereHas'}(
            'section',
            fn (Builder $query) => $query->where('checklist_id', $checklist->getKey())
        );
    }

    /** @return Builder<ChecklistOption> */
    private static function optionsQuery(Checklist $checklist, bool $negate = false): Builder
    {
        return ChecklistOption::query()->{$negate ? 'whereDoesntHave' : 'whereHas'}(
            'question.section',
            fn (Builder $query) => $query->where('checklist_id', $checklist->getKey())
        );
    }
}
