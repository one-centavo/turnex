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
            'phone_number',
            'password',
            'terms',
        ])
        ->assertSet('step', 1);
});

test('step 1 rejects duplicate email and phone number', function (): void {
    Customer::factory()->create([
        'email' => 'carlos.perez@turnex.test',
        'phone_number' => '+573001234567',
    ]);

    Livewire::test('pages::auth.customer.register')
        ->set('first_name', 'Carlos')
        ->set('last_name', 'Pérez')
        ->set('email', 'Carlos.Perez@Turnex.Test')
        ->set('phone_number', '(300) 123-4567')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasErrors(['email', 'phone_number'])
        ->assertSet('step', 1);
});

test('step 1 enforces IP rate limiting of 5 requests per minute', function (): void {
    RateLimiter::clear('customer_reg_ip:127.0.0.1');

    $component = Livewire::test('pages::auth.customer.register')
        ->set('first_name', 'Carlos')
        ->set('last_name', 'Pérez')
        ->set('email', 'new.user@turnex.test')
        ->set('phone_number', '3109876543')
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

test('step 1 happy path dispatches WhatsApp OTP and advances to step 2', function (): void {
    Notification::fake();

    Livewire::test('pages::auth.customer.register')
        ->set('first_name', '  carlos  ')
        ->set('last_name', '  pérez  ')
        ->set('email', '  Carlos.Perez@Turnex.Test  ')
        ->set('phone_number', '3001234567')
        ->set('password', 'Turnex@2026!')
        ->set('password_confirmation', 'Turnex@2026!')
        ->set('terms', true)
        ->call('submitStepOne')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('phone_number', '+573001234567');

    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification): bool {
        return $notification->channel === ContactType::WhatsApp;
    });

    expect(Session::has('customer_registration'))->toBeTrue();
});

test('step 2 rejects invalid verification code', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('+573001234567', ContactType::WhatsApp);

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'phone_number' => '+573001234567',
        'password' => 'Turnex@2026!',
        'phone_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(60)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertHasErrors(['code']);

    expect(Customer::where('phone_number', '+573001234567')->exists())->toBeFalse();
});

test('step 2 happy path verifies OTP, provisions customer, authenticates, and shows step 3 confirmation', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('+573001234567', ContactType::WhatsApp);

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
        'phone_number' => '+573001234567',
        'password' => 'Turnex@2026!',
        'phone_edit_count' => 0,
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

    $customer = Customer::where('phone_number', '+573001234567')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->first_name)->toBe('Carlos')
        ->and($customer->last_name)->toBe('Pérez')
        ->and($customer->email)->toBe('carlos@turnex.test')
        ->and($customer->phone_verified_at)->not->toBeNull()
        ->and($customer->email_verified_at)->toBeNull()
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);

    Notification::assertSentTo($customer, WelcomeCustomerNotification::class);
    expect(Session::has('customer_registration'))->toBeFalse();
});

test('step 2 allows editing phone number up to 3 times and preserves cooldown', function (): void {
    Notification::fake();

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'phone_number' => '+573001234567',
        'password' => 'Turnex@2026!',
        'phone_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(45)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    $component = Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->call('editPhone')
        ->assertSet('isEditingPhone', true)
        ->set('new_phone_number', '3109876543')
        ->call('saveNewPhone')
        ->assertHasNoErrors()
        ->assertSet('phone_number', '+573109876543')
        ->assertSet('phoneEditCount', 1)
        ->assertSet('isEditingPhone', false);

    // 2nd edit
    $component->call('editPhone')
        ->set('new_phone_number', '3111112233')
        ->call('saveNewPhone')
        ->assertSet('phoneEditCount', 2);

    // 3rd edit
    $component->call('editPhone')
        ->set('new_phone_number', '3122223344')
        ->call('saveNewPhone')
        ->assertSet('phoneEditCount', 3);

    // 4th edit should be rejected
    $component->call('editPhone')
        ->assertHasErrors(['phone_edit']);
});

test('step 2 rejects expired session after 15 minutes', function (): void {
    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'phone_number' => '+573001234567',
        'password' => 'Turnex@2026!',
        'phone_edit_count' => 0,
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

test('resendOtp enforces cooldown and allows SMS fallback', function (): void {
    Notification::fake();

    /** @var OtpService $otpService */
    $otpService = app(OtpService::class);
    $otpService->generate('+573001234567', ContactType::WhatsApp);

    Session::put('customer_registration', [
        'step' => 2,
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos@turnex.test',
        'phone_number' => '+573001234567',
        'password' => 'Turnex@2026!',
        'phone_edit_count' => 0,
        'cooldown_until' => now()->addSeconds(30)->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
    ]);

    // Attempting to resend during active cooldown is rejected
    $component = Livewire::test('pages::auth.customer.register')
        ->set('step', 2)
        ->call('resendOtp', 'sms')
        ->assertHasErrors(['code']);

    // Advance time past cooldown
    Carbon::setTestNow(now()->addSeconds(65));

    $component->call('resendOtp', 'sms')
        ->assertHasNoErrors()
        ->assertSet('channel', 'sms');

    Notification::assertSentOnDemand(SendOtpNotification::class, function (SendOtpNotification $notification): bool {
        return $notification->channel === ContactType::Sms;
    });

    Carbon::setTestNow();
});
