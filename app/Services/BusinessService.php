<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessCategory;
use App\Enums\PersonType;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Notifications\WelcomeBusinessNotification;
use App\Support\DataNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BusinessService
{
    /**
     * Create a new BusinessService instance.
     */
    public function __construct(
        public readonly OtpService $otpService,
    ) {}

    /**
     * Register a new business, normalizing details and dispatching welcome notification.
     *
     * @param  array{
     *     business_name: string,
     *     business_category: BusinessCategory|string,
     *     person_type?: PersonType|string|null,
     *     tax_id?: string|null,
     *     dv?: string|null,
     *     owner_first_name: string,
     *     owner_middle_name?: string|null,
     *     owner_last_name: string,
     *     owner_second_last_name?: string|null,
     *     email: string,
     *     phone_number: string,
     *     location: string,
     *     password: string,
     * }  $data
     */
    public function register(array $data): Business
    {
        $normalizedBusinessName = DataNormalizer::name($data['business_name']);
        $normalizedCategory = $data['business_category'] instanceof BusinessCategory
            ? $data['business_category']
            : BusinessCategory::from((string) $data['business_category']);
        $personType = isset($data['person_type']) && filled($data['person_type'])
            ? ($data['person_type'] instanceof PersonType ? $data['person_type'] : PersonType::from((string) $data['person_type']))
            : PersonType::Natural;
        $taxId = filled($data['tax_id'] ?? null) ? trim((string) $data['tax_id']) : null;
        $dv = filled($data['dv'] ?? null) ? trim((string) $data['dv']) : null;
        $normalizedOwnerFirstName = DataNormalizer::name($data['owner_first_name']);
        $normalizedOwnerMiddleName = filled($data['owner_middle_name'] ?? null)
            ? DataNormalizer::name((string) $data['owner_middle_name'])
            : null;
        $normalizedOwnerLastName = DataNormalizer::name($data['owner_last_name']);
        $normalizedOwnerSecondLastName = filled($data['owner_second_last_name'] ?? null)
            ? DataNormalizer::name((string) $data['owner_second_last_name'])
            : null;
        $normalizedEmail = DataNormalizer::email($data['email']);
        $normalizedPhone = DataNormalizer::phone($data['phone_number']);
        $normalizedLocation = DataNormalizer::name($data['location']);

        return DB::transaction(function () use (
            $normalizedBusinessName,
            $normalizedCategory,
            $personType,
            $taxId,
            $dv,
            $normalizedOwnerFirstName,
            $normalizedOwnerMiddleName,
            $normalizedOwnerLastName,
            $normalizedOwnerSecondLastName,
            $normalizedEmail,
            $normalizedPhone,
            $normalizedLocation,
            $data,
        ): Business {
            $business = Business::create([
                'business_name' => $normalizedBusinessName,
                'business_category' => $normalizedCategory,
                'person_type' => $personType,
                'tax_id' => $taxId,
                'dv' => $dv,
                'owner_first_name' => $normalizedOwnerFirstName,
                'owner_middle_name' => $normalizedOwnerMiddleName,
                'owner_last_name' => $normalizedOwnerLastName,
                'owner_second_last_name' => $normalizedOwnerSecondLastName,
                'email' => $normalizedEmail,
                'phone_number' => $normalizedPhone,
                'location' => $normalizedLocation,
                'password' => $data['password'],
                'verification_status' => VerificationStatus::PendingVerification,
                'phone_verified_at' => now(),
                'email_verified_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            Notification::send($business, new WelcomeBusinessNotification);

            return $business;
        });
    }

    /**
     * Determine if a normalized phone number is already registered to a business.
     */
    public function isPhoneRegistered(string $phone): bool
    {
        $normalizedPhone = DataNormalizer::phone($phone);

        return Business::where('phone_number', $normalizedPhone)->exists();
    }

    /**
     * Determine if a normalized email address is already registered to a business.
     */
    public function isEmailRegistered(string $email): bool
    {
        $normalizedEmail = DataNormalizer::email($email);

        return Business::where('email', $normalizedEmail)->exists();
    }
}
