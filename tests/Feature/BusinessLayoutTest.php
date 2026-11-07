<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Support\Facades\Auth;

test('business layout renders navigation, brand mark, and bottom navigation bar', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'business_name' => 'Café Central',
        'email' => 'cafe@turnex.test',
    ]);

    Auth::guard('business')->login($business);

    $view = $this->blade(
        '<x-layouts::business :title="$title"><div>Panel de control</div></x-layouts::business>',
        ['title' => 'Gestión de Turnos']
    );

    $view->assertSee('Panel de control');
    $view->assertSee('Café Central');
    $view->assertSee('TURN');
    $view->assertSee('EX');
    $view->assertSee('T<span class="text-brand-primary">X</span>', false);
    $view->assertSee('data-test="business-mobile-nav-queue"', false);
    $view->assertSee('data-test="business-mobile-nav-dashboard"', false);
    $view->assertSee('data-test="business-mobile-nav-offerings"', false);
    $view->assertSee('data-test="business-mobile-nav-profile"', false);
    $view->assertSee('safe-bottom');
});

test('business live queue page includes mobile bottom navigation bar', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get(route('business.queue.index'))
        ->assertOk()
        ->assertSee('data-test="business-mobile-nav-queue"', false)
        ->assertSee('data-test="business-mobile-nav-dashboard"', false)
        ->assertSee('data-test="business-mobile-nav-offerings"', false)
        ->assertSee('data-test="business-mobile-nav-profile"', false);
});
