<?php

namespace App\Support;

// Where an uploaded picture ended up and how to reach it.
final readonly class ImageStorageResult
{
    // Remembers the path, disk and optional Cloudinary details.
    public function __construct(
        public string $path,
        public string $disk,
        public ?string $publicId = null,
        public ?string $secureUrl = null,
    ) {}
}
