<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use App\Services\QueueService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest visitor is redirected to business login when visiting live queue', function (): void {
    $this->get(route('business.queue.index'))
        ->assertRedirect(route('business.login'));
});

test('authenticated customer is redirected to business login when visiting business live queue', function (): void {
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('business.queue.index'))
        ->assertRedirect(route('business.login'));
});

test('authenticated business operator accesses /business/queue with HTTP 200 and sees queue cockpit', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create([
        'business_name' => 'Barbería Don Pedro',
    ]);

    $this->actingAs($business, 'business')
        ->get(route('business.queue.index'))
        ->assertOk()
        ->assertSee('Gestión de Turnos en Vivo')
        ->assertSee('Mostrador disponible');
});

test('idle counter renders mostrador disponible when spotlight is empty', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index')
        ->assertSee('Mostrador disponible')
        ->assertSee('No hay ningún cliente en atención en este momento.')
        ->assertSee('No hay clientes en espera');
});

test('calling next with empty queue displays feedback that queue is empty', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index')
        ->call('callNext')
        ->assertSet('feedbackMessage', 'No hay clientes en espera en este momento.')
        ->assertSee('No hay clientes en espera');
});

test('calling next with waiting patrons moves oldest customer into spotlight as called', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticketOldest */
    $ticketOldest = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-001',
        'created_at' => Carbon::now()->subMinutes(15),
    ]);

    /** @var QueueTicket $ticketNewer */
    $ticketNewer = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-002',
        'created_at' => Carbon::now()->subMinutes(5),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('callNext');

    expect($ticketOldest->fresh()->status)->toBe(QueueTicketStatus::Called)
        ->and($ticketOldest->fresh()->called_at)->not->toBeNull()
        ->and($ticketNewer->fresh()->status)->toBe(QueueTicketStatus::Pending);

    $component->assertSee('TK-001')
        ->assertSee('Llamado al Mostrador');
});

test('calling next when spotlight is already occupied triggers confirmation modal without unseating current customer', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $activeTicket */
    $activeTicket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-010',
        'called_at' => Carbon::now()->subMinutes(5),
    ]);

    /** @var QueueTicket $waitingTicket */
    $waitingTicket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-011',
        'created_at' => Carbon::now()->subMinutes(2),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('callNext')
        ->assertSet('confirmingCallNext', true);

    // Active customer remains in spotlight and waiting remains pending
    expect($activeTicket->fresh()->status)->toBe(QueueTicketStatus::Called)
        ->and($waitingTicket->fresh()->status)->toBe(QueueTicketStatus::Pending);
});

test('confirming call next from modal unseats previous customer and brings up new customer into spotlight', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $currentTicket */
    $currentTicket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-020',
        'called_at' => Carbon::now()->subMinutes(5),
    ]);

    /** @var QueueTicket $nextTicket */
    $nextTicket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-021',
        'created_at' => Carbon::now()->subMinutes(2),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    // Trigger confirmation modal
    $component->call('callNext')
        ->assertSet('confirmingCallNext', true);

    // Confirm calling next
    $component->call('confirmCallNext')
        ->assertSet('confirmingCallNext', false);

    // Previous customer is unseated to skipped (postponed)
    expect($currentTicket->fresh()->status)->toBe(QueueTicketStatus::Skipped);

    // Next customer is now Called and occupies spotlight
    expect($nextTicket->fresh()->status)->toBe(QueueTicketStatus::Called)
        ->and($nextTicket->fresh()->called_at)->not->toBeNull();

    $component->assertSee('TK-021')
        ->assertSee('Llamado al Mostrador');
});

test('clicking start service transitions ticket to in-service and sets served_at', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-030',
        'called_at' => Carbon::now()->subMinutes(3),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('startService');

    $freshTicket = $ticket->fresh();
    expect($freshTicket->status)->toBe(QueueTicketStatus::InService)
        ->and($freshTicket->served_at)->not->toBeNull();

    $component->assertSee('En Atención');
});

