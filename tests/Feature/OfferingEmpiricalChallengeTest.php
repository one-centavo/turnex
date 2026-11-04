<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
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
| 1. Status Filter Transitions Matrix & Pagination Resets
|--------------------------------------------------------------------------
*/

test('all 12 status filter transition permutations reset pagination from page 2 to page 1', function (string $fromStatus, string $toStatus): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    Offering::factory()->count(12)->for($business)->active()->create();
    Offering::factory()->count(12)->for($business)->inactive()->create();
    Offering::factory()->count(12)->for($business)->trashed()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', $fromStatus)
        ->call('setPage', 2);

    expect($component->get('paginators.page'))->toBe(2);

    $component->set('status', $toStatus);

    expect($component->get('paginators.page'))->toBe(1)
        ->and($component->get('status'))->toBe($toStatus)
        ->and($component->get('errorMessage'))->toBeNull();
})->with([
    ['all', 'active'],
    ['all', 'inactive'],
    ['all', 'archived'],
    ['active', 'all'],
    ['active', 'inactive'],
    ['active', 'archived'],
    ['inactive', 'all'],
    ['inactive', 'active'],
    ['inactive', 'archived'],
    ['archived', 'all'],
    ['archived', 'active'],
    ['archived', 'inactive'],
]);

test('resetFilters resets search, category, status to all, and pagination to page 1', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->create(['name' => 'Haircut']);

    Offering::factory()->count(15)->for($business)->for($category)->trashed()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->set('category', (string) $category->id)
        ->set('search', 'Haircut')
        ->call('setPage', 2);

    expect($component->get('paginators.page'))->toBe(2);

    $component->call('resetFilters');

    expect($component->get('status'))->toBe('all')
        ->and($component->get('category'))->toBe('')
        ->and($component->get('search'))->toBe('')
        ->and($component->get('paginators.page'))->toBe(1)
        ->and($component->get('errorMessage'))->toBeNull();
});

