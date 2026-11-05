<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Offering;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('unauthenticated guest is redirected to business login when visiting offerings index', function (): void {
    $this->get(route('business.offerings.index'))
        ->assertRedirect(route('business.login'));
});

test('unauthenticated user cannot execute archive, restore, or delete actions', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create();
    $trashedOffering = Offering::factory()->for($business)->trashed()->create();

    Livewire::test('pages::business.offerings.index')
        ->call('confirmArchive', $offering->id)
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->call('confirmRestore', $trashedOffering->id)
        ->assertUnauthorized();

    Livewire::test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', $trashedOffering->id)
        ->assertUnauthorized();
});

test('tenant isolation: business manager cannot archive offering belonging to another business', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->create();
    /** @var Business $businessB */
    $businessB = Business::factory()->create();

    $offeringB = Offering::factory()->for($businessB)->create(['name' => 'Foreign Offering B']);

    Livewire::actingAs($businessA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmArchive', $offeringB->id)
        ->assertNotFound();

    expect($offeringB->fresh()->trashed())->toBeFalse();
});

test('tenant isolation: business manager cannot restore offering belonging to another business', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->create();
    /** @var Business $businessB */
    $businessB = Business::factory()->create();

    $offeringB = Offering::factory()->for($businessB)->trashed()->create(['name' => 'Foreign Trashed Offering B']);

    Livewire::actingAs($businessA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmRestore', $offeringB->id)
        ->assertNotFound();

    expect($offeringB->fresh()->trashed())->toBeTrue();
});

test('tenant isolation: business manager cannot permanently delete offering belonging to another business', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->create();
    /** @var Business $businessB */
    $businessB = Business::factory()->create();

    $offeringB = Offering::factory()->for($businessB)->trashed()->create(['name' => 'Foreign Trashed Offering B']);

    Livewire::actingAs($businessA, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmPermanentDelete', $offeringB->id)
        ->assertNotFound();

    $this->assertDatabaseHas('offerings', [
        'id' => $offeringB->id,
    ]);
});

test('catalog table displays "Archivar" button for active and inactive offerings and opens archive confirmation modal', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $activeOffering = Offering::factory()->for($business)->active()->create(['name' => 'Corte Activo']);
    $inactiveOffering = Offering::factory()->for($business)->inactive()->create(['name' => 'Corte Pausado']);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Archivar')
        ->assertSee('data-test="archive-offering-'.$activeOffering->id.'"', false)
        ->assertSee('data-test="archive-offering-'.$inactiveOffering->id.'"', false)
        ->assertSee('data-test="edit-offering-'.$activeOffering->id.'"', false);

    // Call confirmArchive on active offering
    $component->call('confirmArchive', $activeOffering->id)
        ->assertSet('confirmingArchiveId', $activeOffering->id)
        ->assertSee('¿Archivar producto o servicio?')
        ->assertSee('Este producto o servicio se ocultará del catálogo comercial y de la vista de tus clientes, pero todo su historial de turnos y pedidos permanecerá intacto.')
        ->assertSee($activeOffering->name)
        ->assertSee('data-test="confirm-archive-btn"', false);
});

test('confirming archive sets is_active to false, soft-deletes offering, flashes notification, and removes item from default catalog', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->active()->create([
        'name' => 'Servicio a Archivar',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Servicio a Archivar')
        ->call('confirmArchive', $offering->id)
        ->call('archive')
        ->assertSee('Service archived successfully.')
        ->assertDispatched('toast-show')
        ->assertSet('confirmingArchiveId', null);

    $fresh = $offering->fresh();
    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->is_active)->toBeFalse();

    // In default catalog view ('all'), archived item is not visible
    $component->assertDontSee('Servicio a Archivar');
});

test('canceling archive confirmation modal leaves offering unchanged in active catalog', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->active()->create([
        'name' => 'Servicio Intacto',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->call('confirmArchive', $offering->id)
        ->assertSet('confirmingArchiveId', $offering->id);

    // Simulate closing modal / dismissing
    $component->set('confirmingArchiveId', null)
        ->assertSet('confirmingArchiveId', null)
        ->assertSee('Servicio Intacto');

    expect($offering->fresh()->trashed())->toBeFalse()
        ->and($offering->fresh()->is_active)->toBeTrue();
});

test('status filter includes "Archivados" option and filters to show only soft-deleted offerings', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $activeOffering = Offering::factory()->for($business)->active()->create(['name' => 'Servicio Visible']);
    $archivedOffering = Offering::factory()->for($business)->trashed()->create(['name' => 'Servicio Oculto Archivado']);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Archivados')
        ->assertSee('Servicio Visible')
        ->assertDontSee('Servicio Oculto Archivado');

    // Filter to archived
    $component->set('status', 'archived')
        ->assertSee('Servicio Oculto Archivado')
        ->assertDontSee('Servicio Visible');
});

