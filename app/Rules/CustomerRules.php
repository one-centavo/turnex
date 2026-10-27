<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Customer;
use App\Support\DataNormalizer;
use Closure;
use Illuminate\Validation\Rules\Password;

final class CustomerRules
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
     * Get validation rules for first name.
     *
     * @return array<int, string>
     */
    public static function firstName(): array
    {
        return ['required', 'string', 'min:2', 'max:60', 'regex:'.self::NAME_REGEX];
    }

    /**
     * Get validation rules for last name.
     *
     * @return array<int, string>
     */
    public static function lastName(): array
    {
        return ['required', 'string', 'min:2', 'max:60', 'regex:'.self::NAME_REGEX];
    }

    /**
     * Get validation rules for customer email address.
     *
     * @return array<int, mixed>
     */
    public static function email(?int $ignoreCustomerId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreCustomerId): void {
                $normalized = DataNormalizer::email((string) $value);

                $query = Customer::where('email', $normalized);

                if ($ignoreCustomerId !== null) {
                    $query->where('id', '!=', $ignoreCustomerId);
                }

                if ($query->exists()) {
                    $fail('Este correo electrónico ya se encuentra registrado.');
                }
            },
        ];
    }

    /**
     * Get validation rules for customer mobile phone.
     *
     * @return array<int, mixed>
     */
    public static function phone(?int $ignoreCustomerId = null): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreCustomerId): void {
                $normalized = DataNormalizer::phone((string) $value);

                if (! preg_match(self::NORMALIZED_PHONE_REGEX, $normalized)) {
                    $fail('Ingresa un número de celular válido (ej. 300 123 4567).');

                    return;
                }

                $query = Customer::where('phone_number', $normalized);

                if ($ignoreCustomerId !== null) {
                    $query->where('id', '!=', $ignoreCustomerId);
                }

                if ($query->exists()) {
                    $fail('Este número de celular ya se encuentra registrado.');
                }
            },
        ];
    }

    /**
     * Get validation rules for customer password.
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
     * Custom friendly Spanish error messages for customer validation rules.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'first_name.max' => 'El nombre no debe superar los 60 caracteres.',
            'first_name.regex' => 'El nombre solo debe contener letras y espacios.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.min' => 'El apellido debe tener al menos 2 caracteres.',
            'last_name.max' => 'El apellido no debe superar los 60 caracteres.',
            'last_name.regex' => 'El apellido solo debe contener letras y espacios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
            'phone.required' => 'El número de celular es obligatorio.',
            'phone.regex' => 'Ingresa un número de celular válido (ej. 300 123 4567).',
            'phone.unique' => 'Este número de celular ya se encuentra registrado.',
            'phone_number.required' => 'El número de celular es obligatorio.',
            'phone_number.regex' => 'Ingresa un número de celular válido (ej. 300 123 4567).',
            'phone_number.unique' => 'Este número de celular ya se encuentra registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.max' => 'La contraseña no debe superar los 64 caracteres.',
            'password.not_regex' => 'La contraseña no debe contener espacios en blanco.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'terms.accepted' => 'Debes aceptar los Términos y Condiciones y la Política de Privacidad.',
        ];
    }
}
