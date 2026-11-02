<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use Database\Seeders\BusinessSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('business seeder creates test businesses with password prueba123 in non-production', function (): void {
    $this->seed(BusinessSeeder::class);

    $business = Business::where('email', 'contacto@panaderia.test')->first();

    expect($business)->not->toBeNull()
        ->and($business->business_name)->toBe('Panadería La Especial')
        ->and($business->verification_status)->toBe(VerificationStatus::Approved)
        ->and($business->hasVerifiedEmail())->toBeTrue()
        ->and($business->hasVerifiedPhone())->toBeTrue()
        ->and(Hash::check('prueba123', $business->password))->toBeTrue();

    $barberia = Business::where('email', 'barberia@turnex.test')->first();
    expect($barberia)->not->toBeNull()
        ->and(Hash::check('prueba123', $barberia->password))->toBeTrue();

    // Verify all seeded businesses have the password prueba123
    Business::all()->each(function (Business $b): void {
        expect(Hash::check('prueba123', $b->password))->toBeTrue();
    });
});

test('business seeder cannot be executed in production environment', function (): void {
    $this->app->instance('env', 'production');

    expect(app()->isProduction())->toBeTrue();

    expect(fn () => (new BusinessSeeder)->run())
        ->toThrow(RuntimeException::class, 'Cannot run test seeder in production environment.');
});

test('customer seeder creates test customers with password prueba123 in non-production', function (): void {
    $this->seed(CustomerSeeder::class);

    $customer = Customer::where('email', 'carlos.perez@turnex.test')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->fullName())->toBe('Carlos Pérez')
        ->and($customer->hasVerifiedEmail())->toBeTrue()
        ->and($customer->hasVerifiedPhone())->toBeTrue()
        ->and(Hash::check('prueba123', $customer->password))->toBeTrue();

    // Verify all seeded customers have the password prueba123
    Customer::all()->each(function (Customer $c): void {
        expect(Hash::check('prueba123', $c->password))->toBeTrue();
    });
});

test('customer seeder cannot be executed in production environment', function (): void {
    $this->app->instance('env', 'production');

    expect(app()->isProduction())->toBeTrue();

    expect(fn () => (new CustomerSeeder)->run())
        ->toThrow(RuntimeException::class, 'Cannot run test seeder in production environment.');
});

test('database seeder calls test seeders in non-production environment', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Business::where('email', 'contacto@panaderia.test')->exists())->toBeTrue()
        ->and(Customer::where('email', 'carlos.perez@turnex.test')->exists())->toBeTrue();
});

test('database seeder skips test seeders in production environment', function (): void {
    $this->app->instance('env', 'production');

    expect(app()->isProduction())->toBeTrue();

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(Business::where('email', 'contacto@panaderia.test')->exists())->toBeFalse()
        ->and(Customer::where('email', 'carlos.perez@turnex.test')->exists())->toBeFalse();
});
