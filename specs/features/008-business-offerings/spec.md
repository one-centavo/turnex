# 008 · Business Offerings

**Status:** Done

## What it does

This feature allows business owners and managers to create commercial offerings (products and services) to be offered to customers. Merchants initiate this action from the catalog management area via a creation button. The form requests the offering's name, service category, reference image, description, price, and estimated duration in minutes. Upon successful submission, the system displays an immediate confirmation notification confirming the offering was created and sets its availability to active by default.

## Why

Business owners need the ability to define and publish their commercial catalog of products and services so prospective customers can discover available items, select offerings, and place orders when joining virtual queues. Without offerings, merchant storefronts remain empty and non-functional.

Furthermore, establishing active commercial offerings is a mandatory operational prerequisite under platform compliance rules, requiring at least one registered service or product before a business can submit legal and banking documentation for administrative verification.

## Acceptance Criteria

- [x] Unauthenticated users attempting to access the creation page are redirected to the business login screen.
- [x] The new product or service is strictly associated with the authenticated business.
- [x] All entered text fields are automatically cleaned and trimmed (US-CORE-02), treating entries containing only spaces as empty.
- [x] The name is required and must be between 3 and 50 characters long.
- [x] Selecting a category from the active, non-archived categories is required.
- [x] Adding a reference image is optional; if uploaded, it must be a genuine image file (PNG, JPG, or SVG) up to 10 MB, rejecting disguised or unapproved file formats.
- [x] Uploaded images display an immediate live preview before submission, along with an option to remove the selected file.
- [x] Adding a description is optional and limited to a maximum of 250 characters.
- [x] The price is optional; it cannot be negative, cannot exceed 99,999,999.99, and defaults to 0.00 for free or standard services.
- [x] The estimated duration is optional; if provided, it must be a whole number of at least 1 minute.
- [x] The submit button is disabled with a loading state while processing to prevent duplicate submissions.
- [x] Submitting empty required fields or invalid data displays clear error messages without clearing valid inputs.
- [x] Newly created products or services are automatically set to active by default.
- [x] Once saved, the system displays a confirmation message: _"Service created successfully."_, clears the form, and redirects the user to the catalog view.

## Out of Scope

- Product and service listing, search, and filtering table (deferred to a subsequent catalog management feature).
- Editing or updating existing offerings (US05, handled in a dedicated feature).
- Customer-facing showcase and purchasing flow (handled in customer-facing catalog features).
