<?php

declare(strict_types=1);

use App\Enums\ContactType;
use App\Models\Customer;
use App\Notifications\SendOtpNotification;
use App\Notifications\WelcomeCustomerNotification;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

test('guest can access customer registration page', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Crea tu cuenta de cliente');
});

test('authenticated customer is redirected away from registration', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('register'))
        ->assertRedirect(route('dashboard'));
});

test('step 1 validates required fields and security rules', function (): void {
    Livewire::test('pages::auth.customer.register')
        ->call('submitStepOne')
        ->assertHasErrors([
            'first_name',
            'last_name',
            'email',
            'password',
            'terms',
        ])
        ->assertSet('step', 1);
});

test('step 1 rejects duplicate email address', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
    ]);

    Livewire::test('pages::auth.customer.register')
        ->set('first_name', 'Carlos')
        ->set('last_name', 'Pérez')
        ->set('email', 'Carlos.Perez@Turnex.Test')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasErrors(['email'])
        ->assertSet('step', 1);
});

test('step 1 enforces IP rate limiting of 5 requests per minute', function (): void {
    RateLimiter::clear('customer_reg_ip:127.0.0.1');

    $component = Livewire::test('pages::auth.customer.register')
        ->set('first_name', 'Carlos')
        ->set('last_name', 'Pérez')
        ->set('email', 'new.user@turnex.test')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit('customer_reg_ip:127.0.0.1', 60);
    }

    $component->call('submitStepOne')
        ->assertHasErrors(['general'])
        ->assertSet('step', 1);
});

test('step 1 happy path dispatches Email OTP and advances to step 2', function (): void {
    Notification::fake();

    Livewire::test('pages::auth.customer.register')
        ->set('first_name', '  carlos  ')
        ->set('last_name', '  pérez  ')
        ->set('email', '  Carlos.Perez@Turnex.Test  ')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('email', 'carlos.perez@turnex.test');

    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification): bool {
        return $notification->channel === ContactType::Email;
    });

    expect(Session::has('customer_registration'))->toBeTrue();
});

test('step 2 rejects invalid verification code', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('carlos@turnex.test', ContactType::Email);

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(60)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertHasErrors(['code']);

    expect(Customer::where('email', 'carlos@turnex.test')->exists())->toBeFalse();
});

test('step 2 happy path verifies OTP, provisions customer, authenticates, and shows step 3 confirmation', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('carlos@turnex.test', ContactType::Email);

    $plainCode = '';
    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification) use (&$plainCode): bool {
        $plainCode = $notification->code;

        return true;
    });

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(60)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    $component = Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->set('code', $plainCode)
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->assertSee('¡Cuenta creada con éxito!')
        ->assertSee('Redirigiendo a tu panel...');

    $component->call('finishRegistration')
        ->assertRedirect(route('dashboard'));

    $customer = Customer::where('email', 'carlos@turnex.test')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->first_name)->toBe('Carlos')
        ->and($customer->last_name)->toBe('Pérez')
        ->and($customer->email)->toBe('carlos@turnex.test')
        ->and($customer->phone_number)->toBeNull()
        ->and($customer->phone_verified_at)->toBeNull()
        ->and($customer->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);

    Notification::assertSentTo($customer, WelcomeCustomerNotification::class);
    expect(Session::has('customer_registration'))->toBeFalse();
});

test('step 2 allows editing email address up to 3 times and preserves cooldown', function (): void {
    Notification::fake();

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(45)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    $component = Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->call('editEmail')
        ->assertSet('isEditingEmail', true)
        ->set('new_email', 'carlos.new@turnex.test')
        ->call('saveNewEmail')
        ->assertHasNoErrors()
        ->assertSet('email', 'carlos.new@turnex.test')
        ->assertSet('emailEditCount', 1)
        ->assertSet('isEditingEmail', false);

    // 2nd edit
    $component->call('editEmail')
        ->set('new_email', 'carlos.second@turnex.test')
        ->call('saveNewEmail')
        ->assertSet('emailEditCount', 2);

    // 3rd edit
    $component->call('editEmail')
        ->set('new_email', 'carlos.third@turnex.test')
        ->call('saveNewEmail')
        ->assertSet('emailEditCount', 3);

    // 4th edit should be rejected
    $component->call('editEmail')
        ->assertHasErrors(['email_edit']);
});

test('step 2 rejects editing email to an already registered email address', function (): void {
    Notification::fake();

    Customer::factory()->create([
        'email' => 'existing.customer@turnex.test',
    ]);

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(45)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->call('editEmail')
        ->set('new_email', 'Existing.Customer@turnex.test')
        ->call('saveNewEmail')
        ->assertHasErrors(['new_email'])
        ->assertSet('email', 'carlos@turnex.test')
        ->assertSet('emailEditCount', 0)
        ->assertSet('isEditingEmail', true);
});

test('step 2 rejects expired session after 15 minutes', function (): void {
    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->subMinute()->toIso8601String(),
        'expires_at' => now()->subMinute()->toIso8601String(),
    ]);

    Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->set('code', '123456')
        ->call('verifyCode')
        ->assertSet('step', 1)
        ->assertHasErrors(['general']);

    expect(Session::has('customer_registration'))->toBeFalse();
});

test('resendOtp enforces cooldown and dispatches Email OTP', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('carlos@turnex.test', ContactType::Email);

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'password' => 'Turnex@2026!',
        'email_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(30)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    // Attempting to resend during active cooldown is rejected
    $component = Livewire::test('pages::auth.customer.register')
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
