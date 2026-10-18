# 002 · Customer Registration — Plan

## Approach

Implement a dedicated CustomerService in the service layer to manage customer provisioning and profile lifecycle. Customer records will be persisted in a dedicated `customers` table containing profile details, secure credentials, and contact verification timestamps. Input sanitization is separated into pure normalization functions (`DataNormalizer`) and reusable attribute validation rules (`CustomerRules`), completely avoiding coupled traits or boolean flags. The onboarding process is orchestrated through a two-step Livewire 4 flow that leverages `OtpService` to verify the mobile phone number before creating the account and authenticating the customer.

## Implementation

1. Create migration for the `customers` table — `database/migrations/xxxx_xx_xx_xxxxxx_create_customers_table.php` based on `docs/design/entity-relation.md`.
2. Create model `app/Models/Customer.php` with appropriate casts (including `phone_verified_at` and `email_verified_at`) and factory `database/factories/CustomerFactory.php`.
3. Create validation rules catalog `app/Rules/CustomerRules.php` providing atomic, reusable validation rule sets (`firstName()`, `lastName()`, `email(?int $ignoreId = null)`, `phone(?int $ignoreId = null)`, `password()`, `terms()`) for consistent use across registration, profile editing, and Livewire components without boolean flags.
4. Implement customer registration lifecycle and account provisioning in `app/Services/CustomerService.php` leveraging `DataNormalizer` for sanitizing names, emails, and phone numbers before database persistence, handling password hashing, and recording verification timestamps.
5. Create welcome notification to dispatch asynchronous email confirmation — `app/Notifications/WelcomeCustomerNotification.php`.
6. Implement the 2-step registration Livewire 4 Single-File Component (Step 1: profile data entry validated via `CustomerRules`, IP rate limiting, and WhatsApp OTP dispatch; Step 2: code verification, phone edit limit, unified cooldown, SMS fallback, and auto-login) — `resources/views/pages/auth/customer/⚡register.blade.php` using Flux UI.
7. Register the `/register` route with `guest` middleware and write comprehensive automated feature tests in `tests/Feature/CustomerRegistrationTest.php` covering all acceptance criteria.

## Decisions

- **Two-step streamlined flow over multi-screen wizard** — Collects account profile details up front and isolates the OTP challenge into a single mobile verification step, minimizing drop-off and cognitive friction in fast-paced queue scenarios.
- **Decoupled validation catalog and pure normalizers over coupled request traits** — Separates pure data sanitization (`DataNormalizer`) from validation constraints (`CustomerRules`), eliminating confusing boolean flags (`$isUpdate`, `$isUnique`) while enabling clean reuse across registration forms, profile updates, and Livewire components without framework coupling.
- **Deferred asynchronous email verification over synchronous blocking OTP** — Eliminates the friction of opening external email apps during in-person onboarding, collecting email for future receipts while keeping mobile SMS/WhatsApp as the primary identity anchor.
- **WhatsApp primary with SMS fallback over single-channel dispatch** — Leverages instant delivery (<3s) and broad adoption in Colombia to establish a direct communication channel for order pickup alerts, backed by SMS for users without active data.
- **Separate first and last name fields over a single full-name input** — Eliminates unpredictable user inputs and fragile regex parsing, ensuring accurate personalization on queue display screens (`Carlos P.`) and WhatsApp greetings.
- **Server-side session storage over client-side Livewire properties for verified data** — Retains intermediate registration progress and verified contact timestamps in the encrypted server session (with a 15-minute TTL), preventing client-side tampering or step skipping.
- **Dedicated `CustomerService` over inline Eloquent logic in Livewire components** — Encapsulates customer provisioning, credential hashing, and verification timestamps in a reusable service layer, maintaining clean separation of concerns and future reusability.

## Risks

- **SMS/WhatsApp toll fraud and number rotation abuse** — Automated bots or malicious users could repeatedly modify phone numbers to spam messages and inflate costs; mitigated by enforcing IP rate limiting (maximum 5 requests per minute), a 1-minute global cooldown, and capping phone edits to 3 per session.
- **Client-side step tampering and forged verification** — A user could attempt to manipulate Livewire network payloads to bypass the OTP challenge and create an account; mitigated by storing intermediate registration data and verification timestamps exclusively in the encrypted server-side session.
- **Third-party messaging gateway downtime** — Service outages in WhatsApp/Meta could block customer onboarding; mitigated by capturing delivery exceptions, rolling back unwarranted cooldowns, and providing an immediate SMS fallback channel.
- **Unverified or mistyped email registration** — Deferring email verification could allow typos or unauthorized email entries; mitigated by dispatching an immediate welcome notification to the address and anchoring account identity to the verified mobile phone number.
