<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

// Saves, links and deletes the videos people attach to reports.
final class VideoStore
{
    private const CLOUDINARY_PREFIX = 'cloudinary:';

    private const MIME_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    // Remembers how to talk to Cloudinary.
    public function __construct(private readonly CloudinaryImageGateway $cloudinary) {}

    // Stores a report video on the public (Cloudinary) or private disk.
    public function storeReportVideo(UploadedFile $file, string $disk = 'public', string $directory = 'reports', string $field = 'video'): ImageStorageResult
    {
        $mime = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                $field => ['That file is not a video we can read. Please choose an MP4, WebM or MOV video.'],
            ]);
        }

        $maxBytes = (int) config('actify.videos.max_mb') * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                $field => ['That video is too large. The limit is '.(int) config('actify.videos.max_mb').' MB.'],
            ]);
        }

        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $uuid = Str::uuid()->toString();

        if ($disk === 'public' || $disk === 'cloudinary-private') {
            $folder = trim((string) config('services.cloudinary.folder', 'actify'), '/');
            $publicId = trim($folder.'/'.trim($directory, '/'), '/').'/'.$uuid;
            $storedPublicId = null;

            try {
                $result = $this->cloudinary->upload($bytes, $publicId, 'video', $mime);
                $storedPublicId = trim($result['public_id']);
                $secureUrl = trim($result['secure_url']);

                if ($storedPublicId === '' || filter_var($secureUrl, FILTER_VALIDATE_URL) === false) {
                    throw new RuntimeException('Cloudinary returned an invalid video result.');
                }

                return new ImageStorageResult(
                    self::CLOUDINARY_PREFIX.$storedPublicId,
                    $disk === 'cloudinary-private' ? 'private' : 'public',
                    $storedPublicId,
                    $secureUrl,
                );
            } catch (Throwable $e) {
                if ($storedPublicId) {
                    try {
                        $this->cloudinary->delete($storedPublicId, 'video');
                    } catch (Throwable $cleanupError) {
                        $this->logStorageFailure('cleanup', $cleanupError);
                    }
                }

                $this->logStorageFailure('upload', $e);
                throw new RuntimeException('Unable to upload the video. Please try again.');
            }
        }

        if ($disk !== 'private' && $disk !== 'cloudinary-private') {
            throw new InvalidArgumentException('Videos may only be stored on the public or private disk.');
        }

        $path = trim($directory, '/').'/'.$uuid.'.'.$extension;

        try {
            if (! Storage::disk('private')->put($path, $bytes)) {
                throw new RuntimeException('Private video write failed.');
            }
        } catch (Throwable $e) {
            $this->logStorageFailure('write', $e, 'private');
            throw new RuntimeException('Unable to store the private video.');
        }

        return new ImageStorageResult($path, 'private');
    }

    // Moves a pending guest video to the public disk after approval.
    public function promoteGuestVideo(string $path): ImageStorageResult
    {
        if (str_starts_with($path, self::CLOUDINARY_PREFIX)) {
            $publicId = substr($path, strlen(self::CLOUDINARY_PREFIX));
            if (! str_contains($publicId, 'guest-reports/') && ! str_contains($publicId, 'pending/')) {
                throw new InvalidArgumentException('Only guest report videos can be made public.');
            }

            return new ImageStorageResult($path, 'public', $publicId, $this->cloudinary->secureUrl($publicId, 'video'));
        }

        if (! str_starts_with($path, 'guest-reports/') && ! str_starts_with($path, 'pending/')) {
            throw new InvalidArgumentException('Only guest report videos can be made public.');
        }

        try {
            $bytes = Storage::disk('private')->get($path);
        } catch (Throwable $e) {
            $this->logStorageFailure('read', $e, 'private');
            throw new RuntimeException('Unable to read the private report video.');
        }

        if (! is_string($bytes)) {
            throw new RuntimeException('Unable to read the private report video.');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mime = array_search($extension, self::MIME_EXTENSIONS, true) ?: 'video/mp4';

        return $this->putPublic($bytes, 'reports', $mime);
    }

    // Returns a browser-ready URL for a stored video.
    public function url(?string $path, string $disk = 'public'): ?string
    {
        if (! $path || $disk === 'private') {
            return null;
        }

        if ($disk === 'public' && str_starts_with($path, self::CLOUDINARY_PREFIX)) {
            return $this->cloudinary->secureUrl(substr($path, strlen(self::CLOUDINARY_PREFIX)), 'video');
        }

        return Storage::disk($disk)->url($path);
    }

    // Deletes a stored video.
    public function delete(?string $path, string $disk = 'public'): bool
    {
        if (! $path) {
            return true;
        }

        if (str_starts_with($path, self::CLOUDINARY_PREFIX)) {
            try {
                $this->cloudinary->delete(substr($path, strlen(self::CLOUDINARY_PREFIX)), 'video');

                return true;
            } catch (Throwable $e) {
                $this->logStorageFailure('delete', $e);

                return false;
            }
        }

        try {
            $storage = Storage::disk($disk);

            return ! $storage->exists($path) || $storage->delete($path);
        } catch (Throwable $e) {
            $this->logStorageFailure('delete', $e, $disk);

            return false;
        }
    }

    // Uploads raw video bytes to the public disk.
    private function putPublic(string $bytes, string $directory, string $mime): ImageStorageResult
    {
        $uuid = Str::uuid()->toString();
        $folder = trim((string) config('services.cloudinary.folder', 'actify'), '/');
        $publicId = trim($folder.'/'.trim($directory, '/'), '/').'/'.$uuid;

        $result = $this->cloudinary->upload($bytes, $publicId, 'video', $mime);
        $storedPublicId = trim($result['public_id']);
        $secureUrl = trim($result['secure_url']);

        if ($storedPublicId === '') {
            throw new RuntimeException('Cloudinary returned an invalid video result.');
        }

        return new ImageStorageResult(self::CLOUDINARY_PREFIX.$storedPublicId, 'public', $storedPublicId, $secureUrl);
    }

    // Writes a storage problem to the log without leaking secrets.
    private function logStorageFailure(string $operation, Throwable $exception, string $provider = 'cloudinary'): void
    {
        Log::error('Video storage operation failed.', [
            'operation' => $operation,
            'provider' => $provider,
            'exception_class' => $exception::class,
            'exception_message' => mb_substr($exception->getMessage(), 0, 300),
        ]);
    }
}
