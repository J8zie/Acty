<?php

namespace Tests\Fakes;

use App\Support\CloudinaryImageGateway;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FakeCloudinaryImageGateway implements CloudinaryImageGateway
{
    public bool $failUploads = false;

    public bool $failDeletions = false;


    public array $deletedPublicIds = [];


    public array $uploadedPublicIds = [];


    public function upload(string $jpegBytes, string $publicId): array
    {
        if ($this->failUploads) {
            throw new RuntimeException('Simulated Cloudinary upload failure.');
        }

        Storage::disk('public')->put($this->storagePath($publicId), $jpegBytes);
        $this->uploadedPublicIds[] = $publicId;

        return [
            'public_id' => $publicId,
            'secure_url' => $this->secureUrl($publicId),
        ];
    }

    public function delete(string $publicId): void
    {
        if ($this->failDeletions) {
            throw new RuntimeException('Simulated Cloudinary deletion failure.');
        }

        Storage::disk('public')->delete($this->storagePath($publicId));
        $this->deletedPublicIds[] = $publicId;
    }

    public function secureUrl(string $publicId): string
    {
        return 'https://res.cloudinary.com/actify-test/image/upload/'.$publicId.'.jpg';
    }

    public function existsForReference(string $reference): bool
    {
        return Storage::disk('public')->exists($this->storagePath($this->publicIdFromReference($reference)));
    }

    public function bytesForReference(string $reference): string
    {
        return Storage::disk('public')->get($this->storagePath($this->publicIdFromReference($reference)));
    }

    private function publicIdFromReference(string $reference): string
    {
        return str_starts_with($reference, 'cloudinary:')
            ? substr($reference, strlen('cloudinary:'))
            : $reference;
    }

    private function storagePath(string $publicId): string
    {
        return $publicId.'.jpg';
    }
}
