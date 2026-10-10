# USER STORIES FOR TURNEX (GENERIC QUEUE PLATFORM)

## US-CORE - ONE-TIME PASSWORD (OTP) GENERATION & VERIFICATION SERVICE

**As a** system,  
**I want to** manage the lifecycle of temporary 6-digit security codes across communication channels,  
**So that** users can securely verify their contact methods with consistent rate limits and expiration rules.

### Acceptance Criteria

- [ ] The system must generate a random 6-digit numeric security code upon request.
- [ ] Each security code must automatically expire 5 minutes after creation or immediately once successfully used.
- [ ] The system must enforce a 1-minute cooldown period before allowing a user to request a new code for the same contact target (email or phone number).
- [ ] Requesting a new security code must immediately invalidate any previously active code for that specific contact target.
- [ ] A code can only be validated once; subsequent verification attempts using the same code must be rejected.
- [ ] The service must dispatch the code via the requested delivery channel (Email, SMS, or WhatsApp) based on the consuming workflow.

---

## US01 - CUSTOMER REGISTRATION (EMAIL & PASSWORD)

**As a** customer,  
**I want to** register on the platform with minimal required fields,  
**So that** I can order ahead, claim products, and join digital queues without unnecessary friction.

### Acceptance Criteria

- [ ] The system must request the following initial registration data:
  - [ ] First name (Required, up to 60 characters)
  - [ ] Last name (Required, up to 60 characters)
  - [ ] Email address (Required, must be unique)
  - [ ] Phone number (Required, must be unique)
  - [ ] Password
- [ ] The system must verify if the email address is already registered. If it exists, display: _"An error occurred while verifying the email address. Please check if it is correct and try again."_
- [ ] The system must verify if the phone number is already registered. If it exists, display: _"An error occurred while verifying the phone number. Please check if it is correct and try again."_
- [ ] The system must dispatch a phone verification code via SMS or WhatsApp and validate it following the platform security code service rules (**US-CORE**).
- [ ] Once the phone verification code is successfully validated, the system must record the exact date and time the phone number was verified.
- [ ] The system must dispatch an email verification notification following the platform security code service rules (**US-CORE**).
- [ ] Once the email verification code or link is validated, the system must record the exact date and time the email address was verified.
- [ ] The system must verify that the password complies with security rules:
  - [ ] Minimum of 8 characters.
  - [ ] At least one number.
  - [ ] At least one uppercase letter.
  - [ ] At least one lowercase letter.
  - [ ] At least one special character.
- [ ] Upon successful registration and phone verification, the system must redirect the customer to the main discovery catalog.

---

## US01B - CUSTOMER REGISTRATION & AUTHENTICATION VIA GOOGLE OAUTH

**As a** customer,  
**I want to** register or log in using my Google account with one click,  
**So that** I can explore the platform and place pickup orders in seconds without creating and remembering passwords.

### Acceptance Criteria

- [ ] The system must provide a prominent **"Continue with Google"** action on both the customer registration and login interfaces.
- [ ] Upon clicking, the system must initiate the Google OAuth 2.0 authorization flow requesting standard profile scopes (`openid`, `profile`, `email`).
- [ ] Upon successful OAuth callback from Google:
  - [ ] The system must extract the Google ID (`sub`), email address, verified email status, first name, last name, and profile picture URL (`avatar_url`).
  - [ ] The system must mark the email address as verified immediately (`email_verified_at = now()`) since Google verifies email authenticity.
- [ ] **Existing Account Linking**:
  - [ ] If an account with the returned email address already exists and is not yet linked to Google, the system must automatically associate the `google_id` with that existing customer account and log the customer in.
- [ ] **New Account Onboarding (Progressive Mobile Number Gate)**:
  - [ ] If no customer account exists with the returned email, the system must create a new `CUSTOMER` record with `password` set to `NULL` and save the Google profile data.
  - [ ] If the customer account lacks a verified phone number, the system must immediately redirect the customer to a single-step prompt: _"Enter your phone number to receive pickup notifications."_
  - [ ] The system must verify that the phone number is not already registered by another customer.
  - [ ] The system must dispatch a phone verification code via SMS or WhatsApp and validate it following the platform security code service rules (**US-CORE**).
  - [ ] Once validated, the system must record the verification timestamp (`phone_verified_at = now()`) and redirect the user directly to the main marketplace catalog.

