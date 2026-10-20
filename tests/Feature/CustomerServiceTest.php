<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Notifications\WelcomeCustomerNotification;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('it registers a customer with normalized fields and sets phone as verified', function (): void {
    Notification::fake();

    /** @var CustomerService $service */
    $service = app(CustomerService::class);

    $customer = $service->register([
        'first_name' => '  carlos   andrés  ',
        'last_name' => '  pérez   gómez  ',
        'email' => '  Carlos.Perez@Turnex.Test  ',
        'phone_number' => '(300) 123-4567',
        'password' => 'Turnex@2026!',
    ]);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->first_name)->toBe('carlos andrés')
        ->and($customer->last_name)->toBe('pérez gómez')
        ->and($customer->email)->toBe('carlos.perez@turnex.test')
        ->and($customer->phone_number)->toBe('+573001234567')
        ->and($customer->phone_verified_at)->not->toBeNull()
        ->and($customer->email_verified_at)->toBeNull()
        ->and(Hash::check('Turnex@2026!', $customer->password))->toBeTrue();

    Notification::assertSentTo($customer, WelcomeCustomerNotification::class);
});

test('it checks whether a phone number is already registered using normalized search', function (): void {
    Customer::factory()->create([
        'phone_number' => '+573001234567',
    ]);

    /** @var CustomerService $service */
    $service = app(CustomerService::class);

    expect($service->isPhoneRegistered('3001234567'))->toBeTrue()
        ->and($service->isPhoneRegistered('+57 (300) 123 4567'))->toBeTrue()
        ->and($service->isPhoneRegistered('3109999999'))->toBeFalse();
});

test('it checks whether an email is already registered using normalized search', function (): void {
    Customer::factory()->create([
        'email' => 'carlos@turnex.test',
    ]);

    /** @var CustomerService $service */
    $service = app(CustomerService::class);

    expect($service->isEmailRegistered('Carlos@Turnex.Test'))->toBeTrue()
        ->and($service->isEmailRegistered('  carlos@turnex.test  '))->toBeTrue()
        ->and($service->isEmailRegistered('other@turnex.test'))->toBeFalse();
});
