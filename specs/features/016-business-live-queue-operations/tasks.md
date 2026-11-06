# 016 · Business Live Queue Operations — Tasks

Actionable, granular, and test-driven task breakdown derived from `spec.md` and `plan.md`. Check `[ ]` to `[x]` upon completion.

---

## Phase 1: Database Migrations, Models & Factories

- [x] **1.1. Create Service Timestamps Migration for `queue_tickets`**
  - Create migration `database/migrations/2026_11_06_000001_add_service_timestamps_to_queue_tickets_table.php`:
    - Add nullable timestamp column `served_at` after `called_at` (tracks counter arrival / start of service).
    - Add nullable timestamp column `completed_at` after `served_at` (tracks service completion).
    - Add nullable timestamp column `cancelled_at` after `completed_at` (tracks business cancellation or no-show).
    - Add composite index on `['business_id', 'status', 'created_at']` for query performance during periodic polling.
    - Implement `down()` rollback dropping timestamps and composite index.
  - Verification: Run `docker compose exec turnex-app php artisan migrate`.

- [x] **1.2. Create Queue Pause Flag Migration for `businesses`**
  - Create migration `database/migrations/2026_11_06_000002_add_is_queue_paused_to_businesses_table.php`:
    - Add boolean column `is_queue_paused` defaulting to `false` after `business_description`.
    - Implement `down()` rollback dropping `is_queue_paused`.
  - Verification: Run `docker compose exec turnex-app php artisan migrate`.

- [x] **1.3. Update `QueueTicket` Model**
  - In `app/Models/QueueTicket.php`:
    - Add `'served_at'`, `'completed_at'`, and `'cancelled_at'` to `#[Fillable]`.
    - Add property docblocks and casts:
      - `'served_at' => 'datetime'`
      - `'completed_at' => 'datetime'`
      - `'cancelled_at' => 'datetime'`
    - Add state check helper methods:
      - `public function isCalled(): bool` returning `$this->status === QueueTicketStatus::Called`.
      - `public function isInService(): bool` returning `$this->status === QueueTicketStatus::InService`.
      - `public function isPostponed(): bool` returning `$this->status === QueueTicketStatus::Skipped`.

- [x] **1.4. Update `Business` Model**
  - In `app/Models/Business.php`:
    - Add `'is_queue_paused'` to `#[Fillable]`.
    - Add cast `'is_queue_paused' => 'boolean'`.
    - Add helper method `public function isQueuePaused(): bool` returning `(bool) $this->is_queue_paused`.

- [x] **1.5. Enhance Factories (`QueueTicketFactory` and `BusinessFactory`)**
  - In `database/factories/QueueTicketFactory.php`:
    - Update `inService()` state to set `'served_at' => now()`.
    - Update `completed()` state to set `'completed_at' => now()`.
    - Update `noShow()` state to set `'cancelled_at' => now()`.
    - Update `cancelled()` state to set `'cancelled_at' => now()`.
  - In `database/factories/BusinessFactory.php`:
    - Add state `queuePaused()` setting `'is_queue_paused' => true`.

---

## Phase 2: Domain Logic & QueueService Methods

- [x] **2.1. Implement Spotlight Ticket Query Method**
  - In `app/Services/QueueService.php`, implement `getCurrentSpotlightTicket(Business $business): ?QueueTicket`:
    - Query active ticket for `$business->id` with status priority: `IN_SERVICE` first (actively being attended), then `CALLED`.
    - Eager load `['customer', 'items.offering' => fn ($q) => $q->withTrashed()]`.
    - Return `null` if counter is idle (no customer in called or in-service status).

- [x] **2.2. Implement FIFO `callNextTicket` Method**
  - In `app/Services/QueueService.php`, implement `callNextTicket(Business $business): ?QueueTicket`:
    - Execute within database transaction with pessimistic locking (`lockForUpdate()`).
    - Query oldest ticket with `status === QueueTicketStatus::Pending` for `$business->id`, ordered by `created_at ASC, id ASC`.
    - If found:
      - Transition status to `QueueTicketStatus::Called`.
      - Set `called_at = now()`.
      - Save ticket and return loaded model.
    - If no pending tickets exist, return `null`.

