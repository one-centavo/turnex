# 014 · Customer Tickets Hub — Tasks

## Domain & Service Layer

- [ ] Enhance `App\Services\QueueService`:
  - [ ] Implement `getActiveTicketsForCustomer(Customer $customer): Collection` querying active statuses (`Pending`, `Preparing`, `Ready`, `Called`, `InService`, `Skipped`).
  - [ ] Implement dynamic urgency sorting (`CALLED` and `READY` first, followed by pending tickets sorted ascending by queue proximity, followed by `SKIPPED`).
  - [ ] Calculate `turns_ahead` for each active ticket.
  - [ ] Implement `getHistoricalTicketsForCustomer(Customer $customer, int $perPage = 10): LengthAwarePaginator` querying terminal statuses (`Completed`, `NoShow`, `Cancelled`) sorted descending by date.
  - [ ] Ensure eager loading handles soft-deleted businesses and offerings gracefully.

## Layout & Navigation Layer

- [ ] Create customer layout `resources/views/layouts/customer.blade.php`:
  - [ ] Implement responsive shell with header and thumb-zone mobile bottom navigation bar (`Bottom Navigation Bar`).
  - [ ] Add navigation tabs: "Explorar", "Mis Turnos" (active indicator), and "Mi Perfil".
  - [ ] Style using Turnex semantic tokens (`bg-surface-canvas`, `bg-surface-card`, `border-border-subtle`, `text-text-primary`, `text-brand-primary`).

## Presentation & Livewire Layer

- [ ] Register customer tickets route in `routes/web.php` with `auth:customer` middleware: `Route::livewire('my-tickets', 'pages::customer.tickets')->name('customer.tickets');`.
- [ ] Create Livewire Single-File Component in `resources/views/pages/customer/⚡tickets.blade.php`:
  - [ ] Implement tab state toggling (`$tab = 'active'` by default, supports `'history'`).
  - [ ] Implement active turns container with `wire:poll.5s` periodic polling.
  - [ ] Render prioritized active cards displaying business name, ticket identifier (`TK-014`), status badge, turns ahead, and total price.
  - [ ] Build high-priority alert callout when ticket status is `CALLED`.
  - [ ] Build informational notice when ticket status is `SKIPPED`.
  - [ ] Implement resolution card for tickets that complete during the session, with an "Entendido" action to acknowledge and clear from active view.
  - [ ] Build Alpine.js inline accordion (`x-data="{ expanded: false }"`) for instant itemized order breakdown.
  - [ ] Add direct link to individual live tracking view (`route('tickets.show', $ticket->ticket_code)`).
  - [ ] Implement empty state for active turns with action button to explore businesses.
  - [ ] Build paginated history view displaying past tickets, dates, final status badge, and total price.
  - [ ] Implement empty state for history tab.

## Verification & QA

- [ ] Create Pest feature test suite in `tests/Feature/CustomerTicketsHubTest.php` covering:
  - [ ] Unauthenticated visitor redirection to customer login.
  - [ ] Default landing on Active Turns tab.
  - [ ] Concurrent multi-business active ticket listing.
  - [ ] Priority ordering (urgent `CALLED` and `READY` tickets surfaced before pending tickets).
  - [ ] Reactive polling position and status updates.
  - [ ] Notice display for postponed (`SKIPPED`) tickets.
  - [ ] Completed ticket transition acknowledgment and clearing.
  - [ ] Inline item breakdown expansion.
  - [ ] Direct navigation link to dedicated tracking view.
  - [ ] History pagination and chronological ordering.
  - [ ] Resilience against soft-deleted venues or offerings in historical records.
  - [ ] Empty states for both active and history tabs.
- [ ] Run test suite via Docker: `docker compose exec turnex-app php artisan test --filter=CustomerTicketsHubTest --compact`.
- [ ] Run full regression suite via Docker: `docker compose exec turnex-app php artisan test --compact`.
- [ ] Run code style fixer via Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
- [ ] Run static analysis via Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse --memory-limit=512M`.
- [ ] Verify all acceptance criteria in `specs/features/014-customer-tickets-hub/spec.md` and mark `[x]`.

