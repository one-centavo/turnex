# USER STORIES FOR TURNEX (GENERIC QUEUE PLATFORM)

> **Alignment:** This document reflects the behavior defined in `specs/features/001` through `014` (last reviewed 2026-11-05). Stories marked as implemented have all acceptance criteria checked; planned stories and drafts remain unchecked.

---

## US-CORE - ONE-TIME PASSWORD (OTP) GENERATION & VERIFICATION SERVICE

**Status:** Implemented — spec `001 · OTP Generation, Dispatch & Verification`.

**As a** system,  
**I want to** manage the lifecycle of temporary 6-digit security codes across communication channels,  
**So that** users can securely verify their contact methods with consistent rate limits and expiration rules.

### Acceptance Criteria

- [x] The system must generate a random 6-digit numeric security code formatted as an exact zero-padded string (`000000` to `999999`).
- [x] Contact targets must be normalized (RFC lowercase for email, E.164 for phone) before code generation and lookup.
- [x] Each security code must automatically expire 5 minutes after creation or immediately once successfully used.
- [x] The system must enforce a 1-minute cooldown period before allowing a new code request for the same contact target, reporting the remaining cooldown seconds when rejected.
- [x] Requesting a new security code must immediately invalidate any previously active code for that specific contact target.
- [x] If message delivery fails, the operation must be rolled back so the user is not locked into an unwarranted cooldown.
- [x] Input codes submitted for verification must be trimmed of whitespace before attempt evaluation.
- [x] A security code must permit a maximum of 5 incorrect verification attempts; upon the 5th failed attempt, the code must be immediately invalidated.
- [x] Expired codes must be rejected immediately without incrementing the failed attempt counter.
- [x] A code can only be validated once; subsequent verification attempts using the same code must be rejected.
- [x] The service must dispatch the code via the requested delivery channel (Email, SMS, or WhatsApp) based on the consuming workflow.

---

## US-CORE-02 - INPUT DATA SANITIZATION & NORMALIZATION SERVICE

**Status:** Implemented — cross-cutting service referenced by every feature spec.

**As a** system,  
**I want to** automatically clean and format any information entered by users,  
**So that** data remains tidy, consistent, and easy to match across the entire platform.

### Acceptance Criteria

- [x] Accidental spaces at the beginning and end of any text field are automatically removed.
- [x] Extra spaces between words are reduced to a single space.
- [x] Fields containing only spaces are treated as empty.
- [x] Email addresses are converted to lowercase.
- [x] Mobile phone numbers are cleaned of spaces or symbols and saved in E.164 format with their country code (defaulting to Colombia `+57`).
- [x] Security codes and numbers are cleaned of spaces before being verified.

---

## US01 - CUSTOMER REGISTRATION (STREAMLINED ONBOARDING & EMAIL VERIFICATION)

**Status:** Implemented — spec `002 · Customer Registration`.

**As a** customer,  
**I want to** register on the platform in two fast steps with email verification (Email OTP),  
**So that** I can securely join digital queues with certified email reachability without mandatory upfront phone requirements.

### Acceptance Criteria

