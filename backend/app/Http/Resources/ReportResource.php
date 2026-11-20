<?php

namespace App\Http\Resources;

use App\Support\PrivateUrl;
use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Shapes a report into the JSON the app expects.
class ReportResource extends JsonResource
{
    // Builds the report array, hiding private bits from non-admins.
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user('sanctum')?->isAdmin();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'category_id' => $this->category_id,
            'assistance_type' => $this->assistance_type->value,
            'urgency' => $this->urgency->value,
            'status' => $this->status->value,
            'barangay' => $this->barangay,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image_url' => $this->imageUrl($isAdmin),
            'created_at' => $this->created_at?->toISOString(),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ] : null),
            'source' => $this->source->value,
            'task' => $this->whenLoaded('task', fn () => $this->task ? new TaskResource($this->task) : null),

            'reference' => $this->when($isAdmin && $this->reference, fn () => $this->reference),
            'reporter' => $this->when(
                $isAdmin && $this->isGuest() && $this->relationLoaded('guestReporter') && $this->guestReporter,
                fn () => new ReporterResource($this->guestReporter),
            ),
        ];
    }

    // Finds the right picture link, or none for private photos a non-admin asks for.
    private function imageUrl(bool $isAdmin): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if ($this->image_disk === 'private') {

            return $isAdmin ? PrivateUrl::for($this->id, 'photo') : null;
        }

        return app(ImageStore::class)->url($this->image_path, 'public');
    }
}
