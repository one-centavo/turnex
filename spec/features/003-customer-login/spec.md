# 003 · Customer Login

**Status:** Done

## What it does

The customer login feature provides a secure authentication flow for customers to access their accounts.

Customers enter their identifier (email address or mobile phone number) along with their password. Once credentials are validated, the system authenticates the user and redirects them to the main discovery catalog, or displays an error message if the credentials do not match.

The login view also incorporates the entry link "¿Olvidaste tu contraseña?" to facilitate password recovery.

## Why

It is required for customers to log into their accounts and access the catalog and their information.

## Acceptance Criteria

- [x] Customers who are already logged in cannot access the login page and are redirected to the discovery catalog.
- [x] The login form requests the customer's identifier (email or mobile phone number) and password.
- [x] The password field allows toggling between hidden and visible text.
- [x] The login form includes an optional "Remember me" checkbox.
- [x] The login form includes a "¿Olvidaste tu contraseña?" link leading to the password recovery view.
- [x] Both identifier and password fields are required; submitting empty values prompts the customer to complete them.
- [x] The entered email or phone number is automatically cleaned and formatted before checking credentials.
- [x] If the email, phone number, or password does not match an existing account, the system displays: _"Credenciales incorrectas. Por favor, intenta de nuevo."_
- [x] If the account was registered with Google and has no password configured, the system displays: _"Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google."_
- [x] An unsuccessful login clears the password field but keeps the entered email or phone number.
- [x] Login attempts are limited to a maximum of 5 attempts per minute; exceeding this limit temporarily blocks further requests for 1 minute.
- [x] A successful login resets the failed attempts counter.
- [x] Submitting valid credentials logs the customer in for a standard session of 2 hours of inactivity.
- [x] Selecting "Remember me" keeps the customer logged in across browser sessions.
- [x] Upon successful login, the customer is redirected to their intended destination, or to the discovery catalog by default.

## Out of Scope

- Complete backend password reset / recovery flow (deferred to a dedicated future feature; only the entry link is integrated in the login view).
- Third-party OAuth integration (handled in feature `004 · Google Customer Auth`).
