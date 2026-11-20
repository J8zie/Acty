<?php

namespace App\Http\Resources;

use App\Support\PrivateUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The person behind a guest report. ReportResource only includes this for admins.
 *
 * @mixin \App\Models\GuestReporter
 */
class ReporterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'barangay' => $this->barangay,
            // never a plain /storage address: a signed link that stops working after a few minutes
            'selfie_url' => PrivateUrl::for($this->report_id, 'selfie'),
            'liveness_prompt' => $this->liveness_prompt,
            'photo_captured_at' => $this->photo_captured_at?->toISOString(),
            'selfie_captured_at' => $this->selfie_captured_at?->toISOString(),
            'photo_capture_method' => $this->photo_capture_method->value,
            'selfie_capture_method' => $this->selfie_capture_method->value,
        ];
    }
}
