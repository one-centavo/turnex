<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Offering;
use App\Services\OfferingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
});

/*
|--------------------------------------------------------------------------
| 1. Adversarial Tenant Isolation & ID Tampering
|--------------------------------------------------------------------------
*/

test('adversarial: merchant B cannot archive merchant A offering via confirmArchive (returns 404)', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringA = Offering::factory()->for($merchantA)->active()->create(['name' => 'Offering Merchant A']);

    Livewire::actingAs($merchantB, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmArchive', $offeringA->id)
        ->assertNotFound();

    expect($offeringA->fresh()->trashed())->toBeFalse()
        ->and($offeringA->fresh()->is_active)->toBeTrue();
});

test('adversarial: merchant B cannot archive merchant A offering via tampered confirmingArchiveId property', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringA = Offering::factory()->for($merchantA)->active()->create(['name' => 'Offering Merchant A']);

    Livewire::actingAs($merchantB, 'business')
        ->test('pages::business.offerings.index')
        ->set('confirmingArchiveId', $offeringA->id)
        ->call('archive')
        ->assertNotFound();

    expect($offeringA->fresh()->trashed())->toBeFalse();
});

test('adversarial: merchant B cannot restore merchant A offering via confirmRestore (returns 404)', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringA = Offering::factory()->for($merchantA)->trashed()->create(['name' => 'Trashed Offering Merchant A']);

    Livewire::actingAs($merchantB, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmRestore', $offeringA->id)
        ->assertNotFound();

    expect($offeringA->fresh()->trashed())->toBeTrue();
});

test('adversarial: merchant B cannot restore merchant A offering via tampered confirmingRestoreId property', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringA = Offering::factory()->for($merchantA)->trashed()->create(['name' => 'Trashed Offering Merchant A']);

    Livewire::actingAs($merchantB, 'business')
        ->test('pages::business.offerings.index')
        ->set('confirmingRestoreId', $offeringA->id)
        ->call('restore')
        ->assertNotFound();

    expect($offeringA->fresh()->trashed())->toBeTrue();
});

test('adversarial: merchant B cannot permanently delete merchant A offering via confirmPermanentDelete (returns 404)', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringA = Offering::factory()->for($merchantA)->trashed()->create(['name' => 'Trashed Offering Merchant A']);

    Livewire::actingAs($merchantB, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', $offeringA->id)
        ->assertNotFound();

    $this->assertDatabaseHas('offerings', ['id' => $offeringA->id]);
});

test('adversarial: merchant B cannot permanently delete merchant A offering via tampered confirmingPermanentDeleteId property', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringA = Offering::factory()->for($merchantA)->trashed()->create(['name' => 'Trashed Offering Merchant A']);

    Livewire::actingAs($merchantB, 'business')
        ->test('pages::business.offerings.index')
        ->set('confirmingPermanentDeleteId', $offeringA->id)
        ->call('deletePermanently')
        ->assertNotFound();

    $this->assertDatabaseHas('offerings', ['id' => $offeringA->id]);
});

/*
|--------------------------------------------------------------------------
| 2. Adversarial Unauthenticated Access
|--------------------------------------------------------------------------
*/

