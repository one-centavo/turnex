# 016 · Business Live Queue Operations — Plan

Comprehensive technical implementation plan for the Business Live Queue Operations feature (`016-business-live-queue-operations`). This plan adheres strictly to Turnex architectural guidelines, PHP 8.4 strict typing, Laravel 13 standards, Livewire 4 Single-File Component (`⚡`) conventions, Flux UI 2 components, semantic Tailwind CSS 4 design tokens, and Docker-mandatory execution constraints.

---

## 1. Technical Architecture & Approach

This feature introduces an operator-facing live queue management cockpit at `/business/queue` within the authenticated business dashboard. It empowers merchant staff (cashiers, barbers, receptionists, healthcare assistants, clerks) to orchestrate their active line in real time, call patrons to the counter, track actual service durations, manage postponed tickets, and toggle queue reception.

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         Authenticated Business Staff                     │
│                        (Guard: auth:business)                            │
└────────────────────────────────────┬─────────────────────────────────────┘
                                     │
                                     ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ /business/queue (routes/web.php)                                         │
│ Livewire 4 SFC: pages::business.queue.index                              │
│ (resources/views/pages/business/queue/⚡index.blade.php)                 │
│ Dynamic Refresh: wire:poll.5s                                            │
└────────┬───────────────────────────┬──────────────────────────┬──────────┘
         │                           │                          │
         ▼                           ▼                          ▼
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│ Service Hero     │       │ Tabbed Queue     │       │ Reception Switch │
│ Spotlight        │       │ Management       │       │ & Daily Metrics  │
│ - CALLED         │       │ - En Espera      │       │ - Pausar Fila    │
│ - IN_SERVICE     │       │ - Pospuestos     │       │ - Totales de Hoy │
│ - Order Items    │       │ - Historial Hoy  │       │ - Resumen Diario │
│ - Timers & CTAs  │       │ - Search Filter  │       │                  │
└────────┬─────────┘       └─────────┬────────┘       └─────────┬────────┘
         │                           │                          │
         └───────────────────────────┼──────────────────────────┘
                                     │
                                     ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ Domain Service: App\Services\QueueService                                │
│ - callNextTicket()          - postponeTicket()      - toggleQueuePause() │
│ - callSpecificTicket()      - resumeTicket()        - getLiveQueue()     │
│ - recallTicket()            - cancelByBusiness()    - getSpotlight()     │
│ - startService()            - completeTicket()      - getDailyStats()    │
│ - markNoShow()                                                           │
└────────────────────────────────────┬─────────────────────────────────────┘
                                     │
                                     ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ Database Layer (MySQL 8.4 via Docker)                                    │
│ - businesses (is_queue_paused: bool)                                     │
│ - queue_tickets (called_at, served_at, completed_at, cancelled_at)       │
│ - queue_ticket_items (snapshots: quantity, unit_price)                   │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Schema & Persistence Layer

### 2.1 Table Alterations: `queue_tickets`

The `queue_tickets` table currently possesses `called_at`, but lacks dedicated timestamps to track the transition from counter arrival to service completion and terminal closure. A new migration will add these nullable timestamp columns:

- `served_at` (`timestamp`, nullable): Recorded when the operator clicks **"Iniciar Atención"**, transitioning the ticket from `CALLED` to `IN_SERVICE`. Enables calculation of true service duration (`completed_at - served_at`) distinct from waiting room delay (`served_at - called_at`).
- `completed_at` (`timestamp`, nullable): Recorded when the operator clicks **"Completar Atención"**, concluding the customer visit.
- `cancelled_at` (`timestamp`, nullable): Recorded when a ticket is cancelled by business staff, cancelled by the customer, or marked as `NO_SHOW`.

```php
// database/migrations/2026_11_06_000001_add_service_timestamps_to_queue_tickets_table.php
Schema::table('queue_tickets', function (Blueprint $table): void {
    $table->timestamp('served_at')->nullable()->after('called_at');
    $table->timestamp('completed_at')->nullable()->after('served_at');
    $table->timestamp('cancelled_at')->nullable()->after('completed_at');

    $table->index(['business_id', 'status', 'created_at']);
});
```

