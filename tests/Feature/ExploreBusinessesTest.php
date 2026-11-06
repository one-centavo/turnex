<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('4.1. Public & Customer Access Tests', function (): void {
    test('guest visitor can access explore directory without authentication', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Restaurante Central',
        ]);
        Offering::factory()->active()->create([
            'business_id' => $business->id,
        ]);

        $response = $this->get(route('explore'));

        $response->assertOk();
        $response->assertSee('Explora Establecimientos');
        $response->assertSee('Descubre comercios verificados');
        $response->assertSee('Restaurante Central');
        $response->assertSee('data-test="explore-search-input"', false);
        $response->assertSee('data-test="customer-login-link"', false);
        $response->assertSee('data-test="customer-register-link"', false);
        $response->assertSee('Iniciar sesión');
        $response->assertSee('Registrarse');
    });

    test('authenticated customer can access explore directory and sees customer context', function (): void {
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
            'email' => 'carlos.mendoza@turnex.test',
        ]);

        $business = Business::factory()->approved()->create([
            'business_name' => 'Café Gourmet',
        ]);
        Offering::factory()->active()->create([
            'business_id' => $business->id,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('explore'));

        $response->assertOk();
        $response->assertSee('Explora Establecimientos');
        $response->assertSee('Café Gourmet');
        $response->assertSee('Carlos Mendoza');
        $response->assertSee('Cerrar Sesión');
        $response->assertDontSee('data-test="customer-login-link"', false);
    });
});

describe('4.2. Business Approval & Offering Exclusion Tests', function (): void {
    test('approved business with active offering appears in directory', function (): void {
        $approvedBusiness = Business::factory()->approved()->create([
            'business_name' => 'Supermercado Los Andes',
        ]);
        Offering::factory()->active()->create([
            'business_id' => $approvedBusiness->id,
        ]);

        Livewire::test('pages::explore.index')
            ->assertSee('Supermercado Los Andes');
    });

    test('unapproved businesses are excluded from directory', function (): void {
        $pendingBusiness = Business::factory()->create([
            'business_name' => 'Negocio Pendiente',
            'verification_status' => VerificationStatus::PendingVerification,
        ]);
        Offering::factory()->active()->create(['business_id' => $pendingBusiness->id]);

        $underReviewBusiness = Business::factory()->underReview()->create([
            'business_name' => 'Negocio En Revision',
        ]);
        Offering::factory()->active()->create(['business_id' => $underReviewBusiness->id]);

        $actionRequiredBusiness = Business::factory()->actionRequired()->create([
            'business_name' => 'Negocio Accion Requerida',
        ]);
        Offering::factory()->active()->create(['business_id' => $actionRequiredBusiness->id]);

        $rejectedBusiness = Business::factory()->rejected()->create([
            'business_name' => 'Negocio Rechazado',
        ]);
        Offering::factory()->active()->create(['business_id' => $rejectedBusiness->id]);

        $approvedBusiness = Business::factory()->approved()->create([
            'business_name' => 'Negocio Verificado OK',
        ]);
        Offering::factory()->active()->create(['business_id' => $approvedBusiness->id]);

        Livewire::test('pages::explore.index')
            ->assertSee('Negocio Verificado OK')
            ->assertDontSee('Negocio Pendiente')
            ->assertDontSee('Negocio En Revision')
            ->assertDontSee('Negocio Accion Requerida')
            ->assertDontSee('Negocio Rechazado');
    });

    test('approved business with zero offerings is excluded from directory', function (): void {
        $businessWithoutOfferings = Business::factory()->approved()->create([
            'business_name' => 'Tienda Vacia Sin Productos',
        ]);

        $businessWithOfferings = Business::factory()->approved()->create([
            'business_name' => 'Tienda Con Productos',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessWithOfferings->id]);

        Livewire::test('pages::explore.index')
            ->assertSee('Tienda Con Productos')
            ->assertDontSee('Tienda Vacia Sin Productos');
    });

    test('approved business with only inactive offerings is excluded from directory', function (): void {
        $businessWithInactiveOfferings = Business::factory()->approved()->create([
            'business_name' => 'Comercio Inactivo Solo',
        ]);
        Offering::factory()->inactive()->create([
            'business_id' => $businessWithInactiveOfferings->id,
        ]);

        $businessWithActiveOfferings = Business::factory()->approved()->create([
            'business_name' => 'Comercio Activo Total',
        ]);
        Offering::factory()->active()->create([
            'business_id' => $businessWithActiveOfferings->id,
        ]);

        Livewire::test('pages::explore.index')
            ->assertSee('Comercio Activo Total')
            ->assertDontSee('Comercio Inactivo Solo');
    });

    test('approved business with only soft-deleted offerings is excluded from directory', function (): void {
        $businessWithDeletedOfferings = Business::factory()->approved()->create([
            'business_name' => 'Comercio Oferta Eliminada',
        ]);
        $offering = Offering::factory()->active()->create([
            'business_id' => $businessWithDeletedOfferings->id,
        ]);
        $offering->delete();

        $businessWithActiveOffering = Business::factory()->approved()->create([
            'business_name' => 'Comercio Oferta Valida',
        ]);
        Offering::factory()->active()->create([
            'business_id' => $businessWithActiveOffering->id,
        ]);

        Livewire::test('pages::explore.index')
            ->assertSee('Comercio Oferta Valida')
            ->assertDontSee('Comercio Oferta Eliminada');
    });
});

