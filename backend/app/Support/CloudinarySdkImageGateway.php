<?php

namespace App\Support;

use Cloudinary\Cloudinary;
use RuntimeException;

final class CloudinarySdkImageGateway implements CloudinaryImageGateway
{
    private ?Cloudinary $client = null;

    /** @return array{public_id: string, secure_url: string} */
    public function upload(string $jpegBytes, string $publicId): array
    {
        $result = $this->client()->uploadApi()->upload(
            'data:image/jpeg;base64,'.base64_encode($jpegBytes),
            [
                'public_id' => $publicId,
                'resource_type' => 'image',
                'overwrite' => false,
            ],
        );

        return [
            'public_id' => (string) ($result['public_id'] ?? ''),
            'secure_url' => (string) ($result['secure_url'] ?? ''),
        ];
    }

    public function delete(string $publicId): void
    {
        $result = $this->client()->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
            'invalidate' => true,
        ]);
        $status = (string) ($result['result'] ?? '');

        if (! in_array($status, ['ok', 'deleted', 'not found'], true)) {
            throw new RuntimeException('Cloudinary did not confirm image deletion.');
        }
    }

    public function secureUrl(string $publicId): string
    {
        $cloudName = config('services.cloudinary.cloud_name')
            ?: parse_url((string) config('services.cloudinary.url'), PHP_URL_HOST);

        if (! is_string($cloudName) || $cloudName === '') {
            throw new RuntimeException('Cloudinary cloud name is not configured. Check CLOUDINARY_URL.');
        }

        $encodedPublicId = implode('/', array_map('rawurlencode', explode('/', $publicId)));

        return 'https://res.cloudinary.com/'.$cloudName.'/image/upload/'.$encodedPublicId.'.jpg';
    }

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
