<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class OtpCooldownException extends RuntimeException
{
    public function __construct(
        public readonly int $remainingSeconds,
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : "OTP request is on cooldown. Please wait {$remainingSeconds} seconds."
        );
    }
}

