# 013 · Virtual Queue & Digital Ticket

**Status:** In Progress

## What it does

This feature allows customers to join a business's virtual queue, obtain a digital ticket, and track their position in real time until they are called. From a business's public storefront, customers can select one or more products or services into their turn summary, adjust quantities, and confirm their turn request.

Guests and unauthenticated visitors can provide their name and contact information (phone number or email) to join the queue, while logged-in customers are linked automatically. Upon submission, the system issues a digital ticket with a unique human-readable code (e.g., `TK-014`). The customer is directed to a live ticket tracking page that continuously updates their current queue status, the number of turns ahead of them, and estimated wait times without manual reloads. If a customer changes their mind, they can cancel their active turn from the ticket tracking screen.

To maintain operational integrity, a customer is permitted only one active turn at a time within the same business. However, customers are free to join queues at different businesses concurrently.

## Why

Physical lines and crowded waiting rooms create customer frustration and operational counter friction. Digital queueing transforms the waiting experience by empowering customers to use their waiting time productively around the commercial venue or neighborhood while monitoring their exact position from their personal device. For merchants, it organizes incoming customer flow systematically, provides reliable visibility into waiting demand, and reduces lobby congestion.

## Acceptance Criteria

- [ ] Customers can select active products or services from the storefront catalog to prepare their turn.
- [ ] Customers can adjust the quantity of selected items or remove items before confirming their turn.
- [ ] The turn summary calculates and displays the item count and total amount in real time.
- [ ] Submitting a turn requires at least one selected product or service.
- [ ] Authenticated customers can confirm their turn directly using their existing account profile.
- [ ] Unauthenticated visitors can confirm their turn by providing their name and a valid phone number or email address without completing a full account registration.
- [ ] Contact details and customer names automatically trim leading and trailing whitespace and ignore entries composed entirely of spaces.
- [ ] Upon confirmation, the system creates a digital ticket with a unique, human-readable identifier (e.g., `TK-014`) and sets its initial status to waiting.
- [ ] The customer is redirected to a live ticket tracking page accessible via the ticket's unique code.
- [ ] The ticket tracking page displays the business commercial name, ticket identifier, requested products or services, current status, number of turns ahead in line, and estimated wait time.
- [ ] The ticket tracking page automatically refreshes queue status and position periodically without requiring manual page reloads.
- [ ] When the merchant calls the ticket, the tracking page immediately updates to notify the customer that it is their turn to approach the service area.
- [ ] Customers can voluntarily cancel their active ticket before service begins; confirming cancellation marks the ticket as cancelled and removes it from the active waiting line.
- [ ] A customer cannot hold more than one active turn (waiting, called, or in service) in the same business at the same time.
- [ ] If a customer attempts to request a new turn in a business where they already hold an active ticket, the system informs them and directs them to their existing active ticket.
- [ ] A customer can hold active tickets in different businesses simultaneously without interference.
- [ ] Visiting a ticket link with an invalid or non-existent ticket code displays a clear page indicating that the ticket was not found.
- [ ] Inactive, completed, or cancelled tickets display their final status and prevent duplicate cancellation or turn reactivation.

## Out of Scope

- Merchant-side ticket calling, desk assignment, and in-service flow (handled in Feature 014).
- Public waiting room television display boards (handled in Feature 015).
- Payment gateway processing and upfront digital transactions.
- Automated SMS or WhatsApp push dispatches on ticket status changes.
