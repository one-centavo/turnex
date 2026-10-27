<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\BusinessCategory;
use App\Models\Business;
use App\Support\DataNormalizer;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class BusinessRules
{
    /**
     * Regex pattern for letters, Latin diacritics, and whitespace.
     */
    public const string NAME_REGEX = '/^[\pL\s]+$/u';

    /**
     * Regex pattern for normalized Colombian mobile numbers (+57 followed by 3 and 9 digits).
     */
    public const string NORMALIZED_PHONE_REGEX = '/^\+573\d{9}$/';

    /**
     * Get validation rules for business commercial name.
     *
     * @return array<int, string>
     */
    public static function businessName(): array
    {
        return ['required', 'string', 'min:3', 'max:150'];
    }

    /**
     * Get validation rules for business industry category.
     *
     * @return array<int, mixed>
     */
    public static function category(): array
    {
        return ['required', 'string', Rule::enum(BusinessCategory::class)];
    }

    /**
     * Get validation rules for business physical address or location.
     *
     * @return array<int, string>
     */
    public static function location(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get validation rules for owner first name.
     *
     * @return array<int, string>
     */
    public static function ownerFirstName(): array
    {
        return ['required', 'string', 'min:2', 'max:60', 'regex:'.self::NAME_REGEX];
    }

    /**
     * Get validation rules for optional owner middle name.
     *
     * @return array<int, string>
     */
    public static function ownerMiddleName(): array
    {
        return ['nullable', 'string', 'max:60', 'regex:'.self::NAME_REGEX];
    }

    /**
     * Get validation rules for owner last name.
     *
     * @return array<int, string>
     */
    public static function ownerLastName(): array
    {
        return ['required', 'string', 'min:2', 'max:60', 'regex:'.self::NAME_REGEX];
    }

    /**
     * Get validation rules for optional owner second last name.
     *
     * @return array<int, string>
     */
    public static function ownerSecondLastName(): array
    {
        return ['nullable', 'string', 'max:60', 'regex:'.self::NAME_REGEX];
    }

    /**
     * Get validation rules for business email address.
     *
     * @return array<int, mixed>
     */
    public static function email(?int $ignoreBusinessId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreBusinessId): void {
                $normalized = DataNormalizer::email((string) $value);

                $query = Business::where('email', $normalized);

                if ($ignoreBusinessId !== null) {
                    $query->where('id', '!=', $ignoreBusinessId);
                }

                if ($query->exists()) {
                    $fail('Este correo electrónico ya se encuentra registrado a un negocio.');
                }
            },
        ];
    }

    /**
     * Get validation rules for business mobile phone number.
     *
     * @return array<int, mixed>
     */
    public static function phone(?int $ignoreBusinessId = null): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreBusinessId): void {
                $normalized = DataNormalizer::phone((string) $value);

                if (! preg_match(self::NORMALIZED_PHONE_REGEX, $normalized)) {
                    $fail('Ingresa un número de celular válido (ej. 300 123 4567).');

                    return;
                }

                $query = Business::where('phone_number', $normalized);

                if ($ignoreBusinessId !== null) {
                    $query->where('id', '!=', $ignoreBusinessId);
                }

                if ($query->exists()) {
                    $fail('Este número de celular ya se encuentra registrado a un negocio.');
                }
            },
        ];
    }

    /**
     * Get validation rules for business owner password.
     *
     * @return array<int, mixed>
     */
    public static function password(bool $confirmed = true): array
    {
        $passwordRule = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();

        $rules = [
            'required',
            'string',
            'max:64',
            'not_regex:/\s/',
            $passwordRule,
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * Get validation rules for accepting terms and privacy policy.
     *
     * @return array<int, string>
     */
    public static function terms(): array
    {
        return ['accepted'];
    }

    /**
     * Custom friendly Spanish error messages for business validation rules.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'business_name.required' => 'El nombre del negocio es obligatorio.',
            'business_name.min' => 'El nombre del negocio debe tener al menos 3 caracteres.',
            'business_name.max' => 'El nombre del negocio no debe superar los 150 caracteres.',
            'business_category.required' => 'La categoría del negocio es obligatoria.',
            'category.required' => 'La categoría del negocio es obligatoria.',
            'location.required' => 'La dirección o ubicación es obligatoria.',
            'location.max' => 'La dirección no debe superar los 255 caracteres.',
            'owner_first_name.required' => 'El nombre del propietario es obligatorio.',
            'owner_first_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'owner_first_name.max' => 'El nombre no debe superar los 60 caracteres.',
            'owner_first_name.regex' => 'El nombre solo debe contener letras y espacios.',
            'owner_middle_name.max' => 'El segundo nombre no debe superar los 60 caracteres.',
            'owner_middle_name.regex' => 'El segundo nombre solo debe contener letras y espacios.',
            'owner_last_name.required' => 'El apellido del propietario es obligatorio.',
            'owner_last_name.min' => 'El apellido debe tener al menos 2 caracteres.',
            'owner_last_name.max' => 'El apellido no debe superar los 60 caracteres.',
            'owner_last_name.regex' => 'El apellido solo debe contener letras y espacios.',
            'owner_second_last_name.max' => 'El segundo apellido no debe superar los 60 caracteres.',
            'owner_second_last_name.regex' => 'El segundo apellido solo debe contener letras y espacios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado a un negocio.',
            'phone.required' => 'El número de celular es obligatorio.',
            'phone.regex' => 'Ingresa un número de celular válido (ej. 300 123 4567).',
            'phone.unique' => 'Este número de celular ya se encuentra registrado a un negocio.',
            'phone_number.required' => 'El número de celular es obligatorio.',
            'phone_number.regex' => 'Ingresa un número de celular válido (ej. 300 123 4567).',
            'phone_number.unique' => 'Este número de celular ya se encuentra registrado a un negocio.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.max' => 'La contraseña no debe superar los 64 caracteres.',
            'password.not_regex' => 'La contraseña no debe contener espacios en blanco.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'terms.accepted' => 'Debes aceptar los Términos y Condiciones y la Política de Privacidad.',
        ];
    }
}
