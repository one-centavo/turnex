# 009 · Listing and Filtering Business Offerings

**Status:** Done

## What it does

Listing and filtering offerings allows businesses to organize their products and services. Businesses access the offerings catalog and view a table; here, businesses can search and filter products. Then, the table returns a new list with the filters applied.

## Why

Currently, businesses can create offerings, but they cannot view or filter them. With this feature, businesses can view all their offerings and filter them by parameters. It is a priority because without this, they cannot access the created offerings.

## Acceptance Criteria

- [x] Unauthenticated users attempting to access the catalog list page are redirected to the business login screen.
- [x] The catalog table strictly displays offerings associated with the authenticated business (tenant isolation).
- [x] The system displays a paginated list of offerings showing:
  - [x] Reference image (compact thumbnail preview or placeholder if none was uploaded, integrated into the offering column)
  - [x] Offering name
  - [x] Category name
  - [x] Price (formatted as currency, displaying "Free" with subtle styling when price is 0.00)
  - [x] Estimated duration in minutes (or indicated as "-" if not specified)
  - [x] Status indicator: active offerings display cleanly without badges, while inactive offerings display an "Inactive" status badge to clearly highlight exceptions
- [x] The user can search offerings by name in real time using a search bar with input debouncing and sanitization (US-CORE-02), without a full page reload.
- [x] The user can filter offerings by category using a dropdown selector that only lists active, non-archived categories.
- [x] If an offering's assigned category is archived (soft-deleted), the table displays the category name gracefully without throwing errors.
- [x] The user can filter offerings by status (All, Active, Inactive).
- [x] Modifying any filter (search query, category, or status) automatically resets pagination to page 1.
- [x] Active search terms, filters, sorting preferences, and pagination pages persist in the URL query string across page refreshes and browser history navigation.
- [x] The list allows sorting by offering name (A-Z, Z-A) and by creation date (newest first, oldest first), displaying a directional sort icon on the active column.
- [x] If no offerings have been created yet by the business, an empty state is displayed prompting the user to create their first offering with a direct link/button to the creation form.
- [x] If an active search query or filter yields no results, the system displays: _"No offerings found matching your criteria."_ with a button to reset all filters.

## Out of Scope

- Editing or updating existing offerings (US05, handled in a dedicated feature).
- Deleting or archiving offerings (deferred to a dedicated lifecycle feature).
- Customer-facing public catalog view and ordering flow (handled in storefront features).
