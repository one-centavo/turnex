<?php

use App\Models\Customer;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated customers are redirected to customer tickets hub from dashboard', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer, 'customer');

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('customer.tickets'));
});
