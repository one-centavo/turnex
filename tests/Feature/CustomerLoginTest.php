<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\CustomerAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function (): void {
    RateLimiter::clear('customer_login:carlos.perez@turnex.test|127.0.0.1');
    RateLimiter::clear('customer_login:+573001234567|127.0.0.1');
});

test('guest can access customer login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Inicia sesión')
        ->assertSee('Correo electrónico o celular')
        ->assertSee('¿Olvidaste tu contraseña?');
});

test('authenticated customer is redirected away from login', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('login requires identifier and password inputs', function (): void {
    Livewire::test('pages::auth.customer.login')
        ->call('login')
        ->assertHasErrors(['identifier', 'password'])
        ->assertSee('El correo electrónico o número de celular es obligatorio.')
        ->assertSee('La contraseña es obligatoria.');
});

test('customer can authenticate with email', function (): void {
    $customer = Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'Carlos.Perez@Turnex.Test')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);
});

test('customer can authenticate with phone number in various formats', function (): void {
    $customer = Customer::factory()->create([
        'phone_number' => '+573001234567',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', '(300) 123-4567')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);
});

test('invalid credentials displays generic error message without enumerating accounts', function (): void {
    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'nonexistent@turnex.test')
        ->set('password', 'WrongPassword123!')
        ->call('login')
        ->assertHasErrors(['identifier'])
        ->assertSee(CustomerAuthService::MESSAGE_INVALID_CREDENTIALS);

    expect(Auth::guard('customer')->check())->toBeFalse();
});

test('google-only account displays specific guidance message', function (): void {
    Customer::factory()->withGoogle()->create([
        'email' => 'carlos.perez@turnex.test',
    ]);

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'carlos.perez@turnex.test')
        ->set('password', 'SomePassword123!')
        ->call('login')
        ->assertHasErrors(['identifier'])
        ->assertSee(CustomerAuthService::MESSAGE_GOOGLE_ACCOUNT);

    expect(Auth::guard('customer')->check())->toBeFalse();
});

test('failed login preserves identifier and clears password field', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'carlos.perez@turnex.test')
        ->set('password', 'WrongPassword123!')
        ->call('login')
        ->assertHasErrors(['identifier'])
        ->assertSet('identifier', 'carlos.perez@turnex.test')
        ->assertSet('password', '');
});

test('rate limiting locks out user after 5 failed attempts and resets on success', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    $component = Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'carlos.perez@turnex.test');

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $component->set('password', 'WrongPassword!')
            ->call('login')
            ->assertHasErrors(['identifier']);
    }

    // 6th attempt triggers lockout
    $component->set('password', 'CorrectPassword123!')
        ->call('login')
        ->assertHasErrors(['identifier'])
        ->assertSee('Demasiados intentos de inicio de sesión');

    // Clear rate limiter and verify successful login
    /** @var CustomerAuthService $service */
    $service = app(CustomerAuthService::class);
    $service->clearRateLimiter('carlos.perez@turnex.test', '127.0.0.1');

    $component->set('password', 'CorrectPassword123!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('customer')->check())->toBeTrue();
});

test('standard session lifetime is 2 hours and remember me persists token', function (): void {
    expect(config('session.lifetime'))->toBe(120);

    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('Turnex@2026!'),
        'remember_token' => null,
    ]);

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'carlos.perez@turnex.test')
        ->set('password', 'Turnex@2026!')
        ->set('remember', true)
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $customer->refresh();

    expect($customer->getRememberToken())->not->toBeNull();
});

test('it redirects to intended destination upon successful login', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'password' => Hash::make('Turnex@2026!'),
    ]);

    Session::put('url.intended', '/custom-catalog');

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'carlos.perez@turnex.test')
        ->set('password', 'Turnex@2026!')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect('/custom-catalog');
});
