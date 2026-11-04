# 012 · Public Business Storefront

**Status:** Done

## What it does

This feature introduces a public, responsive commercial storefront for registered businesses on TURNEX, accessible through a friendly URL (`/b/{slug}`) without requiring customer authentication. Visitors can view the business's identity (commercial name, category, physical address, description, logo, and cover photo) and browse its active catalog of products and services. Customers can filter offerings by category, search by name in real time, and inspect prices and estimated service durations.

To protect commercial legitimacy, storefronts are publicly accessible only when the business is verified and approved. If an unapproved business is visited, general visitors see an informative notice stating that the business is not yet available, while the authenticated business owner can access a dedicated preview mode to inspect their setup.

## Why

Merchants can configure their catalog internally, but customers currently have no public gateway to discover offerings, inspect venue details, or browse products and services. The public storefront serves as the primary digital window for customers visiting a venue or accessing a shared link, providing self-service and eliminating physical counter congestion.

## Acceptance Criteria

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
- [x] Each offering displays its reference image if available, name, category, formatted price (displaying "Free" if the price is 0.00), estimated duration in minutes if set, and description if provided.
- [x] Visitors can filter offerings by category, including an option to view all categories.
- [x] The category filter only lists categories that currently have active offerings in the visited business.
- [x] Visitors can search offerings by name in real time.
- [x] Search input automatically trims leading and trailing whitespace, ignores searches containing only spaces, and safely handles special characters.
- [x] If an active search or category filter yields no matches, the system displays: _"No offerings found matching your search."_ along with an option to reset filters.
- [x] If a business has no active offerings published, the storefront displays an informative message: _"This business does not have available offerings yet."_

## Out of Scope

- Queue ticket selection, order assembly, ticket code generation, and real-time waiting position tracking (handled in Feature 013).
- Online payment processing and multi-tender transactions.
- Customer reviews, ratings, or public comments.
