# Tech Stack & Conventions

Defines the architecture and development guidelines for TURNEX. This is the binding technical reference that all code and feature plans must adhere to.

## Technologies

- **Backend:** PHP 8.4, Laravel 13, Laravel Fortify (headless authentication & 2FA).
- **Frontend:** Livewire 4 (native Single-File Components `⚡`), Flux UI 2, Livewire Blaze 1, Alpine.js 3, Tailwind CSS 4.
- **Database:** MySQL 8.4 (InnoDB engine, ACID transactions, UTF8MB4 collation).
- **Tooling & QA:** Pest 5 (automated testing), Larastan 3 (strict PHPStan static analysis), Pint 1 (PSR-12/Laravel code style linter), Vite 8 + Vite Plus, pnpm 11.
- **Runtime Environment:** Docker Compose with dedicated containers (`turnex-app`, `turnex-web`, `turnex-db`, `turnex-vite`).

## Key Files & Modules

- `app/Livewire/` — Single-File Components (`⚡`) and interactive classes handling UI interactions.
- `app/Models/` & `app/Enums/` — Eloquent models and string-backed Enums written in `TitleCase`.
- `app/Services/` — Core business domain logic (OTP verification service, queue processing, transactional notifications).
- `resources/views/` — Base layouts, Blade templates, and Flux UI design components.
- `tests/Feature/` & `tests/Unit/` — Automated test suites written in Pest.
- `docker/` & `docker-compose.yml` — Containerized local environment configuration (Nginx, PHP-FPM, MySQL 8.4).

## Commands (Docker Mandatory)

All development, Artisan, and test commands MUST execute inside their respective containers:

- **Local environment:** `docker compose up -d`
- **Vite Dev Server:** `docker compose run --rm turnex-vite pnpm run dev`
- **Frontend Build:** `docker compose run --rm turnex-vite pnpm run build`
- **Database Migrations:** `docker compose exec turnex-app php artisan migrate`
- **Fresh Seed:** `docker compose exec turnex-app php artisan migrate:fresh --seed`
- **Run Pest Tests:** `docker compose exec turnex-app php artisan test --compact`
- **Filtered Test:** `docker compose exec turnex-app php artisan test --filter=testName`
- **Code Style (Pint):** `docker compose exec turnex-app vendor/bin/pint --dirty`
- **Static Analysis (Larastan):** `docker compose exec turnex-app vendor/bin/phpstan analyse`

## Data Model & Domain Invariants

- `SECURITY_CODE`: 6-digit numeric codes for OTP verification. Codes expire in 5 minutes, enforce a 1-minute resend cooldown, and allow a maximum of 5 failed attempts before invalidation (US-CORE).
- `CUSTOMER`: Stores end customers. Supports authentication via credentials or Google OAuth (`google_id`), requiring an E.164-normalized mobile phone number (`+57...`) for queue notifications.
- `BUSINESS`: Associated merchants. Managed through verification states (`PENDING_VERIFICATION`, `UNDER_REVIEW`, `APPROVED`, `ACTION_REQUIRED`). Only approved businesses are publicly visible and can process payments/queues.
- `BUSINESS_DOCUMENT` & `BUSINESS_BANK_ACCOUNT`: KYB compliance records (Tax ID, Representative ID, Bank Certificate) required prior to SuperAdmin approval.
- `CATEGORY` & `OFFERINGS`: Standardized classification for services (with estimated duration in minutes) and products (with price) offered by each business.
- `Virtual Queue (MVP)`: Real-time ticket statuses monitored via Livewire polling (`wire:poll.5s`), providing synchronized updates without WebSockets overhead.

## Conventions

- **Strictly English:** All source code, variable names, methods, classes, database tables/columns, migrations, routes, Git commits, and PRs MUST be written strictly in **English**. Spanish is reserved solely for user-facing UI copy.
- **Strict Typing:** Always declare `declare(strict_types=1);` across all PHP files, with explicit parameter and return types. Control structures must always use curly braces `{}`.
- **Constructor Property Promotion:** Use PHP 8 constructor property promotion for dependency injection (`public function __construct(public QueueService $service) {}`).
- **Enums:** String-backed Enums written in `TitleCase` (e.g., `TicketStatus::Pending`, `BusinessStatus::Approved`).
- **Artisan Scaffolding:** Scaffold components using `php artisan make:... --no-interaction`, accompanying every model with a factory and seeder.
- **Frontend State Handling:** Keep state server-side and validated via `#[Validate]` attributes or `rules()` method. Purely cosmetic client-side interactions (modals, dropdowns, collapsibles) are handled via Alpine.js (`x-data`, `x-show`, `x-cloak`).
- **Commit Format:** Conventional Commits using imperative present tense in English (`feat(queue): implement polling refresh`).

## Visual Style

- **Design System & Components:** Flux UI 2 combined with Tailwind CSS 4.
- **Mobile-First:** Responsive layout prioritizing mobile viewports for users waiting on-site or on the go.
- **Loading States:** Leverage `wire:loading` with automatic button disabling to prevent double submissions and race conditions.

## Hard Boundaries

- **Never execute commands on the host:** Do not run Artisan, Composer, or pnpm outside Docker containers.
- **No WebSockets for MVP:** Avoid Reverb, Pusher, or socket overhead. Real-time queue updates must be handled using Livewire polling (`wire:poll.5s`).
- **No Spanglish:** Never mix English and Spanish within codebase identifiers, comments, or database structures.
- **No direct commits to `main`:** All work must originate from dedicated branches (`feature/*`, `bugfix/*`).
- **No manual ad-hoc verification scripts:** All behavior must be verified through automated, repeatable Pest tests.
- **Security & Secrets:** Never commit `.env` files or real secrets to the repository.
