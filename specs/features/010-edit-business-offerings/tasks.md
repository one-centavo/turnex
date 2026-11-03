# 010 · Edit Business Offerings — Tasks

## Domain & Service Layer

- [ ] Implement `update(Offering $offering, array $data, ?UploadedFile $image = null, bool $removeImage = false): Offering` in `App\Services\OfferingService`.
- [ ] Implement image replacement logic (storing new file, deleting old file upon successful commit) in `OfferingService`.
- [ ] Implement explicit image removal logic (deleting existing file from disk and clearing `reference_image`) in `OfferingService`.
- [ ] Ensure input sanitization using `DataNormalizer` for `name` and `description` during update.
- [ ] Ensure transaction safety with rollback and temporary file cleanup on database failure.

## Presentation & Livewire Layer

- [ ] Register route `/business/offerings/{offering}/edit` in `routes/web.php` with `auth:business` middleware.
- [ ] Create Livewire 4 Single-File Component `resources/views/pages/business/offerings/⚡edit.blade.php` wrapped in `layouts.business`.
- [ ] Implement tenant authorization check in `mount(Offering $offering)` returning `abort(404)` on cross-tenant or unauthenticated access.
- [ ] Pre-fill form state with existing offering attributes (name, category, price, duration, description, status, image preview).
- [ ] Populate category dropdown with active categories and preserve display of archived category name if currently assigned.
- [ ] Implement reactive image controls: Livewire upload, live preview, discarding temporary upload, and explicit image removal.
- [ ] Implement validation rules matching business constraints with error display preserving entered inputs.
- [ ] Implement "Cancel" button linking back to `/business/offerings` without saving.
- [ ] Implement save action with button loading state, calling `OfferingService::update()`, flashing success message, and redirecting to index.
- [ ] Add "Editar" action button in `resources/views/pages/business/offerings/⚡index.blade.php` for each table row linking to the edit route.

## Verification & QA

- [ ] Create Pest feature test suite in `tests/Feature/OfferingEditingTest.php` covering:
  - [ ] Unauthenticated guest redirection to `/business/login`.
  - [ ] Cross-tenant isolation (attempting to edit another business's offering returns 404).
  - [ ] Non-existent offering ID returns 404.
  - [ ] Form pre-population with existing offering data.
  - [ ] Soft-deleted/archived category preservation.
  - [ ] Validation constraints (name length 3-50, no space-only, category required, non-negative price, duration >= 1).
  - [ ] Successful update of text fields, category, price, duration, and status.
  - [ ] Retaining existing image when no new file is uploaded.
  - [ ] Replacing image with a new upload (stores new file, deletes old file from disk).
  - [ ] Discarding temporary upload restores previous image preview.
  - [ ] Removing existing image deletes file from disk and clears `reference_image`.
  - [ ] Submitting form without changes updates cleanly and redirects.
  - [ ] Cancel button returns to index without modifying records.
- [ ] Run feature tests via Docker (`docker compose exec turnex-app php artisan test --compact --filter=OfferingEditingTest`).
- [ ] Run complete test suite regression (`docker compose exec turnex-app php artisan test --compact`).
- [ ] Run Pint formatter (`docker compose exec turnex-app vendor/bin/pint --dirty`).
- [ ] Run Larastan static analysis (`docker compose exec turnex-app vendor/bin/phpstan analyse`).
- [ ] Update `spec.md` acceptance criteria checkboxes and update `specs/constitution/roadmap.md`.
