# 003 · Customer Login — Tasks

## Database & Schema

- [x] Create migration to add `remember_token` column to `customers` table — `database/migrations/2026_10_21_011121_add_remember_token_to_customers_table.php`.
- [x] Run migration and verify column exists in `customers` table via Docker.

## Authentication & Business Logic

- [x] Create `app/Services/CustomerAuthService.php` to encapsulate customer login lifecycle.
- [x] Implement dual-identifier resolution (determine if input is email or phone) and clean data using `DataNormalizer`.
- [x] Implement credential verification against `customer` guard (`Auth::guard('customer')`).
- [x] Implement check for Google-only accounts (`password` is null) to trigger specific Google Sign-In guidance.
- [x] Implement rate limiting and lockout logic (5 attempts per minute with 1-minute lockout cooldown) using Laravel's `RateLimiter`.
- [x] Implement session regeneration and "Remember Me" persistent token support.

## User Interface & Presentation Layer

- [x] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/auth/customer/⚡login.blade.php` using Flux UI.
- [x] Implement form inputs: unified identifier (email/phone), password with visibility toggle, "Remember me" checkbox, and "¿Olvidaste tu contraseña?" link.
- [x] Bind validation rules and handle submission feedback without clearing the entered identifier on failure.
- [x] Handle post-authentication session establishment and intelligent redirection to intended destination or discovery catalog by default (`redirectIntended`).
- [x] Register `/login` route with `guest:customer` middleware in `routes/web.php`.

## Verification & QA

- [x] Write comprehensive Pest feature tests in `tests/Feature/CustomerLoginTest.php` covering all acceptance criteria:
    - [x] Redirect authenticated users away from `/login`.
    - [x] Successful login with email and phone number.
    - [x] Validation feedback on empty inputs.
    - [x] Generic error message on invalid credentials.
    - [x] Guidance message for Google-only accounts without password.
    - [x] Preserving identifier input and clearing password on failed attempt.
    - [x] Rate limiting (lockout for 1 minute after 5 failed attempts) and attempt counter reset on success.
    - [x] Session lifetime (2 hours) and "Remember me" persistence.
    - [x] Redirection to intended URL vs default catalog.
    - [x] Display of "¿Olvidaste tu contraseña?" recovery link.
- [x] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [x] Run Larastan to verify static analysis (`vendor/bin/phpstan analyse`).
- [x] Verify all acceptance criteria from `spec.md`.
- [x] Update feature status to "Done" in `spec/features/003-customer-login/spec.md` and roadmap.
