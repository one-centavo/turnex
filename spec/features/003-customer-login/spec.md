# 003 · Customer Login

**Status:** In progress

## What it does

The customer login feature provides a secure authentication flow for customers to access their accounts.

Customers enter their identifier (email address or mobile phone number) along with their password. Once credentials are validated, the system authenticates the user and redirects them to the main discovery catalog, or displays an error message if the credentials do not match.

## Why

It is required for customers to log into their accounts and access the catalog and their information.

## Acceptance Criteria

- [ ] Customers who are already logged in cannot access the login page and are redirected to the discovery catalog.
- [ ] The login form requests the customer's identifier (email or mobile phone number) and password.
- [ ] The password field allows toggling between hidden and visible text.
- [ ] The login form includes an optional "Remember me" checkbox.
- [ ] Both identifier and password fields are required; submitting empty values prompts the customer to complete them.
- [ ] The entered email or phone number is automatically cleaned and formatted before checking credentials.
- [ ] If the email, phone number, or password does not match an existing account, the system displays: _"Incorrect credentials. Please try again."_
- [ ] If the account was registered with Google and has no password configured, the system displays: _"This account is linked to Google Sign-In. Please log in using Google."_
- [ ] An unsuccessful login clears the password field but keeps the entered email or phone number.
- [ ] Login attempts are limited to a maximum of 5 attempts per minute; exceeding this limit temporarily blocks further requests for 1 minute.
- [ ] A successful login resets the failed attempts counter.
- [ ] Submitting valid credentials logs the customer in for a standard session of 2 hours of inactivity.
- [ ] Selecting "Remember me" keeps the customer logged in across browser sessions.
- [ ] Upon successful login, the customer is redirected to their intended destination, or to the discovery catalog by default.

## Out of Scope

- Password reset / recovery flow.
- Third-party OAuth integration (handled in feature `004 · Google Customer Auth`).
