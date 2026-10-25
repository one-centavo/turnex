<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Enums\PersonType;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Notifications\WelcomeBusinessNotification;
use App\Services\BusinessService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('it registers a business with normalized fields, pending verification status, and verified phone timestamp', function (): void {
    Notification::fake();

    /** @var BusinessService $service */
    $service = app(BusinessService::class);

    $business = $service->register([
        'business_name' => '   Café   &   Bistró   Central   ',
        'business_category' => BusinessCategory::Restaurant,
        'owner_first_name' => '   carlos   andrés   ',
        'owner_middle_name' => '   felipe   ',
        'owner_last_name' => '   pérez   ',
        'owner_second_last_name' => '   gómez   ',
        'email' => '   Carlos.Perez@Turnex.Test   ',
        'phone_number' => '(300) 123-4567',
        'location' => '   Carrera 7 # 71-21, Bogotá   ',
        'password' => 'Turnex@2026!',
    ]);

    expect($business)->toBeInstanceOf(Business::class)
        ->and($business->business_name)->toBe('Café & Bistró Central')
        ->and($business->business_category)->toBe(BusinessCategory::Restaurant)
        ->and($business->owner_first_name)->toBe('carlos andrés')
        ->and($business->owner_middle_name)->toBe('felipe')
        ->and($business->owner_last_name)->toBe('pérez')
        ->and($business->owner_second_last_name)->toBe('gómez')
        ->and($business->email)->toBe('carlos.perez@turnex.test')
        ->and($business->phone_number)->toBe('+573001234567')
        ->and($business->location)->toBe('Carrera 7 # 71-21, Bogotá')
        ->and($business->verification_status)->toBe(VerificationStatus::PendingVerification)
        ->and($business->person_type)->toBe(PersonType::Natural)
        ->and($business->tax_id)->toBeNull()
        ->and($business->dv)->toBeNull()
        ->and($business->formattedTaxId())->toBeNull()
        ->and($business->isNaturalPerson())->toBeTrue()
        ->and($business->isLegalEntity())->toBeFalse()
        ->and($business->phone_verified_at)->not->toBeNull()
        ->and($business->email_verified_at)->toBeNull()
        ->and($business->reviewed_by)->toBeNull()
        ->and($business->reviewed_at)->toBeNull()
        ->and(Hash::check('Turnex@2026!', $business->password))->toBeTrue();

    Notification::assertSentTo($business, WelcomeBusinessNotification::class);
});

test('it accepts string category value during registration and casts to enum', function (): void {
    Notification::fake();

    /** @var BusinessService $service */
    $service = app(BusinessService::class);

    $business = $service->register([
        'business_name' => 'Salón Belleza & Spa',
        'business_category' => 'health_and_beauty',
        'owner_first_name' => 'Laura',
        'owner_middle_name' => null,
        'owner_last_name' => 'Sánchez',
        'owner_second_last_name' => null,
        'email' => 'laura@salon.test',
        'phone_number' => '3109876543',
        'location' => 'Calle 85 # 11-53',
        'password' => 'Secret@1234',
    ]);

    expect($business->business_category)->toBe(BusinessCategory::HealthAndBeauty)
        ->and($business->owner_middle_name)->toBeNull()
        ->and($business->owner_second_last_name)->toBeNull();
});

test('it checks whether a phone number is registered using normalized lookup', function (): void {
    Business::factory()->create([
        'phone_number' => '+573001234567',
    ]);

    /** @var BusinessService $service */
    $service = app(BusinessService::class);

    expect($service->isPhoneRegistered('3001234567'))->toBeTrue()
        ->and($service->isPhoneRegistered('+57 (300) 123-4567'))->toBeTrue()
        ->and($service->isPhoneRegistered('3159999999'))->toBeFalse();
});

test('it checks whether an email is registered using normalized lookup', function (): void {
    Business::factory()->create([
        'email' => 'contacto@restaurante.test',
    ]);

    /** @var BusinessService $service */
    $service = app(BusinessService::class);

    expect($service->isEmailRegistered('CONTACTO@RESTAURANTE.TEST'))->toBeTrue()
        ->and($service->isEmailRegistered('  contacto@restaurante.test  '))->toBeTrue()
        ->and($service->isEmailRegistered('other@turnex.test'))->toBeFalse();
});

test('it isolates business contact uniqueness from customer table', function (): void {
    Customer::factory()->create([
        'email' => 'crossover@turnex.test',
        'phone_number' => '+573009998877',
    ]);

    /** @var BusinessService $service */
    $service = app(BusinessService::class);

    expect($service->isEmailRegistered('crossover@turnex.test'))->toBeFalse()
        ->and($service->isPhoneRegistered('+573009998877'))->toBeFalse();
});

test('it registers a business with explicit legal entity person type', function (): void {
    Notification::fake();

    /** @var BusinessService $service */
    $service = app(BusinessService::class);

    $business = $service->register([
        'business_name' => 'Inversiones Gastronómicas S.A.S.',
        'business_category' => BusinessCategory::Restaurant,
        'person_type' => PersonType::LegalEntity,
        'tax_id' => '901234567',
        'dv' => '3',
        'owner_first_name' => 'Alejandro',
        'owner_middle_name' => null,
        'owner_last_name' => 'Restrepo',
        'owner_second_last_name' => null,
        'email' => 'admin@inversiones.test',
        'phone_number' => '3112223344',
        'location' => 'Avenida El Dorado # 68C-61',
        'password' => 'Turnex@2026!',
    ]);

    expect($business->person_type)->toBe(PersonType::LegalEntity)
        ->and($business->tax_id)->toBe('901234567')
        ->and($business->dv)->toBe('3')
        ->and($business->formattedTaxId())->toBe('901234567-3')
        ->and($business->isLegalEntity())->toBeTrue()
        ->and($business->isNaturalPerson())->toBeFalse();
});
