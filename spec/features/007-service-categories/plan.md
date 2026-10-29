# 007 · Service Categories — Plan

## Approach

Implement the core `Category` entity with schema migrations, Eloquent model, factory, and idempotent seeder inside the Laravel backend. This lays down the persistence layer specified in the data dictionary and ERD, satisfying the prerequisites for offerings and catalogs without coupling to immediate SuperAdmin UI development.

## Implementation

1. **Migration**: Create migration `xxxx_xx_xx_xxxxxx_create_categories_table.php` defining `id`, `name` (unique, varchar 60), `description` (varchar 200, nullable), `is_active` (boolean, default true), `softDeletes()`, and `timestamps()`.
2. **Model**: Create `app/Models/Category.php` using strict typing (`declare(strict_types=1);`), `#[Fillable]` attribute, `SoftDeletes` trait, `HasFactory`, boolean cast for `is_active`, PHPDoc type annotations, and an `active()` query scope.
3. **Factory**: Create `database/factories/CategoryFactory.php` defining sensible default fake data and state modifiers: `active()`, `inactive()`, `trashed()`.
4. **Seeder**: Create `database/seeders/CategorySeeder.php` with an idempotent list of standard Colombian/Latin-American market categories used across queue-enabled venues, and integrate into `database/seeders/DatabaseSeeder.php`.
5. **Testing**: Write comprehensive Pest tests in `tests/Feature/CategoryTest.php` covering schema integrity, soft deleting and restoration, active scopes, factory states, and seeder idempotency.

## Decisions

- **Global Category Taxonomy over Merchant-Specific Categories** — Keeps platform search uniform and aligned with [entity-relationship-diagram.md](file:///home/one-centavo/Proyectos/turnex/docs/design/database/entity-relationship-diagram.md), where categories belong to the platform rather than individual merchants.
- **Soft Deletes (`deleted_at`)** — Enforces US11 and US13 requirements to archive obsolete categories while preserving relational integrity with existing offerings (`ON DELETE RESTRICT`).
- **Idempotent Seeding via `updateOrCreate`** — Prevents duplicate key exceptions when seeding is run repeatedly in CI/CD pipelines or local development environments.
- **PHP 8 `#[Fillable]` Attribute** — Adheres to modern Laravel 12/13 conventions established across existing models (`Business.php`, `Customer.php`).

## Risks

- **Uniqueness conflicts on soft-deleted categories** — If a category is soft deleted and an administrator later attempts to create a category with the same name, standard unique constraints could block insertion.
  - *Mitigation:* The unique constraint is enforced at schema level; for the initial foundation, names remain globally unique. When the SuperAdmin UI is implemented, unique validation will exclude trashed records or provide unarchive options as dictated by US10/US12.
- **Accidental deletion cascading to offerings** — Deleting a category while offerings reference it could leave orphans or violate foreign keys.
  - *Mitigation:* Foreign key constraint on `offerings.category_id` will use `RESTRICT`, and Eloquent will prevent hard deletes.
