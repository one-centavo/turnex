<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('unauthenticated visitor is redirected to login', function (): void {
    $this->get(route('customer.tickets'))
        ->assertRedirect(route('login'));
});

test('authenticated customer defaults directly to the active turns tab', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.tickets'))
        ->assertOk();

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSet('tab', 'active')
        ->assertSee('Mis Turnos')
        ->assertSee('Turnos Activos')
        ->assertSee('Historial');
});

test('renders empty state when customer has no active tickets', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('No tienes turnos activos en este momento')
        ->assertSee('Explorar Locales');
});

test('displays concurrent active tickets across multiple businesses', function (): void {
    $customer = Customer::factory()->create();
    $businessA = Business::factory()->create(['business_name' => 'Café Central']);
    $businessB = Business::factory()->create(['business_name' => 'Barbería Elite']);

    $ticketA = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessA->id,
        'ticket_number' => 'TK-001',
        'total_price' => 15000.00,
    ]);

    $ticketB = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessB->id,
        'ticket_number' => 'TK-002',
        'total_price' => 25000.00,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('Café Central')
        ->assertSee('Barbería Elite')
        ->assertSee('TK-001')
        ->assertSee('TK-002')
        ->assertSee('$ 15.000')
        ->assertSee('$ 25.000');
});

test('prioritizes urgent tickets before pending and skipped tickets', function (): void {
    $customer = Customer::factory()->create();

    $pending = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'ticket_number' => 'TK-PENDING',
        'created_at' => now()->subMinutes(10),
    ]);

    $ready = QueueTicket::factory()->ready()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'ticket_number' => 'TK-READY',
        'created_at' => now()->subMinutes(5),
    ]);

    $called = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'ticket_number' => 'TK-CALLED',
        'created_at' => now()->subMinutes(2),
    ]);

    $skipped = QueueTicket::factory()->skipped()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'ticket_number' => 'TK-SKIPPED',
        'created_at' => now()->subMinutes(15),
    ]);

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets');

    $activeTickets = $component->get('activeTickets');

    expect($activeTickets->pluck('ticket_number')->all())->toBe([
        'TK-CALLED',
        'TK-READY',
        'TK-PENDING',
        'TK-SKIPPED',
    ]);
});

test('displays urgent callout banner when ticket status is called', function (): void {
    $customer = Customer::factory()->create();

    QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('¡Es tu turno! Acércate al módulo de atención');
});

test('displays ready banner when ticket status is ready', function (): void {
    $customer = Customer::factory()->create();

    QueueTicket::factory()->ready()->create([
        'customer_id' => $customer->id,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('¡Tu pedido está listo para recoger en el mostrador!');
});

test('displays informational notice when ticket status is skipped', function (): void {
    $customer = Customer::factory()->create();

    QueueTicket::factory()->skipped()->create([
        'customer_id' => $customer->id,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('Tu turno fue pospuesto. Por favor preséntate ante el personal del establecimiento');
});

test('displays queue proximity information for pending tickets', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create();

    // Customer with 0 turns ahead
    $ticket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('¡Eres el siguiente!');

    // Another customer ahead in the queue
    QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subHour(),
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('1 persona antes de ti');
});

test('displays resolution card and allows acknowledgment for tickets completed during session', function (): void {
    $customer = Customer::factory()->create();
    $ticket = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
    ]);

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets');

    // Simulate merchant completing the ticket in database
    $ticket->update(['status' => QueueTicketStatus::Completed]);

    $component->call('$refresh')
        ->assertSee('Tu turno ha finalizado con éxito.')
        ->assertSee('Entendido')
        ->call('acknowledgeTicket', $ticket->id)
        ->assertDontSee('Tu turno ha finalizado con éxito.')
        ->assertSee('No tienes turnos activos en este momento');
});

test('displays itemized order breakdown and tracking link', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create();

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'name' => 'Hamburguesa Especial',
        'price' => 22000.00,
    ]);

    $ticket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'total_price' => 44000.00,
    ]);

    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $ticket->id,
        'offering_id' => $offering->id,
        'quantity' => 2,
        'unit_price' => 22000.00,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('Hamburguesa Especial')
        ->assertSee('2x')
        ->assertSee('$ 22.000 c/u')
        ->assertSee('$ 44.000')
        ->assertSee('Ver detalle')
        ->assertSee('Ver en pantalla completa')
        ->assertSee(route('tickets.show', $ticket->ticket_code));
});

test('allows switching to history tab and renders empty state when no historical tickets', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->call('switchTab', 'history')
        ->assertSet('tab', 'history')
        ->assertSee('No tienes visitas o turnos pasados en tu historial');
});

