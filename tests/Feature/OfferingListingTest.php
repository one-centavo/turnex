<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('unauthenticated guest is redirected to business login when visiting offerings index', function (): void {
    $this->get(route('business.offerings.index'))
        ->assertRedirect(route('business.login'));
});

test('authenticated business user can view offerings index page', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get(route('business.offerings.index'))
        ->assertOk()
        ->assertSee('Productos y Servicios')
        ->assertSee('Gestiona el catálogo comercial de productos y servicios que ofreces a tus clientes.')
        ->assertSee('Nuevo Producto o Servicio');
});

test('offerings table strictly displays offerings belonging to authenticated business (tenant isolation)', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->create();
    /** @var Business $businessB */
    $businessB = Business::factory()->create();

    $categoryA = Category::factory()->active()->create(['name' => 'Categoría Tenant A']);
    $categoryB = Category::factory()->active()->create(['name' => 'Categoría Tenant B']);

    $offeringA = Offering::factory()->for($businessA)->create([
        'category_id' => $categoryA->id,
        'name' => 'Corte Premium Tenant A',
    ]);

    $offeringB = Offering::factory()->for($businessB)->create([
        'category_id' => $categoryB->id,
        'name' => 'Manicura Secreta Tenant B',
    ]);

    // Business A sees only Offering A
    Livewire::actingAs($businessA, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee($offeringA->name)
        ->assertDontSee($offeringB->name);

    // Business B sees only Offering B
    Livewire::actingAs($businessB, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee($offeringB->name)
        ->assertDontSee($offeringA->name);
});

test('table renders columns with thumbnail image when present and placeholder icon when absent', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create(['name' => 'Barbería']);

    $imageFile = UploadedFile::fake()->create('thumbnail.png', 50, 'image/png');
    $imagePath = $imageFile->store('offerings', 'public');

    $offeringWithImage = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Corte con Foto',
        'reference_image' => $imagePath,
    ]);

    $offeringWithoutImage = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Corte sin Foto',
        'reference_image' => null,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee($offeringWithImage->name)
        ->assertSee(Storage::url($imagePath))
        ->assertSee($offeringWithoutImage->name);
});

test('table renders expected catalog columns without date column and with consistently aligned actions header', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Prueba Columnas',
        'created_at' => now()->subDays(10),
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee(__('Producto o Servicio'))
        ->assertSee(__('Categoría'))
        ->assertSee(__('Precio'))
        ->assertSee(__('Duración'))
        ->assertSee(__('Estado'))
        ->assertSee(__('Acciones'))
        ->assertDontSee(__('Fecha'))
        ->assertDontSee($offering->created_at->format('d/m/Y'));
});

test('table renders formatted duration in minutes or dash when null', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Con Duración',
        'estimated_duration_minutes' => 45,
    ]);

    Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Sin Duración',
        'estimated_duration_minutes' => null,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('45 min')
        ->assertSee('-');
});

test('price of 0.00 is rendered as Gratis with subtle styling', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Offering::factory()->for($business)->free()->create([
        'category_id' => $category->id,
        'name' => 'Consulta de Valoración',
        'price' => 0.00,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Consulta de Valoración')
        ->assertSee('Gratis');
});

test('positive price is formatted as currency with two decimal places', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Tratamiento Facial Deluxe',
        'price' => 75000.50,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Tratamiento Facial Deluxe')
        ->assertSee('$75,000.50');
});

test('active offerings render cleanly without badges while inactive offerings display inactive badge', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    $activeOffering = Offering::factory()->for($business)->active()->create([
        'category_id' => $category->id,
        'name' => 'Servicio Activo',
    ]);

    $inactiveOffering = Offering::factory()->for($business)->inactive()->create([
        'category_id' => $category->id,
        'name' => 'Servicio Desactivado',
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Servicio Activo')
        ->assertSee('Activo')
        ->assertSee('Servicio Desactivado')
        ->assertSee('Inactivo');
});

test('search input filters offerings by name in real time with sanitized input', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    $haircut = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Corte de Barba Clásica',
    ]);

    $manicure = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Manicura Semipermanente',
    ]);

    // Initial state: both are visible
    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Corte de Barba Clásica')
        ->assertSee('Manicura Semipermanente');

    // Case-insensitive search with leading/trailing spaces
    $component->set('search', '   corte   ')
        ->assertSee('Corte de Barba Clásica')
        ->assertDontSee('Manicura Semipermanente');

    // Search for manicure
    $component->set('search', 'manicura')
        ->assertSee('Manicura Semipermanente')
        ->assertDontSee('Corte de Barba Clásica');
});

