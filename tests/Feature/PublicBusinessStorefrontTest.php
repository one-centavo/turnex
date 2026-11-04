<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Services\StorefrontService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guest can view approved business storefront without authentication', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create([
        'business_name' => 'Cafetería La Palma',
        'slug' => 'cafeteria-la-palma',
    ]);

    $this->get(route('storefront.show', ['slug' => $business->slug]))
        ->assertOk()
        ->assertSee('Cafetería La Palma');
});

test('visiting storefront with non-existent slug returns 404', function (): void {
    $this->get(route('storefront.show', ['slug' => 'non-existent-slug-xyz-999']))
        ->assertNotFound();
});

test('storefront resolves slug case-insensitively', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create([
        'business_name' => 'Barbería Moderna',
        'slug' => 'barberia-moderna',
    ]);

    $this->get('/b/BARBERIA-MODERNA')
        ->assertOk()
        ->assertSee('Barbería Moderna');

    $this->get('/b/Barberia-Moderna')
        ->assertOk()
        ->assertSee('Barbería Moderna');
});

test('storefront service generates unique slug and handles collisions with incremental counters', function (): void {
    $service = app(StorefrontService::class);

    $firstSlug = $service->generateUniqueSlug('Salón de Belleza Glamour');
    expect($firstSlug)->toBe('salon-de-belleza-glamour');

    Business::factory()->create([
        'business_name' => 'Salón de Belleza Glamour',
        'slug' => $firstSlug,
    ]);

    $secondSlug = $service->generateUniqueSlug('Salón de Belleza Glamour');
    expect($secondSlug)->toBe('salon-de-belleza-glamour-1');

    Business::factory()->create([
        'business_name' => 'Salón de Belleza Glamour',
        'slug' => $secondSlug,
    ]);

    $thirdSlug = $service->generateUniqueSlug('Salón de Belleza Glamour');
    expect($thirdSlug)->toBe('salon-de-belleza-glamour-2');
});

test('guest visiting unapproved business sees inactive notice and cannot browse offerings', function (): void {
    /** @var Business $business */
    $business = Business::factory()->underReview()->create([
        'business_name' => 'Restaurante En Revisión',
        'slug' => 'restaurante-en-revision',
    ]);
    $category = Category::factory()->active()->create();
    $offering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Plato Secreto En Revisión',
        'is_active' => true,
    ]);

    $response = $this->get(route('storefront.show', ['slug' => $business->slug]));

    $response->assertOk()
        ->assertSee(__('Negocio no disponible'))
        ->assertSee(__('Este negocio se encuentra en proceso de verificación o no está disponible públicamente en este momento.'))
        ->assertDontSee($offering->name);
});

test('authenticated business owner can preview their own unapproved business with preview banner', function (): void {
    /** @var Business $business */
    $business = Business::factory()->underReview()->create([
        'business_name' => 'Mi Negocio En Revisión',
        'slug' => 'mi-negocio-en-revision',
    ]);
    $category = Category::factory()->active()->create();
    $offering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Exclusivo del Dueño',
        'is_active' => true,
    ]);

    $response = $this->actingAs($business, 'business')
        ->get(route('storefront.show', ['slug' => $business->slug]));

    $response->assertOk()
        ->assertSee(__('Modo Vista Previa: Tu negocio aún no está aprobado para el público general. Solo tú puedes ver esta página.'))
        ->assertSee($offering->name)
        ->assertDontSee(__('Negocio no disponible'));
});

test('authenticated business owner cannot preview another unapproved business', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->underReview()->create(['slug' => 'owner-business-a']);
    /** @var Business $businessB */
    $businessB = Business::factory()->underReview()->create([
        'business_name' => 'Negocio Ajeno B',
        'slug' => 'owner-business-b',
    ]);
    $category = Category::factory()->active()->create();
    $offeringB = Offering::factory()->for($businessB)->create([
        'category_id' => $category->id,
        'name' => 'Servicio Privado de B',
        'is_active' => true,
    ]);

    $response = $this->actingAs($businessA, 'business')
        ->get(route('storefront.show', ['slug' => $businessB->slug]));

    $response->assertOk()
        ->assertSee(__('Negocio no disponible'))
        ->assertDontSee(__('Modo Vista Previa: Tu negocio aún no está aprobado para el público general. Solo tú puedes ver esta página.'))
        ->assertDontSee($offeringB->name);
});

test('storefront renders business commercial name, category, location, and description', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create([
        'business_name' => 'Clínica Dental Sonrisas',
        'business_category' => BusinessCategory::HealthAndBeauty,
        'location' => 'Calle 100 # 15-20, Bogotá',
        'business_description' => 'Servicios odontológicos especializados y estética dental.',
        'slug' => 'clinica-dental-sonrisas',
    ]);

    $this->get(route('storefront.show', ['slug' => $business->slug]))
        ->assertOk()
        ->assertSee('Clínica Dental Sonrisas')
        ->assertSee(BusinessCategory::HealthAndBeauty->label())
        ->assertSee('Calle 100 # 15-20, Bogotá')
        ->assertSee('Servicios odontológicos especializados y estética dental.');
});