- [x] **2.3. Implement Direct Priority Calling `callSpecificTicket` Method**
  - In `app/Services/QueueService.php`, implement `callSpecificTicket(Business $business, QueueTicket $ticket): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`; throw `DomainException('Ticket does not belong to this business.')` if violated.
    - Verify `$ticket->status` is either `Pending` or `Skipped`; throw `DomainException` if ticket is in terminal or already active state.
    - Transition status to `QueueTicketStatus::Called`.
    - Set `called_at = now()`.
    - Save and return updated ticket.

- [x] **2.4. Implement Customer Re-Alert `recallTicket` Method**
  - In `app/Services/QueueService.php`, implement `recallTicket(QueueTicket $ticket, Business $business): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->status === QueueTicketStatus::Called`; throw `DomainException` if not in called status.
    - Update `called_at = now()`.
    - Save and return updated ticket.

- [x] **2.5. Implement Service Start `startService` Method**
  - In `app/Services/QueueService.php`, implement `startService(QueueTicket $ticket, Business $business): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->status === QueueTicketStatus::Called`; throw `DomainException` if not currently called.
    - Transition status to `QueueTicketStatus::InService`.
    - Set `served_at = now()`.
    - Save and return updated ticket.

- [x] **2.6. Implement Service Completion `completeTicket` Method**
  - In `app/Services/QueueService.php`, implement `completeTicket(QueueTicket $ticket, Business $business): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->status` is `InService` or `Called`; throw `DomainException` if already terminal.
    - Transition status to `QueueTicketStatus::Completed`.
    - Set `completed_at = now()`.
    - If `served_at` was null, set `served_at = now()`.
    - Save and return updated ticket.

- [x] **2.7. Implement No-Show Marking `markNoShow` Method**
  - In `app/Services/QueueService.php`, implement `markNoShow(QueueTicket $ticket, Business $business): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->status` is `Called` or `InService`; throw `DomainException` if ticket is not active.
    - Transition status to `QueueTicketStatus::NoShow`.
    - Set `cancelled_at = now()`.
    - Save and return updated ticket.

- [x] **2.8. Implement Customer Postponing `postponeTicket` Method**
  - In `app/Services/QueueService.php`, implement `postponeTicket(QueueTicket $ticket, Business $business): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->status` is `Called` or `InService`; throw `DomainException` if not active.
    - Transition status to `QueueTicketStatus::Skipped`.
    - Save and return updated ticket.

- [x] **2.9. Implement Customer Resuming `resumeTicket` Method**
  - In `app/Services/QueueService.php`, implement `resumeTicket(QueueTicket $ticket, Business $business, bool $callImmediately = true): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->status === QueueTicketStatus::Skipped`; throw `DomainException` if not skipped.
    - If `$callImmediately`:
      - Transition status to `QueueTicketStatus::Called`.
      - Set `called_at = now()`.
    - Else:
      - Transition status to `QueueTicketStatus::Pending`.
    - Save and return updated ticket.

- [x] **2.10. Implement Merchant Ticket Cancellation `cancelTicketByBusiness` Method**
  - In `app/Services/QueueService.php`, implement `cancelTicketByBusiness(QueueTicket $ticket, Business $business): QueueTicket`:
    - Verify `$ticket->business_id === $business->id`.
    - Verify `$ticket->isActive()`; throw `DomainException` if already terminal.
    - Transition status to `QueueTicketStatus::Cancelled`.
    - Set `cancelled_at = now()`.
    - Save and return updated ticket.

- [x] **2.11. Implement Queue Pause Toggle & Storefront Guard**
  - In `app/Services/QueueService.php`:
    - Implement `toggleQueuePause(Business $business): bool`:
      - Flip `is_queue_paused = ! $business->is_queue_paused`.
      - Save business and return new boolean value.
    - In existing `createTicket(Business $business, Customer $customer, array $items)`:
      - Add check: `if ($business->isQueuePaused()) { throw new DomainException('La fila se encuentra temporalmente pausada. No se reciben nuevos turnos en este momento.'); }`

