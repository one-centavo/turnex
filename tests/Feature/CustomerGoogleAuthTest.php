<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\CustomerGoogleAuthController;
use App\Models\Customer;
use App\Services\CustomerAuthService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Helper to mock a Socialite user payload from Google.
 *
 * @param  array<string, mixed>|null  $raw
 */
function mockGoogleUser(
    string $id = 'google-123456789',
    string $email = 'juan.perez@turnex.test',
    string $name = 'Juan Pérez',
    ?string $avatar = 'https://example.com/avatar.jpg',
    ?array $raw = ['given_name' => 'Juan', 'family_name' => 'Pérez'],
): void {
    $abstractUser = Mockery::mock(SocialiteTwoUser::class);
    $abstractUser->shouldReceive('getId')->andReturn($id);
    $abstractUser->shouldReceive('getEmail')->andReturn($email);
    $abstractUser->shouldReceive('getName')->andReturn($name);
    $abstractUser->shouldReceive('getAvatar')->andReturn($avatar);
    $abstractUser->shouldReceive('getRaw')->andReturn($raw);

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('customer login view displays continue with google button', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('auth.google.redirect'))
        ->assertSee('Continuar con Google');
});

test('customer register view displays continue with google button on step 1', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee(route('auth.google.redirect'))
        ->assertSee('Continuar con Google');
});

test('authenticated customer cannot trigger google redirect', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('auth.google.redirect'))
        ->assertRedirect(route('dashboard'));
});

test('authenticated customer cannot trigger google callback', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));
});

test('guest is redirected to google authorization screen', function (): void {
    $redirectResponse = new SymfonyRedirectResponse('https://accounts.google.com/o/oauth2/auth');

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('redirect')->andReturn($redirectResponse);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('guest can register a new customer account with google profile', function (): void {
    mockGoogleUser(
        id: 'google-999000111',
        email: 'maria.lopez@turnex.test',
        name: 'María López',
        avatar: 'https://lh3.googleusercontent.com/avatar-maria.jpg',
        raw: ['given_name' => 'María', 'family_name' => 'López'],
    );

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));

    $customer = Customer::where('email', 'maria.lopez@turnex.test')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->first_name)->toBe('María')
        ->and($customer->last_name)->toBe('López')
        ->and($customer->google_id)->toBe('google-999000111')
        ->and($customer->avatar_url)->toBe('https://lh3.googleusercontent.com/avatar-maria.jpg')
        ->and($customer->password)->toBeNull()
        ->and($customer->hasVerifiedEmail())->toBeTrue()
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);
});

test('google registration succeeds gracefully when avatar and last name are missing', function (): void {
    mockGoogleUser(
        id: 'google-single-name',
        email: 'madonna@turnex.test',
        name: 'Madonna',
        avatar: null,
        raw: ['given_name' => 'Madonna'],
    );

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));

    $customer = Customer::where('email', 'madonna@turnex.test')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->first_name)->toBe('Madonna')
        ->and($customer->last_name)->toBe('')
        ->and($customer->avatar_url)->toBeNull()
        ->and($customer->google_id)->toBe('google-single-name')
        ->and($customer->hasVerifiedEmail())->toBeTrue()
        ->and(Auth::guard('customer')->check())->toBeTrue();
});

test('existing customer with same email links google account upon authentication', function (): void {
    $existingCustomer = Customer::factory()->create([
        'first_name' => 'Pedro',
        'last_name' => 'Gómez',
        'email' => 'pedro.gomez@turnex.test',
        'password' => Hash::make('Password@123!'),
        'google_id' => null,
        'email_verified_at' => null,
        'avatar_url' => null,
    ]);

    mockGoogleUser(
        id: 'google-pedro-id',
        email: 'pedro.gomez@turnex.test',
        name: 'Pedro Gómez',
        avatar: 'https://lh3.googleusercontent.com/avatar-pedro.jpg',
    );

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));

    $existingCustomer->refresh();

    expect($existingCustomer->google_id)->toBe('google-pedro-id')
        ->and($existingCustomer->avatar_url)->toBe('https://lh3.googleusercontent.com/avatar-pedro.jpg')
        ->and($existingCustomer->hasVerifiedEmail())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($existingCustomer->id);
});

test('customer with linked google account and password can authenticate with either method', function (): void {
    $customer = Customer::factory()->create([
        'email' => 'dual.user@turnex.test',
        'password' => Hash::make('DualPass@2026!'),
        'google_id' => 'google-dual-id',
    ]);

    // 1. Authenticate via Google OAuth callback
    mockGoogleUser(
        id: 'google-dual-id',
        email: 'dual.user@turnex.test',
    );

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));
    expect(Auth::guard('customer')->id())->toBe($customer->id);

    Auth::guard('customer')->logout();
    expect(Auth::guard('customer')->check())->toBeFalse();

    // 2. Authenticate via standard credentials in CustomerAuthService
    /** @var CustomerAuthService $authService */
    $authService = app(CustomerAuthService::class);
    $authenticated = $authService->authenticate('dual.user@turnex.test', 'DualPass@2026!');

    expect($authenticated->id)->toBe($customer->id);
});

test('canceling or failing google auth redirects to login with informative error message', function (): void {
    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new Exception('OAuth authorization cancelled by user.'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHas('error', CustomerGoogleAuthController::ERROR_MESSAGE);

    // Verify error is displayed on the login page
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('No se pudo completar la autenticación con Google. Por favor, intenta de nuevo.');
});

test('successful google authentication redirects to intended destination', function (): void {
    mockGoogleUser(
        id: 'google-intended-target',
        email: 'intended.user@turnex.test',
    );

    $response = $this->withSession(['url.intended' => 'http://localhost/special-destination'])
        ->get(route('auth.google.callback'));

    $response->assertRedirect('http://localhost/special-destination');
});

test('customer registered exclusively via google cannot log in with standard password form', function (): void {
    $googleCustomer = Customer::factory()->withGoogle('google-only-user')->create([
        'email' => 'google.only@turnex.test',
    ]);

    Livewire::test('pages::auth.customer.login')
        ->set('identifier', 'google.only@turnex.test')
        ->set('password', 'AnyPassword123!')
        ->call('login')
        ->assertHasErrors(['identifier'])
        ->assertSee('Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google.');

    expect(Auth::guard('customer')->check())->toBeFalse();
});
