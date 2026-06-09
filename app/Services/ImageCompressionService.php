<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Server-side image compression safety net.
 * Client-side compression (canvas 1280px, JPEG 82%) is primary.
 * This service catches cases where client compression fails or is bypassed.
 */
class ImageCompressionService
{
    private const MAX_DIMENSION = 1280;
    private const INITIAL_QUALITY = 82;
    private const MIN_QUALITY = 70;
    private const QUALITY_STEP = 5;
    private const MAX_OUTPUT_BYTES = 3 * 1024 * 1024; // 3 MB
    private const MAX_INPUT_BYTES = 10 * 1024 * 1024; // 10 MB
    private const MAX_PIXEL_AREA = 4096 * 4096; // image bomb protection
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Validate and compress an uploaded image file.
     * Returns compressed image binary (JPEG) or null on failure.
     *
     * @param UploadedFile $file
     * @return array{content: string, mime: string, extension: string}|null
     */
    public function compressUploadedFile(UploadedFile $file): ?array
    {
        try {
            if (!$this->validate($file)) {
                return null;
            }

            $image = $this->manager->read($file->getPathname());

            return $this->processImage($image);
        } catch (\Throwable $e) {
            Log::error('ImageCompression: failed to compress uploaded file', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            return null;
        }
    }

    /**
     * Validate and compress raw image binary (e.g., from base64 decode).
     *
     * @param string $binary Raw image data
     * @return array{content: string, mime: string, extension: string}|null
     */
    public function compressBinary(string $binary): ?array
    {
        try {
            $size = strlen($binary);

            if ($size > self::MAX_INPUT_BYTES) {
                Log::warning('ImageCompression: binary exceeds max input size', [
                    'size_mb' => round($size / 1024 / 1024, 2),
                ]);
                return null;
            }

            // Validate mime from binary content
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($binary);

            if (!in_array($mime, self::ALLOWED_MIMES)) {
                Log::warning('ImageCompression: invalid mime from binary', [
                    'mime' => $mime,
                ]);
                return null;
            }

            // Check pixel dimensions before full decode (image bomb protection)
            $info = getimagesizefromstring($binary);
            if ($info === false) {
                Log::warning('ImageCompression: cannot read image dimensions from binary');
                return null;
            }

            $pixelArea = $info[0] * $info[1];
            if ($pixelArea > self::MAX_PIXEL_AREA) {
                Log::warning('ImageCompression: image bomb detected from binary', [
                    'width' => $info[0],
                    'height' => $info[1],
                    'pixels' => $pixelArea,
                ]);
                return null;
            }

            $image = $this->manager->read($binary);

            return $this->processImage($image);
        } catch (\Throwable $e) {
            Log::error('ImageCompression: failed to compress binary', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate uploaded file before processing.
     */
    private function validate(UploadedFile $file): bool
    {
        // Check file size
        if ($file->getSize() > self::MAX_INPUT_BYTES) {
            Log::warning('ImageCompression: file exceeds max input size', [
                'size_mb' => round($file->getSize() / 1024 / 1024, 2),
                'file' => $file->getClientOriginalName(),
            ]);
            return false;
        }

        // Check mime type
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES)) {
            Log::warning('ImageCompression: invalid mime type', [
                'mime' => $mime,
                'file' => $file->getClientOriginalName(),
            ]);
            return false;
        }

        // Check pixel dimensions (image bomb protection)
        $info = getimagesize($file->getPathname());
        if ($info === false) {
            Log::warning('ImageCompression: cannot read image dimensions', [
                'file' => $file->getClientOriginalName(),
            ]);
            return false;
        }

        $pixelArea = $info[0] * $info[1];
        if ($pixelArea > self::MAX_PIXEL_AREA) {
            Log::warning('ImageCompression: image bomb detected', [
                'width' => $info[0],
                'height' => $info[1],
                'pixels' => $pixelArea,
                'file' => $file->getClientOriginalName(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Resize and compress image with progressive quality reduction.
     *
     * @return array{content: string, mime: string, extension: string}
     */
    private function processImage(ImageInterface $image): array
    {
        // Resize proportionally if exceeds max dimension
        $width = $image->width();
        $height = $image->height();

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $image = $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
        }

        // Encode as JPEG with progressive quality reduction
        $quality = self::INITIAL_QUALITY;

        while ($quality >= self::MIN_QUALITY) {
            $encoded = $image->toJpeg($quality);
            $content = $encoded->toString();

            if (strlen($content) <= self::MAX_OUTPUT_BYTES) {
                Log::debug('ImageCompression: compressed successfully', [
                    'quality' => $quality,
                    'output_kb' => round(strlen($content) / 1024, 1),
                    'dimensions' => $image->width() . 'x' . $image->height(),
                ]);

                return [
                    'content' => $content,
                    'mime' => 'image/jpeg',
                    'extension' => 'jpg',
                ];
            }

            $quality -= self::QUALITY_STEP;
        }

        // Last resort: use lowest quality result even if >3MB
        // (shouldn't happen with 1280px max + quality 70)
        $encoded = $image->toJpeg(self::MIN_QUALITY);
        $content = $encoded->toString();

        Log::warning('ImageCompression: could not reduce below 3MB limit', [
            'final_kb' => round(strlen($content) / 1024, 1),
            'quality' => self::MIN_QUALITY,
        ]);

        return [
            'content' => $content,
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
        ];
    }
}
