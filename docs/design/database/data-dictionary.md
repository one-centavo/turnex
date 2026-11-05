# TURNEX — Data Dictionary

This document provides a comprehensive data dictionary for the Turnex platform database schema, derived from the Entity-Relationship Diagram ([entity-relationship-diagram.md](entity-relationship-diagram.md)).

---

## Table of Contents

1. [Users (`users`)](#1-users-users)
2. [Customers (`customers`)](#2-customers-customers)
3. [Businesses (`businesses`)](#3-businesses-businesses)
4. [Categories (`categories`)](#4-categories-categories)
5. [Offerings (`offerings`)](#5-offerings-offerings)
6. [Security Codes (`security_codes`)](#6-security-codes-security_codes)
7. [Queue Tickets (`queue_tickets`)](#7-queue-tickets-queue_tickets)
8. [Queue Ticket Items (`queue_ticket_items`)](#8-queue-ticket-items-queue_ticket_items)
9. [Database Relationships Summary](#9-database-relationships-summary)

---

## 1. Users (`users`)

Administrative and operational users responsible for platform governance, business verification, and audit monitoring.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the system user record. |
| `name` | `VARCHAR(255)` | No | | | Full display name of the administrator or system operator. |
| `email` | `VARCHAR(255)` | No | UK | | Unique corporate/administrative email address used for login and notifications. |
| `email_verified_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp indicating when email ownership was successfully verified. |
| `password` | `VARCHAR(255)` | No | | | Hashed authentication password (Bcrypt/Argon2). |
| `remember_token` | `VARCHAR(100)` | Yes | | `NULL` | Laravel authentication remember-me session token. |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

> Administrative roles and account status are not stored in the current schema; access control is handled outside this table.

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
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

---

## 3. Businesses (`businesses`)

Commercial establishments and service providers registered on the platform to manage queues, catalogs, and orders.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the business entity. |
| `reviewed_by` | `BIGINT UNSIGNED` | Yes | FK | `NULL` | References `users(id)`. Set when review is completed (`ON DELETE SET NULL`). |
| `person_type` | `VARCHAR(20)` | No | | `'NATURAL'` | Legal structure classification. Allowed values: `NATURAL`, `JURIDICA`. |
| `tax_id` | `VARCHAR(20)` | Yes | | `NULL` | National tax identification number (e.g., NIT or Cedula de Ciudadania). |
| `dv` | `CHAR(1)` | Yes | | `NULL` | Single-digit DIAN tax verification digit (`0` through `9`). |
| `business_name` | `VARCHAR(150)` | No | | | Registered commercial trade name or official legal entity name. |
| `slug` | `VARCHAR(160)` | Yes | UK | `NULL` | Unique, URL-friendly public identifier. Generated automatically from `business_name` when a business is created; the database column is nullable but uniqueness is enforced. |
| `business_category` | `VARCHAR(50)` | No | | | Industry domain category. Allowed values: `restaurant`, `health_and_beauty`, `professional_services`, `retail`, `banking`, `other`. |
| `owner_first_name` | `VARCHAR(60)` | No | | | Legal representative or owner's primary given name. |
| `owner_middle_name` | `VARCHAR(60)` | Yes | | `NULL` | Legal representative or owner's middle name. |
| `owner_last_name` | `VARCHAR(60)` | No | | | Legal representative or owner's primary surname. |
| `owner_second_last_name` | `VARCHAR(60)` | Yes | | `NULL` | Legal representative or owner's secondary surname. |
| `email` | `VARCHAR(255)` | No | UK | | Primary contact/login email address for the merchant. |
| `phone_number` | `VARCHAR(20)` | Yes | UK | `NULL` | Contact phone number in E.164 standard format. |
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
| `reviewed_at` | `TIMESTAMP` | Yes | | `NULL` | Date and time when the reviewer submitted the verification decision. |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

---

## 4. Categories (`categories`)

Grouping taxonomy used to organize offerings, products, or services within businesses.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the category record. |
| `name` | `VARCHAR(60)` | No | UK | | Unique human-readable name of the category (e.g., "Beverages", "Consultations", "Haircuts"). |
| `description` | `VARCHAR(200)` | Yes | | `NULL` | Brief explanation of items or services grouped within this category. |
| `is_active` | `BOOLEAN` | No | | `TRUE` | Availability flag to display or hide the category from customer listings. |
| `deleted_at` | `TIMESTAMP` | Yes | | `NULL` | Soft delete timestamp. Categories are retained for historical references while hidden from active listings. |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

---

## 5. Offerings (`offerings`)

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
| `estimated_duration_minutes` | `INT UNSIGNED` | Yes | | `NULL` | Estimated queue handling or service execution duration in minutes. Non-negative value. |
| `is_active` | `BOOLEAN` | No | | `TRUE` | Operational availability flag to display or hide this offering. |
| `deleted_at` | `TIMESTAMP` | Yes | | `NULL` | Soft delete timestamp. Offerings are retained for historical references while hidden from active catalogs. |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

---

## 6. Security Codes (`security_codes`)

Lifecycle management of temporary One-Time Passwords (OTP) across communication channels (Email, SMS, WhatsApp).

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the security code event. |
| `contact_value` | `VARCHAR(255)` | No | INDEX | | Target destination: normalized email address or E.164 phone number. |
| `contact_type` | `VARCHAR(20)` | No | | | Delivery channel enum. Allowed values: `email`, `sms`, `whatsapp`. |
| `code` | `VARCHAR(255)` | No | | | 6-digit numeric OTP code, stored hashed. |
| `attempts_count` | `TINYINT UNSIGNED` | No | | `0` | Number of failed verification attempts. Maximum permitted is `5`. |
| `expires_at` | `TIMESTAMP` | No | | | Expiration timestamp (created_at + 5 minutes). |
| `used_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp when the code was successfully validated (single-use constraint). |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Generation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Last update timestamp. |

---

## 7. Queue Tickets (`queue_tickets`)

Individual virtual queue tickets issued to authenticated customers visiting commercial venues.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the queue ticket record. |
| `business_id` | `BIGINT UNSIGNED` | No | FK | | Venue issuing the ticket. Foreign key referencing `businesses.id`. |
| `customer_id` | `BIGINT UNSIGNED` | No | FK | | Authenticated customer holding the turn. Foreign key referencing `customers.id`. |
| `ticket_number` | `VARCHAR(10)` | No | | | Daily human-readable sequential turn identifier (e.g., `TK-001`, `TK-014`). Resets daily. |
| `ticket_code` | `VARCHAR(32)` | No | UK | | Secure URL token used for public live ticket tracking (`/tickets/{code}`). |
| `status` | `VARCHAR(20)` | No | | `PENDING` | Lifecycle status. Allowed values: `PENDING`, `PREPARING`, `READY`, `CALLED`, `IN_SERVICE`, `SKIPPED`, `COMPLETED`, `NO_SHOW`, `CANCELLED`. |
| `total_price` | `DECIMAL(10,2)` | No | | `0.00` | Aggregated total monetary amount for all requested items in this turn. |
| `called_at` | `TIMESTAMP` | Yes | | `NULL` | Timestamp tracking when the ticket was called to the counter or service station. |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Creation timestamp representing when the customer joined the queue. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

---

## 8. Queue Ticket Items (`queue_ticket_items`)

Individual catalog offerings and quantities associated with a specific queue ticket.

| Column | Data Type | Nullable | Key | Default | Description & Constraints |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | No | PK | `AUTO_INCREMENT` | Unique identifier for the ticket item record. |
| `queue_ticket_id` | `BIGINT UNSIGNED` | No | FK | | Parent ticket record. Foreign key referencing `queue_tickets.id`. |
| `offering_id` | `BIGINT UNSIGNED` | No | FK | | Associated commercial offering. Foreign key referencing `offerings.id`. |
| `quantity` | `INT UNSIGNED` | No | | `1` | Number of units requested for this offering. Minimum is 1. |
| `unit_price` | `DECIMAL(10,2)` | No | | `0.00` | Frozen unit price captured at the moment of ticket creation. |
| `created_at` | `TIMESTAMP` | Yes | | `NULL` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | | `NULL` | Record last update timestamp. |

---

## 9. Database Relationships Summary

| Parent Entity | Relationship | Child Entity | Foreign Key | Cardinality | Action on Delete |
| :--- | :---: | :--- | :--- | :---: | :--- |
| `users` | reviews | `businesses` | `businesses.reviewed_by` | 1 to 0..* | `SET NULL` |
| `businesses` | offers | `offerings` | `offerings.business_id` | 1 to 0..* | `CASCADE` |
| `categories` | classifies | `offerings` | `offerings.category_id` | 1 to 0..* | `RESTRICT` |
| `businesses` | issues | `queue_tickets` | `queue_tickets.business_id` | 1 to 0..* | `CASCADE` |
| `customers` | requests | `queue_tickets` | `queue_tickets.customer_id` | 1 to 0..* | `CASCADE` |
| `queue_tickets` | contains | `queue_ticket_items` | `queue_ticket_items.queue_ticket_id` | 1 to 1..* | `CASCADE` |
| `offerings` | specifies | `queue_ticket_items` | `queue_ticket_items.offering_id` | 1 to 0..* | `RESTRICT` |
