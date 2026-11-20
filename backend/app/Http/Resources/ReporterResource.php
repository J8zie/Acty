<?php

namespace App\Http\Resources;

use App\Support\PrivateUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Shapes a guest reporter's details for admins.
class ReporterResource extends JsonResource
{
    // Builds the reporter array with a signed link to the selfie.
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'barangay' => $this->barangay,

            'selfie_url' => PrivateUrl::for($this->report_id, 'selfie'),
            'liveness_prompt' => $this->liveness_prompt,
            'photo_captured_at' => $this->photo_captured_at?->toISOString(),
            'selfie_captured_at' => $this->selfie_captured_at?->toISOString(),
            'photo_capture_method' => $this->photo_capture_method->value,
            'selfie_capture_method' => $this->selfie_capture_method->value,
        ];
    }
}