describe('4.3. Real-Time Search Tests', function (): void {
    test('search by commercial name matches case-insensitively and partial keywords', function (): void {
        $businessA = Business::factory()->approved()->create([
            'business_name' => 'Cafetería La Palma',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessA->id]);

        $businessB = Business::factory()->approved()->create([
            'business_name' => 'Panadería El Trigo Dorado',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessB->id]);

        Livewire::test('pages::explore.index')
            ->set('search', 'cafetería')
            ->assertSee('Cafetería La Palma')
            ->assertDontSee('Panadería El Trigo Dorado')
            ->set('search', 'PALMA')
            ->assertSee('Cafetería La Palma')
            ->assertDontSee('Panadería El Trigo Dorado')
            ->set('search', 'trigo')
            ->assertSee('Panadería El Trigo Dorado')
            ->assertDontSee('Cafetería La Palma');
    });

    test('search by physical location matches business', function (): void {
        $businessMedellin = Business::factory()->approved()->create([
            'business_name' => 'Barbería Alpha',
            'location' => 'Calle 10 # 45-20, Poblado, Medellín',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessMedellin->id]);

        $businessBogota = Business::factory()->approved()->create([
            'business_name' => 'Barbería Beta',
            'location' => 'Carrera 7 # 32-10, Chapinero, Bogotá',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessBogota->id]);

        Livewire::test('pages::explore.index')
            ->set('search', 'Medellín')
            ->assertSee('Barbería Alpha')
            ->assertDontSee('Barbería Beta')
            ->set('search', 'Chapinero')
            ->assertSee('Barbería Beta')
            ->assertDontSee('Barbería Alpha');
    });

    test('search input trims leading and trailing whitespace and ignores whitespace-only strings', function (): void {
        $businessA = Business::factory()->approved()->create([
            'business_name' => 'Heladería Polar',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessA->id]);

        $businessB = Business::factory()->approved()->create([
            'business_name' => 'Pizzería Napolitana',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessB->id]);

        Livewire::test('pages::explore.index')
            ->set('search', '   ')
            ->assertSee('Heladería Polar')
            ->assertSee('Pizzería Napolitana')
            ->set('search', '   Polar   ')
            ->assertSee('Heladería Polar')
            ->assertDontSee('Pizzería Napolitana');
    });

    test('search safely handles special SQL characters without syntax errors or unexpected wildcard expansion', function (): void {
        $businessSpecial1 = Business::factory()->approved()->create([
            'business_name' => '100% Natural Fresh',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessSpecial1->id]);

        $businessSpecial2 = Business::factory()->approved()->create([
            'business_name' => 'Spa_Relax VIP',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessSpecial2->id]);

        $businessStandard = Business::factory()->approved()->create([
            'business_name' => 'Spa Relax General',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessStandard->id]);

        Livewire::test('pages::explore.index')
            ->set('search', '100%')
            ->assertSee('100% Natural Fresh')
            ->assertDontSee('Spa Relax General')
            ->set('search', 'Spa_Relax')
            ->assertSee('Spa_Relax VIP')
            ->assertDontSee('Spa Relax General')
            ->set('search', "' OR '1'='1")
            ->assertSee('No se encontraron negocios')
            ->set('search', '\\')
            ->assertSee('No se encontraron negocios');
    });
});

