<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('unauthenticated guest is redirected to business login when visiting offerings edit page', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create();

    $this->get(route('business.offerings.edit', $offering))
        ->assertRedirect(route('business.login'));
});

test('authenticated business user cannot access or edit an offering belonging to another business (tenant isolation IDOR mitigation)', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->create();
    /** @var Business $businessB */
    $businessB = Business::factory()->create();

    $offeringB = Offering::factory()->for($businessB)->create([
        'name' => 'Servicio Exclusivo Negocio B',
    ]);

    // Direct HTTP request returns 404
    $this->actingAs($businessA, 'business')
        ->get(route('business.offerings.edit', $offeringB))
        ->assertNotFound();

    // Livewire component initialization returns 404
    Livewire::actingAs($businessA, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offeringB])
        ->assertStatus(404);
});

test('visiting edit page with a non-existent offering id returns 404', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get('/business/offerings/999999/edit')
        ->assertNotFound();
});

test('authenticated business user can view edit offering page pre-populated with current values', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create(['name' => 'Barbería y Estilo']);

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Corte Degradado Fade',
        'description' => 'Corte profesional con degradado a navaja.',
        'price' => 35000.00,
        'estimated_duration_minutes' => 45,
        'is_active' => true,
        'reference_image' => 'offerings/fade-service.png',
    ]);

    $this->actingAs($business, 'business')
        ->get(route('business.offerings.edit', $offering))
        ->assertOk()
        ->assertSee('Editar Producto o Servicio')
        ->assertSee('Corte Degradado Fade')
        ->assertSee('Volver al catálogo');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->assertSet('name', 'Corte Degradado Fade')
        ->assertSet('category_id', $category->id)
        ->assertSet('description', 'Corte profesional con degradado a navaja.')
        ->assertSet('price', 35000.0)
        ->assertSet('estimated_duration_minutes', 45)
        ->assertSet('is_active', true)
        ->assertSet('existing_image', 'offerings/fade-service.png')
        ->assertSet('remove_image', false)
        ->assertSet('image', null);
});

test('pre-population accurately reflects inactive offering state', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->inactive()->create([
        'name' => 'Tratamiento Fuera de Temporada',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->assertSet('is_active', false);
});

test('edit form preserves and displays assigned soft-deleted category with archived suffix in dropdown', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $trashedCategory = Category::factory()->trashed()->create(['name' => 'Categoría Eliminada']);
    $activeCategory = Category::factory()->active()->create(['name' => 'Categoría Vigente']);
    $unrelatedTrashedCategory = Category::factory()->trashed()->create(['name' => 'Otra Trashed Inaccesible']);

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $trashedCategory->id,
        'name' => 'Servicio con Categoría Antigua',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->assertSee('Categoría Eliminada (Archived)')
        ->assertSee('Categoría Vigente')
        ->assertDontSee('Otra Trashed Inaccesible');
});

test('edit form preserves and displays assigned inactive category with archived suffix in dropdown', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $inactiveCategory = Category::factory()->inactive()->create(['name' => 'Categoría Desactivada']);
    $activeCategory = Category::factory()->active()->create(['name' => 'Categoría Activa']);

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $inactiveCategory->id,
        'name' => 'Servicio con Categoría Desactivada',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->assertSee('Categoría Desactivada (Archived)')
        ->assertSee('Categoría Activa');
});

test('category validation allows keeping current archived category but rejects selecting another archived category', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $assignedArchivedCategory = Category::factory()->trashed()->create();
    $unassignedArchivedCategory = Category::factory()->trashed()->create();
    $unassignedInactiveCategory = Category::factory()->inactive()->create();
    $activeCategory = Category::factory()->active()->create();

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $assignedArchivedCategory->id,
        'name' => 'Servicio Existente',
    ]);

    // Keeping the currently assigned archived category passes validation
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->call('save')
        ->assertHasNoErrors(['category_id']);

    // Attempting to select another unassigned archived category fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('category_id', $unassignedArchivedCategory->id)
        ->call('save')
        ->assertHasErrors(['category_id'])
        ->assertSee('La categoría seleccionada no es válida o está inactiva.');

    // Attempting to select another unassigned inactive category fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('category_id', $unassignedInactiveCategory->id)
        ->call('save')
        ->assertHasErrors(['category_id'])
        ->assertSee('La categoría seleccionada no es válida o está inactiva.');

    // Selecting a valid active category succeeds
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('category_id', $activeCategory->id)
        ->call('save')
        ->assertHasNoErrors(['category_id']);
});

