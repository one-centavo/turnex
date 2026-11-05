<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('daily ticket number generation sequentially increments and resets at midnight', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();

    // First ticket should be TK-001
    expect($service->generateTicketNumber($business))->toBe('TK-001');

    QueueTicket::factory()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-001',
        'created_at' => now(),
    ]);

    expect($service->generateTicketNumber($business))->toBe('TK-002');

    QueueTicket::factory()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-002',
        'created_at' => now(),
    ]);

    expect($service->generateTicketNumber($business))->toBe('TK-003');

    // Simulate advancing to the next day (midnight reset)
    Carbon::setTestNow(now()->addDay()->startOfDay());

    try {
        expect($service->generateTicketNumber($business))->toBe('TK-001');
    } finally {
        Carbon::setTestNow();
    }
});

test('different businesses have independent ticket sequences on the same day', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    QueueTicket::factory()->create([
        'business_id' => $businessA->id,
        'ticket_number' => 'TK-001',
        'created_at' => now(),
    ]);

    QueueTicket::factory()->create([
        'business_id' => $businessA->id,
        'ticket_number' => 'TK-002',
        'created_at' => now(),
    ]);

    expect($service->generateTicketNumber($businessA))->toBe('TK-003')
        ->and($service->generateTicketNumber($businessB))->toBe('TK-001');
});

test('generateTicketCode produces unique 32 character URL safe alphanumeric tokens', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $code1 = $service->generateTicketCode();
    $code2 = $service->generateTicketCode();

    expect(strlen($code1))->toBe(32)
        ->and(strlen($code2))->toBe(32)
        ->and($code1)->not->toBe($code2)
        ->and(ctype_alnum($code1))->toBeTrue()
        ->and(ctype_alnum($code2))->toBeTrue();
});

test('getActiveTicketForCustomer returns active ticket or null correctly', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    // No ticket initially
    expect($service->getActiveTicketForCustomer($business, $customer))->toBeNull();

    // Customer has a completed (terminal) ticket
    QueueTicket::factory()->completed()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
    ]);

    expect($service->getActiveTicketForCustomer($business, $customer))->toBeNull();

    // Customer has an active ticket
    $activeTicket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
    ]);

    $resolved = $service->getActiveTicketForCustomer($business, $customer);
    expect($resolved)->not->toBeNull()
        ->and($resolved?->id)->toBe($activeTicket->id);
});

test('createTicket creates ticket, snapshots unit prices, calculates total, and links items', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $offering1 = Offering::factory()->create([
        'business_id' => $business->id,
        'price' => 15.50,
        'is_active' => true,
    ]);

    $offering2 = Offering::factory()->create([
        'business_id' => $business->id,
        'price' => 20.00,
        'is_active' => true,
    ]);

    $items = [
        ['offering_id' => $offering1->id, 'quantity' => 2],
        ['offering_id' => $offering2->id, 'quantity' => 1],
    ];

    $ticket = $service->createTicket($business, $customer, $items);

    expect($ticket)->toBeInstanceOf(QueueTicket::class)
        ->and($ticket->business_id)->toBe($business->id)
        ->and($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->ticket_number)->toBe('TK-001')
        ->and(strlen($ticket->ticket_code))->toBe(32)
        ->and($ticket->status)->toBe(QueueTicketStatus::Pending)
        ->and((float) $ticket->total_price)->toBe(51.00)
        ->and($ticket->items)->toHaveCount(2);

    // Verify item 1 snapshot
    $ticketItem1 = $ticket->items->firstWhere('offering_id', $offering1->id);
    expect($ticketItem1)->not->toBeNull()
        ->and($ticketItem1->quantity)->toBe(2)
        ->and((float) $ticketItem1->unit_price)->toBe(15.50);

    // Verify item 2 snapshot
    $ticketItem2 = $ticket->items->firstWhere('offering_id', $offering2->id);
    expect($ticketItem2)->not->toBeNull()
        ->and($ticketItem2->quantity)->toBe(1)
        ->and((float) $ticketItem2->unit_price)->toBe(20.00);

    // Ensure price snapshotting is decoupled from subsequent catalog price updates
    $offering1->update(['price' => 99.99]);

    $freshItem1 = $ticketItem1->fresh();
    expect((float) $freshItem1->unit_price)->toBe(15.50);
});

test('createTicket rejects empty items list', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $service->createTicket($business, $customer, []);
})->throws(DomainException::class, 'Ticket must contain at least one item.');

