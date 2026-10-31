<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DataNormalizer as SupportDataNormalizer;

/**
 * Service wrapper for data sanitization and normalization (US-CORE-02).
 */
class DataNormalizer
{
    /**
     * Normalize general text by trimming edges and collapsing internal whitespace.
     */
    public static function text(string $text): string
    {
        return SupportDataNormalizer::text($text);
    }

    /**
     * Normalize nullable text, treating empty or whitespace-only inputs as null.
     */
    public static function nullableText(?string $text): ?string
    {
        return SupportDataNormalizer::nullableText($text);
    }

    /**
     * Normalize a name by trimming edges and collapsing internal whitespace.
     */
    public static function name(string $name): string
    {
        return SupportDataNormalizer::name($name);
    }

    /**
     * Normalize an email address to trimmed RFC lowercase format.
     */
    public static function email(string $email): string
    {
        return SupportDataNormalizer::email($email);
    }

    /**
     * Mask an email address for privacy display.
     */
    public static function maskEmail(string $email): string
    {
        return SupportDataNormalizer::maskEmail($email);
    }

    /**
     * Normalize a phone number to standard E.164 international format.
     */
    public static function phone(string $phone, string $defaultCountryCode = SupportDataNormalizer::DEFAULT_COUNTRY_CODE): string
    {
        return SupportDataNormalizer::phone($phone, $defaultCountryCode);
    }
}