describe('4.4. Category Filter Tests', function (): void {
    test('selecting category chip filters results exclusively to that category', function (): void {
        $gastro = Business::factory()->approved()->create([
            'business_name' => 'Restaurante Gourmet',
            'business_category' => BusinessCategory::Restaurant,
        ]);
        Offering::factory()->active()->create(['business_id' => $gastro->id]);

        $beauty = Business::factory()->approved()->create([
            'business_name' => 'Salón de Belleza Glamour',
            'business_category' => BusinessCategory::HealthAndBeauty,
        ]);
        Offering::factory()->active()->create(['business_id' => $beauty->id]);

        $retail = Business::factory()->approved()->create([
            'business_name' => 'Tienda Los Andes',
            'business_category' => BusinessCategory::Retail,
        ]);
        Offering::factory()->active()->create(['business_id' => $retail->id]);

        Livewire::test('pages::explore.index')
            ->call('setCategory', BusinessCategory::Restaurant->value)
            ->assertSet('category', BusinessCategory::Restaurant->value)
            ->assertSee('Restaurante Gourmet')
            ->assertDontSee('Salón de Belleza Glamour')
            ->assertDontSee('Tienda Los Andes')
            ->call('setCategory', BusinessCategory::HealthAndBeauty->value)
            ->assertSet('category', BusinessCategory::HealthAndBeauty->value)
            ->assertSee('Salón de Belleza Glamour')
            ->assertDontSee('Restaurante Gourmet')
            ->assertDontSee('Tienda Los Andes');
    });

    test('selecting empty string or toggling category clears filter and displays all categories', function (): void {
        $gastro = Business::factory()->approved()->create([
            'business_name' => 'Restaurante La Fontana',
            'business_category' => BusinessCategory::Restaurant,
        ]);
        Offering::factory()->active()->create(['business_id' => $gastro->id]);

        $beauty = Business::factory()->approved()->create([
            'business_name' => 'Estética Radiante',
            'business_category' => BusinessCategory::HealthAndBeauty,
        ]);
        Offering::factory()->active()->create(['business_id' => $beauty->id]);

        Livewire::test('pages::explore.index')
            ->call('setCategory', BusinessCategory::Restaurant->value)
            ->assertSet('category', BusinessCategory::Restaurant->value)
            ->assertSee('Restaurante La Fontana')
            ->assertDontSee('Estética Radiante')
            // Calling setCategory with empty string resets filter
            ->call('setCategory', '')
            ->assertSet('category', '')
            ->assertSee('Restaurante La Fontana')
            ->assertSee('Estética Radiante')
            // Toggling: selecting same category again clears it
            ->call('setCategory', BusinessCategory::HealthAndBeauty->value)
            ->assertSet('category', BusinessCategory::HealthAndBeauty->value)
            ->call('setCategory', BusinessCategory::HealthAndBeauty->value)
            ->assertSet('category', '')
            ->assertSee('Restaurante La Fontana')
            ->assertSee('Estética Radiante');
    });

    test('combining search query and category filter applies AND filtering accurately', function (): void {
        $cafeGastro = Business::factory()->approved()->create([
            'business_name' => 'Café Italiano Espresso',
            'business_category' => BusinessCategory::Restaurant,
        ]);
        Offering::factory()->active()->create(['business_id' => $cafeGastro->id]);

        $pizzaGastro = Business::factory()->approved()->create([
            'business_name' => 'Pizzería Trattoria',
            'business_category' => BusinessCategory::Restaurant,
        ]);
        Offering::factory()->active()->create(['business_id' => $pizzaGastro->id]);

        $cafeServices = Business::factory()->approved()->create([
            'business_name' => 'Café Internet y Copias',
            'business_category' => BusinessCategory::ProfessionalServices,
        ]);
        Offering::factory()->active()->create(['business_id' => $cafeServices->id]);

        Livewire::test('pages::explore.index')
            ->set('search', 'Café')
            ->call('setCategory', BusinessCategory::Restaurant->value)
            ->assertSee('Café Italiano Espresso')
            ->assertDontSee('Pizzería Trattoria')
            ->assertDontSee('Café Internet y Copias');
    });
});

