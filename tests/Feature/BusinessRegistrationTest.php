<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Enums\ContactType;
use App\Enums\PersonType;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Notifications\WelcomeBusinessNotification;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

test('guest can access business registration page', function (): void {
    $this->get(route('business.register'))
        ->assertOk()
        ->assertSee('Registra tu negocio');
});

test('authenticated business owner is redirected away from registration', function (): void {
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get(route('business.register'))
        ->assertRedirect(route('business.dashboard'));
});

test('authenticated web user is redirected away from business registration', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get(route('business.register'))
        ->assertRedirect(route('dashboard'));
});

test('authenticated customer is permitted to access business registration', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('business.register'))
        ->assertOk()
        ->assertSee('Registra tu negocio');
});

test('step 1 validates required fields and security rules', function (): void {
    Livewire::test('pages::auth.business.register')
        ->call('submitStepOne')
        ->assertHasErrors([
            'business_name',
            'business_category',
            'owner_first_name',
            'owner_last_name',
            'location',
            'email',
            'password',
            'terms',
        ])
        ->assertSet('step', 1);
});

test('step 1 rejects invalid business category', function (): void {
    Livewire::test('pages::auth.business.register')
        ->set('business_category', 'invalid_industry')
        ->call('submitStepOne')
        ->assertHasErrors(['business_category']);
});

test('step 1 accepts optional middle name and second last name', function (): void {
    Notification::fake();

    Livewire::test('pages::auth.business.register')
        ->set('business_name', 'Café Don Pedro')
        ->set('business_category', BusinessCategory::Restaurant->value)
        ->set('owner_first_name', 'Carlos')
        ->set('owner_middle_name', 'Andrés')
        ->set('owner_last_name', 'Gómez')
        ->set('owner_second_last_name', 'Rodríguez')
        ->set('location', 'Carrera 15 # 85-30, Bogotá')
        ->set('email', 'carlos@cafedonpedro.com')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

test('step 1 rejects duplicate email against existing business', function (): void {
    Business::factory()->create([
        'email' => 'contacto@minegocio.com',
    ]);

    Livewire::test('pages::auth.business.register')
        ->set('business_name', 'Nuevo Local')
        ->set('business_category', BusinessCategory::Retail->value)
        ->set('owner_first_name', 'Carlos')
        ->set('owner_last_name', 'Pérez')
        ->set('location', 'Calle 100 # 20-30')
        ->set('email', 'Contacto@MiNegocio.Com')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasErrors(['email'])
        ->assertSet('step', 1);
});

test('step 1 enforces IP rate limiting of 5 requests per minute', function (): void {
    RateLimiter::clear('business_reg_ip:127.0.0.1');

    $component = Livewire::test('pages::auth.business.register')
        ->set('business_name', 'Mi Tienda')
        ->set('business_category', BusinessCategory::Retail->value)
        ->set('owner_first_name', 'Carlos')
        ->set('owner_last_name', 'Pérez')
        ->set('location', 'Calle 100 # 20-30')
        ->set('email', 'nuevo@tienda.com')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit('business_reg_ip:127.0.0.1', 60);
    }

    $component->call('submitStepOne')
        ->assertHasErrors(['general'])
        ->assertSet('step', 1);
});

test('step 1 happy path dispatches Email OTP and advances to step 2', function (): void {
    Notification::fake();

    Livewire::test('pages::auth.business.register')
        ->set('business_name', '  Café Don Pedro  ')
        ->set('business_category', BusinessCategory::Restaurant->value)
        ->set('owner_first_name', '  Carlos  ')
        ->set('owner_last_name', '  Pérez  ')
        ->set('location', '  Carrera 15 # 85-30  ')
        ->set('email', '  Carlos@CafeDonPedro.Com  ')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('email', 'carlos@cafedonpedro.com');

    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification): bool {
        return $notification->channel === ContactType::Email;
    });

    expect(Session::has('business_registration'))->toBeTrue();
});

test('step 2 rejects invalid verification code', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('carlos@cafedonpedro.com', ContactType::Email);

    Session::put('business_registration', [
        'step' => 2,
        'business_name' => 'Café Don Pedro',
        'business_category' => BusinessCategory::Restaurant->value,
        'owner_first_name' => 'Carlos',
        'owner_middle_name' => null,
        'owner_last_name' => 'Pérez',
        'owner_second_last_name' => null,
        'location' => 'Carrera 15 # 85-30',
        'email' => 'carlos@cafedonpedro.com',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(60)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    Livewire::test('pages::auth.business.register')
        ->set('step', 2)
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertHasErrors(['code']);

    expect(Business::where('email', 'carlos@cafedonpedro.com')->exists())->toBeFalse();
});

test('step 2 happy path verifies OTP, provisions business, authenticates, and redirects to dashboard setup', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('carlos@cafedonpedro.com', ContactType::Email);

    $plainCode = '';
    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification) use (&$plainCode): bool {
        $plainCode = $notification->code;

        return true;
    });

    Session::put('business_registration', [
        'step' => 2,
        'business_name' => 'Café Don Pedro',
        'business_category' => BusinessCategory::Restaurant->value,
        'owner_first_name' => 'Carlos',
        'owner_middle_name' => 'Andrés',
        'owner_last_name' => 'Pérez',
        'owner_second_last_name' => 'Rodríguez',
        'location' => 'Carrera 15 # 85-30',
        'email' => 'carlos@cafedonpedro.com',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(60)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    $component = Livewire::test('pages::auth.business.register')
        ->set('step', 2)
        ->set('code', $plainCode)
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->assertSee('¡Negocio registrado con éxito!')
        ->assertSee('Redirigiendo al panel de tu negocio...');

    $component->call('finishRegistration')
        ->assertRedirect(route('business.dashboard', ['setup' => 1]));

    $business = Business::where('email', 'carlos@cafedonpedro.com')->first();

    expect($business)->not->toBeNull()
        ->and($business->business_name)->toBe('Café Don Pedro')
        ->and($business->business_category)->toBe(BusinessCategory::Restaurant)
        ->and($business->person_type)->toBe(PersonType::Natural)
        ->and($business->verification_status)->toBe(VerificationStatus::PendingVerification)
        ->and($business->owner_first_name)->toBe('Carlos')
        ->and($business->owner_middle_name)->toBe('Andrés')
        ->and($business->owner_last_name)->toBe('Pérez')
        ->and($business->owner_second_last_name)->toBe('Rodríguez')
        ->and($business->email)->toBe('carlos@cafedonpedro.com')
        ->and($business->phone_number)->toBeNull()
        ->and($business->phone_verified_at)->toBeNull()
        ->and($business->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('business')->check())->toBeTrue()
        ->and(Auth::guard('business')->id())->toBe($business->id);

    Notification::assertSentTo($business, WelcomeBusinessNotification::class);
    expect(Session::has('business_registration'))->toBeFalse();
});

