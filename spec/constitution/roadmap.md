# Roadmap

Status and delivery sequence of TURNEX features. Provides visibility into "what is done, what is up next, and what is planned". Each entry points to its respective directory under `features/`.

## Done ✅

1. **001 · OTP Generation, Dispatch & Verification** — Centralized service managing lifecycle, expiration, retry rate limits, and multi-channel delivery (WhatsApp, SMS, and Email) of temporary security codes (US-CORE).
2. **002 · Customer Registration** — Streamlined 2-step onboarding flow for customers certifying mobile phone via WhatsApp/SMS OTP, deferred email confirmation, and automatic authentication (US01).
3. **003 · Customer Login** — Authentication for customers via standard credentials (email/phone + password), redirecting to intended destination or discovery catalog (US03).
4. **004 · Google Customer Authentication & Onboarding** — Single-click social registration/login via Google OAuth 2.0 with automatic email verification and account linking (US01B).
5. **005 · Business Registration** — Rapid onboarding for merchants with initial contact data, industry category, and phone verification via OTP in preliminary status (US02).

## In Progress 🚧

6. **006 · Business Login** — Dedicated login for business owners and operators, verifying credentials and routing to the business operations dashboard (US03).

## Up Next 🔜

None.

## Backlog / Ideas 💡

7. **007 · SuperAdmin Login** — Secure administrative access for SuperAdmin users (seeded via Seeder/Tinker) to the global governance and platform management portal (US09).

> Each new feature is created as `features/NNN-feature-name/` containing `spec.md`, `plan.md`, and `tasks.md` before writing code.
