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

/*
|--------------------------------------------------------------------------
| 1. archive() Edge Cases
|--------------------------------------------------------------------------
*/

test('archive() soft-deletes active offering, sets is_active to false, and retains image on disk', function (): void {
    $business = Business::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('thumbnail.png', 45, 'image/png');
    $imagePath = $service->storeImage($file);

    $offering = Offering::factory()->active()->create([
        'business_id' => $business->id,
        'reference_image' => $imagePath,
        'name' => 'Premium Haircut',
    ]);

    expect($offering->trashed())->toBeFalse()
        ->and($offering->is_active)->toBeTrue();

    $result = $service->archive($offering);

    expect($result)->toBeTrue();

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->trashed())->toBeTrue()
        ->and($freshOffering->deleted_at)->not->toBeNull()
        ->and($freshOffering->is_active)->toBeFalse();

    // Verify image is preserved on public storage
    Storage::disk('public')->assertExists($imagePath);

    // Verify excluded from default queries
    expect(Offering::query()->find($offering->id))->toBeNull()
        ->and(Offering::withTrashed()->find($offering->id))->not->toBeNull()
        ->and(Offering::onlyTrashed()->find($offering->id))->not->toBeNull();
});

test('archive() operates cleanly on an already inactive offering', function (): void {
    $offering = Offering::factory()->inactive()->create([
        'name' => 'Seasonal Promo',
    ]);

    $service = new OfferingService;

    $result = $service->archive($offering);

    expect($result)->toBeTrue();

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->trashed())->toBeTrue()
        ->and($freshOffering->is_active)->toBeFalse();
});

test('repeated calls to archive() on already archived offering remain idempotent and safe', function (): void {
    $offering = Offering::factory()->active()->create();
    $service = new OfferingService;

    $firstArchive = $service->archive($offering);
    expect($firstArchive)->toBeTrue();

    $deletedAtFirst = $offering->fresh()->deleted_at;
    expect($deletedAtFirst)->not->toBeNull();

    // Calling archive again on the already trashed offering
    $secondArchive = $service->archive($offering->fresh());
    expect($secondArchive)->toBeTrue();

    $freshSecond = $offering->fresh();
    expect($freshSecond->trashed())->toBeTrue()
        ->and($freshSecond->is_active)->toBeFalse();
});

