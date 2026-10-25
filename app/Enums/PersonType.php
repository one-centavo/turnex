<?php

declare(strict_types=1);

namespace App\Enums;

enum PersonType: string
{
    case Natural = 'NATURAL';
    case LegalEntity = 'JURIDICA';

    /**
     * Get the human-readable label for user-facing UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Natural => 'Persona Natural',
            self::LegalEntity => 'Persona Jurídica (Empresa)',
        };
    }
}