- [x] **Access Control & Session Lifecycle**:
    - [x] Authenticated users attempting to access the registration flow are redirected to the discovery catalog.
    - [x] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [x] In-progress registration data is temporarily retained in an encrypted session and expires if not completed within 15 minutes.
    - [x] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [x] **Step 1: Account Information & Pre-check**:
    - [x] First name and last name are required and must each contain between 2 and 60 characters.
    - [x] All entered information (names and email) is automatically cleaned and formatted (**US-CORE-02**).
    - [x] The email address is trimmed, normalized to lowercase, and validated against standard RFC email formatting rules.
    - [x] Passwords must be between 8 and 64 characters long, cannot contain spaces, and must include an uppercase letter, a lowercase letter, a number, and a special character.
    - [x] Password confirmation is required and must match the chosen password.
    - [x] Customers must accept the Terms of Service and Privacy Policy before proceeding.
    - [x] If the email address is already registered, registration is halted and the user is prompted to sign in instead.
    - [x] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [x] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [x] For valid, unregistered details, the system dispatches a 6-digit verification code to the customer's email address.
    - [x] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [x] **Step 2: Email Verification Challenge & Account Finalization**:
    - [x] Submitted verification codes are validated against the OTP service (**US-CORE**), rejecting invalid or expired entries.
    - [x] Exceeding maximum verification attempts invalidates the active code and requires requesting a new one.
    - [x] Users can edit their entered email address up to 3 times per registration before being required to restart.
    - [x] Requesting a new code requires waiting for the cooldown period (60 seconds) to elapse, even if the email address is changed.
    - [x] Once the cooldown period expires, users can request a new code to their email.
    - [x] Upon successful verification, the system provisions the customer account with `email_verified_at = now()`, leaving `phone_number` and `phone_verified_at` as null, and dispatches a welcome email.
    - [x] The customer is automatically authenticated into the `customer` guard, the session ID is regenerated, and the user is redirected to their intended destination, or to the main discovery catalog by default, with a confirmation notice.
    - [x] Temporary registration progress is cleared upon successful account creation.

---

## US01B - CUSTOMER REGISTRATION & AUTHENTICATION VIA GOOGLE OAUTH

**Status:** Implemented — spec `004 · Google Auth`.

**As a** customer,  
**I want to** register or log in using my Google account with one click,  
**So that** I can explore the platform and place pickup orders in seconds without creating and remembering passwords.

### Acceptance Criteria

- [x] Customers who are already logged in cannot trigger the Google authentication flow and are redirected to their active dashboard or discovery catalog.
- [x] A visible **"Continue with Google"** button is displayed on both the customer registration and login interfaces.
- [x] Clicking the button redirects the customer to Google's sign-in screen to choose and authorize their account.
- [x] Canceling or failing the Google sign-in process (including expired session state or connection errors) returns the customer to the previous screen with the message: _"No se pudo completar la autenticación con Google. Por favor, intenta de nuevo."_
- [x] **Existing Account Access**:
    - [x] If an account with the Google email address already exists, the customer is logged in immediately and the account is linked to Google for future one-click access.
    - [x] An existing customer who registered with a password and links their Google account can subsequently sign in using either Google or their password.
- [x] **New Account Registration**:
    - [x] If no account exists with the Google email address, a new customer account is created using their Google profile details without requiring a password.
    - [x] The email address is marked as verified automatically without requiring manual email confirmation.
    - [x] If the Google profile does not provide an avatar photo or last name, account creation still succeeds without blocking the customer (avatar is optional, and missing last name defaults safely).
- [x] **Destination & Navigation**:
    - [x] Upon successful authentication, the customer is redirected to their originally intended destination, or to the discovery catalog by default.
- [x] **Passwordless Account Protection**:
    - [x] If a customer registered exclusively through Google attempts to sign in using manual credentials, the system displays: _"Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google."_

---

## US02 - BUSINESS REGISTRATION (STAGE 1: QUICK ONBOARDING & EMAIL VERIFICATION)

**Status:** Implemented — spec `005 · Business Registration`.

**As a** business owner or manager,  
**I want to** register my business with essential store and owner details via email verification (Email OTP),  
**So that** I can securely access the platform and explore the management dashboard quickly without upfront phone verification or SMS gateway hurdles.

### Acceptance Criteria

- [x] **Access Control & Session Lifecycle**:
    - [x] Authenticated business managers or system users attempting to access the business registration flow are redirected to their active dashboard.
    - [x] Authenticated customers visiting the business registration flow are permitted to register a new business without affecting their customer session.
    - [x] Accessing Step 2 directly without completing Step 1 data entry redirects the user to Step 1.
    - [x] In-progress registration data is temporarily retained in an encrypted session and expires if not completed within 15 minutes.
    - [x] Submitting any step after the 15-minute session has expired prompts the user and restarts the registration flow.

