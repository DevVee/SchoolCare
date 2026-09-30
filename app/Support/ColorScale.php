<?php

namespace App\Support;

/**
 * Builds a 50 to 950 tint/shade scale from one brand colour so the UI can be
 * rebranded at runtime (x-ui.brand-style emits the result as CSS variables).
 *
 * The input colour becomes step 600. Lighter steps mix it with white, darker
 * steps mix it with black, matching the shape of the compiled default scale
 * (Tailwind blue around #2563EB).
 */
class ColorScale
{
    /** Share of white mixed into the base colour for the light steps. */
    private const TINTS = [50 => 0.94, 100 => 0.87, 200 => 0.74, 300 => 0.56, 400 => 0.34, 500 => 0.14];

    /** Share of black mixed into the base colour for the dark steps. */
    private const SHADES = [700 => 0.16, 800 => 0.32, 900 => 0.45, 950 => 0.62];

    /** Returns the normalised "#RRGGBB" or null when the value is not a 6-digit hex colour. */
    public static function normalize(mixed $hex): ?string
    {
        if (! is_string($hex)) {
            return null;
        }
        $hex = trim($hex);

        return preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m) ? '#'.strtoupper($m[1]) : null;
    }

    /**
     * @return array<int, string> step => "#RRGGBB" (50, 100, ..., 950)
     */
    public static function scale(string $hex): array
    {
        $base = self::toRgb($hex);
        $scale = [];
        foreach (self::TINTS as $step => $amount) {
            $scale[$step] = self::toHex(self::mix($base, [255, 255, 255], $amount));
        }
        $scale[600] = self::toHex($base);
        foreach (self::SHADES as $step => $amount) {
            $scale[$step] = self::toHex(self::mix($base, [0, 0, 0], $amount));
        }
        ksort($scale);

        return $scale;
    }

    /** "r, g, b" string for rgba(var(--x-rgb), a). */
    public static function rgb(string $hex): string
    {
        return implode(', ', self::toRgb($hex));
    }

    /** White or slate-900, whichever reads better on the colour (WCAG contrast). */
    public static function contrastText(string $hex): string
    {
        $l = self::luminance(self::toRgb($hex));
        $withWhite = 1.05 / ($l + 0.05);
        $withInk = ($l + 0.05) / (self::luminance([15, 23, 42]) + 0.05);

        return $withWhite >= 4.5 || $withWhite >= $withInk ? '#FFFFFF' : '#0F172A';
    }

    /**
     * CSS custom properties that override the compiled brand defaults.
     * Returns [] when the colour is invalid or equal to the default, so the
     * compiled stylesheet is used untouched.
     *
     * @return array<string, string> "--brand-600" => "#2563EB", ...
     */
    public static function cssVariables(mixed $hex, string $default = '#2563EB'): array
    {
        $hex = self::normalize($hex);
        if ($hex === null || $hex === self::normalize($default)) {
            return [];
        }

        $vars = [];
        foreach (self::scale($hex) as $step => $value) {
            $vars['--brand-'.$step] = $value;
        }
        $vars['--brand-rgb'] = self::rgb($hex);
        $vars['--brand-700-rgb'] = self::rgb($vars['--brand-700']);
        $vars['--brand-contrast'] = self::contrastText($hex);

        return $vars;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function toRgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex) ?? '#000000', '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', ...array_map(fn ($c) => max(0, min(255, (int) round($c))), $rgb));
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     * @return array{0:float,1:float,2:float}
     */
    private static function mix(array $a, array $b, float $amountOfB): array
    {
        return [
            $a[0] + ($b[0] - $a[0]) * $amountOfB,
            $a[1] + ($b[1] - $a[1]) * $amountOfB,
            $a[2] + ($b[2] - $a[2]) * $amountOfB,
        ];
    }

    /** @param array{0:int|float,1:int|float,2:int|float} $rgb */
    private static function luminance(array $rgb): float
    {
        $channels = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
