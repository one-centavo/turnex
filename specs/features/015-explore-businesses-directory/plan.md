# 015 · Explore Businesses Directory — Plan

Comprehensive technical implementation plan for the public business exploration directory (`015-explore-businesses-directory`), respecting Turnex architectural patterns, Docker execution constraints, Livewire 4 Single-File Component paradigms, and Flux UI semantic token standards.

---

## 1. Technical Architecture & Approach

The directory provides a publicly accessible discovery portal at `/explore` enabling anonymous guests and authenticated patrons to browse, search, and filter verified establishments (`VerificationStatus::Approved`) that possess at least one active catalog offering (`Offering::scopeActive`).

```
Public / Customer Visitor
         │
         ▼
 ┌─────────────────────────────────────────────────────────┐
 │ /explore (routes/web.php)                               │
 │ Livewire 4 SFC: pages::explore.index                     │
 │ (resources/views/pages/explore/⚡index.blade.php)        │
 └────────────────────────────┬────────────────────────────┘
                              │
             ┌────────────────┴────────────────┐
             ▼                                 ▼
   ┌───────────────────────┐       ┌───────────────────────┐
   │ Business Query Engine │       │ Active Turn Detector  │
   │ - Verification filter │       │ (Customer Guard Check)│
   │ - Active offerings    │       │ Single indexed lookup │
   │ - Case/accent search  │       │ $activeCustomerBids   │
   │ - BusinessCategory    │       └───────────────────────┘
   │ - withCount tickets   │
   │ - Pagination (12/pg)  │
   └───────────┬───────────┘
               │
               ▼
 ┌─────────────────────────────────────────────────────────┐
 │ Responsive Grid View (Flux UI + Semantic Tokens)        │
 │ - Hero & Search Bar (Debounced 300ms)                   │
 │ - Horizontal Scrollable Category Chips                  │
 │ - Establishment Cards with Fallbacks & Badges           │
 │ - Live Queue Occupancy Badge ("Sin fila" / "N en espera")│
 │ - Active Turn Indicator ("Turno activo")                │
 │ - Empty State & Reset Action                            │
 └─────────────────────────────────────────────────────────┘
```

---

## 2. Data & Query Layer

### 2.1 Eloquent Query Construction

The directory listing query on `Business` is encapsulated within a dedicated computed property `#[Computed] public function businesses(): LengthAwarePaginator` or repository method to enforce isolation and prevent performance regressions:

```php
$query = Business::query()
    // 1. Verification Isolation: Only officially approved merchants
    ->where('verification_status', VerificationStatus::Approved)
    
    // 2. Active Offering Guard: Exclude catalog-empty or fully archived businesses
    ->whereHas('offerings', function (Builder $q): void {
        $q->active(); // Offering::scopeActive() enforces `is_active = true` and excludes soft-deleted
    })
    
    // 3. Queue Occupancy Subquery (Zero N+1): Computes live ticket count in a single query
    ->withCount(['queueTickets as active_tickets_count' => function (Builder $q): void {
        $q->active(); // QueueTicket::scopeActive() matches Pending, Preparing, Ready, Called, InService, Skipped
    }]);
```

### 2.2 Search & Filtering Logic

1. **Whitespace Trimming & Sanitization**:
   - The reactive property `$search` is sanitized using `trim($this->search)`.
   - Empty or whitespace-only queries are ignored.
   - SQL wildcard characters (`%` and `_`) are escaped with `addcslashes($sanitizedSearch, '%_')` to prevent unintentional wildcard expansions.

2. **Case-Insensitive & Accent-Insensitive Matching**:
   - Production MySQL uses `utf8mb4_unicode_ci` / `utf8mb4_0900_ai_ci` collations where `LIKE '%term%'` is naturally case- and accent-insensitive.
   - For uniform local/testing compatibility across SQLite and MySQL, searches on `business_name` and `location` are grouped inside a logical closure:
     ```php
     if (filled($trimmedSearch)) {
         $escaped = addcslashes($trimmedSearch, '%_');
         $query->where(function (Builder $sub) use ($escaped): void {
             $sub->where('business_name', 'like', "%{$escaped}%")
                 ->orWhere('location', 'like', "%{$escaped}%");
         });
     }
     ```

3. **Category Matching**:
   - Filter parameter `$category` maps to backed Enum values in `App\Enums\BusinessCategory`.
   - When present and valid (`BusinessCategory::tryFrom($this->category) !== null`), filter:
     ```php
     if (filled($this->category) && BusinessCategory::tryFrom($this->category) !== null) {
         $query->where('business_category', $this->category);
     }
     ```