test('clicking complete service finishes customer, sets completed_at, and clears spotlight', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->inService()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-040',
        'served_at' => Carbon::now()->subMinutes(10),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('completeService');

    $freshTicket = $ticket->fresh();
    expect($freshTicket->status)->toBe(QueueTicketStatus::Completed)
        ->and($freshTicket->completed_at)->not->toBeNull();

    $component->assertSee('Mostrador disponible');
});

test('clicking recall customer refreshes called_at timestamp', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $originalCalledAt = Carbon::now()->subMinutes(12);

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-050',
        'called_at' => $originalCalledAt,
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('recall');

    $freshTicket = $ticket->fresh();
    expect($freshTicket->called_at->timestamp)->toBeGreaterThan($originalCalledAt->timestamp);
});

test('clicking postpone moves customer to postponed list and frees spotlight', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-060',
        'called_at' => Carbon::now()->subMinutes(4),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('postpone');

    expect($ticket->fresh()->status)->toBe(QueueTicketStatus::Skipped);

    $component->assertSee('Mostrador disponible');

    $component->set('activeTab', 'postponed')
        ->assertSee('TK-060')
        ->assertSee('Reanudar');
});

test('resuming postponed ticket moves them back to spotlight as called', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-070',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->set('activeTab', 'postponed')
        ->call('resume', $ticket->id);

    $freshTicket = $ticket->fresh();
    expect($freshTicket->status)->toBe(QueueTicketStatus::Called)
        ->and($freshTicket->called_at)->not->toBeNull();

    $component->assertSee('TK-070')
        ->assertSee('Llamado al Mostrador');
});

test('marking customer as no show requires confirmation and transitions ticket to no show', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-080',
        'called_at' => Carbon::now()->subMinutes(6),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('promptNoShow', $ticket->id)
        ->assertSet('confirmingNoShowTicketId', $ticket->id);

    $component->call('confirmNoShow')
        ->assertSet('confirmingNoShowTicketId', null);

    $freshTicket = $ticket->fresh();
    expect($freshTicket->status)->toBe(QueueTicketStatus::NoShow)
        ->and($freshTicket->cancelled_at)->not->toBeNull();

    $component->assertSee('Mostrador disponible');
});

test('cancelling ticket from waiting list requires confirmation and cancels ticket', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-090',
        'created_at' => Carbon::now()->subMinutes(8),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->call('promptCancel', $ticket->id)
        ->assertSet('confirmingCancelTicketId', $ticket->id);

    $component->call('confirmCancel')
        ->assertSet('confirmingCancelTicketId', null);

    $freshTicket = $ticket->fresh();
    expect($freshTicket->status)->toBe(QueueTicketStatus::Cancelled)
        ->and($freshTicket->cancelled_at)->not->toBeNull();
});

test('real-time search filters waiting list by ticket number or customer name', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var Customer $customerA */
    $customerA = Customer::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Mendoza',
    ]);

    /** @var Customer $customerB */
    $customerB = Customer::factory()->create([
        'first_name' => 'Lucia',
        'last_name' => 'Gomez',
    ]);

    QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'customer_id' => $customerA->id,
        'ticket_number' => 'TK-101',
        'created_at' => Carbon::now()->subMinutes(10),
    ]);

    QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'customer_id' => $customerB->id,
        'ticket_number' => 'TK-102',
        'created_at' => Carbon::now()->subMinutes(5),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    // Both tickets are initially visible
    $component->assertSee('TK-101')
        ->assertSee('Carlos Mendoza')
        ->assertSee('TK-102')
        ->assertSee('Lucia Gomez');

    // Filter by customer name
    $component->set('search', 'Carlos')
        ->assertSee('TK-101')
        ->assertSee('Carlos Mendoza')
        ->assertDontSee('TK-102')
        ->assertDontSee('Lucia Gomez');

    // Filter by ticket number
    $component->set('search', '102')
        ->assertSee('TK-102')
        ->assertSee('Lucia Gomez')
        ->assertDontSee('TK-101')
        ->assertDontSee('Carlos Mendoza');

    // Clearing search shows both again
    $component->set('search', '')
        ->assertSee('TK-101')
        ->assertSee('TK-102');
});