- [x] **2.12. Implement Overnight-Resilient Active Queries**
  - In `app/Services/QueueService.php`:
    - Implement `getWaitingTicketsForBusiness(Business $business, ?string $search = null): Collection`:
      - Query tickets where `business_id === $business->id` and `status === QueueTicketStatus::Pending`.
      - Do NOT restrict by `created_at >= today` to support overnight venues (24-hour bars, clinics).
      - Order strictly chronological: `orderBy('created_at', 'asc')->orderBy('id', 'asc')`.
      - When `$search` is provided, filter case-insensitively by `ticket_number LIKE %term%` or customer `first_name` / `last_name LIKE %term%`.
      - Eager load `['customer', 'items.offering' => fn ($q) => $q->withTrashed()]`.
    - Implement `getPostponedTicketsForBusiness(Business $business): Collection`:
      - Query tickets where `business_id === $business->id` and `status === QueueTicketStatus::Skipped`.
      - Order chronological by `updated_at desc`.
      - Eager load relations.

- [x] **2.13. Implement Daily Queue Statistics & Finished History**
  - In `app/Services/QueueService.php`:
    - Implement `getDailyQueueStats(Business $business): array`:
      - `'waiting'`: count of all active `Pending` tickets for this business.
      - `'served_today'`: count of tickets for this business with `status === QueueTicketStatus::Completed` where `completed_at >= Carbon::today()`.
      - `'no_show_today'`: count of tickets for this business with `status === QueueTicketStatus::NoShow` where `cancelled_at >= Carbon::today()`.
    - Implement `getFinishedTicketsToday(Business $business, int $limit = 50): Collection`:
      - Query tickets where `business_id === $business->id` and `status IN (Completed, NoShow, Cancelled)` where `updated_at >= Carbon::today()`.
      - Order by `updated_at desc`.
      - Eager load `['customer', 'items.offering' => fn ($q) => $q->withTrashed()]`.

---

## Phase 3: Routing, Middleware & Navigation Integration

- [x] **3.1. Register Livewire Route & Redirect Alias in `routes/web.php`**
  - Inside the `Route::middleware(['auth:business'])` group:
    - Register Livewire component route:
      `Route::livewire('business/queue', 'pages::business.queue.index')->name('business.queue.index');`
    - Add legacy URL compatibility redirect:
      ```php
      Route::get('business/live-queue', function () {
          return redirect()->route('business.queue.index');
      });
      ```

- [x] **3.2. Update Business Sidebar Navigation**
  - In `resources/views/layouts/business/sidebar.blade.php`:
    - Update "Turnos en Vivo" navigation item:
      - `:href="route('business.queue.index')"`
      - `:current="request()->routeIs('business.queue.*') || request()->is('business/queue*') || request()->is('business/live-queue*')"`
      - Ensure `wire:navigate` remains enabled.

---

## Phase 4: Livewire Single-File Component (⚡) & UI Implementation

- [x] **4.1. Create Livewire SFC Shell at `resources/views/pages/business/queue/⚡index.blade.php`**
  - Define Livewire 4 anonymous SFC with `declare(strict_types=1);` and layout attributes:
    `new #[Title('Turnos en Vivo')] #[Layout('layouts.business')] class extends Component`
  - Declare reactive state properties:
    - `#[Url(as: 'tab')] public string $activeTab = 'waiting';` (`waiting` | `postponed` | `history`)
    - `#[Url(as: 'q')] public string $search = '';`
    - Modal confirmation flags:
      - `public bool $confirmingCallNext = false;`
      - `public ?int $pendingCallTicketId = null;`
      - `public ?int $confirmingNoShowTicketId = null;`
      - `public ?int $confirmingCancelTicketId = null;`
    - Notice strings:
      - `public ?string $feedbackMessage = null;`
      - `public ?string $errorMessage = null;`
  - Computed properties:
    - `business()`: Authenticated business via `Auth::guard('business')->user()`.
    - `spotlightTicket()`: via `QueueService::getCurrentSpotlightTicket()`.
    - `waitingTickets()`: via `QueueService::getWaitingTicketsForBusiness()`.
    - `postponedTickets()`: via `QueueService::getPostponedTicketsForBusiness()`.
    - `finishedTickets()`: via `QueueService::getFinishedTicketsToday()`.
    - `stats()`: via `QueueService::getDailyQueueStats()`.
  - Add native Livewire polling directive `wire:poll.5s` to the outer container.

