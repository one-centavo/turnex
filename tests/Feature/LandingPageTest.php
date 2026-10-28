<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Customer;

test('guest can view landing page with business and customer registration links', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Registrar mi Negocio')
        ->assertSee(route('business.register'))
        ->assertSee(route('business.login'))
        ->assertSee(route('login'))
        ->assertSee(route('register'));
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
