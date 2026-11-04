# 013 · Virtual Queue & Digital Ticket — Plan

## Approach

Implement a complete digital queueing lifecycle for customers visiting a business storefront. Customers assemble products and services into a "Mi Turno" summary, provide contact details (or leverage authenticated customer sessions), and receive a unique, human-readable ticket code (e.g., `TK-001`). Customers monitor their ticket live at `/tickets/{code}` using lightweight Livewire polling (`wire:poll.5s`).

The architecture strictly enforces business isolation and concurrency constraints: customers may not hold more than one active ticket within the same business, but can hold tickets across different merchants simultaneously.

## Implementation Steps

1. **Database Schema & Persistence**:
   - Create backed string enum `App\Enums\QueueTicketStatus`:
     - `Pending = 'PENDING'` (waiting in queue)
     - `Called = 'CALLED'` (being called right now)
     - `InService = 'IN_SERVICE'` (currently receiving service)
     - `Completed = 'COMPLETED'` (service finalized)
     - `Cancelled = 'CANCELLED'` (cancelled by customer or venue)
   - Create migration `create_queue_tickets_table`:
     - Columns: `id`, `business_id` (FK to `businesses`), `customer_id` (nullable FK to `customers`), `ticket_code` (unique string, indexed), `guest_name` (nullable string), `guest_contact` (nullable string), `status` (string, default `PENDING`), `total_price` (decimal 10,2), `estimated_duration_minutes` (nullable int), `called_at`, `served_at`, `completed_at`, `cancelled_at`, timestamps.
   - Create migration `create_queue_ticket_items_table`:
     - Columns: `id`, `queue_ticket_id` (FK to `queue_tickets`, cascade on delete), `offering_id` (FK to `offerings`, cascade on delete), `quantity` (int, default 1), `unit_price` (decimal 10,2), `subtotal` (decimal 10,2), `estimated_duration_minutes` (nullable int), timestamps.
   - Create models:
     - `App\Models\QueueTicket`:
       - Defines relations: `belongsTo(Business::class)`, `belongsTo(Customer::class)`, `hasMany(QueueTicketItem::class)`.
       - Attributes: `#[Fillable([...])]`, casts for `QueueTicketStatus` and timestamps.
       - Scopes and helpers: `isActive()`, `scopeActive()`.
     - `App\Models\QueueTicketItem`:
       - Defines relations: `belongsTo(QueueTicket::class)`, `belongsTo(Offering::class)`.
       - Attributes: `#[Fillable([...])]`.
   - Update `App\Models\Business` with `queueTickets()` relationship.
   - Update `App\Models\Customer` with `queueTickets()` relationship.

2. **Domain Service Layer (`App\Services\QueueService`)**:
   - Implement `generateTicketCode(Business $business): string`:
     - Generates sequential daily codes formatted as `TK-XXX` (e.g., `TK-001`, `TK-002`) scoped to the business and date.
   - Implement `getActiveTicketForCustomer(Business $business, ?Customer $customer = null, ?string $guestContact = null): ?QueueTicket`:
     - Checks if an active ticket exists for the given customer ID or sanitized contact target within the business.
   - Implement `createTicket(Business $business, array $items, ?Customer $customer = null, ?array $guestData = null): QueueTicket`:
     - Enforces single-active-ticket concurrency rule; throws `DomainException` if active ticket exists.
     - Runs inside atomic database transaction.
     - Calculates total price and aggregated duration.
     - Persists `QueueTicket` and associated `QueueTicketItem` records.
   - Implement `calculateQueuePosition(QueueTicket $ticket): int`:
     - Returns the number of `Pending` tickets created prior to `$ticket` for the same business.
   - Implement `cancelTicket(QueueTicket $ticket): bool`:
     - Marks ticket as `Cancelled` and sets `cancelled_at = now()`.

3. **Storefront Turn Selection & Confirmation**:
   - Update `resources/views/pages/storefront/⚡show.blade.php`:
     - Add selection properties: `$selectedOfferings = []`.
     - Add action methods: `selectOffering(int $offeringId)`, `removeOffering(int $offeringId)`, `incrementQuantity(int $offeringId)`, `decrementQuantity(int $offeringId)`.
     - Render floating "Mi Turno" summary bar showing selected count, total price, and "Confirmar Turno" button when items are selected.
     - Implement turn confirmation modal / drawer:
       - Displays order breakdown.
       - Pre-fills authenticated customer name if signed in.
       - Displays name and phone/email inputs for unauthenticated guests.
       - Checks for existing active ticket before creation; if active ticket found, redirects to it.
       - Submits via `confirmTurn(QueueService $queueService)` and redirects to the tracking page.

4. **Live Ticket Tracking Page (`resources/views/pages/tickets/⚡show.blade.php`)**:
   - Register route in `routes/web.php`:
     - `Route::livewire('tickets/{code}', 'pages::tickets.show')->name('tickets.show');`
   - Create Livewire 4 Single-File Component:
     - Mounts with route parameter `$code`.
     - Resolves ticket; aborts 404 if not found.
     - Uses `wire:poll.5s` to refresh queue status, position ahead, and estimated wait time without page reloads.
     - Provides voluntary cancellation action with confirmation modal.
     - Visually communicates states:
       - `Pending`: Waiting number, count of people ahead, estimated wait time.
       - `Called`: Prominent highlight with alert styling instructing customer to approach the service counter.
       - `InService`: Active service in progress indicator.
       - `Completed` / `Cancelled`: Clear final state notification.
     - Styled with Turnex semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `text-text-primary`, `border-border-subtle`).

5. **Verification & Testing**:
   - Create Pest test suite `tests/Feature/VirtualQueueTicketTest.php` covering:
     - Selecting offerings and calculating totals.
     - Generating a ticket for authenticated customers.
     - Generating a ticket for guest visitors with name and contact details.
     - Unique human-readable ticket code generation (`TK-001`).
     - Enforcement of single active ticket per customer per business.
     - Concurrency allowance across different businesses.
     - Real-time queue position calculation.
     - Live ticket tracking page rendering details, status, and position.
     - Ticket voluntary cancellation by customer.
     - Rejection of invalid ticket codes (404).
