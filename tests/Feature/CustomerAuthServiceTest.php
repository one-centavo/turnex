<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\CustomerAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    RateLimiter::clear('customer_login:carlos.perez@turnex.test|127.0.0.1');
    RateLimiter::clear('customer_login:+573001234567|127.0.0.1');
});

test('it correctly determines identifier type', function (): void {
    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    expect($service->determineIdentifierType('user@example.com'))->toBe('email')
        ->and($service->determineIdentifierType('  user@example.com  '))->toBe('email')
        ->and($service->determineIdentifierType('3001234567'))->toBe('phone_number')
        ->and($service->determineIdentifierType('+573001234567'))->toBe('phone_number')
        ->and($service->determineIdentifierType('(300) 123-4567'))->toBe('phone_number');
});

test('it normalizes email and phone identifiers', function (): void {
    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    expect($service->normalizeIdentifier('  Carlos.Perez@Turnex.Test  '))->toBe('carlos.perez@turnex.test')
        ->and($service->normalizeIdentifier(' (300) 123-4567 '))->toBe('+573001234567')
        ->and($service->normalizeIdentifier('3001234567'))->toBe('+573001234567')
        ->and($service->normalizeIdentifier('+57 300 123 4567'))->toBe('+573001234567');
});

test('it finds customer by normalized email or phone number', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'phone_number' => '+573001234567',
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    expect($service->findCustomer('Carlos.Perez@Turnex.Test')?->id)->toBe($customer->id)
        ->and($service->findCustomer('(300) 123-4567')?->id)->toBe($customer->id)
        ->and($service->findCustomer('nonexistent@turnex.test'))->toBeNull();
});

test('it identifies google-only accounts with null password', function (): void {
    /** @var Customer $standardCustomer */
    $standardCustomer = Customer::factory()->create([
        'password' => Hash::make('Secret123!'),
    ]);

    /** @var Customer $googleCustomer */
    $googleCustomer = Customer::factory()->withGoogle()->create();

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    expect($service->isGoogleOnlyAccount($standardCustomer))->toBeFalse()
        ->and($service->isGoogleOnlyAccount($googleCustomer))->toBeTrue();
});

test('it successfully authenticates a customer with email', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    $authenticated = $service->authenticate('  Carlos.Perez@Turnex.Test  ', 'Turnex@2026!');

    expect($authenticated->id)->toBe($customer->id)
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);
});

test('it successfully authenticates a customer with phone number in various formats', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'phone_number' => '+573001234567',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    $authenticated = $service->authenticate('(300) 123-4567', 'Turnex@2026!');

    expect($authenticated->id)->toBe($customer->id)
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);
});

test('it supports remember me persistent token upon login', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('Turnex@2026!'),
        'remember_token' => null,
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    $service->authenticate('carlos.perez@turnex.test', 'Turnex@2026!', remember: true);

    $customer->refresh();

    expect($customer->getRememberToken())->not->toBeNull();
});

test('it regenerates the session upon successful authentication', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    Session::start();
    $initialSessionId = Session::getId();

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    $service->authenticate('carlos.perez@turnex.test', 'Turnex@2026!');

    expect(Session::getId())->not->toBe($initialSessionId);
});

test('it throws validation exception with google guidance for google-only accounts', function (): void {
    Customer::factory()->withGoogle()->create([
        'email' => 'carlos.perez@turnex.test',
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    try {
        $service->authenticate('carlos.perez@turnex.test', 'RandomPassword123!');
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('identifier')
            ->and($e->errors()['identifier'][0])->toBe(CustomerAuthService::MESSAGE_GOOGLE_ACCOUNT);
    }

    expect(Auth::guard('customer')->check())->toBeFalse();
});

test('it throws validation exception with generic error on invalid password', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    try {
        $service->authenticate('carlos.perez@turnex.test', 'WrongPassword123!');
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('identifier')
            ->and($e->errors()['identifier'][0])->toBe(CustomerAuthService::MESSAGE_INVALID_CREDENTIALS);
    }

    expect(Auth::guard('customer')->check())->toBeFalse();
});

test('it throws validation exception with generic error on non-existent identifier', function (): void {
    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    try {
        $service->authenticate('nonexistent@turnex.test', 'SomePassword123!');
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('identifier')
            ->and($e->errors()['identifier'][0])->toBe(CustomerAuthService::MESSAGE_INVALID_CREDENTIALS);
    }

    expect(Auth::guard('customer')->check())->toBeFalse();
});

test('it enforces rate limiting lockout after 5 failed attempts and resets on success', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        try {
            $service->authenticate('carlos.perez@turnex.test', 'WrongPassword!');
        } catch (ValidationException $e) {
            expect($e->errors()['identifier'][0])->toBe(CustomerAuthService::MESSAGE_INVALID_CREDENTIALS);
        }
    }

    // 6th attempt should be locked out
    try {
        $service->authenticate('carlos.perez@turnex.test', 'CorrectPassword123!');
        test()->fail('Expected lockout ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['identifier'][0])->toContain('Demasiados intentos de inicio de sesión');
    }

    // Reset rate limiter and verify success clears attempts
    $service->clearRateLimiter('carlos.perez@turnex.test', '127.0.0.1');

    $service->authenticate('carlos.perez@turnex.test', 'CorrectPassword123!');
    expect(Auth::guard('customer')->check())->toBeTrue()
        ->and($service->hasTooManyAttempts('carlos.perez@turnex.test', '127.0.0.1'))->toBeFalse();
});
