<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Models\Business;
use App\Rules\BusinessRules;
use Illuminate\Support\Facades\Validator;

test('it validates business commercial name lengths and presence', function (): void {
    $valid = Validator::make([
        'business_name' => 'Panadería & Pastelería Central',
    ], [
        'business_name' => BusinessRules::businessName(),
    ]);

    expect($valid->passes())->toBeTrue();

    $tooShort = Validator::make([
        'business_name' => 'AB',
    ], [
        'business_name' => BusinessRules::businessName(),
    ]);

    expect($tooShort->fails())->toBeTrue()
        ->and($tooShort->errors()->has('business_name'))->toBeTrue();

    $tooLong = Validator::make([
        'business_name' => str_repeat('A', 151),
    ], [
        'business_name' => BusinessRules::businessName(),
    ]);

    expect($tooLong->fails())->toBeTrue()
        ->and($tooLong->errors()->has('business_name'))->toBeTrue();
});

test('it validates business industry category against enum values', function (): void {
    $validEnum = Validator::make([
        'business_category' => BusinessCategory::Restaurant->value,
    ], [
        'business_category' => BusinessRules::category(),
    ]);

    expect($validEnum->passes())->toBeTrue();

    $invalidCategory = Validator::make([
        'business_category' => 'unsupported_industry_xyz',
    ], [
        'business_category' => BusinessRules::category(),
    ]);

    expect($invalidCategory->fails())->toBeTrue()
        ->and($invalidCategory->errors()->has('business_category'))->toBeTrue();
});

test('it validates physical location presence and maximum length', function (): void {
    $valid = Validator::make([
        'location' => 'Calle 100 # 15-20, Oficina 402, Bogotá',
    ], [
        'location' => BusinessRules::location(),
    ]);

    expect($valid->passes())->toBeTrue();

    $empty = Validator::make([
        'location' => '',
    ], [
        'location' => BusinessRules::location(),
    ]);

    expect($empty->fails())->toBeTrue()
        ->and($empty->errors()->has('location'))->toBeTrue();

    $tooLong = Validator::make([
        'location' => str_repeat('X', 256),
    ], [
        'location' => BusinessRules::location(),
    ]);

    expect($tooLong->fails())->toBeTrue()
        ->and($tooLong->errors()->has('location'))->toBeTrue();
});

test('it validates owner first and last names with strict regex and lengths', function (): void {
    $valid = Validator::make([
        'owner_first_name' => 'María José',
        'owner_last_name' => 'Gómez Rodríguez',
    ], [
        'owner_first_name' => BusinessRules::ownerFirstName(),
        'owner_last_name' => BusinessRules::ownerLastName(),
    ]);

    expect($valid->passes())->toBeTrue();

    $invalidCharacters = Validator::make([
        'owner_first_name' => 'María123',
        'owner_last_name' => 'Gómez@!',
    ], [
        'owner_first_name' => BusinessRules::ownerFirstName(),
        'owner_last_name' => BusinessRules::ownerLastName(),
    ]);

    expect($invalidCharacters->fails())->toBeTrue()
        ->and($invalidCharacters->errors()->has('owner_first_name'))->toBeTrue()
        ->and($invalidCharacters->errors()->has('owner_last_name'))->toBeTrue();

    $tooShort = Validator::make([
        'owner_first_name' => 'M',
        'owner_last_name' => 'G',
    ], [
        'owner_first_name' => BusinessRules::ownerFirstName(),
        'owner_last_name' => BusinessRules::ownerLastName(),
    ]);

    expect($tooShort->fails())->toBeTrue()
        ->and($tooShort->errors()->has('owner_first_name'))->toBeTrue()
        ->and($tooShort->errors()->has('owner_last_name'))->toBeTrue();
});

test('it accepts optional middle name and second last name when valid or null', function (): void {
    $validNullable = Validator::make([
        'owner_middle_name' => null,
        'owner_second_last_name' => null,
    ], [
        'owner_middle_name' => BusinessRules::ownerMiddleName(),
        'owner_second_last_name' => BusinessRules::ownerSecondLastName(),
    ]);

    expect($validNullable->passes())->toBeTrue();

    $validStrings = Validator::make([
        'owner_middle_name' => 'Andrés',
        'owner_second_last_name' => 'Hernández',
    ], [
        'owner_middle_name' => BusinessRules::ownerMiddleName(),
        'owner_second_last_name' => BusinessRules::ownerSecondLastName(),
    ]);

    expect($validStrings->passes())->toBeTrue();

    $invalidRegex = Validator::make([
        'owner_middle_name' => 'Andrés#4',
        'owner_second_last_name' => 'Hernández9',
    ], [
        'owner_middle_name' => BusinessRules::ownerMiddleName(),
        'owner_second_last_name' => BusinessRules::ownerSecondLastName(),
    ]);

    expect($invalidRegex->fails())->toBeTrue()
        ->and($invalidRegex->errors()->has('owner_middle_name'))->toBeTrue()
        ->and($invalidRegex->errors()->has('owner_second_last_name'))->toBeTrue();
});

