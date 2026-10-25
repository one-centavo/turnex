<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case PendingVerification = 'PENDING_VERIFICATION';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case ActionRequired = 'ACTION_REQUIRED';
    case Rejected = 'REJECTED';

    /**
     * Get the human-readable label for user-facing UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pendiente de verificación',
            self::UnderReview => 'En revisión',
            self::Approved => 'Aprobado',
            self::ActionRequired => 'Acción requerida',
            self::Rejected => 'Rechazado',
        };
    }
}
