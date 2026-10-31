<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class DataNormalizer
{
    /**
     * Default international country calling code (Colombia).
     */
    public const string DEFAULT_COUNTRY_CODE = '+57';

    /**
     * Normalize general text by trimming edges and collapsing internal whitespace.
     */
    public static function text(string $text): string
    {
        return Str::squish(trim($text));
    }

    /**
     * Normalize nullable text, treating empty or whitespace-only inputs as null.
     */
    public static function nullableText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = Str::squish(trim($text));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize a name by trimming edges and collapsing internal whitespace.
     */
    public static function name(string $name): string
    {
        return Str::squish(trim($name));
    }

    /**
     * Normalize an email address to trimmed RFC lowercase format.
     */
    public static function email(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Mask an email address for privacy display (e.g. c***z@example.com).
     */
    public static function maskEmail(string $email): string
    {
        $normalized = self::email($email);
        $parts = explode('@', $normalized, 2);

        if (count($parts) !== 2) {
            return $normalized;
        }

        [$username, $domain] = $parts;
        $length = mb_strlen($username, 'UTF-8');

        if ($length <= 2) {
            $maskedUsername = mb_substr($username, 0, 1, 'UTF-8').'*';
        } else {
            $first = mb_substr($username, 0, 1, 'UTF-8');
            $last = mb_substr($username, -1, 1, 'UTF-8');
            $maskedUsername = $first.str_repeat('*', min(4, max(2, $length - 2))).$last;
        }

        return $maskedUsername.'@'.$domain;
    }

    /**
     * Normalize a phone number to standard E.164 international format.
     */
    public static function phone(string $phone, string $defaultCountryCode = self::DEFAULT_COUNTRY_CODE): string
    {
        $cleaned = trim($phone);

        // Remove spaces, hyphens, parentheses, and dots
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $cleaned) ?? '';

        // If it already starts with '+', keep the '+' and keep only numeric digits afterwards
        if (str_starts_with($cleaned, '+')) {
            return '+'.preg_replace('/[^\d]/', '', substr($cleaned, 1));
        }

        // Strip any remaining non-digit characters
        $digits = preg_replace('/[^\d]/', '', $cleaned) ?? '';

        // If digits start with Colombian country code '57' followed by 10 digits
        if (str_starts_with($digits, '57') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        // Standard 10-digit Colombian mobile number
        if (strlen($digits) === 10) {
            return $defaultCountryCode.$digits;
        }

        // Fallback: prepend '+' to the digits
        return '+'.$digits;
    }
}
