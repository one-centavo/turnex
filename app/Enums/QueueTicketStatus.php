<?php

declare(strict_types=1);

namespace App\Enums;

enum QueueTicketStatus: string
{
    case Pending = 'PENDING';
    case Preparing = 'PREPARING';
    case Ready = 'READY';
    case Called = 'CALLED';
    case InService = 'IN_SERVICE';
    case Skipped = 'SKIPPED';
    case Completed = 'COMPLETED';
    case NoShow = 'NO_SHOW';
    case Cancelled = 'CANCELLED';

    /**
     * Get all active queue ticket statuses.
     *
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return [
            self::Pending,
            self::Preparing,
            self::Ready,
            self::Called,
            self::InService,
            self::Skipped,
        ];
    }

    /**
     * Determine if the ticket status is active.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Pending,
            self::Preparing,
            self::Ready,
            self::Called,
            self::InService,
            self::Skipped => true,
            default => false,
        };
    }

    /**
     * Determine if the ticket status is terminal.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed,
            self::NoShow,
            self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Get the human-readable label for user-facing UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En espera',
            self::Preparing => 'En preparación',
            self::Ready => 'Listo para entrega',
            self::Called => 'Llamado',
            self::InService => 'En atención',
            self::Skipped => 'Pospuesto',
            self::Completed => 'Completado',
            self::NoShow => 'No se presentó',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Get the badge color name associated with the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Preparing => 'sky',
            self::Ready => 'emerald',
            self::Called => 'indigo',
            self::InService => 'blue',
            self::Skipped => 'orange',
            self::Completed => 'zinc',
            self::NoShow => 'rose',
            self::Cancelled => 'zinc',
        };
    }
}
