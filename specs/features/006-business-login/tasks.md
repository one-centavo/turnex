# 006 · Business Login — Tasks

## Database & Schema

- [x] Verify `remember_token` column exists on `businesses` table (already provisioned).

## Authentication & Business Logic

- [x] Create `app/Services/BusinessAuthService.php` to encapsulate business login lifecycle.
- [x] Implement email normalization using `DataNormalizer::email()`.
- [x] Implement credential verification against `business` guard (`Auth::guard('business')`).
- [x] Implement rate limiting and lockout logic (5 attempts per minute with 1-minute lockout cooldown) using Laravel's `RateLimiter`.
- [x] Implement session regeneration and "Remember Me" persistent token support.

## User Interface & Presentation Layer

- [x] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/auth/business/⚡login.blade.php` using Flux UI.
- [x] Implement form inputs: email address, password with visibility toggle, "Remember me" checkbox, and "¿Olvidaste tu contraseña?" link.
- [x] Bind validation rules and handle submission feedback without clearing the entered email on failure.
- [x] Handle post-authentication session establishment and intelligent redirection to intended destination or `/business/dashboard` by default (`redirectIntended`).
- [x] Register `/business/login` route with `guest:business,web` middleware in `routes/web.php`.

## Verification & QA

- [x] Write comprehensive Pest feature tests in `tests/Feature/BusinessLoginTest.php` covering all acceptance criteria:
    - [x] Redirect authenticated business users away from `/business/login`.
    - [x] Successful login with registered business email address and password.
    - [x] Validation feedback on empty inputs.
    - [x] Generic error message on invalid credentials.
    - [x] Preserving email input and clearing password on failed attempt.
    - [x] Rate limiting (lockout for 1 minute after 5 failed attempts) and attempt counter reset on success.
    - [x] Session lifetime (2 hours) and "Remember me" persistence.
    - [x] Redirection to intended URL vs default `/business/dashboard`.
    - [x] Display of "¿Olvidaste tu contraseña?" recovery link.
    - [x] Guard isolation (customer session remains unaffected when logging in as business).
- [x] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [x] Run Larastan to verify static analysis (`vendor/bin/phpstan analyse`).
- [x] Verify all acceptance criteria from `spec.md`.

