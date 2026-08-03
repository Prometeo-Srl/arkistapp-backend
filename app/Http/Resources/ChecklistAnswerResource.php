<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_submission_id' => $this->checklist_submission_id,
            'checklist_question_id' => $this->checklist_question_id,
            'value_text' => $this->value_text,
            'value_date' => $this->value_date?->toDateString(),
            'value_time' => $this->value_time,
            'value_number' => $this->value_number,
            'selected_option_ids' => $this->selected_option_ids,
            'attachment_path' => $this->attachment_path,
            'question' => new ChecklistQuestionResource($this->whenLoaded('question')),
        ];
    }
}
