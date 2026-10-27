<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Rules\CustomerRules;
use Illuminate\Support\Facades\Validator;

test('it validates first and last names correctly', function (): void {
    $validator = Validator::make([
        'first_name' => 'Carlos Andrés',
        'last_name' => 'Pérez Gómez',
    ], [
        'first_name' => CustomerRules::firstName(),
        'last_name' => CustomerRules::lastName(),
    ]);

    expect($validator->passes())->toBeTrue();
});

test('it rejects invalid name characters and lengths', function (): void {
    $validator = Validator::make([
        'first_name' => 'C',
        'last_name' => 'Pérez123!',
    ], [
        'first_name' => CustomerRules::firstName(),
        'last_name' => CustomerRules::lastName(),
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('first_name'))->toBeTrue()
        ->and($validator->errors()->has('last_name'))->toBeTrue();
});

test('it validates email formatting and detects duplicates even with casing and whitespace differences', function (): void {
    Customer::factory()->create(['email' => 'carlos@turnex.test']);

    $duplicateValidator = Validator::make([
        'email' => '  CARLOS@TURNEX.TEST  ',
    ], [
        'email' => CustomerRules::email(),
    ]);

    expect($duplicateValidator->fails())->toBeTrue()
        ->and($duplicateValidator->errors()->has('email'))->toBeTrue();

    $validValidator = Validator::make([
        'email' => 'new.customer@turnex.test',
    ], [
        'email' => CustomerRules::email(),
    ]);

    expect($validValidator->passes())->toBeTrue();
});

test('it allows ignoring customer id on email update', function (): void {
    $customer = Customer::factory()->create(['email' => 'current@turnex.test']);

    $validator = Validator::make([
        'email' => 'CURRENT@TURNEX.TEST',
    ], [
        'email' => CustomerRules::email($customer->id),
    ]);

    expect($validator->passes())->toBeTrue();
});

test('it validates Colombian phone format and detects duplicates even with unnormalized input', function (): void {
    Customer::factory()->create(['phone_number' => '+573001234567']);

    // Raw 10 digits without country code should match +573001234567
    $rawDuplicateValidator = Validator::make([
        'phone_number' => '3001234567',
    ], [
        'phone_number' => CustomerRules::phone(),
    ]);

    expect($rawDuplicateValidator->fails())->toBeTrue();

    // Formatted with parentheses and spaces should also match +573001234567
    $formattedDuplicateValidator = Validator::make([
        'phone_number' => '(300) 123-4567',
    ], [
        'phone_number' => CustomerRules::phone(),
    ]);

    expect($formattedDuplicateValidator->fails())->toBeTrue();

    // Different number should pass
    $validValidator = Validator::make([
        'phone_number' => '3109876543',
    ], [
        'phone_number' => CustomerRules::phone(),
    ]);

    expect($validValidator->passes())->toBeTrue();

    // Malformed number should fail regex
    $invalidFormatValidator = Validator::make([
        'phone_number' => '12345',
    ], [
        'phone_number' => CustomerRules::phone(),
    ]);

    expect($invalidFormatValidator->fails())->toBeTrue();
});

test('it allows ignoring customer id on phone update', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '+573001234567']);

    $validator = Validator::make([
        'phone_number' => '(300) 123-4567',
    ], [
        'phone_number' => CustomerRules::phone($customer->id),
    ]);

    expect($validator->passes())->toBeTrue();
});

test('it validates password security rules and confirmation', function (): void {
    $invalidValidator = Validator::make([
        'password' => 'simple',
        'password_confirmation' => 'simple',
    ], [
        'password' => CustomerRules::password(),
    ]);

    expect($invalidValidator->fails())->toBeTrue();

    // Rejects spaces in password
    $spacesValidator = Validator::make([
        'password' => 'Turnex @2026!',
        'password_confirmation' => 'Turnex @2026!',
    ], [
        'password' => CustomerRules::password(),
    ], CustomerRules::messages());

    expect($spacesValidator->fails())->toBeTrue()
        ->and($spacesValidator->errors()->first('password'))->toBe('La contraseña no debe contener espacios en blanco.');

    // Rejects password exceeding 64 characters
    $tooLongValidator = Validator::make([
        'password' => str_repeat('A', 30).str_repeat('a', 30).'123!@#$$$', // 69 chars
        'password_confirmation' => str_repeat('A', 30).str_repeat('a', 30).'123!@#$$$',
    ], [
        'password' => CustomerRules::password(),
    ], CustomerRules::messages());

    expect($tooLongValidator->fails())->toBeTrue()
        ->and($tooLongValidator->errors()->first('password'))->toBe('La contraseña no debe superar los 64 caracteres.');

    $validValidator = Validator::make([
        'password' => 'Turnex@2026!',
        'password_confirmation' => 'Turnex@2026!',
    ], [
        'password' => CustomerRules::password(),
    ]);

    expect($validValidator->passes())->toBeTrue();
});

test('it validates terms acceptance', function (): void {
    $invalidValidator = Validator::make([
        'terms' => false,
    ], [
        'terms' => CustomerRules::terms(),
    ]);

    expect($invalidValidator->fails())->toBeTrue();

    $validValidator = Validator::make([
        'terms' => true,
    ], [
        'terms' => CustomerRules::terms(),
    ]);

    expect($validValidator->passes())->toBeTrue();
});
