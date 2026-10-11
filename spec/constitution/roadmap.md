# Roadmap

Status and delivery sequence of TURNEX features. Provides visibility into "what is done, what is up next, and what is planned". Each entry points to its respective directory under `features/`.

## Done ✅

_No features completed yet. Development starts from scratch._

## Up Next 🔜

1. **001 · OTP Generation, Dispatch & Verification** — Centralized service managing lifecycle, expiration, retry rate limits, and multi-channel delivery (WhatsApp, SMS, and Email) of temporary security codes (US-CORE).

## Backlog / Ideas 💡

2. **002 · Customer Registration** — Progressive onboarding flow for customers certifying mobile phone and email address via OTP, alongside streamlined Google OAuth onboarding (US01, US01B).
3. **003 · Business Registration** — Rapid onboarding for merchants with initial contact data, industry category, and phone verification via OTP in preliminary status (US02).
4. **004 · Customer Login** — Authentication for customers via standard credentials (email/phone + password) or Google OAuth, redirecting to the main discovery catalog (US03).
5. **005 · Business Login** — Dedicated login for business owners and operators, verifying credentials and routing to the business operations dashboard (US03).
6. **006 · SuperAdmin Login** — Secure administrative access for SuperAdmin users (seeded via Seeder/Tinker) to the global governance and platform management portal (US09).

> Each new feature is created as `features/NNN-feature-name/` containing `spec.md`, `plan.md`, and `tasks.md` before writing code.
