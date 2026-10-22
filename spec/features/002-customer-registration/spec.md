# 002 · Customer Registration

**Status:** Done

## What it does

The customer registration feature provides a streamlined, 2-step onboarding experience (Mercado Libre style) for consumers who want to participate in virtual queues and place self-service orders. 

Step 1 collects essential account information (first name, last name, mobile phone number, email address, secure password, and terms acceptance), performs duplicate pre-checks, and initiates phone verification by sending a 6-digit security code via WhatsApp (primary) or SMS.

Step 2 presents the verification challenge where users enter the code, edit their phone number if needed (up to 3 times), and complete account provisioning with automatic login. Email verification is deferred asynchronously to avoid in-person onboarding friction.

## Why

It is required to identify customers across virtual queues, ensure reliable notification delivery for turn callouts via WhatsApp/SMS, and prevent spam or ghost accounts while providing a frictionless, mobile-first registration experience that takes less than one minute.

## Acceptance Criteria

- [x] **Access Control & Session Lifecycle**:
    - [x] Authenticated users attempting to access the registration flow are redirected to the discovery catalog.
    - [x] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [x] In-progress registration data is temporarily retained and expires if not completed within 15 minutes.
    - [x] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [x] **Step 1: Account Information & Pre-check**:
    - [x] First name and last name are required and must each contain between 2 and 60 characters.
    - [x] The entered mobile phone number is validated and formatted into a standard international format, defaulting to Colombia (+57).
    - [x] The entered email address is trimmed, normalized to lowercase, and validated against standard email formatting rules.
    - [x] Passwords must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.
    - [x] Password confirmation is required and must match the chosen password.
    - [x] Customers must accept the Terms of Service and Privacy Policy before proceeding.
    - [x] If the phone number is already registered, registration is halted and the user is prompted to sign in instead.
    - [x] If the email address is already registered, registration is halted and the user is prompted to sign in instead.
    - [x] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [x] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [x] For valid, unregistered details, the system dispatches a 6-digit verification code to the mobile number prioritizing WhatsApp.
    - [x] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [x] **Step 2: Phone Verification Challenge & Account Finalization**:
    - [x] Submitted verification codes are validated against the OTP service, rejecting invalid or expired entries.
    - [x] Exceeding maximum verification attempts invalidates the active code and requires requesting a new one.
    - [x] Users can edit their entered phone number up to 3 times per registration before being required to restart.
    - [x] Requesting a new code requires waiting for the cooldown period to elapse, even if the phone number is changed.
    - [x] Once the cooldown period expires, users can request a new code via WhatsApp or choose SMS delivery.
    - [x] Upon successful verification, the system provisions the customer account with a verified phone status (deferring email verification) and dispatches a welcome email.
    - [x] The customer is automatically authenticated and redirected to their intended destination, or to the main discovery catalog by default with a confirmation notice.
    - [x] Temporary registration progress is cleared upon successful account creation.

## Out of Scope

- Synchronous email OTP verification (deferred to asynchronous post-registration verification).
- Business onboarding screens (handled in feature `003 · Business Registration`).
- Third-party OAuth integration (Google Sign-In).