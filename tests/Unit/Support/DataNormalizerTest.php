<?php

declare(strict_types=1);

use App\Support\DataNormalizer;

describe('DataNormalizer::name', function (): void {
    test('it trims leading and trailing whitespace', function (): void {
        expect(DataNormalizer::name('   Carlos Andrés   '))->toBe('Carlos Andrés');
    });

    test('it collapses multiple consecutive spaces into a single space', function (): void {
        expect(DataNormalizer::name('Juan    Carlos    Pérez'))->toBe('Juan Carlos Pérez');
    });

    test('it preserves Spanish accents and characters', function (): void {
        expect(DataNormalizer::name('  María   José   Ñuñez  '))->toBe('María José Ñuñez');
    });

    test('it returns empty string when given only whitespace', function (): void {
        expect(DataNormalizer::name('     '))->toBe('');
    });
});

describe('DataNormalizer::email', function (): void {
    test('it trims leading and trailing whitespace', function (): void {
        expect(DataNormalizer::email('  user@turnex.test  '))->toBe('user@turnex.test');
    });

    test('it normalizes email to lowercase UTF-8 format', function (): void {
        expect(DataNormalizer::email('User.Name+Tag@EXAMPLE.COM'))->toBe('user.name+tag@example.com');
    });

    test('it handles mixed casing and surrounding whitespace simultaneously', function (): void {
        expect(DataNormalizer::email('   CARLOS.PEREZ@GMAIL.COM   '))->toBe('carlos.perez@gmail.com');
    });
});

describe('DataNormalizer::phone', function (): void {
    test('it normalizes a 10-digit Colombian mobile number with default +57 prefix', function (): void {
        expect(DataNormalizer::phone('3001234567'))->toBe('+573001234567');
    });

    test('it strips spaces, dashes, parentheses, and dots before formatting', function (): void {
        expect(DataNormalizer::phone('(300) 123-4567'))->toBe('+573001234567')
            ->and(DataNormalizer::phone('300.123.4567'))->toBe('+573001234567')
            ->and(DataNormalizer::phone('  300  123  4567  '))->toBe('+573001234567');
    });

    test('it normalizes a 12-digit number starting with 57 into E.164 format with plus', function (): void {
        expect(DataNormalizer::phone('573001234567'))->toBe('+573001234567');
    });

    test('it preserves international numbers already formatted with plus prefix', function (): void {
        expect(DataNormalizer::phone('+1 202-555-0123'))->toBe('+12025550123')
            ->and(DataNormalizer::phone('+57 (300) 123 4567'))->toBe('+573001234567');
    });

    test('it respects custom country codes when provided', function (): void {
        expect(DataNormalizer::phone('5551234567', '+1'))->toBe('+15551234567');
    });
});