test('search and category property updates reset pagination to page 1 and clear error message', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->create();

    Offering::factory()->count(15)->for($business)->active()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->call('setPage', 2)
        ->set('errorMessage', 'Previous error');

    expect($component->get('paginators.page'))->toBe(2)
        ->and($component->get('errorMessage'))->toBe('Previous error');

    $component->set('search', 'Test');
    expect($component->get('paginators.page'))->toBe(1)
        ->and($component->get('errorMessage'))->toBeNull();

    $component->call('setPage', 2)
        ->set('errorMessage', 'Another error');
    $component->set('category', (string) $category->id);
    expect($component->get('paginators.page'))->toBe(1)
        ->and($component->get('errorMessage'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 2. Safe Onboarding Count (Merchants Never Trapped)
|--------------------------------------------------------------------------
*/

test('merchant with 0 total offerings sees onboarding view with "Crear mi primer servicio"', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Crear mi primer servicio')
        ->assertSee('Catálogo comercial')
        ->assertDontSee('Todas las categorías')
        ->assertDontSee('Archivados');
});

test('merchant with only archived offerings is not trapped in onboarding view', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $archivedOffering = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Archived Legacy Offering',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    // Verify onboarding view is NOT shown
    $component->assertDontSee('Crear mi primer servicio')
        // Verify filters and create button are accessible
        ->assertSee('Nuevo Producto o Servicio')
        ->assertSee('Todas las categorías')
        ->assertSee('Buscar por nombre...');

    // When viewing "all" status (default), archived items are excluded from active list, showing contextual empty state
    $component->assertSee('No se encontraron resultados')
        ->assertSee('Restablecer filtros')
        ->assertDontSee('Archived Legacy Offering');

    // When switching to "archived", the item is visible with actions
    $component->set('status', 'archived')
        ->assertSee('Archived Legacy Offering')
        ->assertSee('Archivado')
        ->assertSee('Restaurar')
        ->assertSee('Eliminar definitivamente');
});

test('archiving the last active offering does not trap merchant into onboarding view', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $offering = Offering::factory()->for($business)->active()->create([
        'name' => 'Sole Active Offering',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    $component->assertSee('Sole Active Offering')
        ->assertDontSee('Crear mi primer servicio')
        ->call('confirmArchive', $offering->id)
        ->call('archive');

    expect($offering->fresh()->trashed())->toBeTrue();

    // After archiving, totalOfferingsCount is still 1 (via withTrashed()), so onboarding view is NOT shown
    $component->assertDontSee('Crear mi primer servicio')
        ->assertSee('Nuevo Producto o Servicio')
        ->assertSee('No se encontraron resultados');
});

test('contextual empty state for archived filter displays specific Spanish copy', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    // Merchant has active offerings but no archived offerings
    Offering::factory()->for($business)->active()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->assertSee('No se encontraron productos o servicios archivados.')
        ->assertSee('Los productos o servicios que archives aparecerán aquí.');
});

/*
|--------------------------------------------------------------------------
| 3. Action Confirmation Modals: Cancellation vs Submission
|--------------------------------------------------------------------------
*/

test('archive modal workflow: confirmation sets state and submission soft-deletes', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->active()->create([
        'name' => 'Laser Cut Service',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    // Open confirmation modal
    $component->call('confirmArchive', $offering->id)
        ->assertSet('confirmingArchiveId', $offering->id)
        ->assertSee('¿Archivar producto o servicio?')
        ->assertSee('Laser Cut Service')
        ->assertSee('Archivar servicio')
        ->assertSee('Cancelar');

    // Confirm archive submission
    $component->call('archive')
        ->assertSet('confirmingArchiveId', null)
        ->assertDispatched('toast-show')
        ->assertSee('Service archived successfully.');

    expect($offering->fresh()->trashed())->toBeTrue()
        ->and($offering->fresh()->is_active)->toBeFalse();
});

test('archive cancellation leaves offering active and untouched', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->active()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    $component->call('confirmArchive', $offering->id)
        ->assertSet('confirmingArchiveId', $offering->id)
        ->set('confirmingArchiveId', null);

    expect($offering->fresh()->trashed())->toBeFalse()
        ->and($offering->fresh()->is_active)->toBeTrue();
});

test('restore modal workflow: confirmation sets state and submission restores offering', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Old Haircut Service',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived');

    // Open restore modal
    $component->call('confirmRestore', $offering->id)
        ->assertSet('confirmingRestoreId', $offering->id)
        ->assertSee('¿Restaurar producto o servicio?')
        ->assertSee('Old Haircut Service')
        ->assertSee('Restaurar servicio')
        ->assertSee('Cancelar');

    // Confirm restore submission
    $component->call('restore')
        ->assertSet('confirmingRestoreId', null)
        ->assertDispatched('toast-show')
        ->assertSee('Service restored successfully.');

    expect($offering->fresh()->trashed())->toBeFalse();
});

test('restore cancellation leaves offering soft-deleted', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->trashed()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived');

    $component->call('confirmRestore', $offering->id)
        ->assertSet('confirmingRestoreId', $offering->id)
        ->set('confirmingRestoreId', null);

    expect($offering->fresh()->trashed())->toBeTrue();
});

test('permanent delete modal workflow: confirmation sets state and submission force-deletes record and purges image', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $file = UploadedFile::fake()->create('service-photo.jpg', 40, 'image/jpeg');
    $imagePath = (new OfferingService)->storeImage($file);

    $offering = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Temporary Promo',
        'reference_image' => $imagePath,
    ]);

    Storage::disk('public')->assertExists($imagePath);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived');

    // Open permanent delete modal
    $component->call('confirmPermanentDelete', $offering->id)
        ->assertSet('confirmingPermanentDeleteId', $offering->id)
        ->assertSee('¿Eliminar definitivamente este servicio?')
        ->assertSee('Temporary Promo')
        ->assertSee('Eliminar definitivamente')
        ->assertSee('Cancelar');

    // Confirm permanent delete submission
    $component->call('deletePermanently')
        ->assertSet('confirmingPermanentDeleteId', null)
        ->assertDispatched('toast-show')
        ->assertSee('Service permanently deleted.');

    $this->assertDatabaseMissing('offerings', ['id' => $offering->id]);
    Storage::disk('public')->assertMissing($imagePath);
});

