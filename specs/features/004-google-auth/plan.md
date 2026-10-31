# 004 · Google Auth — Plan

## Approach

The Google Auth will live on `CustomerAuthService` using the Laravel Socialite package, it allows handling the OAuth redirect and callback without manual HTTP requests. `CustomerAuthService` will validate the email existence, create the account if it doesn't exist or link the Google Account if it exists. This feature will be implemented in the login and registration views.

## Implementation

1. Install Laravel Socialite and configure Google OAuth service credentials — `composer.json`, `config/services.php`, and `.env.example`.
2. Implement Google user resolution, account linking, and provisioning in `CustomerAuthService` — `app/Services/CustomerAuthService.php`.
3. Register Google OAuth redirect and callback routes with dedicated controller handling authentication responses — `routes/web.php` and `app/Http/Controllers/Auth/CustomerGoogleAuthController.php`.
4. Add "Continue with Google" button to login and registration views — `resources/views/pages/auth/customer/⚡login.blade.php` and `⚡register.blade.php`.
5. Write automated feature tests covering OAuth redirect, existing account linking, new account provisioning, error handling, and intended redirection — `tests/Feature/CustomerGoogleAuthTest.php`.

## Decisions

- **Laravel Socialite over custom OAuth HTTP client** — Leverages Laravel's official, well-tested package for OAuth 2.0 redirect and callback flows, avoiding manual token exchange implementation and ensuring secure state verification out of the box.
- **Extending `CustomerAuthService` over dedicated OAuth service or controller logic** — Consolidates customer authentication lifecycle, account resolution, and session management in one cohesive domain service rather than scattering customer creation across multiple controllers.
- **Dedicated HTTP controller and routes over Livewire SFC actions for OAuth lifecycle** — Uses standard web routes (`/auth/google/redirect` and `/auth/google/callback`) to handle the full browser HTTP redirects and query-parameter exchanges required by OAuth 2.0 protocols.
- **Unified account resolution over strict separation between login and registration** — The Google button dynamically detects existing accounts by email or Google ID to link and log in, or provisions a new passwordless account if unregistered, preventing user confusion and login dead-ends.
- **Deferring phone number verification to a separate onboarding step** — Keeps Google authentication focused on fast, single-click access without coupling the flow to mobile OTP verification or queue-specific contact requirements.

## Risks

- **OAuth state expiration and CSRF mismatch (`InvalidStateException`)** — Users may leave the Google consent screen open until session cookies expire or switch networks; mitigated by wrapping the callback flow in a `try/catch` block that catches Socialite exceptions and redirects back to the login view with a user-friendly notice (_"No se pudo completar la autenticación con Google. Por favor, intenta de nuevo."_) instead of an unhandled 500 error.
- **Incomplete profile attributes from Google (missing last name or avatar)** — Google accounts are not guaranteed to provide a family name or profile picture; mitigated by keeping `avatar_url` nullable and providing safe default fallbacks for missing name attributes in `CustomerAuthService` so customer provisioning never fails.
- **Account collision and pre-registration hijacking** — An attacker might attempt to pre-register a victim's email; mitigated because Google guarantees email verification, automatically confirming `email_verified_at = now()`, linking the customer account cleanly, and preserving any existing password for dual-method access.
- **Session fixation and stale authentication on shared devices** — A social login session on a shared terminal could remain vulnerable if sessions are reused; mitigated by regenerating session IDs upon successful authentication (`session()->regenerate()`) on the `customer` guard and enforcing standard inactivity expiration.
