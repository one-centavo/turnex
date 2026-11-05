<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->business = Business::factory()->create([
        'verification_status' => VerificationStatus::Approved,
        'business_name' => 'Cafetería Gourmet',
        'slug' => 'cafeteria-gourmet',
    ]);

    $this->customer = Customer::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Pérez',
    ]);

    $this->offering = Offering::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Cappuccino Vainilla',
        'price' => 7000.00,
    ]);

    $this->ticket = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'ticket_number' => 'TK-001',
        'ticket_code' => 'test-code-12345',
        'status' => QueueTicketStatus::Pending,
        'total_price' => 14000.00,
    ]);

    QueueTicketItem::factory()->create([
        'queue_ticket_id' => $this->ticket->id,
        'offering_id' => $this->offering->id,
        'quantity' => 2,
        'unit_price' => 7000.00,
    ]);
});

test('invalid ticket code returns 404', function (): void {
    $this->get(route('tickets.show', 'non-existent-code'))
        ->assertNotFound();
});

test('ticket tracking page loads ticket and displays details and order items', function (): void {
    Livewire::test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->assertOk()
        ->assertSee('Cafetería Gourmet')
        ->assertSee('TK-001')
        ->assertSee('En espera')
        ->assertSee('Cappuccino Vainilla')
        ->assertSee('2x')
        ->assertSee('$ 14.000')
        ->assertSee('wire:poll.5s');
});

test('ticket shows queue position ahead and next message when 0 ahead', function (): void {
    // With 0 tickets ahead:
    Livewire::test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->assertSee('¡Eres el siguiente!');

    // Create an earlier pending ticket for the same business:
    $earlierTicket = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'status' => QueueTicketStatus::Pending,
        'created_at' => now()->subMinutes(10),
    ]);

    // Now earlierTicket is ahead of $this->ticket:
    Livewire::test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->assertSee('1')
        ->assertSee('persona antes de ti');
});

test('ticket displays correct message for all queue statuses', function (): void {
    $statusesAndMessages = [
        QueueTicketStatus::Preparing->value => 'Tu pedido se encuentra en preparación',
        QueueTicketStatus::Ready->value => '¡Tu pedido está listo para recoger en mostrador!',
        QueueTicketStatus::Called->value => '¡Es tu turno! Acércate al módulo de atención',
        QueueTicketStatus::InService->value => 'En este momento estás siendo atendido',
        QueueTicketStatus::Skipped->value => 'Tu turno fue pospuesto porque no te encontrabas presente. Por favor acércate al personal',
        QueueTicketStatus::Completed->value => 'Este turno fue completado. ¡Gracias por tu visita!',
        QueueTicketStatus::NoShow->value => 'Este turno expiró por inasistencia',
        QueueTicketStatus::Cancelled->value => 'Este turno fue cancelado',
    ];

    foreach ($statusesAndMessages as $statusValue => $expectedMessage) {
        $this->ticket->update(['status' => QueueTicketStatus::from($statusValue)]);

        Livewire::test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
            ->assertSee($expectedMessage);
    }
});

test('unauthorized user cannot cancel ticket', function (): void {
    $otherCustomer = Customer::factory()->create();

    // Guest cannot cancel
    Livewire::test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->call('cancelTurn')
        ->assertForbidden();

    // Another customer cannot cancel
    Livewire::actingAs($otherCustomer, 'customer')
        ->test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->call('cancelTurn')
        ->assertForbidden();

    expect($this->ticket->fresh()->status)->toBe(QueueTicketStatus::Pending);
});

test('ticket owner can voluntarily cancel an active ticket', function (): void {
    Livewire::actingAs($this->customer, 'customer')
        ->test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->assertSee('Cancelar turno')
        ->call('cancelTurn')
        ->assertOk();

    expect($this->ticket->fresh()->status)->toBe(QueueTicketStatus::Cancelled);
});

test('ticket transitions dynamically to called status on poll refresh', function (): void {
    $component = Livewire::actingAs($this->customer, 'customer')
        ->test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->assertSee('En espera')
        ->assertDontSee('¡Es tu turno! Acércate al módulo de atención');

    // Merchant calls the ticket:
    $this->ticket->update([
        'status' => QueueTicketStatus::Called,
        'called_at' => now(),
    ]);

    // Livewire poll refresh:
    $component->call('$refresh')
        ->assertSee('Llamado')
        ->assertSee('¡Es tu turno! Acércate al módulo de atención');
});

test('terminal ticket does not display cancel button and blocks subsequent cancellation', function (): void {
    $this->ticket->update(['status' => QueueTicketStatus::Completed]);

    Livewire::actingAs($this->customer, 'customer')
        ->test('pages::tickets.show', ['code' => $this->ticket->ticket_code])
        ->assertDontSee('Cancelar turno')
        ->call('cancelTurn');

    expect($this->ticket->fresh()->status)->toBe(QueueTicketStatus::Completed);
});

test('cancellation of preceding ticket updates turns ahead for following customers on refresh', function (): void {
    $customerBehind = Customer::factory()->create();
    $ticketBehind = QueueTicket::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $customerBehind->id,
        'status' => QueueTicketStatus::Pending,
        'created_at' => now()->addMinute(),
    ]);

    $component = Livewire::actingAs($customerBehind, 'customer')
        ->test('pages::tickets.show', ['code' => $ticketBehind->ticket_code])
        ->assertSee('1')
        ->assertSee('persona antes de ti');

    // Customer ahead cancels:
    $this->ticket->update(['status' => QueueTicketStatus::Cancelled]);

    $component->call('$refresh')
        ->assertSee('¡Eres el siguiente!');
});