test('storefront renders fallback visuals when logo and cover photo are missing', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create([
        'business_name' => 'Óptica Visión',
        'logo_path' => null,
        'cover_photo_path' => null,
        'slug' => 'optica-vision',
    ]);

    $initials = strtoupper(substr($business->business_name, 0, 2));

    $this->get(route('storefront.show', ['slug' => $business->slug]))
        ->assertOk()
        ->assertSee($initials);
});

test('storefront renders uploaded logo and cover photo when present', function (): void {
    Storage::fake('public');

    $logoFile = UploadedFile::fake()->create('logo.png', 50, 'image/png');
    $logoPath = $logoFile->store('logos', 'public');

    $coverFile = UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg');
    $coverPath = $coverFile->store('covers', 'public');

    /** @var Business $business */
    $business = Business::factory()->approved()->create([
        'business_name' => 'Gimnasio Titanes',
        'logo_path' => $logoPath,
        'cover_photo_path' => $coverPath,
        'slug' => 'gimnasio-titanes',
    ]);

    $this->get(route('storefront.show', ['slug' => $business->slug]))
        ->assertOk()
        ->assertSee(Storage::url($logoPath))
        ->assertSee(Storage::url($coverPath));
});

test('storefront excludes inactive and soft deleted offerings from catalog', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create(['slug' => 'salon-de-unas']);
    $category = Category::factory()->active()->create();

    $activeOffering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Manicura Rusa Express',
        'is_active' => true,
    ]);

    $inactiveOffering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Pedicura Spa Desactivada',
        'is_active' => false,
    ]);

    $deletedOffering = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Esmaltado Eliminado',
        'is_active' => true,
    ]);
    $deletedOffering->delete();

    Livewire::test('pages::storefront.show', ['slug' => $business->slug])
        ->assertSee($activeOffering->name)
        ->assertDontSee($inactiveOffering->name)
        ->assertDontSee($deletedOffering->name);
});

test('storefront strictly isolates offerings between businesses', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->approved()->create(['slug' => 'pizzeria-napoli']);
    /** @var Business $businessB */
    $businessB = Business::factory()->approved()->create(['slug' => 'tacos-el-pastor']);

    $category = Category::factory()->active()->create();

    $pizza = Offering::factory()->for($businessA)->create([
        'category_id' => $category->id,
        'name' => 'Pizza Margherita Especial',
        'is_active' => true,
    ]);

    $taco = Offering::factory()->for($businessB)->create([
        'category_id' => $category->id,
        'name' => 'Taco de Barbacoa Doble',
        'is_active' => true,
    ]);

    Livewire::test('pages::storefront.show', ['slug' => $businessA->slug])
        ->assertSee($pizza->name)
        ->assertDontSee($taco->name);

    Livewire::test('pages::storefront.show', ['slug' => $businessB->slug])
        ->assertSee($taco->name)
        ->assertDontSee($pizza->name);
});

test('storefront formats price as Gratis when zero and displays formatted currency when positive', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create(['slug' => 'centro-medico']);
    $category = Category::factory()->active()->create();

    $freeService = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Valoración Médica Inicial',
        'price' => 0.00,
        'is_active' => true,
        'estimated_duration_minutes' => 20,
    ]);

    $paidService = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Terapia Física Completa',
        'price' => 45000.00,
        'is_active' => true,
        'estimated_duration_minutes' => 60,
    ]);

    Livewire::test('pages::storefront.show', ['slug' => $business->slug])
        ->assertSee($freeService->name)
        ->assertSee(__('Gratis'))
        ->assertSee('20 min')
        ->assertSee($paidService->name)
        ->assertSee('$ 45.000')
        ->assertSee('60 min');
});

test('visitor can filter offerings by category and reset filter', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create(['slug' => 'estetica-spa']);

    $categoryHair = Category::factory()->active()->create(['name' => 'Cuidado Capilar']);
    $categorySkin = Category::factory()->active()->create(['name' => 'Tratamientos Faciales']);

    $hairOffering = Offering::factory()->for($business)->create([
        'category_id' => $categoryHair->id,
        'name' => 'Hidratación con Keratina',
        'is_active' => true,
    ]);

    $skinOffering = Offering::factory()->for($business)->create([
        'category_id' => $categorySkin->id,
        'name' => 'Limpieza Facial Profunda',
        'is_active' => true,
    ]);

    $component = Livewire::test('pages::storefront.show', ['slug' => $business->slug])
        ->assertSee($hairOffering->name)
        ->assertSee($skinOffering->name);

    // Filter by Cuidado Capilar
    $component->call('selectCategory', $categoryHair->id)
        ->assertSet('selectedCategoryId', $categoryHair->id)
        ->assertSee($hairOffering->name)
        ->assertDontSee($skinOffering->name);

    // Filter by Tratamientos Faciales
    $component->call('selectCategory', $categorySkin->id)
        ->assertSet('selectedCategoryId', $categorySkin->id)
        ->assertSee($skinOffering->name)
        ->assertDontSee($hairOffering->name);

    // Reset to All
    $component->call('selectCategory', null)
        ->assertSet('selectedCategoryId', null)
        ->assertSee($hairOffering->name)
        ->assertSee($skinOffering->name);
});

