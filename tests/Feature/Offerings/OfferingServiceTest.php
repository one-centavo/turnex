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

    $service = new OfferingService;

    // Filter active
    $activeResults = $service->listForBusiness($business, ['status' => 'active']);
    expect($activeResults->pluck('id')->all())->toBe([$activeOffering->id]);

    // Filter inactive
    $inactiveResults = $service->listForBusiness($business, ['status' => 'inactive']);
    expect($inactiveResults->pluck('id')->all())->toBe([$inactiveOffering->id]);

    // Filter all or unspecified returns both
    $allResults = $service->listForBusiness($business, ['status' => 'all']);
    expect($allResults->total())->toBe(2);

    $defaultResults = $service->listForBusiness($business, []);
    expect($defaultResults->total())->toBe(2);
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

test('offering service updates basic fields with sanitization and normalization', function (): void {
    $business = Business::factory()->create();
    $category1 = Category::factory()->create();
    $category2 = Category::factory()->create();
    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category1->id,
        'name' => 'Original Name',
        'description' => 'Original description',
        'price' => '20.00',
        'estimated_duration_minutes' => 30,
        'is_active' => true,
    ]);

    $service = new OfferingService;

    $updated = $service->update($offering, [
        'category_id' => $category2->id,
        'name' => '   Corte   de   Cabello   Moderno   ',
        'description' => "   Nuevo   diseño   \n   y   estilo.   ",
        'price' => '45000.50',
        'estimated_duration_minutes' => '60',
        'is_active' => false,
    ]);

    expect($updated->category_id)->toBe($category2->id)
        ->and($updated->name)->toBe('Corte de Cabello Moderno')
        ->and($updated->description)->toBe('Nuevo diseño y estilo.')
        ->and($updated->price)->toBe('45000.50')
        ->and($updated->estimated_duration_minutes)->toBe(60)
        ->and($updated->is_active)->toBeFalse();

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'business_id' => $business->id,
        'category_id' => $category2->id,
        'name' => 'Corte de Cabello Moderno',
        'description' => 'Nuevo diseño y estilo.',
        'price' => 45000.50,
        'estimated_duration_minutes' => 60,
        'is_active' => false,
    ]);
});

test('offering service defaults price to 0.00 and duration to null when cleared', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'price' => '50.00',
        'estimated_duration_minutes' => 45,
        'description' => 'Some description',
    ]);

    $service = new OfferingService;

    $updated = $service->update($offering, [
        'price' => '',
        'estimated_duration_minutes' => '',
        'description' => '   ',
    ]);

    expect($updated->price)->toBe('0.00')
        ->and($updated->estimated_duration_minutes)->toBeNull()
        ->and($updated->description)->toBeNull();

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'price' => 0.00,
        'estimated_duration_minutes' => null,
        'description' => null,
    ]);
});

test('offering service retains business_id and ignores external tenant reassignments', function (): void {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $category = Category::factory()->create();
    $offering = Offering::factory()->create([
        'business_id' => $businessA->id,
        'category_id' => $category->id,
        'name' => 'Original Item',
    ]);

    $service = new OfferingService;

    $updated = $service->update($offering, [
        'name' => 'Updated Item',
        'business_id' => $businessB->id,
    ]);

    expect($updated->business_id)->toBe($businessA->id)
        ->and($updated->business_id)->not->toBe($businessB->id);

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'business_id' => $businessA->id,
        'name' => 'Updated Item',
    ]);
});

test('offering service retains existing image when no upload provided and removeImage is false', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('existing.png', 50, 'image/png');
    $initialPath = $service->storeImage($file);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'reference_image' => $initialPath,
    ]);

    $updated = $service->update($offering, [
        'name' => 'Updated Name',
    ]);

    expect($updated->reference_image)->toBe($initialPath);
    Storage::disk('public')->assertExists($initialPath);
});