### 2.2 Table Alterations: `businesses`

To fulfill the queue pause requirement ("Pausar Fila"):

- `is_queue_paused` (`boolean`, default `false`): Flag indicating whether the business is temporarily rejecting new turn requests from public storefronts (`/b/{slug}`) while continuing to attend patrons already waiting in line.

```php
// database/migrations/2026_11_06_000002_add_is_queue_paused_to_businesses_table.php
Schema::table('businesses', function (Blueprint $table): void {
    $table->boolean('is_queue_paused')->default(false)->after('business_description');
});
```

### 2.3 Model Enhancements

1. **`App\Models\QueueTicket`**:
   - Add `'served_at'`, `'completed_at'`, `'cancelled_at'` to `#[Fillable]`.
   - Add casts:
     ```php
     'served_at' => 'datetime',
     'completed_at' => 'datetime',
     'cancelled_at' => 'datetime',
     ```
   - Helper methods:
     ```php
     public function isCalled(): bool
     public function isInService(): bool
     public function isPostponed(): bool
     ```

2. **`App\Models\Business`**:
   - Add `'is_queue_paused'` to `#[Fillable]`.
   - Add cast: `'is_queue_paused' => 'boolean'`.
   - Add helper: `public function isQueuePaused(): bool { return (bool) $this->is_queue_paused; }`

3. **`Database\Factories\QueueTicketFactory`**:
   - Update factory states (`inService()`, `completed()`, `cancelled()`, `noShow()`) to populate appropriate timestamps automatically during test setup.

---

## 3. Domain & Service Layer (`App\Services\QueueService`)

All business logic, multi-tenant scoping, authorization guards, and status transitions reside in `App\Services\QueueService`. Livewire components must never execute raw database updates or status changes directly.

### 3.1 State Machine Transitions

```
                 ┌────────────────────────────────────────────────┐
                 │               PENDING (En espera)              │
                 └───────┬───────────────────────────────┬────────┘
                         │                               │
         callNextTicket()│           cancelTicketByBusiness()
     callSpecificTicket()│                               │
                         ▼                               ▼
                 ┌───────────────┐               ┌───────────────┐
      ┌─────────►│ CALLED        │               │ CANCELLED     │
      │          │ (Llamado)     │               │ (Cancelado)   │
      │          └───────┬───────┘               └───────────────┘
      │                  │
      │ recallTicket()   ├───────────────────────────────┐
      │ (reset called_at)│                               │
      │                  │                               │
      │ postponeTicket() │                               │
      │                  ▼                               ▼
┌─────┴─────────┐ ┌───────────────┐              ┌───────────────┐
│ SKIPPED       │ │ IN_SERVICE    │              │ NO_SHOW       │
│ (Pospuesto)   │ │ (En atención) │              │ (No se        │
└───────────────┘ └───────┬───────┘              │  presentó)    │
  ▲                       │                      └───────────────┘
  │ resumeTicket()        │ completeTicket()       ▲           ▲
  └───────────────────────┼────────────────────────┘           │
                          ▼                                    │
                  ┌───────────────┐                            │
                  │ COMPLETED     │                            │
                  │ (Completado)  │                            │
                  └───────────────┘                            │
                          │                                    │
                          └──── markNoShow() (from called) ────┘
```

### 3.2 Service Methods Specification

