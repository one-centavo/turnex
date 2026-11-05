# 013 · Virtual Queue & Digital Ticket

**Status:** Complete

## What it does

This feature allows customers to join a business's virtual queue, obtain a digital ticket, and track their position in real time until they are called. From a business's public storefront, customers can select one or more products or services into their turn summary, adjust quantities, and confirm their turn request.

To prevent fraudulent queue inflation and preserve venue security, customers must be authenticated with a verified customer account (via credentials or single-click Google Sign-In) to join a virtual queue. If an unauthenticated visitor attempts to confirm a turn, the system prompts them to sign in or register before issuing the ticket. Upon confirmation, the system issues a digital ticket with a unique human-readable code (e.g., `TK-014`) resetting daily. The customer is directed to a live ticket tracking page that continuously updates their current queue status, the number of turns ahead of them, and estimated wait times without manual reloads. If a customer changes their mind, they can cancel their active turn from the ticket tracking screen.

To maintain operational integrity, a customer is permitted only one active turn at a time within the same business. However, customers are free to join queues at different businesses concurrently.

## Why

Physical lines and crowded waiting rooms create customer frustration and operational counter friction. Digital queueing transforms the waiting experience by empowering customers to use their waiting time productively around the commercial venue or neighborhood while monitoring their exact position from their personal device. For merchants, it organizes incoming customer flow systematically, provides reliable visibility into waiting demand, and reduces lobby congestion.

## Acceptance Criteria

- [x] Customers can select active products or services from the storefront catalog to prepare their turn.
- [x] Customers can adjust the quantity of selected items or remove items before confirming their turn.
- [x] The turn summary calculates and displays the item count and total amount in real time.
- [x] Submitting a turn requires at least one selected product or service.
- [x] Joining a queue requires an authenticated customer account; unauthenticated visitors attempting to confirm a turn are prompted to sign in or register before the ticket is created.
- [x] Authenticated customers confirm their turn directly using their verified profile, ensuring fraud prevention and venue accountability.
- [x] Upon confirmation, the system creates a digital ticket with a unique, human-readable identifier (e.g., `TK-014`) and sets its initial status to waiting.
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

## Out of Scope

- Merchant-side ticket calling, desk assignment, and in-service flow (handled in Feature 014).
- Public waiting room television display boards (handled in Feature 015).
- Payment gateway processing and upfront digital transactions.
- Automated SMS or WhatsApp push dispatches on ticket status changes.

