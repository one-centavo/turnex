# 005 · Business Registration — Tasks

## Database & Model

- [x] Create enums `app/Enums/VerificationStatus.php`, `app/Enums/BusinessCategory.php`, and `app/Enums/PersonType.php`.
- [x] Create migration for the `businesses` table based on `docs/design/database/entity-relation-diagram.md`.
- [x] Create `app/Models/Business.php` with casts (enums, timestamps, password => 'hashed') and `database/factories/BusinessFactory.php`.
- [x] Configure `business` authentication guard and provider in `config/auth.php`.
- [x] Run migration and verify schema in Docker.

## Validation & Business Logic

- [x] Create `app/Rules/BusinessRules.php` with reusable atomic rule sets (`businessName()`, `category()`, `location()`, `ownerFirstName()`, `ownerLastName()`, `email()`, `phone()`, `password()`, `terms()`).
- [x] Create `app/Notifications/WelcomeBusinessNotification.php` for deferred asynchronous welcome and email verification.
- [x] Create `app/Services/BusinessService.php` to handle business provisioning, data normalization (`DataNormalizer`), credential hashing, and verification timestamp recording.
- [x] Write unit and service tests for `BusinessRules` and `BusinessService`.

## User Interface & Registration Flow

- [x] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/auth/business/⚡register.blade.php` using Flux UI.
- [x] Implement Step 1: form input binding, real-time/submit validation against `BusinessRules`, duplicate pre-checks, double-submit prevention, IP rate limiting (5 req/min), and WhatsApp OTP dispatch via `OtpService`.
- [x] Implement Step 2: 6-digit challenge verification, server-side session data store (15-min TTL), max 3 phone number edits with uniqueness check and preserved cooldown, and SMS fallback resend.
- [x] Implement account provisioning upon successful verification: invoke `BusinessService`, authenticate business session, regenerate session ID, show success confirmation, and redirect to dashboard in setup mode.
- [x] Register `/business/register` route with `guest` middleware.

## Verification & QA

- [x] Write comprehensive Pest feature tests in `tests/Feature/BusinessRegistrationTest.php` covering all acceptance criteria.
- [x] Run Pint to fix and enforce code style (`vendor/bin/pint --dirty`).
- [x] Run Larastan to verify strict static analysis (`vendor/bin/phpstan analyse`).
- [x] Verify all acceptance criteria from `spec.md`.
- [x] Update feature status to "Done" in `spec/features/005-business-registration/spec.md`.

