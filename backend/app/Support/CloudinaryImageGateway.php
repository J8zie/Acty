<?php

namespace App\Support;

// What the app needs from an image host: upload, delete, link.
interface CloudinaryImageGateway
{
    // Uploads bytes and returns where they landed.
    public function upload(string $bytes, string $publicId, string $resourceType = 'image', string $mime = 'image/jpeg'): array;

    // Deletes an uploaded file.
    public function delete(string $publicId, string $resourceType = 'image'): void;

    // Builds the web address for an uploaded file.
    public function secureUrl(string $publicId, string $resourceType = 'image'): string;
}
