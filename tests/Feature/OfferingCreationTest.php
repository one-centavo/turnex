<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('unauthenticated guest is redirected to business login when visiting create page', function (): void {
    $this->get(route('business.offerings.create'))
        ->assertRedirect(route('business.login'));
});

test('unauthenticated guest is redirected to business login when visiting offerings index', function (): void {
    $this->get(route('business.offerings.index'))
        ->assertRedirect(route('business.login'));
});

test('authenticated business user can view create offering page', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get(route('business.offerings.create'))
        ->assertOk()
        ->assertSee('Nuevo Producto o Servicio')
        ->assertSee('Nombre del servicio o producto')
        ->assertSee('Categoría')
        ->assertSee('Precio (COP)')
        ->assertSee('Duración estimada (minutos)')
        ->assertSee('Descripción')
        ->assertSee('Imagen de referencia');
});

test('offering create component displays only active and non-deleted categories', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $activeCategory = Category::factory()->active()->create(['name' => 'Active Barber Category']);
    $inactiveCategory = Category::factory()->inactive()->create(['name' => 'Inactive Spa Category']);
    $trashedCategory = Category::factory()->trashed()->create(['name' => 'Deleted Salon Category']);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->assertSee('Active Barber Category')
        ->assertDontSee('Inactive Spa Category')
        ->assertDontSee('Deleted Salon Category');
});

test('validation fails when required fields are empty', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->call('save')
        ->assertHasErrors(['name', 'category_id'])
        ->assertSee('El nombre del servicio es obligatorio.')
        ->assertSee('Debes seleccionar una categoría.');
});

test('name validation enforces boundary length constraints', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Less than 3 characters fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('category_id', $category->id)
        ->set('name', 'Ab')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('El nombre debe tener al menos 3 caracteres.');

    // Greater than 50 characters fails
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('category_id', $category->id)
        ->set('name', str_repeat('A', 51))
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('El nombre no puede superar los 50 caracteres.');

    // Exactly 3 characters passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('category_id', $category->id)
        ->set('name', 'Cbd')
        ->call('save')
        ->assertHasNoErrors(['name']);

    // Exactly 50 characters passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('category_id', $category->id)
        ->set('name', str_repeat('A', 50))
        ->call('save')
        ->assertHasNoErrors(['name']);
});

test('category validation rejects non-existent, inactive, or soft-deleted categories', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $inactiveCategory = Category::factory()->inactive()->create();
    $trashedCategory = Category::factory()->trashed()->create();
    $activeCategory = Category::factory()->active()->create();

    // Non-existent ID
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Servicio Test')
        ->set('category_id', 999999)
        ->call('save')
        ->assertHasErrors(['category_id'])
        ->assertSee('La categoría seleccionada no es válida o está inactiva.');

    // Inactive category
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Servicio Test')
        ->set('category_id', $inactiveCategory->id)
        ->call('save')
        ->assertHasErrors(['category_id'])
        ->assertSee('La categoría seleccionada no es válida o está inactiva.');

    // Soft-deleted category
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Servicio Test')
        ->set('category_id', $trashedCategory->id)
        ->call('save')
        ->assertHasErrors(['category_id'])
        ->assertSee('La categoría seleccionada no es válida o está inactiva.');

    // Active category passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Servicio Test')
        ->set('category_id', $activeCategory->id)
        ->call('save')
        ->assertHasNoErrors(['category_id']);
});

test('reference image validation rejects non-images, invalid mime types, and files over 10 MB', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Plain text file (not an image / invalid mime)
    $textFile = UploadedFile::fake()->create('notes.txt', 50, 'text/plain');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('image', $textFile)
        ->call('save')
        ->assertHasErrors(['image']);

    // PDF document
    $pdfFile = UploadedFile::fake()->create('document.pdf', 200, 'application/pdf');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('image', $pdfFile)
        ->call('save')
        ->assertHasErrors(['image']);

    // Disguised script (PHP script)
    $scriptFile = UploadedFile::fake()->create('script.png', 50, 'application/x-php');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('image', $scriptFile)
        ->call('save')
        ->assertHasErrors(['image']);

    // Exceeds 10 MB (10240 KB limit, test with 10241 KB)
    $oversizedImage = UploadedFile::fake()->create('huge.png', 10241, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('image', $oversizedImage)
        ->call('save')
        ->assertHasErrors(['image'])
        ->assertSee('La imagen no debe superar los 10 MB.');
});

test('reference image validation accepts valid PNG, JPG, and SVG within 10 MB', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Valid PNG
    $pngFile = UploadedFile::fake()->create('test.png', 200, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte PNG')
        ->set('category_id', $category->id)
        ->set('image', $pngFile)
        ->call('save')
        ->assertHasNoErrors(['image']);

    // Valid JPG
    $jpgFile = UploadedFile::fake()->create('test.jpg', 200, 'image/jpeg');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte JPG')
        ->set('category_id', $category->id)
        ->set('image', $jpgFile)
        ->call('save')
        ->assertHasNoErrors(['image']);

    // Valid SVG
    $svgFile = UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte SVG')
        ->set('category_id', $category->id)
        ->set('image', $svgFile)
        ->call('save')
        ->assertHasNoErrors(['image']);
});

test('price validation rejects negative values and amounts exceeding maximum', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Negative price
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('price', -10.00)
        ->call('save')
        ->assertHasErrors(['price'])
        ->assertSee('El precio no puede ser negativo.');

    // Exceeding maximum (99,999,999.99)
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('price', 100000000.00)
        ->call('save')
        ->assertHasErrors(['price'])
        ->assertSee('El precio no puede superar 99,999,999.99.');

    // Valid decimal price passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Clásico')
        ->set('category_id', $category->id)
        ->set('price', 45000.50)
        ->call('save')
        ->assertHasNoErrors(['price']);
});

