<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->business = Business::factory()->create([
        'verification_status' => VerificationStatus::Approved,
        'slug' => 'cafe-central',
    ]);

    $this->offering1 = Offering::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Café Americano',
        'price' => 5000.00,
        'is_active' => true,
    ]);

    $this->offering2 = Offering::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Croissant Artesanal',
        'price' => 8500.00,
        'is_active' => true,
    ]);
});

test('storefront allows adding and adjusting offering quantities and computes totals', function (): void {
    Livewire::test('pages::storefront.show', ['slug' => $this->business->slug])
        ->assertSet('selectedOfferings', [])
        ->assertSee('Agregar')
        ->call('addOffering', $this->offering1->id)
        ->assertSet('selectedOfferings', [$this->offering1->id => 1])
        ->assertSee('1 ítem seleccionado')
        ->call('incrementQuantity', $this->offering1->id)
        ->assertSet('selectedOfferings', [$this->offering1->id => 2])
        ->call('addOffering', $this->offering2->id)
        ->assertSet('selectedOfferings', [
            $this->offering1->id => 2,
            $this->offering2->id => 1,
        ])
        ->assertSee('3 ítems seleccionados')
        ->call('decrementQuantity', $this->offering1->id)
        ->assertSet('selectedOfferings', [
            $this->offering1->id => 1,
            $this->offering2->id => 1,
        ])
        ->call('decrementQuantity', $this->offering1->id)
        ->assertSet('selectedOfferings', [
            $this->offering2->id => 1,
        ])
        ->call('removeOffering', $this->offering2->id)
        ->assertSet('selectedOfferings', [])
        ->assertDontSee('Solicitar Turno');
});

test('storefront allows direct input updates and enforces quantity boundaries', function (): void {
    Livewire::test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->assertSet('selectedOfferings', [$this->offering1->id => 1])
        ->call('updateQuantity', $this->offering1->id, 10)
        ->assertSet('selectedOfferings', [$this->offering1->id => 10])
        ->call('updateQuantity', $this->offering1->id, 100)
        ->assertSet('selectedOfferings', [$this->offering1->id => 50])
        ->call('incrementQuantity', $this->offering1->id)
        ->assertSet('selectedOfferings', [$this->offering1->id => 50])
        ->call('updateQuantity', $this->offering1->id, -5)
        ->assertSet('selectedOfferings', [])
        ->call('addOffering', $this->offering1->id)
        ->call('updateQuantity', $this->offering1->id, 0)
        ->assertSet('selectedOfferings', []);
});

test('guest customer requesting turn is prompted to authenticate and ticket is not created', function (): void {
    Livewire::test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('requestTurn')
        ->assertSee('Debes iniciar sesión para solicitar un turno')
        ->assertSee(route('customer.login'))
        ->assertSee(route('customer.register'));

    expect(QueueTicket::count())->toBe(0);

    // Direct confirmTurn as guest also rejects
    Livewire::test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('confirmTurn')
        ->assertNoRedirect();

    expect(QueueTicket::count())->toBe(0);
});

test('authenticated customer can confirm turn and is redirected to ticket tracking page', function (): void {
    $customer = Customer::factory()->create();

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('incrementQuantity', $this->offering1->id) // 2x offering1 = 10,000
        ->call('addOffering', $this->offering2->id) // 1x offering2 = 8,500
        ->call('confirmTurn');

    $ticket = QueueTicket::query()
        ->where('business_id', $this->business->id)
        ->where('customer_id', $customer->id)
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->status)->toBe(QueueTicketStatus::Pending)
        ->and((float) $ticket->total_price)->toBe(18500.00)
        ->and($ticket->items)->toHaveCount(2);

    $component->assertRedirect(route('tickets.show', ['code' => $ticket->ticket_code]));
});

test('authenticated customer with active ticket cannot create another and sees active ticket modal', function (): void {
    $customer = Customer::factory()->create();

    $existingTicket = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'ticket_number' => 'TK-005',
        'status' => QueueTicketStatus::Pending,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('requestTurn')
        ->assertSet('existingTicketCode', $existingTicket->ticket_code)
        ->assertSet('existingTicketNumber', 'TK-005')
        ->assertSee('Ya tienes un turno activo')
        ->assertSee('TK-005')
        ->assertSee(route('tickets.show', $existingTicket->ticket_code));

    // Confirming turn directly also fails and does not create a new ticket
    Livewire::actingAs($customer, 'customer')
        ->test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('confirmTurn')
        ->assertNoRedirect();

    expect(QueueTicket::where('business_id', $this->business->id)->count())->toBe(1);
});

test('customer can have active tickets across different businesses', function (): void {
    $otherBusiness = Business::factory()->create([
        'verification_status' => VerificationStatus::Approved,
        'slug' => 'panaderia-norte',
    ]);

    $otherOffering = Offering::factory()->create([
        'business_id' => $otherBusiness->id,
        'price' => 3000.00,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create();

    // Active ticket in first business
    QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'status' => QueueTicketStatus::Pending,
    ]);

    // Customer can still request and confirm ticket in other business
    Livewire::actingAs($customer, 'customer')
        ->test('pages::storefront.show', ['slug' => $otherBusiness->slug])
        ->call('addOffering', $otherOffering->id)
        ->call('confirmTurn')
        ->assertRedirect();

    expect(QueueTicket::where('customer_id', $customer->id)->count())->toBe(2);
});
