<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getCurrentSpotlightTicket prioritizes InService over Called', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    expect($service->getCurrentSpotlightTicket($business))->toBeNull();

    $calledTicket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'called_at' => now()->subMinutes(5),
    ]);

    $spotlight = $service->getCurrentSpotlightTicket($business);
    expect($spotlight)->not->toBeNull()
        ->and($spotlight?->id)->toBe($calledTicket->id);

    $inServiceTicket = QueueTicket::factory()->inService()->create([
        'business_id' => $business->id,
        'served_at' => now(),
    ]);

    $spotlightPriority = $service->getCurrentSpotlightTicket($business);
    expect($spotlightPriority)->not->toBeNull()
        ->and($spotlightPriority?->id)->toBe($inServiceTicket->id)
        ->and($spotlightPriority?->relationLoaded('customer'))->toBeTrue()
        ->and($spotlightPriority?->relationLoaded('items'))->toBeTrue();
});

test('callNextTicket calls the oldest pending ticket in chronological order', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket1 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subMinutes(10),
    ]);

    $ticket2 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'created_at' => now()->subMinutes(5),
    ]);

    $called = $service->callNextTicket($business);

    expect($called)->not->toBeNull()
        ->and($called?->id)->toBe($ticket1->id)
        ->and($called?->status)->toBe(QueueTicketStatus::Called)
        ->and($called?->called_at)->not->toBeNull();

    expect($ticket1->fresh()->status)->toBe(QueueTicketStatus::Called)
        ->and($ticket2->fresh()->status)->toBe(QueueTicketStatus::Pending);

    $nextCalled = $service->callNextTicket($business);
    expect($nextCalled?->id)->toBe($ticket2->id);
});

test('callNextTicket returns null when queue has no pending tickets', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    expect($service->callNextTicket($business))->toBeNull();
});

test('callSpecificTicket calls a chosen waiting ticket and updates called_at', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
    ]);

    $called = $service->callSpecificTicket($business, $ticket);

    expect($called->id)->toBe($ticket->id)
        ->and($called->status)->toBe(QueueTicketStatus::Called)
        ->and($called->called_at)->not->toBeNull();
});

test('callSpecificTicket accepts postponed ticket and transitions to Called', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
    ]);

    $called = $service->callSpecificTicket($business, $ticket);

    expect($called->status)->toBe(QueueTicketStatus::Called);
});

test('callSpecificTicket rejects tickets belonging to another business', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $ticketB = QueueTicket::factory()->pending()->create([
        'business_id' => $businessB->id,
    ]);

    $service->callSpecificTicket($businessA, $ticketB);
})->throws(DomainException::class, 'Ticket does not belong to this business.');

test('callSpecificTicket rejects tickets not in callable state', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->completed()->create([
        'business_id' => $business->id,
    ]);

    $service->callSpecificTicket($business, $ticket);
})->throws(DomainException::class, 'Ticket is not in a callable state.');

test('recallTicket updates called_at for an already called ticket', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $oldTime = now()->subMinutes(5);
    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'called_at' => $oldTime,
    ]);

    $recalled = $service->recallTicket($ticket, $business);

    expect($recalled->called_at)->not->toBeNull()
        ->and($recalled->called_at->toIso8601String())->not->toBe($oldTime->toIso8601String());
});

test('recallTicket rejects non-called tickets', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
    ]);

    $service->recallTicket($ticket, $business);
})->throws(DomainException::class, 'Ticket is not currently in called state.');

test('startService transitions status from Called to InService and sets served_at', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'served_at' => null,
    ]);

    $started = $service->startService($ticket, $business);

    expect($started->status)->toBe(QueueTicketStatus::InService)
        ->and($started->served_at)->not->toBeNull();
});

test('completeTicket transitions status to Completed and sets completed_at and fallback served_at', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'served_at' => null,
        'completed_at' => null,
    ]);

    $completed = $service->completeTicket($ticket, $business);

    expect($completed->status)->toBe(QueueTicketStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->served_at)->not->toBeNull();
});

