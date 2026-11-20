<?php

namespace App\Support;

interface CloudinaryImageGateway
{
    /** @return array{public_id: string, secure_url: string} */
    public function upload(string $jpegBytes, string $publicId): array;

    public function delete(string $publicId): void;

    public function secureUrl(string $publicId): string;
}
