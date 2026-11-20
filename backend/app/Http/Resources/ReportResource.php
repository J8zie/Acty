<?php

namespace App\Http\Resources;

use App\Support\PrivateUrl;
use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How a report looks in JSON (see API-CONTRACT.md).
 *
 * The report is always loaded with `Report::withApiRelations()` (or ->loadApiRelations()), so printing a list does
 * not run a database query per row.
 *
 * What only admins get: `reporter` (name, phone, email, selfie link...), `reference`, and the link to the photo
 * of a guest report that is still waiting for review. Everybody else gets none of these.
 *
 * @mixin \App\Models\Report
 */
class ReportResource extends JsonResource
{
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
            // Admin only
            'reference' => $this->when($isAdmin && $this->reference, fn () => $this->reference),
            'reporter' => $this->when(
                $isAdmin && $this->isGuest() && $this->relationLoaded('guestReporter') && $this->guestReporter,
                fn () => new ReporterResource($this->guestReporter),
            ),
        ];
    }

    private function imageUrl(bool $isAdmin): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if ($this->image_disk === 'private') {
            // Not approved yet: nobody but an admin may even know the picture exists.
            return $isAdmin ? PrivateUrl::for($this->id, 'photo') : null;
        }

        return app(ImageStore::class)->url($this->image_path, 'public');
    }
}
