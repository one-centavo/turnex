# 002 · Customer Registration — Tasks

## Database & Model

- [x] Create migration for the `customers` table based on `docs/design/entity-relation.md`.
- [x] Create `app/Models/Customer.php` with casts (`phone_verified_at`, `email_verified_at`, `password` => 'hashed') and `database/factories/CustomerFactory.php`.
- [x] Run migration and verify schema in Docker.

## Validation & Business Logic

- [x] Create `app/Rules/CustomerRules.php` with reusable atomic rule sets (`firstName()`, `lastName()`, `email()`, `phone()`, `password()`, `terms()`).
- [x] Create `app/Notifications/WelcomeCustomerNotification.php` for deferred asynchronous welcome and email verification.
- [x] Create `app/Services/CustomerService.php` to handle customer creation, data normalization (`DataNormalizer`), credential hashing, and verification timestamp recording.
- [x] Write unit and service tests for `CustomerRules` and `CustomerService`.

## User Interface & Registration Flow

- [x] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/auth/customer/⚡register.blade.php` using Flux UI.
- [x] Implement Step 1: form input binding, real-time/submit validation against `CustomerRules`, duplicate pre-checks, IP rate limiting (5 req/min), and WhatsApp OTP dispatch via `OtpService`.
- [x] Implement Step 2: 6-digit challenge verification, server-side session data store, max 3 phone number edits with unified cooldown, and SMS fallback resend.
- [x] Implement account provisioning upon successful verification: invoke `CustomerService`, authenticate customer session, regenerate session ID, and redirect to intended dashboard.
- [x] Register `/register` route with `guest` middleware.

## Verification & QA

- [x] Write comprehensive Pest feature tests in `tests/Feature/CustomerRegistrationTest.php` covering all acceptance criteria (happy path, validation failures, rate limits, phone modification limit, invalid/expired OTP, auto-login).
- [x] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [x] Run Larastan to verify strict static analysis (`vendor/bin/phpstan analyse`).
- [x] Verify all acceptance criteria from `spec.md`.
- [x] Update feature status to "Done" in `spec/features/002-customer-registration/spec.md` and roadmap.