test('archiving an offering belonging to business A does not affect business B offerings', function (): void {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $offeringA = Offering::factory()->active()->create(['business_id' => $businessA->id]);
    $offeringB = Offering::factory()->active()->create(['business_id' => $businessB->id]);

    $service = new OfferingService;
    $service->archive($offeringA);

    expect($offeringA->fresh()->trashed())->toBeTrue()
        ->and($offeringB->fresh()->trashed())->toBeFalse()
        ->and($offeringB->fresh()->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 2. restore() Edge Cases
|--------------------------------------------------------------------------
*/

test('restore() restores soft-deleted offering and preserves all original attributes including is_active', function (): void {
    $business = Business::factory()->create();
    $service = new OfferingService;

    $file = UploadedFile::fake()->create('service-photo.jpg', 50, 'image/jpeg');
    $imagePath = $service->storeImage($file);

    $originalAttributes = [
        'business_id' => $business->id,
        'name' => 'Full Beard Trim & Styling',
        'description' => 'Comprehensive beard grooming with hot towel and oil.',
        'price' => '45.50',
        'estimated_duration_minutes' => 35,
        'reference_image' => $imagePath,
        'is_active' => false,
    ];

    $offering = Offering::factory()->trashed()->create($originalAttributes);

    expect($offering->trashed())->toBeTrue()
        ->and($offering->deleted_at)->not->toBeNull();

    $restored = $service->restore($offering);

    expect($restored)->toBeTrue();

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->trashed())->toBeFalse()
        ->and($freshOffering->deleted_at)->toBeNull()
        ->and($freshOffering->name)->toBe($originalAttributes['name'])
        ->and($freshOffering->description)->toBe($originalAttributes['description'])
        ->and($freshOffering->price)->toBe($originalAttributes['price'])
        ->and($freshOffering->estimated_duration_minutes)->toBe($originalAttributes['estimated_duration_minutes'])
        ->and($freshOffering->reference_image)->toBe($originalAttributes['reference_image'])
        ->and($freshOffering->is_active)->toBeFalse();

    // Verify offering is now discoverable via standard query
    expect(Offering::query()->find($offering->id))->not->toBeNull();
    Storage::disk('public')->assertExists($imagePath);
});

test('restore() returns false when offering is not soft-deleted', function (): void {
    $offering = Offering::factory()->active()->create();
    $service = new OfferingService;

    expect($offering->trashed())->toBeFalse();

    $result = $service->restore($offering);

    expect($result)->toBeFalse();

    $freshOffering = $offering->fresh();
    expect($freshOffering->trashed())->toBeFalse()
        ->and($freshOffering->deleted_at)->toBeNull();
});

test('repeated restore() calls: first succeeds, second returns false', function (): void {
    $offering = Offering::factory()->trashed()->create();
    $service = new OfferingService;

    $firstRestore = $service->restore($offering);
    expect($firstRestore)->toBeTrue();

    // Subsequent restore on now-active offering returns false
    $secondRestore = $service->restore($offering->fresh());
    expect($secondRestore)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 3. hasHistoricalActivity() Edge Cases
|--------------------------------------------------------------------------
*/

test('hasHistoricalActivity() evaluates dynamic safeguard flag accurately', function (): void {
    $offering = Offering::factory()->create();
    $service = new OfferingService;

    // Default without flag
    expect($service->hasHistoricalActivity($offering))->toBeFalse();

    // Explicit true boolean
    $offering->has_historical_activity = true;
    expect($service->hasHistoricalActivity($offering))->toBeTrue();

    // Explicit false boolean
    $offering->has_historical_activity = false;
    expect($service->hasHistoricalActivity($offering))->toBeFalse();

    // Truthy numeric dynamic property
    $offering->has_historical_activity = 1;
    expect($service->hasHistoricalActivity($offering))->toBeTrue();
});

test('hasHistoricalActivity() accurately checks all six historical database tables', function (string $tableName): void {
    $offering = Offering::factory()->create();
    $otherOffering = Offering::factory()->create();
    $service = new OfferingService;

    Schema::create($tableName, function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('offering_id');
        $table->timestamps();
    });

    try {
        // No records in table yet
        expect($service->hasHistoricalActivity($offering))->toBeFalse();

        // Record for another offering should NOT trigger historical activity for this offering
        DB::table($tableName)->insert([
            'offering_id' => $otherOffering->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        expect($service->hasHistoricalActivity($offering))->toBeFalse();

        // Record for target offering triggers true
        DB::table($tableName)->insert([
            'offering_id' => $offering->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        expect($service->hasHistoricalActivity($offering))->toBeTrue();
    } finally {
        Schema::dropIfExists($tableName);
    }
})->with([
    'pos_tickets',
    'ticket_offerings',
    'tickets',
    'order_items',
    'orders',
    'transactions',
]);

test('hasHistoricalActivity() gracefully handles tables that exist without offering_id column', function (): void {
    $offering = Offering::factory()->create();
    $service = new OfferingService;

    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->string('order_number');
    });

    try {
        DB::table('orders')->insert(['order_number' => 'ORD-12345']);

        // Schema::hasColumn checks prevent SQL column not found errors
        expect($service->hasHistoricalActivity($offering))->toBeFalse();
    } finally {
        Schema::dropIfExists('orders');
    }
});

/*
|--------------------------------------------------------------------------
| 4. deletePermanently() Edge Cases
|--------------------------------------------------------------------------
*/

test('deletePermanently() blocks deletion when historical activity exists and preserves record and image', function (): void {
    $service = new OfferingService;
    $file = UploadedFile::fake()->create('guarded-service.jpg', 40, 'image/jpeg');
    $imagePath = $service->storeImage($file);

    $offering = Offering::factory()->trashed()->create([
        'reference_image' => $imagePath,
    ]);

    // Activate historical activity via dynamic flag
    $offering->has_historical_activity = true;

    try {
        $service->deletePermanently($offering);
        $this->fail('Expected DomainException was not thrown.');
    } catch (DomainException $e) {
        expect($e->getMessage())->toBe('This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.');
    }

    // Invariant: database record is untouched
    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
    ]);

    // Invariant: disk image is untouched
    Storage::disk('public')->assertExists($imagePath);
});

test('deletePermanently() blocks deletion when historical table record is present', function (): void {
    $service = new OfferingService;
    $offering = Offering::factory()->trashed()->create();

    Schema::create('ticket_offerings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('offering_id');
    });

    try {
        DB::table('ticket_offerings')->insert(['offering_id' => $offering->id]);

        expect(fn () => $service->deletePermanently($offering))
            ->toThrow(DomainException::class, 'This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.');

        $this->assertDatabaseHas('offerings', ['id' => $offering->id]);
    } finally {
        Schema::dropIfExists('ticket_offerings');
    }
});

test('deletePermanently() forceDeletes record and purges reference image when no historical activity', function (): void {
    $service = new OfferingService;
    $file = UploadedFile::fake()->create('purge-me.png', 30, 'image/png');
    $imagePath = $service->storeImage($file);

    $offering = Offering::factory()->trashed()->create([
        'reference_image' => $imagePath,
    ]);
    $offeringId = $offering->id;

    Storage::disk('public')->assertExists($imagePath);

    $result = $service->deletePermanently($offering);

    expect($result)->toBeTrue();

    // Database record permanently gone
    $this->assertDatabaseMissing('offerings', ['id' => $offeringId]);
    expect(Offering::withTrashed()->find($offeringId))->toBeNull();

    // Image purged from storage disk
    Storage::disk('public')->assertMissing($imagePath);
});

test('deletePermanently() operates cleanly when reference image is null', function (): void {
    $service = new OfferingService;
    $offering = Offering::factory()->trashed()->create([
        'reference_image' => null,
    ]);
    $offeringId = $offering->id;

    $result = $service->deletePermanently($offering);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('offerings', ['id' => $offeringId]);
});

test('deletePermanently() operates cleanly when reference image is empty string or points to missing file', function (): void {
    $service = new OfferingService;

    // Empty string path
    $offeringEmpty = Offering::factory()->trashed()->create([
        'reference_image' => '   ',
    ]);
    $idEmpty = $offeringEmpty->id;

    expect($service->deletePermanently($offeringEmpty))->toBeTrue();
    $this->assertDatabaseMissing('offerings', ['id' => $idEmpty]);

    // Path points to a file that does not actually exist in storage
    $offeringMissingFile = Offering::factory()->trashed()->create([
        'reference_image' => 'offerings/non-existent-file.jpg',
    ]);
    $idMissingFile = $offeringMissingFile->id;

    expect($service->deletePermanently($offeringMissingFile))->toBeTrue();
    $this->assertDatabaseMissing('offerings', ['id' => $idMissingFile]);
});

/*
|--------------------------------------------------------------------------
| 5. listForBusiness() Status & Isolation Edge Cases
|--------------------------------------------------------------------------
*/

test('listForBusiness() status archived strictly returns soft-deleted offerings and status all returns non-deleted offerings', function (): void {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $category = Category::factory()->create();

    $activeA = Offering::factory()->active()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Active Item Business A',
    ]);
    $inactiveA = Offering::factory()->inactive()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Inactive Item Business A',
    ]);
    $archivedA = Offering::factory()->trashed()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'name' => 'Archived Item Business A',
    ]);

    // Other business items to test tenant isolation across all filters
    $activeB = Offering::factory()->active()->create(['business_id' => $otherBusiness->id]);
    $archivedB = Offering::factory()->trashed()->create(['business_id' => $otherBusiness->id]);

    $service = new OfferingService;

    // 1. Status 'archived'
    $archivedResults = $service->listForBusiness($business, ['status' => 'archived']);
    expect($archivedResults->total())->toBe(1)
        ->and($archivedResults->pluck('id')->all())->toBe([$archivedA->id])
        ->and($archivedResults->pluck('id')->all())->not->toContain($activeA->id, $inactiveA->id, $archivedB->id);

    // 2. Status 'all'
    $allResults = $service->listForBusiness($business, ['status' => 'all']);
    expect($allResults->total())->toBe(2)
        ->and($allResults->pluck('id')->all())->toContain($activeA->id, $inactiveA->id)
        ->and($allResults->pluck('id')->all())->not->toContain($archivedA->id, $activeB->id, $archivedB->id);

    // 3. Status 'active'
    $activeResults = $service->listForBusiness($business, ['status' => 'active']);
    expect($activeResults->total())->toBe(1)
        ->and($activeResults->pluck('id')->all())->toBe([$activeA->id])
        ->and($activeResults->pluck('id')->all())->not->toContain($inactiveA->id, $archivedA->id);

    // 4. Status 'inactive'
    $inactiveResults = $service->listForBusiness($business, ['status' => 'inactive']);
    expect($inactiveResults->total())->toBe(1)
        ->and($inactiveResults->pluck('id')->all())->toBe([$inactiveA->id])
        ->and($inactiveResults->pluck('id')->all())->not->toContain($activeA->id, $archivedA->id);

    // 5. Status normalization (case-insensitive and trimmed)
    $archivedCased = $service->listForBusiness($business, ['status' => '  ARCHIVED  ']);
    expect($archivedCased->pluck('id')->all())->toBe([$archivedA->id]);

    $activeCased = $service->listForBusiness($business, ['status' => '  Active  ']);
    expect($activeCased->pluck('id')->all())->toBe([$activeA->id]);
});