4. **Deterministic Ordering & Pagination**:
   - Orders by queue proximity or name: `orderByDesc('active_tickets_count')->orderBy('business_name')`.
   - Paginated at 12 records per page: `paginate(12)->withQueryString()`.

### 2.3 Authenticated Customer Active Turn Detection

To satisfy the requirement that cards highlight an active turn if the customer is currently in line, the component checks the customer guard once per request without issuing N queries:

```php
#[Computed]
public function activeCustomerBusinessIds(): array
{
    $customer = Auth::guard('customer')->user();
    if (! $customer) {
        return [];
    }

    return QueueTicket::query()
        ->where('customer_id', $customer->id)
        ->active()
        ->pluck('business_id')
        ->flip()
        ->all(); // Flipped array provides O(1) isset() lookup in Blade
}
```

### 2.4 Database Indexes & Performance Review

The existing schema includes:
- `businesses`: PK `id`, unique `slug`, unique `email`, unique `phone_number`.
- `offerings`: FK `business_id`, `category_id`, index `deleted_at`.
- `queue_tickets`: FK `business_id`, FK `customer_id`, compound indexes `['business_id', 'created_at', 'ticket_number']` and `['business_id', 'customer_id', 'status']`.

**Optimization Notes**:
- For high-volume production directories, adding a compound index `['verification_status', 'business_category']` on `businesses` and `['business_id', 'status']` on `queue_tickets` optimizes the subquery filter. Since existing tables have indexed foreign keys, the `withCount` subquery executes via foreign key lookup and status filtering efficiently.

---

## 3. Livewire 4 Component Architecture

### 3.1 Component Specifications
- **File Location:** `resources/views/pages/explore/⚡index.blade.php`
- **Class Model:** Anonymous Single-File Component (`new #[Title('Explorar')] #[Layout('layouts.customer')] class extends Component`)
- **Traits:** `Livewire\WithPagination;`

### 3.2 State & Reactivity

```php
#[Url(as: 'q', history: true)]
public string $search = '';

#[Url(as: 'categoria', history: true)]
public string $category = '';

public function updatedSearch(): void
{
    $this->resetPage();
}

public function updatedCategory(): void
{
    $this->resetPage();
}

public function selectCategory(string $category): void
{
    $this->category = $this->category === $category ? '' : $category;
    $this->resetPage();
}

public function resetFilters(): void
{
    $this->reset(['search', 'category']);
    $this->resetPage();
}
```

### 3.3 UI Composition & Semantic Token Styling

All template markup strictly adheres to project guidelines (no arbitrary hex values, strictly semantic tokens):

1. **Header & Search Bar**:
   - Title: `Explora Establecimientos`
   - Subtitle: `Descubre comercios verificados, consulta el estado de las filas en tiempo real y pide tu turno.`
   - Search: `<flux:input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre de negocio o dirección..." icon="magnifying-glass" clearable />`

2. **Category Chip Bar**:
   - Horizontal scroll container on mobile (`overflow-x-auto flex gap-2 pb-2 no-scrollbar`).
   - "Todos" pill (`wire:click="selectCategory('')"`).
   - Category chips generated from `BusinessCategory::cases()` displaying `$category->label()`.
   - Selected state: `bg-brand-primary text-white border-brand-primary font-medium`.
   - Unselected state: `bg-surface-card text-text-secondary border-border-subtle hover:border-border-strong hover:text-text-primary`.

