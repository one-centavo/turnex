# 008 · Business Offerings — Plan

## Approach

Implement the `offerings` database persistence layer and `Offering` Eloquent model with foreign relations to `Business` and `Category`. Business logic, text sanitization, and secure image storage are encapsulated within an `OfferingService` in the service layer, strictly scoping persistence to the authenticated business. On the presentation layer, a Livewire 4 Single-File Component using Flux UI at `/business/offerings/create` provides the merchant creation form with client-side live image preview, clearable upload state, and real-time validation, redirecting to the catalog view upon successful completion.

## Implementation

1. **Database & Persistence Layer**:
   - Create migration `create_offerings_table.php` defining `id`, `business_id` (FK -> `businesses(id)` on delete cascade), `category_id` (FK -> `categories(id)` on delete restrict), `name` (varchar 50), `reference_image` (varchar 255 nullable), `description` (varchar 250 nullable), `price` (decimal 10,2 default 0.00), `estimated_duration_minutes` (integer nullable), `is_active` (boolean default true), and standard timestamps.
   - Create Eloquent model `app/Models/Offering.php` with `declare(strict_types=1);`, PHP 8 `#[Fillable]` attribute, attribute casts (`price` to decimal, `is_active` to boolean, `estimated_duration_minutes` to integer), relationships (`business()` and `category()`), and query scope `scopeActive()`.
   - Update `app/Models/Business.php` and `app/Models/Category.php` with `offerings()` relationship (`hasMany(Offering::class)`).
   - Create factory `database/factories/OfferingFactory.php` with realistic seed data and state modifiers: `active()`, `inactive()`, and `free()`.

2. **Domain & Service Layer**:
   - Create `app/Services/OfferingService.php` to handle:
     - Input text sanitization via `DataNormalizer` (trimming text fields, collapsing whitespace, treating space-only strings as empty).
     - Secure file processing: validating MIME type, generating unique storage filenames, and persisting image assets to the public disk storage (`offerings/`).
     - Tenant-scoped persistence: creating the offering strictly via `$business->offerings()->create(...)`.

3. **Presentation & Livewire Layer**:
   - Create dedicated business portal layout `resources/views/layouts/business.blade.php` and `resources/views/layouts/business/sidebar.blade.php` using Flux UI (`<flux:sidebar>`, `<flux:header>`) and semantic design tokens:
     - Header block showing active `business_name`, avatar/logo placeholder, and live status badge.
     - Grouped navigation links: Operación (Cola en Vivo, Métricas), Gestión (Productos y Servicios `/business/offerings`), Configuración (Perfil, Verificación KYB).
     - User dropdown menu with business profile info and dedicated logout action.
   - Create Livewire 4 Single-File Component `resources/views/pages/business/offerings/⚡create.blade.php` using Flux UI components and semantic design tokens, wrapped in the business layout:
     - Form inputs for name, category dropdown (populating active categories), price, estimated duration, description, and file upload.
     - Real-time temporary image preview using Livewire `WithFileUploads` with an action to discard/remove the selected file before saving.
     - Submission button with `wire:loading.attr="disabled"` to prevent duplicate submissions.
     - Post-creation session feedback with success message (_"Service created successfully."_) and redirection.
   - Register route `/business/offerings/create` in `routes/web.php` guarded by `auth:business`.
   - Register catalog landing placeholder route `/business/offerings` (or `/business/catalog`) so successful creations redirect smoothly with flash notification.

4. **Testing & QA**:
   - Create Pest feature test suite in `tests/Feature/OfferingCreationTest.php` covering:
     - Authentication protection (guest redirected to `/business/login`).
     - Validation rules (name length, active category requirement, valid image formats and size limit, non-negative price, positive duration).
     - Input sanitization (white space collapsing and space-only field rejection).
     - Multi-tenant isolation (record bound strictly to authenticated business).
     - Successful submission with image upload and redirection.
   - Run Pint and Larastan checks via Docker.

## Decisions

- **Dedicated `OfferingService` over Fat Component Logic** — Isolates file handling, path resolution, data normalization, and transactional writes away from the Livewire component, ensuring logic is testable in isolation and reusable across API or seeders.
- **Strict Multi-Tenant Scoping via Eloquent Relationship** — Never accepts `business_id` from client payloads. Persisting through `$business->offerings()->create()` guarantees offerings cannot be assigned to another business.
- **Image Storage with Hashed/UUID Filenames** — Storing uploaded images using Laravel's file hashing rather than client-provided file names prevents directory traversal, file name collision, and script execution risks.
- **Relational Integrity with `ON DELETE RESTRICT` on Categories** — Prevents deleting categories that currently classify active or inactive offerings, preserving data integrity across merchant catalogs.
- **Semantic Design Tokens over Arbitrary CSS** — Complies with the project's strict frontend manifesto by relying on semantic tokens defined in `app.css` (`bg-surface-canvas`, `border-border-subtle`, `text-text-primary`) rather than hardcoded hex classes.

## Risks

- **Disguised Malicious Files or Executable Scripts** — Attackers could rename PHP scripts or executables with image extensions (`.jpg`, `.png`).
  - *Mitigation:* Apply strict validation using Laravel's `image` and `mimes:png,jpg,svg` rules, which inspect binary MIME headers, and store on the `public` storage disk without execute permissions.
- **Large Image Upload Overhead & Timeouts** — Uploading large images on slower mobile connections could stall the UI.
  - *Mitigation:* Enforce a 10 MB maximum limit (`max:10240`), display a real-time upload progress indicator, and disable the submission trigger during upload (`wire:loading`).
- **Referencing Inactive or Soft-Deleted Categories** — A category could be deactivated or deleted while a merchant is on the form.
  - *Mitigation:* Category select input queries only active, non-archived records (`Category::active()->get()`) and validates against the database with an active condition rule.