test('validation fails when required fields are empty', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', '')
        ->set('category_id', '')
        ->call('save')
        ->assertHasErrors(['name', 'category_id'])
        ->assertSee('El nombre del servicio es obligatorio.')
        ->assertSee('Debes seleccionar una categoría.');
});

test('name validation enforces boundary length and rejects whitespace-only string', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();
    $offering = Offering::factory()->for($business)->create(['category_id' => $category->id]);

    // Less than 3 characters fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', 'Ab')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('El nombre debe tener al menos 3 caracteres.');

    // Greater than 50 characters fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', str_repeat('A', 51))
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('El nombre no puede superar los 50 caracteres.');

    // Whitespace only fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', '       ')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('El nombre del servicio es obligatorio.');

    // Boundary 3 characters passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', 'Cbd')
        ->call('save')
        ->assertHasNoErrors(['name']);

    // Boundary 50 characters passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', str_repeat('A', 50))
        ->call('save')
        ->assertHasNoErrors(['name']);
});

test('description validation enforces 250 character limit and accepts empty value', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create();

    // Exceeding 250 characters fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('description', str_repeat('D', 251))
        ->call('save')
        ->assertHasErrors(['description'])
        ->assertSee('La descripción no puede superar los 250 caracteres.');

    // Exactly 250 characters passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('description', str_repeat('D', 250))
        ->call('save')
        ->assertHasNoErrors(['description']);

    // Empty description passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('description', '')
        ->call('save')
        ->assertHasNoErrors(['description']);
});

test('price validation rejects negative values and amounts exceeding maximum', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create();

    // Negative price
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('price', -5.00)
        ->call('save')
        ->assertHasErrors(['price'])
        ->assertSee('El precio no puede ser negativo.');

    // Exceeding maximum
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('price', 100000000.00)
        ->call('save')
        ->assertHasErrors(['price'])
        ->assertSee('El precio no puede superar 99,999,999.99.');

    // Valid price passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('price', 89000.50)
        ->call('save')
        ->assertHasNoErrors(['price']);
});

test('clearing price resets price to 0.00 upon saving', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create([
        'price' => 45000.00,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('price', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($offering->fresh()->price)->toBe('0.00');
});

test('estimated duration validation rejects zero, negative values, and non-integers', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create();

    // Zero minutes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('estimated_duration_minutes', 0)
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSee('La duración estimada debe ser de al menos 1 minuto.');

    // Negative minutes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('estimated_duration_minutes', -10)
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSee('La duración estimada debe ser de al menos 1 minuto.');

    // Non-integer
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('estimated_duration_minutes', '25.5')
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSee('La duración debe ser un número entero de minutos.');

    // Valid integer >= 1 passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('estimated_duration_minutes', 30)
        ->call('save')
        ->assertHasNoErrors(['estimated_duration_minutes']);
});

test('clearing estimated duration removes duration on save', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create([
        'estimated_duration_minutes' => 60,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('estimated_duration_minutes', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($offering->fresh()->estimated_duration_minutes)->toBeNull();
});

test('active status can be toggled and persisted', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->active()->create();

    // Toggle active to inactive
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($offering->fresh()->is_active)->toBeFalse();

    // Toggle inactive back to active
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering->fresh()])
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($offering->fresh()->is_active)->toBeTrue();
});