---

## US02 - BUSINESS REGISTRATION (STAGE 1: QUICK ONBOARDING)

**As a** business owner or manager,  
**I want to** register my business with basic contact information and select its industry category,  
**So that** I can access the platform and explore the management dashboard quickly.

### Acceptance Criteria

- [ ] The system must request the following initial data:
  - [ ] Business name (Required, up to 150 characters)
  - [ ] Business category / Industry type (Required, e.g., Restaurant, Health & Beauty, Professional Services, Retail, Banking)
  - [ ] Owner first name (Required, up to 60 characters)
  - [ ] Owner middle name (Optional, up to 60 characters)
  - [ ] Owner last name (Required, up to 60 characters)
  - [ ] Owner second last name (Optional, up to 60 characters)
  - [ ] Email address (Required, must be unique)
  - [ ] Phone number (Required, must be unique)
  - [ ] Physical address / Location (Required)
  - [ ] Password
- [ ] The system must verify if the email is already registered. If it exists, display: _"An error occurred while verifying the email address. Please check if it is correct and try again."_
- [ ] The system must verify if the phone number is already registered. If it exists, display: _"An error occurred while verifying the phone number. Please check if it is correct and try again."_
- [ ] The system must dispatch a phone verification code via SMS or WhatsApp and validate it following the platform security code service rules (**US-CORE**).
- [ ] Once the phone verification code is successfully validated, the system must record the exact date and time the phone number was verified.
- [ ] The system must set the business account initial status to **"Pending Verification"**.
- [ ] Upon successful registration, the user must be redirected to the business dashboard in setup mode.

---

## US03 - LOGIN

**As a** user (customer or business manager),  
**I want to** log in to the system using credentials or social login,  
**So that** I can access my account features and dashboard.

### Acceptance Criteria

- [ ] The system must offer multiple authentication methods:
  - [ ] Standard credentials (Email or Phone number + Password) for customers and businesses.
  - [ ] Single-click social login (**"Continue with Google"**) for customers (**US01B**).
- [ ] For credential-based login:
  - [ ] The system must verify that both identifier and password fields contain data before validating credentials.
  - [ ] The system must verify if the email or phone number exists. If not registered, display: _"Incorrect credentials. Please try again."_
  - [ ] If the account was registered exclusively via Google OAuth and has no password configured, the system must display: _"This account is linked to Google Sign-In. Please log in using Google."_
  - [ ] The system must verify if the password matches the registered user. If not, display: _"Incorrect credentials. Please try again."_
- [ ] Upon successful authentication (credentials or Google OAuth), the system must redirect the user to their corresponding role dashboard or landing view (Customer Catalog or Business Dashboard).

---

## US04 - CREATE SERVICE OR PRODUCT

**As a** business manager,  
**I want to** create a service or product offered by my business,  
**So that** customers can select or purchase it when joining the queue.

### Acceptance Criteria

- [ ] The system must request the following data:
  - [ ] Service / Product name
  - [ ] Service category / Line
  - [ ] Reference image
  - [ ] Description
  - [ ] Price (Optional or 0 for free/standard queue services)
  - [ ] Estimated duration in minutes (Optional)
- [ ] The **"Service name"** field is mandatory and must contain between 3 and 50 characters.
- [ ] The **"Reference image"** field is optional, accepts files up to 10 MB, and allows PNG, JPG, and SVG formats only.
- [ ] The **"Description"** field is optional and limited to a maximum of 250 characters.
- [ ] The **"Price"** field must accept non-negative numbers with up to two decimal places (defaults to 0.00).
- [ ] The **"Estimated duration in minutes"** field is optional and must only accept whole numbers.
- [ ] Newly created services or products must be active by default.

---