- [x] **Step 1: Business & Owner Information & Pre-check**:
    - [x] Business name is required and must contain between 3 and 150 characters.
    - [x] Business category is required and must match a supported industry type (e.g., Restaurant, Health & Beauty, Professional Services, Retail, Banking); invalid or manipulated options are rejected.
    - [x] Physical location/address is required and limited to 255 characters.
    - [x] Owner name fields provide separate inputs: owner first name and last name are required (between 2 and 60 characters each); middle name and second last name are optional (up to 60 characters each).
    - [x] The entered email address is trimmed, normalized to lowercase, and validated against standard email formatting rules.
    - [x] Passwords must be between 8 and 64 characters long, cannot contain spaces, and must include an uppercase letter, a lowercase letter, a number, and a special character.
    - [x] Password confirmation is required and must match the chosen password.
    - [x] Business owners must accept the Terms of Service and Privacy Policy before proceeding.
    - [x] All entered business and owner details are automatically cleaned and formatted (**US-CORE-02**).
    - [x] If the email address is already registered to an existing business, registration is halted and the user is prompted to sign in instead.
    - [x] Submitting invalid inputs displays clear feedback without resetting valid fields.
    - [x] Submission buttons are disabled and display a loading state while processing to prevent double submissions.
    - [x] Code dispatch requests are limited to a maximum of 5 attempts per minute per IP address, temporarily rejecting excessive traffic.
    - [x] For valid, unregistered details, the system dispatches a 6-digit verification code to the owner's email address.
    - [x] If code dispatch fails due to delivery provider errors, the user is notified immediately without locking them into an unwarranted cooldown.

- [x] **Step 2: Email Verification Challenge & Account Provisioning**:
    - [x] Submitted verification codes are validated against the OTP service (**US-CORE**), rejecting invalid or expired entries.
    - [x] Exceeding maximum verification attempts (5 attempts) invalidates the active code and requires requesting a new one.
    - [x] Users can edit their entered email address up to 3 times per registration before being required to restart.
    - [x] Submitting a new email address during editing checks for uniqueness against existing businesses; if duplicate, the system rejects it without consuming an edit attempt.
    - [x] Submitting an unchanged email address during editing does not decrement the remaining edit attempts.
    - [x] Requesting a new code requires waiting for the cooldown period (60 seconds) to elapse, even if the email address is changed.
    - [x] Once the cooldown period expires, users can request a new code to their email.
    - [x] Verification submission buttons are disabled with a loading indicator while the OTP is being evaluated.
    - [x] Upon successful verification, the system transitions to a success confirmation state, provisions the business account in **"Pending Verification"** status with default person type as **"Persona Natural"** (`PersonType::Natural`), records the email verification timestamp (`email_verified_at = now()`), leaves `phone_number` and `phone_verified_at` as null, and dispatches a welcome email.
    - [x] The business owner is automatically authenticated into the `business` guard and redirected to the business dashboard in setup mode.
    - [x] Temporary registration progress is cleared upon successful account creation.

---

## US03 - CUSTOMER LOGIN

**Status:** Implemented — spec `003 · Customer Login`.

**As a** customer,  
**I want to** log in with my email address and password,  
**So that** I can access my account, the discovery catalog, and my tickets.

### Acceptance Criteria

- [x] Customers who are already logged in cannot access the login page and are redirected to the discovery catalog.
- [x] The login form requests the customer's email address and password.
- [x] The password field allows toggling between hidden and visible text.
- [x] The login form includes an optional **"Remember me"** checkbox.
- [x] The login form includes a **"¿Olvidaste tu contraseña?"** link leading to the password recovery view.
- [x] Both email and password fields are required; submitting empty values prompts the customer to complete them.
- [x] The entered email address is automatically cleaned, normalized to lowercase, and trimmed (**US-CORE-02**) before verifying credentials.
- [x] If the email address or password does not match an existing account, the system displays: _"Credenciales incorrectas. Por favor, intenta de nuevo."_
- [x] If the account was registered exclusively via Google OAuth and has no password configured, the system displays: _"Esta cuenta está vinculada a Google Sign-In. Por favor, inicia sesión con Google."_
- [x] An unsuccessful login clears the password field but keeps the entered email address.
- [x] Login attempts are limited to a maximum of 5 attempts per minute; exceeding this limit temporarily blocks further requests for 1 minute.
- [x] A successful login resets the failed attempts counter.
- [x] Submitting valid credentials logs the customer in for a standard session of 2 hours of inactivity.
- [x] Selecting **"Remember me"** keeps the customer logged in across browser sessions.
- [x] Upon successful login, the customer is redirected to their intended destination, or to the discovery catalog by default.

