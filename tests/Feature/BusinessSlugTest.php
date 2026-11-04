<?php

declare(strict_types=1);

use App\Models\Business;

test('business automatically generates slug on creation when empty', function (): void {
    $business = Business::factory()->create([
        'business_name' => 'Café San Alberto',
        'slug' => null,
    ]);

    expect($business->slug)->toBe('cafe-san-alberto');
    $this->assertDatabaseHas('businesses', [
        'id' => $business->id,
        'slug' => 'cafe-san-alberto',
    ]);
});

test('business resolves slug collisions by appending incremental counter', function (): void {
    $businessA = Business::factory()->create([
        'business_name' => 'Barbería Moderna',
        'slug' => null,
    ]);

    $businessB = Business::factory()->create([
        'business_name' => 'Barbería Moderna',
        'slug' => null,
    ]);

    $businessC = Business::factory()->create([
        'business_name' => 'Barbería Moderna',
        'slug' => null,
    ]);

    expect($businessA->slug)->toBe('barberia-moderna')
        ->and($businessB->slug)->toBe('barberia-moderna-1')
        ->and($businessC->slug)->toBe('barberia-moderna-2');
});

test('business respects explicit slug when provided', function (): void {
    $business = Business::factory()->create([
        'business_name' => 'La Tiendita',
        'slug' => 'mi-tiendita-oficial',
    ]);

    expect($business->slug)->toBe('mi-tiendita-oficial');
});

test('business factory generates non-empty slug by default', function (): void {
    $business = Business::factory()->create();

    expect($business->slug)->toBeString()
        ->and(filled($business->slug))->toBeTrue();
});

test('business generateUniqueSlug ignores current model id', function (): void {
    $business = Business::factory()->create([
        'business_name' => 'Restaurante Central',
        'slug' => 'restaurante-central',
    ]);

    $resolvedSlug = Business::generateUniqueSlug('Restaurante Central', $business->id);

    expect($resolvedSlug)->toBe('restaurante-central');
});