test('completeTicket rejects tickets already terminal', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->completed()->create([
        'business_id' => $business->id,
    ]);

    $service->completeTicket($ticket, $business);
})->throws(DomainException::class, 'Ticket is not in an active actionable state.');

test('markNoShow transitions status to NoShow and sets cancelled_at', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
        'cancelled_at' => null,
    ]);

    $noShow = $service->markNoShow($ticket, $business);

    expect($noShow->status)->toBe(QueueTicketStatus::NoShow)
        ->and($noShow->cancelled_at)->not->toBeNull();
});

test('postponeTicket transitions status to Skipped', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->called()->create([
        'business_id' => $business->id,
    ]);

    $postponed = $service->postponeTicket($ticket, $business);

    expect($postponed->status)->toBe(QueueTicketStatus::Skipped)
        ->and($postponed->isPostponed())->toBeTrue();
});

test('resumeTicket restores a skipped ticket to Called or Pending', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticketA = QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
    ]);

    $resumedCalled = $service->resumeTicket($ticketA, $business, callImmediately: true);
    expect($resumedCalled->status)->toBe(QueueTicketStatus::Called)
        ->and($resumedCalled->called_at)->not->toBeNull();

    $ticketB = QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
    ]);

    $resumedPending = $service->resumeTicket($ticketB, $business, callImmediately: false);
    expect($resumedPending->status)->toBe(QueueTicketStatus::Pending);
});

test('cancelTicketByBusiness transitions active ticket to Cancelled and sets cancelled_at', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'cancelled_at' => null,
    ]);

    $cancelled = $service->cancelTicketByBusiness($ticket, $business);

    expect($cancelled->status)->toBe(QueueTicketStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

test('cancelTicketByBusiness rejects already terminal tickets', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $ticket = QueueTicket::factory()->cancelled()->create([
        'business_id' => $business->id,
    ]);

    $service->cancelTicketByBusiness($ticket, $business);
})->throws(DomainException::class, 'Ticket cannot be cancelled because it is already in a terminal state.');

test('toggleQueuePause toggles is_queue_paused boolean on business', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create(['is_queue_paused' => false]);

    expect($business->isQueuePaused())->toBeFalse();

    $newStatus = $service->toggleQueuePause($business);
    expect($newStatus)->toBeTrue()
        ->and($business->fresh()->isQueuePaused())->toBeTrue();

    $nextStatus = $service->toggleQueuePause($business);
    expect($nextStatus)->toBeFalse()
        ->and($business->fresh()->isQueuePaused())->toBeFalse();
});

test('createTicket throws DomainException when business queue is paused', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->queuePaused()->create();
    $customer = Customer::factory()->create();
    $offering = Offering::factory()->for($business)->create(['is_active' => true]);

    $service->createTicket($business, $customer, [
        ['offering_id' => $offering->id, 'quantity' => 1],
    ]);
})->throws(DomainException::class, 'This business has temporarily paused its queue and is not accepting new turns.');

test('overnight resilience: tickets created before midnight remain active, searchable, and callable after 00:05', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    // Set time to 23:55 yesterday
    Carbon::setTestNow(now()->subDay()->setTime(23, 55));

    $ticketYesterday = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'ticket_number' => 'TK-099',
        'created_at' => now(),
    ]);

    // Advance time to 00:05 today
    Carbon::setTestNow(now()->addMinutes(10));

    try {
        $waiting = $service->getWaitingTicketsForBusiness($business);
        expect($waiting)->toHaveCount(1)
            ->and($waiting->first()?->id)->toBe($ticketYesterday->id);

        $called = $service->callNextTicket($business);
        expect($called)->not->toBeNull()
            ->and($called?->id)->toBe($ticketYesterday->id);
    } finally {
        Carbon::setTestNow();
    }
});

