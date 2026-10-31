# 001 · OTP Generation, Dispatch & Verification — Plan

## Approach

Implement a dedicated OtpService in the service layer to generate, send, verify, rate-limit and resend OTPs. The codes will be stored in an encrypted format in a dedicated `security_codes` table, tracking expiration and failed attempts, while dispatching codes via Email, SMS, and WhatsApp.

## Implementation

1. Create migration for the `security_codes` table — `database/migrations/xxxx_xx_xx_xxxxxx_create_security_codes_table.php`.
2. Define `ContactType` backed enum with Email, SMS, and WhatsApp cases — `app/Enums/ContactType.php`.
3. Create model `app/Models/SecurityCode.php` with casts and factory `database/factories/SecurityCodeFactory.php`.
4. Create notification class to dispatch OTP codes via delivery channels — `app/Notifications/SendOtpCodeNotification.php`.
5. Implement core OTP lifecycle service (normalization, zero-padded generation, cooldown tracking, attempts counter, and verification) — `app/Services/OtpService.php`.
6. Write automated feature tests covering generation, expiration, attempt threshold, and verification flow — `tests/Feature/OtpServiceTest.php`.

## Decisions

- **Dedicated database table over cache storage** — Storing security codes in MySQL provides persistent auditability, accurate attempt tracking, and invalidation history; volatile cache was discarded due to accidental eviction risks and lack of audit trail.
- **VARCHAR column with PHP Backed Enum over native MySQL ENUM** — Enforces strict typing within Laravel and complies with project guidelines while keeping schema migrations flexible for future delivery channels; native MySQL ENUM was discarded to prevent blocking ALTER TABLE operations in production.
- **Service layer encapsulation over controller/component logic** — Centralizes the OTP lifecycle in `OtpService` for reuse across Customer Registration (002), Business Registration (003), and Auth; embedding logic directly into Livewire components was discarded to avoid duplication.
- **Hashed code persistence over plaintext storage** — Storing OTP codes as secure hashes protects credentials against database exposure or compromised logs; plaintext storage was discarded for security best practices.

## Risks

- **Brute force guessing of 6-digit codes** — A malicious script could attempt rapid combinations; mitigated by enforcing a strict maximum threshold of 5 failed attempts before immediately invalidating the code.
- **SMS/WhatsApp toll fraud and messaging abuse** — Automated bots could spam OTP requests and inflate messaging costs; mitigated by enforcing a 1-minute cooldown per target and rate limiting.
- **Replay attacks or duplicate code reuse** — An intercepted or previously validated code could be submitted again; mitigated by atomic single-use invalidation (`used_at` timestamp).
- **Messaging delivery failure locking out users** — If the external notification transport fails after code persistence, the user would be penalized with an unwarranted cooldown; mitigated by wrapping generation and dispatch in a database transaction with rollback on failure.
