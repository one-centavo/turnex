# 007 · Service Categories

**Status:** Done

## What it does

The Service Categories feature establishes the global grouping taxonomy required to classify merchant products, services, and appointment types across TURNEX.

It defines the persistence layer (`categories` table) with soft deletion support, the Eloquent model (`Category`) with factory states, and an initial database seeder (`CategorySeeder`) populated with standardized commercial categories (e.g., Food & Beverages, Beauty & Personal Care, Health & Wellness, Professional Services, Retail, Customer Service & Administration).

This provides the prerequisite foreign key target (`category_id`) and relational backbone required for businesses to register offerings (US04/US05) and for customers to filter storefront catalogs without waiting for a full SuperAdmin front-end management portal.

## Why

1. **Unblocks Merchant Offerings (US04/US05):** In accordance with the database design ([entity-relationship-diagram.md](file:///home/one-centavo/Proyectos/turnex/docs/design/database/entity-relationship-diagram.md)), commercial offerings (`offerings` table) require a mandatory foreign key reference to `categories.id` (`ON DELETE RESTRICT`). Without categories, merchants cannot register products or services.
2. **Standardized Platform Taxonomy:** Centralizing categories at the system level ensures coherent discovery, prevents arbitrary tags, and prepares the platform for category-based filtering when customers search for commercial venues.
3. **Seed-Driven Development:** Seeding standard categories during deployment and test execution enables immediate automated end-to-end testing and rapid feature delivery for both merchant dashboards and customer catalogs.

## Acceptance Criteria

- [x] **Database Schema & Constraints**:
  - [x] The `categories` table includes: `id` (bigint unsigned PK), `name` (varchar 60, unique), `description` (varchar 200, nullable), `is_active` (boolean, default true), `deleted_at` (timestamp, nullable for soft deletes), and standard timestamps (`created_at`, `updated_at`).
  - [x] Attempting to insert a duplicate category name is prevented at the database schema level.
- [x] **Eloquent Model & Business Rules**:
  - [x] The `Category` model defines `$fillable` (via PHP 8 `#[Fillable]` attribute) for `name`, `description`, and `is_active`.
  - [x] `is_active` is cast to boolean.
  - [x] The model incorporates Laravel's `SoftDeletes` trait to archive records without permanent physical deletion.
  - [x] The model includes query scopes: `active()` (filtering by `is_active = true`), and default exclusion of soft-deleted records.
- [x] **Factory & States**:
  - [x] `CategoryFactory` generates valid realistic category records.
  - [x] The factory provides state modifiers: `active()`, `inactive()`, and `trashed()`.
- [x] **Baseline Database Seeder**:
  - [x] `CategorySeeder` idempotently provisions standard primary categories (e.g., *Alimentos y Bebidas*, *Belleza y Cuidado Personal*, *Salud y Bienestar*, *Servicios Profesionales*, *Comercio Minorista*, *Trámites y Atención al Cliente*).
  - [x] Re-running the seeder (`db:seed --class=CategorySeeder`) does not produce duplicate key errors or duplicate records (`updateOrCreate` / `firstOrCreate`).
  - [x] `DatabaseSeeder` includes `CategorySeeder` in its execution sequence.
- [x] **Code Quality & Testing**:
  - [x] Automated Pest feature tests (`tests/Feature/CategoryTest.php`) verify schema constraints, soft delete behavior, factory states, and seeder idempotency.

## Out of Scope

- SuperAdmin web UI for creating, viewing, updating, or deleting categories (covered in US10, US11, US12, US13 when the SuperAdmin management portal is built).
- Business-specific custom category assignment (categories remain global/standardized at the platform level).
