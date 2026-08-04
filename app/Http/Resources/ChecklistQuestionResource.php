<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_section_id' => $this->checklist_section_id,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'allows_attachment' => $this->allows_attachment,
            'position' => $this->position,
            'options' => ChecklistOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
