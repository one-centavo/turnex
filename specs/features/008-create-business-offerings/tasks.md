# 008 · Business Offerings — Tasks

## Database & Persistence

- [x] Create migration for `offerings` table (`business_id`, `category_id`, `name`, `reference_image`, `description`, `price`, `estimated_duration_minutes`, `is_active`, `timestamps`).
- [x] Run migration inside Docker container (`docker compose exec turnex-app php artisan migrate`).
- [x] Create `App\Models\Offering` model with `declare(strict_types=1);`, `#[Fillable]`, casts, relationships, and `scopeActive()`.
- [x] Add `offerings()` relationship (`hasMany(Offering::class)`) to `App\Models\Business` and `App\Models\Category`.
- [x] Create `Database\Factories\OfferingFactory` with `active()`, `inactive()`, and `free()` state methods.

## Domain & Service Layer

- [x] Implement `App\Services\OfferingService` handling input normalization, image validation/upload, and tenant-scoped creation.
- [x] Support text cleaning and normalization in `DataNormalizer` or within service layer (US-CORE-02).
- [x] Implement image storage on `public` disk with safe generated filenames.

## Presentation & Livewire Layer

- [x] Create dedicated business portal layout `resources/views/layouts/business.blade.php` with Flux UI sidebar, mobile navigation header, and semantic design tokens (`bg-surface-canvas`, `border-border-subtle`, `text-text-primary`).
- [x] Implement business navigation items with active route highlighting: Live Queue (placeholder), Dashboard, Products & Services (`/business/offerings`), Profile, and Verification.
- [x] Implement authenticated business user menu displaying active `business_name`, status badge, and business logout action.
- [x] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/business/offerings/⚡create.blade.php` wrapped in the business layout.
- [x] Implement reactive form inputs: name, category select, price, estimated duration, description, and file upload.
- [x] Implement live image preview and "remove image" action using Livewire `WithFileUploads`.
- [x] Bind validation rules and handle error feedback without clearing previously valid input fields.
- [x] Implement loading indicator and disabled state on submit button during processing.
- [x] Register route `/business/offerings/create` in `routes/web.php` with `auth:business` middleware.
- [x] Register catalog placeholder view `/business/offerings` to receive redirect with flash success notification.

## Verification & QA

- [x] Create Pest feature test suite in `tests/Feature/OfferingCreationTest.php` covering:
  - [x] Unauthenticated guest redirection to `/business/login`.
  - [x] Validation feedback on missing or invalid required fields (name, category).
  - [x] Name length constraints (3 to 50 characters).
  - [x] Selection restricted to active, non-archived categories.
  - [x] Reference image validation (PNG, JPG, SVG up to 10 MB, rejecting invalid mime types).
  - [x] Price validation (non-negative, default 0.00, maximum 99,999,999.99).
  - [x] Estimated duration validation (positive whole number >= 1 minute).
  - [x] Input data sanitization (US-CORE-02 trimming and whitespace collapsing).
  - [x] Strict multi-tenant isolation (offering bound to authenticated business).
  - [x] Successful offering creation, image file persistence, form reset, and catalog redirection.
- [x] Run feature tests via Docker (`docker compose exec turnex-app php artisan test --compact --filter=OfferingCreationTest`).
- [x] Run complete test suite regression (`docker compose exec turnex-app php artisan test --compact`).
- [x] Run Pint formatter (`docker compose exec turnex-app vendor/bin/pint --dirty`).
- [x] Run Larastan static analysis (`docker compose exec turnex-app vendor/bin/phpstan analyse`).
- [x] Update `spec.md` acceptance criteria checkboxes and update `specs/constitution/roadmap.md`.
