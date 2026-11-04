# 011 · Archive and Delete Business Offerings — Plan

## Approach

Implement a safe, two-stage lifecycle for business offerings (products and services) using Laravel Eloquent `SoftDeletes`, domain encapsulation in `OfferingService`, and reactive Livewire 4 Single-File Component interactions (`⚡index.blade.php`) paired with Flux UI confirmation modals. In the first stage, offerings are archived via soft delete (`deleted_at`), deactivating the item and hiding it from active operational views while safeguarding historical queue and order integrity. In the second stage, merchants can review archived items via a dedicated status filter, allowing one-click restoration or irreversible permanent deletion guarded by historical activity verification.

## Implementation

1. **Data Layer (Persistence & Schema)**:
   - Generate migration `add_deleted_at_to_offerings_table` adding `$table->softDeletes()` to the `offerings` table.
   - Update `app/Models/Offering.php`:
     - Import and add `Illuminate\Database\Eloquent\SoftDeletes` trait.
     - Add `@property Carbon|null $deleted_at` to the class-level PHPDoc.
     - Maintain strict typing, casts, and relations.

2. **Domain & Service Layer (`app/Services/OfferingService.php`)**:
   - Update `listForBusiness()` query logic:
     - When `$filters['status'] === 'archived'`, query exclusively soft-deleted offerings: `$business->offerings()->onlyTrashed()`.
     - When status is `'all'`, query non-archived offerings by default (`$business->offerings()`), displaying active and inactive items.
     - When status is `'active'` or `'inactive'`, filter standard non-archived offerings accordingly.
   - Add `archive(Offering $offering): bool`:
     - Run within a database transaction.
     - Set `$offering->is_active = false`.
     - Call `$offering->delete()` to apply soft deletion.
   - Add `restore(Offering $offering): bool`:
     - Ensure the offering is currently trashed.
     - Call `$offering->restore()` to clear the deletion timestamp while keeping existing attributes intact.
   - Add `hasHistoricalActivity(Offering $offering): bool`:
     - Check for associations with customer queue tickets, orders, or active transactions.
     - Return `false` if no historical records are linked; return `true` if referenced, preventing database constraint violations.
   - Add `deletePermanently(Offering $offering): bool`:
     - Check `hasHistoricalActivity()`. If activity exists, throw a `DomainException` preventing deletion.
     - If safe, remove the reference image from disk storage via `$this->deleteImage($offering->reference_image)`.
     - Call `$offering->forceDelete()` to permanently remove the database row.

3. **Presentation & Livewire Layer (`resources/views/pages/business/offerings/⚡index.blade.php`)**:
   - Update the status filter selector to include the **"Archived"** option:
     - `<flux:select.option value="archived">{{ __('Archivados') }}</flux:select.option>`.
   - Add component state and lifecycle actions:
     - Modal tracking properties: `$confirmingArchiveId = null;`, `$confirmingRestoreId = null;`, `$confirmingPermanentDeleteId = null;`.
     - Action methods:
       - `confirmArchive(int $id)` and `archive()`: verifies ownership via `$business->offerings()`, executes service archive, resets modal state, and emits success toast.
       - `confirmRestore(int $id)` and `restore()`: verifies ownership via `$business->offerings()->onlyTrashed()`, executes service restore, resets modal state, and emits success toast.
       - `confirmPermanentDelete(int $id)` and `deletePermanently()`: verifies ownership, checks historical activity, executes service force delete, resets modal state, and emits success or error toast.
   - UI Composition (Flux UI & Semantic Tokens):
     - Table rows:
       - For standard offerings (active/inactive): render an **"Archive"** button (or dropdown action) with an archive icon.
       - For archived offerings:
         - Render an `<flux:badge color="zinc">{{ __('Archivado') }}</flux:badge>` badge in the status column.
         - Render **"Restaurar"** (secondary button) and **"Eliminar definitivamente"** (danger button) in the actions column.
     - Modals:
       - **Archive Modal (`<flux:modal>`):** Asks for confirmation, explaining that the offering will be hidden from customer discovery while historical data remains safe.
       - **Restore Modal (`<flux:modal>`):** Asks for confirmation to return the offering to the active catalog.
       - **Permanent Delete Modal (`<flux:modal>`):** High-friction warning emphasizing that permanent deletion is irreversible.
     - Empty states:
       - Contextual empty state for archived filter: _"No se encontraron productos o servicios archivados."_
     - Notification feedback:
       - Success toasts matching acceptance criteria: _"Service archived successfully."_, _"Service restored successfully."_, and _"Service permanently deleted."_

4. **Testing Layer (Pest 5)**:
   - Create feature test file `tests/Feature/OfferingArchiveAndDeleteTest.php` covering:
     - Unauthenticated user redirection to `/business/login`.
     - Archiving an active or inactive offering soft-deletes the record and removes it from the default catalog view.
     - Filtering by status `'archived'` displays exclusively soft-deleted offerings for the authenticated business.
     - Multi-tenant isolation: A business cannot archive, restore, or delete offerings belonging to another business.
     - Restoring an archived offering clears `deleted_at` and restores it to the active catalog.
     - Permanent deletion permanently destroys the database record and removes the uploaded reference image from storage.
     - Permanent deletion protection: blocks deletion when historical queue/order activity is linked.
     - Pagination reset when switching to or from the 'archived' filter.
   - Execute mandatory pre-commit verification via Docker:
     - `docker compose exec turnex-app vendor/bin/pint --dirty`
     - `docker compose exec turnex-app php artisan test --compact`

## Decisions

- **Two-Stage Lifecycle (Soft Delete $\rightarrow$ Guarded Force Delete) over Direct Hard Deletion** — Prevents catastrophic data loss from accidental clicks during busy business hours, mirrors the existing category soft-delete pattern from `US13`, and prevents foreign key constraint crashes on historical queue activity.
- **Service Layer Encapsulation (`OfferingService`)** — Keeps the Livewire SFC focused solely on UI state and event handling, while keeping data mutation, disk cleanups, and historical activity safeguards reusable and easily testable.
- **Image Deletion Deferred to Permanent Deletion** — Uploaded images are preserved on disk during soft deletion so that restoring an offering immediately recovers its visual presentation. Images are only purged from disk upon confirmed `forceDelete()`.
- **High-Friction Confirmation Dialogs** — Uses Flux UI modals with explicit warning copy and loading states for destructive actions to avoid double clicks or accidental submissions.
- **Tenant-Scoped Lookups (`$business->offerings()->withTrashed()`)** — Guarantees multi-tenant isolation by ensuring a merchant can never query, archive, restore, or permanently destroy another business's records.

## Risks

- **Corrupting Past Queue or Order Records on Force Delete** — Permanently deleting an offering that was previously used in a customer queue ticket or completed order would cause orphan references or cascade deletions.
  - *Mitigation:* Implement `hasHistoricalActivity()` in `OfferingService` to block permanent deletion if historical records reference the offering, instructing the user that the item must remain archived.
- **Storage Disk Orphan Files** — If permanent deletion occurs but image deletion fails (or vice versa), abandoned files might accumulate in storage.
  - *Mitigation:* Remove the image file from storage only after validating constraints, and ensure clean error handling.
- **Tenant Query Boundary Leaks with `withTrashed()`** — Calling `withTrashed()` on an unconstrained model query could accidentally expose soft-deleted items across tenant boundaries.
  - *Mitigation:* Always root all Eloquent queries in the authenticated `$business->offerings()` relationship before chaining `onlyTrashed()` or `withTrashed()`.
