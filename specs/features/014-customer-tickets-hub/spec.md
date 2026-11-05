# 014 · Customer Tickets Hub

**Status:** Complete

## What it does

This feature allows authenticated customers to view, monitor, and manage all their virtual queue tickets from a single, centralized area.

Customers can access their personal tickets hub from anywhere in the application. Upon opening this section, customers are immediately presented with their active waiting lines. If a customer holds multiple concurrent turns across different businesses (for example, waiting for food while in line at a barbershop), each ticket is tracked independently in real time without requiring manual page reloads.

Active tickets are prioritized dynamically based on urgency: tickets that require immediate action (such as tickets that have been called to the counter or are ready for pickup) are surfaced first, followed by pending tickets organized by queue proximity. For each active turn, the customer can see the establishment name, ticket number, current line status, the count of people ahead, and the purchase total. Customers can also inspect the itemized list of requested items and quantities on demand without navigating away, or open the full dedicated tracking view if preferred.

If a customer's turn was skipped due to temporary absence when called, the ticket clearly indicates that the turn was postponed and advises them to approach staff. If an establishment marks a ticket as completed or cancelled while the customer is viewing the screen, the system provides clear visual confirmation of the final resolution with an acknowledgment option rather than abruptly disappearing from the view.

Furthermore, customers can switch to a history section to review their past visits, showing whether previous tickets were completed, cancelled, or marked as unattended, with chronological paging. If an establishment was archived or updated after a visit, historical records remain legible. When customers have no active tickets or past visits, clear guidance helps them discover local establishments.

## Why

Customers who take turns at multiple venues or navigate away from an individual ticket link lack a central place to monitor their waiting status. A unified ticket hub gives customers instant visibility over all active lines, prioritizes urgent counter calls so turns are never missed, provides reassurance when tickets reach resolution, and keeps an organized history of past patronage without relying on saved links or browser tabs.

## Acceptance Criteria

- [x] Customers must be authenticated to access their personal tickets hub; unauthenticated visitors are redirected to sign in first.
- [x] Upon entering the tickets hub, the system defaults directly to the active turns section to prioritize immediate waiting information.
- [x] Active tickets are ordered by urgency: tickets requiring immediate customer action (called or ready for pickup) appear first, followed by pending tickets ordered by nearest queue proximity.
- [x] Each active ticket clearly displays the business identity, assigned ticket identifier, current waiting status, the number of people ahead in line, and the purchase total.
- [x] Active ticket statuses, line positions, and urgency alerts update periodically without requiring manual page reloads.
- [x] When a business calls the customer's ticket, the system presents an immediate, high-priority visual notice alerting them to approach the service area.
- [x] When an active ticket is postponed due to initial absence, the ticket communicates that it was skipped and directs the customer to present themselves to venue staff.
- [x] When an active ticket transitions to completed or cancelled while being viewed, the system displays a clear resolution notice with an acknowledgment action before moving the ticket to history.
- [x] Customers can inspect the itemized products or services and quantities of any active ticket on demand without leaving the hub.
- [x] Customers can navigate directly from any active ticket to its dedicated live tracking view.
- [x] Concurrent active tickets held across different businesses update independently.
- [x] Customers can view a paginated history section displaying past completed, cancelled, or unattended tickets sorted chronologically from newest to oldest.
- [x] Historical ticket records display accurate venue details and final statuses even if the venue or offerings were subsequently modified or archived.
- [x] Clear and welcoming guidance is shown when the customer has no active tickets or no past history.

## Out of Scope

- Exploring or searching the general business directory (handled in Feature 015).
- Managing personal profile details, account credentials, or security settings (handled in Feature 016).
- Automated SMS, email, or WhatsApp push notifications.