test('adversarial: unauthenticated guest is blocked on all action methods with 401', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $active = Offering::factory()->for($business)->active()->create();
    $trashed = Offering::factory()->for($business)->trashed()->create();

    Livewire::test('pages::business.offerings.index')
        ->call('confirmArchive', $active->id)
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->set('confirmingArchiveId', $active->id)
        ->call('archive')
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->call('confirmRestore', $trashed->id)
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->set('confirmingRestoreId', $trashed->id)
        ->call('restore')
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', $trashed->id)
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->set('confirmingPermanentDeleteId', $trashed->id)
        ->call('deletePermanently')
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| 3. Active Offering Permanent Deletion Prevention
|--------------------------------------------------------------------------
*/

test('adversarial: active offering cannot be permanently deleted via confirmPermanentDelete or deletePermanently (must 404)', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $active = Offering::factory()->for($business)->active()->create(['name' => 'Active Service Un-deletable']);

    // Attempting confirmPermanentDelete on active offering must 404 because query scopes to onlyTrashed()
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', $active->id)
        ->assertNotFound();

    // Directly attempting deletePermanently on active offering must 404
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('confirmingPermanentDeleteId', $active->id)
        ->call('deletePermanently')
        ->assertNotFound();

    $this->assertDatabaseHas('offerings', ['id' => $active->id]);
    expect($active->fresh()->trashed())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 4. Guarded Deletion: Historical Activity Protection
|--------------------------------------------------------------------------
*/

test('adversarial: confirmPermanentDelete blocks modal and displays exact error alert when historical activity exists', function (string $table): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create(['name' => 'Guarded Offering']);

    Schema::create($table, function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('offering_id');
        $t->timestamps();
    });

    try {
        DB::table($table)->insert([
            'offering_id' => $archived->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expectedAlert = 'This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.';

        $component = Livewire::actingAs($business, 'business')
            ->test('pages::business.offerings.index')
            ->set('status', 'archived')
            ->call('confirmPermanentDelete', $archived->id);

        // Modal ID must remain null (modal opening blocked)
        $component->assertSet('confirmingPermanentDeleteId', null)
            ->assertSet('errorMessage', $expectedAlert)
            ->assertSee($expectedAlert);

        $this->assertDatabaseHas('offerings', ['id' => $archived->id]);
    } finally {
        Schema::dropIfExists($table);
    }
})->with([
    'pos_tickets',
    'ticket_offerings',
    'tickets',
    'order_items',
    'orders',
    'transactions',
]);

/*
|--------------------------------------------------------------------------
| 5. Modal Dismissal Immutability
|--------------------------------------------------------------------------
*/

test('adversarial: dismissing modals leaves database records untouched', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $file = UploadedFile::fake()->create('offering.jpg', 30, 'image/jpeg');
    $service = new OfferingService;
    $imagePath = $service->storeImage($file);

    $active = Offering::factory()->for($business)->active()->create(['name' => 'Active To Dismiss']);
    $trashed = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Trashed To Dismiss',
        'reference_image' => $imagePath,
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    // 1. Archive modal opened and dismissed
    $component->call('confirmArchive', $active->id)
        ->assertSet('confirmingArchiveId', $active->id)
        ->set('confirmingArchiveId', null);

    expect($active->fresh()->trashed())->toBeFalse()
        ->and($active->fresh()->is_active)->toBeTrue();

    // 2. Restore modal opened and dismissed
    $component->call('confirmRestore', $trashed->id)
        ->assertSet('confirmingRestoreId', $trashed->id)
        ->set('confirmingRestoreId', null);

    expect($trashed->fresh()->trashed())->toBeTrue();

    // 3. Permanent delete modal opened and dismissed
    $component->call('confirmPermanentDelete', $trashed->id)
        ->assertSet('confirmingPermanentDeleteId', $trashed->id)
        ->set('confirmingPermanentDeleteId', null);

    $this->assertDatabaseHas('offerings', ['id' => $trashed->id]);
    Storage::disk('public')->assertExists($imagePath);
});

/*
|--------------------------------------------------------------------------
| 6. Pagination Reset on Status Switch
|--------------------------------------------------------------------------
*/

test('adversarial: switching status filter between any states resets pagination to page 1', function (string $initialStatus, string $targetStatus): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    Offering::factory()->count(15)->for($business)->active()->create();
    Offering::factory()->count(15)->for($business)->inactive()->create();
    Offering::factory()->count(15)->for($business)->trashed()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', $initialStatus)
        ->call('setPage', 2);

    expect($component->get('paginators.page'))->toBe(2);

    $component->set('status', $targetStatus);

    expect($component->get('paginators.page'))->toBe(1);
})->with([
    ['all', 'archived'],
    ['archived', 'all'],
    ['active', 'archived'],
    ['archived', 'active'],
    ['inactive', 'archived'],
    ['archived', 'inactive'],
]);
