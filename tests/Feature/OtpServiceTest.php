<?php

declare(strict_types=1);

use App\Enums\ContactType;
use App\Exceptions\OtpCooldownException;
use App\Models\SecurityCode;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\SendOtpNotification;
use App\Services\OtpService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->service = app(OtpService::class);
    Notification::fake();
});

test('it generates a 6-digit zero-padded security code stored as a hash with 5-minute expiration', function (): void {
    $code = $this->service->generate('user@turnex.test', ContactType::Email);

    expect($code)->toBeInstanceOf(SecurityCode::class)
        ->and($code->contact_value)->toBe('user@turnex.test')
        ->and($code->contact_type)->toBe(ContactType::Email)
        ->and($code->attempts_count)->toBe(0)
        ->and($code->used_at)->toBeNull()
        ->and($code->expires_at->isFuture())->toBeTrue()
        ->and((int) round(now()->diffInMinutes($code->expires_at)))->toBe(5);

    // Assert the code is stored as a secure hash, not plaintext
    expect($code->code)->not->toHaveLength(6)
        ->and(str_starts_with($code->code, '$2y$'))->toBeTrue();

    // Verify the record exists in the database
    $this->assertDatabaseHas('security_codes', [
        'id' => $code->id,
        'contact_value' => 'user@turnex.test',
        'contact_type' => 'email',
        'attempts_count' => 0,
    ]);
});

test('it dispatches notification via the requested channel', function (ContactType $channel, string $target, string $expectedChannel): void {
    $this->service->generate($target, $channel);

    Notification::assertSentOnDemand(
        SendOtpNotification::class,
        function (SendOtpNotification $notification, array $channels, object $notifiable) use ($expectedChannel, $channel): bool {
            return in_array($expectedChannel, $notification->via($notifiable), true)
                && $notification->channel === $channel
                && strlen($notification->code) === 6;
        }
    );
})->with([
    'email' => [ContactType::Email, 'user@turnex.test', 'mail'],
    'sms' => [ContactType::Sms, '3001234567', SmsChannel::class],
    'whatsapp' => [ContactType::WhatsApp, '3009876543', WhatsAppChannel::class],
]);

test('it normalizes contact targets before generation and verification', function (): void {
    $code = $this->service->generate('  User.Test@GMAIL.COM  ', ContactType::Email);

    expect($code->contact_value)->toBe('user.test@gmail.com');

    $this->travel(61)->seconds();

    $smsCode = $this->service->generate('300 123 4567', ContactType::Sms);
    expect($smsCode->contact_value)->toBe('+573001234567');
});

test('it invalidates previous active codes when a new code is requested', function (): void {
    $firstCode = $this->service->generate('user@turnex.test', ContactType::Email);
    expect($firstCode->isExpired())->toBeFalse();

    // Fast-forward past cooldown to generate a second code
    $this->travel(61)->seconds();

    $secondCode = $this->service->generate('user@turnex.test', ContactType::Email);

    $firstCode->refresh();
    expect($firstCode->isExpired())->toBeTrue()
        ->and($secondCode->isExpired())->toBeFalse()
        ->and($firstCode->id)->not->toBe($secondCode->id);
});

test('it enforces a 1-minute cooldown period and reports remaining seconds', function (): void {
    $this->service->generate('cooldown@turnex.test', ContactType::Email);

    // Immediate second attempt should be rejected
    try {
        $this->service->generate('cooldown@turnex.test', ContactType::Email);
        $this->fail('Expected OtpCooldownException was not thrown.');
    } catch (OtpCooldownException $e) {
        expect($e->remainingSeconds)->toBeGreaterThanOrEqual(59)
            ->and($e->remainingSeconds)->toBeLessThanOrEqual(60);
    }

    // After 30 seconds, remaining cooldown should be around 30 seconds
    $this->travel(30)->seconds();

    expect($this->service->getRemainingCooldownSeconds('cooldown@turnex.test', ContactType::Email))
        ->toBe(30);

    try {
        $this->service->generate('cooldown@turnex.test', ContactType::Email);
        $this->fail('Expected OtpCooldownException was not thrown.');
    } catch (OtpCooldownException $e) {
        expect($e->remainingSeconds)->toBe(30);
    }
});

test('it allows requesting a new code once the 1-minute cooldown has passed', function (): void {
    $first = $this->service->generate('user@turnex.test', ContactType::Email);

    $this->travel(61)->seconds();

    expect($this->service->getRemainingCooldownSeconds('user@turnex.test', ContactType::Email))->toBe(0);

    $second = $this->service->generate('user@turnex.test', ContactType::Email);
    expect($second->id)->toBeGreaterThan($first->id);
});

