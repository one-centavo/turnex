# TURNEX — Data Dictionary

This document provides a comprehensive data dictionary for the Turnex platform database schema, derived from the Entity-Relationship Diagram ([entity-relationship-diagram.md](entity-relationship-diagram.md)).

---

## Table of Contents

1. [System Users (`system_users` / `users`)](#1-system-users-system_users--users)
2. [Customers (`customers`)](#2-customers-customers)
3. [Businesses (`businesses`)](#3-businesses-businesses)
4. [Business Bank Accounts (`business_bank_accounts`)](#4-business-bank-accounts-business_bank_accounts)
5. [Business Documents (`business_documents`)](#5-business-documents-business_documents)
6. [Categories (`categories`)](#6-categories-categories)
7. [Offerings (`offerings`)](#7-offerings-offerings)
8. [Security Codes (`security_codes`)](#8-security-codes-security_codes)
9. [Database Relationships Summary](#9-database-relationships-summary)

---

## 1. System Users (`system_users` / `users`)

Administrative and operational users responsible for platform governance, business verification, and audit monitoring.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the system user record. |
| `first_name` | `VARCHAR(60)` | No | | | Given name of the administrator or system operator. |
| `last_name` | `VARCHAR(60)` | No | | | Family name of the administrator or system operator. |
| `email` | `VARCHAR(255)` | No | UK | | Unique corporate/administrative email address used for login and notifications. |
| `password` | `VARCHAR(255)` | No | | | Hashed authentication password (Bcrypt/Argon2). |
| `role` | `VARCHAR(30)` | No | | | User authorization role. Allowed values: `SUPERADMIN`, `ADMIN`, `AUDITOR`. |
| `is_active` | `BOOLEAN` | No | | `TRUE` | Status flag determining if user access is granted or revoked. |
| `last_login_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp tracking the user's latest authenticated session. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 2. Customers (`customers`)

End-users who join queues, monitor turn status, place self-service orders, and interact with commercial establishments.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the customer record. |
| `first_name` | `VARCHAR(60)` | No | | | Customer's first name. |
| `last_name` | `VARCHAR(60)` | No | | | Customer's last name. |
| `email` | `VARCHAR(255)` | No | UK | | Unique personal email address. |
| `phone_number` | `VARCHAR(20)` | Yes | UK | `NULL` | Unique phone number normalized in international E.164 format (e.g., `+573001234567`). |
| `password` | `VARCHAR(255)` | Yes | | `NULL` | Hashed password. Nullable when registering via OAuth / Socialite providers. |
| `google_id` | `VARCHAR(255)` | Yes | UK | `NULL` | Unique external subject identifier from Google OAuth2. |
| `avatar_url` | `VARCHAR(255)` | Yes | | `NULL` | URL or relative storage path pointing to customer's profile picture. |
| `email_verified_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp indicating when email ownership was successfully verified. |
| `phone_verified_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp indicating when mobile phone was verified via OTP. |
| `remember_token` | `VARCHAR(100)` | Yes | | `NULL` | Laravel authentication remember-me session token. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 3. Businesses (`businesses`)

Commercial establishments and service providers registered on the platform to manage queues, catalogs, and orders.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the business entity. |
| `reviewed_by` | `BIGINT UNSIGNED` | Yes | FK | `NULL` | References `system_users(id)` or `users(id)`. Set on review completion (`ON DELETE SET NULL`). |
| `person_type` | `VARCHAR(20)` | No | | `'NATURAL'` | Legal structure classification. Allowed values: `NATURAL`, `JURIDICA`. |
| `tax_id` | `VARCHAR(20)` | Yes | | `NULL` | National tax identification number (e.g., NIT or Cedula de Ciudadania). |
| `dv` | `CHAR(1)` | Yes | | `NULL` | Single-digit DIAN tax verification digit (`0` through `9`). |
| `business_name` | `VARCHAR(150)` | No | | | Registered commercial trade name or official legal entity name. |
| `business_category` | `VARCHAR(50)` | No | | | Industry domain category (e.g., `restaurant`, `health_and_beauty`, `professional_services`, `retail`, `banking`, `other`). |
| `owner_first_name` | `VARCHAR(60)` | No | | | Legal representative or owner's primary given name. |
| `owner_middle_name` | `VARCHAR(60)` | Yes | | `NULL` | Legal representative or owner's middle name. |
| `owner_last_name` | `VARCHAR(60)` | No | | | Legal representative or owner's primary surname. |
| `owner_second_last_name` | `VARCHAR(60)` | Yes | | `NULL` | Legal representative or owner's secondary surname. |
| `email` | `VARCHAR(255)` | No | UK | | Primary contact/login email address for the merchant. |
| `phone_number` | `VARCHAR(20)` | No | UK | | Contact phone number in E.164 standard format. |
| `location` | `VARCHAR(255)` | No | | | Physical business address, city, or operating location. |
| `logo_path` | `VARCHAR(255)` | Yes | | `NULL` | File system storage path to the uploaded business logo asset. |
| `cover_photo_path` | `VARCHAR(255)` | Yes | | `NULL` | File system storage path to the public header/banner image. |
| `business_description` | `VARCHAR(500)` | Yes | | `NULL` | Public merchant description and service overview. |
| `verification_status` | `VARCHAR(30)` | No | | `'PENDING_VERIFICATION'` | KYC onboarding approval state. Allowed values: `PENDING_VERIFICATION`, `UNDER_REVIEW`, `APPROVED`, `ACTION_REQUIRED`, `REJECTED`. |
| `rejection_reason` | `TEXT` | Yes | | `NULL` | Explanatory feedback and remarks provided by the reviewer if rejected. |
| `email_verified_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp indicating confirmation of business email address. |
| `phone_verified_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp indicating confirmation of business phone number. |
| `password` | `VARCHAR(255)` | No | | | Hashed authentication password for the business account. |
| `remember_token` | `VARCHAR(100)` | Yes | | `NULL` | Laravel authentication session persistence token. |
| `reviewed_at` | `TIMESTAMP` | Yes | | `NULL` | Date and time when the reviewer submitted verification decision. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 4. Business Bank Accounts (`business_bank_accounts`)

Banking and payout details registered by a business for settlements and payment disbursements.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the bank account record. |
| `business_id` | `BIGINT UNSIGNED` | No | FK, UK | | References `businesses(id)`. Strict 1-to-1 relationship (`ON DELETE CASCADE`). |
| `bank_name` | `VARCHAR(100)` | No | | | Name of the authorized banking institution. |
| `account_type` | `VARCHAR(30)` | No | | | Account category. Allowed values: `SAVINGS`, `CHECKING`, `ELECTRONIC_DEPOSIT`. |
| `account_number` | `VARCHAR(50)` | No | | | Financial account identification number. |
| `account_holder_name` | `VARCHAR(150)` | No | | | Legal name or trade entity matching the bank account ownership. |
| `account_holder_id` | `VARCHAR(30)` | No | | | Official identification document number (e.g., NIT or CC) of the account holder. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 5. Business Documents (`business_documents`)

Compliance and legal documentation submitted during the merchant verification (KYC) process.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the document record. |
| `business_id` | `BIGINT UNSIGNED` | No | FK | | References `businesses(id)`. Multiple documents per business (`ON DELETE CASCADE`). |
| `document_type` | `VARCHAR(40)` | No | | | Compliance document classification. Allowed values: `TAX_ID`, `REPRESENTATIVE_ID`, `BANK_CERTIFICATE`, `OPERATING_LICENSE`. |
| `file_path` | `VARCHAR(255)` | No | | | Secure internal disk storage path where the file is stored. |
| `status` | `VARCHAR(20)` | No | | `'PENDING'` | Document review status. Allowed values: `PENDING`, `APPROVED`, `REJECTED`. |
| `rejection_reason` | `VARCHAR(500)` | Yes | | `NULL` | Detailed explanation why this individual document was rejected. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 6. Categories (`categories`)

Grouping taxonomy used to organize offerings, products, or services within businesses.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the category record. |
| `name` | `VARCHAR(60)` | No | | | Human-readable name of the category (e.g., "Beverages", "Consultations", "Haircuts"). |
| `description` | `VARCHAR(200)` | Yes | | `NULL` | Brief explanation of items or services grouped within this category. |
| `is_active` | `BOOLEAN` | No | | `TRUE` | Availability flag to display or hide the category from customer listings. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 7. Offerings (`offerings`)

Commercial products, menu items, or bookable services made available by businesses in virtual queues and self-service catalogs.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the offering record. |
| `business_id` | `BIGINT UNSIGNED` | No | FK | | References `businesses(id)` (`ON DELETE CASCADE`). |
| `category_id` | `BIGINT UNSIGNED` | No | FK | | References `categories(id)` (`ON DELETE RESTRICT`). |
| `name` | `VARCHAR(50)` | No | | | Commercial name of the product or service offering. |
| `reference_image` | `VARCHAR(255)` | Yes | | `NULL` | File system storage path or URL for the offering's promotional image. |
| `description` | `VARCHAR(250)` | Yes | | `NULL` | Detailed description of the offering, ingredients, or service prerequisites. |
| `price` | `DECIMAL(10,2)` | No | | `0.00` | Monetary unit price in local currency (e.g., COP). Non-negative value. |
| `estimated_duration_minutes` | `INT` | Yes | | `NULL` | Estimated queue handling or service execution duration in minutes. |
| `is_active` | `BOOLEAN` | No | | `TRUE` | Operational availability flag to display or hide this offering. |
| `created_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | No | | `CURRENT_TIMESTAMP` | Record last update timestamp. |

---

## 8. Security Codes (`security_codes`)

Lifecycle management of temporary One-Time Passwords (OTP) across communication channels (Email, SMS, WhatsApp).

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the security code event. |
| `contact_value` | `VARCHAR(255)` | No | INDEX | | Target destination: normalized email address or E.164 phone number. |
| `contact_type` | `VARCHAR(20)` | No | | | Delivery channel enum. Allowed values: `EMAIL`, `SMS`, `WHATSAPP`. |
| `code` | `VARCHAR(255)` | No | | | 6-digit numeric OTP code (stored securely / hashed). |
| `attempts_count` | `TINYINT UNSIGNED` | No | | `0` | Number of failed verification attempts. Maximum permitted is `5`. |
| `expires_at` | `TIMESTAMP` | No | | | Expiration timestamp (created_at + 5 minutes). |
| `used_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp when the code was successfully validated (single-use constraint). |
| `created_at` | `TIMESTAMP` | Yes | | `CURRENT_TIMESTAMP` | Generation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `CURRENT_TIMESTAMP` | Last update timestamp. |

---

## 9. Database Relationships Summary

| Parent Entity | Relationship | Child Entity | Foreign Key | Cardinality | Action on Delete |
| :--- | :---: | :--- | :--- | :---: | :--- |
| `system_users` | reviews | `businesses` | `businesses.reviewed_by` | 1 to 0..* | `SET NULL` |
| `businesses` | has | `business_bank_accounts` | `business_bank_accounts.business_id` | 1 to 0..1 | `CASCADE` |
| `businesses` | submits | `business_documents` | `business_documents.business_id` | 1 to 0..* | `CASCADE` |
| `businesses` | offers | `offerings` | `offerings.business_id` | 1 to 0..* | `CASCADE` |
| `categories` | classifies | `offerings` | `offerings.category_id` | 1 to 0..* | `RESTRICT` |