```php
/**
 * Retrieve the active ticket currently being served or called in the counter spotlight.
 * Priority: IN_SERVICE first (actively attended), then CALLED.
 */
public function getCurrentSpotlightTicket(Business $business): ?QueueTicket;

/**
 * Call the next waiting customer in chronological order (FIFO).
 * Returns null if no customers are waiting.
 *
 * @throws DomainException if business queue isolation fails.
 */
public function callNextTicket(Business $business): ?QueueTicket;

/**
 * Call a specific ticket directly from the waiting list (e.g. priority care).
 *
 * @throws DomainException if ticket does not belong to business or is not in Pending/Skipped state.
 */
public function callSpecificTicket(Business $business, QueueTicket $ticket): QueueTicket;

/**
 * Re-alert a currently called customer, updating called_at to the current timestamp.
 *
 * @throws DomainException if ticket is not in CALLED state or belongs to another business.
 */
public function recallTicket(QueueTicket $ticket, Business $business): QueueTicket;

/**
 * Mark a called customer as arrived at the counter and begin service.
 * Transitions status from CALLED to IN_SERVICE and sets served_at = now().
 *
 * @throws DomainException if ticket is not in CALLED state.
 */
public function startService(QueueTicket $ticket, Business $business): QueueTicket;

/**
 * Conclude the customer service visit.
 * Transitions status from IN_SERVICE (or CALLED) to COMPLETED and sets completed_at = now().
 *
 * @throws DomainException if ticket is not in an active actionable state.
 */
public function completeTicket(QueueTicket $ticket, Business $business): QueueTicket;

/**
 * Mark a called customer as absent after failing to arrive at the counter.
 * Transitions status to NO_SHOW and sets cancelled_at = now().
 *
 * @throws DomainException if ticket is not in CALLED or IN_SERVICE state.
 */
public function markNoShow(QueueTicket $ticket, Business $business): QueueTicket;

/**
 * Temporarily postpone a customer who is not ready at the counter.
 * Transitions status to SKIPPED, freeing the counter spotlight.
 *
 * @throws DomainException if ticket is not in CALLED or IN_SERVICE state.
 */
public function postponeTicket(QueueTicket $ticket, Business $business): QueueTicket;

/**
 * Resume a postponed customer back to the counter (CALLED) or to the waiting line.
 *
 * @throws DomainException if ticket is not in SKIPPED state.
 */
public function resumeTicket(QueueTicket $ticket, Business $business, bool $callImmediately = true): QueueTicket;

/**
 * Cancel an active waiting ticket on behalf of the merchant.
 * Transitions status to CANCELLED and sets cancelled_at = now().
 *
 * @throws DomainException if ticket is already terminal or belongs to another business.
 */
public function cancelTicketByBusiness(QueueTicket $ticket, Business $business): QueueTicket;

/**
 * Toggle the queue reception pause status for the business.
 * Returns the new boolean state of is_queue_paused.
 */
public function toggleQueuePause(Business $business): bool;

/**
 * Retrieve all pending tickets waiting in line for the business, with optional search filter.
 * Preserves chronological order (created_at ASC, id ASC) regardless of midnight crossing.
 *
 * @return Collection<int, QueueTicket>
 */
public function getWaitingTicketsForBusiness(Business $business, ?string $search = null): Collection;

/**
 * Retrieve all postponed tickets (SKIPPED) for the business.
 *
 * @return Collection<int, QueueTicket>
 */
public function getPostponedTicketsForBusiness(Business $business): Collection;

/**
 * Compute daily summary metrics for the business dashboard.
 * - waiting: all currently pending tickets (active across midnight).
 * - served_today: completed tickets where completed_at >= today (or updated_at >= today).
 * - no_show_today: no-show tickets where cancelled_at >= today (or updated_at >= today).
 *
 * @return array{waiting: int, served_today: int, no_show_today: int}
 */
public function getDailyQueueStats(Business $business): array;

/**
 * Retrieve the log of tickets finalized today for the business (Completed, NoShow, Cancelled).
 *
 * @return Collection<int, QueueTicket>
 */
public function getFinishedTicketsToday(Business $business, int $limit = 50): Collection;
```

### 3.3 Guarding Queue Creation when Paused

In `QueueService::createTicket`:
```php
if ($business->is_queue_paused) {
    throw new DomainException('La fila se encuentra temporalmente pausada. No se reciben nuevos turnos en este momento.');
}
```
Existing waiting patrons continue to be attended, but new entries are blocked immediately.