test('changing status filter to or from "archived" resets table pagination to page 1', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    // Create 15 active offerings and 15 archived offerings
    Offering::factory()->count(15)->for($business)->active()->create();
    Offering::factory()->count(15)->for($business)->trashed()->create();

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->call('setPage', 2);

    expect($component->get('paginators.page'))->toBe(2);

    // Switch to archived resets to page 1
    $component->set('status', 'archived');
    expect($component->get('paginators.page'))->toBe(1);

    // Set page to 2 in archived list
    $component->call('setPage', 2);
    expect($component->get('paginators.page'))->toBe(2);

    // Switch back to all resets to page 1
    $component->set('status', 'all');
    expect($component->get('paginators.page'))->toBe(1);
});

test('onboarding trap fix: business with only archived offerings sees catalog table instead of onboarding view', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    // Business has 1 archived offering and 0 non-archived offerings
    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Mi Unico Servicio Archivado',
    ]);

    expect($business->offerings()->count())->toBe(0)
        ->and($business->offerings()->withTrashed()->count())->toBe(1);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertDontSee('Crear mi primer servicio')
        ->assertSee('Buscar por nombre...')
        ->assertSee('Archivados')
        ->set('status', 'archived')
        ->assertSee('Mi Unico Servicio Archivado');
});

test('archived offering row displays "Archivado" badge, "Restaurar" button, and "Eliminar definitivamente" button', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio en Archivo',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->assertSee('Servicio en Archivo')
        ->assertSee('Archivado')
        ->assertSee('Restaurar')
        ->assertSee('data-test="restore-offering-'.$archived->id.'"', false)
        ->assertSee('Eliminar definitivamente')
        ->assertSee('data-test="permanent-delete-offering-'.$archived->id.'"', false);
});

test('active catalog view strictly does not render "Eliminar definitivamente" button on active or inactive rows', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $active = Offering::factory()->for($business)->active()->create(['name' => 'Servicio Activo Seguro']);
    $inactive = Offering::factory()->for($business)->inactive()->create(['name' => 'Servicio Inactivo Seguro']);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Servicio Activo Seguro')
        ->assertSee('Servicio Inactivo Seguro')
        ->assertDontSee('data-test="permanent-delete-offering-'.$active->id.'"', false)
        ->assertDontSee('data-test="permanent-delete-offering-'.$inactive->id.'"', false);
});

test('contextual empty state displays when archived status filter has zero results', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    Offering::factory()->for($business)->active()->create(['name' => 'Servicio Activo']);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->assertSee('No se encontraron productos o servicios archivados.')
        ->assertSee('Los productos o servicios que archives aparecerán aquí.')
        ->assertDontSee('No se encontraron resultados');
});

test('restoring an archived offering returns it to catalog with previous details intact', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio Restaurable',
        'price' => '25000.00',
        'is_active' => false,
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->call('confirmRestore', $archived->id)
        ->assertSet('confirmingRestoreId', $archived->id)
        ->assertSee('¿Restaurar producto o servicio?')
        ->assertSee('Este producto o servicio volverá a estar disponible en tu catálogo comercial con su configuración anterior.')
        ->assertSee($archived->name)
        ->assertSee('data-test="confirm-restore-btn"', false)
        ->call('restore')
        ->assertSee('Service restored successfully.')
        ->assertDispatched('toast-show')
        ->assertSet('confirmingRestoreId', null);

    $fresh = $archived->fresh();
    expect($fresh->trashed())->toBeFalse()
        ->and($fresh->price)->toBe('25000.00');

    // No longer in archived view
    $component->assertDontSee('Servicio Restaurable');

    // Visible in 'all' view
    $component->set('status', 'all')
        ->assertSee('Servicio Restaurable');
});