---

## US03B - BUSINESS LOGIN

**Status:** Implemented — spec `006 · Business Login`.

**As a** business owner or operator,  
**I want to** log in to the merchant portal with my business credentials,  
**So that** I can access my operations dashboard, manage my catalog, and monitor incoming tickets.

### Acceptance Criteria

- [x] Authenticated business owners who access the login page are redirected to the business dashboard (`/business/dashboard`).
- [x] Authenticated customers who visit the business login page are allowed to log into their business account without terminating or interfering with their customer session.
- [x] The login form requests the merchant's email address and password.
- [x] The password field allows toggling between hidden and visible text.
- [x] The login form includes an optional **"Remember me"** checkbox.
- [x] The login form includes a **"¿Olvidaste tu contraseña?"** link leading to the password recovery view.
- [x] Both email and password fields are required; submitting empty values prompts the user with validation feedback.
- [x] The entered email address is automatically trimmed and normalized to lowercase (**US-CORE-02**) before credential verification.
- [x] If the email address or password does not match an existing business account, the system displays: _"Credenciales incorrectas. Por favor, intenta de nuevo."_
- [x] An unsuccessful login clears the password field but preserves the entered email address.
- [x] Login attempts are rate-limited to a maximum of 5 failed attempts per minute per email and IP address; exceeding this threshold locks further attempts for 1 minute.
- [x] A successful login clears the failed attempts rate limiter.
- [x] Submitting valid credentials authenticates the merchant into the `business` guard with a standard 2-hour inactivity session.
- [x] Selecting **"Remember me"** establishes a persistent session across browser restarts via `remember_token`.
- [x] Upon successful login, the merchant is redirected to their intended destination, or to `/business/dashboard` by default.

---

## US04 - CREATE SERVICE OR PRODUCT

**Status:** Implemented — spec `008 · Business Offerings`.

**As a** business manager,  
**I want to** create a service or product offered by my business,  
**So that** customers can select or purchase it when joining the queue.

### Acceptance Criteria

- [x] Unauthenticated users attempting to access the creation page are redirected to the business login screen.
- [x] The new product or service is strictly associated with the authenticated business.
- [x] The system must request the following data:
    - [x] Service / Product name
    - [x] Service category / Line
    - [x] Reference image
    - [x] Description
    - [x] Price (optional, or `0` for free/standard queue services)
    - [x] Estimated duration in minutes (optional)
- [x] All entered text fields are automatically cleaned and trimmed (**US-CORE-02**), treating entries containing only spaces as empty.
- [x] The **"Service name"** field is mandatory and must contain between 3 and 50 characters.
- [x] Selecting a category from the active, non-archived categories is required.
- [x] The **"Reference image"** field is optional; if uploaded, it must be a genuine image file (PNG, JPG, or SVG) up to 10 MB, rejecting disguised or unapproved file formats.
- [x] Uploaded images display an immediate live preview before submission, along with an option to remove the selected file.
- [x] The **"Description"** field is optional and limited to a maximum of 250 characters.
- [x] The **"Price"** field is optional, cannot be negative, cannot exceed `99,999,999.99`, and defaults to `0.00`.
- [x] The **"Estimated duration in minutes"** field is optional; if provided, it must be a whole number of at least 1 minute.
- [x] The submit button is disabled and displays a loading state while processing to prevent duplicate submissions.
- [x] Submitting empty required fields or invalid data displays clear error messages without clearing valid inputs.
- [x] Newly created services or products are automatically set to active by default.
- [x] Once saved, the system displays: _"Service created successfully."_, clears the form, and redirects the user to the catalog view.

