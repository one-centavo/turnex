# 002 · Customer Registration

**Status:** In progress

## What it does

Provides a streamlined two-step onboarding flow that collects customer profile details, email, and credentials in an initial screen, then verifies the mobile phone number via a security code challenge (prioritizing WhatsApp with SMS fallback) before provisioning the account.

Email verification is decoupled from the immediate registration barrier and deferred to asynchronous post-registration confirmation, ensuring fast access to virtual queues while certifying mobile reachability for pickup notifications. It delegates account creation and data persistence to a centralized `CustomerService`.

## Why

Minimizes onboarding friction for in-person queue and ordering scenarios by requiring only a single, fast mobile verification step (WhatsApp / SMS), while maintaining data integrity, anti-abuse controls, and certified mobile contact.

## Acceptance Criteria

- [ ] **Access Control & Session Lifecycle**:
    - [ ] Authenticated users attempting to access the registration flow are redirected to the discovery catalog.
    - [ ] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [ ] In-progress registration data is temporarily retained and expires if not completed within 15 minutes.
    - [ ] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [ ] **Step 1: Account Information & Pre-check**:
    - [ ] First name and last name are required and must each contain between 2 and 60 characters.
    - [ ] The entered mobile phone number is validated and formatted into a standard international format, defaulting to Colombia (+57).
    - [ ] The entered email address is trimmed, normalized to lowercase, and validated against standard email formatting rules.
    - [ ] Passwords must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.
    - [ ] Password confirmation is required and must match the chosen password.
    - [ ] Customers must accept the Terms of Service and Privacy Policy before proceeding.
    - [ ] If the phone number is already registered, registration is halted and the user is prompted to sign in instead.
    - [ ] If the email address is already registered, registration is halted and the user is prompted to sign in instead.
    - [ ] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [ ] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [ ] For valid, unregistered details, the system dispatches a 6-digit verification code to the mobile number prioritizing WhatsApp.
    - [ ] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [ ] **Step 2: Phone Verification Challenge & Account Finalization**:
    - [ ] Submitted verification codes are validated against the OTP service, rejecting invalid or expired entries.
    - [ ] Exceeding maximum verification attempts invalidates the active code and requires requesting a new one.
    - [ ] Users can edit their entered phone number up to 3 times per registration before being required to restart.
    - [ ] Requesting a new code requires waiting for the cooldown period to elapse, even if the phone number is changed.
    - [ ] Once the cooldown period expires, users can request a new code via WhatsApp or choose SMS delivery.
    - [ ] Upon successful verification, the system provisions the customer account with a verified phone status (deferring email verification) and dispatches a welcome email.
    - [ ] The customer is automatically authenticated and redirected to the main discovery catalog with a confirmation notice.
    - [ ] Temporary registration progress is cleared upon successful account creation.

## Out of Scope

- Synchronous email OTP verification (deferred to asynchronous post-registration verification).
- Business onboarding screens (handled in feature `003 · Business Registration`).
- Third-party OAuth integration (Google Sign-In).