test('step 2 allows editing email up to 3 times, rejects unchanged or duplicate without consuming attempts', function (): void {
    Notification::fake();

    Business::factory()->create([
        'email' => 'existing@minegocio.com',
    ]);

    Session::put('business_registration', [
        'step' => 2,
        'business_name' => 'Café Don Pedro',
        'business_category' => BusinessCategory::Restaurant->value,
        'owner_first_name' => 'Carlos',
        'owner_middle_name' => null,
        'owner_last_name' => 'Pérez',
        'owner_second_last_name' => null,
        'location' => 'Carrera 15 # 85-30',
        'email' => 'carlos@cafedonpedro.com',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(45)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    $component = Livewire::test('pages::auth.business.register')
        ->set('step', 2)
        ->call('editEmail')
        ->assertSet('isEditingEmail', true)
        // Submitting unchanged email doesn't consume an attempt
        ->set('new_email', 'carlos@cafedonpedro.com')
        ->call('saveNewEmail')
        ->assertSet('emailEditCount', 0)
        ->assertSet('isEditingEmail', false);

    // Submitting duplicate email doesn't consume an attempt
    $component->call('editEmail')
        ->set('new_email', 'existing@minegocio.com')
        ->call('saveNewEmail')
        ->assertHasErrors(['new_email'])
        ->assertSet('emailEditCount', 0);

    // 1st valid edit
    $component->set('new_email', 'nuevo1@cafedonpedro.com')
        ->call('saveNewEmail')
        ->assertHasNoErrors()
        ->assertSet('email', 'nuevo1@cafedonpedro.com')
        ->assertSet('emailEditCount', 1)
        ->assertSet('isEditingEmail', false);

    // 2nd edit
    $component->call('editEmail')
        ->set('new_email', 'nuevo2@cafedonpedro.com')
        ->call('saveNewEmail')
        ->assertSet('emailEditCount', 2);

    // 3rd edit
    $component->call('editEmail')
        ->set('new_email', 'nuevo3@cafedonpedro.com')
        ->call('saveNewEmail')
        ->assertSet('emailEditCount', 3);

    // 4th edit should be rejected
    $component->call('editEmail')
        ->assertHasErrors(['email_edit']);
});

test('step 2 rejects expired session after 15 minutes', function (): void {
    Session::put('business_registration', [
        'step' => 2,
        'business_name' => 'Café Don Pedro',
        'business_category' => BusinessCategory::Restaurant->value,
        'owner_first_name' => 'Carlos',
        'owner_middle_name' => null,
        'owner_last_name' => 'Pérez',
        'owner_second_last_name' => null,
        'location' => 'Carrera 15 # 85-30',
        'email' => 'carlos@cafedonpedro.com',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->subMinute()->toIso8601String(),
        'expires_at' => now()->subMinute()->toIso8601String(),
    ]);

    Livewire::test('pages::auth.business.register')
        ->set('step', 2)
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertSet('step', 1)
        ->assertHasErrors(['general']);

    expect(Session::has('business_registration'))->toBeFalse();
});

test('resendOtp enforces cooldown for email OTP', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('carlos@cafedonpedro.com', ContactType::Email);

    Session::put('business_registration', [
        'step' => 2,
        'business_name' => 'Café Don Pedro',
        'business_category' => BusinessCategory::Restaurant->value,
        'owner_first_name' => 'Carlos',
        'owner_middle_name' => null,
        'owner_last_name' => 'Pérez',
        'owner_second_last_name' => null,
        'location' => 'Carrera 15 # 85-30',
        'email' => 'carlos@cafedonpedro.com',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(30)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    // Attempting to resend during active cooldown is rejected
    $component = Livewire::test('pages::auth.business.register')
        ->set('step', 2)
        ->call('resendOtp')
        ->assertHasErrors(['code']);

    // Advance time past cooldown
    Carbon::setTestNow(now()->addSeconds(65));

    $component->call('resendOtp')
        ->assertHasNoErrors();

    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification): bool {
        return $notification->channel === ContactType::Email;
    });

    Carbon::setTestNow();
});
