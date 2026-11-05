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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getActiveTicketsForCustomer returns only active tickets for the customer', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $business = Business::factory()->create();

    // Active tickets for target customer
    $pendingTicket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);
    $calledTicket = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
    ]);
    $skippedTicket = QueueTicket::factory()->skipped()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
    ]);

    // Terminal tickets for target customer (must NOT be returned)
    QueueTicket::factory()->completed()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);
    QueueTicket::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);
    QueueTicket::factory()->noShow()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);

    // Active ticket for other customer (must NOT be returned)
    QueueTicket::factory()->pending()->create([
        'customer_id' => $otherCustomer->id,
        'business_id' => $business->id,
    ]);

    $activeTickets = $service->getActiveTicketsForCustomer($customer);

    expect($activeTickets)->toHaveCount(3)
        ->and($activeTickets->pluck('id'))->toContain(
            $pendingTicket->id,
            $calledTicket->id,
            $skippedTicket->id,
        );

    foreach ($activeTickets as $ticket) {
        expect($ticket->isActive())->toBeTrue()
            ->and($ticket->customer_id)->toBe($customer->id);
    }
});

test('urgent tickets (Called, Ready) appear before Pending and Skipped tickets', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();

    $pendingTicket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'created_at' => now()->subMinutes(10),
    ]);
    $readyTicket = QueueTicket::factory()->ready()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'created_at' => now()->subMinutes(5),
    ]);
    $calledTicket = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'created_at' => now()->subMinutes(1),
    ]);
    $skippedTicket = QueueTicket::factory()->skipped()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
        'created_at' => now()->subMinutes(15),
    ]);

    $activeTickets = $service->getActiveTicketsForCustomer($customer);

    expect($activeTickets)->toHaveCount(4);

    $firstTicket = $activeTickets->get(0);
    $secondTicket = $activeTickets->get(1);
    $thirdTicket = $activeTickets->get(2);
    $fourthTicket = $activeTickets->get(3);

    expect($firstTicket)->not->toBeNull()
        ->and($secondTicket)->not->toBeNull()
        ->and($thirdTicket)->not->toBeNull()
        ->and($fourthTicket)->not->toBeNull();

    // Urgent tickets (Called and Ready) are first (Tier 1)
    expect([$firstTicket->id, $secondTicket->id])->toContain($calledTicket->id, $readyTicket->id)
        ->and($firstTicket->status)->toBe(QueueTicketStatus::Called)
        ->and($secondTicket->status)->toBe(QueueTicketStatus::Ready);

    // Pending ticket is next (Tier 2)
    expect($thirdTicket->id)->toBe($pendingTicket->id)
        ->and($thirdTicket->status)->toBe(QueueTicketStatus::Pending);

    // Skipped ticket is last (Tier 3)
    expect($fourthTicket->id)->toBe($skippedTicket->id)
        ->and($fourthTicket->status)->toBe(QueueTicketStatus::Skipped);
});

test('pending tickets are ordered by turns_ahead ascending', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $businessC = Business::factory()->create();

    // In Business A: 1 other customer ahead -> Customer ticket has turns_ahead = 1
    QueueTicket::factory()->pending()->create([
        'business_id' => $businessA->id,
        'created_at' => now()->subMinutes(20),
    ]);
    $ticketA = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessA->id,
        'created_at' => now()->subMinutes(10),
    ]);

    // In Business B: 0 other customers ahead -> Customer ticket has turns_ahead = 0
    $ticketB = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessB->id,
        'created_at' => now()->subMinutes(5),
    ]);

    // In Business C: 3 other customers ahead -> Customer ticket has turns_ahead = 3
    QueueTicket::factory()->pending()->create([
        'business_id' => $businessC->id,
        'created_at' => now()->subMinutes(30),
    ]);
    QueueTicket::factory()->pending()->create([
        'business_id' => $businessC->id,
        'created_at' => now()->subMinutes(25),
    ]);
    QueueTicket::factory()->pending()->create([
        'business_id' => $businessC->id,
        'created_at' => now()->subMinutes(20),
    ]);
    $ticketC = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businessC->id,
        'created_at' => now()->subMinutes(15),
    ]);

    $activeTickets = $service->getActiveTicketsForCustomer($customer);

    expect($activeTickets)->toHaveCount(3)
        ->and($activeTickets->pluck('id')->all())->toBe([
            $ticketB->id, // turns_ahead = 0
            $ticketA->id, // turns_ahead = 1
            $ticketC->id, // turns_ahead = 3
        ]);
});