test('price defaults to 0.00 when omitted or empty', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Servicio Sin Precio')
        ->set('category_id', $category->id)
        ->set('price', null)
        ->call('save')
        ->assertHasNoErrors();

    $offering = Offering::where('name', 'Servicio Sin Precio')->first();
    expect($offering)->not->toBeNull()
        ->and($offering->price)->toBe('0.00');
});

test('estimated duration validation rejects zero, negative values, and non-integers', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Zero minutes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Rápido')
        ->set('category_id', $category->id)
        ->set('estimated_duration_minutes', 0)
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSee('La duración estimada debe ser de al menos 1 minuto.');

    // Negative minutes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Rápido')
        ->set('category_id', $category->id)
        ->set('estimated_duration_minutes', -15)
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSee('La duración estimada debe ser de al menos 1 minuto.');

    // Non-integer (decimal)
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Rápido')
        ->set('category_id', $category->id)
        ->set('estimated_duration_minutes', '15.5')
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSee('La duración debe ser un número entero de minutos.');

    // Valid duration >= 1 passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Rápido')
        ->set('category_id', $category->id)
        ->set('estimated_duration_minutes', 30)
        ->call('save')
        ->assertHasNoErrors(['estimated_duration_minutes']);
});

test('description validation enforces 250 characters limit and accepts optional text', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Exceeding 250 characters
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Premium')
        ->set('category_id', $category->id)
        ->set('description', str_repeat('D', 251))
        ->call('save')
        ->assertHasErrors(['description'])
        ->assertSee('La descripción no puede superar los 250 caracteres.');

    // Exactly 250 characters passes
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Premium')
        ->set('category_id', $category->id)
        ->set('description', str_repeat('D', 250))
        ->call('save')
        ->assertHasNoErrors(['description']);
});

test('input sanitization trims whitespace, collapses internal spaces, and treats space-only names as empty', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    // Space-only name treated as empty and fails validation
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', '     ')
        ->set('category_id', $category->id)
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('El nombre del servicio es obligatorio.');

    // Extra spaces trimmed and collapsed
    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', '   Corte   de   Barba   Especial   ')
        ->set('category_id', $category->id)
        ->set('description', '   Descripción   con   múltiples   espacios.   ')
        ->call('save')
        ->assertHasNoErrors();

    $offering = Offering::where('business_id', $business->id)->latest('id')->first();
    expect($offering)->not->toBeNull()
        ->and($offering->name)->toBe('Corte de Barba Especial')
        ->and($offering->description)->toBe('Descripción con múltiples espacios.');
});

test('submitting invalid data displays error messages without clearing previously entered valid inputs', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Corte Válido')
        ->set('category_id', $category->id)
        ->set('price', '25000.00')
        ->set('estimated_duration_minutes', -5)
        ->set('description', 'Descripción que debe persistir')
        ->call('save')
        ->assertHasErrors(['estimated_duration_minutes'])
        ->assertSet('name', 'Corte Válido')
        ->assertSet('category_id', $category->id)
        ->assertSet('price', '25000.00')
        ->assertSet('description', 'Descripción que debe persistir');
});

test('created offering is strictly associated with the authenticated business tenant', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    /** @var Business $otherBusiness */
    $otherBusiness = Business::factory()->create();
    $category = Category::factory()->active()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Tratamiento Capilar')
        ->set('category_id', $category->id)
        ->call('save')
        ->assertHasNoErrors();

    $offering = Offering::where('name', 'Tratamiento Capilar')->first();
    expect($offering)->not->toBeNull()
        ->and($offering->business_id)->toBe($business->id)
        ->and($offering->business_id)->not->toBe($otherBusiness->id);

    $this->assertDatabaseHas('offerings', [
        'id' => $offering->id,
        'business_id' => $business->id,
        'name' => 'Tratamiento Capilar',
    ]);

    $this->assertDatabaseMissing('offerings', [
        'id' => $offering->id,
        'business_id' => $otherBusiness->id,
    ]);
});

test('successful offering creation stores file, creates record, flashes message, and redirects to catalog', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $category = Category::factory()->active()->create();
    $fakeImage = UploadedFile::fake()->create('service-preview.png', 300, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('name', 'Manicura Spa Completa')
        ->set('category_id', $category->id)
        ->set('price', '28000.00')
        ->set('estimated_duration_minutes', 45)
        ->set('description', 'Tratamiento relajante con exfoliación e hidratación profunda.')
        ->set('image', $fakeImage)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSessionHas('status', 'Service created successfully.')
        ->assertRedirect(route('business.offerings.index'));

    $offering = Offering::where('name', 'Manicura Spa Completa')->first();

    expect($offering)->not->toBeNull()
        ->and($offering->business_id)->toBe($business->id)
        ->and($offering->category_id)->toBe($category->id)
        ->and($offering->price)->toBe('28000.00')
        ->and($offering->estimated_duration_minutes)->toBe(45)
        ->and($offering->description)->toBe('Tratamiento relajante con exfoliación e hidratación profunda.')
        ->and($offering->is_active)->toBeTrue()
        ->and($offering->reference_image)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $offering->reference_image);
});

test('user can remove selected reference image preview before submitting', function (): void {
    Storage::fake('public');

    /** @var Business $business */
    $business = Business::factory()->create();
    $fakeImage = UploadedFile::fake()->create('preview-to-remove.png', 50, 'image/png');

    Livewire::actingAs($business, 'business')
        ->test('pages::business.offerings.create')
        ->set('image', $fakeImage)
        ->assertSet('image', fn ($value): bool => $value !== null)
        ->call('removeImage')
        ->assertSet('image', null);
});
