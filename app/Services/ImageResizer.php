<?php

namespace App\Services;

use RuntimeException;

/**
 * Shrinks an uploaded image with GD before it is stored, so printed PDFs stay small.
 *
 * - Keeps the aspect ratio and never enlarges.
 * - Keeps transparency (PNG, and WebP with transparent pixels, are saved as PNG).
 *   A PNG without any transparent pixel is saved without an alpha channel.
 * - Always re-encodes, which drops EXIF, GPS and other metadata. JPEG photos are
 *   turned upright first when the EXIF extension is available.
 * - JPEG stays JPEG, PNG stays PNG, an opaque WebP becomes JPEG. dompdf prints all
 *   of these reliably.
 *
 * Used for the letterhead banner and signature images (Admin > Settings > Printing),
 * through the 'resize' key of an image setting in config/settings.php.
 */
class ImageResizer
{
    /** Larger images are refused at validation: decoding them needs too much memory. */
    public const MAX_PIXELS = 36_000_000;

    /**
     * @return array{contents:string, extension:string, mime:string, width:int, height:int}
     *
     * @throws RuntimeException when the file cannot be read or written as an image
     */
    public function fit(string $sourcePath, int $maxWidth, int $maxHeight): array
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD image extension is not available.');
        }

        $info = @getimagesize($sourcePath);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            throw new RuntimeException('The file is not a readable image.');
        }

        [$width, $height, $type] = $info;
        if ($width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('The image is too large to process.');
        }

        $this->ensureMemory($width, $height);

        $source = match ($type) {
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default        => false,
        };

        if (! $source) {
            throw new RuntimeException('The image could not be decoded.');
        }

        if (! imageistruecolor($source)) {
            // Palette PNGs: convert so the transparent colour becomes real alpha.
            imagepalettetotruecolor($source);
        }

        if ($type === IMAGETYPE_JPEG) {
            $source = $this->orient($source, $sourcePath);
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, $maxWidth / $width, $maxHeight / $height);
        $newW   = max(1, (int) round($width * $scale));
        $newH   = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newW, $newH);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($source);

        $transparent = $type !== IMAGETYPE_JPEG && $this->hasTransparency($target);
        $png = match ($type) {
            IMAGETYPE_PNG  => true,
            IMAGETYPE_JPEG => false,
            default        => $transparent,
        };

        if ($png && ! $transparent) {
            // Opaque PNG: saved without an alpha channel (smaller, and quicker for dompdf).
            // Composited onto white so a stray transparent pixel can never turn black.
            $opaque = imagecreatetruecolor($newW, $newH);
            imagefill($opaque, 0, 0, imagecolorallocate($opaque, 255, 255, 255));
            imagealphablending($opaque, true);
            imagecopy($opaque, $target, 0, 0, 0, 0, $newW, $newH);
            imagedestroy($target);
            $target = $opaque;
        }

        ob_start();
        $ok = $png ? imagepng($target, null, 9) : imagejpeg($target, null, 88);
        $contents = (string) ob_get_clean();
        imagedestroy($target);

        if (! $ok || $contents === '') {
            throw new RuntimeException('The image could not be saved.');
        }

        return [
            'contents'  => $contents,
            'extension' => $png ? 'png' : 'jpg',
            'mime'      => $png ? 'image/png' : 'image/jpeg',
            'width'     => $newW,
            'height'    => $newH,
        ];
    }

    /** True when any pixel is (partly) transparent. Checks a small copy to stay fast. */
    private function hasTransparency(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 400 / $w, 400 / $h);
        $sw = max(1, (int) round($w * $scale));
        $sh = max(1, (int) round($h * $scale));

        $sample = imagecreatetruecolor($sw, $sh);
        imagealphablending($sample, false);
        imagesavealpha($sample, true);
        imagecopyresampled($sample, $image, 0, 0, 0, 0, $sw, $sh, $w, $h);

        try {
            for ($y = 0; $y < $sh; $y++) {
                for ($x = 0; $x < $sw; $x++) {
                    if (((imagecolorat($sample, $x, $y) >> 24) & 0x7F) > 0) {
                        return true;
                    }
                }
            }

            return false;
        } finally {
            imagedestroy($sample);
        }
    }

    /** Turn a JPEG upright using its EXIF orientation (when the exif extension is loaded). */
    private function orient(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3, 4    => imagerotate($image, 180, 0),
            5, 6    => imagerotate($image, -90, 0),
            7, 8    => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated === false) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }

    /** Raise the memory limit for this request when a large image needs it. */
    private function ensureMemory(int $width, int $height): void
    {
        $limit = $this->bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return; // unlimited
        }

        // About 5 bytes per pixel for the decoded source, plus room for the copy.
        $needed = memory_get_usage() + (int) ($width * $height * 5 * 1.8) + 32 * 1024 * 1024;
        if ($needed > $limit) {
            @ini_set('memory_limit', (string) min($needed, 1024 * 1024 * 1024));
        }
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
