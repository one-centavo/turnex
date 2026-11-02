<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BusinessCategory;
use App\Enums\PersonType;
use App\Enums\VerificationStatus;
use App\Models\Business;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Cannot run test seeder in production environment.');
        }

        $businesses = [
            [
                'email' => 'contacto@panaderia.test',
                'business_name' => 'Panadería La Especial',
                'person_type' => PersonType::Natural,
                'business_category' => BusinessCategory::Restaurant,
                'owner_first_name' => 'Carlos',
                'owner_last_name' => 'Gómez',
                'phone_number' => '+573001112233',
                'location' => 'Cra 7 # 72-10, Bogotá',
                'business_description' => 'Panadería artesanal y cafetería de especialidad.',
                'verification_status' => VerificationStatus::Approved,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'password' => Hash::make('prueba123'),
                'reviewed_at' => now(),
            ],
            [
                'email' => 'barberia@turnex.test',
                'business_name' => 'Barbería Capital',
                'person_type' => PersonType::LegalEntity,
                'tax_id' => '900123456',
                'dv' => '1',
                'business_category' => BusinessCategory::HealthAndBeauty,
                'owner_first_name' => 'Alejandro',
                'owner_last_name' => 'Torres',
                'phone_number' => '+573004445566',
                'location' => 'Calle 85 # 11-53, Bogotá',
                'business_description' => 'Cortes clásicos, cuidado de barba y estilo contemporáneo.',
                'verification_status' => VerificationStatus::Approved,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'password' => Hash::make('prueba123'),
                'reviewed_at' => now(),
            ],
        ];

        foreach ($businesses as $businessData) {
            Business::updateOrCreate(
                ['email' => $businessData['email']],
                $businessData
            );
        }

        if (Business::count() < 5) {
            Business::factory()->count(5 - Business::count())->create([
                'password' => Hash::make('prueba123'),
            ]);
        }
    }
}