---

## US04B - VIEW & FILTER SERVICES OR PRODUCTS

**Status:** Implemented — spec `009 · Listing and Filtering Business Offerings`.

**As a** business manager,  
**I want to** view, search, and filter the catalog of services and products registered by my business,  
**So that** I can easily inspect, organize, and manage my commercial offerings.

### Acceptance Criteria

- [x] Unauthenticated users attempting to access the catalog list page are redirected to the business login screen.
- [x] The system must display a paginated list of offerings strictly associated with the authenticated business, showing:
    - [x] Reference image (compact thumbnail preview or placeholder if none was uploaded)
    - [x] Offering name
    - [x] Category name
    - [x] Price (formatted as currency, displaying "Free" with subtle styling when price is `0.00`)
    - [x] Estimated duration in minutes (or indicated as "-" if not specified)
    - [x] Status indicator: active offerings display cleanly without badges, while inactive offerings display an "Inactive" status badge
- [x] The user can search offerings by name in real time using a search bar with input debouncing and sanitization (**US-CORE-02**), without a full page reload.
- [x] The user can filter offerings by category using a dropdown selector that only lists active, non-archived categories.
- [x] If an offering's assigned category is archived (soft-deleted), the table displays the category name gracefully without throwing errors.
- [x] The user can filter offerings by status (All, Active, Inactive). The **"Archived"** option is added by the offering lifecycle in **US05C**.
- [x] Modifying any filter (search query, category, or status) automatically resets pagination to page 1.
- [x] Active search terms, filters, sorting preferences, and pagination pages persist in the URL query string across page refreshes and browser history navigation.
- [x] The list allows sorting by offering name (A-Z, Z-A) and by creation date (newest first, oldest first), displaying a directional sort icon on the active column.
- [x] If no offerings have been created yet by the business, the system displays an empty state prompting the user to create their first offering with a direct link/button to the creation form.
- [x] If an active search or filter produces no matching results, the system displays: _"No offerings found matching your criteria."_ with a button to reset all filters.
- [x] The list never displays offerings belonging to other businesses (tenant isolation).

---

## US05 - EDIT SERVICE OR PRODUCT

**Status:** Implemented — spec `010 · Edit Business Offerings`.

**As a** business manager,  
**I want to** edit an existing service or product,  
**So that** I can keep my service catalog up to date.

### Acceptance Criteria

- [x] Unauthenticated users attempting to access the edit page are redirected to the login screen.
- [x] Businesses can only access and edit their own offerings; attempting to access offerings belonging to other businesses or non-existent items displays a not-found page.
- [x] The user can cancel editing via a cancel/back button, returning to the catalog list without saving any changes.
- [x] The system must display current details pre-filled in the edit form:
    - [x] Service / Product name
    - [x] Service category / Line
    - [x] Current image preview (or placeholder if none was uploaded)
    - [x] Description
    - [x] Price
    - [x] Estimated duration in minutes
    - [x] Service status (Active / Inactive)
- [x] Submitting the form without making any changes processes cleanly without errors and returns the user to the catalog list.
- [x] The **"Service name"** field is mandatory, must contain between 3 and 50 characters, and cannot consist solely of blank spaces.
- [x] Selecting a category is required. The selector only lists active categories; if the offering's previously assigned category was archived, its name remains displayed and can either be kept or replaced with an active one.
- [x] The **"Reference image"** field is optional:
    - [x] If a new image is selected, it must be an image file (PNG, JPG, or SVG) up to 10 MB and show an immediate preview.
    - [x] Selecting an invalid file format or a file exceeding 10 MB displays an immediate error and preserves the previously saved image.
    - [x] If a new image was temporarily selected, the user can discard the temporary upload to restore the previously saved image preview.
    - [x] The user can explicitly remove the existing image without uploading a replacement; saving the form removes the image and resets the offering to the default placeholder.
    - [x] If no new image is selected and the existing image is not removed, the existing image is kept intact upon saving.