3. **Responsive Establishment Card Grid**:
   - Grid layout: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6`.
   - Each card is structured using Flux and semantic tokens:
     - **Cover Media Container (`h-36 relative overflow-hidden rounded-t-xl bg-surface-subtle border-b border-border-subtle`)**:
       - If `cover_photo_path`: `<img src="{{ Storage::url($business->cover_photo_path) }}" alt="{{ $business->business_name }}" loading="lazy" class="h-full w-full object-cover">`
       - Fallback: Branded gradient placeholder (`bg-gradient-to-br from-surface-subtle via-surface-card to-blue-900/20`) with a centered subtle icon (`<flux:icon.building-storefront class="size-12 text-border-strong" />`).
       - Badges Overlay:
         - Top-Left: Category badge `<flux:badge variant="subtle" size="sm">{{ $business->business_category->label() }}</flux:badge>`.
         - Top-Right: Active Turn Indicator if `isset($this->activeCustomerBusinessIds[$business->id])`:
           `<flux:badge color="blue" icon="ticket" size="sm" class="shadow-sm font-semibold">Turno activo</flux:badge>`.
     - **Card Content Body (`p-5 flex flex-col justify-between flex-1 bg-surface-card border-x border-b border-border-subtle rounded-b-xl`)**:
       - Business Logo & Name:
         - Logo thumbnail (`size-10 rounded-lg object-cover border border-border-subtle`) or Initials avatar (`size-10 rounded-lg bg-surface-subtle border border-border-subtle font-bold flex items-center justify-center text-text-primary`).
         - Business name heading: `<flux:heading size="lg" class="truncate font-semibold text-text-primary">{{ $business->business_name }}</flux:heading>`.
       - Physical Location:
         - Map pin icon (`flux:icon.map-pin`) with truncated location string (`text-xs text-text-secondary truncate`).
       - Queue Occupancy Indicator:
         - If `active_tickets_count === 0`:
           `<flux:badge color="emerald" size="sm" icon="check-circle">Sin fila</flux:badge>`
         - If `active_tickets_count === 1`:
           `<flux:badge color="amber" size="sm" icon="clock">1 persona en espera</flux:badge>`
         - If `active_tickets_count > 1`:
           `<flux:badge color="amber" size="sm" icon="clock">{{ $business->active_tickets_count }} personas en espera</flux:badge>`
       - Storefront CTA:
         - `<flux:button href="{{ route('storefront.show', ['slug' => $business->slug]) }}" wire:navigate variant="primary" class="w-full mt-4">Ver Catálogo y Turnos</flux:button>`

4. **Empty State**:
   - Triggered when `$this->businesses->isEmpty()`.
   - Card container with icon (`magnifying-glass-slash`), descriptive title, explanatory text, and action button:
     `<flux:button wire:click="resetFilters" variant="outline" icon="arrow-path">Limpiar filtros</flux:button>`.

5. **Pagination**:
   - `{{ $this->businesses->links() }}` using native Flux UI / Tailwind responsive pagination controls.

---

## 4. Layout & Navigation Strategy

### 4.1 Customer Layout Adaptation (`resources/views/layouts/customer.blade.php`)

The customer layout must provide a unified, responsive shell for both logged-in customers and public guests:

1. **Authentication State Detection**:
   ```php
   $customer = auth()->guard('customer')->user() ?? auth()->user();
   $isCustomerAuthenticated = Auth::guard('customer')->check();
   ```

2. **Desktop Header Navigation**:
   - Brand Logo: points to `route('explore')` or `route('home')`.
   - Primary Nav Links:
     - "Explorar" (`route('explore')`) — marked active with `text-brand-primary` when `request()->routeIs('explore*')`.
     - "Mis Turnos" (`$ticketsUrl`) — visible to both; if clicked by a guest, redirected to customer login.
   - User Section (Right):
     - If `$isCustomerAuthenticated`: renders current `flux:dropdown` profile with customer name, avatar, email, profile link, and logout button.
     - If guest (`! $isCustomerAuthenticated`): renders clean auth CTA buttons:
       - `<flux:button href="{{ route('customer.login') }}" wire:navigate variant="ghost" size="sm">Iniciar sesión</flux:button>`
       - `<flux:button href="{{ route('customer.register') }}" wire:navigate variant="primary" size="sm">Registrarse</flux:button>`

3. **Mobile Bottom Navigation Bar**:
   - Preserves 3-slot mobile navigation grid:
     1. "Explorar" (`route('explore')`, `data-test="mobile-nav-explore"`) with `magnifying-glass` icon.
     2. "Mis Turnos" (`$ticketsUrl`, `data-test="mobile-nav-tickets"`) with `ticket` icon.
     3. Profile / Auth slot (`data-test="mobile-nav-profile"`):
        - When authenticated: links to `customer.profile` with `user` icon and label "Perfil".
        - When guest: links to `customer.login` with `user` icon and label "Perfil" (maintaining full test assertion compatibility with existing tests in `CustomerLayoutTest.php`).

### 4.2 Public Landing Page (`resources/views/welcome.blade.php`)

- Add "Explorar" navigation link into the top header navigation alongside brand elements.
- Add an exploration discovery button or link in the hero section ("Explorar Comercios") pointing to `route('explore')`.

### 4.3 Web Routing (`routes/web.php`)

- Register route cleanly without auth middleware:
  ```php
  Route::livewire('explore', 'pages::explore.index')->name('explore');
  ```

---

## 5. Architectural Decisions

1. **Single-File Component (`⚡index.blade.php`)**:
   - Co-locates template, reactive URL properties, and Eloquent queries in a single file following Livewire 4 modern conventions. Avoids unnecessary controller/view file sprawl.
2. **Subquery `withCount` for Real-Time Queue Occupancy**:
   - Executes live line counts directly in the database engine as a correlated subquery (`withCount`), avoiding any N+1 query vulnerability when iterating through cards.
3. **Array Flip Set Lookup for Active Customer Turns**:
   - Pre-plucks active business IDs for the authenticated customer into a flipped array (`['b_id' => index]`), enabling $O(1)$ memory checks in Blade loops instead of running per-card DB queries.
4. **Mandatory Catalog Offering Guard (`whereHas('offerings', fn ($q) => $q->active())`)**:
   - Excludes registered businesses that lack active services or products, preventing customer disappointment from browsing empty venues.
5. **Full Guest Accessibility**:
   - `/explore` imposes zero authentication barriers. Anyone can view venues, browse queues, and click through to public storefronts (`/b/{slug}`).

---

## 6. Risks & Mitigations

| Risk | Impact | Architectural Mitigation |
|---|---|---|
| **Slow `LIKE` queries with thousands of businesses** | Database CPU spikes | Enforce 300ms input debounce (`wire:model.live.debounce.300ms`), trim input, escape special characters, and restrict search exclusively to indexed/short text fields (`business_name`, `location`). |
| **Heavy page weight from cover images** | Mobile load latency | Force `loading="lazy"` on all `<img>` tags; render zero-network SVG fallback placeholders when `cover_photo_path` is null. |
| **Spanglish UI copy leakage** | Inconsistent user experience | Enforce strict rule: all code/variables in English, all user-facing strings strictly in Spanish ("Sin fila", "1 persona en espera", "N personas en espera", "Turno activo", "Limpiar filtros"). |
| **Stale queue count in long-opened tabs** | Outdated occupancy view | Provide standard Livewire polling (`wire:poll.15s`) or let reactive search/filter refreshes update counts without full page reload overhead. |
| **URL bookmarking inconsistencies** | Broken shareable links | Synchronize `$search` and `$category` to the browser URL using `#[Url(as: 'q', history: true)]` and `#[Url(as: 'categoria', history: true)]`. |

