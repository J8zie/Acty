<?php

namespace App\Support;

// What the app needs from an image host: upload, delete, link.
interface CloudinaryImageGateway
{
    // Uploads image bytes and returns where they landed.
    public function upload(string $jpegBytes, string $publicId): array;

    // Deletes an uploaded image.
    public function delete(string $publicId): void;

    // Builds the web address for an uploaded image.
    public function secureUrl(string $publicId): string;
}