- [x] The **"Description"** field is optional and cannot exceed 250 characters.
- [x] The **"Price"** field is optional, cannot be negative, cannot exceed `99,999,999.99`, supports up to two decimal places, and defaults to `0.00` if cleared.
- [x] The **"Estimated duration in minutes"** field is optional; if provided, it must be a whole number of at least 1 minute, and clearing the field removes the estimated duration upon saving.
- [x] The system must allow changing the service status between active and inactive at any time.
- [x] The save button shows a loading state and cannot be clicked repeatedly while processing.
- [x] Submitting invalid or missing required data displays clear error messages without clearing what the user already typed.
- [x] Saving valid changes updates the offering, displays: _"Service updated successfully."_, and returns the user to the catalog list reflecting the changes.

---

## US05C - ARCHIVE & DELETE SERVICE OR PRODUCT

**Status:** Implemented — spec `011 · Archive and Delete Business Offerings`.

**As a** business manager,  
**I want to** archive outdated services and permanently delete unneeded offerings,  
**So that** I can keep my commercial catalog clean and organized without losing past activity records.

### Acceptance Criteria

- [x] **Archive Offering**:
    - [x] Unauthenticated users attempting to archive an offering are redirected to the business login screen.
    - [x] Business managers can only archive offerings belonging to their own business; attempting to access offerings of other businesses displays a not-found page.
    - [x] The catalog table displays an **"Archive"** action button for each active or inactive offering.
    - [x] Clicking **"Archive"** opens a confirmation dialog warning that the offering will be removed from the active catalog and customer storefront while past records remain safe.
    - [x] Canceling the confirmation dialog closes the modal and leaves the offering unchanged.
    - [x] Confirming the archive action hides the offering from the active catalog and public discovery, marks it as archived, displays: _"Service archived successfully."_, and immediately removes the item from the default catalog view.

- [x] **Archived Offerings View & Status Filtering**:
    - [x] The status filter selector in the offerings list includes an **"Archived"** option alongside All, Active, and Inactive.
    - [x] Filtering by **"Archived"** exclusively displays archived offerings belonging to the authenticated business.
    - [x] Modifying the status filter to or from "Archived" resets table pagination to page 1.
    - [x] Each archived offering row displays an **"Archived"** status badge and provides **"Restore"** and **"Delete Permanently"** actions.
    - [x] If no archived offerings exist, the system displays: _"No archived offerings found."_

- [x] **Restore Offering**:
    - [x] The system provides a **"Restore"** action for each archived offering.
    - [x] Clicking **"Restore"** opens a confirmation dialog.
    - [x] Canceling the dialog dismisses the prompt without changes.
    - [x] Upon confirmation, the offering is restored to the active catalog with all its previous information intact, displays: _"Service restored successfully."_, and is removed from the archived list.
    - [x] The restored offering immediately reappears in the active catalog list according to its status.

- [x] **Permanent Deletion**:
    - [x] The **"Delete Permanently"** action is strictly available for archived offerings and cannot be triggered from the active catalog view.
    - [x] Before executing permanent deletion, the system verifies whether the offering has any past queue tickets or customer orders.
    - [x] If historical queue tickets or orders exist, the system prevents deletion and displays: _"This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived."_
    - [x] If no historical activity is linked, the system displays an explicit confirmation dialog warning that this action is irreversible and will permanently delete the service and its stored images.
    - [x] Upon confirming permanent deletion, the offering and its uploaded image are completely removed from the platform, and the system displays: _"Service permanently deleted."_

- [x] **Access Control & Business Privacy**:
    - [x] Unauthenticated users attempting to archive, restore, or delete offerings are redirected to the login screen.
    - [x] Business managers can only view, archive, restore, or delete offerings belonging to their own business; attempting to access offerings of other businesses displays a not-found page.

