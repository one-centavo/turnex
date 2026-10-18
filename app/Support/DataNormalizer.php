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
