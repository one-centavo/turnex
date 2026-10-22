# 003 · Customer Login — Plan

## Approach

Implement a dedicated `CustomerAuthService` in the service layer to handle credentials validation and session authentication on the `customer` guard, providing a complete Livewire 4 interface with Remember Me support and entry link to password recovery.

## Implementation

1. Add `remember_token` column to `customers` table — `database/migrations/2026_10_21_011121_add_remember_token_to_customers_table.php`.
2. Implement customer authentication service handling identifier resolution, credential validation, and Google-only account detection — `app/Services/CustomerAuthService.php`.
3. Implement customer login interface as a Livewire 4 Single-File Component using Flux UI with password visibility toggle, remember me support, and forgot password link — `resources/views/pages/auth/customer/⚡login.blade.php`.
4. Register `/login` route with guest middleware and intended redirection — `routes/web.php`.
5. Write comprehensive automated feature tests covering credential verification (email and phone), rate limiting, lockout duration, session persistence, and intended redirection — `tests/Feature/CustomerLoginTest.php`.

## Decisions

- **Dedicated `CustomerAuthService` over embedding logic in `CustomerService` or Livewire** — Isolates authentication lifecycle, identifier resolution, and credential validation in a focused service without coupling unused dependencies (like `OtpService`), keeping Livewire components strictly presentation-focused.
- **Dual-identifier resolution with `DataNormalizer` over separate input fields** — Allows customers to enter either an email or mobile phone number in a single input, cleaning spaces and formatting international phone prefixes automatically to reduce mobile cognitive load.
- **Dedicated `customer` authentication guard over default `web` guard** — Enforces strict security boundary and session isolation between queue consumers (`Customer`) and merchant/administrative users (`User`).
- **Explicit Google OAuth guidance over generic rejection for passwordless accounts** — Detects accounts registered exclusively via Google OAuth and prompts the user to use Google Sign-In, eliminating login frustration and unnecessary password reset attempts.
- **In-memory cache rate limiting over persistent database attempt logging** — Leverages Laravel's `RateLimiter` for the 5-attempt/1-minute lockout threshold, preventing brute-force attacks without generating unnecessary MySQL write overhead.
- **Forgot password entry point in view** — Integrate the "¿Olvidaste tu contraseña?" link directly into the login form interface for seamless UX, deferring the dedicated backend recovery lifecycle to a subsequent feature.

## Risks

- **Credential stuffing and brute-force password guessing** — Automated bots could attempt rapid credential combinations to compromise customer accounts; mitigated by enforcing rate limiting via Laravel's `RateLimiter` (maximum 5 failed attempts per minute with a 1-minute lockout).
- **User enumeration via differential error responses** — Malicious actors could probe the login form to discover registered phone numbers or emails; mitigated by returning a unified generic error message (_"Credenciales incorrectas. Por favor, intenta de nuevo."_) regardless of whether the identifier or password was invalid.
- **Null password comparison failure on OAuth accounts** — Customers created via Google OAuth have a `NULL` password, which could cause unexpected evaluation behavior or PHP exceptions if evaluated blindly; mitigated by explicitly checking password existence in `CustomerAuthService` prior to hash verification.
- **Session fixation and stale authentication on shared devices** — A session established on a public device could remain vulnerable to hijacking; mitigated by regenerating session IDs upon successful authentication (`session()->regenerate()`) and defaulting to a 2-hour inactivity expiration unless "Remember me" is explicitly chosen.