---

## US14 - PUBLIC BUSINESS STOREFRONT

**Status:** Implemented — spec `012 · Public Business Storefront`.

**As a** visitor or customer,  
**I want to** browse a business's public storefront and catalog without creating an account,  
**So that** I can discover available products and services before joining its queue.

### Acceptance Criteria

- [x] Any visitor can access an approved business's storefront using a friendly public link (e.g., `/b/business-name`) without logging in or creating an account.
- [x] If a visitor enters a link for a business that does not exist, the system displays a clear page indicating that the business was not found.
- [x] Storefront link resolution is case-insensitive.
- [x] If multiple businesses share the same name, the system ensures unique link identifiers without collisions.
- [x] Only businesses with an approved verification status (`APPROVED`) are publicly accessible to general visitors.
- [x] Visiting an unapproved business displays an informative message indicating that the business is under review or not yet active.
- [x] An authenticated business owner can view their own storefront in preview mode regardless of approval status, but cannot preview unapproved storefronts belonging to other businesses.
- [x] The storefront displays the business's commercial name, industry category, physical address, and description if configured.
- [x] The storefront displays the business's logo and cover photo, or fallback visuals if not uploaded.
- [x] The storefront is accessible and readable from both mobile and desktop devices.
- [x] The storefront strictly displays active offerings belonging to the visited business, excluding inactive, archived, or other businesses' offerings.
- [x] Each offering displays its reference image if available, name, category, formatted price (displaying "Free" if the price is `0.00`), estimated duration in minutes if set, and description if provided.
- [x] Visitors can filter offerings by category, including an option to view all categories.
- [x] The category filter only lists categories that currently have active offerings in the visited business.
- [x] Visitors can search offerings by name in real time.
- [x] Search input automatically trims leading and trailing whitespace, ignores searches containing only spaces, and safely handles special characters.
- [x] If an active search or category filter yields no matches, the system displays: _"No offerings found matching your search."_ along with an option to reset filters.
- [x] If a business has no active offerings published, the storefront displays: _"This business does not have available offerings yet."_

---

## US15 - VIRTUAL QUEUE & DIGITAL TICKET

**Status:** Implemented — spec `013 · Virtual Queue & Digital Ticket`.

**As a** customer,  
**I want to** select offerings, join a business's virtual queue, and track my position in real time,  
**So that** I can wait productively without standing in a physical line.

### Acceptance Criteria

- [x] Customers can select active products or services from the storefront catalog to prepare their turn.
- [x] Customers can adjust the quantity of selected items or remove items before confirming their turn.
- [x] The turn summary calculates and displays the item count and total amount in real time.
- [x] Submitting a turn requires at least one selected product or service.
- [x] Joining a queue requires an authenticated customer account; unauthenticated visitors attempting to confirm a turn are prompted to sign in or register before the ticket is created.
- [x] Authenticated customers confirm their turn directly using their verified profile, ensuring fraud prevention and venue accountability.
- [x] Upon confirmation, the system creates a digital ticket with a unique, human-readable identifier (e.g., `TK-014`) resetting daily, and sets its initial status to waiting.
- [x] The customer is redirected to a live ticket tracking page accessible via the ticket's unique code.
- [x] The ticket tracking page displays the business commercial name, ticket identifier, requested products or services, current status, number of turns ahead in line, and estimated wait time.
- [x] The ticket tracking page automatically refreshes queue status and position periodically without requiring manual page reloads.
- [x] When the merchant calls the ticket, the tracking page immediately updates to notify the customer that it is their turn to approach the service area.
- [x] Customers can voluntarily cancel their active ticket before service begins; confirming cancellation marks the ticket as cancelled and removes it from the active waiting line.
- [x] A customer cannot hold more than one active turn (waiting, called, or in service) in the same business at the same time.
- [x] If a customer attempts to request a new turn in a business where they already hold an active ticket, the system informs them and directs them to their existing active ticket.
- [x] A customer can hold active tickets in different businesses simultaneously without interference.
- [x] Visiting a ticket link with an invalid or non-existent ticket code displays a clear page indicating that the ticket was not found.
- [x] Inactive, completed, or cancelled tickets display their final status and prevent duplicate cancellation or turn reactivation.