describe('4.5. URL Synchronization & Pagination Reset Tests', function (): void {
    test('component mounts with q and categoria query parameters and filters businesses', function (): void {
        $gastro = Business::factory()->approved()->create([
            'business_name' => 'Café Especialidad',
            'business_category' => BusinessCategory::Restaurant,
        ]);
        Offering::factory()->active()->create(['business_id' => $gastro->id]);

        $retail = Business::factory()->approved()->create([
            'business_name' => 'Tienda Moda',
            'business_category' => BusinessCategory::Retail,
        ]);
        Offering::factory()->active()->create(['business_id' => $retail->id]);

        Livewire::withQueryParams([
            'q' => 'Especialidad',
            'categoria' => BusinessCategory::Restaurant->value,
        ])
            ->test('pages::explore.index')
            ->assertSet('search', 'Especialidad')
            ->assertSet('category', BusinessCategory::Restaurant->value)
            ->assertSee('Café Especialidad')
            ->assertDontSee('Tienda Moda');
    });

    test('component mounts with search and category query parameters and filters businesses', function (): void {
        $retail = Business::factory()->approved()->create([
            'business_name' => 'Boutique Exclusiva',
            'business_category' => BusinessCategory::Retail,
        ]);
        Offering::factory()->active()->create(['business_id' => $retail->id]);

        Livewire::withQueryParams([
            'search' => 'Boutique',
            'category' => BusinessCategory::Retail->value,
        ])
            ->test('pages::explore.index')
            ->assertSet('search', 'Boutique')
            ->assertSet('category', BusinessCategory::Retail->value)
            ->assertSee('Boutique Exclusiva');
    });

    test('changing search query while on page 2 resets pagination to page 1', function (): void {
        // Create 15 businesses so there are 2 pages (12 per page)
        $businesses = Business::factory()->count(15)->approved()->create();
        foreach ($businesses as $business) {
            Offering::factory()->active()->create(['business_id' => $business->id]);
        }

        $component = Livewire::test('pages::explore.index');

        $component->call('gotoPage', 2);
        expect($component->get('paginators')['page'] ?? 1)->toBe(2);

        $component->set('search', 'Term');
        expect($component->get('paginators')['page'] ?? 1)->toBe(1);
    });

    test('changing category filter while on page 2 resets pagination to page 1', function (): void {
        // Create 15 businesses
        $businesses = Business::factory()->count(15)->approved()->create([
            'business_category' => BusinessCategory::Restaurant,
        ]);
        foreach ($businesses as $business) {
            Offering::factory()->active()->create(['business_id' => $business->id]);
        }

        $component = Livewire::test('pages::explore.index');

        $component->call('gotoPage', 2);
        expect($component->get('paginators')['page'] ?? 1)->toBe(2);

        $component->call('setCategory', BusinessCategory::HealthAndBeauty->value);
        expect($component->get('paginators')['page'] ?? 1)->toBe(1);
    });
});

describe('4.6. Queue Occupancy & Active Turn Badge Tests', function (): void {
    test('business with 0 active tickets displays "Sin fila"', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Local Desocupado',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->assertSee('Local Desocupado')
            ->assertSee('Sin fila');
    });

    test('business with 1 active ticket displays "1 persona en espera"', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Local Con Una Persona',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        QueueTicket::factory()->pending()->create([
            'business_id' => $business->id,
        ]);

        Livewire::test('pages::explore.index')
            ->assertSee('Local Con Una Persona')
            ->assertSee('1 persona en espera');
    });

    test('business with 3 active tickets displays "3 personas en espera"', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Local Concurrido',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        QueueTicket::factory()->pending()->create(['business_id' => $business->id]);
        QueueTicket::factory()->preparing()->create(['business_id' => $business->id]);
        QueueTicket::factory()->ready()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->assertSee('Local Concurrido')
            ->assertSee('3 personas en espera');
    });

    test('terminal tickets are not counted towards active queue occupancy', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Local Con Turnos Terminales',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        // 1 active ticket
        QueueTicket::factory()->pending()->create(['business_id' => $business->id]);

        // 3 terminal tickets (Completed, NoShow, Cancelled)
        QueueTicket::factory()->completed()->create(['business_id' => $business->id]);
        QueueTicket::factory()->noShow()->create(['business_id' => $business->id]);
        QueueTicket::factory()->cancelled()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->assertSee('Local Con Turnos Terminales')
            ->assertSee('1 persona en espera')
            ->assertDontSee('4 personas en espera');
    });

    test('authenticated customer holding active ticket sees "Turno activo" badge on that business card', function (): void {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();

        $businessWithTicket = Business::factory()->approved()->create([
            'business_name' => 'Restaurante Con Mi Turno',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessWithTicket->id]);

        $businessWithoutTicket = Business::factory()->approved()->create([
            'business_name' => 'Restaurante Sin Mi Turno',
        ]);
        Offering::factory()->active()->create(['business_id' => $businessWithoutTicket->id]);

        // Customer has active ticket at businessWithTicket
        QueueTicket::factory()->pending()->create([
            'customer_id' => $customer->id,
            'business_id' => $businessWithTicket->id,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test('pages::explore.index')
            ->assertSee('Turno activo')
            ->assertSee('data-test="active-turn-badge-'.$businessWithTicket->id.'"', false)
            ->assertDontSee('data-test="active-turn-badge-'.$businessWithoutTicket->id.'"', false);
    });

    test('customer with only completed or cancelled tickets does not see "Turno activo" badge', function (): void {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();

        $business = Business::factory()->approved()->create([
            'business_name' => 'Local Con Turno Finalizado',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        QueueTicket::factory()->completed()->create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
        ]);
        QueueTicket::factory()->cancelled()->create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test('pages::explore.index')
            ->assertDontSee('data-test="active-turn-badge-'.$business->id.'"', false)
            ->assertDontSee('Turno activo');
    });

    test('guest visitor never sees "Turno activo" badge', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Local General',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        QueueTicket::factory()->pending()->create([
            'business_id' => $business->id,
        ]);

        Livewire::test('pages::explore.index')
            ->assertDontSee('data-test="active-turn-badge-'.$business->id.'"', false)
            ->assertDontSee('Turno activo');
    });
});

