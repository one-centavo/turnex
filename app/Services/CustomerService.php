<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Notifications\WelcomeCustomerNotification;
use App\Support\DataNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CustomerService
{
    /**
     * Create a new CustomerService instance.
     */
    public function __construct(
        public readonly OtpService $otpService,
    ) {}

    /**
     * Register a new customer, normalizing contact details and dispatching welcome notification.
     *
     * @param  array{first_name: string, last_name: string, email: string, password: string, phone_number?: string|null}  $data
     */
    public function register(array $data): Customer
    {
        $normalizedFirstName = DataNormalizer::name($data['first_name']);
        $normalizedLastName = DataNormalizer::name($data['last_name']);
        $normalizedEmail = DataNormalizer::email($data['email']);
        $normalizedPhone = ! empty($data['phone_number'])
            ? DataNormalizer::phone($data['phone_number'])
            : null;

        return DB::transaction(function () use ($normalizedFirstName, $normalizedLastName, $normalizedEmail, $normalizedPhone, $data): Customer {
            $customer = Customer::create([
                'first_name' => $normalizedFirstName,
                'last_name' => $normalizedLastName,
                'email' => $normalizedEmail,
                'phone_number' => $normalizedPhone,
                'password' => $data['password'],
                'email_verified_at' => now(),
                'phone_verified_at' => null,
            ]);

            Notification::send($customer, new WelcomeCustomerNotification);

            return $customer;
        });
    }

    /**
     * Determine if a normalized phone number is already registered to a customer.
     */
    public function isPhoneRegistered(string $phone): bool
    {
        $normalizedPhone = DataNormalizer::phone($phone);

        return Customer::where('phone_number', $normalizedPhone)->exists();
    }

    /**
     * Determine if a normalized email address is already registered to a customer.
     */
    public function isEmailRegistered(string $email): bool
    {
        $normalizedEmail = DataNormalizer::email($email);

        return Customer::where('email', $normalizedEmail)->exists();
    }
}