test('getWaitingTicketsForBusiness filters by ticket number and customer name', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $customer1 = Customer::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Santana',
    ]);
    $ticket1 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'customer_id' => $customer1->id,
        'ticket_number' => 'TK-001',
    ]);

    $customer2 = Customer::factory()->create([
        'first_name' => 'Lucia',
        'last_name' => 'Mendez',
    ]);
    $ticket2 = QueueTicket::factory()->pending()->create([
        'business_id' => $business->id,
        'customer_id' => $customer2->id,
        'ticket_number' => 'TK-002',
    ]);

    // Unfiltered
    expect($service->getWaitingTicketsForBusiness($business))->toHaveCount(2);

    // Search by ticket number
    $searchTicket = $service->getWaitingTicketsForBusiness($business, 'TK-001');
    expect($searchTicket)->toHaveCount(1)
        ->and($searchTicket->first()?->id)->toBe($ticket1->id);

    // Search by customer name
    $searchName = $service->getWaitingTicketsForBusiness($business, 'Lucia');
    expect($searchName)->toHaveCount(1)
        ->and($searchName->first()?->id)->toBe($ticket2->id);

    // No match
    expect($service->getWaitingTicketsForBusiness($business, 'Nonexistent'))->toHaveCount(0);
});

test('getPostponedTicketsForBusiness returns skipped tickets in descending order', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    $skipped1 = QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
        'updated_at' => now()->subMinutes(10),
    ]);

    $skipped2 = QueueTicket::factory()->skipped()->create([
        'business_id' => $business->id,
        'updated_at' => now(),
    ]);

    $postponed = $service->getPostponedTicketsForBusiness($business);
    expect($postponed)->toHaveCount(2)
        ->and($postponed->first()?->id)->toBe($skipped2->id)
        ->and($postponed->last()?->id)->toBe($skipped1->id);
});

test('getDailyQueueStats and getFinishedTicketsToday correctly compute metrics and logs', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $business = Business::factory()->create();

    // 2 Pending
    QueueTicket::factory()->pending()->count(2)->create([
        'business_id' => $business->id,
    ]);

    // 1 Completed today
    QueueTicket::factory()->completed()->create([
        'business_id' => $business->id,
        'completed_at' => now(),
    ]);

    // 1 NoShow today
    QueueTicket::factory()->noShow()->create([
        'business_id' => $business->id,
        'cancelled_at' => now(),
    ]);

    // 1 Cancelled today
    QueueTicket::factory()->cancelled()->create([
        'business_id' => $business->id,
        'cancelled_at' => now(),
    ]);

    $stats = $service->getDailyQueueStats($business);
    expect($stats['waiting'])->toBe(2)
        ->and($stats['waiting_count'])->toBe(2)
        ->and($stats['served_today'])->toBe(1)
        ->and($stats['completed_count'])->toBe(1)
        ->and($stats['no_show_today'])->toBe(1)
        ->and($stats['no_show_count'])->toBe(1)
        ->and($stats['total_today'])->toBe(5);

    $finishedToday = $service->getFinishedTicketsToday($business);
    expect($finishedToday)->toHaveCount(3);
});

test('multi-tenant boundary isolation: Business A operations cannot alter Business B tickets', function (): void {
    /** @var QueueService $service */
    $service = app(QueueService::class);
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $ticketB = QueueTicket::factory()->called()->create([
        'business_id' => $businessB->id,
    ]);

    expect(fn () => $service->recallTicket($ticketB, $businessA))
        ->toThrow(DomainException::class, 'Ticket does not belong to this business.');

    expect(fn () => $service->startService($ticketB, $businessA))
        ->toThrow(DomainException::class, 'Ticket does not belong to this business.');

    expect(fn () => $service->completeTicket($ticketB, $businessA))
        ->toThrow(DomainException::class, 'Ticket does not belong to this business.');

    expect(fn () => $service->markNoShow($ticketB, $businessA))
        ->toThrow(DomainException::class, 'Ticket does not belong to this business.');

    expect(fn () => $service->postponeTicket($ticketB, $businessA))
        ->toThrow(DomainException::class, 'Ticket does not belong to this business.');

    expect(fn () => $service->cancelTicketByBusiness($ticketB, $businessA))
        ->toThrow(DomainException::class, 'Ticket does not belong to this business.');
});
