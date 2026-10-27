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
- [ ] A security code must permit a maximum of 5 incorrect verification attempts; upon the 5th failed attempt, the code must be immediately invalidated.
- [ ] A code can only be validated once; subsequent verification attempts using the same code must be rejected.
- [ ] The service must dispatch the code via the requested delivery channel (Email, SMS, or WhatsApp) based on the consuming workflow.

---

## US-CORE-02 - INPUT DATA SANITIZATION & NORMALIZATION SERVICE

**As a** system,  
**I want to** automatically clean and format any information entered by users,  
**So that** data remains tidy, consistent, and easy to match across the entire platform.

### Acceptance Criteria

- [ ] Accidental spaces at the beginning and end of any text field are automatically removed.
- [ ] Extra spaces between words are reduced to a single space.
- [ ] Fields containing only spaces are treated as empty.
- [ ] Email addresses are converted to lowercase.
- [ ] Mobile phone numbers are cleaned of spaces or symbols and saved with their country code (defaulting to Colombia +57).
- [ ] Security codes and numbers are cleaned of spaces before being verified.

---

## US01 - CUSTOMER REGISTRATION (STREAMLINED ONBOARDING & EMAIL VERIFICATION)

**As a** customer,  
**I want to** register on the platform in two fast steps with email verification (Email OTP),  
**So that** I can securely join digital queues with certified email reachability without mandatory upfront phone requirements.

### Acceptance Criteria

- [ ] **Access Control & Session Lifecycle**:
    - [ ] Authenticated users attempting to access the registration flow are redirected to the discovery catalog.
    - [ ] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [ ] In-progress registration data is temporarily retained and expires if not completed within 15 minutes.
    - [ ] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [ ] **Step 1: Account Information & Pre-check**:
    - [ ] First name and last name are required and must each contain between 2 and 60 characters.
    - [ ] All entered information (name and email) is automatically cleaned and formatted (**US-CORE-02**).
    - [ ] The email address is validated against standard email formatting rules.
    - [ ] Passwords must be between 8 and 64 characters long, cannot contain spaces, and must include an uppercase letter, a lowercase letter, a number, and a special character.
    - [ ] Password confirmation is required and must match the chosen password.
    - [ ] Customers must accept the Terms of Service and Privacy Policy before proceeding.
    - [ ] If the email address is already registered, registration is halted and the user is prompted to sign in instead.
    - [ ] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [ ] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [ ] For valid, unregistered details, the system dispatches a 6-digit verification code to the customer's email address.
    - [ ] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [ ] **Step 2: Email Verification Challenge & Account Finalization**:
    - [ ] Submitted verification codes are validated against the OTP service, rejecting invalid or expired entries.
    - [ ] Exceeding maximum verification attempts invalidates the active code and requires requesting a new one.
    - [ ] Users can edit their entered email address up to 3 times per registration before being required to restart.
    - [ ] Requesting a new code requires waiting for the cooldown period (60 seconds) to elapse, even if the email address is changed.
    - [ ] Once the cooldown period expires, users can request a new code to their email.
    - [ ] Upon successful verification, the system provisions the customer account with a verified email status (`email_verified_at = now()`) and dispatches a welcome email.
    - [ ] The customer is automatically authenticated and redirected to their intended destination, or to the main discovery catalog by default with a confirmation notice.
    - [ ] Temporary registration progress is cleared upon successful account creation.

---

## US01B - CUSTOMER REGISTRATION & AUTHENTICATION VIA GOOGLE OAUTH

**As a** customer,  
**I want to** register or log in using my Google account with one click,  
**So that** I can explore the platform and place pickup orders in seconds without creating and remembering passwords.

### Acceptance Criteria

- [ ] Customers who are already logged in cannot trigger the Google authentication flow and are redirected to their active dashboard or discovery catalog.
- [ ] A visible **"Continue with Google"** button is displayed on both the customer registration and login interfaces.
- [ ] Clicking the button redirects the customer to Google's sign-in screen to choose and authorize their account.
- [ ] Canceling or failing the Google sign-in process (including expired session state or connection errors) returns the customer to the previous screen with the message: _"No se pudo completar la autenticación con Google. Por favor, intenta de nuevo."_
- [ ] **Existing Account Access**:
    - [ ] If an account with the Google email address already exists, the customer is logged in immediately and the account is linked to Google for future one-click access.
    - [ ] An existing customer who registered with a password and links their Google account can subsequently sign in using either Google or their password.
