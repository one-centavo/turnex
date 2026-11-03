# 010 · Edit Business Offerings

**Status:** Complete

## What it does

This feature allows business owners and managers to update existing commercial offerings (products and services) to keep their catalog accurate and correct mistakes. From the catalog management table, merchants initiate the action by clicking an edit button linked to a specific offering. This action opens an edit form pre-filled with the offering's current data—including its name, category, reference image preview, description, price, estimated duration, and active status. Upon submitting valid modifications, the system saves the changes, displays an immediate confirmation notification (_"Service updated successfully."_), and redirects the user back to the catalog view reflecting the updated information.

## Why

Currently, businesses can create and view offerings, but they cannot edit them. This feature allows merchants to update details (such as prices, images, and active status) and fix mistakes without recreating items. It is a priority because keeping catalog information accurate is essential before exposing products and services to customers.

## Acceptance Criteria

- [x] Unauthenticated users attempting to access the edit page are redirected to the login screen.
- [x] Businesses can only access and edit their own offerings; attempting to access offerings belonging to other businesses or non-existent items displays a not-found page.
- [x] The user can cancel editing via a cancel/back button, returning to the catalog list without saving any changes.
- [x] The system displays the edit form pre-filled with the offering's current information:
    - [x] Offering name
    - [x] Category
    - [x] Current image preview (or placeholder if none was uploaded)
    - [x] Description
    - [x] Price
    - [x] Estimated duration
    - [x] Status (Active / Inactive)
- [x] Submitting the form without making any changes processes cleanly without errors and returns the user to the catalog list.
- [x] The name is required, must be between 3 and 50 characters, and cannot consist solely of blank spaces.
- [x] Selecting a category is required. The selector only lists active categories; if the offering's previously assigned category was archived, its name remains displayed and can either be kept or replaced with an active one.
- [x] The reference image is optional:
    - [x] If a new image is selected, it must be an image file (PNG, JPG, or SVG) up to 10 MB and show an immediate preview.
    - [x] Selecting an invalid file format or a file exceeding 10 MB displays an immediate error and preserves the previously saved image.
    - [x] If a new image was temporarily selected, the user can discard the temporary upload to restore the previously saved image preview.
    - [x] The user can explicitly remove the existing image without uploading a replacement; saving the form removes the image and resets the offering to the default placeholder.
    - [x] If no new image is selected and the existing image is not removed, the existing image is kept intact upon saving.
- [x] The description is optional and cannot exceed 250 characters.
- [x] The price is optional, cannot be negative, cannot exceed 99,999,999.99, supports up to two decimal places, and defaults to 0.00 if cleared.
- [x] The estimated duration is optional; if provided, it must be a whole number of at least 1 minute, and clearing the field removes the estimated duration upon saving.
- [x] The user can toggle the offering status between Active and Inactive at any time.
- [x] The save button shows a loading state and cannot be clicked repeatedly while processing.
- [x] Submitting invalid or missing required data displays clear error messages without clearing what the user already typed.
- [x] Saving valid changes updates the offering, displays a confirmation message: _"Service updated successfully."_, and returns the user to the catalog list reflecting the changes.

## Out of Scope

- Deleting or archiving offerings (deferred to a dedicated lifecycle feature).
- Bulk editing of multiple offerings at once.
- Customer-facing public catalog view and ordering flow (handled in storefront features).