describe('4.7. Empty State & Fallback Asset Tests', function (): void {
    test('non-matching search query displays empty state and reset button', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Café Del Sol',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->set('search', 'palabra-inexistente-xyz-123')
            ->assertSee('No se encontraron negocios')
            ->assertSee('Intenta ajustar tu búsqueda o limpiar los filtros seleccionados.')
            ->assertSee('Limpiar filtros')
            ->assertSee('data-test="explore-empty-state"', false)
            ->assertSee('data-test="explore-reset-filters-button"', false)
            ->assertDontSee('Café Del Sol');
    });

    test('resetFilters action resets search and category properties and restores listing', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Zapatería Elegante',
            'business_category' => BusinessCategory::Retail,
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->set('search', 'palabra-inexistente')
            ->call('setCategory', BusinessCategory::Restaurant->value)
            ->assertSee('No se encontraron negocios')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('category', '')
            ->assertSee('Zapatería Elegante')
            ->assertDontSee('No se encontraron negocios');
    });

    test('business card renders fallback monogram initials and banner when photos are null', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Barbería Moderna',
            'cover_photo_path' => null,
            'logo_path' => null,
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->assertSee('Barbería Moderna')
            ->assertSee($business->initials())
            ->assertDontSee('src="'.Storage::url('').'"', false);
    });

    test('business card renders custom cover photo and logo when paths are present', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Restaurante Bella Vista',
            'cover_photo_path' => 'businesses/covers/cover-sample.jpg',
            'logo_path' => 'businesses/logos/logo-sample.jpg',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->assertSee(Storage::url('businesses/covers/cover-sample.jpg'))
            ->assertSee(Storage::url('businesses/logos/logo-sample.jpg'));
    });

    test('business card links to storefront public route', function (): void {
        $business = Business::factory()->approved()->create([
            'business_name' => 'Óptica Visión Clara',
            'slug' => 'optica-vision-clara-01',
        ]);
        Offering::factory()->active()->create(['business_id' => $business->id]);

        Livewire::test('pages::explore.index')
            ->assertSee(route('storefront.show', ['slug' => 'optica-vision-clara-01']))
            ->assertSee('data-test="storefront-cta-'.$business->id.'"', false);
    });
});

describe('4.8. Multi-Page Pagination Tests', function (): void {
    test('with more than 12 approved businesses, results are paginated across multiple pages', function (): void {
        // Create 15 approved businesses with sequential naming
        $businesses = collect();
        for ($i = 1; $i <= 15; $i++) {
            $business = Business::factory()->approved()->create([
                'business_name' => sprintf('Negocio Test %02d', $i),
            ]);
            Offering::factory()->active()->create(['business_id' => $business->id]);
            $businesses->push($business);
        }

        $component = Livewire::test('pages::explore.index')
            ->assertSee('data-test="explore-pagination"', false)
            ->assertSee('Negocio Test 01')
            ->assertSee('Negocio Test 12')
            ->assertDontSee('Negocio Test 13')
            ->assertDontSee('Negocio Test 15');

        // Navigate to page 2
        $component->call('gotoPage', 2)
            ->assertSee('Negocio Test 13')
            ->assertSee('Negocio Test 15')
            ->assertDontSee('Negocio Test 01')
            ->assertDontSee('Negocio Test 12');
    });
});