- [x] **4.2. Build Header, Daily Stats Bar & Queue Pause Switch**
  - Page header with title "Gestión de Turnos en Vivo" and business operational subtitle.
  - Queue reception pause switch:
    - Toggle button or `<flux:switch>` bound to `toggleQueueReception()` action.
    - Dynamic visual badge:
      - Green indicator: "Fila Abierta (Recibiendo turnos)".
      - Amber indicator: "Fila Pausada (No recibe nuevos turnos)".
  - 3-column daily metrics summary bar styled with semantic tokens (`bg-surface-card`, `border-border-subtle`, `text-text-primary`):
    - "En Espera": count of waiting customers.
    - "Atendidos Hoy": count of completed customers today.
    - "No se Presentaron": count of no-show customers today.

- [x] **4.3. Build Main Service Spotlight Hero Section**
  - **Idle Counter State (Counter Free)**:
    - Rendered when `$this->spotlightTicket === null`.
    - Clean dashed card container (`border-dashed border-border-strong bg-surface-card rounded-2xl p-8 text-center`).
    - Megaphone icon, title "Mostrador disponible", and description "No hay ningún cliente en atención en este momento."
    - Primary CTA: `<flux:button variant="primary" size="lg" icon="megaphone" wire:click="callNext">Llamar Siguiente</flux:button>`.
    - Informational toast/notice when clicked and line is empty: "No hay clientes en espera".
  - **Active Service State (Customer Called or In-Service)**:
    - Rendered when `$this->spotlightTicket !== null`.
    - Distinctive container styling:
      - Amber border/pulse when status is `CALLED`.
      - Emerald border when status is `IN_SERVICE`.
    - Header: Monospace turn identifier (e.g. `TK-004`) in `text-3xl sm:text-4xl font-extrabold`, customer full name, and clickable phone number link (`tel:...`).
    - Real-time timers:
      - Wait duration before call (`created_at` to `called_at`).
      - Time elapsed since called (`called_at->diffForHumans()`).
      - Service duration timer when in service (`served_at->diffForHumans()`).
    - Itemized order breakdown box:
      - Clean nested card with each requested product/service, quantity, unit price, and formatted grand total.
    - Action Toolbar (Ergonomic Touch Targets):
      - When `CALLED`:
        - `<flux:button variant="primary" icon="play" wire:click="startService">Iniciar Atención</flux:button>`
        - `<flux:button variant="outline" icon="bell" wire:click="recallCustomer">Volver a llamar</flux:button>`
        - `<flux:button variant="subtle" icon="pause" wire:click="postponeCustomer">Posponer</flux:button>`
        - `<flux:button variant="danger" icon="user-minus" wire:click="promptNoShow({{ $spotlightTicket->id }})">No se presentó</flux:button>`
      - When `IN_SERVICE`:
        - `<flux:button variant="primary" color="emerald" icon="check-circle" wire:click="completeService">Completar Atención</flux:button>`
        - `<flux:button variant="subtle" icon="pause" wire:click="postponeCustomer">Posponer</flux:button>`
        - `<flux:button variant="danger" icon="x-mark" wire:click="promptCancel({{ $spotlightTicket->id }})">Cancelar</flux:button>`

