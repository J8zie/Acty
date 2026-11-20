<?php

namespace App\Support;

use Cloudinary\Cloudinary;
use RuntimeException;

// Talks to Cloudinary to store and serve pictures and videos.
final class CloudinarySdkImageGateway implements CloudinaryImageGateway
{
    private ?Cloudinary $client = null;

    // Uploads the bytes and returns its Cloudinary ids.
    public function upload(string $bytes, string $publicId, string $resourceType = 'image', string $mime = 'image/jpeg'): array
    {
        $result = $this->client()->uploadApi()->upload(
            'data:'.$mime.';base64,'.base64_encode($bytes),
            [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'overwrite' => false,
            ],
        );

        return [
            'public_id' => (string) ($result['public_id'] ?? ''),
            'secure_url' => (string) ($result['secure_url'] ?? ''),
        ];
    }

    // Deletes a file from Cloudinary.
    public function delete(string $publicId, string $resourceType = 'image'): void
    {
        $result = $this->client()->uploadApi()->destroy($publicId, [
            'resource_type' => $resourceType,
            'invalidate' => true,
        ]);
        $status = (string) ($result['result'] ?? '');

        if (! in_array($status, ['ok', 'deleted', 'not found'], true)) {
            throw new RuntimeException('Cloudinary did not confirm file deletion.');
        }
    }

    // Builds the web address for an uploaded file.
    public function secureUrl(string $publicId, string $resourceType = 'image'): string
    {
        $cloudName = config('services.cloudinary.cloud_name')
            ?: parse_url((string) config('services.cloudinary.url'), PHP_URL_HOST);

        if (! is_string($cloudName) || $cloudName === '') {
            throw new RuntimeException('Cloudinary cloud name is not configured. Check CLOUDINARY_URL.');
        }

        $encodedPublicId = implode('/', array_map('rawurlencode', explode('/', $publicId)));
        $delivery = $resourceType === 'video' ? 'video' : 'image';
        $extension = $resourceType === 'video' ? '' : '.jpg';

        return 'https://res.cloudinary.com/'.$cloudName.'/'.$delivery.'/upload/'.$encodedPublicId.$extension;
    }

    // Gets the Cloudinary client, making it on first use.
    private function client(): Cloudinary
    {
        if ($this->client) {
            return $this->client;
        }

        $url = config('services.cloudinary.url');

        if (! is_string($url) || trim($url) === '') {
            throw new RuntimeException('CLOUDINARY_URL is not configured.');
        }

        return $this->client = new Cloudinary($url);
    }
}
