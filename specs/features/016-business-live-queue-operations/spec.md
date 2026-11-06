# 016 · Business Live Queue Operations

**Status:** Complete

## What it does

This feature provides business owners and counter staff (cashiers, barbers, receptionists, healthcare assistants, or clerks) with a live queue management screen in their business dashboard to run and advance their daily customer line smoothly.

From their dashboard navigation menu, operators access the live queue view where they can see who is currently being served, who is next in line, and what products or services each customer requested. The screen is centered around an active counter service area:

1. **Current Customer in Service (Two-Step Traceable Flow):**
   - Prominently displays the customer currently called to the counter, showing their turn number (e.g., `TK-004`), full name, phone number, and elapsed time since they were called.
   - Lists the exact products or services the customer selected when requesting their turn, along with quantities and estimated price totals, allowing staff to prepare orders before the customer arrives.
   - When the customer arrives at the counter, the operator clicks **"Iniciar Atención"**, updating the ticket to in-service and starting the true service duration timer.
   - When the service is finished, the operator clicks **"Completar Atención"** to conclude the visit and clear the counter for the next customer.
   - If the customer does not arrive after being called, staff can click **"Volver a llamar"** to re-alert them, mark them as **"No se presentó"**, or **"Posponer"** their turn if they need extra time before being attended.

2. **Call Next Customer:**
   - When ready for the next person, the operator clicks **"Llamar Siguiente"**. The system automatically calls the customer who has been waiting the longest, immediately alerting them on their digital ticket that it is their turn to approach the counter.
   - If a customer is already in progress at the counter, the system protects the operator by asking for confirmation before bringing up a new customer.

3. **Waiting Queue List & Real-Time Search:**
   - Shows all customers currently waiting in line in chronological order of arrival.
   - Displays each person's turn number, name, requested items, and how many minutes they have been waiting.
   - Includes a quick search bar so staff can instantly look up a customer by name or turn number in busy queues.
   - Allows staff to call a specific customer directly if priority service is needed (such as elderly or pregnant patrons), or cancel an in-line ticket if requested by the customer.

4. **Queue Reception Control (Pause / Resume):**
   - Staff can toggle a **"Pausar Fila"** switch at any time. When paused, public storefront visitors see that the line is temporarily closed for new turns, while existing patrons already waiting in line continue to be attended normally.

5. **Postponed Customers, Overnight Resilience & Daily Summary:**
   - If a customer needs extra time before being served, staff can postpone their turn without deleting it. A dedicated list keeps postponed patrons organized so they can be resumed as soon as they return.
   - For establishments operating through the night (such as bars, late-night diners, or 24-hour clinics), active waiting turns remain visible and valid across midnight without disappearing or resetting abruptly.
   - A daily summary displays quick totals for the day: customers currently waiting, customers successfully served, and customers who did not show up, along with a log of finished turns.
   - The screen refreshes automatically as customers join the queue or cancel their turns, without requiring the operator to manually reload the page.

## Why

Currently, customers can join a virtual queue from a business's digital storefront, but business operators have no screen in their portal to see who is waiting, call customers to the counter, or mark services as completed.

Without this tool:
- Counter staff cannot notify patrons when their turn is up.
- Customers remain stuck in waiting status on their digital tickets, creating confusion about when to approach.
- Staff cannot see what services or products customers requested ahead of time, causing counter delays.
- Businesses cannot measure real counter wait times versus actual service duration.

This feature completes the core queue cycle: it gives merchants full operational control over their customer flow, reduces physical counter crowding, provides accurate service time tracking, and eliminates confusion about who is next.

## Acceptance Criteria

- [x] Authenticated business operators can access the live queue management screen from their business dashboard menu.
- [x] Each business can only view and manage its own queue; staff cannot view or alter turns belonging to other establishments.
- [x] When no customer is currently being served, the screen clearly displays the option to call the next waiting customer.
- [x] Clicking **"Llamar Siguiente"** automatically selects the customer who has been waiting the longest in line (first-come, first-served).
- [x] Calling a customer updates their status to called and immediately updates their digital ticket screen so they know it is their turn to approach the service area.
- [x] If a customer is already in called or in-service status, attempting to call another customer prompts the operator for confirmation to prevent accidental loss of the current service.
- [x] The called customer moves into the main service spotlight, showing their turn number (e.g., `TK-004`), name, contact phone number, arrival time, and elapsed time since called.
- [x] The main service area displays the full list of products or services the customer requested, including quantities and estimated price totals.
- [x] When the customer arrives at the counter, the operator can click **"Iniciar Atención"**, updating the ticket status to in-service and tracking true service time.
- [x] When the service is finished, the operator can click **"Completar Atención"**, which marks the turn as completed and clears the service area for the next customer.
- [x] If the operator clicks **"Llamar Siguiente"** and no customers are waiting in line, the system informs them with a clear message: _"No hay clientes en espera"_.
- [x] The waiting list displays all customers currently in line in order of arrival, showing their turn number, name, and elapsed waiting time.
- [x] The operator can search the waiting list in real time by customer name or ticket number to locate patrons quickly in busy lines.
- [x] The operator can manually call any specific customer from the waiting list, allowing priority care when needed.
- [x] The operator can cancel an active waiting ticket directly from the queue list after confirming the action, handling customer walk-outs or erroneous requests.
- [x] Irreversible actions such as marking a customer as no-show or cancelling a ticket require explicit confirmation before taking effect.
- [x] If a called customer does not show up at the counter after multiple calls, the operator can click **"No se presentó"**, which closes the turn and frees the service area.
- [x] The operator can click **"Volver a llamar"** to re-alert a customer who is currently in turn.
- [x] If a customer arrives at the counter but is not ready (e.g., retrieving payment or documents), the operator can click **"Posponer"** to move the turn to a postponed list without cancelling it.
- [x] Postponed customers remain visible in a dedicated list and can be recalled to the counter as soon as they are ready.
- [x] Active waiting and called turns remain visible and operational across midnight, ensuring late-night establishments can continue service without losing queued patrons.
- [x] The operator can toggle queue reception on or off ("Pausar Fila"); when paused, public storefronts reject new turn requests while existing waiting patrons continue to be attended.
- [x] If a customer voluntarily cancels their turn from their own phone while waiting, they disappear from the operator's active waiting line automatically.
- [x] The screen displays daily counters summarizing: customers currently waiting, customers completed today, and customers who did not show up.
- [x] The operator can view a log of finished turns for today, showing completion times and final statuses (completed, no-show, or cancelled).
- [x] The screen updates automatically when new customers join the queue or change status, without requiring manual browser reloads.
- [x] The interface is comfortable to operate on touchscreens (counter tablets and smartphones) as well as desktop computers.
- [x] All user-facing text, buttons, and alerts are written in clear, natural Spanish suitable for retail and service staff.

## Out of Scope

- Multi-counter simultaneous routing (e.g., assigning turns to "Ventanilla 1", "Caja 2", "Sillón B" with separate operator logins) — deferred to a future multi-station feature.
- Physical paper ticket printing or thermal receipt printer integrations.
- Point-of-sale (POS) card payments or digital checkout at the counter.
- Public waiting room television display board (covered in upcoming feature `017 · Public Queue Display Board`).
