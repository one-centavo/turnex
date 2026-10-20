<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Pérez',
            'email' => 'carlos.perez@turnex.test',
            'phone_number' => '+573001234567',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        Customer::factory()->count(10)->create();
    }
}