test('switching between tabs renders waiting, postponed, and finished datasets', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-W01',
    ]);

    QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-P01',
    ]);

    QueueTicket::factory()->completed()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-F01',
        'completed_at' => Carbon::now(),
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    // Default waiting tab
    $component->assertSet('activeTab', 'waiting')
        ->assertSee('TK-W01')
        ->assertDontSee('TK-P01')
        ->assertDontSee('TK-F01');

    // Postponed tab
    $component->set('activeTab', 'postponed')
        ->assertSee('TK-P01')
        ->assertDontSee('TK-W01')
        ->assertDontSee('TK-F01');

    // History tab
    $component->set('activeTab', 'history')
        ->assertSee('TK-F01')
        ->assertDontSee('TK-W01')
        ->assertDontSee('TK-P01');
});

test('toggling queue pause updates business state and storefront rejects new turns', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create(['is_queue_paused' => false]);
    /** @var Customer $customer */
    $customer = Customer::factory()->create();
    /** @var Offering $offering */
    $offering = Offering::factory()->for($business)->create();

    /** @var QueueService $queueService */
    $queueService = app(QueueService::class);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->assertSee('Fila Abierta');

    // Toggle pause ON
    $component->call('togglePause');

    expect($business->fresh()->isQueuePaused())->toBeTrue();
    $component->assertSee('Fila Pausada');

    // Attempting to create ticket should throw DomainException
    expect(fn () => $queueService->createTicket(
        $business->fresh(),
        $customer,
        [['offering_id' => $offering->id, 'quantity' => 1]]
    ))->toThrow(DomainException::class);

    // Toggle pause OFF
    $component->call('togglePause');

    expect($business->fresh()->isQueuePaused())->toBeFalse();
    $component->assertSee('Fila Abierta');
});

test('customer voluntary self cancellation removes ticket from waiting list on refresh', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();
    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'ticket_number' => 'TK-999',
    ]);

    $component = Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index');

    $component->assertSee('TK-999');

    // Customer cancels voluntarily
    /** @var QueueService $queueService */
    $queueService = app(QueueService::class);
    $queueService->cancelTicket($ticket, $customer);

    // Livewire polls / refreshes
    $component->call('$refresh');

    $component->assertDontSee('TK-999');
});

test('legacy url /business/live-queue redirects to /business/queue', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    $this->actingAs($business, 'business')
        ->get('business/live-queue')
        ->assertRedirect(route('business.queue.index'));
});

test('spotlight displays requested items and pricing breakdown', function (): void {
    /** @var Business $business */
    $business = Business::factory()->create();

    /** @var Offering $offering */
    $offering = Offering::factory()->for($business)->create([
        'name' => 'Corte Premium con Barba',
        'price' => 35000.00,
    ]);

    /** @var QueueTicket $ticket */
    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-777',
        'total_price' => 35000.00,
        'called_at' => Carbon::now()->subMinutes(2),
    ]);

    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $ticket->id,
        'offering_id' => $offering->id,
        'quantity' => 1,
        'unit_price' => 35000.00,
    ]);

    Livewire::actingAs($business, 'business')
        ->test('pages::business.queue.index')
        ->assertSee('TK-777')
        ->assertSee('Corte Premium con Barba')
        ->assertSee('35,000.00');
});

test('business cannot see or operate on tickets of another business', function (): void {
    /** @var Business $businessA */
    $businessA = Business::factory()->create();
    /** @var Business $businessB */
    $businessB = Business::factory()->create();

    /** @var QueueTicket $ticketB */
    $ticketB = QueueTicket::factory()->pending()->create([
        'business_id' => $businessB->id,
        'ticket_number' => 'TK-B99',
    ]);

    $component = Livewire::actingAs($businessA, 'business')
        ->test('pages::business.queue.index');

    $component->assertDontSee('TK-B99');

    // Attempting to call ticket belonging to another business fails
    $component->call('callSpecific', $ticketB->id);
    expect($ticketB->fresh()->status)->toBe(QueueTicketStatus::Pending);
});
