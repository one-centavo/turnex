# 007 · Service Categories — Tasks

## Database & Persistence

- [x] Create migration for `categories` table (`name`, `description`, `is_active`, `deleted_at`, `timestamps`).
- [x] Run migration inside Docker container (`docker compose exec turnex-app php artisan migrate`).

## Model & Factory

- [x] Implement `App\Models\Category` model with `declare(strict_types=1);`, `#[Fillable]`, `SoftDeletes`, `HasFactory`, boolean casting, and `active()` scope.
- [x] Implement `Database\Factories\CategoryFactory` with `active()`, `inactive()`, and `trashed()` state methods.

## Seeders

- [x] Create `Database\Seeders\CategorySeeder` with standard master categories using idempotent upserts (`updateOrCreate`).
- [x] Register `CategorySeeder` inside `Database\Seeders\DatabaseSeeder`.
- [x] Verify fresh database seeding via Docker (`docker compose exec turnex-app php artisan db:seed --class=CategorySeeder`).

## Verification & QA

- [x] Create Pest feature tests in `tests/Feature/CategoryTest.php` covering:
  - [x] Creation with valid attributes and default `is_active = true`.
  - [x] Uniqueness constraint on `name`.
  - [x] Soft deletion and restoration via `SoftDeletes`.
  - [x] `active()` query scope.
  - [x] Factory states (`active`, `inactive`, `trashed`).
  - [x] Seeder idempotency without errors on repeated executions.
- [x] Run test suite inside Docker (`docker compose exec turnex-app php artisan test --filter=CategoryTest`).
- [x] Run code formatter (`docker compose exec turnex-app vendor/bin/pint --dirty`).
- [x] Run Larastan analysis (`docker compose exec turnex-app vendor/bin/phpstan analyse`).
- [x] Update `spec/constitution/roadmap.md` and feature status.