- [x] **4.4. Build Real-Time Search Bar & Tab Navigation**
  - Quick search input:
    - `<flux:input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por número de turno o nombre de cliente..." icon="magnifying-glass" clearable />`
    - Real-time filtering without page reloads.
  - Tab navigation bar:
    - Tab 1: "En Espera" with badge count `{{ $this->waitingTickets->count() }}`.
    - Tab 2: "Pospuestos" with badge count `{{ $this->postponedTickets->count() }}`.
    - Tab 3: "Historial de Hoy" with badge count `{{ $this->finishedTickets->count() }}`.
    - Tab state switches `$activeTab` and updates URL query string seamlessly.

- [x] **4.5. Build "En Espera" Waiting Queue Tab UI**
  - Rendered when `$activeTab === 'waiting'`.
  - Chronological list of pending tickets:
    - Queue position index badge (`#1`, `#2`, ...).
    - Ticket number and customer name.
    - Elapsed wait time badge (e.g. "Espera: 12 min").
    - Compact order summary (quantities and total price).
    - Action buttons per card:
      - Priority "Llamar": Calls `callSpecific({{ $ticket->id }})` to jump line for priority patrons.
      - "Cancelar": Calls `promptCancel({{ $ticket->id }})`.
  - Empty state when no waiting tickets exist:
    - Icon, message "No hay clientes en espera en este momento", and reminder that new turns appear automatically.

- [x] **4.6. Build "Pospuestos" Tab UI**
  - Rendered when `$activeTab === 'postponed'`.
  - List of skipped tickets:
    - Ticket number, customer name, phone number, and items requested.
    - Badge "Pospuesto".
    - Primary CTA: `<flux:button variant="primary" size="sm" icon="arrow-path" wire:click="resumePostponed({{ $ticket->id }})">Reanudar</flux:button>` returning the customer to the counter.
  - Empty state when no postponed customers exist:
    - Clean illustration and copy "No hay turnos pospuestos".

- [x] **4.7. Build "Historial de Hoy" Tab UI**
  - Rendered when `$activeTab === 'history'`.
  - Chronological descending log of today's terminal tickets:
    - Ticket number, customer name, and time of completion/cancellation.
    - Status badge:
      - Emerald for "Completado".
      - Amber/Zinc for "No se presentó".
      - Rose for "Cancelado".
    - Total order amount.
  - Empty state when no tickets completed yet today.

- [x] **4.8. Implement Safety Confirmation Modals & Touch Feedback**
  - 1. Modal `confirm-call-modal`:
    - Displayed when calling next or specific customer while spotlight is already occupied.
    - Clear warning in Spanish that current customer will be unassigned.
    - Actions: "Volver al mostrador" (dismiss) and "Continuar y Llamar" (executes `confirmCallNext()`).
  - 2. Modal `confirm-no-show-modal`:
    - Displayed when marking customer as absent (`promptNoShow`).
    - Actions: "Cancelar" and "Confirmar No Asistencia" (executes `confirmNoShow()`).
  - 3. Modal `confirm-cancel-modal`:
    - Displayed when cancelling a waiting or active ticket (`promptCancel`).
    - Actions: "Volver" and "Confirmar Cancelación" (executes `confirmCancel()`).
  - Mobile touch ergonomics: min 44px touch targets on buttons, responsive layout adapting from mobile stack to tablet/desktop split view.

---

## Phase 5: Automated Testing Suite with Pest

- [x] **5.1. Create Domain Service Feature Tests in `tests/Feature/BusinessLiveQueueServiceTest.php`**
  - Test 1: FIFO ordering in `callNextTicket` (returns the oldest pending ticket in chronological order).
  - Test 2: `callNextTicket` returns `null` when queue has no pending tickets.
  - Test 3: `callSpecificTicket` calls a chosen waiting ticket and updates `called_at`.
  - Test 4: `callSpecificTicket` rejects tickets belonging to another business with `DomainException`.
  - Test 5: `recallTicket` updates `called_at` for an already called ticket.
  - Test 6: `startService` transitions status from `Called` to `InService` and sets `served_at`.
  - Test 7: `completeTicket` transitions status to `Completed` and sets `completed_at`.
  - Test 8: `markNoShow` transitions status to `NoShow` and sets `cancelled_at`.
  - Test 9: `postponeTicket` transitions status to `Skipped`.
  - Test 10: `resumeTicket` restores a skipped ticket to `Called` or `Pending`.
  - Test 11: `cancelTicketByBusiness` transitions active ticket to `Cancelled` and sets `cancelled_at`.
  - Test 12: `toggleQueuePause` flips `is_queue_paused` boolean on business.
  - Test 13: `createTicket` throws `DomainException` when business queue is paused.
  - Test 14: Overnight resilience: tickets created before midnight (23:55) remain active, searchable, and callable after 00:05.
  - Test 15: `getDailyQueueStats` correctly calculates waiting, served today, and no-shows today.
  - Test 16: Multi-tenant boundary isolation: Business A operations cannot alter Business B tickets.

