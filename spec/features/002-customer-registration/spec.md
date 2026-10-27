# 002 · Customer Registration

**Status:** Done

## What it does

The customer registration feature provides a streamlined, 2-step onboarding experience for consumers who want to participate in virtual queues and place self-service orders.

Step 1 collects essential account information (first name, last name, email address, secure password, and terms acceptance), performs duplicate pre-checks, and initiates email verification by sending a 6-digit security code via Email OTP. Mobile phone number collection is omitted from the initial customer onboarding to keep registration frictionless and universal.

Step 2 presents the verification challenge where users enter the 6-digit security code sent to their email, edit their email address if mistyped (up to 3 times), and complete account provisioning with automatic login and immediate email verification.

## Why

It is required to identify customers across virtual queues, ensure authentic account ownership via verified email, and prevent spam or ghost accounts while providing a frictionless, zero-cost, and instant onboarding experience.

## Acceptance Criteria

- [x] **Access Control & Session Lifecycle**:
    - [x] Authenticated users attempting to access the registration flow are redirected to the discovery catalog.
    - [x] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [x] In-progress registration data is temporarily retained in an encrypted session and expires if not completed within 15 minutes.
    - [x] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [x] **Step 1: Account Information & Pre-check**:
    - [x] First name and last name are required and must each contain between 2 and 60 characters.
    - [x] The entered email address is trimmed, normalized to lowercase, and validated against standard RFC email formatting rules.
    - [x] Passwords must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.
    - [x] Password confirmation is required and must match the chosen password.
    - [x] Customers must accept the Terms of Service and Privacy Policy before proceeding.
    - [x] If the email address is already registered, registration is halted and the user is prompted to sign in instead.
    - [x] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [x] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [x] For valid, unregistered details, the system dispatches a 6-digit verification code to the customer's email address.
    - [x] If email dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [x] **Step 2: Email Verification Challenge & Account Finalization**:
    - [x] Submitted verification codes are validated against the OTP service, rejecting invalid or expired entries.
    - [x] Exceeding maximum verification attempts invalidates the active code and requires requesting a new one.
    - [x] Users can edit their entered email address up to 3 times per registration before being required to restart.
    - [x] Requesting a new code requires waiting for the 60-second cooldown period to elapse, even if the email address is changed.
    - [x] Once the cooldown period expires, users can request a new code to their email.
    - [x] Upon successful verification, the system provisions the customer account with `email_verified_at = now()`, leaving `phone_number` and `phone_verified_at` as null.
    - [x] The customer is automatically authenticated into the `customer` guard, the session ID is regenerated, and the user is redirected to their intended destination or the main catalog with a confirmation notice.
    - [x] Temporary registration progress in session is cleared upon successful account creation.

## Out of Scope

- Mobile phone collection during initial customer registration (deferred to profile settings or optional queue-level SMS alerts).
- WhatsApp and SMS OTP dispatch for customers (reserved for future milestones or business communication).
- Business onboarding screens (handled in feature `003 · Business Registration`).
- Third-party OAuth integration (Google Sign-In is handled in feature `001 · Customer & Business Authentication`).
