# 001 · OTP Generation, Dispatch & Verification — Tasks

- [ ] Create the migration to the table `security_codes` based on `docs/design/entity-relation.md`.
- [ ] Create the enum `ContactType` with its 3 cases (email,sms and whatsapp)
- [ ] Create the model SecurityCode with its casts and factory.
- [ ] Run and validate the migration.
- [ ] Create the notification SendOtpNotification.
- [ ] Implement the logic of email channel.
- [ ] Structure the dispatch to SMS and WhatsApp
- [ ] Create `app/Services/OtpService.php`.
- [ ] Implement the method `generate()` (6-digit code, hash, ttl 5 min, invalidation of previous codes).
- [ ] Implement cooldown rule of one minute before allowing resend.
- [ ] Implement the method `resend()` to resend new codes after one minute.
- [ ] Implement the method `verify()` (hash verification, 5-attempt limits and mark as used).
- [ ] Write Pest tests for OTP generation, expiration, rate limits, and verification.
- [ ] Run Pint to fix code style (`vendor/bin/pint --dirty`).
- [ ] Verify all acceptance criteria from `spec.md`.
- [ ] Move feature to "Done" in `spec/constitution/roadmap.md`.