test('turns_ahead attribute is populated correctly', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $business = Business::factory()->create();

    // 2 tickets ahead of our customer
    QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subMinutes(10),
    ]);
    QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subMinutes(5),
    ]);

    $pendingTicket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'created_at' => now(),
    ]);

    $calledTicket = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => Business::factory()->create()->id,
    ]);

    $activeTickets = $service->getActiveTicketsForCustomer($customer);

    /** @var QueueTicket $foundPending */
    $foundPending = $activeTickets->firstWhere('id', $pendingTicket->id);
    /** @var QueueTicket $foundCalled */
    $foundCalled = $activeTickets->firstWhere('id', $calledTicket->id);

    expect($foundPending->turns_ahead)->toBe(2)
        ->and($foundCalled->turns_ahead)->toBe(0);
});

test('getHistoricalTicketsForCustomer returns paginated terminal tickets ordered newest first', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $business = Business::factory()->create();

    // Create 15 terminal tickets for target customer with descending created_at timestamps
    $terminalTickets = [];
    for ($i = 0; $i < 15; $i++) {
        $terminalTickets[] = QueueTicket::factory()->completed()->create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
            'created_at' => Carbon::now()->subMinutes(15 - $i),
        ]);
    }

    // Active ticket for target customer (must NOT be in history)
    QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);

    // Terminal ticket for another customer (must NOT be in history)
    QueueTicket::factory()->completed()->create([
        'customer_id' => $otherCustomer->id,
        'business_id' => $business->id,
    ]);

    $paginator = $service->getHistoricalTicketsForCustomer($customer, perPage: 10);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(15)
        ->and($paginator->perPage())->toBe(10)
        ->and($paginator->count())->toBe(10);

    // Verify ordering: newest first
    $items = $paginator->items();
    for ($j = 0; $j < count($items) - 1; $j++) {
        expect($items[$j]->created_at?->timestamp)->toBeGreaterThanOrEqual($items[$j + 1]->created_at?->timestamp);
    }

    // First item is the newest ($terminalTickets[14])
    expect($items[0]->id)->toBe($terminalTickets[14]->id);

    // Second page contains the remaining 5 items
    $page2 = QueueTicket::query()
        ->terminal()
        ->where('customer_id', $customer->id)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->paginate(10, ['*'], 'page', 2);

    expect($page2->count())->toBe(5);
});

test('soft-deleted offerings in past tickets do not cause errors or missing item records', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $business = Business::factory()->create();

    $offeringA = Offering::factory()->create([
        'business_id' => $business->id,
        'name' => 'Legacy Burger',
        'price' => 15.00,
    ]);
    $offeringB = Offering::factory()->create([
        'business_id' => $business->id,
        'name' => 'Fries',
        'price' => 5.00,
    ]);

    // Create a historical ticket with items
    $historicalTicket = QueueTicket::factory()->completed()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
        'total_price' => 20.00,
    ]);

    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $historicalTicket->id,
        'offering_id' => $offeringA->id,
        'quantity' => 1,
        'unit_price' => 15.00,
    ]);
    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $historicalTicket->id,
        'offering_id' => $offeringB->id,
        'quantity' => 1,
        'unit_price' => 5.00,
    ]);

    // Soft-delete offeringA
    $offeringA->delete();
    expect($offeringA->trashed())->toBeTrue();

    // Query historical tickets
    $paginator = $service->getHistoricalTicketsForCustomer($customer);
    /** @var QueueTicket $ticketFromHistory */
    $ticketFromHistory = $paginator->items()[0];

    expect($ticketFromHistory->relationLoaded('business'))->toBeTrue()
        ->and($ticketFromHistory->relationLoaded('items'))->toBeTrue()
        ->and($ticketFromHistory->items)->toHaveCount(2);

    $itemForDeletedOffering = $ticketFromHistory->items->firstWhere('offering_id', $offeringA->id);
    expect($itemForDeletedOffering)->not->toBeNull()
        ->and($itemForDeletedOffering->relationLoaded('offering'))->toBeTrue()
        ->and($itemForDeletedOffering->offering)->not->toBeNull()
        ->and($itemForDeletedOffering->offering->id)->toBe($offeringA->id)
        ->and($itemForDeletedOffering->offering->name)->toBe('Legacy Burger')
        ->and($itemForDeletedOffering->offering->trashed())->toBeTrue();

    // Also test active tickets eager loading resilience
    $activeTicket = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $business->id,
    ]);
    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $activeTicket->id,
        'offering_id' => $offeringA->id,
        'quantity' => 2,
        'unit_price' => 15.00,
    ]);

    $activeTickets = $service->getActiveTicketsForCustomer($customer);
    $activeTicketWithDeletedOffering = $activeTickets->firstWhere('id', $activeTicket->id);

    expect($activeTicketWithDeletedOffering)->not->toBeNull()
        ->and($activeTicketWithDeletedOffering->relationLoaded('business'))->toBeTrue()
        ->and($activeTicketWithDeletedOffering->relationLoaded('items'))->toBeTrue()
        ->and($activeTicketWithDeletedOffering->items->first()->offering)->not->toBeNull()
        ->and($activeTicketWithDeletedOffering->items->first()->offering->id)->toBe($offeringA->id)
        ->and($activeTicketWithDeletedOffering->items->first()->offering->trashed())->toBeTrue();
});

