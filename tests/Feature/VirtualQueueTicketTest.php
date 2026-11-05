<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->business = Business::factory()->create([
        'verification_status' => VerificationStatus::Approved,
        'slug' => 'bistro-central',
    ]);

    $this->customer = Customer::factory()->create();

    $this->offering1 = Offering::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Pizza Margherita',
        'price' => 25000.00,
        'is_active' => true,
    ]);

    $this->offering2 = Offering::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Limonada Natural',
        'price' => 6000.00,
        'is_active' => true,
    ]);
});

test('adding offerings to turn selection and calculating totals', function (): void {
    Livewire::test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('incrementQuantity', $this->offering1->id) // 2x 25,000 = 50,000
        ->call('addOffering', $this->offering2->id) // 1x 6,000 = 6,000
        ->assertSet('selectedCount', 3)
        ->assertSet('selectedTotal', 56000.00)
        ->assertSee('3 ítems seleccionados')
        ->assertSee('$ 56.000');
});

test('mandatory customer authentication check when confirming turn', function (): void {
    Livewire::test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('confirmTurn')
        ->assertNoRedirect();

    expect(QueueTicket::count())->toBe(0);
});

test('daily sequential ticket numbering with midnight reset', function (): void {
    $queueService = app(QueueService::class);

    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00'));

    $ticket1 = $queueService->createTicket($this->business, $this->customer, [
        ['offering_id' => $this->offering1->id, 'quantity' => 1],
    ]);
    expect($ticket1->ticket_number)->toBe('TK-001');

    $customer2 = Customer::factory()->create();
    $ticket2 = $queueService->createTicket($this->business, $customer2, [
        ['offering_id' => $this->offering1->id, 'quantity' => 1],
    ]);
    expect($ticket2->ticket_number)->toBe('TK-002');

    // Next day (midnight reset)
    Carbon::setTestNow(Carbon::parse('2026-11-06 00:01:00'));
    $customer3 = Customer::factory()->create();
    $ticket3 = $queueService->createTicket($this->business, $customer3, [
        ['offering_id' => $this->offering1->id, 'quantity' => 1],
    ]);
    expect($ticket3->ticket_number)->toBe('TK-001');

    Carbon::setTestNow();
});

test('single active ticket per customer per business constraint', function (): void {
    QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'ticket_number' => 'TK-001',
        'status' => QueueTicketStatus::Pending,
    ]);

    Livewire::actingAs($this->customer, 'customer')
        ->test('pages::storefront.show', ['slug' => $this->business->slug])
        ->call('addOffering', $this->offering1->id)
        ->call('requestTurn')
        ->assertSee('Ya tienes un turno activo');

    expect(QueueTicket::where('business_id', $this->business->id)->count())->toBe(1);
});

test('concurrent active tickets across different venues', function (): void {
    $otherBusiness = Business::factory()->create([
        'verification_status' => VerificationStatus::Approved,
        'slug' => 'otra-tienda',
    ]);
    $otherOffering = Offering::factory()->create([
        'business_id' => $otherBusiness->id,
        'price' => 10000.00,
        'is_active' => true,
    ]);

    // Active ticket in business 1
    QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => QueueTicketStatus::Pending,
    ]);

    // Can still create ticket in otherBusiness
    Livewire::actingAs($this->customer, 'customer')
        ->test('pages::storefront.show', ['slug' => $otherBusiness->slug])
        ->call('addOffering', $otherOffering->id)
        ->call('confirmTurn')
        ->assertRedirect();

    expect(QueueTicket::where('customer_id', $this->customer->id)->count())->toBe(2);
});

test('queue position calculation and reactive poll updates', function (): void {
    $firstTicket = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'status' => QueueTicketStatus::Pending,
        'created_at' => now()->subMinutes(5),
    ]);

    $secondTicket = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => QueueTicketStatus::Pending,
        'created_at' => now(),
    ]);

    $component = Livewire::actingAs($this->customer, 'customer')
        ->test('pages::tickets.show', ['code' => $secondTicket->ticket_code])
        ->assertSee('1')
        ->assertSee('persona antes de ti');

    // First ticket finishes, customer becomes next in line
    $firstTicket->update(['status' => QueueTicketStatus::Completed]);

    $component->call('$refresh')
        ->assertSee('¡Eres el siguiente!');
});

test('customer voluntary ticket cancellation', function (): void {
    $ticket = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => QueueTicketStatus::Pending,
    ]);

    Livewire::actingAs($this->customer, 'customer')
        ->test('pages::tickets.show', ['code' => $ticket->ticket_code])
        ->call('cancelTurn')
        ->assertOk();

    expect($ticket->fresh()->status)->toBe(QueueTicketStatus::Cancelled);
});

test('404 response for invalid ticket codes', function (): void {
    $this->get(route('tickets.show', 'invalid-ticket-code-xyz'))
        ->assertNotFound();
});
