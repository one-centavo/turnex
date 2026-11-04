# 011 · Archive and Delete Business Offerings — Tasks

## Persistence & Data Layer

- [x] Create database migration `add_deleted_at_to_offerings_table` with `$table->softDeletes()`.
- [x] Add `Illuminate\Database\Eloquent\SoftDeletes` trait to `App\Models\Offering`.
- [x] Update PHPDoc in `App\Models\Offering` to include `@property Carbon|null $deleted_at`.

## Domain & Service Layer

- [x] Extend `listForBusiness()` in `App\Services\OfferingService` to support status `'archived'` querying `$business->offerings()->onlyTrashed()`.
- [x] Implement `archive(Offering $offering): bool` in `OfferingService` setting `is_active = false` and soft-deleting the record.
- [x] Implement `restore(Offering $offering): bool` in `OfferingService` restoring the soft-deleted record.
- [x] Implement `hasHistoricalActivity(Offering $offering): bool` in `OfferingService` to safeguard against deleting offerings with historical records.
- [x] Implement `deletePermanently(Offering $offering): bool` in `OfferingService` checking historical activity, purging reference images from disk, and executing `forceDelete()`.

## Presentation & Livewire Layer

- [x] Add `'archived'` option to the status filter dropdown in `resources/views/pages/business/offerings/⚡index.blade.php`.
- [x] Add component state for confirmation modals (`$confirmingArchiveId`, `$confirmingRestoreId`, `$confirmingPermanentDeleteId`).
- [x] Implement action methods in the Livewire component: `confirmArchive()`, `archive()`, `confirmRestore()`, `restore()`, `confirmPermanentDelete()`, and `deletePermanently()`.
- [x] Add table actions column rendering "Archivar" for standard offerings, and "Restaurar" / "Eliminar definitivamente" for archived offerings.
- [x] Add "Archivado" badge for soft-deleted offerings in the table.
- [x] Build Flux UI confirmation modals for archiving, restoring, and permanent deletion with explicit warning copy.
- [x] Add feedback notifications (success toasts) matching the acceptance criteria.

## Verification & QA

- [x] Create Pest feature test suite in `tests/Feature/OfferingArchiveAndDeleteTest.php` covering:
  - [x] Unauthenticated guest redirection to `/business/login`.
  - [x] Strict multi-tenant isolation (cannot archive, restore, or delete another business's offering).
  - [x] Archiving an offering (soft-delete applied, removed from default catalog list).
  - [x] Status filter `archived` exclusively listing soft-deleted offerings for the authenticated business.
  - [x] Restoring an archived offering (clears deletion timestamp, reappears in active catalog).
  - [x] Permanent deletion of an unreferenced offering removes record and purges image file from disk.
  - [x] Permanent deletion prevention when historical activity is present.
  - [x] Pagination reset upon switching to or from the 'archived' filter.
- [x] Run feature tests via Docker: `docker compose exec turnex-app php artisan test --filter=OfferingArchiveAndDeleteTest --compact`.
- [x] Run full test suite regression: `docker compose exec turnex-app php artisan test --compact`.
- [x] Run Pint formatter: `docker compose exec turnex-app vendor/bin/pint`.
- [x] Run Larastan analysis: `docker compose exec turnex-app vendor/bin/phpstan analyse`.
- [x] Mark completed tasks in `spec.md` and `tasks.md`.
