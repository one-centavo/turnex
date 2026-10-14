# 001 · OTP Generation, Dispatch & Verification — Tasks

- [x] Create the migration to the table `security_codes` based on `docs/design/entity-relation.md`.
- [x] Create the enum `ContactType` with its 3 cases (email,sms and whatsapp)
- [x] Create the model SecurityCode with its casts and factory.
- [x] Run and validate the migration.
- [ ] Create the notification SendOtpNotification.
- [ ] Implement the logic of email channel.
- [ ] Structure the dispatch to SMS and WhatsApp
- [ ] Create `app/Services/OtpService.php`.
- [ ] Implement target normalization helper (RFC lowercase email, E.164 phone).
- [ ] Implement the method `generate()` (zero-padded 6-digit code, hash, TTL 5 min, invalidation of previous codes, dispatch transaction).
- [ ] Implement cooldown rule of one minute, returning remaining seconds if active.
- [ ] Implement the method `verify()` (input trimming, expired check precedence, hash verification, 5-attempt limit, atomic mark as used).
- [ ] Write Pest tests for OTP generation, expiration, rate limits, and verification.
- [ ] Run Pint to fix code style (`vendor/bin/pint --dirty`).
- [ ] Verify all acceptance criteria from `spec.md`.
- [ ] Move feature to "Done" in `spec/constitution/roadmap.md`.
