# 005 · Business Registration — Plan

## Approach

Implement a dedicated `BusinessService` in the domain layer to encapsulate business provisioning, credential hashing, and verification status lifecycle in the `businesses` table. Input sanitization leverages `DataNormalizer` while validation constraints are organized in a reusable `BusinessRules` catalog without boolean flags. The two-step registration flow is orchestrated via a Livewire 4 Single-File Component using Flux UI, persisting intermediate state in encrypted server sessions, and leveraging `OtpService` to validate the mobile phone number prior to finalizing account provisioning and authenticating the merchant.

## Implementation

1. Create enums `app/Enums/VerificationStatus.php` and `app/Enums/BusinessCategory.php` for strongly typed statuses and supported industry types.
2. Create migration for the `businesses` table — `database/migrations/xxxx_xx_xx_xxxxxx_create_businesses_table.php` based on `docs/design/database/entity-relation-diagram.md`.
3. Create authenticatable model `app/Models/Business.php` with appropriate casts (including enums and timestamps), configure its guard and provider in `config/auth.php`, and create factory `database/factories/BusinessFactory.php`.
4. Create validation rules catalog `app/Rules/BusinessRules.php` providing atomic, reusable validation rule sets (`businessName()`, `category()`, `location()`, `ownerFirstName()`, `ownerLastName()`, `email()`, `phone()`, `password()`, `terms()`) without boolean flags.
5. Implement business registration lifecycle and account provisioning in `app/Services/BusinessService.php` leveraging `DataNormalizer` for sanitization, handling password hashing, and recording verification timestamps.
6. Create welcome notification to dispatch asynchronous email confirmation — `app/Notifications/WelcomeBusinessNotification.php`.
7. Implement the 2-step registration Livewire 4 Single-File Component using Flux UI — `resources/views/pages/auth/business/⚡register.blade.php`.
8. Register the `/business/register` route with `guest` middleware and write comprehensive automated feature tests in `tests/Feature/BusinessRegistrationTest.php` covering all acceptance criteria.

## Decisions

- **Progressive two-step onboarding over upfront legal (KYB) document collection** — Collects essential business information and verifies mobile phone reachability upfront to eliminate friction and minimize drop-off, deferring heavy legal and banking verification (Tax ID/RUT, Representative ID, bank certificate) to Stage 3 (`US07`) after the merchant has explored their dashboard.
- **Business-scoped contact uniqueness over cross-role global blocking** — Evaluates email and phone number uniqueness strictly against the `businesses` table, allowing an individual to maintain a `Customer` account for personal queueing and a `Business` account for merchant operations using the same contact credentials.
- **Decoupled validation catalog and pure normalizers over coupled request traits** — Separates pure data sanitization (`DataNormalizer`) from validation rules (`BusinessRules`), eliminating boolean flags while enabling clean reuse across registration forms, profile updates, and Livewire components.
- **Server-side session storage over client-side Livewire properties for intermediate state** — Retains registration progress and verified phone state in the encrypted server session with a 15-minute TTL, preventing client-side tampering or step skipping.
- **WhatsApp primary with SMS fallback over single-channel dispatch** — Leverages instant delivery (<3s) and broad market adoption in Colombia to establish a reliable communication channel for queue alerts, backed by SMS for users without active data connections.
- **Separate fields for owner names over a single full-name input** — Captures distinct first, middle, last, and second last names into dedicated database columns matching official identity records and the ERD, avoiding fragile regex splitting.

## Risks

- **SMS/WhatsApp toll fraud and number rotation abuse** — Automated bots or malicious users could repeatedly modify phone numbers to spam messages and inflate provider costs; mitigated by enforcing IP rate limiting (maximum 5 requests per minute), a 60-second cooldown period, validating uniqueness before dispatching, and capping phone edits to 3 per session.
- **Business name squatting and impersonation** — An unauthorized user could register a recognized commercial name before the legitimate owner; mitigated by keeping `business_name` non-unique to prevent namespace lockout, and provisioning accounts in `PendingVerification` status with no public storefront or queue visibility until legal documents are approved by SuperAdmin in Stage 3 (`US07`/`US08`).
- **Client-side step tampering and forged verification** — A user could attempt to manipulate Livewire network payloads to bypass Step 1 validation or the OTP challenge; mitigated by storing intermediate registration data, edit counters, and verification state exclusively in the encrypted server-side session with a 15-minute TTL.
- **Concurrent requests and double-submission** — Rapid multi-clicking on slow connections could trigger redundant OTP dispatches or duplicate record creation; mitigated by disabling submit buttons during processing (`wire:loading.attr="disabled"`) and atomic database transactions in `BusinessService`.
