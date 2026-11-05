<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Services\OfferingService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

test('listForBusiness strictly enforces multi-tenant isolation', function (): void {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $category = Category::factory()->create();

    $offeringA1 = Offering::factory()->create(['business_id' => $businessA->id, 'category_id' => $category->id, 'name' => 'Service A1']);
    $offeringA2 = Offering::factory()->create(['business_id' => $businessA->id, 'category_id' => $category->id, 'name' => 'Service A2']);
    $offeringB1 = Offering::factory()->create(['business_id' => $businessB->id, 'category_id' => $category->id, 'name' => 'Service B1']);

    $service = new OfferingService;
    $resultsA = $service->listForBusiness($businessA);

    expect($resultsA->total())->toBe(2)
        ->and($resultsA->pluck('id')->all())->toContain($offeringA1->id, $offeringA2->id)
        ->and($resultsA->pluck('id')->all())->not->toContain($offeringB1->id);

    $resultsB = $service->listForBusiness($businessB);
    expect($resultsB->total())->toBe(1)
        ->and($resultsB->pluck('id')->all())->toBe([$offeringB1->id]);
});

test('listForBusiness filters offerings by name with case-insensitivity, whitespace trimming, and wildcard escaping', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    $corte = Offering::factory()->create(['business_id' => $business->id, 'category_id' => $category->id, 'name' => 'Corte de Cabello Clásico']);
    $barba = Offering::factory()->create(['business_id' => $business->id, 'category_id' => $category->id, 'name' => 'Arreglo de Barba']);
    $tintura = Offering::factory()->create(['business_id' => $business->id, 'category_id' => $category->id, 'name' => 'Tintura 100% Pura']);

    $service = new OfferingService;

    // Partial lowercase search
    $results = $service->listForBusiness($business, ['search' => 'corte']);
    expect($results->pluck('id')->all())->toBe([$corte->id]);

    // Partial uppercase search
    $results = $service->listForBusiness($business, ['search' => 'BARBA']);
    expect($results->pluck('id')->all())->toBe([$barba->id]);

    // Trimmed and squished whitespace
    $results = $service->listForBusiness($business, ['search' => '   corte   de   cabello   ']);
    expect($results->pluck('id')->all())->toBe([$corte->id]);

    // Escaped SQL wildcard character %
    $results = $service->listForBusiness($business, ['search' => '100%']);
    expect($results->pluck('id')->all())->toBe([$tintura->id]);

    // Blank or whitespace-only search returns all items
    $results = $service->listForBusiness($business, ['search' => '   ']);
    expect($results->total())->toBe(3);
});

test('listForBusiness filters offerings by category', function (): void {
    $business = Business::factory()->create();
    $categoryA = Category::factory()->create(['name' => 'Category A']);
    $categoryB = Category::factory()->create(['name' => 'Category B']);

    $offeringA = Offering::factory()->create(['business_id' => $business->id, 'category_id' => $categoryA->id, 'name' => 'Item A']);
    $offeringB = Offering::factory()->create(['business_id' => $business->id, 'category_id' => $categoryB->id, 'name' => 'Item B']);

    $service = new OfferingService;

    // Filter using category_id key
    $resultsA = $service->listForBusiness($business, ['category_id' => $categoryA->id]);
    expect($resultsA->pluck('id')->all())->toBe([$offeringA->id]);

    // Filter using category key
    $resultsB = $service->listForBusiness($business, ['category' => $categoryB->id]);
    expect($resultsB->pluck('id')->all())->toBe([$offeringB->id]);

    // Filter with 'all' or empty string ignores category filter
    $resultsAll = $service->listForBusiness($business, ['category' => 'all']);
    expect($resultsAll->total())->toBe(2);

    $resultsEmpty = $service->listForBusiness($business, ['category' => '']);
    expect($resultsEmpty->total())->toBe(2);
});

