<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Support\DataNormalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessAuthService
{
    /**
     * Maximum number of failed login attempts allowed before lockout.
     */
    public const int MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in seconds when max attempts are exceeded.
     */
    public const int LOCKOUT_SECONDS = 60;

    /**
     * Generic error message displayed for invalid credentials.
     */
    public const string MESSAGE_INVALID_CREDENTIALS = 'Credenciales incorrectas. Por favor, intenta de nuevo.';

    /**
     * Clean and normalize the given email address.
     */
    public function normalizeEmail(string $email): string
    {
        return DataNormalizer::email($email);
    }

    /**
     * Find a business record matching the given email address.
     */
    public function findBusiness(string $email): ?Business
    {
        $normalized = $this->normalizeEmail($email);

        return Business::where('email', $normalized)->first();
    }

    /**
     * Generate the cache rate limiting throttle key based on email and IP address.
     */
    public function throttleKey(string $email, ?string $ipAddress = null): string
    {
        $normalized = $this->normalizeEmail($email);
        $ip = $ipAddress ?? request()->ip() ?? '127.0.0.1';

        return 'business_login:'.Str::transliterate(Str::lower($normalized).'|'.$ip);
    }

    /**
     * Determine if the user has too many failed login attempts.
     */
    public function hasTooManyAttempts(string $email, ?string $ipAddress = null): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($email, $ipAddress), self::MAX_ATTEMPTS);
    }

    /**
     * Get the number of seconds until the rate limit is cleared.
     */
    public function availableIn(string $email, ?string $ipAddress = null): int
    {
        return RateLimiter::availableIn($this->throttleKey($email, $ipAddress));
    }

    /**
     * Increment the failed login attempt counter.
     */
    public function hitRateLimiter(string $email, ?string $ipAddress = null): void
    {
        RateLimiter::hit($this->throttleKey($email, $ipAddress), self::LOCKOUT_SECONDS);
    }

    /**
     * Clear the rate limiter attempts counter.
     */
    public function clearRateLimiter(string $email, ?string $ipAddress = null): void
    {
        RateLimiter::clear($this->throttleKey($email, $ipAddress));
    }

    /**
     * Authenticate a business operator using their email and password.
     *
     * @throws ValidationException
     */
    public function authenticate(
        string $email,
        string $password,
        bool $remember = false,
        ?string $ipAddress = null,
    ): Business {
        if ($this->hasTooManyAttempts($email, $ipAddress)) {
            $seconds = max(1, $this->availableIn($email, $ipAddress));

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos de inicio de sesión. Por favor espera {$seconds} segundos antes de intentar nuevamente.",
            ]);
        }

        $normalized = $this->normalizeEmail($email);

        $attempted = Auth::guard('business')->attempt([
            'email' => $normalized,
            'password' => $password,
        ], $remember);

        if (! $attempted) {
            $this->hitRateLimiter($email, $ipAddress);

            throw ValidationException::withMessages([
                'email' => __(self::MESSAGE_INVALID_CREDENTIALS),
            ]);
        }

        $this->clearRateLimiter($email, $ipAddress);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        /** @var Business $authenticatedBusiness */
        $authenticatedBusiness = Auth::guard('business')->user();

        return $authenticatedBusiness;
    }

    /**
     * Log out the business operator and invalidate their session.
     */
    public function logout(): void
    {
        Auth::guard('business')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}