## US05 - EDIT SERVICE OR PRODUCT

**As a** business manager,  
**I want to** edit an existing service or product,  
**So that** I can keep my service catalog up to date.

### Acceptance Criteria

- [ ] The system must display current details pre-filled in the edit form:
  - [ ] Service / Product name
  - [ ] Service category / Line
  - [ ] Reference image preview
  - [ ] Description
  - [ ] Price
  - [ ] Estimated duration in minutes
  - [ ] Service status (Active / Inactive)
- [ ] The **"Service name"** field is mandatory and must contain between 3 and 50 characters.
- [ ] The **"Reference image"** field is optional. If a new image is uploaded, it must accept files up to 10 MB in PNG, JPG, or SVG format only. If no file is uploaded, the system must retain the existing image.
- [ ] The **"Description"** field is optional and limited to a maximum of 250 characters.
- [ ] The **"Price"** field must accept non-negative numbers with up to two decimal places.
- [ ] The system must allow changing the service status between active and inactive at any time.
- [ ] The system must update details upon saving and display a success notification: _"Service updated successfully."_

---

## US06 - BUSINESS PROFILE & BRANDING SETUP (STAGE 2)

**As a** registered business,  
**I want to** customize my public profile with branding assets and location details,  
**So that** my storefront looks attractive and identifiable to prospective customers.

### Acceptance Criteria

- [ ] The system must allow the business to upload:
  - [ ] **Logo image:** Required, minimum resolution of 360x360 px (PNG, JPG format up to 5 MB).
  - [ ] **Cover photo:** Required, horizontal orientation, minimum resolution of 360x360 px (PNG, JPG format up to 10 MB).
  - [ ] **Business description:** Optional, limited to a maximum of 500 characters.
- [ ] The system must validate image dimensions and file formats before allowing the upload.
- [ ] If an uploaded image does not meet resolution rules, the system must display: _"The image dimensions do not meet the minimum requirements (360x360 px)."_
- [ ] The system must save the configuration and display a live preview of how the business profile will appear to customers.

---

## US07 - BUSINESS LEGAL & BANKING VERIFICATION (STAGE 3: KYB)

**As a** business owner,  
**I want to** submit my business legal and banking documentation,  
**So that** my business can be verified to process paid transactions and operate publicly.

### Acceptance Criteria

- [ ] The system must request the upload of the following legal documents (PDF or image format up to 10 MB each):
  - [ ] **Tax Identification Document** (e.g., RUT / Business Tax ID)
  - [ ] **Legal Representative ID Document** (National ID / Passport)
  - [ ] **Bank Account Certificate** (issued within the last 90 days)
  - [ ] **Commercial Permit / Operating License** (Optional depending on business category)
- [ ] Each uploaded document must start in a **"Pending"** evaluation state.
- [ ] The system must request bank details for payout settlements:
  - [ ] Bank name
  - [ ] Account type (Savings or Checking)
  - [ ] Account number
  - [ ] Account holder name
  - [ ] Account holder ID number (must match legal documents)
- [ ] The system must require at least **1 active service or product** created before enabling submission.
- [ ] Once all mandatory documents and banking requirements are completed, the business can click **"Submit for Verification"**.
- [ ] The system must update the business account status to **"Under Review"** and lock all document and bank fields against edits while under evaluation.

---

## US08 - SUPERADMIN BUSINESS APPROVAL WORKFLOW

**As a** SuperAdmin,  
**I want to** review, approve, or reject business verification submissions,  
**So that** I can prevent fraud and ensure only legitimate businesses operate on the platform.

### Acceptance Criteria

- [ ] The system must display a list of businesses filtered by status: **"Under Review"**, **"Approved"**, and **"Action Required"**.
- [ ] The SuperAdmin must be able to view all submitted legal documents, bank details, and active services or products for any business under review.
- [ ] The SuperAdmin must have two primary actions:
  - [ ] **Approve:** Updates the business status to **"Approved"**, marks all pending submitted documents as **"Approved"**, makes the business profile publicly visible, and enables digital queue management and online payments.
  - [ ] **Reject:**
    - [ ] Requires entering a mandatory general rejection reason for the business.
    - [ ] Allows providing specific feedback on individual invalid documents and marking those specific files as **"Rejected"**.
    - [ ] Updates the business status to **"Action Required"** and unlocks document upload fields so the business owner can re-submit the requested files.