test('category dropdown lists only active and non-deleted categories', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $activeCategory = Category::factory()->active()->create(['name' => 'Peluquería Activa']);
    $inactiveCategory = Category::factory()->inactive()->create(['name' => 'Spa Inactivo Oculto']);
    $trashedCategory = Category::factory()->trashed()->create(['name' => 'Categoría Eliminada']);

    // Create at least one offering so catalog table/filters are rendered
    Offering::factory()->for($business)->create(['category_id' => $activeCategory->id]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Peluquería Activa')
        ->assertDontSee('Spa Inactivo Oculto')
        ->assertDontSee('Categoría Eliminada');
});

test('category filter restricts offerings to selected category', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $categoryBarber = Category::factory()->active()->create(['name' => 'Barbería']);
    $categorySpa = Category::factory()->active()->create(['name' => 'Spa & Bienestar']);

    $barberOffering = Offering::factory()->for($business)->create([
        'category_id' => $categoryBarber->id,
        'name' => 'Afeitado Tradicional',
    ]);

    $spaOffering = Offering::factory()->for($business)->create([
        'category_id' => $categorySpa->id,
        'name' => 'Masaje Descontracturante',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Afeitado Tradicional')
        ->assertSee('Masaje Descontracturante');

    // Filter by Barbería
    $component->set('category', (string) $categoryBarber->id)
        ->assertSee('Afeitado Tradicional')
        ->assertDontSee('Masaje Descontracturante');

    // Filter by Spa
    $component->set('category', (string) $categorySpa->id)
        ->assertSee('Masaje Descontracturante')
        ->assertDontSee('Afeitado Tradicional');
});

test('offering assigned to soft-deleted category renders gracefully without errors', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $category = Category::factory()->active()->create(['name' => 'Categoría que se archivará']);

    $offering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio con Categoría Archivada',
    ]);

    // Soft delete the category
    $category->delete();

    expect($category->fresh()->trashed())->toBeTrue();

    // Renders safely without 500 error, displaying category name
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertOk()
        ->assertSee('Servicio con Categoría Archivada')
        ->assertSee('Categoría que se archivará');
});

