# 005 · Business Registration — Plan

## Approach

Implement a dedicated `BusinessService` in the domain layer to encapsulate business provisioning, credential hashing, and verification status lifecycle in the `businesses` table. Input sanitization leverages `DataNormalizer` while validation constraints are organized in a reusable `BusinessRules` catalog without boolean flags. The two-step registration flow is orchestrated via a Livewire 4 Single-File Component using Flux UI, persisting intermediate state in encrypted server sessions, and leveraging `OtpService` with `ContactType::Email` to validate email ownership prior to finalizing account provisioning and authenticating the merchant. Commercial contact phone numbers are deferred to the Business Profile Settings to prevent unverified phone entry during onboarding.

## Implementation

1. Create migration to ensure `phone_number` in `businesses` table is nullable.
2. Update `app/Models/Business.php` to ensure `phone_number` and `phone_verified_at` remain optional/nullable.
3. Update `app/Services/BusinessService.php` to handle business registration without requiring `phone_number`, marking `email_verified_at = now()` and `phone_verified_at = null`.
4. Update `app/Notifications/WelcomeBusinessNotification.php` to confirm email verification and welcome the business owner.
5. Refactor the 2-step registration Livewire 4 Single-File Component using Flux UI — `resources/views/pages/auth/business/⚡register.blade.php`:
   - Step 1: Business name, category, owner names, location, email, password (8-64 chars, no spaces), terms. Rate limit (5 req/min), email uniqueness check, and Email OTP dispatch via `OtpService::generate($email, ContactType::Email)`.
   - Step 2: 6-digit challenge code verification, masked email display, max 3 email edits with business-scoped uniqueness check and 60s cooldown, and email resend.
   - Finalization: Provisioning via `BusinessService`, auto-authentication in `business` guard, session regeneration, and redirect to dashboard in setup mode.
6. Update comprehensive automated feature tests in `tests/Feature/BusinessRegistrationTest.php` and service tests in `tests/Feature/BusinessServiceTest.php` covering all acceptance criteria.

## Decisions

- **Email OTP verification over SMS/WhatsApp for merchant onboarding** — Eliminates telecom costs, prevents dependency on paid third-party SMS/WhatsApp gateways, and avoids blocking international users or reviewers.
- **Omission of phone number from initial business onboarding** — Prevents collecting unverified phone numbers with restrictive `unique` constraints that could squat commercial lines; public contact phone numbers are configured in the Business Profile Settings.
- **Business-scoped email uniqueness over cross-role global blocking** — Evaluates email uniqueness strictly against the `businesses` table, allowing an individual to maintain a `Customer` account for personal queueing and a `Business` account for merchant operations using the same email address.
- **Decoupled validation catalog and pure normalizers over coupled request traits** — Separates pure data sanitization (`DataNormalizer`) from validation rules (`BusinessRules`), eliminating boolean flags while enabling clean reuse across registration forms, profile updates, and Livewire components.
- **Server-side session storage over client-side Livewire properties for intermediate state** — Retains registration progress and verified state in the encrypted server session with a 15-minute TTL, preventing client-side tampering or step skipping.

## Risks

- **Spam or throwaway email registrations** — Malicious users could attempt automated registration with temporary mailboxes; mitigated by enforcing IP rate limiting (maximum 5 requests per minute per IP), a 60-second cooldown per target, and synchronous 6-digit challenge verification.
- **Business name squatting and impersonation** — An unauthorized user could register a recognized commercial name before the legitimate owner; mitigated by keeping `business_name` non-unique to prevent namespace lockout, and provisioning accounts in `PendingVerification` status with no public storefront or queue visibility until legal documents are approved by SuperAdmin in Stage 3 (`US07`/`US08`).
- **Client-side step tampering and forged verification** — A user could attempt to manipulate Livewire network payloads to bypass Step 1 validation or the OTP challenge; mitigated by storing intermediate registration data, edit counters, and verification state exclusively in the encrypted server-side session with a 15-minute TTL.
- **Concurrent requests and double-submission** — Rapid multi-clicking on slow connections could trigger redundant OTP dispatches or duplicate record creation; mitigated by disabling submit buttons during processing (`wire:loading.attr="disabled"`) and atomic database transactions in `BusinessService`.
