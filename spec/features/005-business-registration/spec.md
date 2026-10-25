# 005 · Business Registration

**Status:** Done

## What it does

The business registration feature provides business owners with a streamlined 2-step onboarding flow to register and configure a new business profile.

Step 1 collects essential business and owner information (business name, business category, owner names with first and last name required and middle/second last name optional, email address, mobile phone number, physical location, and password), validates uniqueness, and initiates phone verification by sending a 6-digit security code via WhatsApp or SMS.

Step 2 presents the verification challenge where the owner enters the code to validate their mobile number. Upon successful verification, the business account is provisioned with a "Pending Verification" status, and the user is redirected to the business dashboard in setup mode.

## Why

Business registration is essential to enable merchants to access the platform, configure their catalog, and set up digital queues so customers can discover and interact with them.

Collecting only essential information upfront minimizes onboarding friction and gives business owners immediate access to their dashboard, while deferring formal business verification to subsequent stages.

## Acceptance Criteria

- [x] **Access Control & Session Lifecycle**:
    - [x] Authenticated business managers or system users attempting to access the registration flow are redirected to their active dashboard.
    - [x] Authenticated customers visiting the business registration flow are permitted to register a new business without affecting their customer session.
    - [x] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [x] In-progress registration data is temporarily retained and expires if not completed within 15 minutes.
    - [x] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [x] **Step 1: Business & Owner Information & Pre-check**:
    - [x] Business name is required and must contain between 3 and 150 characters.
    - [x] Business category is required and must match a supported industry type (e.g., Restaurant, Health & Beauty, Professional Services, Retail, Banking); invalid or manipulated options are rejected.
    - [x] Physical location/address is required and limited to 255 characters.
    - [x] Owner name fields provide separate inputs: owner first name and last name are required (between 2 and 60 characters each); middle name and second last name are optional (up to 60 characters each).
    - [x] The entered mobile phone number is validated and formatted into standard international format, defaulting to Colombia (+57).
    - [x] The entered email address is trimmed, normalized to lowercase, and validated against standard email formatting rules.
    - [x] Passwords must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.
    - [x] Password confirmation is required and must match the chosen password.
    - [x] Business owners must accept the Terms of Service and Privacy Policy before proceeding.
    - [x] All entered business and owner details are automatically cleaned and formatted (**US-CORE-02**).
    - [x] If the phone number is already registered to an existing business, registration is halted and the user is prompted to sign in instead.
    - [x] If the email address is already registered to an existing business, registration is halted and the user is prompted to sign in instead.
    - [x] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [x] Submission buttons are disabled and display a loading state while processing to prevent double submissions.
    - [x] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [x] For valid, unregistered details, the system dispatches a 6-digit verification code to the mobile number prioritizing WhatsApp.
    - [x] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [x] **Step 2: Phone Verification Challenge & Account Provisioning**:
    - [x] Submitted verification codes are validated against the OTP service, rejecting invalid or expired entries.
    - [x] Exceeding maximum verification attempts (5 attempts) invalidates the active code and requires requesting a new one.
    - [x] Users can edit their entered phone number up to 3 times per registration before being required to restart.
    - [x] Submitting a new phone number during editing checks for uniqueness against existing businesses; if duplicate, the system rejects it without consuming an edit attempt.
    - [x] Submitting an unchanged phone number during editing does not decrement the remaining edit attempts.
    - [x] Requesting a new code requires waiting for the cooldown period (60 seconds) to elapse, even if the phone number is changed.
    - [x] Once the cooldown period expires, users can request a new code via WhatsApp or choose SMS delivery.
    - [x] Verification submission buttons are disabled with a loading indicator while the OTP is being evaluated.
    - [x] Upon successful verification, the system transitions to a success confirmation state and provisions the business account in "Pending Verification" status with default person type as "Persona Natural" (PersonType::Natural), a verified phone timestamp, and dispatches a welcome email (deferring email verification).
    - [x] The business owner is automatically authenticated and redirected to the business dashboard in setup mode.
    - [x] Temporary registration progress is cleared upon successful account creation.

## Out of Scope

- Business profile customization and branding assets upload (logo, cover photo, description) — deferred to Stage 2 (`US06`).
- Legal documents (Tax ID / RUT, Representative ID, Operating license) and banking certificate submission (KYB) — deferred to Stage 3 (`US07`).
- Administrative manual business review and approval workflow — covered in `US08`.
- Multi-branch or multi-location management for a single business entity.