test('renders paginated history view sorted chronologically descending', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create(['business_name' => 'Pastelería Francesa']);

    $olderTicket = QueueTicket::factory()->completed()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-OLD',
        'created_at' => now()->subDays(3),
    ]);

    $newerTicket = QueueTicket::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-NEW',
        'created_at' => now()->subHour(),
    ]);

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets', ['tab' => 'history'])
        ->assertSet('tab', 'history')
        ->assertSee('TK-NEW')
        ->assertSee('TK-OLD')
        ->assertSee('Pastelería Francesa');

    $historyTickets = $component->get('historyTickets');

    expect($historyTickets->first()->id)->toBe($newerTicket->id)
        ->and($historyTickets->last()->id)->toBe($olderTicket->id);
});

test('history view is resilient against soft-deleted offerings', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create(['business_name' => 'Panadería Tradicional']);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'name' => 'Croissant de Chocolate',
    ]);

    $ticket = QueueTicket::factory()->completed()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-ARCHIVED',
    ]);

    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $ticket->id,
        'offering_id' => $offering->id,
        'quantity' => 1,
        'unit_price' => 7500.00,
    ]);

    $offering->delete();

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets', ['tab' => 'history'])
        ->assertOk()
        ->assertSee('TK-ARCHIVED')
        ->assertSee('Panadería Tradicional');
});

test('simulates polling synchronization as ticket transitions from pending to called to completed', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create(['business_name' => 'Restaurante Gourmet']);

    $ticket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-SYNC',
    ]);

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('TK-SYNC')
        ->assertSee('Restaurante Gourmet')
        ->assertSee('En espera')
        ->assertDontSee('¡Es tu turno!')
        ->assertDontSee('Tu turno ha finalizado con éxito.');

    // Transition 1: Merchant calls customer
    $ticket->update(['status' => QueueTicketStatus::Called]);

    $component->call('$refresh')
        ->assertSee('TK-SYNC')
        ->assertSee('Llamado')
        ->assertSee('¡Es tu turno! Acércate al módulo de atención')
        ->assertDontSee('Tu turno ha finalizado con éxito.');

    // Transition 2: Merchant completes ticket
    $ticket->update(['status' => QueueTicketStatus::Completed]);

    $component->call('$refresh')
        ->assertSee('TK-SYNC')
        ->assertSee('Completado')
        ->assertSee('Tu turno ha finalizado con éxito.')
        ->assertSee('Entendido');

    // Customer acknowledges resolution
    $component->call('acknowledgeTicket', $ticket->id)
        ->assertDontSee('TK-SYNC')
        ->assertDontSee('Tu turno ha finalizado con éxito.')
        ->assertSee('No tienes turnos activos en este momento');
});

test('displays multiple concurrent active tickets across 3 or more businesses with correct urgency order', function (): void {
    $customer = Customer::factory()->create();

    $businessA = Business::factory()->create(['business_name' => 'Peluquería Alpha']);
    $businessB = Business::factory()->create(['business_name' => 'Cafetería Beta']);
    $businessC = Business::factory()->create(['business_name' => 'Farmacia Gamma']);
    $businessD = Business::factory()->create(['business_name' => 'Veterinaria Delta']);

    // Urgent: Called (Tier 1)
    $ticketCalled = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessA->id,
        'ticket_number' => 'TK-CALLED-1',
    ]);

    // Urgent: Ready (Tier 1)
    $ticketReady = QueueTicket::factory()->ready()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessB->id,
        'ticket_number' => 'TK-READY-1',
    ]);

    // Pending: Queue proximity 0 (Tier 2)
    $ticketPending = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessC->id,
        'ticket_number' => 'TK-PENDING-1',
    ]);

    // Skipped: Postponed (Tier 3)
    $ticketSkipped = QueueTicket::factory()->skipped()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessD->id,
        'ticket_number' => 'TK-SKIPPED-1',
    ]);

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets');

    $component->assertSee('Peluquería Alpha')
        ->assertSee('Cafetería Beta')
        ->assertSee('Farmacia Gamma')
        ->assertSee('Veterinaria Delta')
        ->assertSee('TK-CALLED-1')
        ->assertSee('TK-READY-1')
        ->assertSee('TK-PENDING-1')
        ->assertSee('TK-SKIPPED-1');

    $orderedTicketNumbers = $component->get('activeTickets')->pluck('ticket_number')->all();

    expect($orderedTicketNumbers[0])->toBe('TK-CALLED-1')
        ->and($orderedTicketNumbers[1])->toBe('TK-READY-1')
        ->and($orderedTicketNumbers[2])->toBe('TK-PENDING-1')
        ->and($orderedTicketNumbers[3])->toBe('TK-SKIPPED-1');
});

