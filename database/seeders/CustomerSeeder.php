<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Cannot run test seeder in production environment.');
        }

        $customers = [
            [
                'email' => 'carlos.perez@turnex.test',
                'first_name' => 'Carlos',
                'last_name' => 'Pérez',
                'phone_number' => '+573001234567',
                'password' => Hash::make('prueba123'),
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
            [
                'email' => 'maria.lopez@turnex.test',
                'first_name' => 'María',
                'last_name' => 'López',
                'phone_number' => '+573007654321',
                'password' => Hash::make('prueba123'),
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::updateOrCreate(
                ['email' => $customerData['email']],
                $customerData
            );
        }

        if (Customer::count() < 10) {
            Customer::factory()->count(10 - Customer::count())->create([
                'password' => Hash::make('prueba123'),
            ]);
        }
    }
}
