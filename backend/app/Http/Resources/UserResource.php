<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Shapes a user into the JSON the app expects.
class UserResource extends JsonResource
{
    // Builds the user array with a link to their photo.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role->value,
            'created_at' => $this->created_at?->toISOString(),
            'photo_url' => $this->photoUrl(),
        ];
    }
}
