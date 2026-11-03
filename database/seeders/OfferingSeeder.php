<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Database\Seeder;
use RuntimeException;

class OfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Cannot run test seeder in production environment.');
        }

        if (Category::query()->doesntExist()) {
            $this->call(CategorySeeder::class);
        }

        if (Business::query()->doesntExist()) {
            $this->call(BusinessSeeder::class);
        }

        $foodCategory = Category::where('name', 'Alimentos y Bebidas')->first() ?? Category::firstOrFail();
        $beautyCategory = Category::where('name', 'Belleza y Cuidado Personal')->first() ?? Category::firstOrFail();

        $panaderia = Business::where('email', 'contacto@panaderia.test')->first();
        if ($panaderia !== null) {
            $bakeryOfferings = [
                [
                    'name' => 'Café Americano 8oz',
                    'category_id' => $foodCategory->id,
                    'price' => '4500.00',
                    'estimated_duration_minutes' => 5,
                    'is_active' => true,
                    'description' => 'Café de especialidad recién preparado con notas achocolatadas.',
                ],
                [
                    'name' => 'Capuchino Artesanal',
                    'category_id' => $foodCategory->id,
                    'price' => '6500.00',
                    'estimated_duration_minutes' => 10,
                    'is_active' => true,
                    'description' => 'Espresso doble con leche texturizada y arte latte.',
                ],
                [
                    'name' => 'Croissant de Almendras',
                    'category_id' => $foodCategory->id,
                    'price' => '7200.00',
                    'estimated_duration_minutes' => 5,
                    'is_active' => true,
                    'description' => 'Masa hojaldrada con mantequilla y relleno de crema de almendras tostadas.',
                ],
                [
                    'name' => 'Pan de Masa Madre',
                    'category_id' => $foodCategory->id,
                    'price' => '12000.00',
                    'estimated_duration_minutes' => null,
                    'is_active' => true,
                    'description' => 'Hogaza artesanal de 500g elaborada con fermentación lenta de 24 horas.',
                ],
                [
                    'name' => 'Combo Desayuno Especial',
                    'category_id' => $foodCategory->id,
                    'price' => '18500.00',
                    'estimated_duration_minutes' => 20,
                    'is_active' => true,
                    'description' => 'Huevos campesinos, pan caliente, café de la casa y jugo de naranja natural.',
                ],
                [
                    'name' => 'Muestra de Café Filtrado',
                    'category_id' => $foodCategory->id,
                    'price' => '0.00',
                    'estimated_duration_minutes' => 5,
                    'is_active' => true,
                    'description' => 'Degustación de café filtrado para clientes en sala de espera.',
                ],
                [
                    'name' => 'Pastel de Zanahoria (Temporada)',
                    'category_id' => $foodCategory->id,
                    'price' => '8000.00',
                    'estimated_duration_minutes' => null,
                    'is_active' => false,
                    'description' => 'Porción individual con nueces y glaseado suave de queso crema.',
                ],
            ];

            foreach ($bakeryOfferings as $offeringData) {
                Offering::updateOrCreate(
                    [
                        'business_id' => $panaderia->id,
                        'name' => $offeringData['name'],
                    ],
                    $offeringData
                );
            }
        }

        $barberia = Business::where('email', 'barberia@turnex.test')->first();
        if ($barberia !== null) {
            $barberOfferings = [
                [
                    'name' => 'Corte Clásico de Cabello',
                    'category_id' => $beautyCategory->id,
                    'price' => '25000.00',
                    'estimated_duration_minutes' => 35,
                    'is_active' => true,
                    'description' => 'Corte tradicional a tijera o máquina, incluye lavado y peinado con cera.',
                ],
                [
                    'name' => 'Perfilado de Barba',
                    'category_id' => $beautyCategory->id,
                    'price' => '18000.00',
                    'estimated_duration_minutes' => 25,
                    'is_active' => true,
                    'description' => 'Delineado a navaja, toalla caliente y aplicación de bálsamo hidratante.',
                ],
                [
                    'name' => 'Combo Corte + Barba Capital',
                    'category_id' => $beautyCategory->id,
                    'price' => '38000.00',
                    'estimated_duration_minutes' => 55,
                    'is_active' => true,
                    'description' => 'Experiencia completa con corte de cabello, toalla caliente y tratamiento de barba.',
                ],
                [
                    'name' => 'Diagnóstico Capilar y Estilo',
                    'category_id' => $beautyCategory->id,
                    'price' => '0.00',
                    'estimated_duration_minutes' => 15,
                    'is_active' => true,
                    'description' => 'Evaluación personalizada previa al corte para elegir el mejor estilo.',
                ],
                [
                    'name' => 'Camuflaje de Canas',
                    'category_id' => $beautyCategory->id,
                    'price' => '32000.00',
                    'estimated_duration_minutes' => 30,
                    'is_active' => false,
                    'description' => 'Matización sutil y rápida de canas para un look fresco y natural.',
                ],
            ];

            foreach ($barberOfferings as $offeringData) {
                Offering::updateOrCreate(
                    [
                        'business_id' => $barberia->id,
                        'name' => $offeringData['name'],
                    ],
                    $offeringData
                );
            }
        }

        $otherBusinesses = Business::whereNotIn('email', [
            'contacto@panaderia.test',
            'barberia@turnex.test',
        ])->get();

        $categories = Category::all();

        foreach ($otherBusinesses as $otherBusiness) {
            if ($otherBusiness->offerings()->count() === 0 && $categories->isNotEmpty()) {
                Offering::factory()->count(3)->create([
                    'business_id' => $otherBusiness->id,
                    'category_id' => $categories->random()->id,
                ]);
            }
        }
    }
}