test('createTicket rejects non-positive item quantity', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
    ]);

    $service->createTicket($business, $customer, [
        ['offering_id' => $offering->id, 'quantity' => 0],
    ]);
})->throws(DomainException::class, 'Offering quantity must be at least 1.');

test('createTicket rejects offerings that belong to another business', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $customer = Customer::factory()->create();

    $offeringB = Offering::factory()->create([
        'business_id' => $businessB->id,
        'is_active' => true,
    ]);

    $service->createTicket($businessA, $customer, [
        ['offering_id' => $offeringB->id, 'quantity' => 1],
    ]);
})->throws(DomainException::class, 'One or more selected offerings are invalid, inactive, or do not belong to this business.');

test('createTicket rejects inactive offerings', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $inactiveOffering = Offering::factory()->create([
        'business_id' => $business->id,
        'is_active' => false,
    ]);

    $service->createTicket($business, $customer, [
        ['offering_id' => $inactiveOffering->id, 'quantity' => 1],
    ]);
})->throws(DomainException::class, 'One or more selected offerings are invalid, inactive, or do not belong to this business.');

test('createTicket enforces single active ticket per customer per business', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $offering = Offering::factory()->create([
        'business_id' => $business->id,
        'is_active' => true,
    ]);

    // First ticket succeeds
    $service->createTicket($business, $customer, [
        ['offering_id' => $offering->id, 'quantity' => 1],
    ]);

    // Second ticket in the same business fails
    $service->createTicket($business, $customer, [
        ['offering_id' => $offering->id, 'quantity' => 1],
    ]);
})->throws(DomainException::class, 'Customer already has an active ticket for this business.');

test('customer can hold active tickets in different businesses concurrently', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $customer = Customer::factory()->create();

    $offeringA = Offering::factory()->create([
        'business_id' => $businessA->id,
        'is_active' => true,
    ]);

    $offeringB = Offering::factory()->create([
        'business_id' => $businessB->id,
        'is_active' => true,
    ]);

    $ticketA = $service->createTicket($businessA, $customer, [
        ['offering_id' => $offeringA->id, 'quantity' => 1],
    ]);

    $ticketB = $service->createTicket($businessB, $customer, [
        ['offering_id' => $offeringB->id, 'quantity' => 1],
    ]);

    expect($ticketA->isActive())->toBeTrue()
        ->and($ticketB->isActive())->toBeTrue()
        ->and($ticketA->business_id)->toBe($businessA->id)
        ->and($ticketB->business_id)->toBe($businessB->id);
});

test('calculateQueuePosition returns accurate count of pending tickets ahead', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $ticket1 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subMinutes(10),
    ]);

    $ticket2 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subMinutes(5),
    ]);

    $ticket3 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now(),
    ]);

    // Ticket in another business should not affect queue position
    QueueTicket::factory()->pending()->create([
        'business_id' => $otherBusiness->id,
        'created_at' => now()->subMinutes(8),
    ]);

    expect($service->calculateQueuePosition($ticket1))->toBe(0)
        ->and($service->calculateQueuePosition($ticket2))->toBe(1)
        ->and($service->calculateQueuePosition($ticket3))->toBe(2);

    // When ticket1 is transitioned to InService (no longer pending), ticket2 is first in line
    $ticket1->update(['status' => QueueTicketStatus::InService]);

    expect($service->calculateQueuePosition($ticket2))->toBe(0)
        ->and($service->calculateQueuePosition($ticket3))->toBe(1);

    // Non-pending tickets always return 0
    expect($service->calculateQueuePosition($ticket1))->toBe(0);
});

test('cancelTicket voluntarily cancels an active ticket owned by the customer', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $ticket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
    ]);

    expect($ticket->isActive())->toBeTrue();

    $result = $service->cancelTicket($ticket, $customer);

    expect($result)->toBeTrue()
        ->and($ticket->fresh()->status)->toBe(QueueTicketStatus::Cancelled)
        ->and($ticket->fresh()->isActive())->toBeFalse()
        ->and($ticket->fresh()->isTerminal())->toBeTrue();
});

test('cancelTicket fails when attempted by non-owner customer', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $owner = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $ticket = QueueTicket::factory()->pending()->create([
        'customer_id' => $owner->id,
    ]);

    $service->cancelTicket($ticket, $otherCustomer);
})->throws(DomainException::class, 'You are not authorized to cancel this ticket.');

test('cancelTicket fails when ticket is already terminal', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $ticket = QueueTicket::factory()->completed()->create([
        'customer_id' => $customer->id,
    ]);

    $service->cancelTicket($ticket, $customer);
})->throws(DomainException::class, 'Ticket cannot be cancelled because it is already in a terminal state.');
