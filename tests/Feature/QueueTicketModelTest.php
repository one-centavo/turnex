<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('queue ticket status enum values and helper methods', function (): void {
    expect(QueueTicketStatus::Pending->value)->toBe('PENDING')
        ->and(QueueTicketStatus::Preparing->value)->toBe('PREPARING')
        ->and(QueueTicketStatus::Ready->value)->toBe('READY')
        ->and(QueueTicketStatus::Called->value)->toBe('CALLED')
        ->and(QueueTicketStatus::InService->value)->toBe('IN_SERVICE')
        ->and(QueueTicketStatus::Skipped->value)->toBe('SKIPPED')
        ->and(QueueTicketStatus::Completed->value)->toBe('COMPLETED')
        ->and(QueueTicketStatus::NoShow->value)->toBe('NO_SHOW')
        ->and(QueueTicketStatus::Cancelled->value)->toBe('CANCELLED');

    // Test isActive()
    expect(QueueTicketStatus::Pending->isActive())->toBeTrue()
        ->and(QueueTicketStatus::Preparing->isActive())->toBeTrue()
        ->and(QueueTicketStatus::Ready->isActive())->toBeTrue()
        ->and(QueueTicketStatus::Called->isActive())->toBeTrue()
        ->and(QueueTicketStatus::InService->isActive())->toBeTrue()
        ->and(QueueTicketStatus::Skipped->isActive())->toBeTrue()
        ->and(QueueTicketStatus::Completed->isActive())->toBeFalse()
        ->and(QueueTicketStatus::NoShow->isActive())->toBeFalse()
        ->and(QueueTicketStatus::Cancelled->isActive())->toBeFalse();

    // Test isTerminal()
    expect(QueueTicketStatus::Completed->isTerminal())->toBeTrue()
        ->and(QueueTicketStatus::NoShow->isTerminal())->toBeTrue()
        ->and(QueueTicketStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(QueueTicketStatus::Pending->isTerminal())->toBeFalse()
        ->and(QueueTicketStatus::InService->isTerminal())->toBeFalse();

    // Test label()
    expect(QueueTicketStatus::Pending->label())->toBe('En espera')
        ->and(QueueTicketStatus::Preparing->label())->toBe('En preparación')
        ->and(QueueTicketStatus::Ready->label())->toBe('Listo para entrega')
        ->and(QueueTicketStatus::Called->label())->toBe('Llamado')
        ->and(QueueTicketStatus::InService->label())->toBe('En atención')
        ->and(QueueTicketStatus::Skipped->label())->toBe('Pospuesto')
        ->and(QueueTicketStatus::Completed->label())->toBe('Completado')
        ->and(QueueTicketStatus::NoShow->label())->toBe('No se presentó')
        ->and(QueueTicketStatus::Cancelled->label())->toBe('Cancelado');

    // Test color()
    expect(QueueTicketStatus::Pending->color())->toBeString()
        ->and(QueueTicketStatus::Ready->color())->toBe('emerald');
});

test('queue ticket model can be created with factory and casts attributes correctly', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $ticket = QueueTicket::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'ticket_number' => 'TK-001',
        'ticket_code' => 'abcdef1234567890',
        'status' => QueueTicketStatus::Pending,
        'total_price' => 45.50,
        'called_at' => null,
    ]);

    expect($ticket->status)->toBe(QueueTicketStatus::Pending)
        ->and($ticket->total_price)->toBe('45.50')
        ->and($ticket->called_at)->toBeNull()
        ->and($ticket->isActive())->toBeTrue()
        ->and($ticket->isTerminal())->toBeFalse();

    $this->assertDatabaseHas('queue_tickets', [
        'id' => $ticket->id,
        'ticket_number' => 'TK-001',
        'ticket_code' => 'abcdef1234567890',
        'status' => 'PENDING',
    ]);
});