- [ ] **New Account Registration**:
    - [ ] If no account exists with the Google email address, a new customer account is created using their Google profile details without requiring a password.
    - [ ] The email address is marked as verified automatically without requiring manual email confirmation.
    - [ ] If the Google profile does not provide an avatar photo or last name, account creation still succeeds without blocking the customer (avatar is optional, and missing last name defaults safely).
- [ ] **Destination & Navigation**:
    - [ ] Upon successful authentication, the customer is redirected to their originally intended destination, or to the discovery catalog by default.
- [ ] **Passwordless Account Protection**:
    - [ ] If a customer registered exclusively through Google attempts to sign in using manual credentials, the system displays: _"Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google."_

---

## US02 - BUSINESS REGISTRATION (STAGE 1: QUICK ONBOARDING)

**As a** business owner or manager,  
**I want to** register my business with basic contact information and select its industry category,  
**So that** I can access the platform and explore the management dashboard quickly.

### Acceptance Criteria

- [ ] **Access Control & Session Lifecycle**:
    - [ ] Authenticated business managers or system users attempting to access the business registration flow are redirected to their active dashboard.
    - [ ] Authenticated customers visiting the business registration flow are permitted to register a new business without affecting their customer session.
    - [ ] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [ ] In-progress registration data is temporarily retained and expires if not completed within 15 minutes.
    - [ ] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [ ] **Step 1: Business & Owner Information & Pre-check**:
    - [ ] Business name is required and must contain between 3 and 150 characters.
    - [ ] Business category is required and must match a supported industry type (e.g., Restaurant, Health & Beauty, Professional Services, Retail, Banking); invalid or manipulated options are rejected.
    - [ ] Physical location/address is required and limited to 255 characters.
    - [ ] Owner name fields provide separate inputs: owner first name and last name are required (between 2 and 60 characters each); middle name and second last name are optional (up to 60 characters each).
    - [ ] The entered mobile phone number is validated and formatted into standard international format, defaulting to Colombia (+57).
    - [ ] The entered email address is trimmed, normalized to lowercase, and validated against standard email formatting rules.
    - [ ] Passwords must be between 8 and 64 characters long, cannot contain spaces, and must include an uppercase letter, a lowercase letter, a number, and a special character.
    - [ ] Password confirmation is required and must match the chosen password.
    - [ ] Business owners must accept the Terms of Service and Privacy Policy before proceeding.
    - [ ] All entered business and owner details are automatically cleaned and formatted (**US-CORE-02**).
    - [ ] If the phone number is already registered to an existing business, registration is halted and the user is prompted to sign in instead.
    - [ ] If the email address is already registered to an existing business, registration is halted and the user is prompted to sign in instead.
    - [ ] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [ ] Submission buttons are disabled and display a loading state while processing to prevent double submissions.
    - [ ] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [ ] For valid, unregistered details, the system dispatches a 6-digit verification code to the mobile number prioritizing WhatsApp.
    - [ ] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [ ] **Step 2: Phone Verification Challenge & Account Provisioning**:
    - [ ] Submitted verification codes are validated against the OTP service (**US-CORE**), rejecting invalid or expired entries.
    - [ ] Exceeding maximum verification attempts (5 attempts) invalidates the active code and requires requesting a new one.
    - [ ] Users can edit their entered phone number up to 3 times per registration before being required to restart.
    - [ ] Submitting a new phone number during editing checks for uniqueness against existing businesses; if duplicate, the system rejects it without consuming an edit attempt.
    - [ ] Submitting an unchanged phone number during editing does not decrement the remaining edit attempts.
    - [ ] Requesting a new code requires waiting for the cooldown period (60 seconds) to elapse, even if the phone number is changed.
    - [ ] Once the cooldown period expires, users can request a new code via WhatsApp or choose SMS delivery.
    - [ ] Verification submission buttons are disabled with a loading indicator while the OTP is being evaluated.
    - [ ] Upon successful verification, the system transitions to a success confirmation state, provisions the business account with **"Pending Verification"** status and default person type as **"Persona Natural"** (updatable to Persona Jurídica in Stage 3), records the phone verification timestamp, and dispatches a welcome email (deferring email verification).
    - [ ] The business owner is automatically authenticated and redirected to the business dashboard in setup mode.
    - [ ] Temporary registration progress is cleared upon successful account creation.

