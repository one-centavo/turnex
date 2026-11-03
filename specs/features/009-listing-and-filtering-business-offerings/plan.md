# 009 · Listing and Filtering Business Offerings — Plan

## Approach

Transform the business offerings catalog landing view (`/business/offerings`) into an interactive Livewire 4 Single-File Component (`⚡index.blade.php`) using Flux UI tables, badges, dropdowns, and search inputs. Query filtering, eager loading, and tenant scoping are encapsulated within `OfferingService`, strictly restricting queries to the authenticated business and avoiding N+1 bottlenecks. State reactivity is driven by Livewire `#[Url]` synchronized properties with input debouncing, real-time filtering by category and status, column sorting, and automatic pagination resets.

## Implementation

1. **Domain & Query Layer**:
   - Extend `app/Services/OfferingService.php` with a query method `listForBusiness(Business $business, array $filters, string $sortField, string $sortDirection, int $perPage = 10)`:
     - Strict tenant scoping via `$business->offerings()`.
     - Eager loading of categories including soft-deleted records: `with(['category' => fn ($query) => $query->withTrashed()])`.
     - Case-insensitive search on offering `name` with trimmed and sanitized input.
     - Filtering by active category (`category_id`).
     - Filtering by status (`all`, `active`, `inactive`).
     - Dynamic sorting on allowed columns (`name`, `created_at`) and directions (`asc`, `desc`).
     - Pagination with a standardized page size (10 items per page).
   - Ensure the category relation on `Offering` handles soft deletes safely without throwing null exceptions.

2. **Presentation & Livewire Layer**:
   - Update `routes/web.php` route definition from `Route::view('business/offerings', ...)` to `Route::livewire('business/offerings', 'pages::business.offerings.index')` guarded by `auth:business`.
   - Convert `resources/views/pages/business/offerings/index.blade.php` into a Livewire 4 Single-File Component `resources/views/pages/business/offerings/⚡index.blade.php` wrapped in `<x-layouts::business>`:
     - Component state:
       - `#[Url] public string $search = '';`
       - `#[Url] public string $category = '';`
       - `#[Url] public string $status = 'all';`
       - `#[Url] public string $sortField = 'created_at';`
       - `#[Url] public string $sortDirection = 'desc';`
       - Trait: `use WithPagination;`
     - Reactivity & Lifecycle Hooks:
       - `updatedSearch()`, `updatedCategory()`, `updatedStatus()` calling `$this->resetPage()`.
       - `sortBy(string $field)` toggling direction (asc/desc) and resetting page.
       - `resetFilters()` resetting search, category, status to defaults, and resetting page.
     - UI Composition (Flux UI & Semantic Tokens):
       - Header with title, subtitle, and primary action button (`Nuevo Producto o Servicio` linking to `business.offerings.create`).
       - Control bar: real-time search input (`<flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search">`), active category select (`<flux:select wire:model.live="category">` populated with `Category::active()->get()`), and status select (`<flux:select wire:model.live="status">`).
       - Interactive table (`<flux:table>`):
         - **Offering column:** Compact square thumbnail ($40\times 40\text{ px}$, `size-10 rounded-lg object-cover`) or neutral placeholder icon, placed alongside the offering name.
         - **Category column:** Category name (with muted indicator if the category is archived).
         - **Price column:** Formatted currency (e.g. `$15.00`), or explicit subtle label (`"Gratis"`) when price is 0.00.
         - **Duration column:** Estimated duration in minutes (e.g. `30 min` or `-`).
         - **Status column:** Clean display for active offerings (no badge/dot clutter); explicit `<flux:badge color="zinc">Inactivo</flux:badge>` badge for inactive offerings.
         - Sortable table headers displaying active directional indicator icons.
       - Pagination controls using Livewire pagination links.
       - Contextual empty states:
         - **Initial empty state** (when the business has 0 offerings total) prompting the merchant to create their first offering.
         - **Filtered empty state** (when search/filters yield 0 matches) displaying _"No offerings found matching your criteria."_ with a reset filters button.

3. **Testing & QA**:
   - Create Pest feature test suite in `tests/Feature/OfferingListingTest.php` verifying:
     - Guest access redirection to `/business/login`.
     - Multi-tenant isolation (offerings of other businesses never appear).
     - Correct rendering of columns (compact thumbnail preview/placeholder, name, category, price, duration).
     - Price 0.00 renders as "Gratis" / "Free" rather than blank.
     - Inactive offerings display the "Inactive" status badge; active offerings display cleanly without badges.
     - Real-time search filters offerings by name.
     - Category filter selects matching offerings and excludes non-matching ones.
     - Offering assigned to soft-deleted category renders safely without runtime errors.
     - Status filter filters by active and inactive offerings.
     - Modifying search or filter resets pagination to page 1.
     - Sorting toggles between ascending and descending on name and creation date.
     - Initial empty state vs search-filtered empty state with filter reset.
     - Query string parameter synchronization.
   - Run Docker-based linting and quality checks:
     - `docker compose exec turnex-app vendor/bin/pint --dirty`
     - `docker compose exec turnex-app php artisan test --compact`

## Decisions

- **Livewire 4 Single-File Component (`⚡index.blade.php`)** — Unifies component logic and template into a reactive SFC consistent with `⚡create.blade.php`, delivering instant search, filtering, and pagination without full page reloads.
- **Dedicated Query Method in `OfferingService`** — Avoids bloat in the Livewire component by encapsulating tenant scoping, relation eager loading, and query filters in the service layer, keeping logic modular and testable.
- **Eager Loading with `withTrashed()` on Categories** — Pre-fetches category relations in a single query (`with(['category' => fn ($q) => $q->withTrashed()])`) preventing N+1 database performance hits and eliminating null-pointer crashes if a category is soft-deleted.
- **`#[Url]` Query String Synchronization** — Synchronizes component filter and sorting properties directly with URL parameters, enabling browser history navigation, back-button usability, and direct link bookmarking.
- **Semantic Design Tokens over Arbitrary Utility Classes** — Strictly employs design tokens defined in `app.css` (`bg-surface-card`, `bg-surface-subtle`, `border-border-subtle`, `text-text-primary`, `text-text-secondary`) to ensure uniform theme integration and automatic dark mode support.

## Risks

- **N+1 Query Waterfall on Large Datasets** — Fetching category data for each offering in the table loop could trigger excessive individual database queries.
  - *Mitigation:* Enforce eager loading with `with(['category' => fn ($q) => $q->withTrashed()])` within `OfferingService`.
- **Search Query Latency on Unindexed Fields** — High-frequency search typing could overload MySQL with simultaneous unconstrained `LIKE` queries.
  - *Mitigation:* Apply `wire:model.live.debounce.300ms` on the search input, sanitize whitespace, and scope the query strictly within the indexed `business_id` partition before filtering by name.
- **Category Soft Deletion Null Reference Exceptions** — If an offering's assigned category is archived, calling `$offering->category->name` would fail if the relation query excludes soft-deleted models.
  - *Mitigation:* Use `withTrashed()` on the relation eager load and use the null-safe operator or default fallback (`$offering->category?->name ?? __('Sin categoría')`).
- **Pagination State Mismatch After Filtering** — If a user filters while on a higher page number, the table could query an empty offset and show no results.
  - *Mitigation:* Implement Livewire's `updatedSearch()`, `updatedCategory()`, and `updatedStatus()` hooks to explicitly call `$this->resetPage()`.