test('queue ticket relationships and scopes function properly', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create();

    $activeTicket = QueueTicket::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'status' => QueueTicketStatus::Preparing,
    ]);

    $completedTicket = QueueTicket::factory()->completed()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
    ]);

    // Check relations from ticket
    expect($activeTicket->business->id)->toBe($business->id)
        ->and($activeTicket->customer->id)->toBe($customer->id);

    // Check relations from Business and Customer
    expect($business->queueTickets)->toHaveCount(2)
        ->and($customer->queueTickets)->toHaveCount(2);

    // Check active scope
    $activeTickets = QueueTicket::query()->active()->get();
    expect($activeTickets->pluck('id'))->toContain($activeTicket->id)
        ->and($activeTickets->pluck('id'))->not->toContain($completedTicket->id);
});

test('queue ticket item model and subtotal calculation', function (): void {
    $business = Business::factory()->create();
    $ticket = QueueTicket::factory()->for($business)->create();
    $offering = Offering::factory()->for($business)->create([
        'price' => 15.00,
    ]);

    $item = QueueTicketItem::factory()->create([
        'queue_ticket_id' => $ticket->id,
        'offering_id' => $offering->id,
        'quantity' => 3,
        'unit_price' => 15.00,
    ]);

    expect($item->queueTicket->id)->toBe($ticket->id)
        ->and($item->offering->id)->toBe($offering->id)
        ->and($item->quantity)->toBe(3)
        ->and($item->subtotal())->toBe(45.00)
        ->and($item->subtotal)->toBe(45.00);

    // Check hasMany from QueueTicket and Offering
    expect($ticket->items)->toHaveCount(1)
        ->and($offering->queueTicketItems)->toHaveCount(1);
});

test('queue ticket factory states', function (): void {
    $pending = QueueTicket::factory()->pending()->create();
    $preparing = QueueTicket::factory()->preparing()->create();
    $ready = QueueTicket::factory()->ready()->create();
    $called = QueueTicket::factory()->called()->create();
    $inService = QueueTicket::factory()->inService()->create();
    $skipped = QueueTicket::factory()->skipped()->create();
    $completed = QueueTicket::factory()->completed()->create();
    $noShow = QueueTicket::factory()->noShow()->create();
    $cancelled = QueueTicket::factory()->cancelled()->create();

    expect($pending->status)->toBe(QueueTicketStatus::Pending)
        ->and($preparing->status)->toBe(QueueTicketStatus::Preparing)
        ->and($ready->status)->toBe(QueueTicketStatus::Ready)
        ->and($called->status)->toBe(QueueTicketStatus::Called)
        ->and($called->called_at)->not->toBeNull()
        ->and($called->isCalled())->toBeTrue()
        ->and($called->isInService())->toBeFalse()
        ->and($called->isPostponed())->toBeFalse()
        ->and($inService->status)->toBe(QueueTicketStatus::InService)
        ->and($inService->served_at)->not->toBeNull()
        ->and($inService->isInService())->toBeTrue()
        ->and($inService->isCalled())->toBeFalse()
        ->and($skipped->status)->toBe(QueueTicketStatus::Skipped)
        ->and($skipped->isPostponed())->toBeTrue()
        ->and($completed->status)->toBe(QueueTicketStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->isTerminal())->toBeTrue()
        ->and($noShow->status)->toBe(QueueTicketStatus::NoShow)
        ->and($noShow->cancelled_at)->not->toBeNull()
        ->and($cancelled->status)->toBe(QueueTicketStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

test('business queue pause state and helper method', function (): void {
    $business = Business::factory()->create();
    expect($business->is_queue_paused)->toBeFalse()
        ->and($business->isQueuePaused())->toBeFalse();

    $pausedBusiness = Business::factory()->queuePaused()->create();
    expect($pausedBusiness->is_queue_paused)->toBeTrue()
        ->and($pausedBusiness->isQueuePaused())->toBeTrue();
});