test('permanent delete cancellation leaves offering in database and image on storage', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $file = UploadedFile::fake()->create('service-photo.jpg', 40, 'image/jpeg');
    $imagePath = (new OfferingService)->storeImage($file);

    $offering = Offering::factory()->for($business)->trashed()->create([
        'reference_image' => $imagePath,
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived');

    $component->call('confirmPermanentDelete', $offering->id)
        ->assertSet('confirmingPermanentDeleteId', $offering->id)
        ->set('confirmingPermanentDeleteId', null);

    $this->assertDatabaseHas('offerings', ['id' => $offering->id]);
    Storage::disk('public')->assertExists($imagePath);
});

test('permanent delete is blocked when historical activity exists and modal does not open', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Popular Spa Service',
    ]);

    Schema::create('ticket_offerings', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('offering_id');
        $t->timestamps();
    });

    try {
        DB::table('ticket_offerings')->insert([
            'offering_id' => $offering->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($business, 'business')
            ->test('pages::business.offerings.index')
            ->set('status', 'archived');

        $component->call('confirmPermanentDelete', $offering->id);

        // Modal ID must remain null
        $component->assertSet('confirmingPermanentDeleteId', null);

        $expectedAlert = 'This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.';
        $component->assertSet('errorMessage', $expectedAlert)
            ->assertSee($expectedAlert)
            ->assertDispatched('toast-show');

        $this->assertDatabaseHas('offerings', ['id' => $offering->id]);
    } finally {
        Schema::dropIfExists('ticket_offerings');
    }
});

test('action methods handle null confirming IDs gracefully as no-ops', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    // Calling actions without opening modal (confirming IDs are null)
    $component->call('archive')
        ->assertSet('confirmingArchiveId', null);

    $component->call('restore')
        ->assertSet('confirmingRestoreId', null);

    $component->call('deletePermanently')
        ->assertSet('confirmingPermanentDeleteId', null);
});

/*
|--------------------------------------------------------------------------
| 4. Adversarial Tenant Isolation & ID Tampering
|--------------------------------------------------------------------------
*/

test('merchant cannot confirmArchive on non-existent or foreign offering (returns 404)', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringB = Offering::factory()->for($merchantB)->active()->create();

    Livewire::actingAs($merchantA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmArchive', 999999)
        ->assertNotFound();

    Livewire::actingAs($merchantA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmArchive', $offeringB->id)
        ->assertNotFound();
});

test('merchant cannot confirmRestore on non-existent or foreign offering (returns 404)', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringB = Offering::factory()->for($merchantB)->trashed()->create();

    Livewire::actingAs($merchantA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmRestore', 999999)
        ->assertNotFound();

    Livewire::actingAs($merchantA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmRestore', $offeringB->id)
        ->assertNotFound();
});

test('merchant cannot confirmPermanentDelete on non-existent or foreign offering (returns 404)', function (): void {
    /** @var Business $merchantA */
    $merchantA = Business::factory()->create();
    /** @var Business $merchantB */
    $merchantB = Business::factory()->create();

    $offeringB = Offering::factory()->for($merchantB)->trashed()->create();

    Livewire::actingAs($merchantA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', 999999)
        ->assertNotFound();

    Livewire::actingAs($merchantA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', $offeringB->id)
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 5. DOM Assertions & Action Button Visibility
|--------------------------------------------------------------------------
*/

test('active catalog rows strictly display "Archivar" and never "Eliminar definitivamente" or "Restaurar"', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $active = Offering::factory()->for($business)->active()->create(['name' => 'Active Item Only']);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'all')
        ->assertSee('Active Item Only')
        ->assertSee('Archivar')
        ->assertSee('data-test="archive-offering-'.$active->id.'"', false)
        ->assertDontSee('data-test="restore-offering-'.$active->id.'"', false)
        ->assertDontSee('data-test="permanent-delete-offering-'.$active->id.'"', false);
});

test('archived catalog rows strictly display "Archivado" badge, "Restaurar", and "Eliminar definitivamente"', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create(['name' => 'Archived Item Only']);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->assertSee('Archived Item Only')
        ->assertSee('Archivado')
        ->assertSee('Restaurar')
        ->assertSee('Eliminar definitivamente')
        ->assertDontSee('Editar');
});
