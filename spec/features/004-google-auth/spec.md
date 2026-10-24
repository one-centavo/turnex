# 004 · Google Auth

**Status:** In progress

## What it does

Allows customers to authenticate with Google on the login and registration views with a single click on the Google button.

In the login view, existing customers can sign in using their Google account. In the registration view, new customers can create an account using their Google profile. Once authenticated, customers are automatically logged in and redirected to the dashboard.

## Why

This feature reduces friction for customers when entering their credentials and personal information. Developing this feature now is necessary because it allows customers to log in and sign up easily.

## Acceptance Criteria

- [ ] Customers who are already logged in cannot trigger the Google authentication flow and are redirected to their active dashboard or discovery catalog.
- [ ] A visible **"Continue with Google"** button is displayed on both the customer login and registration views.
- [ ] Clicking the button redirects the customer to Google's sign-in screen to authorize their account.
- [ ] Canceling or failing the Google sign-in flow (including expired session state or connection errors) returns the customer to their previous view with the message: _"No se pudo completar la autenticación con Google. Por favor, intenta de nuevo."_
- [ ] **Existing Account Access**:
  - [ ] If an account with the Google email already exists, the customer is logged in immediately and the account is linked for future Google logins.
  - [ ] An existing customer who registered with a password and links their Google account can subsequently sign in using either Google or their password.
- [ ] **New Account Registration**:
  - [ ] If no account exists with that email, a new customer account is created using their Google profile details without requiring a password.
  - [ ] The customer's email is considered verified automatically without requiring manual email confirmation.
  - [ ] If the Google profile does not provide an avatar photo or last name, account creation still succeeds without blocking the customer (avatar is optional, and missing last name defaults safely).
- [ ] **Destination & Navigation**:
  - [ ] Upon successful authentication, the customer is redirected to their originally intended destination, or to the discovery catalog by default.
- [ ] If a customer registered exclusively via Google attempts standard credential-based login, the system displays: _"Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google."_

## Out of Scope

- Mobile phone number collection and OTP verification for Google-authenticated users (handled as a separate progressive profile step).
- Other third-party OAuth providers (e.g., Apple, Facebook).
