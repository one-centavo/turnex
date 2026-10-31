# 006 · Business Login — Plan

## Approach

Implement a dedicated `BusinessAuthService` in the service layer to handle credential validation, rate limiting, and session authentication on the `business` guard, paired with a Livewire 4 Single-File Component using Flux UI at `/business/login` with "Remember me" support and password visibility toggle.

## Implementation

1. Verify `businesses` table schema — `remember_token` column is already provisioned in migration `2026_10_25_150000_create_businesses_table.php`.
2. Implement business authentication service handling email normalization, credential validation, throttle key generation, and rate limiting — `app/Services/BusinessAuthService.php`.
3. Implement business login interface as a Livewire 4 Single-File Component using Flux UI with password visibility toggle, "Remember me" checkbox, and "¿Olvidaste tu contraseña?" link — `resources/views/pages/auth/business/⚡login.blade.php`.
4. Register `/business/login` route with `guest:business` middleware and intended redirection — `routes/web.php`.
5. Write comprehensive automated feature tests covering credential verification, rate limiting, lockout duration, session persistence, guard isolation, and intended redirection — `tests/Feature/BusinessLoginTest.php`.

## Decisions

- **Dedicated `BusinessAuthService` over sharing `CustomerAuthService` or embedding in Livewire** — Keeps domain boundaries clear, prevents cross-guard contamination, encapsulates rate-limiting keys with `business_login:` namespace, and leaves presentation components focused on UI events.
- **Dedicated `business` authentication guard over default `web` guard** — Enforces strict security boundary and session isolation between queue consumers (`Customer`), merchants (`Business`), and system administrators (`User`).
- **In-memory cache rate limiting over database attempt logging** — Uses Laravel's `RateLimiter` for the 5-attempt/1-minute lockout threshold, preventing brute-force attacks without generating unnecessary MySQL write overhead.
- **Generic anti-enumeration error response** — Returns a unified error message (_"Credenciales incorrectas. Por favor, intenta de nuevo."_) regardless of whether the email or password was invalid.
- **Forgot password entry point in view** — Integrates the "¿Olvidaste tu contraseña?" link directly into the form interface, deferring backend recovery logic to a subsequent feature.

## Risks

- **Credential stuffing and brute-force attacks** — Automated bots could attempt rapid credential combinations against merchant accounts; mitigated by enforcing rate limiting via `RateLimiter` (maximum 5 failed attempts per minute with 1-minute cooldown).
- **Session fixation and stale authentication** — Sessions established on shared or public workstations could remain open; mitigated by regenerating session IDs upon successful authentication (`session()->regenerate()`) and defaulting to a 2-hour inactivity expiration unless "Remember me" is explicitly selected.
- **Cross-guard session conflict** — Merchants who are also customers could experience session overwrite; mitigated by using independent cookie session stores and guards (`customer` vs `business`).
