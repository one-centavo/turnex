<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Alimentos y Bebidas',
                'description' => 'Restaurantes, cafeterías, reposterías, bares y preparación de comidas y bebidas.',
                'is_active' => true,
            ],
            [
                'name' => 'Belleza y Cuidado Personal',
                'description' => 'Barberías, peluquerías, salones de belleza, estéticas y spas.',
                'is_active' => true,
            ],
            [
                'name' => 'Salud y Bienestar',
                'description' => 'Consultorios médicos, odontología, farmacias, fisioterapia y laboratorios clínicos.',
                'is_active' => true,
            ],
            [
                'name' => 'Servicios Profesionales',
                'description' => 'Consultoría legal, asesoría contable, notarías, servicios técnicos y oficinas.',
                'is_active' => true,
            ],
            [
                'name' => 'Comercio Minorista',
                'description' => 'Tiendas de conveniencia, papelerías, ropa, calzado, tecnología y minimercados.',
                'is_active' => true,
            ],
            [
                'name' => 'Trámites y Atención al Cliente',
                'description' => 'Puntos de atención, ventanillas de servicio al cliente, recaudos y despachos.',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