- [x] **5.2. Create Livewire Component Feature Tests in `tests/Feature/BusinessLiveQueueTest.php`**
  - Test 1: Guest or customer authentication is blocked; redirected to `business.login`.
  - Test 2: Authenticated business accesses `/business/queue` with HTTP 200 and sees queue cockpit.
  - Test 3: Idle counter renders "Mostrador disponible" when no customer is called/in service.
  - Test 4: Clicking "Llamar Siguiente" when line is empty displays "No hay clientes en espera".
  - Test 5: Clicking "Llamar Siguiente" with waiting customers calls oldest customer into spotlight.
  - Test 6: Calling another customer while spotlight is occupied opens confirmation modal without unseating current customer.
  - Test 7: Confirming call from modal unseats current customer and calls the new customer.
  - Test 8: Clicking "Iniciar Atención" changes spotlight badge to `En atención` and sets `served_at`.
  - Test 9: Clicking "Completar Atención" finishes customer, records `completed_at`, and clears spotlight.
  - Test 10: Clicking "Volver a llamar" refreshes `called_at` timestamp.
  - Test 11: Clicking "Posponer" moves customer to postponed list and frees spotlight.
  - Test 12: Reanudar from postponed tab recalls customer to counter.
  - Test 13: Marking customer "No se presentó" requires confirmation and transitions to `NO_SHOW`.
  - Test 14: Cancelling ticket from waiting list requires confirmation and removes customer from active queue.
  - Test 15: Real-time search filters waiting list reactively by ticket number or customer name.
  - Test 16: Tab switching between "En Espera", "Pospuestos", and "Historial de Hoy" renders correct datasets.
  - Test 17: Toggling queue pause updates business state and toggles UI indicator.
  - Test 18: Customer voluntary self-cancellation from phone removes them from operator waiting list on refresh/poll.
  - Test 19: Legacy URL `/business/live-queue` redirects to `/business/queue`.

---

## Phase 6: Code Quality, Styling & Verification

- [x] **6.1. Code Formatting with Pint**
  - Run Pint inside Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
  - Ensure zero formatting violations.

- [x] **6.2. Static Analysis with Larastan**
  - Run Larastan inside Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse`.
  - Ensure zero errors and full strict typing compliance.

- [x] **6.3. Execute Feature and Regression Test Suites**
  - Run service test suite: `docker compose exec turnex-app php artisan test --filter=BusinessLiveQueueServiceTest --compact`.
  - Run component test suite: `docker compose exec turnex-app php artisan test --filter=BusinessLiveQueueTest --compact`.
  - Run entire application test suite: `docker compose exec turnex-app php artisan test --compact`.
  - Ensure 100% green tests with zero regressions.

- [x] **6.4. Design Tokens & Linguistic Consistency Audit**
  - Verify zero arbitrary Tailwind classes (`class-[...]`, `text-[#...]`, `bg-[#...]`).
  - Verify all classes utilize semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `border-border-subtle`, `text-text-primary`, `text-brand-primary`).
  - Verify all code, methods, classes, database columns, and comments are strictly in English.
  - Verify all user-facing UI labels, headings, buttons, and alerts are strictly in natural Spanish.
  - Cross-check and validate against all 28 acceptance criteria in `specs/features/016-business-live-queue-operations/spec.md`.
