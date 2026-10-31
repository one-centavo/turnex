# 004 · Google Auth — Tasks

- [x] Install Laravel Socialite.
- [x] Configure Google OAuth credentials in `config/services.php`.
- [x] Document Google environment variables in .env.example.
- [x] Extend `app/Services/CustomerAuthService.php` to resolve profile data, link existing accounts, and provisions passwordless customer profiles.
- [x] Create `app/Http/Controllers/Auth/CustomerGoogleAuthController.php` to handle redirect and callback responses with error handling.
- [x] Register Google OAuth redirect and callback routes in `routes/web.php`.
- [x] Add "Continue with Google" button to `resources/views/pages/auth/customer/⚡login.blade.php`
- [x] Add "Continue with Google" button to `resources/views/pages/auth/customer/⚡register.blade.php`.
- [x] Write comprehensive automated Pest tests in `tests/Feature/CustomerGoogleAuthTest.php`.
- [x] Run pint and Larastan to verify code style and static analysis.
- [x] Validate against acceptance criteria in `spec.md` and update feature status.
