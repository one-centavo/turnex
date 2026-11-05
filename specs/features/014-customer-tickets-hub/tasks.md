# 014 · Customer Tickets Hub — Tasks

## Domain & Service Layer

- [x] Enhance `App\Services\QueueService`:
  - [x] Implement `getActiveTicketsForCustomer(Customer $customer): Collection` querying active statuses (`Pending`, `Preparing`, `Ready`, `Called`, `InService`, `Skipped`).
  - [x] Implement dynamic urgency sorting (`CALLED` and `READY` first, followed by pending tickets sorted ascending by queue proximity, followed by `SKIPPED`).
  - [x] Calculate `turns_ahead` for each active ticket.
  - [x] Implement `getHistoricalTicketsForCustomer(Customer $customer, int $perPage = 10): LengthAwarePaginator` querying terminal statuses (`Completed`, `NoShow`, `Cancelled`) sorted descending by date.
  - [x] Ensure eager loading handles soft-deleted businesses and offerings gracefully.

## Layout & Navigation Layer

- [x] Create customer layout `resources/views/layouts/customer.blade.php`:
  - [x] Implement responsive shell with header and thumb-zone mobile bottom navigation bar (`Bottom Navigation Bar`).
  - [x] Add navigation tabs: "Explorar", "Mis Turnos" (active indicator), and "Mi Perfil".
  - [x] Style using Turnex semantic tokens (`bg-surface-canvas`, `bg-surface-card`, `border-border-subtle`, `text-text-primary`, `text-brand-primary`).

## Presentation & Livewire Layer

- [x] Register customer tickets route in `routes/web.php` with `auth:customer` middleware: `Route::livewire('my-tickets', 'pages::customer.tickets')->name('customer.tickets');`.
- [x] Create Livewire Single-File Component in `resources/views/pages/customer/⚡tickets.blade.php`:
  - [x] Implement tab state toggling (`$tab = 'active'` by default, supports `'history'`).
  - [x] Implement active turns container with `wire:poll.5s` periodic polling.
  - [x] Render prioritized active cards displaying business name, ticket identifier (`TK-014`), status badge, turns ahead, and total price.
  - [x] Build high-priority alert callout when ticket status is `CALLED`.
  - [x] Build informational notice when ticket status is `SKIPPED`.
  - [x] Implement resolution card for tickets that complete during the session, with an "Entendido" action to acknowledge and clear from active view.
  - [x] Build Alpine.js inline accordion (`x-data="{ expanded: false }"`) for instant itemized order breakdown.
  - [x] Add direct link to individual live tracking view (`route('tickets.show', $ticket->ticket_code)`).
  - [x] Implement empty state for active turns with action button to explore businesses.
  - [x] Build paginated history view displaying past tickets, dates, final status badge, and total price.
  - [x] Implement empty state for history tab.

## Verification & QA

- [x] Create Pest feature test suite in `tests/Feature/CustomerTicketsHubTest.php` covering:
  - [x] Unauthenticated visitor redirection to customer login.
  - [x] Default landing on Active Turns tab.
  - [x] Concurrent multi-business active ticket listing.
  - [x] Priority ordering (urgent `CALLED` and `READY` tickets surfaced before pending tickets).
  - [x] Reactive polling position and status updates.
  - [x] Notice display for postponed (`SKIPPED`) tickets.
  - [x] Completed ticket transition acknowledgment and clearing.
  - [x] Inline item breakdown expansion.
  - [x] Direct navigation link to dedicated tracking view.
  - [x] History pagination and chronological ordering.
  - [x] Resilience against soft-deleted venues or offerings in historical records.
  - [x] Empty states for both active and history tabs.
- [x] Run test suite via Docker: `docker compose exec turnex-app php artisan test --filter=CustomerTicketsHubTest --compact`.
- [x] Run full regression suite via Docker: `docker compose exec turnex-app php artisan test --compact`.
- [x] Run code style fixer via Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
- [x] Run static analysis via Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse --memory-limit=512M`.
- [x] Verify all acceptance criteria in `specs/features/014-customer-tickets-hub/spec.md` and mark `[x]`.

