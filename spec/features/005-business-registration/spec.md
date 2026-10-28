# 005 · Business Registration

**Status:** In Progress

## What it does

The business registration feature provides business owners with a streamlined 2-step onboarding flow to register and configure a new business profile.

Step 1 collects essential business and owner information (business name, business category, owner names with first and last name required and middle/second last name optional, physical location, corporate/personal email address, secure password, and terms acceptance), validates uniqueness on email, and initiates email verification by sending a 6-digit security code via Email OTP. Mobile phone number collection is omitted from initial registration to eliminate unverified data entry and third-party SMS/WhatsApp gateway friction, deferring public contact phone configuration to the Business Profile Settings in the dashboard.

Step 2 presents the verification challenge where the owner enters the 6-digit code sent to their email, can edit their email address if mistyped (up to 3 times), and completes account provisioning. Upon successful verification, the business account is provisioned with a "Pending Verification" status and `email_verified_at = now()`, and the user is automatically authenticated and redirected to the business dashboard in setup mode.

## Why

Business registration is essential to enable merchants to access the platform, configure their catalog, and set up digital queues so customers can discover and interact with them.

Verifying authentic ownership via Email OTP upfront eliminates telecom costs, removes international registration barriers, and prevents unverified phone squatting while providing an instant, frictionless merchant onboarding experience.

## Acceptance Criteria

- [ ] **Access Control & Session Lifecycle**:
    - [ ] Authenticated business managers or system users attempting to access the registration flow are redirected to their active dashboard.
    - [ ] Authenticated customers visiting the business registration flow are permitted to register a new business without affecting their customer session.
    - [ ] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [ ] In-progress registration data is temporarily retained in an encrypted session and expires if not completed within 15 minutes.
    - [ ] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [ ] **Step 1: Business & Owner Information & Pre-check**:
    - [ ] Business name is required and must contain between 3 and 150 characters.
    - [ ] Business category is required and must match a supported industry type (e.g., Restaurant, Health & Beauty, Professional Services, Retail, Banking); invalid or manipulated options are rejected.
    - [ ] Physical location/address is required and limited to 255 characters.
    - [ ] Owner name fields provide separate inputs: owner first name and last name are required (between 2 and 60 characters each); middle name and second last name are optional (up to 60 characters each).
    - [ ] The entered email address is trimmed, normalized to lowercase, and validated against standard email formatting rules.
    - [ ] Passwords must be between 8 and 64 characters long, cannot contain spaces, and must include an uppercase letter, a lowercase letter, a number, and a special character.
    - [ ] Password confirmation is required and must match the chosen password.
    - [ ] Business owners must accept the Terms of Service and Privacy Policy before proceeding.
    - [ ] All entered business and owner details are automatically cleaned and formatted (**US-CORE-02**).
    - [ ] If the email address is already registered to an existing business, registration is halted and the user is prompted to sign in instead.
    - [ ] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [ ] Submission buttons are disabled and display a loading state while processing to prevent double submissions.
    - [ ] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [ ] For valid, unregistered details, the system dispatches a 6-digit verification code to the owner's email address.
    - [ ] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [ ] **Step 2: Email Verification Challenge & Account Provisioning**:
    - [ ] Submitted verification codes are validated against the OTP service, rejecting invalid or expired entries.
    - [ ] Exceeding maximum verification attempts (5 attempts) invalidates the active code and requires requesting a new one.
    - [ ] Users can edit their entered email address up to 3 times per registration before being required to restart.
    - [ ] Submitting a new email address during editing checks for uniqueness against existing businesses; if duplicate, the system rejects it without consuming an edit attempt.
    - [ ] Submitting an unchanged email address during editing does not decrement the remaining edit attempts.
    - [ ] Requesting a new code requires waiting for the cooldown period (60 seconds) to elapse, even if the email address is changed.
    - [ ] Once the cooldown period expires, users can request a new code to their email.
    - [ ] Verification submission buttons are disabled with a loading indicator while the OTP is being evaluated.
    - [ ] Upon successful verification, the system transitions to a success confirmation state and provisions the business account in "Pending Verification" status with default person type as "Persona Natural" (`PersonType::Natural`), verified email timestamp (`email_verified_at = now()`), leaving `phone_number` and `phone_verified_at` as null, and dispatches a welcome email.
    - [ ] The business owner is automatically authenticated into the `business` guard and redirected to the business dashboard in setup mode.
    - [ ] Temporary registration progress is cleared upon successful account creation.

## Out of Scope

- Mobile phone collection during initial registration (deferred to Business Profile Settings in the dashboard).
- WhatsApp/SMS OTP dispatch for business onboarding.
- Business profile customization and branding assets upload (logo, cover photo, description) — deferred to Stage 2 (`US06`).
- Legal documents (Tax ID / RUT, Representative ID, Operating license) and banking certificate submission (KYB) — deferred to Stage 3 (`US07`).
- Administrative manual business review and approval workflow — covered in `US08`.
- Multi-branch or multi-location management for a single business entity.