test('status filter restricts offerings by all, active, and inactive', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    $activeOffering = Offering::factory()->for($business)->active()->create([
        'category_id' => $category->id,
        'name' => 'Servicio Siempre Disponible',
    ]);

    $inactiveOffering = Offering::factory()->for($business)->inactive()->create([
        'category_id' => $category->id,
        'name' => 'Servicio Temporalmente Pausado',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    // Default 'all' sees both
    $component->assertSee('Servicio Siempre Disponible')
        ->assertSee('Servicio Temporalmente Pausado');

    // Filter 'active'
    $component->set('status', 'active')
        ->assertSee('Servicio Siempre Disponible')
        ->assertDontSee('Servicio Temporalmente Pausado');

    // Filter 'inactive'
    $component->set('status', 'inactive')
        ->assertSee('Servicio Temporalmente Pausado')
        ->assertDontSee('Servicio Siempre Disponible');
});

test('pagination resets to page 1 when search or filters change', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $categoryA = Category::factory()->active()->create();
    $categoryB = Category::factory()->active()->create();

    // Create 15 offerings so we have 2 pages (perPage = 10)
    Offering::factory()->count(15)->for($business)->create([
        'category_id' => $categoryA->id,
        'is_active' => true,
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->call('setPage', 2);

    expect($component->get('paginators.page'))->toBe(2);

    // Changing search resets page to 1
    $component->set('search', 'keyword');
    expect($component->get('paginators.page'))->toBe(1);

    // Set back to page 2 and change category
    $component->call('setPage', 2);
    expect($component->get('paginators.page'))->toBe(2);

    $component->set('category', (string) $categoryB->id);
    expect($component->get('paginators.page'))->toBe(1);

    // Set back to page 2 and change status
    $component->call('setPage', 2);
    expect($component->get('paginators.page'))->toBe(2);

    $component->set('status', 'inactive');
    expect($component->get('paginators.page'))->toBe(1);
});

test('sorting toggles by name and creation date with directional indicators', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    $offeringA = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'AAA Offering',
        'created_at' => now()->subDays(5),
    ]);

    $offeringZ = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'ZZZ Offering',
        'created_at' => now(),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index');

    // Initial default: sortField = created_at, sortDirection = desc (newest first)
    expect($component->get('sortField'))->toBe('created_at')
        ->and($component->get('sortDirection'))->toBe('desc')
        ->and($component->get('offerings')->pluck('name')->values()->all())->toBe(['ZZZ Offering', 'AAA Offering']);

    // Sort by name (first click defaults to asc)
    $component->call('sortBy', 'name');
    expect($component->get('sortField'))->toBe('name')
        ->and($component->get('sortDirection'))->toBe('asc')
        ->and($component->get('offerings')->pluck('name')->values()->all())->toBe(['AAA Offering', 'ZZZ Offering']);

    // Sort by name again (toggles to desc)
    $component->call('sortBy', 'name');
    expect($component->get('sortField'))->toBe('name')
        ->and($component->get('sortDirection'))->toBe('desc')
        ->and($component->get('offerings')->pluck('name')->values()->all())->toBe(['ZZZ Offering', 'AAA Offering']);

    // Sort by created_at (first click defaults to asc: oldest first)
    $component->call('sortBy', 'created_at');
    expect($component->get('sortField'))->toBe('created_at')
        ->and($component->get('sortDirection'))->toBe('asc')
        ->and($component->get('offerings')->pluck('name')->values()->all())->toBe(['AAA Offering', 'ZZZ Offering']);

    // Sort by created_at again (toggles to desc: newest first)
    $component->call('sortBy', 'created_at');
    expect($component->get('sortField'))->toBe('created_at')
        ->and($component->get('sortDirection'))->toBe('desc')
        ->and($component->get('offerings')->pluck('name')->values()->all())->toBe(['ZZZ Offering', 'AAA Offering']);
});

test('initial empty state renders when business has no offerings with link to create', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    expect($business->offerings()->count())->toBe(0);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Catálogo comercial')
        ->assertSee('Registra tus servicios y productos comerciales para que los clientes puedan descubrirlos y seleccionarlos al unirse a tus turnos virtuales.')
        ->assertSee('Crear mi primer servicio')
        ->assertSee(route('business.offerings.create'))
        ->assertDontSee('Buscar por nombre...');
});

test('filtered empty state renders when query matches zero records with reset filters action', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Existente',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.index')
        ->assertSee('Servicio Existente')
        ->assertDontSee('No se encontraron resultados');

    // Search for something that does not exist
    $component->set('search', 'NonExistentTermXYZ123')
        ->assertSee('No se encontraron resultados')
        ->assertSee('No se encontraron productos o servicios que coincidan con tus criterios.')
        ->assertSee('Restablecer filtros')
        ->assertDontSee('Servicio Existente');

    // Call resetFilters action
    $component->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('category', '')
        ->assertSet('status', 'all')
        ->assertSet('sortField', 'created_at')
        ->assertSet('sortDirection', 'desc')
        ->assertSee('Servicio Existente')
        ->assertDontSee('No se encontraron resultados');
});

test('query string parameters are synchronized via Url attributes', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create(['name' => 'Barbería']);

    Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Corte Especial',
        'is_active' => false,
    ]);

    Livewire::actingAs($business, 'business')
        ->withQueryParams([
            'search' => 'Especial',
            'category' => (string) $category->id,
            'status' => 'inactive',
            'sortField' => 'name',
            'sortDirection' => 'asc',
        ])
        ->test('pages::business.offerings.index')
        ->assertSet('search', 'Especial')
        ->assertSet('category', (string) $category->id)
        ->assertSet('status', 'inactive')
        ->assertSet('sortField', 'name')
        ->assertSet('sortDirection', 'asc')
        ->assertSee('Corte Especial');
});
