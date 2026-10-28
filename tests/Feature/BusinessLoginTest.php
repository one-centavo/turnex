<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Services\BusinessAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function (): void {
    RateLimiter::clear('business_login:contacto@panaderia.test|127.0.0.1');
});

test('guest can access business login page', function (): void {
    $this->get(route('business.login'))
        ->assertOk()
        ->assertSee('Inicia sesión en tu negocio')
        ->assertSee('Correo electrónico')
        ->assertSee('¿Olvidaste tu contraseña?');
});

test('authenticated business operator is redirected away from business login', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get(route('business.login'))
        ->assertRedirect(route('dashboard'));
});

test('authenticated customer can access business login without session interference', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('business.login'))
        ->assertOk();

    expect(Auth::guard('customer')->check())->toBeTrue();
});

test('login requires email and password inputs', function (): void {
    Livewire::test('pages::auth.business.login')
        ->call('login')
        ->assertHasErrors(['email', 'password'])
        ->assertSee('El correo electrónico es obligatorio.')
        ->assertSee('La contraseña es obligatoria.');
});

test('login validates email format', function (): void {
    Livewire::test('pages::auth.business.login')
        ->set('email', 'not-a-valid-email')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Ingresa un correo electrónico válido.');
});

test('business operator can authenticate with email and password', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    Livewire::test('pages::auth.business.login')
        ->set('email', 'Contacto@Panaderia.Test')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('business')->check())->toBeTrue()
        ->and(Auth::guard('business')->id())->toBe($business->id);
});

test('invalid credentials displays generic error message without enumerating accounts', function (): void {
    Livewire::test('pages::auth.business.login')
        ->set('email', 'nonexistent@panaderia.test')
        ->set('password', 'WrongPassword123!')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee(BusinessAuthService::MESSAGE_INVALID_CREDENTIALS);

    expect(Auth::guard('business')->check())->toBeFalse();
});

test('failed login preserves email and clears password field', function (): void {
    Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    Livewire::test('pages::auth.business.login')
        ->set('email', 'contacto@panaderia.test')
        ->set('password', 'WrongPassword123!')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSet('email', 'contacto@panaderia.test')
        ->assertSet('password', '');
});

test('rate limiting locks out user after 5 failed attempts and resets on success', function (): void {
    Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    $component = Livewire::test('pages::auth.business.login')
        ->set('email', 'contacto@panaderia.test');

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $component->set('password', 'WrongPassword!')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    // 6th attempt triggers lockout
    $component->set('password', 'CorrectPassword123!')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Demasiados intentos de inicio de sesión');

    // Clear rate limiter and verify successful login
    /** @var BusinessAuthService $service */
    $service = app(BusinessAuthService::class);
    $service->clearRateLimiter('contacto@panaderia.test', '127.0.0.1');

    $component->set('password', 'CorrectPassword123!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('business')->check())->toBeTrue();
});

test('standard session lifetime is 2 hours and remember me persists token', function (): void {
    expect(config('session.lifetime'))->toBe(120);

    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!', ['rounds' => 4]),
        'remember_token' => null,
    ]);

    Livewire::test('pages::auth.business.login')
        ->set('email', 'contacto@panaderia.test')
        ->set('password', 'Turnex@2026!')
        ->set('remember', true)
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $business->refresh();

    expect($business->getRememberToken())->not->toBeNull();
});

test('it redirects to intended destination upon successful business login', function (): void {
    Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!', ['rounds' => 4]),
    ]);

    Session::put('url.intended', '/business/orders');

    Livewire::test('pages::auth.business.login')
        ->set('email', 'contacto@panaderia.test')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect('/business/orders');
});

test('customer session remains unaffected when logging into business guard', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var Business $business */
    $business = Business::factory()->create([
        'email' => 'contacto@panaderia.test',
        'password' => Hash::make('Turnex@2026!', ['rounds' => 4]),
    ]);

    $this->actingAs($customer, 'customer');
    expect(Auth::guard('customer')->check())->toBeTrue();

    Livewire::test('pages::auth.business.login')
        ->set('email', 'contacto@panaderia.test')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasNoErrors();

    expect(Auth::guard('business')->check())->toBeTrue()
        ->and(Auth::guard('customer')->check())->toBeTrue();
});
