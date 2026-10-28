<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Services\BusinessAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    RateLimiter::clear('business_login:contacto@panaderia.test|127.0.0.1');
});

test('it normalizes email address', function (): void {
    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    expect($service->normalizeEmail('  Contacto@Panaderia.Test  '))->toBe('contacto@panaderia.test')
        ->and($service->normalizeEmail('BUSINESS@EXAMPLE.COM'))->toBe('business@example.com');
});

test('it finds business by normalized email', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
    ]);

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    expect($service->findBusiness('Contacto@Panaderia.Test')?->id)->toBe($business->id)
        ->and($service->findBusiness('  contacto@panaderia.test  ')?->id)->toBe($business->id)
        ->and($service->findBusiness('nonexistent@panaderia.test'))->toBeNull();
});

test('it successfully authenticates a business operator with email', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    $authenticated = $service->authenticate('  Contacto@Panaderia.Test  ', 'Turnex@2026!');

    expect($authenticated->id)->toBe($business->id)
        ->and(Auth::guard('business')->check())->toBeTrue()
        ->and(Auth::guard('business')->id())->toBe($business->id);
});

test('it supports remember me persistent token upon business login', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!'),
        'remember_token' => null,
    ]);

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    $service->authenticate('contacto@panaderia.test', 'Turnex@2026!', remember: true);

    $business->refresh();

    expect($business->getRememberToken())->not->toBeNull();
});

test('it regenerates the session upon successful business authentication', function (): void {
    Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    Session::start();
    $initialSessionId = Session::getId();

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    $service->authenticate('contacto@panaderia.test', 'Turnex@2026!');

    expect(Session::getId())->not->toBe($initialSessionId);
});

test('it throws validation exception with generic error on invalid business password', function (): void {
    Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    $capturedException = null;
    try {
        $service->authenticate('contacto@panaderia.test', 'WrongPassword123!');
    } catch (ValidationException $e) {
        $capturedException = $e;
    }

    expect($capturedException)->not->toBeNull()
        ->and($capturedException?->errors())->toHaveKey('email')
        ->and($capturedException?->errors()['email'][0])->toBe(BusinessAuthService::MESSAGE_INVALID_CREDENTIALS)
        ->and(Auth::guard('business')->check())->toBeFalse();
});

test('it throws validation exception with generic error on non-existent business email', function (): void {
    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    $capturedException = null;
    try {
        $service->authenticate('nonexistent@panaderia.test', 'SomePassword123!');
    } catch (ValidationException $e) {
        $capturedException = $e;
    }

    expect($capturedException)->not->toBeNull()
        ->and($capturedException?->errors())->toHaveKey('email')
        ->and($capturedException?->errors()['email'][0])->toBe(BusinessAuthService::MESSAGE_INVALID_CREDENTIALS)
        ->and(Auth::guard('business')->check())->toBeFalse();
});

test('it enforces rate limiting lockout after 5 failed attempts and resets on success', function (): void {
    Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        try {
            $service->authenticate('contacto@panaderia.test', 'WrongPassword!');
        } catch (ValidationException $e) {
            expect($e->errors()['email'][0])->toBe(BusinessAuthService::MESSAGE_INVALID_CREDENTIALS);
        }
    }

    // 6th attempt should be locked out
    $lockoutException = null;
    try {
        $service->authenticate('contacto@panaderia.test', 'CorrectPassword123!');
    } catch (ValidationException $e) {
        $lockoutException = $e;
    }

    expect($lockoutException)->not->toBeNull()
        ->and($lockoutException?->errors()['email'][0])->toContain('Demasiados intentos de inicio de sesión');

    // Reset rate limiter and verify success clears attempts
    $service->clearRateLimiter('contacto@panaderia.test', '127.0.0.1');

    $service->authenticate('contacto@panaderia.test', 'CorrectPassword123!');
    expect(Auth::guard('business')->check())->toBeTrue()
        ->and($service->hasTooManyAttempts('contacto@panaderia.test', '127.0.0.1'))->toBeFalse();
});

test('it logs out business operator and clears session', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);

    $service->authenticate('contacto@panaderia.test', 'Turnex@2026!');
    expect(Auth::guard('business')->check())->toBeTrue();

    $service->logout();
    expect(Auth::guard('business')->check())->toBeFalse();
});
