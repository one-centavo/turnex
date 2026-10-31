<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Services\OfferingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('offering service creates offering strictly scoped to business tenant', function (): void {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $category = Category::factory()->create();

    $service = new OfferingService;

    // Even if client attempts to pass another business_id, it must be ignored
    $offering = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Barbería Premium',
        'business_id' => $otherBusiness->id,
    ]);

    expect($offering)->toBeInstanceOf(Offering::class)
        ->and($offering->business_id)->toBe($business->id)
        ->and($offering->business_id)->not->toBe($otherBusiness->id);

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'business_id' => $business->id,
        'name' => 'Barbería Premium',
    ]);
});

test('offering service normalizes name and collapses whitespace per US-CORE-02', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $offering = $service->create($business, [
        'category_id' => $category->id,
        'name' => '   Corte   de    Cabello   y   Barba   ',
    ]);

    expect($offering->name)->toBe('Corte de Cabello y Barba');
});

test('offering service sanitizes description and converts whitespace-only to null', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    // Whitespace only description should be treated as null
    $offeringWithWhitespaceOnly = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio A',
        'description' => '     ',
    ]);

    expect($offeringWithWhitespaceOnly->description)->toBeNull();

    // Valid description with extra spaces should be trimmed and collapsed
    $offeringWithDescription = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio B',
        'description' => "   Incluye   lavado   \n   y   peinado.   ",
    ]);

    expect($offeringWithDescription->description)->toBe('Incluye lavado y peinado.');

    // Omitted description should be null
    $offeringWithoutDescription = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio C',
    ]);

    expect($offeringWithoutDescription->description)->toBeNull();
});

test('offering service handles default values for price and is_active', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    // Omitted price and is_active
    $offering = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio Gratuito',
    ]);

    expect($offering->price)->toBe('0.00')
        ->and($offering->is_active)->toBeTrue()
        ->and($offering->estimated_duration_minutes)->toBeNull();

    // Explicit empty string for price
    $offeringWithEmptyPrice = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio Con Precio Vacio',
        'price' => '',
    ]);

    expect($offeringWithEmptyPrice->price)->toBe('0.00');

    // Explicit custom price, duration, and inactive status
    $customOffering = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio Especial',
        'price' => '35000.50',
        'estimated_duration_minutes' => '45',
        'is_active' => false,
    ]);

    expect($customOffering->price)->toBe('35000.50')
        ->and($customOffering->estimated_duration_minutes)->toBe(45)
        ->and($customOffering->is_active)->toBeFalse();
});

test('offering service securely stores uploaded image on public disk with non-colliding filename', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('haircut.jpg', 100, 'image/jpeg');

    $offering = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Corte Moderno',
    ], $file);

    expect($offering->reference_image)->not->toBeNull()
        ->and($offering->reference_image)->toStartWith('offerings/')
        ->and($offering->reference_image)->not->toBe('offerings/haircut.jpg');

    Storage::disk('public')->assertExists((string) $offering->reference_image);
});

test('offering service accepts uploaded image in data array', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('beard_trim.png', 50, 'image/png');

    $offering = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Arreglo de Barba',
        'reference_image' => $file,
    ]);

    expect($offering->reference_image)->not->toBeNull()
        ->and($offering->reference_image)->toStartWith('offerings/');

    Storage::disk('public')->assertExists((string) $offering->reference_image);
});

test('offering service accepts pre-stored string path for reference image', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $offering = $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio con Ruta Preexistente',
        'reference_image' => 'offerings/existing-sample.png',
    ]);

    expect($offering->reference_image)->toBe('offerings/existing-sample.png');
});

test('offering service cleans up stored image if database transaction fails', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('failing.jpg', 20, 'image/jpeg');

    // Mock DB transaction to simulate database failure
    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new RuntimeException('Database failure simulation'));

    expect(fn () => $service->create($business, [
        'category_id' => $category->id,
        'name' => 'Servicio Fallido',
    ], $file))->toThrow(RuntimeException::class, 'Database failure simulation');

    // Confirm that any file stored under offerings/ was cleaned up
    expect(Storage::disk('public')->allFiles('offerings'))->toBeEmpty();
});

test('offering service storeImage and deleteImage helper methods work as expected', function (): void {
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('sample.jpg', 30, 'image/jpeg');
    $path = $service->storeImage($file);

    expect($path)->toStartWith('offerings/');
    Storage::disk('public')->assertExists($path);

    $deleted = $service->deleteImage($path);
    expect($deleted)->toBeTrue();
    Storage::disk('public')->assertMissing($path);

    // Deleting non-existent or null path returns false
    expect($service->deleteImage(null))->toBeFalse()
        ->and($service->deleteImage(''))->toBeFalse()
        ->and($service->deleteImage('non-existent.jpg'))->toBeFalse();
});
