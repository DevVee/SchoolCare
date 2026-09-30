<?php

namespace App\Support;

/**
 * Branding for outgoing email (resources/views/vendor/mail, vendor/notifications):
 * the clinic's colour, logo and contact details from settings, with safe fallbacks.
 */
class MailBrand
{
    public const DEFAULT_COLOR = '#2563EB';

    public static function color(): string
    {
        return ColorScale::normalize(settings('brand_primary_color')) ?? self::DEFAULT_COLOR;
    }

    /** White or near-black, whichever reads better on the brand colour. */
    public static function colorText(): string
    {
        return ColorScale::contrastText(self::color());
    }

    public static function appName(): string
    {
        return (string) (settings('app_name') ?: config('app.name'));
    }

    /** Who the email comes from: the clinic name, or the app name when none is set. */
    public static function senderName(): string
    {
        return (string) (settings('clinic_name') ?: self::appName());
    }

    /**
     * Absolute URL of the uploaded logo, or null when there is none or it is a
     * format many email clients block (SVG, WebP).
     */
    public static function logoUrl(): ?string
    {
        $path = (string) settings('brand_logo', '');

        if ($path === '' || ! preg_match('/\.(png|jpe?g|gif)$/i', $path)) {
            return null;
        }

        return url(settings()->imageUrl('brand_logo'));
    }

    /** The clinic's email (settings) when it is a valid address; used as Reply-To. */
    public static function replyTo(): ?string
    {
        $email = trim((string) settings('clinic_email', ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Clinic address and "contact · email" for the footer; empty values left out. */
    public static function contactLines(): array
    {
        $reach = array_filter([
            trim((string) settings('clinic_contact', '')),
            trim((string) settings('clinic_email', '')),
        ]);

        return array_values(array_filter([
            trim((string) settings('clinic_address', '')),
            implode(' · ', $reach),
        ]));
    }
}