test('customer isolation is strictly enforced across active and historical ticket retrieval', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    $business = Business::factory()->create();

    // Active tickets
    $activeTicketA = QueueTicket::factory()->pending()->create([
        'customer_id' => $customerA->id,
        'business_id' => $business->id,
    ]);
    QueueTicket::factory()->pending()->create([
        'customer_id' => $customerB->id,
        'business_id' => $business->id,
    ]);

    // Historical tickets
    $historyTicketA = QueueTicket::factory()->completed()->create([
        'customer_id' => $customerA->id,
        'business_id' => $business->id,
    ]);
    QueueTicket::factory()->completed()->create([
        'customer_id' => $customerB->id,
        'business_id' => $business->id,
    ]);

    $activeA = $service->getActiveTicketsForCustomer($customerA);
    $historyA = $service->getHistoricalTicketsForCustomer($customerA);

    expect($activeA)->toHaveCount(1)
        ->and($activeA->first()->id)->toBe($activeTicketA->id)
        ->and($historyA->total())->toBe(1)
        ->and($historyA->items()[0]->id)->toBe($historyTicketA->id);
});

test('active tickets across 4 businesses are correctly ordered across all urgency tiers', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $businesses = Business::factory()->count(6)->create();

    // Tier 1: Called & Ready
    $called = QueueTicket::factory()->called()->create([
        'customer_id' => $customer->id,
        'business_id' => $businesses[0]->id,
    ]);
    $ready = QueueTicket::factory()->ready()->create([
        'customer_id' => $customer->id,
        'business_id' => $businesses[1]->id,
    ]);

    // Tier 2: InService, Preparing, Pending
    $inService = QueueTicket::factory()->inService()->create([
        'customer_id' => $customer->id,
        'business_id' => $businesses[2]->id,
    ]);
    $preparing = QueueTicket::factory()->preparing()->create([
        'customer_id' => $customer->id,
        'business_id' => $businesses[3]->id,
    ]);
    $pending = QueueTicket::factory()->pending()->create([
        'customer_id' => $customer->id,
        'business_id' => $businesses[4]->id,
    ]);

    // Tier 3: Skipped
    $skipped = QueueTicket::factory()->skipped()->create([
        'customer_id' => $customer->id,
        'business_id' => $businesses[5]->id,
    ]);

    $active = $service->getActiveTicketsForCustomer($customer);

    expect($active)->toHaveCount(6);

    $ids = $active->pluck('id')->all();

    // Tier 1 items must be at index 0 and 1
    expect([$ids[0], $ids[1]])->toContain($called->id, $ready->id);

    // Tier 2 items must be at index 2, 3, 4
    expect([$ids[2], $ids[3], $ids[4]])->toContain($inService->id, $preparing->id, $pending->id);

    // Tier 3 item must be at index 5
    expect($ids[5])->toBe($skipped->id);
});

test('getHistoricalTicketsForCustomer paginates across 3 pages preserving strict descending order', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);

    $customer = Customer::factory()->create();
    $business = Business::factory()->create();

    for ($i = 1; $i <= 25; $i++) {
        QueueTicket::factory()->completed()->create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
            'ticket_number' => sprintf('TK-%02d', $i),
            'created_at' => Carbon::now()->subMinutes(30 - $i),
        ]);
    }

    $page1 = $service->getHistoricalTicketsForCustomer($customer, perPage: 10);
    expect($page1->total())->toBe(25)
        ->and($page1->count())->toBe(10)
        ->and($page1->items()[0]->ticket_number)->toBe('TK-25')
        ->and($page1->items()[9]->ticket_number)->toBe('TK-16');

    // Page 2
    request()->merge(['page' => 2]);
    $page2 = $service->getHistoricalTicketsForCustomer($customer, perPage: 10);
    expect($page2->count())->toBe(10)
        ->and($page2->items()[0]->ticket_number)->toBe('TK-15')
        ->and($page2->items()[9]->ticket_number)->toBe('TK-06');

    // Page 3
    request()->merge(['page' => 3]);
    $page3 = $service->getHistoricalTicketsForCustomer($customer, perPage: 10);
    expect($page3->count())->toBe(5)
        ->and($page3->items()[0]->ticket_number)->toBe('TK-05')
        ->and($page3->items()[4]->ticket_number)->toBe('TK-01');
});