### 3.4 Overnight Resilience (24-Hour Establishments)

Many establishments (such as bars, late-night diners, emergency clinics, and veterinary centers) operate continuously across midnight.
- Active queue queries (`getWaitingTicketsForBusiness`, `getCurrentSpotlightTicket`, `getPostponedTicketsForBusiness`) filter **exclusively by status** (`Pending`, `Called`, `InService`, `Skipped`), never bounding by `created_at >= today`.
- This ensures customers who took a ticket at 23:45 remain visible and actionable at 00:15 without being cleared or reset.
- Ticket numbering (`generateTicketNumber`) resets daily at midnight based on `created_at >= todayStart`, preventing collisions while preserving active turns.

---

## 4. Livewire 4 Component Architecture

### 4.1 Component Specifications
- **File Location:** `resources/views/pages/business/queue/⚡index.blade.php`
- **Class Model:** Livewire 4 Anonymous Single-File Component (`new #[Title('Turnos en Vivo')] #[Layout('layouts.business')] class extends Component`)
- **Polling:** Native Livewire polling container with `wire:poll.5s` ensuring background updates without page reloads.

### 4.2 Component State & Properties

```php
// URL-synchronized properties
#[Url(as: 'tab')]
public string $activeTab = 'waiting'; // 'waiting' | 'postponed' | 'history'

#[Url(as: 'q')]
public string $search = '';

// Confirmation state management
public bool $confirmingCallNext = false;
public ?int $pendingCallTicketId = null;
public ?int $confirmingNoShowTicketId = null;
public ?int $confirmingCancelTicketId = null;

// Error & feedback notices
public ?string $feedbackMessage = null;
public ?string $errorMessage = null;
```

### 4.3 Computed Properties

To maintain clean separation and avoid repetitive database hits:
```php
#[Computed]
public function business(): Business
{
    /** @var Business */
    return Auth::guard('business')->user();
}

#[Computed]
public function spotlightTicket(): ?QueueTicket
{
    return app(QueueService::class)->getCurrentSpotlightTicket($this->business);
}

#[Computed]
public function waitingTickets(): Collection
{
    return app(QueueService::class)->getWaitingTicketsForBusiness($this->business, $this->search);
}

#[Computed]
public function postponedTickets(): Collection
{
    return app(QueueService::class)->getPostponedTicketsForBusiness($this->business);
}

#[Computed]
public function finishedTickets(): Collection
{
    return app(QueueService::class)->getFinishedTicketsToday($this->business);
}

#[Computed]
public function stats(): array
{
    return app(QueueService::class)->getDailyQueueStats($this->business);
}
```

### 4.4 User Actions

1. `callNext(): void`:
   - If `$this->spotlightTicket !== null`, sets `$this->confirmingCallNext = true` to prevent accidental abandonment of the current customer.
   - If counter is free, executes `QueueService::callNextTicket()`.
   - If no patrons are waiting, displays Flux toast / notice: _"No hay clientes en espera"_.

2. `confirmCallNext(): void`:
   - Closes confirmation modal.
   - If `$this->pendingCallTicketId` is set, calls `callSpecificTicket()`. Otherwise calls `callNextTicket()`.
   - Resets confirmation flags.

3. `callSpecific(int $ticketId): void`:
   - If spotlight is occupied, prompts confirmation modal with `$this->pendingCallTicketId = $ticketId`.
   - If counter is free, calls `QueueService::callSpecificTicket()` directly.

4. `recallCustomer(): void`:
   - Calls `QueueService::recallTicket($this->spotlightTicket, $this->business)`.
   - Triggers customer alert refresh and emits toast _"Llamado nuevamente al cliente"_.

5. `startService(): void`:
   - Calls `QueueService::startService($this->spotlightTicket, $this->business)`.
   - Moves status to `IN_SERVICE`, begins live service timer.

