<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

test('customer layout renders successfully with customer context and navigation', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'first_name' => 'Valentina',
        'last_name' => 'Gomez',
        'email' => 'valentina.gomez@turnex.test',
    ]);

    Auth::guard('customer')->login($customer);

    $view = $this->blade(
        '<x-layouts::customer :title="$title"><div>Contenido de prueba</div></x-layouts::customer>',
        ['title' => 'Mis Turnos']
    );

    $view->assertSee('Contenido de prueba');
    $view->assertSee('Mis Turnos - '.config('app.name', 'Laravel'));
    $view->assertSee('Explorar');
    $view->assertSee('Mis Turnos');
    $view->assertSee('Perfil');
    $view->assertSee('Valentina Gomez');
    $view->assertSee('Cerrar Sesión');
    $view->assertSee(route('customer.logout'));
    $view->assertSee('safe-bottom');
});

test('customer layout renders fallback initials when guest', function (): void {
    Auth::guard('customer')->logout();

    $view = $this->blade(
        '<x-layouts::customer><div>Invitado</div></x-layouts::customer>'
    );

    $view->assertSee('Invitado');
    $view->assertSee('Explorar');
    $view->assertSee('Mis Turnos');
    $view->assertSee('Perfil');
});

test('customer logout post route invalidates session and redirects to customer login', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer');
    expect(Auth::guard('customer')->check())->toBeTrue();

    $response = $this->post(route('customer.logout'));

    $response->assertRedirect(route('customer.login'));
    expect(Auth::guard('customer')->check())->toBeFalse();
});

test('customer layout highlights active route in desktop and mobile bottom navigation', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.tickets'))
        ->assertOk()
        ->assertSee('data-test="mobile-nav-tickets"', false)
        ->assertSee('data-test="mobile-nav-explore"', false)
        ->assertSee('data-test="mobile-nav-profile"', false)
        ->assertSee('text-brand-primary');
});