test('it validates business email formatting and detects duplicates against businesses table', function (): void {
    Business::factory()->create([
        'email' => 'contacto@restaurante.test',
    ]);

    $duplicate = Validator::make([
        'email' => '  CONTACTO@RESTAURANTE.TEST  ',
    ], [
        'email' => BusinessRules::email(),
    ]);

    expect($duplicate->fails())->toBeTrue()
        ->and($duplicate->errors()->has('email'))->toBeTrue();

    $valid = Validator::make([
        'email' => 'nuevo.negocio@turnex.test',
    ], [
        'email' => BusinessRules::email(),
    ]);

    expect($valid->passes())->toBeTrue();
});

test('it allows ignoring business id when validating email uniqueness on update', function (): void {
    $business = Business::factory()->create([
        'email' => 'tienda@turnex.test',
    ]);

    $validator = Validator::make([
        'email' => 'TIENDA@TURNEX.TEST',
    ], [
        'email' => BusinessRules::email($business->id),
    ]);

    expect($validator->passes())->toBeTrue();
});

test('it validates Colombian phone format and detects duplicate against businesses table', function (): void {
    Business::factory()->create([
        'phone_number' => '+573001234567',
    ]);

    // Raw 10 digits matches normalized +573001234567
    $rawDuplicate = Validator::make([
        'phone_number' => '3001234567',
    ], [
        'phone_number' => BusinessRules::phone(),
    ]);

    expect($rawDuplicate->fails())->toBeTrue();

    // Formatted matches
    $formattedDuplicate = Validator::make([
        'phone_number' => '+57 (300) 123-4567',
    ], [
        'phone_number' => BusinessRules::phone(),
    ]);

    expect($formattedDuplicate->fails())->toBeTrue();

    // New valid number passes
    $valid = Validator::make([
        'phone_number' => '3159876543',
    ], [
        'phone_number' => BusinessRules::phone(),
    ]);

    expect($valid->passes())->toBeTrue();

    // Malformed format fails
    $invalidFormat = Validator::make([
        'phone_number' => '12345',
    ], [
        'phone_number' => BusinessRules::phone(),
    ]);

    expect($invalidFormat->fails())->toBeTrue();
});

test('it allows ignoring business id when validating phone uniqueness', function (): void {
    $business = Business::factory()->create([
        'phone_number' => '+573001234567',
    ]);

    $validator = Validator::make([
        'phone_number' => '300 123 4567',
    ], [
        'phone_number' => BusinessRules::phone($business->id),
    ]);

    expect($validator->passes())->toBeTrue();
});

test('it validates password complexity and confirmation', function (): void {
    $valid = Validator::make([
        'password' => 'Turnex@2026!',
        'password_confirmation' => 'Turnex@2026!',
    ], [
        'password' => BusinessRules::password(confirmed: true),
    ]);

    expect($valid->passes())->toBeTrue();

    $unconfirmed = Validator::make([
        'password' => 'Turnex@2026!',
        'password_confirmation' => 'DifferentPassword@2026!',
    ], [
        'password' => BusinessRules::password(confirmed: true),
    ]);

    expect($unconfirmed->fails())->toBeTrue()
        ->and($unconfirmed->errors()->has('password'))->toBeTrue();

    $tooSimple = Validator::make([
        'password' => 'simple',
        'password_confirmation' => 'simple',
    ], [
        'password' => BusinessRules::password(confirmed: true),
    ]);

    expect($tooSimple->fails())->toBeTrue();

    // Rejects spaces in password
    $spaces = Validator::make([
        'password' => 'Turnex @2026!',
        'password_confirmation' => 'Turnex @2026!',
    ], [
        'password' => BusinessRules::password(confirmed: true),
    ], BusinessRules::messages());

    expect($spaces->fails())->toBeTrue()
        ->and($spaces->errors()->first('password'))->toBe('La contraseña no debe contener espacios en blanco.');

    // Rejects password exceeding 64 characters
    $tooLong = Validator::make([
        'password' => str_repeat('A', 30).str_repeat('a', 30).'123!@#$$$',
        'password_confirmation' => str_repeat('A', 30).str_repeat('a', 30).'123!@#$$$',
    ], [
        'password' => BusinessRules::password(confirmed: true),
    ], BusinessRules::messages());

    expect($tooLong->fails())->toBeTrue()
        ->and($tooLong->errors()->first('password'))->toBe('La contraseña no debe superar los 64 caracteres.');
});

test('it validates acceptance of terms and privacy policy', function (): void {
    $valid = Validator::make(['terms' => true], ['terms' => BusinessRules::terms()]);
    expect($valid->passes())->toBeTrue();

    $invalid = Validator::make(['terms' => false], ['terms' => BusinessRules::terms()]);
    expect($invalid->fails())->toBeTrue();
});

test('it returns custom friendly Spanish messages for all business fields', function (): void {
    $messages = BusinessRules::messages();

    expect($messages)->toBeArray()
        ->and($messages)->toHaveKey('business_name.required')
        ->and($messages)->toHaveKey('business_category.required')
        ->and($messages)->toHaveKey('owner_first_name.required')
        ->and($messages)->toHaveKey('owner_last_name.required')
        ->and($messages)->toHaveKey('email.required')
        ->and($messages)->toHaveKey('phone_number.required')
        ->and($messages)->toHaveKey('password.required')
        ->and($messages)->toHaveKey('terms.accepted');
});