6. `completeService(): void`:
   - Calls `QueueService::completeTicket($this->spotlightTicket, $this->business)`.
   - Clears spotlight area and emits success toast _"Atención completada con éxito"_.

7. `postponeCustomer(): void`:
   - Calls `QueueService::postponeTicket($this->spotlightTicket, $this->business)`.
   - Clears spotlight and adds to postponed tab.

8. `resumePostponed(int $ticketId): void`:
   - Resumes customer to `CALLED` status via `QueueService::resumeTicket()`.

9. `promptNoShow(int $ticketId): void`:
   - Sets `$this->confirmingNoShowTicketId = $ticketId` and shows confirmation modal.

10. `confirmNoShow(): void`:
    - Calls `QueueService::markNoShow()`.
    - Resets modal state.

11. `promptCancel(int $ticketId): void`:
    - Sets `$this->confirmingCancelTicketId = $ticketId` and shows confirmation modal.

12. `confirmCancel(): void`:
    - Calls `QueueService::cancelTicketByBusiness()`.
    - Resets modal state.

13. `toggleQueueReception(): void`:
    - Calls `QueueService::toggleQueuePause($this->business)`.
    - Refreshes `$this->business`.

---

## 5. UI Layout, Composition & Semantic Design Tokens

All UI markup strictly adheres to Turnex frontend rules:
- Strictly semantic tokens from `resources/css/app.css` (`bg-surface-canvas`, `bg-surface-card`, `bg-surface-subtle`, `border-border-subtle`, `border-border-strong`, `text-text-primary`, `text-text-secondary`, `text-brand-primary`, `text-brand-accent`).
- **Strictly NO arbitrary classes** (`class-[...]`, `text-[#...]`, `bg-[#...]`).
- All user-facing strings strictly in natural Spanish.
- Mobile and counter-tablet touch ergonomics: high contrast, 44px+ touch targets, clear badge indicators.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Header: Gestión de Turnos en Vivo         [Switch: Fila Abierta / Pausada]  │
├─────────────────────────────────────────────────────────────────────────────┤
│ ┌──────────────────────┐ ┌──────────────────────┐ ┌──────────────────────┐  │
│ │ En Espera: 5         │ │ Atendidos Hoy: 18    │ │ No se Presentaron: 2 │  │
│ └──────────────────────┘ └──────────────────────┘ └──────────────────────┘  │
├─────────────────────────────────────────────────────────────────────────────┤
│ ★ MOSTRADOR DE ATENCIÓN (Spotlight Hero)                                    │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ [En atención]  Turno: TK-004    Cliente: Juan Pérez (300 123 4567)      │ │
│ │ Espera: 8 min  •  Tiempo en atención: 03:42 min                         │ │
│ │ ─────────────────────────────────────────────────────────────────────── │ │
│ │ Pedido: 2x Café Americano ($10.000) • 1x Croissant ($8.500) Total: $18.5│ │
│ │ ─────────────────────────────────────────────────────────────────────── │ │
│ │ [Completar Atención] [Volver a llamar] [Posponer] [No se presentó]      │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────────────────────┤
│ [Buscar por turno o cliente...]                                             │
│ Tabs: [ En Espera (5) ]  [ Pospuestos (1) ]  [ Historial de Hoy (20) ]      │
├─────────────────────────────────────────────────────────────────────────────┤
│ Waiting Queue List (Chronological)                                          │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ #1  TK-005  María Gómez (311 987 6543)  •  Espera: 12 min               │ │
│ │     Items: 1x Latte Caramelo ($9.000)                                   │ │
│ │     [Llamar]  [Cancelar]                                                │ │
│ ├─────────────────────────────────────────────────────────────────────────┤ │
│ │ #2  TK-006  Andrés Torres  •  Espera: 5 min                             │ │
│ │     [Llamar]  [Cancelar]                                                │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.1 Main Service Spotlight Area
- **When Active Customer Present (`CALLED` or `IN_SERVICE`)**:
  - Container: `bg-surface-card border-2 rounded-2xl p-6 shadow-sm`, with emerald accent border if `IN_SERVICE`, indigo/amber pulse border if `CALLED`.
  - Turn Indicator: Large monospace typography (`text-3xl sm:text-4xl font-extrabold tracking-tight text-text-primary`).
  - Status Badge: `<flux:badge>` with `size="lg"`, color corresponding to status (`indigo` for Llamado, `blue` for En atención).
  - Timers: Live elapsed time badge calculating duration since `called_at` / `served_at`.
  - Customer Metadata: Name, telephone link (`<a href="tel:..." class="text-brand-primary underline">`), arrival time (`H:i A`).
  - Order Breakdown Card: `bg-surface-subtle border border-border-subtle rounded-xl p-4` displaying each requested offering, quantity, unit price, and formatted grand total (`$total_price`).
  - Action Button Toolbar:
    - If `CALLED`:
      - `<flux:button variant="primary" icon="play" wire:click="startService">Iniciar Atención</flux:button>`
      - `<flux:button variant="outline" icon="bell" wire:click="recallCustomer">Volver a llamar</flux:button>`
      - `<flux:button variant="subtle" icon="pause" wire:click="postponeCustomer">Posponer</flux:button>`
      - `<flux:button variant="danger" icon="user-minus" wire:click="promptNoShow({{ $spotlight->id }})">No se presentó</flux:button>`
    - If `IN_SERVICE`:
      - `<flux:button variant="primary" color="emerald" icon="check-circle" wire:click="completeService">Completar Atención</flux:button>`
      - `<flux:button variant="subtle" icon="pause" wire:click="postponeCustomer">Posponer</flux:button>`
      - `<flux:button variant="danger" icon="x-mark" wire:click="promptCancel({{ $spotlight->id }})">Cancelar</flux:button>`