test('submitting invalid data displays errors without wiping previously entered valid inputs', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create([
        'name' => 'Nombre Inicial',
        'price' => 10000.00,
        'description' => 'Descripción Inicial',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', 'Corte con Estilo Modificado')
        ->set('price', -50.00) // Invalid price
        ->set('description', 'Nueva descripción persistente')
        ->call('save')
        ->assertHasErrors(['price'])
        ->assertSet('name', 'Corte con Estilo Modificado')
        ->assertSet('description', 'Nueva descripción persistente');
});

test('submitting form without changes updates cleanly and redirects with flash message', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();
    $offering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Sin Cambios',
        'price' => 25000.00,
        'estimated_duration_minutes' => 30,
        'description' => 'Sin modificaciones.',
        'is_active' => true,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSessionHas('status', 'Service updated successfully.')
        ->assertRedirect(route('business.offerings.index'));

    $freshOffering = $offering->fresh();
    expect($freshOffering->name)->toBe('Servicio Sin Cambios')
        ->and($freshOffering->price)->toBe('25000.00')
        ->and($freshOffering->estimated_duration_minutes)->toBe(30);
});

test('successful offering update persists all fields with sanitization and redirects', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $categoryOld = Category::factory()->active()->create();
    $categoryNew = Category::factory()->active()->create();

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $categoryOld->id,
        'name' => 'Nombre Anterior',
        'description' => 'Descripción anterior',
        'price' => 20000.00,
        'estimated_duration_minutes' => 20,
        'is_active' => true,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', '   Corte   de   Barba   VIP   ')
        ->set('category_id', $categoryNew->id)
        ->set('description', "   Nueva   descripción   \n   con   estilo.   ")
        ->set('price', '48000.50')
        ->set('estimated_duration_minutes', 50)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSessionHas('status', 'Service updated successfully.')
        ->assertRedirect(route('business.offerings.index'));

    $freshOffering = $offering->fresh();
    expect($freshOffering)->not->toBeNull()
        ->and($freshOffering->name)->toBe('Corte de Barba VIP')
        ->and($freshOffering->category_id)->toBe($categoryNew->id)
        ->and($freshOffering->description)->toBe('Nueva descripción con estilo.')
        ->and($freshOffering->price)->toBe('48000.50')
        ->and($freshOffering->estimated_duration_minutes)->toBe(50)
        ->and($freshOffering->is_active)->toBeFalse();
});

test('retaining existing image leaves file intact on disk when no new image is provided', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();

    $initialFile = UploadedFile::fake()->create('existing-service.png', 100, 'image/png');
    $storedPath = $initialFile->store('offerings', 'public');

    $offering = Offering::factory()->for($business)->create([
        'name' => 'Servicio Con Imagen Inicial',
        'reference_image' => $storedPath,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('name', 'Servicio Con Imagen Conservada')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('business.offerings.index'));

    Storage::disk('public')->assertExists($storedPath);
    expect($offering->fresh()->reference_image)->toBe($storedPath);
});

test('replacing reference image stores new file and deletes old file from disk upon save', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();

    $oldFile = UploadedFile::fake()->create('old-photo.jpg', 120, 'image/jpeg');
    $oldPath = $oldFile->store('offerings', 'public');

    $offering = Offering::factory()->for($business)->create([
        'name' => 'Servicio Imagen Antigua',
        'reference_image' => $oldPath,
    ]);

    $replacementFile = UploadedFile::fake()->create('new-photo.png', 200, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('image', $replacementFile)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('business.offerings.index'));

    $freshOffering = $offering->fresh();

    // Old file deleted
    Storage::disk('public')->assertMissing($oldPath);

    // New file exists
    expect($freshOffering->reference_image)->not->toBeNull()
        ->and($freshOffering->reference_image)->not->toBe($oldPath);

    Storage::disk('public')->assertExists((string) $freshOffering->reference_image);
});

