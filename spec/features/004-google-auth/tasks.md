# 004 · Google Auth — Tasks

- [ ] Install Laravel Socialite.
- [ ] Configure Google OAuth credentials in `config/services.php`.
- [ ] Document Google environment variables in .env.example.
- [ ] Extend `app/Services/CustomerAuthService.php` to resolve profile data, link existing accounts, and provisions passwordless customer profiles.
- [ ] Create `app/Http/Controllers/Auth/CustomerGoogleAuthController.php` to handle redirect and callback responses with error handling.
- [ ] Register Google OAuth redirect and callback routes in `routes/web.php`.
- [ ] Add "Continue with Google" button to `resources/views/pages/auth/customer/⚡login.blade.php`
- [ ] Add "Continue with Google" button to `resources/views/pages/auth/customer/⚡register.blade.php`.
- [ ] Write comprehensive automated Pest tests in `tests/Feature/CustomerGoogleAuthTest.php`.
- [ ] Run pint and Larastan to verify code style and static analysis.
- [ ] Validate against acceptance criteria in `spec.md` and update feature status.