- **When Counter Free (Idle State)**:
  - Container: `bg-surface-card border border-dashed border-border-strong rounded-2xl p-8 text-center`.
  - Visual: Megaphone icon (`size-12 text-text-secondary mx-auto mb-3`).
  - Heading: _"Mostrador disponible"_.
  - Copy: _"No hay ningún cliente en atención en este momento. Llama al siguiente en la fila cuando estés listo."_
  - Primary CTA: `<flux:button variant="primary" size="lg" icon="megaphone" wire:click="callNext" class="w-full sm:w-auto shadow-md">Llamar Siguiente</flux:button>`.

### 5.2 Real-Time Search & Tabbed Navigation
- **Search Bar**: `<flux:input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por número de turno o nombre de cliente..." icon="magnifying-glass" clearable />`.
- **Tabs**:
  - `En Espera ({{ $waitingTickets->count() }})`: Displays chronological cards of pending customers with wait times and requested items. Staff can click **"Llamar"** (for priority service) or **"Cancelar"**.
  - `Pospuestos ({{ $postponedTickets->count() }})`: Displays customers who were put on hold. Staff can click **"Reanudar"** to call them back to the counter immediately.
  - `Historial de Hoy ({{ $finishedTickets->count() }})`: Displays terminal tickets completed, marked no-show, or cancelled today with timestamps and outcomes.

### 5.3 Confirmation Modals (Flux UI)
1. `confirm-call-modal`: Displays when calling a new customer while another is already in the spotlight:
   - _"¿Llamar a un nuevo cliente?"_
   - _"Actualmente el turno TK-XXX está en el mostrador. Si llamas a otro cliente, el turno actual dejará de estar en atención. ¿Deseas continuar?"_
   - Actions: `[Cancelar]` and `[Continuar y Llamar]`.
2. `confirm-no-show-modal`: Confirms marking patron as absent:
   - _"¿Marcar cliente como 'No se presentó'?"_
   - _"El turno TK-XXX se cerrará definitivamente y se liberará el mostrador."_
   - Actions: `[Volver]` and `[Confirmar No Asistencia]`.
