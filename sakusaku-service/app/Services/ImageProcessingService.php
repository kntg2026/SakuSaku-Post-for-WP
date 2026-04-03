<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageProcessingService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB
    private const DEFAULT_MAX_WIDTH = 1600;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(Driver::class);
    }

    public function process(string $imageUrl, int $tenantId, int $maxWidth = self::DEFAULT_MAX_WIDTH): ProcessedImage
    {
        // Handle data URI (base64-encoded images from Google Docs export)
        if (str_starts_with($imageUrl, 'data:')) {
            $data = $this->decodeDataUri($imageUrl);
        } else {
            $response = Http::timeout(30)->get($imageUrl);
            if (!$response->successful()) {
                throw new \RuntimeException("Failed to download image: HTTP {$response->status()}");
            }
            $data = $response->body();
        }

        $size = strlen($data);

        if ($size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException("Image too large: {$size} bytes (max " . self::MAX_FILE_SIZE . ")");
        }

        // Validate MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data);

        if (!in_array($mime, self::ALLOWED_MIMES)) {
            throw new \RuntimeException("Invalid image type: {$mime}");
        }

        // Process with Intervention Image
        $image = $this->manager->decodeBinary($data);
        $width = $image->width();
        $height = $image->height();

        // Resize if needed
        if ($width > $maxWidth) {
            $image = $image->scaleDown(width: $maxWidth);
            $width = $image->width();
            $height = $image->height();
        }

        // Determine output format and encode
        $ext = $this->mimeToExtension($mime);
        $quality = in_array($mime, ['image/jpeg', 'image/webp']) ? 85 : null;
        $encoded = $quality !== null
            ? $image->encodeUsingMediaType($mime, quality: $quality)
            : $image->encodeUsingMediaType($mime);

        $encodedData = (string) $encoded;

        // Save to temp
        $filename = Str::uuid() . ".{$ext}";
        $path = "temp/images/{$tenantId}/{$filename}";
        Storage::disk('local')->put($path, $encodedData);

        return new ProcessedImage(
            tempPath: Storage::disk('local')->path($path),
            width: $width,
            height: $height,
            fileSize: strlen($encodedData),
            mimeType: $mime,
            originalUrl: $imageUrl,
            filename: $filename,
        );
    }

    public function cleanup(int $tenantId, int $maxAgeHours = 24): int
    {
        $dir = "temp/images/{$tenantId}";
        if (!Storage::disk('local')->exists($dir)) {
            return 0;
        }

        $deleted = 0;
        $cutoff = now()->subHours($maxAgeHours)->timestamp;

        foreach (Storage::disk('local')->files($dir) as $file) {
            if (Storage::disk('local')->lastModified($file) < $cutoff) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function toBase64(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Image file not found: {$filePath}");
        }

        return base64_encode(file_get_contents($filePath));
    }

    private function decodeDataUri(string $dataUri): string
    {
        // Format: data:image/jpeg;base64,/9j/4AAQ...
        if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUri, $matches)) {
            throw new \RuntimeException('Invalid data URI format');
        }

        $data = base64_decode($matches[2], true);
        if ($data === false) {
            throw new \RuntimeException('Failed to decode base64 image data');
        }

        return $data;
    }

    private function mimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}

class ProcessedImage
{
    public function __construct(
        public readonly string $tempPath,
        public readonly int $width,
        public readonly int $height,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly string $originalUrl,
        public readonly string $filename,
    ) {}
}