test('category selector only displays categories having active offerings for the visited business', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->approved()->create(['slug' => 'business-categories-a']);
    /** @var Business $businessB */
    $businessB = Business::factory()->approved()->create(['slug' => 'business-categories-b']);

    $activeCategoryInA = Category::factory()->active()->create(['name' => 'Categoría Activa Negocio A']);
    $inactiveOnlyCategoryInA = Category::factory()->active()->create(['name' => 'Categoría Inactiva Negocio A']);
    $categoryOnlyInB = Category::factory()->active()->create(['name' => 'Categoría Exclusiva Negocio B']);

    Offering::factory()->for($businessA)->create([
        'category_id' => $activeCategoryInA->id,
        'is_active' => true,
    ]);

    Offering::factory()->for($businessA)->create([
        'category_id' => $inactiveOnlyCategoryInA->id,
        'is_active' => false,
    ]);

    Offering::factory()->for($businessB)->create([
        'category_id' => $categoryOnlyInB->id,
        'is_active' => true,
    ]);

    Livewire::test('pages::storefront.show', ['slug' => $businessA->slug])
        ->assertSee($activeCategoryInA->name)
        ->assertDontSee($inactiveOnlyCategoryInA->name)
        ->assertDontSee($categoryOnlyInB->name);
});

test('search input filters offerings by name reactively with whitespace trimming', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create(['slug' => 'barber-club']);
    $category = Category::factory()->active()->create();

    $fadeCut = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Corte Fade Degradado',
        'is_active' => true,
    ]);

    $beardTrim = Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Perfilado de Barba',
        'is_active' => true,
    ]);

    $component = Livewire::test('pages::storefront.show', ['slug' => $business->slug])
        ->assertSee($fadeCut->name)
        ->assertSee($beardTrim->name);

    // Search with whitespace
    $component->set('search', '   Fade   ')
        ->assertSee($fadeCut->name)
        ->assertDontSee($beardTrim->name);

    // Search for beard
    $component->set('search', 'Barba')
        ->assertSee($beardTrim->name)
        ->assertDontSee($fadeCut->name);
});

test('displays empty state when search yields no matches with reset filters option', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create(['slug' => 'ferreteria-el-tornillo']);
    $category = Category::factory()->active()->create();

    Offering::factory()->for($business)->create([
        'category_id' => $category->id,
        'name' => 'Martillo de Uña 16oz',
        'is_active' => true,
    ]);

    $component = Livewire::test('pages::storefront.show', ['slug' => $business->slug])
        ->assertSee('Martillo de Uña 16oz');

    // Search for non-existing term
    $component->set('search', 'TerminoInexistenteXYZ')
        ->assertDontSee('Martillo de Uña 16oz')
        ->assertSee(__('No encontramos productos o servicios que coincidan con tu búsqueda.'))
        ->assertSee(__('Prueba ajustando los términos de búsqueda o eliminando los filtros seleccionados.'))
        ->assertSee(__('Restablecer filtros'));

    // Reset filters
    $component->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('selectedCategoryId', null)
        ->assertSee('Martillo de Uña 16oz')
        ->assertDontSee(__('No encontramos productos o servicios que coincidan con tu búsqueda.'));
});

test('displays zero offerings empty state when business has no active offerings published', function (): void {
    /** @var Business $business */
    $business = Business::factory()->approved()->create(['slug' => 'libreria-central']);

    Livewire::test('pages::storefront.show', ['slug' => $business->slug])
        ->assertSee(__('Este negocio aún no tiene productos o servicios disponibles.'))
        ->assertSee(__('Vuelve a consultar más tarde para descubrir las novedades de este catálogo.'))
        ->assertDontSee(__('No encontramos productos o servicios que coincidan con tu búsqueda.'));
});

test('artisan business approve command changes status to Approved and sets reviewed at timestamp', function (): void {
    /** @var Business $business */
    $business = Business::factory()->underReview()->create([
        'business_name' => 'Pastelería Francesa',
        'reviewed_at' => null,
    ]);

    expect($business->verification_status)->toBe(VerificationStatus::UnderReview)
        ->and($business->reviewed_at)->toBeNull();

    $this->artisan('business:approve', ['id' => $business->id])
        ->assertSuccessful()
        ->expectsOutputToContain("Business [{$business->business_name}] (ID: {$business->id}) has been approved successfully.");

    $freshBusiness = $business->fresh();
    expect($freshBusiness->verification_status)->toBe(VerificationStatus::Approved)
        ->and($freshBusiness->reviewed_at)->not->toBeNull();
});

test('artisan business approve command fails gracefully when business id is invalid', function (): void {
    $this->artisan('business:approve', ['id' => 999999])
        ->assertFailed()
        ->expectsOutputToContain('Business with ID [999999] not found.');
});