test('it rolls back the database transaction if notification dispatch fails', function (): void {
    app()->bind(Dispatcher::class, function () {
        $mock = Mockery::mock(Dispatcher::class);
        $mock->shouldReceive('send')
            ->andThrow(new RuntimeException('SMS provider service unavailable'));

        return $mock;
    });

    expect(fn () => $this->service->generate('+573001234567', ContactType::Sms))
        ->toThrow(RuntimeException::class, 'SMS provider service unavailable');

    // Assert no security code was persisted in the database
    $this->assertDatabaseEmpty('security_codes');
});

test('it trims whitespace from verification code input', function (): void {
    $plainCode = '482910';

    SecurityCode::factory()->create([
        'contact_value' => 'user@turnex.test',
        'contact_type' => ContactType::Email,
        'code' => Hash::make($plainCode),
    ]);

    $verified = $this->service->verify('user@turnex.test', '  482910  ', ContactType::Email);

    expect($verified)->toBeTrue();
});

test('it successfully verifies a valid code and marks it as used atomically', function (): void {
    $plainCode = '123456';

    $code = SecurityCode::factory()->create([
        'contact_value' => 'user@turnex.test',
        'contact_type' => ContactType::Email,
        'code' => Hash::make($plainCode),
    ]);

    expect($code->isUsed())->toBeFalse();

    $verified = $this->service->verify('user@turnex.test', $plainCode, ContactType::Email);

    expect($verified)->toBeTrue();

    $code->refresh();
    expect($code->isUsed())->toBeTrue()
        ->and($code->used_at)->not->toBeNull();
});

test('it rejects already used security codes', function (): void {
    $plainCode = '123456';

    SecurityCode::factory()->used()->create([
        'contact_value' => 'user@turnex.test',
        'contact_type' => ContactType::Email,
        'code' => Hash::make($plainCode),
    ]);

    $verified = $this->service->verify('user@turnex.test', $plainCode, ContactType::Email);

    expect($verified)->toBeFalse();
});

test('it increments attempts counter on failed verification', function (): void {
    $code = SecurityCode::factory()->create([
        'contact_value' => 'user@turnex.test',
        'contact_type' => ContactType::Email,
        'code' => Hash::make('123456'),
        'attempts_count' => 0,
    ]);

    $verified = $this->service->verify('user@turnex.test', '999999', ContactType::Email);

    expect($verified)->toBeFalse();

    $code->refresh();
    expect($code->attempts_count)->toBe(1);
});

test('it immediately invalidates the security code upon the 5th failed attempt', function (): void {
    $code = SecurityCode::factory()->create([
        'contact_value' => 'user@turnex.test',
        'contact_type' => ContactType::Email,
        'code' => Hash::make('123456'),
        'attempts_count' => 4,
    ]);

    // 5th attempt
    $verified = $this->service->verify('user@turnex.test', '999999', ContactType::Email);

    expect($verified)->toBeFalse();

    $code->refresh();
    expect($code->attempts_count)->toBe(5)
        ->and($code->isExpired())->toBeTrue();

    // Even if the user enters the correct code now, it must be rejected
    $afterInvalidated = $this->service->verify('user@turnex.test', '123456', ContactType::Email);
    expect($afterInvalidated)->toBeFalse();
});

test('it rejects expired codes without incrementing the failed attempt counter', function (): void {
    $code = SecurityCode::factory()->expired()->create([
        'contact_value' => 'user@turnex.test',
        'contact_type' => ContactType::Email,
        'code' => Hash::make('123456'),
        'attempts_count' => 1,
    ]);

    $verified = $this->service->verify('user@turnex.test', '999999', ContactType::Email);

    expect($verified)->toBeFalse();

    $code->refresh();
    // Precedence rule: attempt counter must NOT increment for expired codes
    expect($code->attempts_count)->toBe(1);
});

test('it rejects codes belonging to a different channel', function (): void {
    $plainCode = '123456';

    SecurityCode::factory()->create([
        'contact_value' => '+573001234567',
        'contact_type' => ContactType::Sms,
        'code' => Hash::make($plainCode),
    ]);

    // Verifying with WhatsApp channel when the code was issued for SMS must fail
    $verified = $this->service->verify('+573001234567', $plainCode, ContactType::WhatsApp);

    expect($verified)->toBeFalse();
});
