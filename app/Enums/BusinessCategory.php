<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessCategory: string
{
    case Restaurant = 'restaurant';
    case HealthAndBeauty = 'health_and_beauty';
    case ProfessionalServices = 'professional_services';
    case Retail = 'retail';
    case Banking = 'banking';
    case Other = 'other';

    /**
     * Get the human-readable label for user-facing UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Restaurant => 'Restaurante / Gastronomía',
            self::HealthAndBeauty => 'Salud y Belleza',
            self::ProfessionalServices => 'Servicios Profesionales',
            self::Retail => 'Comercio / Retail',
            self::Banking => 'Banca / Finanzas',
            self::Other => 'Otro',
        };
    }
}