3. `confirm-cancel-modal`: Confirms staff cancellation of a waiting ticket:
   - _"¿Cancelar este turno?"_
   - _"Esta acción cancelará el turno TK-XXX de la fila virtual."_
   - Actions: `[Volver]` and `[Cancelar Turno]`.

---

## 6. Routing & Navigation

### 6.1 Web Routing (`routes/web.php`)

Register the Livewire component under the `auth:business` middleware group:

```php
Route::middleware(['auth:business'])->group(function () {
    Route::view('business/dashboard', 'pages.business.dashboard')->name('business.dashboard');
    Route::livewire('business/queue', 'pages::business.queue.index')->name('business.queue.index');
    
    // Redirect alias to maintain legacy URL compatibility with sidebar
    Route::get('business/live-queue', function () {
        return redirect()->route('business.queue.index');
    });

    Route::livewire('business/offerings', 'pages::business.offerings.index')->name('business.offerings.index');
    // ...
});
```

### 6.2 Sidebar Navigation (`resources/views/layouts/business/sidebar.blade.php`)

Update the sidebar item to link to `route('business.queue.index')` and highlight when active:

```blade
<flux:sidebar.item
    icon="queue-list"
    :href="route('business.queue.index')"
    :current="request()->routeIs('business.queue.*') || request()->is('business/queue*') || request()->is('business/live-queue*')"
    wire:navigate
    class="cursor-pointer transition-colors data-current:border-transparent! data-current:bg-brand-primary/10! data-current:text-brand-primary! hover:bg-surface-subtle! hover:text-text-primary!"
>
    {{ __('Turnos en Vivo') }}
</flux:sidebar.item>
```

---

## 7. Architectural Decisions

1. **Two-Step Traceable Service Flow (`CALLED` vs `IN_SERVICE`)**:
   - Rather than jumping straight from `PENDING` to `COMPLETED`, introducing explicit `CALLED` and `IN_SERVICE` states enables merchants to measure two distinct metrics: **Arrival Latency** (how long customer takes to approach the counter after being summoned) and **True Service Duration** (actual time spent performing the service or preparing the order).
2. **Native Polling (`wire:poll.5s`) over WebSockets**:
   - WebSockets (Laravel Reverb / Pusher) introduce additional server infrastructure, background daemons, and connection state management. Per project rules, Livewire 5-second polling provides an optimal, lightweight, zero-dependency real-time experience for MVP.
3. **Dedicated Timestamp Tracking (`served_at`, `completed_at`, `cancelled_at`)**:
   - Storing concrete timestamps on `queue_tickets` avoids ambiguous diff calculations based on `updated_at` (which changes whenever any record column is modified) and enables auditability.
4. **Overnight Queue Resilience (Status-Driven over Date-Truncated)**:
   - Rather than blindly filtering `whereDate('created_at', today())`, all active queue operations filter strictly by status (`Pending`, `Called`, `InService`, `Skipped`). This safeguards late-night businesses (nightclubs, 24h clinics, late diners) against customer drop-off at midnight.
5. **Strict Multi-Tenant Scoping in Service Layer**:
   - Every service mutation requires `Business $business` and verifies `ticket->business_id === $business->id`. Operators can never inspect or manipulate queues belonging to other establishments.
6. **Graceful Queue Pause**:
   - Pausing queue reception does not cancel existing tickets or prevent staff from serving waiting customers. It only blocks storefront visitors from acquiring new tickets.

---

## 8. Risks & Mitigations