test('listForBusiness filters offerings by status', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    $activeOffering = Offering::factory()->active()->create(['business_id' => $business->id, 'category_id' => $category->id, 'name' => 'Active Item']);
    $inactiveOffering = Offering::factory()->inactive()->create(['business_id' => $business->id, 'category_id' => $category->id, 'name' => 'Inactive Item']);
    $archivedOffering = Offering::factory()->trashed()->create(['business_id' => $business->id, 'category_id' => $category->id, 'name' => 'Archived Item']);

    $service = new OfferingService;

    // Filter active
    $activeResults = $service->listForBusiness($business, ['status' => 'active']);
    expect($activeResults->pluck('id')->all())->toBe([$activeOffering->id]);

    // Filter inactive
    $inactiveResults = $service->listForBusiness($business, ['status' => 'inactive']);
    expect($inactiveResults->pluck('id')->all())->toBe([$inactiveOffering->id]);

    // Filter archived returns exclusively soft-deleted offerings
    $archivedResults = $service->listForBusiness($business, ['status' => 'archived']);
    expect($archivedResults->pluck('id')->all())->toBe([$archivedOffering->id]);

    // Filter all or unspecified returns both active and inactive, excluding archived
    $allResults = $service->listForBusiness($business, ['status' => 'all']);
    expect($allResults->total())->toBe(2)
        ->and($allResults->pluck('id')->all())->toContain($activeOffering->id, $inactiveOffering->id)
        ->and($allResults->pluck('id')->all())->not->toContain($archivedOffering->id);

    $defaultResults = $service->listForBusiness($business, []);
    expect($defaultResults->total())->toBe(2)
        ->and($defaultResults->pluck('id')->all())->toContain($activeOffering->id, $inactiveOffering->id)
        ->and($defaultResults->pluck('id')->all())->not->toContain($archivedOffering->id);
});

test('listForBusiness dynamically sorts by name and creation date with direction and safe fallbacks', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    $item1 = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Corte Alpha',
        'created_at' => now()->subHours(3),
    ]);
    $item2 = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Corte Beta',
        'created_at' => now()->subHours(1),
    ]);
    $item3 = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Corte Gamma',
        'created_at' => now()->subHours(2),
    ]);

    $service = new OfferingService;

    // Sort by name asc
    $nameAsc = $service->listForBusiness($business, sortField: 'name', sortDirection: 'asc');
    expect($nameAsc->pluck('id')->all())->toBe([$item1->id, $item2->id, $item3->id]);

    // Sort by name desc
    $nameDesc = $service->listForBusiness($business, sortField: 'name', sortDirection: 'desc');
    expect($nameDesc->pluck('id')->all())->toBe([$item3->id, $item2->id, $item1->id]);

    // Sort by created_at asc
    $createdAsc = $service->listForBusiness($business, sortField: 'created_at', sortDirection: 'asc');
    expect($createdAsc->pluck('id')->all())->toBe([$item1->id, $item3->id, $item2->id]);

    // Sort by created_at desc (default)
    $createdDesc = $service->listForBusiness($business, sortField: 'created_at', sortDirection: 'desc');
    expect($createdDesc->pluck('id')->all())->toBe([$item2->id, $item3->id, $item1->id]);

    // Invalid column and direction fall back safely to created_at desc
    $fallback = $service->listForBusiness($business, sortField: 'invalid_col', sortDirection: 'invalid_dir');
    expect($fallback->pluck('id')->all())->toBe([$item2->id, $item3->id, $item1->id]);
});

test('listForBusiness paginates offerings with customizable perPage', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    Offering::factory()->count(15)->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
    ]);

    $service = new OfferingService;
    $paginator = $service->listForBusiness($business, perPage: 5);

    expect($paginator->total())->toBe(15)
        ->and($paginator->perPage())->toBe(5)
        ->and($paginator->lastPage())->toBe(3)
        ->and($paginator->count())->toBe(5);
});

test('listForBusiness eagerly loads categories including soft-deleted categories', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create(['name' => 'Barbería Tradicional']);
    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Corte Clásico',
    ]);

    // Soft delete category
    $category->delete();
    expect($category->fresh()->trashed())->toBeTrue();

    $service = new OfferingService;
    $results = $service->listForBusiness($business);

    expect($results->total())->toBe(1);

    /** @var Offering $loadedOffering */
    $loadedOffering = $results->first();

    // Verify relation is eagerly loaded (preventing N+1 queries)
    expect($loadedOffering->relationLoaded('category'))->toBeTrue()
        ->and($loadedOffering->category)->not->toBeNull()
        ->and($loadedOffering->category->id)->toBe($category->id)
        ->and($loadedOffering->category->name)->toBe('Barbería Tradicional')
        ->and($loadedOffering->category->trashed())->toBeTrue();

    // Verify Offering model category() relation direct load with withTrashed
    $freshOffering = Offering::query()->find($offering->id);
    expect($freshOffering->category)->not->toBeNull()
        ->and($freshOffering->category->id)->toBe($category->id)
        ->and($freshOffering->category->trashed())->toBeTrue();
});

