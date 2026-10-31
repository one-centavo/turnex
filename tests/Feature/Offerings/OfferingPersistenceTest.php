<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('offerings table has expected schema columns', function (): void {
    expect(Schema::hasTable('offerings'))->toBeTrue()
        ->and(Schema::hasColumns('offerings', [
            'id',
            'business_id',
            'category_id',
            'name',
            'reference_image',
            'description',
            'price',
            'estimated_duration_minutes',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('offering can be created with valid attributes and default values', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    $offering = Offering::create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Corte de Cabello Clásico',
    ]);

    expect($offering)->toBeInstanceOf(Offering::class)
        ->and($offering->exists)->toBeTrue()
        ->and($offering->name)->toBe('Corte de Cabello Clásico')
        ->and($offering->business_id)->toBe($business->id)
        ->and($offering->category_id)->toBe($category->id)
        ->and($offering->price)->toBe('0.00')
        ->and($offering->is_active)->toBeTrue()
        ->and($offering->reference_image)->toBeNull()
        ->and($offering->description)->toBeNull()
        ->and($offering->estimated_duration_minutes)->toBeNull();

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Corte de Cabello Clásico',
        'price' => '0.00',
        'is_active' => true,
    ]);
});

test('offering casts attributes properly', function (): void {
    $offering = Offering::factory()->create([
        'price' => 49.99,
        'estimated_duration_minutes' => '45',
        'is_active' => 1,
    ]);

    $offering->refresh();

    expect($offering->price)->toBe('49.99')
        ->and($offering->estimated_duration_minutes)->toBe(45)
        ->and($offering->is_active)->toBeTrue();
});

test('offering belongs to business and category', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
    ]);

    expect($offering->business)->toBeInstanceOf(Business::class)
        ->and($offering->business->id)->toBe($business->id)
        ->and($offering->category)->toBeInstanceOf(Category::class)
        ->and($offering->category->id)->toBe($category->id);
});

test('business has many offerings relationship', function (): void {
    $business = Business::factory()->create();
    $offering1 = Offering::factory()->create(['business_id' => $business->id]);
    $offering2 = Offering::factory()->create(['business_id' => $business->id]);

    $businessOfferings = $business->offerings;

    expect($businessOfferings)->toHaveCount(2)
        ->and($businessOfferings->pluck('id'))->toContain($offering1->id, $offering2->id);
});

test('category has many offerings relationship', function (): void {
    $category = Category::factory()->create();
    $offering1 = Offering::factory()->create(['category_id' => $category->id]);
    $offering2 = Offering::factory()->create(['category_id' => $category->id]);

    $categoryOfferings = $category->offerings;

    expect($categoryOfferings)->toHaveCount(2)
        ->and($categoryOfferings->pluck('id'))->toContain($offering1->id, $offering2->id);
});

test('deleting business cascades and deletes associated offerings', function (): void {
    $business = Business::factory()->create();
    $offering = Offering::factory()->create(['business_id' => $business->id]);

    $this->assertDatabaseHas('offerings', ['id' => $offering->id]);

    $business->delete();

    $this->assertDatabaseMissing('offerings', ['id' => $offering->id]);
});

test('deleting category with offerings is restricted by database constraint', function (): void {
    $category = Category::factory()->create();
    $offering = Offering::factory()->create(['category_id' => $category->id]);

    $this->assertDatabaseHas('offerings', ['id' => $offering->id]);

    expect(fn () => $category->forceDelete())->toThrow(QueryException::class);
});

test('active scope returns only active offerings', function (): void {
    $activeOffering = Offering::factory()->active()->create();
    $inactiveOffering = Offering::factory()->inactive()->create();

    $activeOfferings = Offering::active()->get();

    expect($activeOfferings->pluck('id'))->toContain($activeOffering->id)
        ->and($activeOfferings->pluck('id'))->not->toContain($inactiveOffering->id);
});

test('offering factory states work as expected', function (): void {
    $active = Offering::factory()->active()->create();
    $inactive = Offering::factory()->inactive()->create();
    $free = Offering::factory()->free()->create();

    expect($active->is_active)->toBeTrue()
        ->and($inactive->is_active)->toBeFalse()
        ->and($free->price)->toBe('0.00');
});