---

## US16 - CUSTOMER TICKETS HUB

**Status:** Draft — spec `014 · Customer Tickets Hub` (in progress).

**As a** customer,  
**I want to** view and manage all my virtual queue tickets from a single centralized area,  
**So that** I can monitor concurrent turns across businesses and review my visit history without relying on saved links.

### Acceptance Criteria

- [ ] Customers must be authenticated to access their personal tickets hub; unauthenticated visitors are redirected to sign in first.
- [ ] Upon entering the tickets hub, the system defaults directly to the active turns section to prioritize immediate waiting information.
- [ ] Active tickets are ordered by urgency: tickets requiring immediate customer action (called or ready for pickup) appear first, followed by pending tickets ordered by nearest queue proximity.
- [ ] Each active ticket clearly displays the business identity, assigned ticket identifier, current waiting status, the number of people ahead in line, and the purchase total.
- [ ] Active ticket statuses, line positions, and urgency alerts update periodically without requiring manual page reloads.
- [ ] When a business calls the customer's ticket, the system presents an immediate, high-priority visual notice alerting them to approach the service area.
- [ ] When an active ticket is postponed due to initial absence, the ticket communicates that it was skipped and directs the customer to present themselves to venue staff.
- [ ] When an active ticket transitions to completed or cancelled while being viewed, the system displays a clear resolution notice with an acknowledgment action before moving the ticket to history.
- [ ] Customers can inspect the itemized products or services and quantities of any active ticket on demand without leaving the hub.
- [ ] Customers can navigate directly from any active ticket to its dedicated live tracking view.
- [ ] Concurrent active tickets held across different businesses update independently.
- [ ] Customers can view a paginated history section displaying past completed, cancelled, or unattended tickets sorted chronologically from newest to oldest.
- [ ] Historical ticket records display accurate venue details and final statuses even if the venue or offerings were subsequently modified or archived.
- [ ] Clear and welcoming guidance is shown when the customer has no active tickets or no past history.

---

## US06 - BUSINESS PROFILE & BRANDING SETUP (STAGE 2)

**Status:** Planned — no spec yet (referenced by spec `005` as Stage 2).

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

**Status:** Planned — no spec yet (referenced by spec `005` as Stage 3).

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

**Status:** Planned — no spec yet (referenced by spec `005`).

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

---

## US09 - ADMIN LOGIN

**Status:** Planned — no spec yet (administrative portal).

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

**Status:** Planned SuperAdmin UI — the underlying categories schema, model, factory, and seeder are implemented in spec `007 · Service Categories`.

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

**Status:** Planned SuperAdmin UI — soft deletes, active scope, factory states, and seeder are implemented in spec `007 · Service Categories`.

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

**Status:** Planned SuperAdmin UI — no management endpoint in spec `007 · Service Categories`.

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

**Status:** Planned SuperAdmin UI — the soft-delete persistence behavior is implemented in spec `007 · Service Categories`.

**As a** system administrator,  
**I want to** delete an unused service category,  
**So that** outdated categories are archived without losing system data integrity.

### Acceptance Criteria

- [ ] The system must provide a delete action next to each category in the list.
- [ ] Before executing the deletion, the system must verify whether there are any existing services or products associated with the category.
- [ ] If at least one active or inactive service or product is linked to the category, the system must prevent deletion and display: _"This category cannot be deleted because it is currently assigned to one or more services."_
- [ ] If no services or products are linked, the system must prompt for confirmation before proceeding.
- [ ] Upon confirmation, the system must perform a soft delete (marking the category as inactive and setting the archive date and time) without physically removing the record from the database.
- [ ] Once archived, the system must display a notification: _"Category deleted successfully."_
