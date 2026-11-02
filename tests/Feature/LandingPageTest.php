<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Customer;

test('guest can view landing page with call to action and registration links', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Solicitar Turno')
        ->assertSee('Registrar mi Negocio')
        ->assertDontSee('¿Ya registraste tu empresa?')
        ->assertDontSee('Inicia sesión aquí')
        ->assertSee(route('business.register'))
        ->assertSee(route('business.login'))
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('guest can view navbar dropdown menus for login and registration with specified options', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Iniciar sesión')
        ->assertSee('Registrarse')
        ->assertSee('Como Cliente')
        ->assertSee('Como Negocio')
        ->assertSee('Cuenta Personal')
        ->assertSee('Registrar Negocio')
        ->assertSee(route('login'))
        ->assertSee(route('business.login'))
        ->assertSee(route('register'))
        ->assertSee(route('business.register'));
});

test('authenticated customer sees customer panel link on landing page', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Mi Panel')
        ->assertSee(route('dashboard'));
});

test('authenticated business owner sees business panel link on landing page', function (): void {
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Panel de Negocio')
        ->assertSee(route('dashboard'));
});
