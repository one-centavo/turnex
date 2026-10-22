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

- [ ] Create Livewire 4 Single-File Component (SFC) `resources/views/pages/auth/customer/⚡login.blade.php` using Flux UI.
- [ ] Implement form inputs: unified identifier (email/phone), password with visibility toggle, and "Remember me" checkbox.
- [ ] Bind validation rules and handle submission feedback without clearing the entered identifier on failure.
- [ ] Handle post-authentication session establishment and intelligent redirection to intended destination or discovery catalog by default (`redirectIntended`).
- [ ] Register `/login` route with `guest:customer` middleware in `routes/web.php`.

## Verification & QA

- [ ] Write comprehensive Pest feature tests in `tests/Feature/CustomerLoginTest.php` covering all acceptance criteria:
    - [ ] Redirect authenticated users away from `/login`.
    - [ ] Successful login with email and phone number.
    - [ ] Validation feedback on empty inputs.
    - [ ] Generic error message on invalid credentials.
    - [ ] Guidance message for Google-only accounts without password.
    - [ ] Preserving identifier input and clearing password on failed attempt.
    - [ ] Rate limiting (lockout for 1 minute after 5 failed attempts) and attempt counter reset on success.
    - [ ] Session lifetime (2 hours) and "Remember me" persistence.
    - [ ] Redirection to intended URL vs default catalog.
- [ ] Run Pint to fix and enforce code style (`vendor/bin/pint`).
- [ ] Run Larastan to verify static analysis (`vendor/bin/phpstan analyse`).
- [ ] Verify all acceptance criteria from `spec.md`.
- [ ] Update feature status to "Done" in `spec/features/003-customer-login/spec.md` and roadmap.