test('canceling restore confirmation leaves offering archived', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio Permanece Archivado',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->call('confirmRestore', $archived->id)
        ->assertSet('confirmingRestoreId', $archived->id)
        ->set('confirmingRestoreId', null)
        ->assertSet('confirmingRestoreId', null);

    expect($archived->fresh()->trashed())->toBeTrue();
});

test('permanent deletion is blocked with alert when historical activity exists and modal does not open', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio Con Historial',
    ]);

    // Create a temporary historical table simulating past tickets
    Schema::create('ticket_offerings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('offering_id');
    });

    try {
        DB::table('ticket_offerings')->insert(['offering_id' => $archived->id]);

        $expectedMessage = 'This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.';

        $component = Livewire::actingAs($business, 'business')
            ->test('pages::business.offerings.index')
            ->set('status', 'archived')
            ->call('confirmPermanentDelete', $archived->id);

        $component->assertSet('confirmingPermanentDeleteId', null)
            ->assertSet('errorMessage', $expectedMessage)
            ->assertSee($expectedMessage)
            ->assertDispatched('toast-show');

        // Record must remain intact in database
        $this->assertDatabaseHas('offerings', [
            'id' => $archived->id,
        ]);
        expect($archived->fresh()->trashed())->toBeTrue();
    } finally {
        Schema::dropIfExists('ticket_offerings');
    }
});

test('permanent deletion of unreferenced archived offering removes database record and stored image', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $file = UploadedFile::fake()->create('purge-image.jpg', 40, 'image/jpeg');
    $imagePath = $file->store('offerings', 'public');

    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio Limpiable',
        'reference_image' => $imagePath,
    ]);
    $archivedId = $archived->id;

    Storage::disk('public')->assertExists($imagePath);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->call('confirmPermanentDelete', $archived->id)
        ->assertSet('confirmingPermanentDeleteId', $archivedId)
        ->assertSee('¿Eliminar definitivamente este servicio?')
        ->assertSee('Esta acción no se puede deshacer. El producto o servicio y su imagen asociada se eliminarán permanentemente de la plataforma.')
        ->assertSee($archived->name)
        ->assertSee('data-test="confirm-delete-btn"', false)
        ->call('deletePermanently')
        ->assertSee('Service permanently deleted.')
        ->assertDispatched('toast-show')
        ->assertSet('confirmingPermanentDeleteId', null);

    // Database record must be permanently destroyed (not just soft-deleted)
    $this->assertDatabaseMissing('offerings', [
        'id' => $archivedId,
    ]);

    // Image file must be deleted from storage disk
    Storage::disk('public')->assertMissing($imagePath);

    // No longer rendered in archived view
    $component->assertDontSee('Servicio Limpiable');
});

test('canceling permanent deletion confirmation leaves offering soft-deleted and image intact', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $file = UploadedFile::fake()->create('keep-image.jpg', 30, 'image/jpeg');
    $imagePath = $file->store('offerings', 'public');

    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio Cancelado',
        'reference_image' => $imagePath,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->call('confirmPermanentDelete', $archived->id)
        ->assertSet('confirmingPermanentDeleteId', $archived->id)
        ->set('confirmingPermanentDeleteId', null)
        ->assertSet('confirmingPermanentDeleteId', null);

    $this->assertDatabaseHas('offerings', [
        'id' => $archived->id,
    ]);
    Storage::disk('public')->assertExists($imagePath);
});

test('deletePermanently handles DomainException gracefully if historical activity is linked concurrently', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $archived = Offering::factory()->for($business)->trashed()->create([
        'name' => 'Servicio Carrera',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->set('status', 'archived')
        ->call('confirmPermanentDelete', $archived->id)
        ->assertSet('confirmingPermanentDeleteId', $archived->id);

    // Create activity before execution
    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('offering_id');
    });

    try {
        DB::table('orders')->insert(['offering_id' => $archived->id]);

        $expectedMessage = 'This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.';

        $component->call('deletePermanently');

        $component->assertSet('confirmingPermanentDeleteId', null)
            ->assertSet('errorMessage', $expectedMessage)
            ->assertSee($expectedMessage)
            ->assertDispatched('toast-show');

        $this->assertDatabaseHas('offerings', [
            'id' => $archived->id,
        ]);
    } finally {
        Schema::dropIfExists('orders');
    }
});
