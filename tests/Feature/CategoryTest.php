<?php

declare(strict_types=1);

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\UniqueConstraintViolationException;

test('category can be created with valid attributes and defaults is_active to true', function (): void {
    $category = Category::create([
        'name' => 'Tecnología e Informática',
        'description' => 'Servicios de soporte técnico y venta de equipos',
    ]);

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->exists)->toBeTrue()
        ->and($category->name)->toBe('Tecnología e Informática')
        ->and($category->description)->toBe('Servicios de soporte técnico y venta de equipos')
        ->and($category->is_active)->toBeTrue()
        ->and($category->deleted_at)->toBeNull();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Tecnología e Informática',
        'description' => 'Servicios de soporte técnico y venta de equipos',
        'is_active' => true,
    ]);
});

test('category allows nullable description', function (): void {
    $category = Category::create([
        'name' => 'Consultoría Express',
    ]);

    expect($category->description)->toBeNull()
        ->and($category->is_active)->toBeTrue();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Consultoría Express',
        'description' => null,
        'is_active' => true,
    ]);
});

test('category enforces unique constraint on name', function (): void {
    Category::factory()->create(['name' => 'Gastronomía']);

    expect(fn () => Category::factory()->create(['name' => 'Gastronomía']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('category supports soft deletes and restoration', function (): void {
    $category = Category::factory()->create();

    $category->delete();

    expect($category->trashed())->toBeTrue()
        ->and($category->deleted_at)->not->toBeNull()
        ->and(Category::query()->where('id', $category->id)->exists())->toBeFalse()
        ->and(Category::all()->pluck('id'))->not->toContain($category->id)
        ->and(Category::withTrashed()->where('id', $category->id)->exists())->toBeTrue()
        ->and(Category::onlyTrashed()->where('id', $category->id)->exists())->toBeTrue();

    $category->restore();

    expect($category->trashed())->toBeFalse()
        ->and($category->deleted_at)->toBeNull()
        ->and(Category::query()->where('id', $category->id)->exists())->toBeTrue();
});

test('active scope retrieves only categories where is_active is true', function (): void {
    $activeCategory = Category::factory()->active()->create();
    $inactiveCategory = Category::factory()->inactive()->create();

    $activeCategories = Category::active()->get();

    expect($activeCategories->pluck('id'))->toContain($activeCategory->id)
        ->and($activeCategories->pluck('id'))->not->toContain($inactiveCategory->id);
});

test('category factory states work as expected', function (): void {
    $active = Category::factory()->active()->create();
    $inactive = Category::factory()->inactive()->create();
    $trashed = Category::factory()->trashed()->create();

    expect($active->is_active)->toBeTrue()
        ->and($active->trashed())->toBeFalse()
        ->and($inactive->is_active)->toBeFalse()
        ->and($inactive->trashed())->toBeFalse()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->deleted_at)->not->toBeNull();
});

test('category seeder seeds standard categories and is idempotent', function (): void {
    $this->seed(CategorySeeder::class);

    $expectedCategories = [
        'Alimentos y Bebidas',
        'Belleza y Cuidado Personal',
        'Salud y Bienestar',
        'Servicios Profesionales',
        'Comercio Minorista',
        'Trámites y Atención al Cliente',
    ];

    expect(Category::count())->toBe(count($expectedCategories));

    foreach ($expectedCategories as $name) {
        $this->assertDatabaseHas('categories', [
            'name' => $name,
            'is_active' => true,
        ]);
    }

    // Run the seeder a second time to verify idempotency without duplicate key errors or duplicate rows
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(count($expectedCategories));
});
