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

    public function upload(string $bytes, string $publicId, string $resourceType = 'image', string $mime = 'image/jpeg'): array
    {
        if ($this->failUploads) {
            throw new RuntimeException('Simulated Cloudinary upload failure.');
        }

        $extension = $resourceType === 'video' ? '.mp4' : '.jpg';
        Storage::disk('public')->put($this->storagePath($publicId, $extension), $bytes);
        $this->uploadedPublicIds[] = $publicId;

        return [
            'public_id' => $publicId,
            'secure_url' => $this->secureUrl($publicId, $resourceType),
        ];
    }

    public function delete(string $publicId, string $resourceType = 'image'): void
    {
        if ($this->failDeletions) {
            throw new RuntimeException('Simulated Cloudinary deletion failure.');
        }

        $extension = $resourceType === 'video' ? '.mp4' : '.jpg';
        Storage::disk('public')->delete($this->storagePath($publicId, $extension));
        $this->deletedPublicIds[] = $publicId;
    }

    public function secureUrl(string $publicId, string $resourceType = 'image'): string
    {
        $delivery = $resourceType === 'video' ? 'video' : 'image';
        $extension = $resourceType === 'video' ? '' : '.jpg';

        return 'https://res.cloudinary.com/actify-test/'.$delivery.'/upload/'.$publicId.$extension;
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

    private function storagePath(string $publicId, string $extension = '.jpg'): string
    {
        return $publicId.$extension;
    }
}
