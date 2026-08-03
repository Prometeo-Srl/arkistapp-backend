<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_question_id' => $this->checklist_question_id,
            'label' => $this->label,
            'image_path' => $this->image_path,
            'position' => $this->position,
            'is_non_conformity' => $this->is_non_conformity,
        ];
    }
}