| Risk | Impact | Architectural Mitigation |
|---|---|---|
| **Accidental Double-Calling / Concurrency Race** | Multiple operators or fast clicks could attempt to call the same customer simultaneously | Wrap `callNextTicket` and `callSpecificTicket` in database transactions with `lockForUpdate()`. Verify status is still `Pending` before updating. |
| **Overwriting Active Service Accidental Clicks** | Operator accidentally clicks "Llamar Siguiente" while currently attending a customer, losing track of active turn | Prompt confirmation modal if `$this->spotlightTicket !== null`, warning that another ticket is already active at the counter. |
| **Polling Overhead with Large Queues** | 5-second polling might execute heavy database queries | Paginate finished history, index `['business_id', 'status', 'created_at']`, and eager load only required customer and items relationships. |
| **Ghost Turns (Customers Abandoning Queue)** | Customer leaves venue without cancelling ticket | Operator can click "No se presentó" or "Cancelar", immediately recording the outcome and freeing the counter. |
| **Storefront Queue Collision while Paused** | Customer submits turn request right as merchant pauses queue | `QueueService::createTicket` validates `$business->is_queue_paused` inside the DB transaction, rejecting concurrent creations cleanly with Spanish error toast. |

---

## 9. Verification & Automated Testing Plan

All tests will run inside Docker using Pest (`docker compose exec turnex-app php artisan test --compact`):

### 9.1 Domain Service Tests (`tests/Feature/BusinessLiveQueueServiceTest.php`)
1. **FIFO Calling**: `callNextTicket` calls the oldest pending ticket in chronological arrival order.
2. **Empty Queue**: `callNextTicket` returns `null` when no pending tickets exist.
3. **Direct Calling**: `callSpecificTicket` calls a chosen waiting ticket and updates `called_at`.
4. **Service Start**: `startService` updates ticket status to `IN_SERVICE` and sets `served_at = now()`.
5. **Service Completion**: `completeTicket` updates status to `COMPLETED` and sets `completed_at = now()`.
6. **No-Show Handling**: `markNoShow` updates status to `NO_SHOW` and sets `cancelled_at = now()`.
7. **Postponing & Resuming**:
   - `postponeTicket` updates status to `SKIPPED`.
   - `resumeTicket` restores customer to `CALLED`.
8. **Business Cancellation**: `cancelTicketByBusiness` marks ticket as `CANCELLED` and sets `cancelled_at = now()`.
9. **Queue Pause Toggle**: `toggleQueuePause` toggles `is_queue_paused` and prevents new tickets in `createTicket`.
10. **Multi-Tenant Isolation**: An operator from Business A cannot call, postpone, complete, or cancel tickets belonging to Business B.
11. **Overnight Durability**: Active tickets created before midnight (23:55) remain active, searchable, and callable after 00:01.
12. **Daily Stats Computation**: Correctly counts pending, completed today, and no-shows today.

### 9.2 Livewire Component Tests (`tests/Feature/BusinessLiveQueueTest.php`)
1. **Authorization Gate**: Unauthenticated users and customer guards are redirected to `business.login`; authenticated businesses access `/business/queue` (HTTP 200).
2. **Spotlight Rendering**:
   - Displays idle state when no customer is called/in service.
   - Displays customer name, phone number, turn number, and ordered items when spotlight is occupied.
3. **Action Executions from UI**:
   - Clicking "Llamar Siguiente" calls the next customer into the spotlight.
   - Clicking "Iniciar Atención" switches badge to `En atención`.
   - Clicking "Completar Atención" finishes turn and clears spotlight.
   - Clicking "Volver a llamar" refreshes `called_at`.
   - Clicking "Posponer" moves customer to postponed tab.
4. **Confirmation Modal Safety**:
   - Attempting to call next customer while spotlight is occupied displays the confirmation modal.
   - Confirming advances queue; dismissing retains current customer.
5. **Real-Time Search**: Filtering by customer name or ticket number filters the waiting line list reactively.
6. **Tab Switching**: Toggling between "En Espera", "Pospuestos", and "Historial de Hoy" displays matching records.
7. **Queue Pause Switch**: Toggling queue pause updates business state and displays visual status pill.
8. **Customer Self-Cancellation Reflection**: If a customer cancels their turn, the next poll removes them from the waiting list.
9. **Code Quality**:
   - Pint: `docker compose exec turnex-app vendor/bin/pint --dirty`
   - Larastan: `docker compose exec turnex-app vendor/bin/phpstan analyse`
