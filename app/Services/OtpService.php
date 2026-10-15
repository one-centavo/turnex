<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactType;
use App\Models\SecurityCode;

class OtpService
{
    /**
     * The expiration time for an OTP in minutes.
     */
    public const int EXPIRATION_MINUTES = 5;

    /**
     * The cooldown time before a new OTP can be requested in seconds.
     */
    public const int COOLDOWN_SECONDS = 60;

    /**
     * The maximum allowed failed verification attempts.
     */
    public const int MAX_ATTEMPTS = 5;

    /**
     * Generate and dispatch a new OTP code.
     */
    public function generate(string $target, ContactType $channel): SecurityCode
    {
        // To be implemented in Task 10
        throw new \BadMethodCallException('Method generate() is not yet implemented.');
    }

    /**
     * Verify an entered OTP code against the active code for the given target.
     */
    public function verify(string $target, string $code, ContactType $channel): bool
    {
        // To be implemented in Task 12
        throw new \BadMethodCallException('Method verify() is not yet implemented.');
    }
}

