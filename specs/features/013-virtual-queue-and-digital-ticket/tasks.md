# 013 · Virtual Queue & Digital Ticket — Tasks

## Database & Persistence Layer

- [ ] Create backed string enum `App\Enums\QueueTicketStatus` with cases `Pending`, `Called`, `InService`, `Completed`, `Cancelled`.
- [ ] Create migration `create_queue_tickets_table` with business relation, optional customer relation, code index, guest fields, status, price, and timestamp tracking.
- [ ] Create migration `create_queue_ticket_items_table` with ticket relation, offering relation, quantity, price, and duration.
- [ ] Create model `App\Models\QueueTicket`:
  - [ ] Add `#[Fillable]` and casts.
  - [ ] Define relationships: `business()`, `customer()`, `items()`.
  - [ ] Add scope `scopeActive()` and helper `isActive()`.
- [ ] Create model `App\Models\QueueTicketItem`:
  - [ ] Add `#[Fillable]` and relations: `queueTicket()`, `offering()`.
- [ ] Update `App\Models\Business` to add `queueTickets()` relation.
- [ ] Update `App\Models\Customer` to add `queueTickets()` relation.
- [ ] Create factories `QueueTicketFactory` and `QueueTicketItemFactory`.

## Domain & Service Layer

- [ ] Create `App\Services\QueueService`:
  - [ ] Implement `generateTicketCode(Business $business): string` generating formatted sequential codes (`TK-001`).
  - [ ] Implement `getActiveTicketForCustomer(Business $business, ?Customer $customer = null, ?string $guestContact = null): ?QueueTicket`.
  - [ ] Implement `createTicket(Business $business, array $items, ?Customer $customer = null, ?array $guestData = null): QueueTicket` running in atomic transaction.
  - [ ] Implement `calculateQueuePosition(QueueTicket $ticket): int`.
  - [ ] Implement `cancelTicket(QueueTicket $ticket): bool`.

## Presentation & Livewire Layer

- [ ] Update Storefront Livewire component in `resources/views/pages/storefront/⚡show.blade.php`:
  - [ ] Implement selection state (`$selectedOfferings`), `toggleOffering()`, `incrementQuantity()`, `decrementQuantity()`.
  - [ ] Build floating "Mi Turno" summary bar displaying selected item count and total.
  - [ ] Build confirmation modal / drawer collecting guest details or using authenticated customer profile.
  - [ ] Handle active ticket detection with redirection to existing ticket.
  - [ ] Submit turn request and redirect to live tracking page.
- [ ] Register tracking route in `routes/web.php`: `Route::livewire('tickets/{code}', 'pages::tickets.show')->name('tickets.show')`.
- [ ] Create Livewire Single-File Component in `resources/views/pages/tickets/⚡show.blade.php`:
  - [ ] Implement `mount(string $code)` resolving ticket or aborting 404.
  - [ ] Add reactive periodic polling (`wire:poll.5s`) to refresh status and queue position.
  - [ ] Implement cancellation action with confirmation modal.
  - [ ] Build responsive tracking interface displaying ticket code, status badge, position ahead, estimated wait time, business info, and items summary.
  - [ ] Style using Turnex semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `text-text-primary`, `border-border-subtle`).

## Verification & QA

- [ ] Create Pest feature test suite in `tests/Feature/VirtualQueueTicketTest.php` covering:
  - [ ] Adding offerings to turn selection and computing totals.
  - [ ] Creating ticket for authenticated customer.
  - [ ] Creating ticket for guest visitor with name and contact details.
  - [ ] Unique human-readable ticket code generation (`TK-001`).
  - [ ] Single active ticket per customer per business constraint.
  - [ ] Concurrent active tickets across different businesses.
  - [ ] Queue position calculation.
  - [ ] Live tracking page rendering details, status, and position ahead.
  - [ ] Reactive queue position updates upon preceding ticket completions.
  - [ ] Customer voluntary ticket cancellation.
  - [ ] Invalid or non-existent ticket code 404 handling.
- [ ] Run test suite via Docker: `docker compose exec turnex-app php artisan test --filter=VirtualQueueTicketTest --compact`.
- [ ] Run full test suite regression: `docker compose exec turnex-app php artisan test --compact`.
- [ ] Run code style fixer via Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
- [ ] Run static analysis via Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse`.
- [ ] Update `spec.md`, `tasks.md`, and roadmap with final completion status.