test('archive soft-deletes offering, sets is_active to false, and retains image on disk', function (): void {
    $business = Business::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('service-img.jpg', 40, 'image/jpeg');
    $imagePath = $service->storeImage($file);

    $offering = Offering::factory()->active()->create([
        'business_id' => $business->id,
        'reference_image' => $imagePath,
    ]);

    $result = $service->archive($offering);

    expect($result)->toBeTrue();

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->trashed())->toBeTrue()
        ->and($freshOffering->is_active)->toBeFalse();

    Storage::disk('public')->assertExists($imagePath);
});

test('archive operates cleanly on already inactive offerings', function (): void {
    $offering = Offering::factory()->inactive()->create();
    $service = new OfferingService;

    $result = $service->archive($offering);

    expect($result)->toBeTrue();

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->trashed())->toBeTrue()
        ->and($freshOffering->is_active)->toBeFalse();
});

test('restore clears deleted_at and retains all original attributes intact', function (): void {
    $offering = Offering::factory()->trashed()->create([
        'price' => '89.99',
        'description' => 'Original description for restore',
        'is_active' => false,
    ]);

    $service = new OfferingService;

    $result = $service->restore($offering);

    expect($result)->toBeTrue();

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->trashed())->toBeFalse()
        ->and($freshOffering->deleted_at)->toBeNull()
        ->and($freshOffering->price)->toBe('89.99')
        ->and($freshOffering->description)->toBe('Original description for restore')
        ->and($freshOffering->is_active)->toBeFalse();
});

test('restore returns false if offering is not soft-deleted', function (): void {
    $offering = Offering::factory()->active()->create();
    $service = new OfferingService;

    $result = $service->restore($offering);

    expect($result)->toBeFalse();
});

test('hasHistoricalActivity returns true when dynamic safeguard flag is present', function (): void {
    $offering = Offering::factory()->create();
    $service = new OfferingService;

    expect($service->hasHistoricalActivity($offering))->toBeFalse();

    $offering->has_historical_activity = true;
    expect($service->hasHistoricalActivity($offering))->toBeTrue();
});

test('hasHistoricalActivity detects references in historical database tables', function (): void {
    $offering = Offering::factory()->create();
    $service = new OfferingService;

    Schema::create('ticket_offerings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('offering_id');
    });

    try {
        expect($service->hasHistoricalActivity($offering))->toBeFalse();

        DB::table('ticket_offerings')->insert(['offering_id' => $offering->id]);

        expect($service->hasHistoricalActivity($offering))->toBeTrue();
    } finally {
        Schema::dropIfExists('ticket_offerings');
    }
});

test('deletePermanently throws DomainException and retains record and image when historical activity exists', function (): void {
    $service = new OfferingService;
    $file = UploadedFile::fake()->create('guarded.jpg', 30, 'image/jpeg');
    $imagePath = $service->storeImage($file);

    $offering = Offering::factory()->trashed()->create([
        'reference_image' => $imagePath,
    ]);

    $offering->has_historical_activity = true;

    expect(fn () => $service->deletePermanently($offering))
        ->toThrow(DomainException::class, 'This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.');

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
    ]);

    Storage::disk('public')->assertExists($imagePath);
});

test('deletePermanently forceDeletes database record and purges image file from storage disk', function (): void {
    $service = new OfferingService;
    $file = UploadedFile::fake()->create('delete-me.jpg', 25, 'image/jpeg');
    $imagePath = $service->storeImage($file);

    $offering = Offering::factory()->trashed()->create([
        'reference_image' => $imagePath,
    ]);
    $offeringId = $offering->id;

    $result = $service->deletePermanently($offering);

    expect($result)->toBeTrue();

    $this->assertDatabaseMissing('offerings', [
        'id' => $offeringId,
    ]);

    Storage::disk('public')->assertMissing($imagePath);
});

test('deletePermanently forceDeletes database record when reference image is null', function (): void {
    $service = new OfferingService;
    $offering = Offering::factory()->trashed()->create([
        'reference_image' => null,
    ]);
    $offeringId = $offering->id;

    $result = $service->deletePermanently($offering);

    expect($result)->toBeTrue();

    $this->assertDatabaseMissing('offerings', [
        'id' => $offeringId,
    ]);
});
