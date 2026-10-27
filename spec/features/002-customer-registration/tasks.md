# 002 · Customer Registration — Tasks

## Database & Model

- [x] Create migration for the `customers` table based on `docs/design/entity-relation.md`.
- [x] Create `app/Models/Customer.php` with casts (`phone_verified_at`, `email_verified_at`, `password` => 'hashed') and `database/factories/CustomerFactory.php`.
- [x] Verify schema allows nullable `phone_number`.

## Validation & Business Logic

- [x] Create `app/Rules/CustomerRules.php` with reusable atomic rule sets (`firstName()`, `lastName()`, `email()`, `password()`, `terms()`).
- [x] Refactor `app/Services/CustomerService.php` to handle customer registration without requiring `phone_number` and mark `email_verified_at = now()`.

## User Interface & Registration Flow

- [x] Refactor Livewire 4 Single-File Component `resources/views/pages/auth/customer/⚡register.blade.php` using Flux UI:
  - [x] Step 1: remove phone field, validate credentials, rate limit (5 req/min), dispatch Email OTP via `OtpService::generate($email, ContactType::Email)`.
  - [x] Step 2: 6-digit challenge code verification, masked email display, max 3 email edits with unified 60s cooldown, and email resend.
  - [x] Finalization: invoke `CustomerService::register()`, authenticate customer session, regenerate session ID, and display confirmation/redirect.

## Verification & QA

- [x] Update comprehensive Pest feature tests in `tests/Feature/CustomerRegistrationTest.php` covering the Email OTP flow.
- [x] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [x] Run Larastan to verify strict static analysis (`vendor/bin/phpstan analyse`).
- [x] Verify all acceptance criteria from `spec.md`.
- [x] Update feature status to "Done" in `spec/features/002-customer-registration/spec.md`.
