# TURNEX — Class Dictionary

This document describes every class that appears in the [class diagram](class-diagram.md): domain entities, backed enumerations, application services, and the presentation layer that handles incoming requests.

Related documents:

- [Class diagram](class-diagram.md) — structure and relationships.
- [Data dictionary](../database/data-dictionary.md) — persisted columns and constraints.
- [Entity-relationship diagram](../database/entity-relationship-diagram.md) — database model.

## Conventions

- Types follow PHP 8.4 syntax. `?T` means nullable, `T[]` means a list, and `Collection<T>` means a Laravel collection.
- `static` marks class-level operations; the rest are instance operations.
- "Source" paths are relative to the project root.
- Entities document persisted attributes and the public API. Framework traits (`HasFactory`, `Notifiable`, `SoftDeletes`) are noted when they change behaviour.
- Classes from the in-progress virtual queue feature (`QueueTicket`, `QueueTicketItem`, `QueueService`, `QueueTicketStatus`) are outside the current class diagram and are therefore not described here.

## Table of Contents

1. [Class Index](#1-class-index)
2. [Domain Entities](#2-domain-entities)
   1. [Business](#21-business)
   2. [Category](#22-category)
   3. [Customer](#23-customer)
   4. [Offering](#24-offering)
   5. [SecurityCode](#25-securitycode)
   6. [User](#26-user)
3. [Enumerations](#3-enumerations)
   1. [BusinessCategory](#31-businesscategory)
   2. [ContactType](#32-contacttype)
   3. [PersonType](#33-persontype)
   4. [VerificationStatus](#34-verificationstatus)
4. [Services](#4-services)
   1. [BusinessAuthService](#41-businessauthservice)
   2. [BusinessService](#42-businessservice)
   3. [CustomerAuthService](#43-customerauthservice)
   4. [CustomerService](#44-customerservice)
   5. [OfferingService](#45-offeringservice)
   6. [OtpService](#46-otpservice)
   7. [StorefrontService](#47-storefrontservice)
5. [Presentation Layer](#5-presentation-layer)
   1. [CustomerGoogleAuthController](#51-customergoogleauthcontroller)
   2. [CustomerLogin](#52-customerlogin)
   3. [CustomerRegister](#53-customerregister)
   4. [BusinessLogin](#54-businesslogin)
   5. [BusinessRegister](#55-businessregister)
   6. [StorefrontShow](#56-storefrontshow)
   7. [OfferingIndex](#57-offeringindex)
   8. [OfferingCreate](#58-offeringcreate)
   9. [OfferingEdit](#59-offeringedit)
   10. [SettingsProfile](#510-settingsprofile)
   11. [SettingsSecurity](#511-settingssecurity)
   12. [SettingsDeleteUser](#512-settingsdeleteuser)

---

## 1. Class Index

| Class | Stereotype | Source | Responsibility |
| :--- | :--- | :--- | :--- |
| `Business` | Entity | `app/Models/Business.php` | Merchant account that owns offerings and holds onboarding and verification data. |
| `Category` | Entity | `app/Models/Category.php` | Shared taxonomy that classifies offerings. |
| `Customer` | Entity | `app/Models/Customer.php` | End-user account that joins queues and places self-service orders. |
| `Offering` | Entity | `app/Models/Offering.php` | Commercial product or service published by a business. |
| `SecurityCode` | Entity | `app/Models/SecurityCode.php` | One-time verification code issued for email or phone channels. |
| `User` | Entity | `app/Models/User.php` | Administrative account that reviews businesses. |
| `BusinessCategory` | Enumeration | `app/Enums/BusinessCategory.php` | Industry domain of a business. |
| `ContactType` | Enumeration | `app/Enums/ContactType.php` | Delivery channel of a one-time code. |
| `PersonType` | Enumeration | `app/Enums/PersonType.php` | Legal structure of a business. |
| `VerificationStatus` | Enumeration | `app/Enums/VerificationStatus.php` | Onboarding review state of a business. |
| `BusinessAuthService` | Service | `app/Services/BusinessAuthService.php` | Credential authentication and throttling for businesses. |
| `BusinessService` | Service | `app/Services/BusinessService.php` | Business registration and contact uniqueness checks. |
| `CustomerAuthService` | Service | `app/Services/CustomerAuthService.php` | Credential and Google authentication for customers. |
| `CustomerService` | Service | `app/Services/CustomerService.php` | Customer registration and contact uniqueness checks. |
| `OfferingService` | Service | `app/Services/OfferingService.php` | Offering lifecycle, image storage, filtering, and archival. |
| `OtpService` | Service | `app/Services/OtpService.php` | One-time code generation, delivery, cooldown, and verification. |
| `StorefrontService` | Service | `app/Services/StorefrontService.php` | Public catalog resolution, visibility rules, and filtering. |
| `CustomerGoogleAuthController` | Controller | `app/Http/Controllers/Auth/CustomerGoogleAuthController.php` | Google OAuth redirect and callback endpoints. |
| `CustomerLogin` | Component | `resources/views/pages/auth/customer/⚡login.blade.php` | Customer credential login page. |
| `CustomerRegister` | Component | `resources/views/pages/auth/customer/⚡register.blade.php` | Two-step customer registration with email OTP verification. |
| `BusinessLogin` | Component | `resources/views/pages/auth/business/⚡login.blade.php` | Business credential login page. |
| `BusinessRegister` | Component | `resources/views/pages/auth/business/⚡register.blade.php` | Two-step business registration with email OTP verification. |
| `StorefrontShow` | Component | `resources/views/pages/storefront/⚡show.blade.php` | Public catalog page for a business slug. |
| `OfferingIndex` | Component | `resources/views/pages/business/offerings/⚡index.blade.php` | Offering listing with filters, sorting, and archive actions. |
| `OfferingCreate` | Component | `resources/views/pages/business/offerings/⚡create.blade.php` | Offering creation form. |
| `OfferingEdit` | Component | `resources/views/pages/business/offerings/⚡edit.blade.php` | Offering edition form, including image replacement. |
| `SettingsProfile` | Component | `resources/views/pages/settings/⚡profile.blade.php` | Profile information and email verification settings. |
| `SettingsSecurity` | Component | `resources/views/pages/settings/⚡security.blade.php` | Password and two-factor authentication settings. |
| `SettingsDeleteUser` | Component | `resources/views/pages/settings/⚡delete-user-modal.blade.php` | Account deletion confirmation modal. |

---

## 2. Domain Entities

### 2.1 Business

**Stereotype:** Entity — Eloquent model · **Source:** `app/Models/Business.php` · **Namespace:** `App\Models`

**Extends:** `Illuminate\Foundation\Auth\User` (`Authenticatable`) · **Traits:** `HasFactory`, `Notifiable`

Represents a merchant account. It authenticates through the `business` guard and stores both the commercial profile and the onboarding review state. The `booted()` hook generates a unique slug before insertion when none is provided.

#### Attributes

| Attribute | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Primary key. |
| `reviewed_by` | `?int` | Foreign key to the `User` who reviewed the application. |
| `person_type` | `PersonType` | Legal structure of the merchant. |
| `tax_id` | `?string` | National tax identification number. |
| `dv` | `?string` | Tax verification digit. |
| `business_name` | `string` | Trade name or legal entity name. |
| `slug` | `string` | Unique URL-friendly public identifier. |
| `business_category` | `BusinessCategory` | Industry domain. |
| `owner_first_name` | `string` | Owner or legal representative's first name. |
| `owner_middle_name` | `?string` | Owner's middle name. |
| `owner_last_name` | `string` | Owner's first surname. |
| `owner_second_last_name` | `?string` | Owner's second surname. |
| `email` | `string` | Login and contact email address. |
| `phone_number` | `?string` | Contact phone number in E.164 format. |
| `location` | `string` | Physical address or operating location. |
| `logo_path` | `?string` | Storage path of the uploaded logo. |
| `cover_photo_path` | `?string` | Storage path of the uploaded cover photo. |
| `business_description` | `?string` | Public description of the business. |
| `verification_status` | `VerificationStatus` | Current onboarding review state. |
| `rejection_reason` | `?string` | Reviewer feedback when the application is rejected. |
| `email_verified_at` | `?CarbonInterface` | Email verification timestamp. |
| `phone_verified_at` | `?CarbonInterface` | Phone verification timestamp. |
| `password` | `string` | Hashed password. |
| `remember_token` | `?string` | Remember-me token. |
| `reviewed_at` | `?CarbonInterface` | Timestamp of the review decision. |
| `created_at` | `?Carbon` | Creation timestamp. |
| `updated_at` | `?Carbon` | Last update timestamp. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `static generateUniqueSlug(string $name, ?int $excludeId = null)` | `string` | Builds a slug from the name and appends a numeric suffix until it is unique, optionally ignoring one record. |
| `reviewer()` | `BelongsTo` | System user that reviewed the business. |
| `offerings()` | `HasMany` | Offerings owned by the business. |
| `ownerFullName()` | `string` | Concatenates the non-empty owner name parts. |
| `hasVerifiedPhone()` | `bool` | Indicates whether `phone_verified_at` is set. |
| `hasVerifiedEmail()` | `bool` | Indicates whether `email_verified_at` is set. |
| `isPendingVerification()` | `bool` | Indicates `VerificationStatus::PendingVerification`. |
| `isUnderReview()` | `bool` | Indicates `VerificationStatus::UnderReview`. |
| `isApproved()` | `bool` | Indicates `VerificationStatus::Approved`. |
| `isActionRequired()` | `bool` | Indicates `VerificationStatus::ActionRequired`. |
| `isRejected()` | `bool` | Indicates `VerificationStatus::Rejected`. |
| `isNaturalPerson()` | `bool` | Indicates `PersonType::Natural`. |
| `isLegalEntity()` | `bool` | Indicates `PersonType::LegalEntity`. |
| `formattedTaxId()` | `?string` | Returns `tax_id-dv`, `tax_id`, or `null` when no tax id exists. |
| `initials()` | `string` | Two-letter initials derived from `business_name`. |
| `protected casts()` | `array` | Casts enums, verification timestamps, and hashes the password. |
| `protected static booted()` | `void` | Registers the `creating` hook that fills the slug. |

#### Relationships

| Relationship | Kind | Target | Description |
| :--- | :--- | :--- | :--- |
| `reviewer` | Association | `User` | Reviewer assigned when the application is evaluated. |
| `offerings` | Composition | `Offering` | Offerings owned by the business; they do not outlive it. |

---

### 2.2 Category

**Stereotype:** Entity — Eloquent model · **Source:** `app/Models/Category.php` · **Namespace:** `App\Models`

**Extends:** `Illuminate\Database\Eloquent\Model` · **Traits:** `HasFactory`, `SoftDeletes`

Shared taxonomy used to group offerings across businesses. Categories are soft deleted so historical offerings keep a valid reference. The default value of `is_active` is `true`.

#### Attributes

| Attribute | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Primary key. |
| `name` | `string` | Unique human-readable name. |
| `description` | `?string` | Short explanation of the category scope. |
| `is_active` | `bool` | Visibility flag; defaults to `true`. |
| `deleted_at` | `?Carbon` | Soft delete timestamp. |
| `created_at` | `?Carbon` | Creation timestamp. |
| `updated_at` | `?Carbon` | Last update timestamp. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `scopeActive(Builder $query)` | `void` | Restricts a query to active categories. |
| `offerings()` | `HasMany` | Offerings classified under the category. |
| `protected casts()` | `array` | Casts `is_active` to boolean. |

#### Relationships

| Relationship | Kind | Target | Description |
| :--- | :--- | :--- | :--- |
| `offerings` | Association | `Offering` | Offerings classified by the category. |

---

### 2.3 Customer

**Stereotype:** Entity — Eloquent model · **Source:** `app/Models/Customer.php` · **Namespace:** `App\Models`

**Extends:** `Illuminate\Foundation\Auth\User` (`Authenticatable`) · **Traits:** `HasFactory`, `Notifiable`

End-user account. It authenticates through the `customer` guard and supports both password and Google-only accounts; `password` is `null` when the account was provisioned through Socialite.

#### Attributes

| Attribute | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Primary key. |
| `first_name` | `string` | First name. |
| `last_name` | `string` | Last name. |
| `email` | `string` | Unique login and contact email. |
| `phone_number` | `?string` | Unique phone number in E.164 format. |
| `password` | `?string` | Hashed password; `null` for Google-only accounts. |
| `google_id` | `?string` | Google OAuth subject identifier. |
| `avatar_url` | `?string` | Profile picture URL or storage path. |
| `email_verified_at` | `?CarbonInterface` | Email verification timestamp. |
| `phone_verified_at` | `?CarbonInterface` | Phone verification timestamp. |
| `remember_token` | `?string` | Remember-me token. |
| `created_at` | `?Carbon` | Creation timestamp. |
| `updated_at` | `?Carbon` | Last update timestamp. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `fullName()` | `string` | Concatenates first and last name. |
| `getNameAttribute()` | `string` | Accessor that exposes `fullName()` as the `name` attribute. |
| `hasVerifiedPhone()` | `bool` | Indicates whether `phone_verified_at` is set. |
| `hasVerifiedEmail()` | `bool` | Indicates whether `email_verified_at` is set. |
| `initials()` | `string` | Two-letter initials derived from the full name. |
| `protected casts()` | `array` | Casts verification timestamps and hashes the password. |

---

### 2.4 Offering

**Stereotype:** Entity — Eloquent model · **Source:** `app/Models/Offering.php` · **Namespace:** `App\Models`

**Extends:** `Illuminate\Database\Eloquent\Model` · **Traits:** `HasFactory`, `SoftDeletes`

Commercial product or service published by a business. The model is tenant-scoped through `business_id`, classified through `category_id`, and soft deleted when archived. Defaults are `price = 0.00` and `is_active = true`.

#### Attributes

| Attribute | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Primary key. |
| `business_id` | `int` | Foreign key to the owning business. |
| `category_id` | `int` | Foreign key to the classifying category. |
| `name` | `string` | Offering name. |
| `reference_image` | `?string` | Storage path of the reference image. |
| `description` | `?string` | Public description. |
| `price` | `string` | Price with two decimals (`decimal:2` cast). |
| `estimated_duration_minutes` | `?int` | Estimated service duration. |
| `is_active` | `bool` | Availability flag; defaults to `true`. |
| `deleted_at` | `?Carbon` | Soft delete timestamp. |
| `created_at` | `?Carbon` | Creation timestamp. |
| `updated_at` | `?Carbon` | Last update timestamp. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `business()` | `BelongsTo` | Owning business. |
| `category()` | `BelongsTo` | Classifying category; includes soft-deleted categories. |
| `scopeActive(Builder $query)` | `void` | Restricts a query to active offerings. |
| `protected casts()` | `array` | Casts price, duration, and availability. |

#### Relationships

| Relationship | Kind | Target | Description |
| :--- | :--- | :--- | :--- |
| `business` | Association | `Business` | Tenant that owns the offering. |
| `category` | Association | `Category` | Category that classifies the offering. |

---

### 2.5 SecurityCode

**Stereotype:** Entity — Eloquent model · **Source:** `app/Models/SecurityCode.php` · **Namespace:** `App\Models`

**Extends:** `Illuminate\Database\Eloquent\Model` · **Traits:** `HasFactory`

One-time code issued by `OtpService`. The plain code is never stored: the `code` attribute holds a hash and is hidden from serialization. Each record carries its own expiry, usage timestamp, and failed-attempt counter.

#### Attributes

| Attribute | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Primary key. |
| `contact_value` | `string` | Normalized email or phone number that receives the code. |
| `contact_type` | `ContactType` | Delivery channel. |
| `code` | `string` | Hashed verification code. |
| `attempts_count` | `int` | Failed verification attempts. |
| `expires_at` | `Carbon` | Expiry timestamp. |
| `used_at` | `?Carbon` | Successful usage timestamp. |
| `created_at` | `?Carbon` | Creation timestamp. |
| `updated_at` | `?Carbon` | Last update timestamp. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `isExpired()` | `bool` | Indicates whether `expires_at` is in the past. |
| `isUsed()` | `bool` | Indicates whether `used_at` is set. |
| `hasExceededAttempts()` | `bool` | Indicates whether the counter reached 5 attempts. |
| `invalidate()` | `bool` | Expires the code immediately. |
| `markAsUsed()` | `bool` | Stamps `used_at` after a successful verification. |
| `protected casts()` | `array` | Casts the channel, counter, and timestamps. |

---

### 2.6 User

**Stereotype:** Entity — Eloquent model · **Source:** `app/Models/User.php` · **Namespace:** `App\Models`

**Extends:** `Illuminate\Foundation\Auth\User` (`Authenticatable`) · **Implements:** `MustVerifyEmail` · **Traits:** `HasFactory`, `Notifiable`

Administrative account used to review business applications and audit platform activity. It authenticates through the `web` guard.

#### Attributes

| Attribute | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Primary key. |
| `name` | `string` | Display name. |
| `email` | `string` | Unique administrative email. |
| `email_verified_at` | `?Carbon` | Email verification timestamp. |
| `password` | `string` | Hashed password. |
| `two_factor_secret` | `?string` | Encrypted TOTP secret declared for Fortify; not present in the current `users` schema. |
| `two_factor_recovery_codes` | `?string` | Encrypted recovery codes declared for Fortify; not present in the current `users` schema. |
| `two_factor_confirmed_at` | `?Carbon` | Two-factor confirmation timestamp declared for Fortify; not present in the current `users` schema. |
| `remember_token` | `?string` | Remember-me token. |
| `created_at` | `?Carbon` | Creation timestamp. |
| `updated_at` | `?Carbon` | Last update timestamp. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `initials()` | `string` | Two-letter initials derived from `name`. |
| `protected casts()` | `array` | Casts the verification timestamp and hashes the password. |

---

## 3. Enumerations

### 3.1 BusinessCategory

**Stereotype:** Enumeration — backed string enum · **Source:** `app/Enums/BusinessCategory.php` · **Namespace:** `App\Enums`

Industry domain of a business.

| Case | Value | UI label |
| :--- | :--- | :--- |
| `Restaurant` | `restaurant` | Restaurante / Gastronomía |
| `HealthAndBeauty` | `health_and_beauty` | Salud y Belleza |
| `ProfessionalServices` | `professional_services` | Servicios Profesionales |
| `Retail` | `retail` | Comercio / Retail |
| `Banking` | `banking` | Banca / Finanzas |
| `Other` | `other` | Otro |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `label()` | `string` | Returns the Spanish label shown in the UI. |

---

### 3.2 ContactType

**Stereotype:** Enumeration — backed string enum · **Source:** `app/Enums/ContactType.php` · **Namespace:** `App\Enums`

Delivery channel of a one-time code.

| Case | Value | Notes |
| :--- | :--- | :--- |
| `Email` | `email` | Delivered through the `mail` notification channel. |
| `Sms` | `sms` | Delivered through the `sms` notification channel. |
| `WhatsApp` | `whatsapp` | Delivered through the `whatsapp` notification channel. |

---

### 3.3 PersonType

**Stereotype:** Enumeration — backed string enum · **Source:** `app/Enums/PersonType.php` · **Namespace:** `App\Enums`

Legal structure of a business.

| Case | Value | UI label |
| :--- | :--- | :--- |
| `Natural` | `NATURAL` | Persona Natural |
| `LegalEntity` | `JURIDICA` | Persona Jurídica (Empresa) |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `label()` | `string` | Returns the Spanish label shown in the UI. |

---

### 3.4 VerificationStatus

**Stereotype:** Enumeration — backed string enum · **Source:** `app/Enums/VerificationStatus.php` · **Namespace:** `App\Enums`

Onboarding review state of a business.

| Case | Value | UI label |
| :--- | :--- | :--- |
| `PendingVerification` | `PENDING_VERIFICATION` | Pendiente de verificación |
| `UnderReview` | `UNDER_REVIEW` | En revisión |
| `Approved` | `APPROVED` | Aprobado |
| `ActionRequired` | `ACTION_REQUIRED` | Acción requerida |
| `Rejected` | `REJECTED` | Rechazado |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `label()` | `string` | Returns the Spanish label shown in the UI. |

---

## 4. Services

### 4.1 BusinessAuthService

**Stereotype:** Service — application service · **Source:** `app/Services/BusinessAuthService.php` · **Namespace:** `App\Services`

Authenticates business operators with email and password, applying a rate limit keyed by normalized email and client IP. It is the only place that knows how to log a business in or out.

#### Constants

| Constant | Type | Value | Description |
| :--- | :--- | :--- | :--- |
| `MAX_ATTEMPTS` | `int` | `5` | Failed attempts allowed before lockout. |
| `LOCKOUT_SECONDS` | `int` | `60` | Lockout window in seconds. |
| `MESSAGE_INVALID_CREDENTIALS` | `string` | `Credenciales incorrectas...` | Generic message returned for invalid credentials. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `normalizeEmail(string $email)` | `string` | Normalizes the email through `DataNormalizer`. |
| `findBusiness(string $email)` | `?Business` | Finds a business by normalized email. |
| `throttleKey(string $email, ?string $ipAddress = null)` | `string` | Builds the `business_login:{email}\|{ip}` rate limiter key. |
| `hasTooManyAttempts(string $email, ?string $ipAddress = null)` | `bool` | Checks the limiter against `MAX_ATTEMPTS`. |
| `availableIn(string $email, ?string $ipAddress = null)` | `int` | Seconds until the limiter clears. |
| `hitRateLimiter(string $email, ?string $ipAddress = null)` | `void` | Increments the failure counter for `LOCKOUT_SECONDS`. |
| `clearRateLimiter(string $email, ?string $ipAddress = null)` | `void` | Resets the failure counter. |
| `authenticate(string $email, string $password, bool $remember = false, ?string $ipAddress = null)` | `Business` | Authenticates through the `business` guard, regenerates the session, and throws `ValidationException` on throttling or invalid credentials. |
| `logout()` | `void` | Logs out the guard and invalidates the session. |

---

### 4.2 BusinessService

**Stereotype:** Service — domain service · **Source:** `app/Services/BusinessService.php` · **Namespace:** `App\Services`

Coordinates business registration inside a database transaction: normalizes input, creates the record in `PendingVerification`, and dispatches `WelcomeBusinessNotification`.

#### Dependencies

| Dependency | Injection | Description |
| :--- | :--- | :--- |
| `OtpService` | Constructor (`otpService`) | Available for contact verification flows. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `register(array $data)` | `Business` | Normalizes the profile, persists the business, and sends the welcome notification transactionally. |
| `isPhoneRegistered(string $phone)` | `bool` | Checks whether the normalized phone already belongs to a business. |
| `isEmailRegistered(string $email)` | `bool` | Checks whether the normalized email already belongs to a business. |

---

### 4.3 CustomerAuthService

**Stereotype:** Service — application service · **Source:** `app/Services/CustomerAuthService.php` · **Namespace:** `App\Services`

Authenticates customers with credentials or Google, and applies the same rate limiting strategy used for businesses. It also owns the account linking and provisioning rules for Socialite.

#### Constants

| Constant | Type | Value | Description |
| :--- | :--- | :--- | :--- |
| `MAX_ATTEMPTS` | `int` | `5` | Failed attempts allowed before lockout. |
| `LOCKOUT_SECONDS` | `int` | `60` | Lockout window in seconds. |
| `MESSAGE_INVALID_CREDENTIALS` | `string` | `Credenciales incorrectas...` | Generic message returned for invalid credentials. |
| `MESSAGE_GOOGLE_ACCOUNT` | `string` | `Esta cuenta está vinculada a Google...` | Guidance shown when a Google-only account tries a password login. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `normalizeEmail(string $email)` | `string` | Normalizes the email through `DataNormalizer`. |
| `findCustomer(string $email)` | `?Customer` | Finds a customer by normalized email. |
| `isGoogleOnlyAccount(Customer $customer)` | `bool` | Indicates whether the account has no local password. |
| `throttleKey(string $email, ?string $ipAddress = null)` | `string` | Builds the `customer_login:{email}\|{ip}` rate limiter key. |
| `hasTooManyAttempts(string $email, ?string $ipAddress = null)` | `bool` | Checks the limiter against `MAX_ATTEMPTS`. |
| `availableIn(string $email, ?string $ipAddress = null)` | `int` | Seconds until the limiter clears. |
| `hitRateLimiter(string $email, ?string $ipAddress = null)` | `void` | Increments the failure counter for `LOCKOUT_SECONDS`. |
| `clearRateLimiter(string $email, ?string $ipAddress = null)` | `void` | Resets the failure counter. |
| `authenticate(string $email, string $password, bool $remember = false, ?string $ipAddress = null)` | `Customer` | Authenticates through the `customer` guard and throws `ValidationException` on throttling, Google-only accounts, or invalid credentials. |
| `handleGoogleUser(SocialiteUser $googleUser)` | `Customer` | Resolves the Google identity, links or provisions the account, and logs the customer in. |
| `linkGoogleAccount(Customer $customer, SocialiteUser $googleUser)` | `Customer` | Attaches the Google id, verifies the email, and fills the avatar when missing. |
| `provisionGoogleCustomer(SocialiteUser $googleUser)` | `Customer` | Creates a password-less customer from the Google profile. |

---

### 4.4 CustomerService

**Stereotype:** Service — domain service · **Source:** `app/Services/CustomerService.php` · **Namespace:** `App\Services`

Coordinates customer registration inside a database transaction: normalizes names and contacts, persists the customer, and dispatches `WelcomeCustomerNotification`.

#### Dependencies

| Dependency | Injection | Description |
| :--- | :--- | :--- |
| `OtpService` | Constructor (`otpService`) | Available for contact verification flows. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `register(array $data)` | `Customer` | Normalizes the profile, persists the customer, and sends the welcome notification transactionally. |
| `isPhoneRegistered(string $phone)` | `bool` | Checks whether the normalized phone already belongs to a customer. |
| `isEmailRegistered(string $email)` | `bool` | Checks whether the normalized email already belongs to a customer. |

---

### 4.5 OfferingService

**Stereotype:** Service — domain service · **Source:** `app/Services/OfferingService.php` · **Namespace:** `App\Services`

Owns the offering lifecycle for a tenant: creation, edition, image storage, filtered listing, archival, restoration, and guarded permanent deletion. Write operations run inside transactions and compensate stored images when the database write fails.

#### Constants

| Constant | Type | Value | Description |
| :--- | :--- | :--- | :--- |
| `ALLOWED_SORT_FIELDS` | `string[]` | `name`, `created_at` | Whitelist of sortable columns. |
| `ALLOWED_SORT_DIRECTIONS` | `string[]` | `asc`, `desc` | Whitelist of sort directions. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `create(Business $business, array $data, ?UploadedFile $image = null)` | `Offering` | Normalizes input, stores the image, and creates the offering scoped to the business. |
| `update(Offering $offering, array $data, ?UploadedFile $image = null, bool $removeImage = false)` | `Offering` | Updates the offering, swaps or removes the image, and deletes the previous file when it is no longer referenced. |
| `storeImage(UploadedFile $file)` | `string` | Stores the file on the `public` disk under `offerings/`. |
| `deleteImage(?string $path)` | `bool` | Deletes an image from the `public` disk when it exists. |
| `listForBusiness(Business $business, array $filters = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 10)` | `LengthAwarePaginator` | Applies escaped search, category and status filters, whitelisted sorting, and pagination. |
| `archive(Offering $offering)` | `bool` | Deactivates and soft deletes the offering, keeping its image on disk. |
| `restore(Offering $offering)` | `bool` | Restores a soft-deleted offering; returns `false` when it is not trashed. |
| `hasHistoricalActivity(Offering $offering)` | `bool` | Detects related queue or order records that forbid permanent deletion. |
| `deletePermanently(Offering $offering)` | `bool` | Force deletes the record and purges its image; throws `DomainException` when historical activity exists. |

---

### 4.6 OtpService

**Stereotype:** Service — domain service · **Source:** `app/Services/OtpService.php` · **Namespace:** `App\Services`

Issues and verifies one-time codes. Codes are hashed before persistence, previous active codes are invalidated on generation, and verification enforces single use, attempt limits, and expiry precedence.

#### Constants

| Constant | Type | Value | Description |
| :--- | :--- | :--- | :--- |
| `EXPIRATION_MINUTES` | `int` | `5` | Code lifetime in minutes. |
| `COOLDOWN_SECONDS` | `int` | `60` | Minimum wait before a new code can be requested. |
| `MAX_ATTEMPTS` | `int` | `5` | Failed verifications allowed before invalidation. |
| `DEFAULT_COUNTRY_CODE` | `string` | `+57` | Country code used when normalizing phones. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `normalizeTarget(string $target, ContactType $channel)` | `string` | Normalizes the target according to the channel. |
| `normalizeEmail(string $email)` | `string` | Normalizes an email address. |
| `normalizePhone(string $phone)` | `string` | Normalizes a phone number to E.164. |
| `generate(string $target, ContactType $channel)` | `SecurityCode` | Invalidates previous codes, creates a hashed code, and dispatches `SendOtpNotification`; throws `OtpCooldownException` during cooldown. |
| `getRemainingCooldownSeconds(string $target, ?ContactType $channel = null)` | `int` | Seconds left before a new code can be requested for the target. |
| `verify(string $target, string $code, ContactType $channel)` | `bool` | Checks single use, attempt limit, expiry, and hash; increments failures and invalidates at the limit. |
| `protected generatePlainCode()` | `string` | Returns a cryptographically secure six-digit code. |
| `protected invalidatePreviousCodes(string $normalizedTarget)` | `int` | Expires unused active codes for the same target. |

---

### 4.7 StorefrontService

**Stereotype:** Service — domain service · **Source:** `app/Services/StorefrontService.php` · **Namespace:** `App\Services`

Resolves public storefronts and applies the visibility rules: approved businesses are public, while unapproved businesses are only visible in preview mode to their own authenticated owner.

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `generateUniqueSlug(string $name, ?int $excludeId = null)` | `string` | Delegates slug generation to `Business`. |
| `resolveBusiness(string $slug)` | `?Business` | Case-insensitive lookup by slug. |
| `canBeViewedBy(Business $business, ?Authenticatable $user = null)` | `bool` | Allows approved businesses or the authenticated owner. |
| `getActiveStorefrontOfferings(Business $business, ?string $search = null, ?int $categoryId = null)` | `Collection` | Returns active offerings ordered by name with escaped search and category filters. |
| `getActiveCategories(Business $business)` | `Collection` | Returns categories that currently have active offerings for the business. |

---

## 5. Presentation Layer

Full-page Livewire components act as controllers: they receive the route, validate input, and delegate to a service. `CustomerGoogleAuthController` is the only classic HTTP controller.

### 5.1 CustomerGoogleAuthController

**Stereotype:** Controller — HTTP controller · **Source:** `app/Http/Controllers/Auth/CustomerGoogleAuthController.php` · **Namespace:** `App\Http\Controllers\Auth`

Handles the Google OAuth handshake for customers.

| Field | Value |
| :--- | :--- |
| Routes | `GET auth/google/redirect` (`auth.google.redirect`), `GET auth/google/callback` (`auth.google.callback`) |
| Middleware | `guest:customer` |
| Extends | `App\Http\Controllers\Controller` |
| Dependency | `CustomerAuthService` injected through the constructor |

#### Constants

| Constant | Type | Value | Description |
| :--- | :--- | :--- | :--- |
| `ERROR_MESSAGE` | `string` | `No se pudo completar la autenticación con Google...` | Message returned to the login page when the flow fails. |

#### Operations

| Operation | Returns | Description |
| :--- | :--- | :--- |
| `redirect()` | `SymfonyRedirectResponse` | Redirects to Google, or to the dashboard when a session already exists. |
| `callback()` | `RedirectResponse` | Resolves the Google user, delegates to `handleGoogleUser()`, and redirects to the dashboard; reports failures and redirects to login. |

---

### 5.2 CustomerLogin

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/auth/customer/⚡login.blade.php`

Customer credential login page.

| Field | Value |
| :--- | :--- |
| Route | `GET login` (`login`) |
| Middleware | `guest:customer` |
| Collaborators | `CustomerAuthService` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `email` | `string` | Login email. |
| `password` | `string` | Plain password. |
| `remember` | `bool` | Remember-me flag. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount()` | Redirects authenticated customers to the dashboard. |
| `login(CustomerAuthService $authService)` | Validates the credentials, delegates to `authenticate()`, clears the password on failure, and redirects to the intended URL. |

---

### 5.3 CustomerRegister

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/auth/customer/⚡register.blade.php`

Customer registration in three stages: form submission, email OTP verification, and confirmation.

| Field | Value |
| :--- | :--- |
| Route | `GET register` (`register`) |
| Middleware | `guest` |
| Collaborators | `CustomerService`, `OtpService` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `step` | `int` | Current stage (`1` form, `2` verification, `3` confirmation). |
| `first_name` | `string` | First name. |
| `last_name` | `string` | Last name. |
| `email` | `string` | Email under registration. |
| `password` | `string` | Plain password. |
| `password_confirmation` | `string` | Password confirmation. |
| `terms` | `bool` | Terms acceptance flag. |
| `code` | `string` | Entered OTP code. |
| `emailEditCount` | `int` | Number of email corrections performed. |
| `isEditingEmail` | `bool` | Email edit mode flag. |
| `new_email` | `string` | Replacement email during edit. |
| `cooldownSeconds` | `int` | Remaining OTP cooldown. |
| `maskedEmail` | `string` | Partially masked email shown in the verification stage. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount()` | Restores an in-progress registration from the session and discards expired state. |
| `submitStepOne(OtpService $otpService)` | Applies an IP rate limit, normalizes and validates the form, sends the OTP, and stores a 15-minute session draft. |
| `verifyCode(CustomerService $customerService, OtpService $otpService)` | Verifies the code, registers the customer, logs the account in, regenerates the session, and moves to the confirmation stage. |
| `finishRegistration()` | Redirects to the intended dashboard. |
| `resendOtp(OtpService $otpService)` | Issues a new code when the cooldown allows it. |
| `editEmail()` | Opens the email correction mode. |
| `cancelEditEmail()` | Discards the pending email change. |
| `saveNewEmail(OtpService $otpService)` | Validates and persists the corrected email, then reissues the code. |
| `decrementCooldown()` | Decrements the client-facing cooldown counter. |
| `goToStepOne()` | Returns to the form step. |

---

### 5.4 BusinessLogin

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/auth/business/⚡login.blade.php`

Business credential login page.

| Field | Value |
| :--- | :--- |
| Route | `GET business/login` (`business.login`) |
| Middleware | `guest:business,web` |
| Collaborators | `BusinessAuthService` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `email` | `string` | Login email. |
| `password` | `string` | Plain password. |
| `remember` | `bool` | Remember-me flag. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount()` | Redirects authenticated businesses or administrators to their dashboards. |
| `login(BusinessAuthService $authService)` | Validates the credentials, delegates to `authenticate()`, clears the password on failure, and redirects to the business dashboard. |

---

### 5.5 BusinessRegister

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/auth/business/⚡register.blade.php`

Two-stage business registration: company, owner, and access form, then email OTP verification.

| Field | Value |
| :--- | :--- |
| Route | `GET business/register` (`business.register`) |
| Middleware | `guest:business,web` |
| Collaborators | `BusinessService`, `OtpService` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `step` | `int` | Current stage (`1` form, `2` verification, `3` confirmation). |
| `business_name` | `string` | Trade or legal name. |
| `business_category` | `string` | Selected category value. |
| `owner_first_name` | `string` | Owner's first name. |
| `owner_middle_name` | `string` | Owner's middle name. |
| `owner_last_name` | `string` | Owner's first surname. |
| `owner_second_last_name` | `string` | Owner's second surname. |
| `location` | `string` | Operating location. |
| `email` | `string` | Email under registration. |
| `password` | `string` | Plain password. |
| `password_confirmation` | `string` | Password confirmation. |
| `terms` | `bool` | Terms acceptance flag. |
| `code` | `string` | Entered OTP code. |
| `emailEditCount` | `int` | Number of email corrections performed. |
| `isEditingEmail` | `bool` | Email edit mode flag. |
| `new_email` | `string` | Replacement email during edit. |
| `cooldownSeconds` | `int` | Remaining OTP cooldown. |
| `maskedEmail` | `string` | Partially masked email shown in the verification step. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount()` | Restores an in-progress registration from the session and discards expired state. |
| `submitStepOne(OtpService $otpService)` | Applies an IP rate limit, normalizes and validates the form, sends the OTP, and stores a 15-minute session draft. |
| `verifyCode(BusinessService $businessService, OtpService $otpService)` | Verifies the code, registers the business, logs the account in, regenerates the session, and moves to the confirmation stage. |
| `finishRegistration()` | Redirects to the business dashboard with the setup flag. |
| `resendOtp(OtpService $otpService)` | Issues a new code when the cooldown allows it. |
| `editEmail()` | Opens the email correction mode. |
| `cancelEditEmail()` | Discards the pending email change. |
| `saveNewEmail(OtpService $otpService)` | Validates and persists the corrected email, then reissues the code. |
| `decrementCooldown()` | Decrements the client-facing cooldown counter. |
| `goToStepOne()` | Returns to the form step. |

---

### 5.6 StorefrontShow

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/storefront/⚡show.blade.php`

Public catalog for a business slug, with search and category filtering.

| Field | Value |
| :--- | :--- |
| Route | `GET b/{slug}` (`storefront.show`) |
| Collaborators | `StorefrontService`, `Business`, `Category`, `Offering` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `slug` | `string` | Business slug resolved from the route. |
| `businessId` | `?int` | Resolved business identifier. |
| `isPreview` | `bool` | Indicates that an unapproved business is being previewed by its owner. |
| `isAvailable` | `bool` | Indicates that the catalog can be shown to the visitor. |
| `search` | `string` | Search term synchronized with the query string. |
| `selectedCategoryId` | `?int` | Active category filter synchronized with the query string. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount(string $slug, StorefrontService $storefrontService)` | Resolves the business, evaluates visibility, and enables preview mode for the owner. |
| `business()` | Computed property that reloads the business by id. |
| `categories()` | Computed property with the active categories of the business. |
| `offerings()` | Computed property with the filtered active offerings. |
| `selectCategory(?int $categoryId = null)` | Applies a category filter. |
| `resetFilters()` | Clears the search and category filters. |
| `hasActiveFilters()` | Indicates whether any filter is applied. |

---

### 5.7 OfferingIndex

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/business/offerings/⚡index.blade.php`

Tenant offering listing with search, category and status filters, sorting, and archive, restore, and permanent deletion actions.

| Field | Value |
| :--- | :--- |
| Route | `GET business/offerings` (`business.offerings.index`) |
| Middleware | `auth:business` |
| Collaborators | `OfferingService`, `Business`, `Category`, `Offering` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `search` | `string` | Search term. |
| `category` | `string` | Selected category filter. |
| `status` | `string` | Status filter (`all`, `active`, `inactive`, `archived`). |
| `sortField` | `string` | Active sort column. |
| `sortDirection` | `string` | Active sort direction. |
| `confirmingArchiveId` | `?int` | Offering pending archive confirmation. |
| `confirmingRestoreId` | `?int` | Offering pending restore confirmation. |
| `confirmingPermanentDeleteId` | `?int` | Offering pending permanent deletion confirmation. |
| `errorMessage` | `?string` | Error surfaced by guarded operations. |

#### Actions

| Action | Description |
| :--- | :--- |
| `updatedSearch()` | Resets pagination when the search changes. |
| `updatedCategory()` | Resets pagination when the category changes. |
| `updatedStatus()` | Resets pagination when the status changes. |
| `sortBy(string $field)` | Toggles the direction of a whitelisted column. |
| `resetFilters()` | Restores the default filters and sorting. |
| `categories()` | Computed property with the available categories. |
| `totalOfferingsCount()` | Total offerings of the tenant. |
| `offerings()` | Computed paginated listing. |
| `offeringToArchive()` | Offering pending archive confirmation. |
| `offeringToRestore()` | Offering pending restore confirmation. |
| `offeringToDelete()` | Offering pending permanent deletion confirmation. |
| `confirmArchive(int $id)` | Opens the archive confirmation. |
| `archive(OfferingService $offeringService)` | Delegates archival to the service. |
| `confirmRestore(int $id)` | Opens the restore confirmation. |
| `restore(OfferingService $offeringService)` | Delegates restoration to the service. |
| `confirmPermanentDelete(int $id, OfferingService $offeringService)` | Guards the deletion against historical activity before opening the confirmation. |
| `deletePermanently(OfferingService $offeringService)` | Delegates permanent deletion to the service and exposes domain errors. |

---

### 5.8 OfferingCreate

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/business/offerings/⚡create.blade.php`

Offering creation form for the authenticated business.

| Field | Value |
| :--- | :--- |
| Route | `GET business/offerings/create` (`business.offerings.create`) |
| Middleware | `auth:business` |
| Collaborators | `OfferingService`, `Business`, `Category` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `name` | `string` | Offering name. |
| `category_id` | `int\|string\|null` | Selected category. |
| `description` | `string` | Offering description. |
| `price` | `float\|string\|null` | Price. |
| `estimated_duration_minutes` | `int\|string\|null` | Estimated duration. |
| `image` | `mixed` | Temporary uploaded image. |

#### Actions

| Action | Description |
| :--- | :--- |
| `categories()` | Computed property with the selectable categories. |
| `removeImage()` | Discards the temporary image. |
| `save(OfferingService $offeringService)` | Validates the form and delegates creation to the service. |

---

### 5.9 OfferingEdit

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/business/offerings/⚡edit.blade.php`

Offering edition form, including image replacement and restoration.

| Field | Value |
| :--- | :--- |
| Route | `GET business/offerings/{offering}/edit` (`business.offerings.edit`) |
| Middleware | `auth:business` |
| Collaborators | `OfferingService`, `Offering`, `Category` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `offering` | `Offering` | Offering bound to the route. |
| `name` | `string` | Offering name. |
| `category_id` | `int\|string\|null` | Selected category. |
| `description` | `string` | Offering description. |
| `price` | `float\|string\|null` | Price. |
| `estimated_duration_minutes` | `int\|string\|null` | Estimated duration. |
| `is_active` | `bool` | Availability flag. |
| `existing_image` | `?string` | Stored image path. |
| `image` | `mixed` | Temporary uploaded replacement image. |
| `remove_image` | `bool` | Flag that requests removal of the stored image. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount(Offering $offering)` | Verifies tenant ownership and hydrates the form from the offering. |
| `categories()` | Computed property with the selectable categories. |
| `updatedImage()` | Validates the replacement image after upload. |
| `discardTempImage()` | Discards the temporary upload. |
| `removeExistingImage()` | Flags the stored image for removal. |
| `restoreExistingImage()` | Cancels the pending image removal. |
| `removeImage()` | Removes the temporary upload. |
| `save(OfferingService $offeringService)` | Validates the form and delegates the update to the service. |

---

### 5.10 SettingsProfile

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/settings/⚡profile.blade.php`

Profile information settings for the authenticated user.

| Field | Value |
| :--- | :--- |
| Route | `GET settings/profile` (`profile.edit`) |
| Middleware | `auth` |
| Collaborators | `Auth`, `Session`, `MustVerifyEmail` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `name` | `string` | Display name. |
| `email` | `string` | Email address. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount()` | Hydrates the form from the authenticated user. |
| `updateProfileInformation()` | Validates and persists the profile, resetting the verification state when the email changes. |
| `resendVerificationNotification()` | Resends the verification email. |
| `hasUnverifiedEmail()` | Computed property that indicates whether the account still requires email verification. |
| `showDeleteUser()` | Computed property that enables the deletion section once the email is verified. |

---

### 5.11 SettingsSecurity

**Stereotype:** Component — full-page Livewire component · **Source:** `resources/views/pages/settings/⚡security.blade.php`

Password and two-factor authentication settings.

| Field | Value |
| :--- | :--- |
| Route | `GET settings/security` (`security.edit`) |
| Middleware | `auth`, `verified` |
| Collaborators | `Laravel\Fortify\Actions\DisableTwoFactorAuthentication`, `Fortify` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `current_password` | `string` | Current password. |
| `password` | `string` | New password. |
| `password_confirmation` | `string` | New password confirmation. |

#### Actions

| Action | Description |
| :--- | :--- |
| `mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication)` | Receives Fortify's action through dependency injection; currently a no-op. |
| `updatePassword()` | Validates and updates the password. |

---

### 5.12 SettingsDeleteUser

**Stereotype:** Component — Livewire modal component · **Source:** `resources/views/pages/settings/⚡delete-user-modal.blade.php`

Account deletion confirmation modal, rendered from the profile settings page.

| Field | Value |
| :--- | :--- |
| Rendered by | `resources/views/pages/settings/⚡delete-user-form.blade.php` |
| Collaborators | `App\Livewire\Actions\Logout`, `Auth` |

#### State

| Property | Type | Description |
| :--- | :--- | :--- |
| `password` | `string` | Current password used to confirm the deletion. |

#### Actions

| Action | Description |
| :--- | :--- |
| `deleteUser(Logout $logout)` | Validates the password, invokes the logout action, deletes the authenticated account, and redirects home. |

---

## Notes

- `SettingsAppearance` and the `delete-user-form` wrapper are presentation-only components without domain dependencies; they are omitted from both the class diagram and this dictionary.
- Service members are documented in full here and collapsed in the presentation view of the class diagram.
- Livewire component state is public by design: every property listed under "State" is part of the component contract with its Blade view.
