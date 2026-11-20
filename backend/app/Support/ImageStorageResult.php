<?php

namespace App\Support;

final readonly class ImageStorageResult
{
    public function __construct(
        public string $path,
        public string $disk,
        public ?string $publicId = null,
        public ?string $secureUrl = null,
    ) {}
}
