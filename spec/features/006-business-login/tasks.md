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

- [ ] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/auth/business/⚡login.blade.php` using Flux UI.
- [ ] Implement form inputs: email address, password with visibility toggle, "Remember me" checkbox, and "¿Olvidaste tu contraseña?" link.
- [ ] Bind validation rules and handle submission feedback without clearing the entered email on failure.
- [ ] Handle post-authentication session establishment and intelligent redirection to intended destination or `/business/dashboard` by default (`redirectIntended`).
- [ ] Register `/business/login` route with `guest:business` middleware in `routes/web.php`.

## Verification & QA

- [ ] Write comprehensive Pest feature tests in `tests/Feature/BusinessLoginTest.php` covering all acceptance criteria:
    - [ ] Redirect authenticated business users away from `/business/login`.
    - [ ] Successful login with registered business email address and password.
    - [ ] Validation feedback on empty inputs.
    - [ ] Generic error message on invalid credentials.
    - [ ] Preserving email input and clearing password on failed attempt.
    - [ ] Rate limiting (lockout for 1 minute after 5 failed attempts) and attempt counter reset on success.
    - [ ] Session lifetime (2 hours) and "Remember me" persistence.
    - [ ] Redirection to intended URL vs default `/business/dashboard`.
    - [ ] Display of "¿Olvidaste tu contraseña?" recovery link.
    - [ ] Guard isolation (customer session remains unaffected when logging in as business).
- [ ] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [ ] Run Larastan to verify static analysis (`vendor/bin/phpstan analyse`).
- [ ] Verify all acceptance criteria from `spec.md`.

