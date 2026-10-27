# 002 · Customer Registration — Plan

## Approach

Implement customer provisioning and lifecycle management in `CustomerService`. Customer records are persisted in the `customers` table with verified email credentials (`email_verified_at`), while `phone_number` remains optional/nullable. Data sanitization is separated into pure normalization functions (`DataNormalizer`) and reusable attribute validation rules (`CustomerRules`). The onboarding flow is orchestrated through a two-step Livewire 4 Single-File Component (`⚡register.blade.php`) using Flux UI, validating ownership via `OtpService` with `ContactType::Email` before creating the account and authenticating the customer.

## Implementation

1. Update `app/Services/CustomerService.php` to handle registration without requiring a phone number, setting `email_verified_at = now()`.
2. Ensure validation rules catalog `app/Rules/CustomerRules.php` provides atomic, reusable validation rule sets (`firstName()`, `lastName()`, `email(?int $ignoreId = null)`, `password()`, `terms()`).
3. Refactor the 2-step registration Livewire 4 Single-File Component (`resources/views/pages/auth/customer/⚡register.blade.php`) using Flux UI:
   - Step 1: Profile and credentials input (First Name, Last Name, Email, Password, Password Confirmation, Terms), IP rate limiting (5 req/min), and email OTP dispatch via `OtpService::generate($email, ContactType::Email)`.
   - Step 2: 6-digit challenge code verification, server-side session data store with 15-minute TTL, masked email display (`c***z@domain.com`), max 3 email edits with unified 60s cooldown, and email resend.
   - Finalization: Provisioning via `CustomerService`, auto-authentication in `customer` guard, session regeneration, and step 3 confirmation/redirect.
4. Update comprehensive automated feature tests in `tests/Feature/CustomerRegistrationTest.php` covering all acceptance criteria.

## Decisions

- **Two-step streamlined flow over multi-screen wizard** — Collects account profile details up front and isolates the OTP challenge into an email verification step, minimizing drop-off and cognitive friction.
- **Email OTP verification over SMS/WhatsApp for MVP** — Eliminates third-party telecom costs, Meta/WhatsApp business template verification delays, and international country-code restrictions (+57 lock-in).
- **Omission of phone number from customer registration** — `customers.phone_number` remains nullable in the schema; phone numbers can be linked later when needed for specific SMS alert services.
- **Server-side session storage over client-side Livewire properties** — Retains intermediate registration progress and cooldown state in the encrypted server session (with a 15-minute TTL), preventing client-side tampering or step skipping.
- **Dedicated `CustomerService` over inline Eloquent logic in Livewire components** — Encapsulates customer provisioning, credential hashing, and verification timestamps in a reusable service layer.

## Risks

- **Spam or throwaway email registrations** — Malicious users could attempt automated registration with temporary mailboxes; mitigated by enforcing IP rate limiting (maximum 5 requests per minute per IP), a 60-second cooldown per target, and synchronous 6-digit challenge verification.
- **Client-side step tampering and forged verification** — A user could attempt to manipulate Livewire network payloads to bypass the OTP challenge; mitigated by storing intermediate registration data and verification timestamps exclusively in the encrypted server-side session.
- **Email delivery delays or spam folder routing** — Transactional mail servers could delay OTP delivery; mitigated by providing clear UI hints, a countdown timer, and a 1-click resend button once the cooldown expires.
