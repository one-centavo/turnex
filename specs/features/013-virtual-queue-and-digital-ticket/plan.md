# 013 · Virtual Queue & Digital Ticket — Plan

## Approach

Implement an end-to-end digital queueing lifecycle connecting authenticated customers with venue operations. From the public storefront (`/b/{slug}`), customers assemble products and services into their turn, authenticate to prevent fraudulent entries, and receive a digital ticket with a daily sequential identifier (`TK-001`) and a secure URL token. Customers monitor their position in real time at `/tickets/{code}` using lightweight Livewire polling (`wire:poll.5s`).

The architecture enforces strict tenant isolation and concurrency controls: customers are restricted to at most one active ticket per business, while remaining free to hold tickets across distinct venues concurrently.

## Implementation

1. **Data Layer (Persistence & Schema)**:
   - Create backed string enum `App\Enums\QueueTicketStatus` with cases:
     - `Pending = 'PENDING'` (waiting in queue)
     - `Preparing = 'PREPARING'` (kitchen/prep in progress for food/products)
     - `Ready = 'READY'` (order ready on counter waiting for pickup)
     - `Called = 'CALLED'` (customer called to service module/counter)
     - `InService = 'IN_SERVICE'` (currently receiving service)
     - `Skipped = 'SKIPPED'` (customer absent when called; operator skips to keep line moving)
     - `Completed = 'COMPLETED'` (delivered / service completed)
     - `NoShow = 'NO_SHOW'` (unattended / abandoned after timeout)
     - `Cancelled = 'CANCELLED'` (voluntarily cancelled by customer)
   - Create migration `create_queue_tickets_table` linking `business_id` (mandatory) and `customer_id` (mandatory), with sequential `ticket_number` (`VARCHAR(10)`), secure token `ticket_code` (`VARCHAR(32)` unique index), `status`, `total_price`, `called_at`, and standard timestamps.
   - Create migration `create_queue_ticket_items_table` linking `queue_ticket_id` (cascade delete) and `offering_id` (restrict delete), with `quantity`, frozen `unit_price`, and standard timestamps.
   - Create Eloquent models `App\Models\QueueTicket` and `App\Models\QueueTicketItem` with `#[Fillable]` attributes, type casts, query scope `scopeActive()`, and relational methods.
   - Update `App\Models\Business` and `App\Models\Customer` with `queueTickets()` relationships (`hasMany(QueueTicket::class)`).
   - Create model factories `QueueTicketFactory` and `QueueTicketItemFactory`.

2. **Domain & Service Layer (`App\Services\QueueService`)**:
   - `generateTicketNumber(Business $business): string`: Calculates the next daily sequential ticket number for the business resetting each midnight (e.g., `TK-001`, `TK-014`).
   - `generateTicketCode(): string`: Generates a collision-resistant unique token for the public tracking URL.
   - `getActiveTicketForCustomer(Business $business, Customer $customer): ?QueueTicket`: Checks whether the customer already holds an active ticket (`Pending`, `Preparing`, `Ready`, `Called`, `InService`, or `Skipped`) for the given business.
   - `createTicket(Business $business, Customer $customer, array $items): QueueTicket`: Enforces single-active-ticket concurrency, validates item availability, calculates aggregated totals, and persists the ticket and its items within an atomic database transaction.
   - `calculateQueuePosition(QueueTicket $ticket): int`: Calculates the number of `Pending` tickets ahead of the given ticket for the same business.
   - `cancelTicket(QueueTicket $ticket, Customer $customer): bool`: Verifies ticket ownership and updates status to `Cancelled` if still in an active, cancellable state.

3. **Presentation & Livewire Layer**:
   - Update storefront Livewire SFC `resources/views/pages/storefront/⚡show.blade.php`:
     - Maintain selection state for offerings with reactive quantity adjustments.
     - Display floating summary bar when offerings are selected with total amount and action button.
     - Implement turn confirmation modal: if the visitor is unauthenticated, prompts for sign-in or quick registration; if authenticated, confirms turn creation.
     - Check for existing active tickets and redirect to the active tracking screen if a duplicate attempt is detected.
   - Create Livewire SFC `resources/views/pages/tickets/⚡show.blade.php` registered at `/tickets/{code}`:
     - Resolves ticket via route code, aborting 404 if invalid.
     - Uses `wire:poll.5s` to refresh queue status, position ahead, and estimated wait time without full page reloads.
     - Renders responsive status cards (waiting number, counter call alerts, service progress, or cancellation feedback).
     - Provides a voluntary cancellation dialog allowing the customer to release their turn.
     - Adheres strictly to Turnex semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `text-text-primary`, `border-border-subtle`).

4. **Testing & QA**:
   - Create Pest feature test suite in `tests/Feature/VirtualQueueTicketTest.php` covering:
     - Offering selection, quantity adjustments, and total price aggregation.
     - Mandatory customer authentication requirement for queue entry.
     - Daily sequential ticket numbering (`TK-001`) and reset.
     - Single active ticket enforcement per business per customer.
     - Concurrent active tickets across different venues.
     - Real-time queue position calculation and poll updates.
     - Customer voluntary cancellation flow.
     - 404 handling for invalid ticket codes.
   - Run Pint and Larastan checks via Docker.

## Decisions

- **Mandatory Customer Authentication over Anonymous Guest Entries** — In alignment with `US01`, requiring a verified customer account prevents queue inflation, malicious spam, and bot sabotage, while providing venues with real customer contact reachability.
- **Daily Sequential Reset (`TK-001` - `TK-999`)** — Numbering resets daily per business, ensuring ticket codes remain short, readable, and comfortable for counter staff and public displays.
- **Separate Secure URL Token (`ticket_code`) from Daily Number (`ticket_number`)** — Prevents sequential enumeration attacks where users manipulate the URL to inspect other patrons' orders.
- **Livewire Polling (`wire:poll.5s`) over WebSockets** — Respects project guidelines by avoiding the operational overhead of WebSocket servers (Reverb/Pusher) while delivering responsive real-time updates for waiting customers.
- **Derived Subtotal Calculation** — Maintains database normalization (3NF) by computing subtotals on the fly (`quantity * unit_price`) rather than persisting redundant calculated columns.
