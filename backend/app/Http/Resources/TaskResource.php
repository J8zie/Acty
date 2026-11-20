<?php

namespace App\Http\Resources;

use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReportTask */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'volunteer' => $this->whenLoaded('volunteer', fn () => $this->volunteer ? [
                'id' => $this->volunteer->id,
                'first_name' => $this->volunteer->first_name,
                'last_name' => $this->volunteer->last_name,
            ] : null),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'outcome_note' => $this->outcome_note,
            'outcome_image_url' => $this->outcome_image_path
                ? app(ImageStore::class)->url($this->outcome_image_path, 'public')
                : null,
            'rating' => $this->rating,
        ];
    }
}
