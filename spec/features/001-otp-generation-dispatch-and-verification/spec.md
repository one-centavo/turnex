# 001 · OTP Generation, Dispatch & Verification

**Status:** Done

## What it does

The OTP service provides a centralized backend solution to generate and send a code to the user, verify the code entered and resend a new code.

It handles security rules like a 5-minute expiration window and a maximum of five failed attempts, and delivers codes via Whatsapp, SMS and email.

## Why

It is required by customer and business registration flows to verify the contact information entered by users.

## Acceptance Criteria

- [x] The system generates a random 6-digit numeric security code formatted as an exact zero-padded string (`000000` to `999999`).
- [x] Contact targets are normalized (RFC lowercase for email, E.164 for phone) prior to code generation and lookup.
- [x] Each security code automatically expires 5 minutes after creation or immediately once successfully used.
- [x] The system enforces a 1-minute cooldown period before allowing a new code request for the same contact target, reporting the remaining cooldown seconds when rejected.
- [x] Requesting a new code immediately invalidates any previously active code for that specific contact target.
- [x] If message delivery fails, the transaction is rolled back so the user is not locked into an unwarranted cooldown.
- [x] Input codes submitted for verification are trimmed of whitespace before attempt evaluation.
- [x] A security code permits a maximum of 5 incorrect verification attempts; upon the 5th failed attempt, the code is immediately invalidated.
- [x] Expired codes are rejected immediately without incrementing the failed attempt counter.
- [x] A code can only be validated once; subsequent verification attempts using the same code are rejected.
- [x] The service dispatches the code via the requested delivery channel (Email, SMS, or WhatsApp).

## Out of Scope

- User-facing registration wizards or UI forms (handled in feature `002 · Customer Registration`).
- Business onboarding screens (handled in feature `003 · Business Registration`).
- Third-party OAuth integration (Google Sign-In).
