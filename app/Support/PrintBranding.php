<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Letterhead, signatories and footer of printed documents, from Admin > Settings >
 * Printing (config/settings.php, group 'printing').
 *
 * Two document types:
 *   reports  the clinic reports (reports/pdf/*: daily, monthly, annual, inventory,
 *            medicine usage, appointments) and their browser print versions
 *   health   the individual health record (patients/pdf/health-report and the
 *            patients/health-report page)
 *
 * Rendered by the shared partials reports/pdf/_letterhead and reports/pdf/_signatures.
 * PDFs (dompdf) get images as data URIs from the local file; browser print gets URLs.
 * With nothing configured every method returns "nothing", so documents print as before.
 */
class PrintBranding
{
    public const REPORTS = 'reports';
    public const HEALTH = 'health';

    public const SIGNATORIES = 3;

    /** Printable width in mm (A4 portrait minus the side margins of that document's PDF). */
    public const CONTENT_WIDTH_MM = [
        self::REPORTS => 182.0, // @page margin 14mm left and right
        self::HEALTH  => 174.0, // dompdf default 12mm margins plus 22px body padding
    ];

    /** Blank space (mm) left above the line when a signatory has no signature image. */
    public const BLANK_SIGNATURE_MM = 12;

    /** Largest file embedded in a PDF; resized uploads are far smaller. */
    private const MAX_EMBED_BYTES = 8 * 1024 * 1024;

    /** Used when neither a school nor a clinic name is set. Never the product name. */
    public const FALLBACK_NAME = 'School Clinic';

    // ─── Names and logo (never the product name or product logo) ────────────

    /** School or organisation name (Admin > Settings > General), '' when not set. */
    public static function orgName(): string
    {
        return trim((string) settings('org_name', ''));
    }

    /** Clinic name (Admin > Settings > Clinic), else the school name, else "School Clinic". */
    public static function clinicName(): string
    {
        return trim((string) settings('clinic_name', '')) ?: (self::orgName() ?: self::FALLBACK_NAME);
    }

    /** Who issues the document: the school when set, else the clinic. */
    public static function issuerName(): string
    {
        return self::orgName() ?: self::clinicName();
    }

    /** Local path of the uploaded school logo (school_logo, else brand_logo); null without an upload. */
    public static function logoPath(): ?string
    {
        return settings()->imagePath('school_logo', false) ?: settings()->imagePath('brand_logo', false);
    }

    /** URL of the uploaded school logo for browser print; null without an upload (no product mark). */
    public static function logoUrl(): ?string
    {
        foreach (['school_logo', 'brand_logo'] as $key) {
            if (settings()->imagePath($key, false)) {
                return settings()->imageUrl($key, '');
            }
        }

        return null;
    }

    /** Address, phone and email of the clinic on one line, empty parts left out. */
    public static function contactLine(): string
    {
        return collect([settings('clinic_address'), settings('clinic_contact'), settings('clinic_email')])
            ->map(fn ($v) => trim(preg_replace('/\s+/', ' ', (string) $v)))
            ->filter()
            ->implode(' · ');
    }

    // ─── Letterhead banner ───────────────────────────────────────────────────

    public static function hasBanner(): bool
    {
        return settings()->imagePath('print_banner', false) !== null;
    }

    /**
     * The banner as printed on a document, or null when none is uploaded.
     *
     * @return array{src:string, width_mm:float, height_mm:float, align:string}|null
     */
    public static function banner(string $document = self::REPORTS, bool $embed = true): ?array
    {
        $path = settings()->imagePath('print_banner', false);
        $size = $path ? @getimagesize($path) : false;
        if (! $size || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        $src = $embed ? self::dataUri($path) : settings()->imageUrl('print_banner');
        if (! $src) {
            return null;
        }

        $box = self::bannerBox(
            (int) $size[0],
            (int) $size[1],
            (int) settings('print_banner_height'),
            (string) settings('print_banner_fit'),
            self::contentWidth($document),
        );

        $align = (string) settings('print_banner_align');

        return $box + [
            'src'   => $src,
            'align' => in_array($align, ['left', 'center', 'right'], true) ? $align : 'center',
        ];
    }

    /**
     * Printed size of the banner in mm, keeping its shape.
     *   width    fills the printable width; the height setting is the most it may take
     *   natural  exactly the set height, never wider than the printable width
     * The settings page preview repeats this calculation in JavaScript.
     *
     * @return array{width_mm:float, height_mm:float}
     */
    public static function bannerBox(int $pixelWidth, int $pixelHeight, int $heightMm, string $fit, float $contentWidthMm): array
    {
        $ratio    = $pixelWidth / max(1, $pixelHeight);
        $heightMm = max(15, min(60, $heightMm ?: 30));

        if ($fit === 'natural') {
            $height = $heightMm;
            $width  = $height * $ratio;
        } else {
            $width  = $contentWidthMm;
            $height = $width / $ratio;
            if ($height > $heightMm) {
                $height = $heightMm;
                $width  = $height * $ratio;
            }
        }

        if ($width > $contentWidthMm) {
            $width  = $contentWidthMm;
            $height = $width / $ratio;
        }

        return ['width_mm' => round($width, 2), 'height_mm' => round($height, 2)];
    }

    // ─── Signatures ──────────────────────────────────────────────────────────

    /**
     * Signatories printed on a document, in order: the signed-in user ("Prepared by",
     * when turned on for this document), then signatories 1 to 3 that have a name
     * and are turned on for this document.
     *
     * @return list<array{caption:string, name:string, position:string, license:string, image:?string, image_width_mm:float, image_height_mm:float}>
     */
    public static function signatories(string $document, ?User $user = null, bool $embed = true): array
    {
        $suffix = self::suffix($document);
        $people = [];

        if ($user && settings("print_prepared_by_on_{$suffix}")) {
            $people[] = self::person(trim((string) settings('print_prepared_by_label')) ?: 'Prepared by', (string) $user->name);
        }

        for ($n = 1; $n <= self::SIGNATORIES; $n++) {
            $name = trim((string) settings("print_sig{$n}_name"));
            if ($name === '' || ! settings("print_sig{$n}_on_{$suffix}")) {
                continue;
            }

            $people[] = self::person(
                trim((string) settings("print_sig{$n}_label")),
                $name,
                trim((string) settings("print_sig{$n}_position")),
                trim((string) settings("print_sig{$n}_license")),
                "print_sig{$n}_image",
                (int) settings("print_sig{$n}_height"),
                $embed,
            );
        }

        // Fit each signature image in its column (at least three columns across the page).
        $columnMm = self::contentWidth($document) / max(3, count($people)) - 8;
        foreach ($people as &$person) {
            if ($person['image'] && $person['image_width_mm'] > $columnMm) {
                $person['image_height_mm'] = round($person['image_height_mm'] * $columnMm / $person['image_width_mm'], 2);
                $person['image_width_mm']  = round($columnMm, 2);
            }
        }
        unset($person);

        return $people;
    }

    /** Whether the signed-in user's "Prepared by" signature prints on this document. */
    public static function preparedByShown(string $document): bool
    {
        return (bool) settings('print_prepared_by_on_'.self::suffix($document));
    }

    /** "Prepared by" becomes "Prepared by:"; a caption that ends in punctuation is kept. */
    public static function caption(string $caption): string
    {
        $caption = trim($caption);

        return $caption === '' || preg_match('/[:.!?]$/u', $caption) ? $caption : $caption.':';
    }

    // ─── Footer ──────────────────────────────────────────────────────────────

    /** Footer line of a document ('' when turned off or empty), placeholders filled in. */
    public static function footerText(string $document): string
    {
        if (! settings('print_footer_on_'.self::suffix($document))) {
            return '';
        }

        // {app} is accepted by the shared placeholder check but printed documents never
        // carry the product name, so it prints the school (or clinic) name instead.
        $text = strtr((string) settings('print_footer_text'), [
            '{clinic}'         => self::clinicName(),
            '{school}'         => self::issuerName(),
            '{clinic_contact}' => trim((string) settings('clinic_contact')),
            '{app}'            => self::issuerName(),
        ]);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    public static function pageNumbers(string $document): bool
    {
        return (bool) settings('print_page_numbers_'.self::suffix($document));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public static function contentWidth(string $document): float
    {
        return self::CONTENT_WIDTH_MM[$document] ?? self::CONTENT_WIDTH_MM[self::REPORTS];
    }

    /**
     * A local image file as a data URI for dompdf, or null when it cannot be embedded.
     * A PNG with transparency is embedded flattened onto white (what paper shows anyway):
     * without Imagick, dompdf reads PNG transparency pixel by pixel, about a second per
     * banner on every PDF. The flattened copy is cached until the file changes.
     */
    public static function dataUri(?string $path, int $maxBytes = self::MAX_EMBED_BYTES): ?string
    {
        if (! $path || ! is_file($path) || ! is_readable($path) || filesize($path) > $maxBytes) {
            return null;
        }

        $mime = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'gif' => 'image/gif',
        ][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if (! $mime) {
            return null;
        }

        if ($mime === 'image/png' && self::pngHasAlpha($path)) {
            $flat = self::flattenedPng($path);
            if ($flat !== null) {
                return 'data:image/png;base64,'.$flat;
            }
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /** PNG colour type 4 or 6 (grey or colour with an alpha channel), read from the IHDR header. */
    private static function pngHasAlpha(string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 26);

        return strlen($head) === 26
            && str_starts_with($head, "\x89PNG\r\n\x1a\n")
            && in_array(ord($head[25]), [4, 6], true);
    }

    /** Base64 of the PNG composited onto white, cached per file version; null when GD cannot read it. */
    private static function flattenedPng(string $path): ?string
    {
        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        $key = 'print-branding.flat.'.md5($path.'|'.filemtime($path).'|'.filesize($path));

        try {
            return Cache::remember($key, now()->addDays(30), fn () => self::flatten($path));
        } catch (\Throwable) {
            return self::flatten($path); // cache store unavailable
        }
    }

    private static function flatten(string $path): ?string
    {
        $source = @imagecreatefrompng($path);
        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $flat = imagecreatetruecolor($width, $height);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagepng($flat, null, 6);
        $bytes = (string) ob_get_clean();
        imagedestroy($flat);

        return $bytes !== '' ? base64_encode($bytes) : null;
    }

    private static function suffix(string $document): string
    {
        return $document === self::HEALTH ? 'health' : 'reports';
    }

    private static function person(
        string $caption,
        string $name,
        string $position = '',
        string $license = '',
        ?string $imageKey = null,
        int $heightMm = 15,
        bool $embed = true,
    ): array {
        $image = null;
        $width = 0.0;
        $height = 0.0;

        $path = $imageKey ? settings()->imagePath($imageKey, false) : null;
        $size = $path ? @getimagesize($path) : false;
        if ($size && $size[0] > 0 && $size[1] > 0) {
            $image = $embed ? self::dataUri($path) : settings()->imageUrl($imageKey);
            $height = (float) max(8, min(30, $heightMm ?: 15));
            $width = round($height * $size[0] / $size[1], 2);
        }

        return [
            'caption'         => self::caption($caption),
            'name'            => $name,
            'position'        => $position,
            'license'         => $license,
            'image'           => $image,
            'image_width_mm'  => $image ? $width : 0.0,
            'image_height_mm' => $image ? $height : 0.0,
        ];
    }
}
