<?php

declare(strict_types=1);

use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

test('it can create a customer using factory', function (): void {
    $customer = Customer::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
        'email' => 'carlos.perez@turnex.test',
        'phone_number' => '+573001234567',
    ]);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->first_name)->toBe('Carlos')
        ->and($customer->last_name)->toBe('Pérez')
        ->and($customer->email)->toBe('carlos.perez@turnex.test')
        ->and($customer->phone_number)->toBe('+573001234567')
        ->and($customer->fullName())->toBe('Carlos Pérez')
        ->and($customer->initials())->toBe('CP')
        ->and($customer->hasVerifiedPhone())->toBeTrue()
        ->and($customer->hasVerifiedEmail())->toBeFalse();
});

test('it casts dates and password correctly', function (): void {
    $customer = Customer::factory()->create([
        'password' => 'secret-password',
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);

    expect($customer->email_verified_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($customer->phone_verified_at)->toBeInstanceOf(CarbonInterface::class)
        ->and(Hash::check('secret-password', $customer->password))->toBeTrue();
});

test('it hides sensitive attributes from serialization', function (): void {
    $customer = Customer::factory()->create();

    $array = $customer->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token');
});

test('it supports unverifiedPhone state', function (): void {
    $customer = Customer::factory()->unverifiedPhone()->create();

    expect($customer->phone_verified_at)->toBeNull()
        ->and($customer->hasVerifiedPhone())->toBeFalse();
});

test('it supports verifiedEmail state', function (): void {
    $customer = Customer::factory()->verifiedEmail()->create();

    expect($customer->email_verified_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($customer->hasVerifiedEmail())->toBeTrue();
});

test('it supports withGoogle state', function (): void {
    $customer = Customer::factory()->withGoogle('google-sub-98765')->create();

    expect($customer->google_id)->toBe('google-sub-98765')
        ->and($customer->password)->toBeNull();
});
