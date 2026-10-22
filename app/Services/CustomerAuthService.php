<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Support\DataNormalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAuthService
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
     * Guidance message displayed for accounts registered with Google without a password.
     */
    public const string MESSAGE_GOOGLE_ACCOUNT = 'Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google.';

    /**
     * Determine whether an identifier is an email address or a mobile phone number.
     *
     * @return 'email'|'phone_number'
     */
    public function determineIdentifierType(string $identifier): string
    {
        $trimmed = trim($identifier);

        return str_contains($trimmed, '@') ? 'email' : 'phone_number';
    }

    /**
     * Clean and normalize the given identifier according to its detected type.
     */
    public function normalizeIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);

        if ($this->determineIdentifierType($trimmed) === 'email') {
            return DataNormalizer::email($trimmed);
        }

        return DataNormalizer::phone($trimmed);
    }

    /**
     * Find a customer record matching the given identifier.
     */
    public function findCustomer(string $identifier): ?Customer
    {
        $type = $this->determineIdentifierType($identifier);
        $normalized = $this->normalizeIdentifier($identifier);

        return Customer::where($type, $normalized)->first();
    }

    /**
     * Determine whether the customer account was registered via Google without a local password.
     */
    public function isGoogleOnlyAccount(Customer $customer): bool
    {
        return $customer->password === null;
    }

    /**
     * Generate the cache rate limiting throttle key based on identifier and IP address.
     */
    public function throttleKey(string $identifier, ?string $ipAddress = null): string
    {
        $normalized = $this->normalizeIdentifier($identifier);
        $ip = $ipAddress ?? request()->ip() ?? '127.0.0.1';

        return 'customer_login:'.Str::transliterate(Str::lower($normalized).'|'.$ip);
    }

    /**
     * Determine if the user has too many failed login attempts.
     */
    public function hasTooManyAttempts(string $identifier, ?string $ipAddress = null): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($identifier, $ipAddress), self::MAX_ATTEMPTS);
    }

    /**
     * Get the number of seconds until the rate limit is cleared.
     */
    public function availableIn(string $identifier, ?string $ipAddress = null): int
    {
        return RateLimiter::availableIn($this->throttleKey($identifier, $ipAddress));
    }

    /**
     * Increment the failed login attempt counter.
     */
    public function hitRateLimiter(string $identifier, ?string $ipAddress = null): void
    {
        RateLimiter::hit($this->throttleKey($identifier, $ipAddress), self::LOCKOUT_SECONDS);
    }

    /**
     * Clear the rate limiter attempts counter.
     */
    public function clearRateLimiter(string $identifier, ?string $ipAddress = null): void
    {
        RateLimiter::clear($this->throttleKey($identifier, $ipAddress));
    }

    /**
     * Authenticate a customer using their identifier and password.
     *
     * @throws ValidationException
     */
    public function authenticate(
        string $identifier,
        string $password,
        bool $remember = false,
        ?string $ipAddress = null,
    ): Customer {
        if ($this->hasTooManyAttempts($identifier, $ipAddress)) {
            $seconds = max(1, $this->availableIn($identifier, $ipAddress));

            throw ValidationException::withMessages([
                'identifier' => "Demasiados intentos de inicio de sesión. Por favor espera {$seconds} segundos antes de intentar nuevamente.",
            ]);
        }

        $customer = $this->findCustomer($identifier);

        if ($customer !== null && $this->isGoogleOnlyAccount($customer)) {
            $this->hitRateLimiter($identifier, $ipAddress);

            throw ValidationException::withMessages([
                'identifier' => __(self::MESSAGE_GOOGLE_ACCOUNT),
            ]);
        }

        $type = $this->determineIdentifierType($identifier);
        $normalized = $this->normalizeIdentifier($identifier);

        $attempted = Auth::guard('customer')->attempt([
            $type => $normalized,
            'password' => $password,
        ], $remember);

        if (! $attempted) {
            $this->hitRateLimiter($identifier, $ipAddress);

            throw ValidationException::withMessages([
                'identifier' => __(self::MESSAGE_INVALID_CREDENTIALS),
            ]);
        }

        $this->clearRateLimiter($identifier, $ipAddress);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        /** @var Customer $authenticatedCustomer */
        $authenticatedCustomer = Auth::guard('customer')->user();

        return $authenticatedCustomer;
    }
}
