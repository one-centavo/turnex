# Roadmap

Status and delivery sequence of TURNEX features. Provides visibility into "what is done, what is up next, and what is planned". Each entry points to its respective directory under `features/`.

## Done ✅

1. **001 · OTP Generation, Dispatch & Verification** — Centralized service managing lifecycle, expiration, retry rate limits, and multi-channel delivery (WhatsApp, SMS, and Email) of temporary security codes (US-CORE).
2. **002 · Customer Registration** — Streamlined 2-step onboarding flow for customers certifying mobile phone via WhatsApp/SMS OTP, deferred email confirmation, and automatic authentication (US01).
3. **003 · Customer Login** — Authentication for customers via standard credentials (email/phone + password), redirecting to intended destination or discovery catalog (US03).
4. **004 · Google Customer Authentication & Onboarding** — Single-click social registration/login via Google OAuth 2.0 with automatic email verification and account linking (US01B).
5. **005 · Business Registration** — Rapid onboarding for merchants with initial contact data, industry category, and phone verification via OTP in preliminary status (US02).
6. **006 · Business Login** — Dedicated login for business owners and operators, verifying credentials and routing to the business operations dashboard (US03).
7. **007 · Service Categories** — Global grouping taxonomy, persistence, soft deletes, and baseline seeders for classifying business offerings (US10, US11, US13).
8. **008 · Create Business Offerings** — Creation of commercial products and services by merchants, with categorization, image upload, price, and duration controls (US04).
9. **009 · Listing and Filtering Business Offerings** — Real-time catalog listing, category/status filtering, search, sorting, and pagination for merchants (US04B).
10. **010 · Edit Business Offerings** — Update commercial offerings, categories, prices, durations, and active status with reactive image replacement/removal controls.
11. **011 · Archive and Delete Business Offerings** — Lifecycle management for offerings with safe soft-delete archiving, restoration, and guarded permanent deletion (US05C).
12. **012 · Public Business Storefront** — Public mobile-responsive commercial catalog with human-friendly URL slugs (`/b/{slug}`), business identity, reactive category filtering, and real-time search.

13. **013 · Virtual Queue & Digital Ticket** — Customer virtual queue joining, live ticket issuance (`TK-001`), and real-time queue position tracking.
14. **014 · Customer Tickets Hub** — Centralized customer portal for tracking active queue turns and reviewing ticket history.
15. **015 · Explore Businesses Directory** — Public discovery hub to search and filter establishments by commercial category and line occupancy.
16. **016 · Business Live Queue Operations** — Live ticket queue management cockpit for merchants, including two-step calling, in-service status, holds, no-shows, cancellation, and queue pause controls.

## In Progress 🚧

None.

## Up Next 🔜

17. **017 · Public Queue Display Board** — Waiting room TV display board for venues showing currently called tickets and live queue sequence.

## Backlog / Ideas 💡

None.

> Each new feature is created as `features/NNN-feature-name/` containing `spec.md`, `plan.md`, and `tasks.md` before writing code.