---

## 7. Verification & Testing Plan

Automated feature tests will be executed inside Docker via Pest (`docker compose exec turnex-app php artisan test --compact`):

1. **Guest and Customer Access**:
   - Unauthenticated guests can access `/explore` (HTTP 200).
   - Authenticated customers can access `/explore` (HTTP 200).
2. **Business Eligibility Verification**:
   - Approved businesses with active offerings appear in the listing.
   - Pending, under-review, or rejected businesses are excluded.
   - Approved businesses with zero offerings or only inactive/soft-deleted offerings are excluded.
3. **Search Functionality**:
   - Search by exact and partial `business_name`.
   - Search by `location`.
   - Search trims leading/trailing whitespace and handles special characters safely.
   - Search is case-insensitive.
4. **Category Filtering**:
   - Selecting a `BusinessCategory` filters only establishments within that category.
   - Clearing the category returns all valid establishments.
5. **URL Parameter Synchronization**:
   - `?q=...&categoria=...` correctly populates component state and initial filtered results.
6. **Queue Occupancy Display**:
   - Businesses with 0 active tickets render "Sin fila".
   - Businesses with 1 active ticket render "1 persona en espera".
   - Businesses with multiple active tickets render "N personas en espera".
   - Terminal tickets (Completed, Cancelled, NoShow) are ignored in line occupancy.
7. **Active Turn Badge**:
   - When authenticated customer holds an active ticket at business A, card A displays "Turno activo".
   - Business B where the customer has no active ticket does not display the badge.
8. **Navigation & Storefront Routing**:
   - Card link directs to `route('storefront.show', ['slug' => $business->slug])`.
9. **Empty State & Reset Action**:
   - Unmatched query displays Spanish empty state and reset button.
   - Clicking reset resets `$search` and `$category` and re-displays results.
10. **Code Quality**:
    - Pint: `docker compose exec turnex-app vendor/bin/pint --dirty`
    - Larastan: `docker compose exec turnex-app vendor/bin/phpstan analyse`