---

## US03 - LOGIN

**As a** user (customer or business manager),  
**I want to** log in to the system using credentials or social login,  
**So that** I can access my account features and dashboard.

### Acceptance Criteria

- [ ] Authenticated users attempting to access the login page are redirected to their corresponding role dashboard or catalog.
- [ ] The system offers multiple authentication methods:
    - [ ] Standard credentials (Email or Phone number + Password) for customers and businesses.
    - [ ] Single-click social login (**"Continue with Google"**) for customers (**US01B**).
- [ ] For credential-based login:
    - [ ] The login form requests the user's identifier (email or mobile phone number) and password.
    - [ ] The password field allows toggling between hidden and visible text.
    - [ ] The login form includes an optional **"Remember me"** checkbox.
    - [ ] Both identifier and password fields are required; submitting empty values prompts the user to complete them.
    - [ ] The entered identifier (email or phone number) is automatically cleaned and formatted (**US-CORE-02**) before verifying credentials.
    - [ ] If the email, phone number, or password does not match an existing account, the system displays: _"Incorrect credentials. Please try again."_
    - [ ] If the account was registered exclusively via Google OAuth and has no password configured, the system displays: _"This account is linked to Google Sign-In. Please log in using Google."_
    - [ ] An unsuccessful login clears the password field but keeps the entered email or phone number.
    - [ ] Login attempts are limited to a maximum of 5 attempts per minute; exceeding this limit temporarily blocks further requests for 1 minute.
    - [ ] A successful login resets the failed attempts counter.
    - [ ] Submitting valid credentials logs the user in for a standard session of 2 hours of inactivity.
    - [ ] Selecting **"Remember me"** keeps the user logged in across browser sessions.
- [ ] Upon successful authentication (credentials or Google OAuth), the system redirects the user to their intended destination, or to their corresponding role dashboard or landing view by default.

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

- [ ] The system must prompt the business owner to select their legal entity category:
    - [ ] **Persona Natural** (individual proprietor / independent small business)
    - [ ] **Persona Jurídica** (registered company / SAS / corporation)
- [ ] For **Persona Jurídica (Empresa)**, the system requires the following legal documents (PDF or image format up to 10 MB each):
    - [ ] **Corporate Tax ID Document (RUT)**: Required, verifying company NIT and economic activity.
    - [ ] **Legal Representative ID Document**: Required, National ID (Cédula de Ciudadanía / CE / Passport).
    - [ ] **Corporate Bank Account Certificate**: Required, issued within the last 90 days matching the legal entity name.
    - [ ] **Commercial Chamber of Commerce / Operating License**: Optional, depending on business category.
- [ ] For **Persona Natural (Pequeño Negocio / Independiente)**, the system adapts requirements to prevent operational exclusion:
    - [ ] **National ID Document (Cédula de Ciudadanía / CE)**: Required, acting as primary legal tax identifier.
    - [ ] **Bank or Digital Account Certificate**: Required, personal savings/checking account or certified digital wallet (e.g., Nequi, Daviplata) in the owner's name.
    - [ ] **Personal Tax ID (RUT)**: Optional, for merchants who maintain formal tax registration.
- [ ] Each uploaded document must start in a **"Pending"** evaluation state.
- [ ] The system must request bank details for payout settlements:
    - [ ] Bank or platform name
    - [ ] Account type (Savings, Checking, or Electronic Deposit)
    - [ ] Account number
    - [ ] Account holder name
    - [ ] Account holder ID number (must match legal documents)
- [ ] The system must require at least **1 active service or product** created before enabling submission.
- [ ] Once all mandatory documents and banking requirements are completed according to the business person type, the business can click **"Submit for Verification"**.
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
