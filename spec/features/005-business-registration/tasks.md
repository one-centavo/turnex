# 005 · Business Registration — Tasks

## Database & Model

- [x] Create enums `app/Enums/VerificationStatus.php`, `app/Enums/BusinessCategory.php`, and `app/Enums/PersonType.php`.
- [x] Create migration for the `businesses` table based on `docs/design/database/entity-relation-diagram.md`.
- [x] Create `app/Models/Business.php` with casts (enums, timestamps, password => 'hashed') and `database/factories/BusinessFactory.php`.
- [x] Configure `business` authentication guard and provider in `config/auth.php`.
- [x] Create migration to make `phone_number` in `businesses` table nullable.

## Validation & Business Logic

- [x] Create `app/Rules/BusinessRules.php` with reusable atomic rule sets (`businessName()`, `category()`, `location()`, `ownerFirstName()`, `ownerLastName()`, `email()`, `phone()`, `password()`, `terms()`).
- [x] Refactor `app/Services/BusinessService.php` to handle business registration without requiring `phone_number` and mark `email_verified_at = now()`.
- [x] Update `app/Notifications/WelcomeBusinessNotification.php` for verified email confirmation.

## User Interface & Registration Flow

- [x] Refactor Livewire 4 Single-File Component `resources/views/pages/auth/business/⚡register.blade.php` using Flux UI:
  - [x] Step 1: remove phone field, validate store and owner details, rate limit (5 req/min), dispatch Email OTP via `OtpService::generate($email, ContactType::Email)`.
  - [x] Step 2: 6-digit challenge code verification, masked email display, max 3 email edits with 60s cooldown, and email resend.
  - [x] Finalization: invoke `BusinessService::register()`, authenticate business session, regenerate session ID, show confirmation, and redirect to dashboard in setup mode.

## Verification & QA

- [x] Update comprehensive Pest feature tests in `tests/Feature/BusinessRegistrationTest.php` and `tests/Feature/BusinessServiceTest.php`.
- [x] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [x] Run Larastan to verify strict static analysis (`vendor/bin/phpstan analyse`).
- [x] Verify all acceptance criteria from `spec.md`.
- [x] Update feature status to "Done" in `spec/features/005-business-registration/spec.md`.