- [ ] The system must send an automated email notification to the business containing the rejection reason and a direct link to update the flagged documentation.

## US09 - ADMIN LOGIN

**As a** system administrator,  
**I want to** log in to the administrative portal with my administrative credentials,  
**So that** I can access back-office features and manage platform catalog data.

### Acceptance Criteria

- [ ] The system must request the following login credentials:
  - [ ] Administrative email address
  - [ ] Password
- [ ] The system must verify that both fields contain data before attempting authentication.
- [ ] If the email address does not exist or the account is marked as inactive, the system must display: _"Invalid credentials or inactive account."_
- [ ] If the password does not match the registered user, the system must display: _"Invalid credentials or inactive account."_
- [ ] Upon successful authentication, the system must record the current date and time of login and redirect the user directly to the administrative dashboard.

---

## US10 - CREATE SERVICE CATEGORY

**As a** system administrator,  
**I want to** create a new service category,  
**So that** businesses can classify their services and products under standardized lines.

### Acceptance Criteria

- [ ] The system must request the following data:
  - [ ] Category name (Required, up to 60 characters)
  - [ ] Description (Optional, up to 200 characters)
- [ ] The system must verify that the category name contains at least 3 characters and is not already registered. If duplicated, display: _"A category with this name already exists."_
- [ ] The new category must be set to active by default upon creation.
- [ ] Once saved, the system must display a confirmation notification: _"Category created successfully."_

---

## US11 - VIEW SERVICE CATEGORIES

**As a** system administrator,  
**I want to** view a list of all available service categories,  
**So that** I can inspect the active catalog structure, search for specific entries, and filter by status.

### Acceptance Criteria

- [ ] The system must display a paginated list of categories showing:
  - [ ] Category name
  - [ ] Description
  - [ ] Current status (Active / Inactive)
  - [ ] Creation date
- [ ] By default, the list must only display non-archived categories (excluding soft-deleted records).
- [ ] The administrator must be able to search categories by name using a search bar.
- [ ] The administrator must be able to filter the list by status (All, Active, Inactive, Archived).
- [ ] The list must allow sorting by category name (A-Z, Z-A) and by creation date (newest first, oldest first).
- [ ] If a search or filter produces no matching results, the system must display: _"No categories found matching your criteria."_

---

## US12 - EDIT SERVICE CATEGORY

**As a** system administrator,  
**I want to** update the details of an existing category,  
**So that** I can keep classification names and descriptions accurate.

### Acceptance Criteria

- [ ] The system must display an edit form pre-filled with the current category data:
  - [ ] Category name
  - [ ] Description
  - [ ] Active status
- [ ] The category name must remain mandatory, have between 3 and 60 characters, and cannot duplicate another existing category's name.
- [ ] The administrator must be able to update the name, description, or toggle the status between active and inactive.
- [ ] Upon saving changes, the system must record the update date and time and display a success notification: _"Category updated successfully."_

---

## US13 - SOFT DELETE SERVICE CATEGORY

**As a** system administrator,  
**I want to** delete an unused service category,  
**So that** outdated categories are archived without losing system data integrity.

### Acceptance Criteria

- [ ] The system must provide a delete action next to each category in the list.
- [ ] Before executing the deletion, the system must verify whether there are any existing services or products associated with the category.
- [ ] If at least one active or inactive service or product is linked to the category, the system must prevent deletion and display an error message: _"This category cannot be deleted because it is currently assigned to one or more services."_
- [ ] If no services or products are linked, the system must prompt for confirmation before proceeding.
- [ ] Upon confirmation, the system must perform a soft delete (marking the category as inactive and setting the archive date and time) without physically removing the record from the database.
- [ ] Once archived, the system must display a notification: _"Category deleted successfully."_
