# 006 · Business Login

**Status:** Ready for Dev

## What it does

The business login feature provides a secure authentication flow for business owners and operators to access their merchant dashboard and operations management portal.

Merchants enter their registered corporate/personal email address along with their password. Once credentials are validated against the `business` authentication guard, the system authenticates the merchant, regenerates the session, and redirects them to the business operations dashboard (`/business/dashboard`) or to their intended destination. If credentials do not match, the system displays an error message without revealing whether the email or password was invalid.

The login view also incorporates the entry link "¿Olvidaste tu contraseña?" to facilitate password recovery.

## Why

Business owners need a dedicated, secure entry point to access their business operations dashboard, configure catalogs, manage digital queues, and monitor incoming tickets.

Isolating merchant authentication behind a dedicated `business` guard prevents permission leakage, ensures strict separation from consumer customer accounts, and provides tailored routing according to merchant account status.

## Acceptance Criteria

- [ ] Authenticated business owners who access the login page are redirected to the business dashboard (`/business/dashboard`).
- [ ] Authenticated customers who visit the business login page are allowed to log into their business account without terminating or interfering with their customer session.
- [ ] The login form requests the merchant's email address and password.
- [ ] The password field allows toggling between hidden and visible text.
- [ ] The login form includes an optional "Remember me" checkbox.
- [ ] The login form includes a "¿Olvidaste tu contraseña?" link leading to the password recovery view.
- [ ] Both email and password fields are required; submitting empty values prompts the user with validation feedback.
- [ ] The entered email address is automatically trimmed and normalized to lowercase before credential verification.
- [ ] If the email address or password does not match an existing business account, the system displays: _"Credenciales incorrectas. Por favor, intenta de nuevo."_
- [ ] An unsuccessful login clears the password field but preserves the entered email address.
- [ ] Login attempts are rate-limited to a maximum of 5 failed attempts per minute per email and IP address; exceeding this threshold locks further attempts for 1 minute.
- [ ] A successful login clears the failed attempts rate limiter.
- [ ] Submitting valid credentials authenticates the merchant into the `business` guard with a standard 2-hour inactivity session.
- [ ] Selecting "Remember me" establishes a persistent session across browser restarts via `remember_token`.
- [ ] Upon successful login, the merchant is redirected to their intended destination, or to `/business/dashboard` by default.

## Out of Scope

- Password recovery and reset flow (deferred to dedicated password reset feature; only the entry link is integrated in the view).
- Third-party social login for businesses (all business accounts authenticate via email and password).
- Registration entry point (handled in feature `005 · Business Registration`).