test('discarding temporary image upload reverts to existing saved image preview', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();

    $existingFile = UploadedFile::fake()->create('saved-photo.png', 50, 'image/png');
    $existingPath = $existingFile->store('offerings', 'public');

    $offering = Offering::factory()->for($business)->create([
        'reference_image' => $existingPath,
    ]);

    $tempFile = UploadedFile::fake()->create('temp-photo.png', 80, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('image', $tempFile)
        ->assertSet('image', fn ($value): bool => $value !== null)
        ->call('discardTempImage')
        ->assertSet('image', null)
        ->assertSet('existing_image', $existingPath)
        ->assertSet('remove_image', false)
        ->call('save')
        ->assertHasNoErrors();

    // Existing file is kept intact
    Storage::disk('public')->assertExists($existingPath);
    expect($offering->fresh()->reference_image)->toBe($existingPath);
});

test('general removeImage action discards temporary upload if present or marks existing image for removal', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();

    $existingFile = UploadedFile::fake()->create('saved-photo.png', 50, 'image/png');
    $existingPath = $existingFile->store('offerings', 'public');

    $offering = Offering::factory()->for($business)->create([
        'reference_image' => $existingPath,
    ]);

    $tempFile = UploadedFile::fake()->create('temp-photo.png', 80, 'image/png');

    // 1. With temporary image set, removeImage discards temp image
    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('image', $tempFile)
        ->call('removeImage')
        ->assertSet('image', null)
        ->assertSet('remove_image', false);

    // 2. Without temporary image, removeImage marks existing image for removal
    $component->call('removeImage')
        ->assertSet('remove_image', true);

    // 3. User can restore existing image
    $component->call('restoreExistingImage')
        ->assertSet('remove_image', false);
});

test('explicit image removal deletes old file from disk and clears reference_image on save', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();

    $existingFile = UploadedFile::fake()->create('photo-to-remove.png', 50, 'image/png');
    $existingPath = $existingFile->store('offerings', 'public');

    $offering = Offering::factory()->for($business)->create([
        'reference_image' => $existingPath,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->call('removeExistingImage')
        ->assertSet('remove_image', true)
        ->assertSet('image', null)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('business.offerings.index'));

    Storage::disk('public')->assertMissing($existingPath);
    expect($offering->fresh()->reference_image)->toBeNull();
});

test('invalid image upload rejects invalid formats or oversized files and retains existing image', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();

    $existingFile = UploadedFile::fake()->create('saved.png', 50, 'image/png');
    $existingPath = $existingFile->store('offerings', 'public');

    $offering = Offering::factory()->for($business)->create([
        'reference_image' => $existingPath,
    ]);

    // Invalid non-image format
    $txtFile = UploadedFile::fake()->create('notes.txt', 20, 'text/plain');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('image', $txtFile)
        ->assertHasErrors(['image'])
        ->assertSet('image', null)
        ->assertSet('existing_image', $existingPath);

    // Oversized image (> 10MB)
    $oversizedFile = UploadedFile::fake()->create('huge.png', 10241, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->set('image', $oversizedFile)
        ->assertHasErrors(['image'])
        ->assertSee('La imagen no debe superar los 10 MB.')
        ->assertSet('image', null)
        ->assertSet('existing_image', $existingPath);
});

test('cancel button directs back to offerings index with wire:navigate without saving changes', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create([
        'name' => 'Nombre Inmutable',
        'price' => 15000.00,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.edit', ['offering' => $offering])
        ->assertSee(route('business.offerings.index'))
        ->assertSee('Cancelar')
        ->assertSee('Volver al catálogo');

    // Offering remains unmodified
    expect($offering->fresh()->name)->toBe('Nombre Inmutable')
        ->and($offering->fresh()->price)->toBe('15000.00');
});

test('offerings index table renders Editar action button linking to offering edit route', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $offering = Offering::factory()->for($business)->create([
        'name' => 'Servicio Con Botón Editar',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee($offering->name)
        ->assertSee(route('business.offerings.edit', $offering))
        ->assertSee('Editar')
        ->assertSee('data-test="edit-offering-button-'.$offering->id.'"', false);
});
