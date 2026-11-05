# 014 · Customer Tickets Hub — Plan

## Approach

Implement a centralized, mobile-first Livewire Single-File Component (`resources/views/pages/customer/⚡tickets.blade.php`) accessible at `/my-tickets` for authenticated customers. The interface leverages a dedicated customer layout (`layouts/customer.blade.php`) equipped with a persistent mobile bottom navigation bar adhering strictly to Turnex semantic tokens.

The tickets hub delegates domain querying and position calculations to `App\Services\QueueService`, which organizes active tickets into a dynamic urgency-sorted collection and serves terminal tickets through a paginated history query. Active ticket tracking utilizes lightweight Livewire polling (`wire:poll.5s`) for real-time synchronization, paired with Alpine.js for zero-latency inline order breakdown expansion and graceful client-side acknowledgment when tickets complete.

## Implementation

1. **Domain Service Enhancements (`App\Services\QueueService`)**:
   - `getActiveTicketsForCustomer(Customer $customer): Collection`:
     - Queries tickets in active statuses (`Pending`, `Preparing`, `Ready`, `Called`, `InService`, `Skipped`).
     - Eager loads `business` and `items.offering` (resilient to soft-deleted entities).
     - Augments each ticket with calculated line position (`turns_ahead`).
     - Sorts results by urgency: highest priority to actionable states (`Called`, `Ready`), followed by pending tickets sorted ascending by queue proximity, followed by `Skipped`.
   - `getHistoricalTicketsForCustomer(Customer $customer, int $perPage = 10): LengthAwarePaginator`:
     - Queries tickets in terminal statuses (`Completed`, `NoShow`, `Cancelled`).
     - Eager loads `business` and `items.offering`.
     - Orders records chronologically descending by `created_at`.

2. **Customer Layout & Navigation (`resources/views/layouts/customer.blade.php`)**:
   - Build responsive layout with header and persistent mobile bottom navigation bar (`Bottom Navigation Bar`).
   - Include navigation items: "Explorar", "Mis Turnos" (active indicator), and "Mi Perfil".
   - Styled with Turnex semantic tokens (`bg-surface-canvas`, `bg-surface-card`, `border-border-subtle`, `text-text-primary`, `text-brand-primary`).

3. **Presentation & Livewire Layer**:
   - Register route in `routes/web.php`:
     - `Route::middleware('auth:customer')->group(...)` registering `Route::livewire('my-tickets', 'pages::customer.tickets')->name('customer.tickets');`.
   - Create SFC `resources/views/pages/customer/⚡tickets.blade.php`:
     - Property `$tab = 'active'` (supports toggling between `'active'` and `'history'`).
     - Property `$acknowledgedTickets = []` (tracks IDs of tickets acknowledged upon completion).
     - Active Turns View:
       - Uses `wire:poll.5s` on the active turns list container.
       - Renders prioritized cards with venue logo/name, ticket number, status badge, line position, and total price.
       - High-visibility callout banner when status is `CALLED`.
       - Informational notice when status is `SKIPPED`.
       - Transition banner when a ticket completes during the session, offering an "Entendido" button to acknowledge and clear from active view.
       - Alpine.js collapsible accordion (`x-data="{ expanded: false }"`) for instant inline inspection of items, quantities, and prices.
       - Action link to open full dedicated tracking view (`route('tickets.show', $ticket->ticket_code)`).
       - Empty state when no active turns exist, with a button to explore businesses.
     - History View:
       - Paginated list of historical tickets.
       - Final status badge, date, total price, and items summary.
       - Empty state when no history exists.

4. **Testing & QA**:
   - Create Pest feature test suite in `tests/Feature/CustomerTicketsHubTest.php` covering:
     - Authentication gate (guest redirected to login).
     - Default landing on Active Turns tab.
     - Multi-business active ticket listing.
     - Priority ordering (called/ready tickets placed at the top).
     - Polling position and status updates.
     - Skipped turn notification display.
     - Completed ticket transition acknowledgment.
     - Inline item expansion data visibility.
     - History pagination and chronological ordering.
     - Resilience against soft-deleted venues or offerings in historical records.
     - Empty states for both active and history tabs.
   - Run Pint and PHPStan static analysis inside Docker.

## Decisions

- **Single-File Component with Client Tab Toggling over Separate URLs** — Keeps tab switching between active turns and history instantaneous on mobile devices without full browser reloads, improving perceived speed.
- **Alpine.js Inline Accordion over Compulsory Navigation** — Empowers customers holding multiple tickets to inspect order contents inline without leaving the hub or losing sight of concurrent queue positions.
- **Client-side Acknowledgment Pattern for Finalized Tickets** — Prevents active tickets from disappearing abruptly upon merchant completion while strictly preserving the merchant as the sole authority of service completion.
- **Resilient Historical Eager Loading (`withTrashed()`)** — Safeguards the history view against broken object references if an establishment or offering is subsequently deleted or archived.

## Risks

- **Polling Load Amplification with Multiple Concurrent Tickets** — If a customer holds several active tickets, calculating individual positions during each poll could multiply queries. Mitigated by eager loading relationships and executing efficient, indexed count queries for positions.
- **History Growth on Heavy Accounts** — Unbounded database querying for long-time customers could degrade memory and response times. Mitigated by strict pagination (`LengthAwarePaginator`).

