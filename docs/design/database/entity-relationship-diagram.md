```mermaid
erDiagram
USERS ||--o{ BUSINESSES : reviews
BUSINESSES ||--o{ OFFERINGS : offers
CATEGORIES ||--o{ OFFERINGS : classifies
BUSINESSES ||--o{ QUEUE_TICKETS : issues
CUSTOMERS ||--o{ QUEUE_TICKETS : requests
QUEUE_TICKETS ||--|{ QUEUE_TICKET_ITEMS : contains
OFFERINGS ||--o{ QUEUE_TICKET_ITEMS : specifies

    USERS {
        BIGINT id PK "AUTO INCREMENT"
        VARCHAR(255) name
        VARCHAR(255) email UK "UNIQUE"
        TIMESTAMP email_verified_at "NULL"
        VARCHAR(255) password
        VARCHAR(100) remember_token "NULL"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    CUSTOMERS {
        BIGINT id PK "AUTO INCREMENT"
        VARCHAR(60) first_name
        VARCHAR(60) last_name
        VARCHAR(255) email UK "UNIQUE"
        VARCHAR(20) phone_number UK "UNIQUE, NULL - E.164 format"
        VARCHAR(255) password "NULL"
        VARCHAR(255) google_id UK "NULL"
        VARCHAR(255) avatar_url "NULL"
        TIMESTAMP email_verified_at "NULL"
        TIMESTAMP phone_verified_at "NULL"
        VARCHAR(100) remember_token "NULL"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    BUSINESSES {
        BIGINT id PK "AUTO INCREMENT"
        BIGINT reviewed_by FK "NULL - References USERS"
        VARCHAR(20) person_type "NATURAL, JURIDICA - Default NATURAL"
        VARCHAR(20) tax_id "NULL - NIT or National ID for tax purposes"
        CHAR(1) dv "NULL - DIAN verification digit (0-9)"
        VARCHAR(150) business_name
        VARCHAR(160) slug UK "UNIQUE, NULL - Auto-generated public URL slug"
        VARCHAR(50) business_category "restaurant, health_and_beauty, professional_services, retail, banking, other"
        VARCHAR(60) owner_first_name
        VARCHAR(60) owner_middle_name "NULL"
        VARCHAR(60) owner_last_name
        VARCHAR(60) owner_second_last_name "NULL"
        VARCHAR(255) email UK "UNIQUE"
        VARCHAR(20) phone_number UK "UNIQUE, NULL - E.164 format"
        VARCHAR(255) location
        VARCHAR(255) logo_path "NULL"
        VARCHAR(255) cover_photo_path "NULL"
        VARCHAR(500) business_description "NULL"
        VARCHAR(30) verification_status "PENDING_VERIFICATION, UNDER_REVIEW, APPROVED, ACTION_REQUIRED, REJECTED - Default PENDING_VERIFICATION"
        TEXT rejection_reason "NULL"
        TIMESTAMP email_verified_at "NULL"
        TIMESTAMP phone_verified_at "NULL"
        VARCHAR(255) password
        VARCHAR(100) remember_token "NULL"
        TIMESTAMP reviewed_at "NULL"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    CATEGORIES {
        BIGINT id PK "AUTO INCREMENT"
        VARCHAR(60) name UK "UNIQUE"
        VARCHAR(200) description "NULL"
        BOOLEAN is_active "DEFAULT TRUE"
        TIMESTAMP deleted_at "NULL - Soft delete"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    OFFERINGS {
        BIGINT id PK "AUTO INCREMENT"
        BIGINT business_id FK "References BUSINESSES - ON DELETE CASCADE"
        BIGINT category_id FK "References CATEGORIES - ON DELETE RESTRICT"
        VARCHAR(50) name
        VARCHAR(255) reference_image "NULL"
        VARCHAR(250) description "NULL"
        DECIMAL(10,2) price "DEFAULT 0.00"
        INT estimated_duration_minutes "UNSIGNED, NULL"
        BOOLEAN is_active "DEFAULT TRUE"
        TIMESTAMP deleted_at "NULL - Soft delete"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    SECURITY_CODES {
        BIGINT id PK "AUTO INCREMENT"
        VARCHAR(255) contact_value "INDEX"
        VARCHAR(20) contact_type "email, sms, whatsapp"
        VARCHAR(255) code "Hashed OTP"
        TINYINT attempts_count "UNSIGNED, DEFAULT 0"
        TIMESTAMP expires_at
        TIMESTAMP used_at "NULL"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    QUEUE_TICKETS {
        BIGINT id PK "AUTO INCREMENT"
        BIGINT business_id FK "Mandatory - References BUSINESSES"
        BIGINT customer_id FK "Mandatory - References CUSTOMERS"
        VARCHAR(10) ticket_number "e.g. TK-001, daily reset"
        VARCHAR(32) ticket_code UK "UNIQUE - Secure URL token"
        VARCHAR(20) status "PENDING, PREPARING, READY, CALLED, IN_SERVICE, SKIPPED, COMPLETED, NO_SHOW, CANCELLED - Default PENDING"
        DECIMAL(10,2) total_price "DEFAULT 0.00"
        TIMESTAMP called_at "NULL - Timestamp when ticket is called"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }

    QUEUE_TICKET_ITEMS {
        BIGINT id PK "AUTO INCREMENT"
        BIGINT queue_ticket_id FK "References QUEUE_TICKETS"
        BIGINT offering_id FK "References OFFERINGS"
        INT quantity "UNSIGNED, DEFAULT 1"
        DECIMAL(10,2) unit_price "DEFAULT 0.00 - Frozen unit price"
        TIMESTAMP created_at "NULL"
        TIMESTAMP updated_at "NULL"
    }
```
