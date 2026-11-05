# 013 · Virtual Queue & Digital Ticket — Tasks

## Database & Persistence Layer

- [x] Create backed string enum `App\Enums\QueueTicketStatus` with cases `Pending`, `Preparing`, `Ready`, `Called`, `InService`, `Skipped`, `Completed`, `NoShow`, `Cancelled`.
- [x] Create migration `create_queue_tickets_table` linking `business_id` and `customer_id`, with `ticket_number`, `ticket_code` index, `status`, `total_price`, `called_at`, and timestamps.
- [x] Create migration `create_queue_ticket_items_table` linking `queue_ticket_id` and `offering_id`, with `quantity`, `unit_price`, and timestamps.
- [x] Create model `App\Models\QueueTicket`:
  - [x] Add `#[Fillable]` and casts.
  - [x] Define relationships: `business()`, `customer()`, `items()`.
  - [x] Add scope `scopeActive()` and helper `isActive()`.
- [x] Create model `App\Models\QueueTicketItem`:
  - [x] Add `#[Fillable]` and relations: `queueTicket()`, `offering()`.
- [x] Update `App\Models\Business` with `queueTickets()` relationship.
- [x] Update `App\Models\Customer` with `queueTickets()` relationship.
- [x] Create model factories `QueueTicketFactory` and `QueueTicketItemFactory`.

## Domain & Service Layer

- [x] Create `App\Services\QueueService`:
  - [x] Implement `generateTicketNumber(Business $business): string` resetting daily (`TK-001`).
  - [x] Implement `generateTicketCode(): string` returning unique URL token.
  - [x] Implement `getActiveTicketForCustomer(Business $business, Customer $customer): ?QueueTicket`.
  - [x] Implement `createTicket(Business $business, Customer $customer, array $items): QueueTicket` running in atomic database transaction.
  - [x] Implement `calculateQueuePosition(QueueTicket $ticket): int`.
  - [x] Implement `cancelTicket(QueueTicket $ticket, Customer $customer): bool`.

## Presentation & Livewire Layer

- [x] Update Storefront Livewire component in `resources/views/pages/storefront/⚡show.blade.php`:
  - [x] Implement offering selection state and quantity adjustments.
  - [x] Build floating summary bar displaying selected item count, total price, and action button.
  - [x] Build confirmation modal: prompts for login/registration if unauthenticated; creates ticket directly if authenticated.
  - [x] Handle active ticket detection with redirection to existing ticket.
  - [x] Submit turn and redirect to live tracking page.
- [x] Register tracking route in `routes/web.php`: `Route::livewire('tickets/{code}', 'pages::tickets.show')->name('tickets.show');`.
- [x] Create Livewire Single-File Component in `resources/views/pages/tickets/⚡show.blade.php`:
  - [x] Implement `mount(string $code)` resolving ticket or aborting 404.
  - [x] Add reactive periodic polling (`wire:poll.5s`) to refresh status and position ahead.
  - [x] Implement voluntary cancellation action with confirmation modal.
  - [x] Build responsive tracking interface displaying ticket code, status, queue position, estimated wait time, venue details, and items summary.
  - [x] Style using Turnex semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `text-text-primary`, `border-border-subtle`).

## Verification & QA

- [x] Create Pest feature test suite in `tests/Feature/VirtualQueueTicketTest.php` covering:
  - [x] Adding offerings to turn selection and calculating totals.
  - [x] Mandatory customer authentication check when confirming turn.
  - [x] Daily sequential ticket numbering (`TK-001`) with midnight reset.
  - [x] Single active ticket per customer per business constraint.
  - [x] Concurrent active tickets across different venues.
  - [x] Queue position calculation and reactive poll updates.
  - [x] Customer voluntary ticket cancellation.
  - [x] 404 response for invalid ticket codes.
- [x] Run test suite via Docker: `docker compose exec turnex-app php artisan test --filter=VirtualQueueTicketTest --compact`.
- [x] Run full test suite regression: `docker compose exec turnex-app php artisan test --compact`.
- [x] Run code style fixer via Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
- [x] Run static analysis via Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse`.
- [x] Update `spec.md`, `tasks.md`, and roadmap with final completion status.