test('paginates correctly through multiple history pages', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create(['business_name' => 'Tienda Histórica']);

    // Create 25 completed tickets with timestamps
    for ($i = 1; $i <= 25; $i++) {
        QueueTicket::factory()->completed()->create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
            'ticket_number' => sprintf('TK-%02d', $i),
            'created_at' => now()->subHours(30 - $i),
        ]);
    }

    // Page 1 should show tickets 25 down to 16
    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets', ['tab' => 'history'])
        ->assertSet('tab', 'history')
        ->assertSee('TK-25')
        ->assertSee('TK-16')
        ->assertDontSee('TK-15')
        ->assertDontSee('TK-01');

    // Navigate to page 2 (tickets 15 down to 06)
    $component->call('gotoPage', 2)
        ->assertSee('TK-15')
        ->assertSee('TK-06')
        ->assertDontSee('TK-25')
        ->assertDontSee('TK-05');

    // Navigate to page 3 (tickets 05 down to 01)
    $component->call('gotoPage', 3)
        ->assertSee('TK-05')
        ->assertSee('TK-01')
        ->assertDontSee('TK-25')
        ->assertDontSee('TK-06');
});

test('handles acknowledgment independently for multiple simultaneously completed tickets', function (): void {
    $customer = Customer::factory()->create();
    $businessA = Business::factory()->create(['business_name' => 'Restaurante Uno']);
    $businessB = Business::factory()->create(['business_name' => 'Restaurante Dos']);

    $ticketA = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessA->id,
        'ticket_number' => 'TK-MULTI-A',
    ]);

    $ticketB = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessB->id,
        'ticket_number' => 'TK-MULTI-B',
    ]);

    $component = Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets');

    // Both tickets are completed simultaneously
    $ticketA->update(['status' => QueueTicketStatus::Completed]);
    $ticketB->update(['status' => QueueTicketStatus::Completed]);

    $component->call('$refresh')
        ->assertSee('TK-MULTI-A')
        ->assertSee('TK-MULTI-B')
        ->assertSee('Tu turno ha finalizado con éxito.');

    // Acknowledge only Ticket A
    $component->call('acknowledgeTicket', $ticketA->id)
        ->assertDontSee('TK-MULTI-A')
        ->assertSee('TK-MULTI-B')
        ->assertSee('Tu turno ha finalizado con éxito.');

    // Acknowledge Ticket B
    $component->call('acknowledgeTicket', $ticketB->id)
        ->assertDontSee('TK-MULTI-B')
        ->assertDontSee('Tu turno ha finalizado con éxito.')
        ->assertSee('No tienes turnos activos en este momento');
});

test('customer cannot see, manipulate or acknowledge another customers ticket data', function (): void {
    $customerA = Customer::factory()->create(['first_name' => 'Carlos']);
    $customerB = Customer::factory()->create(['first_name' => 'Ana']);

    $business = Business::factory()->create(['business_name' => 'Local Privado']);

    $ticketCustomerA = QueueTicket::factory()->pending()->create([
        'customer_id' => $customerA->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-CARLOS-01',
    ]);

    $ticketCustomerB = QueueTicket::factory()->pending()->create([
        'customer_id' => $customerB->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-ANA-01',
    ]);

    // Customer A must not see Customer B's ticket
    $component = Livewire::actingAs($customerA, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('TK-CARLOS-01')
        ->assertDontSee('TK-ANA-01');

    // Customer A attempts to acknowledge Customer B's ticket ID
    $component->call('acknowledgeTicket', $ticketCustomerB->id);

    // Customer A's own ticket remains active and visible
    $component->assertSee('TK-CARLOS-01');

    // When Customer B logs in, their ticket is still active and visible
    Livewire::actingAs($customerB, 'customer')
        ->test('pages::customer.tickets')
        ->assertSee('TK-ANA-01')
        ->assertDontSee('TK-CARLOS-01');
});

test('tickets hub displays fallback when business logo is null and handles soft-deleted offering in active turns', function (): void {
    $customer = Customer::factory()->create();
    $business = Business::factory()->create([
        'business_name' => 'Café Sin Logo',
        'logo_path' => null,
    ]);

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'name' => 'Té Verde Especial',
        'price' => 5000.00,
    ]);

    $ticket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'ticket_number' => 'TK-FALLBACK',
        'total_price' => 5000.00,
    ]);

    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $ticket->id,
        'offering_id' => $offering->id,
        'quantity' => 1,
        'unit_price' => 5000.00,
    ]);

    // Delete offering while ticket is active
    $offering->delete();

    Livewire::actingAs($customer, 'customer')
        ->test('pages::customer.tickets')
        ->assertOk()
        ->assertSee('Café Sin Logo')
        ->assertSee('TK-FALLBACK')
        ->assertSee('Té Verde Especial')
        ->assertSee($business->initials());
});
