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

// Saves, resizes and deletes the pictures users upload.
final class ImageStore
{
    private const CLOUDINARY_PREFIX = 'cloudinary:';

    // Remembers how to talk to Cloudinary.
    public function __construct(private readonly CloudinaryImageGateway $cloudinary) {}

    // A profile picture: cropped to a square, avatar_px wide.
    public function storeAvatar(UploadedFile $file, string $field = 'photo'): ImageStorageResult
    {
        $bytes = $this->encode($file, (int) config('actify.images.avatar_px'), square: true, field: $field);

        return $this->put('public', 'avatars', $bytes);
    }

    // A photo of a problem or of finished work.
    public function storeReportPhoto(UploadedFile $file, string $disk = 'public', string $directory = 'reports', string $field = 'photo'): ImageStorageResult
    {
        $bytes = $this->encode($file, (int) config('actify.images.report_max_side'), square: false, field: $field);

        return $this->put($disk, $directory, $bytes);
    }

    // A guest's selfie. Always the private disk.
    public function storeSelfie(UploadedFile $file): ImageStorageResult
    {
        return $this->storeReportPhoto($file, 'cloudinary-private', 'guest-selfies', 'selfie');
    }

    // Delete either a Cloudinary public asset or a legacy/local filesystem image.
    // Private guest photos and selfies always remain on the private local disk.
    public function delete(?string $path, string $disk = 'public'): bool
    {
        if (! $path) {
            return true;
        }

        if ($this->isCloudinaryPath($path)) {
            try {
                $this->cloudinary->delete($this->cloudinaryPublicId($path));

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

    // Uploads a pending guest report photo but deliberately keeps its private source until the database update commits.
    // Legacy pending/ paths remain eligible; selfie paths are never eligible for promotion.
    public function promoteGuestReportPhoto(string $path): ImageStorageResult
    {
        if ($this->isCloudinaryPath($path)) {
            $publicId = $this->cloudinaryPublicId($path);
            if (! str_contains($publicId, 'guest-reports/') && ! str_contains($publicId, 'pending/')) {
                throw new InvalidArgumentException('Only guest report photos can be made public.');
            }

            return new ImageStorageResult($path, 'public', $publicId);
        }

        if (! str_starts_with($path, 'guest-reports/') && ! str_starts_with($path, 'pending/')) {
            throw new InvalidArgumentException('Only guest report photos can be made public.');
        }

        try {
            $bytes = Storage::disk('private')->get($path);
        } catch (Throwable $e) {
            $this->logStorageFailure('read', $e, 'private');
            throw new RuntimeException('Unable to read the private report photo.');
        }

        if (! is_string($bytes)) {
            throw new RuntimeException('Unable to read the private report photo.');
        }

        return $this->put('public', 'reports', $bytes);
    }

    // Return a browser-ready URL. Existing local public paths remain supported,
    // so images uploaded before the Cloudinary migration continue to display.
    public function url(?string $path, string $disk = 'public'): ?string
    {
        if (! $path || $disk === 'private') {
            return null;
        }

        if ($this->isCloudinaryPath($path)) {
            return $this->cloudinary->secureUrl($this->cloudinaryPublicId($path));
        }

        return Storage::disk($disk)->url($path);
    }

    // Sends the image bytes to Cloudinary or the private disk.
    private function put(string $disk, string $directory, string $bytes): ImageStorageResult
    {
        $uuid = Str::uuid()->toString();

        if ($disk === 'public' || $disk === 'cloudinary-private') {
            $folder = trim((string) config('services.cloudinary.folder', 'actify'), '/');
            $publicId = trim($folder.'/'.trim($directory, '/'), '/').'/'.$uuid;
            $storedPublicId = null;

            try {
                $result = $this->cloudinary->upload($bytes, $publicId);
                $storedPublicId = trim($result['public_id']);
                $secureUrl = trim($result['secure_url']);
                $urlParts = parse_url($secureUrl);

                if ($storedPublicId === '' || filter_var($secureUrl, FILTER_VALIDATE_URL) === false || ($urlParts['scheme'] ?? null) !== 'https') {
                    throw new RuntimeException('Cloudinary returned an invalid image result.');
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
                        $this->cloudinary->delete($storedPublicId);
                    } catch (Throwable $cleanupError) {
                        $this->logStorageFailure('cleanup', $cleanupError);
                    }
                }

                $this->logStorageFailure('upload', $e);
                throw new RuntimeException('Unable to upload the image. Please try again.');
            }
        }

        if ($disk !== 'private') {
            throw new InvalidArgumentException('Images may only be stored on the public or private disk.');
        }

        $path = trim($directory, '/').'/'.$uuid.'.jpg';

        try {
            if (! Storage::disk('private')->put($path, $bytes)) {
                throw new RuntimeException('Private image write failed.');
            }
        } catch (Throwable $e) {
            $this->logStorageFailure('write', $e, 'private');
            throw new RuntimeException('Unable to store the private image.');
        }

        return new ImageStorageResult($path, 'private');
    }

    // Checks if a stored path points at Cloudinary.
    private function isCloudinaryPath(string $path): bool
    {
        return str_starts_with($path, self::CLOUDINARY_PREFIX);
    }

    // Strips the cloudinary: prefix to get the real Cloudinary id.
    private function cloudinaryPublicId(string $path): string
    {
        return substr($path, strlen(self::CLOUDINARY_PREFIX));
    }

    // Writes a storage problem to the log without leaking secrets.
    private function logStorageFailure(string $operation, Throwable $exception, string $provider = 'cloudinary'): void
    {
        Log::error('Image storage operation failed.', [
            'operation' => $operation,
            'provider' => $provider,
            'exception_class' => $exception::class,
            'exception_message' => mb_substr($exception->getMessage(), 0, 300),
        ]);
    }

    // The decoding / resizing / re-encoding. Returns the bytes of a new JPEG.
    public function encode(UploadedFile $file, int $maxSide, bool $square, string $field = 'photo'): string
    {
        $unreadable = ValidationException::withMessages([
            $field => ['That file is not a picture we can read. Please choose a JPG, PNG or WebP photo.'],
        ]);

        $path = $file->getRealPath();
        $size = $path ? @getimagesize($path) : false;
        if ($size === false) {
            throw $unreadable;
        }

        [$width, $height] = $size;
        if ($width < 1 || $height < 1) {
            throw $unreadable;
        }
        if ($width * $height > (int) config('actify.images.max_megapixels') * 1_000_000) {
            throw ValidationException::withMessages([
                $field => ['That picture is too large. Please use a smaller photo.'],
            ]);
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if ($source === false) {
            throw $unreadable;
        }

        $source = $this->applyExifOrientation($source, $path, $size[2] ?? 0);
        $width = imagesx($source);
        $height = imagesy($source);

        if ($square) {
            $side = min($width, $height);
            $target = min($maxSide, $side);
            $crop = ['x' => intdiv($width - $side, 2), 'y' => intdiv($height - $side, 2), 'w' => $side, 'h' => $side];
            $outW = $outH = $target;
        } else {
            $scale = min(1, $maxSide / max($width, $height));
            $outW = max(1, (int) round($width * $scale));
            $outH = max(1, (int) round($height * $scale));
            $crop = ['x' => 0, 'y' => 0, 'w' => $width, 'h' => $height];
        }

        $canvas = imagecreatetruecolor($outW, $outH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, $crop['x'], $crop['y'], $outW, $outH, $crop['w'], $crop['h']);

        ob_start();
        imagejpeg($canvas, null, (int) config('actify.images.jpeg_quality'));
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $bytes;
    }

    // Turns the photo the right way up when a phone stored a "rotate me" note.
    private function applyExifOrientation(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }
}
