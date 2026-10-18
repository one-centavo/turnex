<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactType;
use App\Exceptions\OtpCooldownException;
use App\Models\SecurityCode;
use App\Notifications\SendOtpNotification;
use App\Support\DataNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

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
     * The default country code for phone numbers in E.164 format (Colombia for MVP).
     */
    public const string DEFAULT_COUNTRY_CODE = '+57';

    /**
     * Normalize a contact target based on its channel type.
     */
    public function normalizeTarget(string $target, ContactType $channel): string
    {
        return match ($channel) {
            ContactType::Email => $this->normalizeEmail($target),
            ContactType::Sms, ContactType::WhatsApp => $this->normalizePhone($target),
        };
    }

    /**
     * Normalize an email address to trimmed RFC lowercase format.
     */
    public function normalizeEmail(string $email): string
    {
        return DataNormalizer::email($email);
    }

    /**
     * Normalize a phone number to standard E.164 international format.
     */
    public function normalizePhone(string $phone): string
    {
        return DataNormalizer::phone($phone, self::DEFAULT_COUNTRY_CODE);
    }

    /**
     * Generate and dispatch a new OTP code for the given contact target.
     */
    public function generate(string $target, ContactType $channel): SecurityCode
    {
        $normalizedTarget = $this->normalizeTarget($target, $channel);

        $remainingSeconds = $this->getRemainingCooldownSeconds($normalizedTarget);
        if ($remainingSeconds > 0) {
            throw new OtpCooldownException($remainingSeconds);
        }

        $plainCode = $this->generatePlainCode();

        return DB::transaction(function () use ($normalizedTarget, $channel, $plainCode): SecurityCode {
            $this->invalidatePreviousCodes($normalizedTarget);

            $securityCode = SecurityCode::create([
                'contact_value' => $normalizedTarget,
                'contact_type' => $channel,
                'code' => Hash::make($plainCode),
                'attempts_count' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
                'used_at' => null,
            ]);

            Notification::route($channel->value, $normalizedTarget)
                ->notify(new SendOtpNotification($plainCode, $channel));

            return $securityCode;
        });
    }

    /**
     * Generate a cryptographically secure, zero-padded 6-digit numeric string.
     */
    protected function generatePlainCode(): string
    {
        return sprintf('%06d', random_int(0, 999999));
    }

    /**
     * Invalidate any active, unused security codes for the given contact target.
     */
    protected function invalidatePreviousCodes(string $normalizedTarget): int
    {
        return SecurityCode::where('contact_value', $normalizedTarget)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);
    }

    /**
     * Get the remaining cooldown seconds before a new OTP can be requested for the contact target.
     */
    public function getRemainingCooldownSeconds(string $target, ?ContactType $channel = null): int
    {
        $normalizedTarget = $channel !== null
            ? $this->normalizeTarget($target, $channel)
            : trim($target);

        $latest = SecurityCode::where('contact_value', $normalizedTarget)
            ->latest('created_at')
            ->first();

        if ($latest === null || $latest->created_at === null) {
            return 0;
        }

        $secondsPassed = (int) $latest->created_at->diffInSeconds(now());

        if ($secondsPassed < self::COOLDOWN_SECONDS) {
            return self::COOLDOWN_SECONDS - $secondsPassed;
        }

        return 0;
    }

    /**
     * Verify an entered OTP code against the active code for the given target.
     */
    public function verify(string $target, string $code, ContactType $channel): bool
    {
        $trimmedCode = trim($code);
        $normalizedTarget = $this->normalizeTarget($target, $channel);

        $securityCode = SecurityCode::where('contact_value', $normalizedTarget)
            ->where('contact_type', $channel)
            ->latest('created_at')
            ->first();

        if ($securityCode === null) {
            return false;
        }

        // 1. Single-use check: already used codes are rejected
        if ($securityCode->isUsed()) {
            return false;
        }

        // 2. Threshold check: if maximum attempts already reached, code is invalidated
        if ($securityCode->hasExceededAttempts()) {
            return false;
        }

        // 3. Precedence rule: expired codes are rejected without incrementing the attempt counter
        if ($securityCode->isExpired()) {
            return false;
        }

        // 4. Cryptographic hash verification
        if (! Hash::check($trimmedCode, $securityCode->code)) {
            $securityCode->increment('attempts_count');

            // Upon reaching the 5th failed attempt, immediately invalidate the code
            if ($securityCode->attempts_count >= self::MAX_ATTEMPTS) {
                $securityCode->invalidate();
            }

            return false;
        }

        // 5. Successful verification: atomically mark code as used
        $securityCode->markAsUsed();

        return true;
    }
}