test('offering service replaces existing image, stores new file, and deletes old file upon success', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $oldFile = UploadedFile::fake()->create('old.png', 40, 'image/png');
    $oldPath = $service->storeImage($oldFile);
    Storage::disk('public')->assertExists($oldPath);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'reference_image' => $oldPath,
    ]);

    $newFile = UploadedFile::fake()->create('new.jpg', 60, 'image/jpeg');
    $updated = $service->update($offering, [
        'name' => 'Replaced Image Item',
    ], image: $newFile);

    expect($updated->reference_image)->not->toBeNull()
        ->and($updated->reference_image)->not->toBe($oldPath)
        ->and($updated->reference_image)->toStartWith('offerings/');

    Storage::disk('public')->assertExists((string) $updated->reference_image);
    Storage::disk('public')->assertMissing($oldPath);
});

test('offering service explicitly removes image and deletes file from disk when removeImage is true', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('to_delete.png', 40, 'image/png');
    $path = $service->storeImage($file);
    Storage::disk('public')->assertExists($path);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'reference_image' => $path,
    ]);

    $updated = $service->update($offering, [
        'name' => 'Item Without Image',
    ], removeImage: true);

    expect($updated->reference_image)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('offering service cleans up newly uploaded image when update transaction fails and preserves old image', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $oldFile = UploadedFile::fake()->create('keeper.png', 40, 'image/png');
    $oldPath = $service->storeImage($oldFile);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'reference_image' => $oldPath,
    ]);

    $newFile = UploadedFile::fake()->create('attempt.jpg', 60, 'image/jpeg');

    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new RuntimeException('Simulated database error during update'));

    expect(fn () => $service->update($offering, [
        'name' => 'Will Fail',
    ], image: $newFile))->toThrow(RuntimeException::class, 'Simulated database error during update');

    // Verify old image is intact and newly uploaded file was deleted
    Storage::disk('public')->assertExists($oldPath);
    $allFiles = Storage::disk('public')->allFiles('offerings');
    expect($allFiles)->toBe([$oldPath]);
});

test('offering service allows updating offerings associated with soft-deleted categories', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create(['name' => 'Archived Haircuts']);
    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Vintage Cut',
    ]);

    $category->delete();
    expect($category->fresh()->trashed())->toBeTrue();

    $service = new OfferingService;
    $updated = $service->update($offering, [
        'name' => 'Vintage Cut Pro',
        'price' => '30.00',
    ]);

    expect($updated->name)->toBe('Vintage Cut Pro')
        ->and($updated->category_id)->toBe($category->id)
        ->and($updated->category)->not->toBeNull()
        ->and($updated->category->trashed())->toBeTrue();
});

test('offering service prioritizes uploaded file over removeImage flag and deletes old file upon success', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $oldFile = UploadedFile::fake()->create('old_to_replace.png', 40, 'image/png');
    $oldPath = $service->storeImage($oldFile);
    Storage::disk('public')->assertExists($oldPath);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'reference_image' => $oldPath,
    ]);

    $newFile = UploadedFile::fake()->create('priority_new.jpg', 60, 'image/jpeg');
    $updated = $service->update($offering, [
        'name' => 'Priority Upload Item',
    ], image: $newFile, removeImage: true);

    expect($updated->reference_image)->not->toBeNull()
        ->and($updated->reference_image)->not->toBe($oldPath)
        ->and($updated->reference_image)->toStartWith('offerings/');

    Storage::disk('public')->assertExists((string) $updated->reference_image);
    Storage::disk('public')->assertMissing($oldPath);
});

test('offering service handles removeImage true gracefully when reference_image is already null', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $service = new OfferingService;

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'reference_image' => null,
    ]);

    $updated = $service->update($offering, [
        'name' => 'Item Already Null Image',
    ], removeImage: true);

    expect($updated->reference_image)->toBeNull();

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'reference_image' => null,
        'name' => 'Item Already Null Image',
    ]);
});

test('offering service defaults whitespace-only duration to null on update', function (): void {
    $business = Business::factory()->create();
    $category = Category::factory()->create();
    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'estimated_duration_minutes' => 45,
    ]);

    $service = new OfferingService;
    $updated = $service->update($offering, [
        'estimated_duration_minutes' => '   ',
    ]);

    expect($updated->estimated_duration_minutes)->toBeNull();

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'estimated_duration_minutes' => null,
    ]);
});
