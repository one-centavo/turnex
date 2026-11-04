# 011 · Archive and Delete Business Offerings

**Status:** Done

## What it does

This feature allows business owners and managers to manage the lifecycle of commercial offerings (products and services) by archiving outdated items and permanently deleting unneeded ones. From the offerings management table, merchants can click an **"Archive"** action on any offering, which prompts a confirmation dialog and archives the item, hiding it from the active catalog and customer storefront while keeping past activity records safe.

From the catalog list, merchants can use the status filter to view **"Archived"** items. In this view, each archived offering provides two distinct actions:
1. **Restore:** Returns the offering to the active catalog with its original settings intact upon confirmation.
2. **Delete Permanently:** Checks whether the offering has any recorded activity (such as past queue tickets or customer orders). If historical activity exists, permanent deletion is prevented and the merchant is informed that the item must remain archived. If no past activity exists, the merchant can permanently remove the offering and its image after confirming a safeguard dialog.

## Why

Currently, merchants can create, view, and edit offerings, but they cannot remove outdated, seasonal, or erroneous items from their day-to-day dashboard. Using the active/inactive toggle only pauses an offering, leaving the management table cluttered over time. Furthermore, deleting an offering directly and permanently without safeguards introduces risks of accidental data loss and breaks historical records for past customer queues and orders.

A two-stage lifecycle (**Archive** $\rightarrow$ **Restore** / **Delete Permanently**) gives merchants full control over their catalog, eliminates clutter, protects against accidental deletion, and guarantees historical consistency.

## Acceptance Criteria

### 1. Archive Offering
- [x] Unauthenticated users attempting to archive an offering are redirected to the business login screen.
- [x] Business managers can only archive offerings belonging to their own business; attempting to access offerings of other businesses displays a not-found page.
- [x] The catalog table displays an **"Archive"** action button for each active or inactive offering.
- [x] Clicking **"Archive"** opens a confirmation dialog warning that the offering will be removed from the active catalog and customer storefront while past records remain safe.
- [x] Canceling the confirmation dialog closes the modal and leaves the offering unchanged.
- [x] Confirming the archive action hides the offering from the active catalog, marks it as archived, displays a confirmation notification (_"Service archived successfully."_), and immediately removes the item from the default catalog view.

### 2. View & Filter Archived Offerings
- [x] The status filter dropdown on the offerings catalog list includes an **"Archived"** option alongside All, Active, and Inactive.
- [x] Selecting **"Archived"** filters the table to display exclusively archived offerings belonging to the authenticated business.
- [x] Modifying the status filter to or from "Archived" resets table pagination to page 1.
- [x] Each archived offering row clearly displays an **"Archived"** badge and provides **"Restore"** and **"Delete Permanently"** action buttons.
- [x] If no archived offerings exist, the table displays an empty state: _"No archived offerings found."_

### 3. Restore Offering
- [x] Clicking **"Restore"** on an archived offering opens a confirmation dialog.
- [x] Canceling the dialog dismisses the prompt without changes.
- [x] Confirming restoration returns the offering to the active catalog with all its previous details intact, displays a confirmation notification (_"Service restored successfully."_), and removes the item from the archived list.
- [x] The restored offering immediately reappears in the active catalog list according to its status.

### 4. Delete Permanently
- [x] The **"Delete Permanently"** action is strictly available for archived offerings and cannot be triggered from the active catalog view.
- [x] Attempting to permanently delete an offering verifies whether it has past queue tickets or customer orders.
- [x] If historical activity exists, permanent deletion is blocked, and the system displays an alert message: _"This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived."_
- [x] If no historical activity is linked, the system presents an explicit confirmation dialog warning that this action is irreversible and will permanently delete the service and its stored images.
- [x] Confirming permanent deletion removes the offering and its stored reference image from the platform, and displays a confirmation notification: _"Service permanently deleted."_

## Out of Scope

- Bulk archiving, restoring, or permanent deletion of multiple offerings simultaneously.
- Automatic scheduled purging of archived offerings after a retention window (e.g., auto-delete after 30 days).
- Customer-facing archived indicators (archived offerings are completely hidden from customer discovery).
