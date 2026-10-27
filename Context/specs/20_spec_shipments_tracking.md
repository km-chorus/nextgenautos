# Spec 20 — Shipment Management, Customer Tracking and Shipment Emails

**Status:** APPROVED by the owner on 2026-10-21 ("agree and start the build": D1-D10 as proposed) — IMPLEMENTED 2026-10-21, awaiting owner review.
**Implementation notes:** D1 is done by email: when the customer email matches an ACTIVE website account, the shipment is linked to it automatically (no separate account search). D4 stays open: the shipping line is free text with suggestions, and each shipment may store its own https tracking link (else the Settings link). Email preview opens in a new tab (`admin/shipment-email-preview.php`), with its own narrow Content-Security-Policy (inline styles, no scripts).
**Replaces in the tracker:** row 11 "Shipment Management" and row 12 "Vehicle Tracking" (both `DEFERRED` by the owner on 2026-10-08 until the shipping workflow was clarified).
**Source:** owner questions of 2026-10-21 ("Can the email template be used for vehicle tracking emails? Would the admin need to update shipping status manually?").
**Depends on:** 06 (vehicles), 13 (customer accounts and the Track Vehicle page), the branded email template (`EmailTemplate`, 2026-10-21), Brevo SMTP (`EmailService`).

Items marked **PROPOSED** are recommendations that need the owner's yes / no. Items marked **OPEN** need the owner's answer.

---

## 1. Purpose

Let administrators record each customer's vehicle shipment (shipping line, container number, status, dates) and let the customer follow it: on the Track Vehicle page after signing in, and by a branded email when the administrator records a meaningful update. Live location always comes from the shipping line's own tracking page.

## 2. What already exists

* Table `shipments` (schema.sql): vehicle_id (FK), customer_name, customer_email, container_number (never generated), shipping_company, status (the six statuses below), shipped_date, estimated_arrival, arrival_date, delivered_date, tracking_url (https only), internal_notes, timestamps.
* Business rules (06, sections 7 to 9): manual process; container number comes from the shipping company and is recorded by an administrator; the carrier is the source of truth; no fabricated statuses, locations, numbers or live tracking; no scraping; no carrier API.
* `/track/` (spec 13): signed-in customers only; today it explains the process and links to the official tracking site, with no per-customer data.
* Admin sidebar item "Shipments" (shows the "coming soon" page).
* Settings: the default official tracking link (Hapag-Lloyd) used site-wide.
* Branded email template and working Brevo SMTP delivery.

## 3. How it works (the approved process, unchanged)

```text
Customer buys a vehicle
  -> administrator creates the shipment record (status: Not yet shipped)
  -> shipping company books the shipment            -> administrator sets "Shipment booked"
  -> shipping company assigns the container and gives its number
                                                    -> administrator enters the container number
                                                       ("Tracking information available")
  -> vessel departs                                 -> administrator sets "In transit" (+ shipped date)
  -> vessel arrives in Ghana                        -> administrator sets "Arrived" (+ arrival date)
  -> vehicle handed over                            -> administrator sets "Delivered" (+ delivered date)
```

**Every change is made by an administrator in the dashboard.** The website never learns anything from the shipping line by itself. Statuses describe the company's administrative understanding; the shipping line's tracking page stays the source of truth for where the container is.

Statuses (existing, business rules section 8): `Not yet shipped`, `Shipment booked`, `Tracking information available`, `In transit`, `Arrived`, `Delivered`. No new statuses.

## 4. Decisions needed

| # | Question | Recommendation |
| --- | --- | --- |
| D1 | How is a shipment linked to the customer? | **PROPOSED:** an optional link to the customer's website account (`customer_id`), chosen by searching the account by name or email, **plus** the existing name / email fields for customers without an account. With an account the customer sees the shipment on Track Vehicle; without one they get emails only. |
| D2 | Which updates email the customer? | **PROPOSED:** never automatic. Each save has a tick box **"Email the customer about this update"** with a preview of the email. It is pre-ticked for *Shipment booked*, *Tracking information available*, *In transit*, *Arrived* and *Delivered*; unticked for corrections and note-only changes. |
| D3 | Can one container carry several vehicles / customers? | **PROPOSED:** yes (common for car shipping). One shipment record = one vehicle and one customer; several records may share a container number. Each customer only ever sees their own record. |
| D4 | Which shipping lines? Per-shipment tracking link? | **OPEN:** which lines does the company use (Hapag-Lloyd only, or also e.g. Maersk, MSC, CMA CGM, Grimaldi)? **PROPOSED:** the shipment stores the shipping line's name and, optionally, its official tracking address (https only); when empty the site-wide link from Settings is used. |
| D5 | Should the customer see shipment details on Track Vehicle? | **PROPOSED:** yes, signed-in customers see their own shipments: vehicle, status, container number (once entered), shipping line, the dates that were entered, and an "Open official tracking" button. Customers never see internal notes. |
| D6 | Should creating a shipment change the vehicle's status? | **PROPOSED:** no automatic change (avoids surprises). The shipment form only offers vehicles already marked *Reserved* or *Sold*, and shows a reminder if the vehicle is still *Available*. |
| D7 | Show the estimated arrival to the customer? | **PROPOSED:** only when entered, labelled "Estimated arrival (given by the shipping line; may change)". |
| D8 | Can a status go backwards (a mistake, or a delay)? | **PROPOSED:** yes, after a confirmation step; it is audited and does not email the customer unless the box is ticked. |
| D9 | Validate container numbers? | **PROPOSED:** the international format (4 letters + 7 digits, e.g. `HLXU1234567`) is required; a wrong check digit gives a warning ("please re-check the number") but can be saved, because the number must match the shipping company's paperwork exactly. |
| D10 | Can a shipment be deleted? | **PROPOSED:** only while it is *Not yet shipped* and no email was sent; otherwise it is kept (history and audit). |

## 5. Data changes (migration 008, after approval)

* `shipments.customer_id` INT UNSIGNED NULL, FK → `customers(id)` ON DELETE RESTRICT (customers are disabled, never deleted).
* `shipments.notified_at` DATETIME NULL (last time the customer was emailed; for the list and for D10).
* Container number stored in upper case without spaces; the existing index is kept (not unique: D3).
* No new table: each status change, email sent or failed is written to `audit_logs` (`SHIPMENT_CREATED`, `SHIPMENT_UPDATED`, `SHIPMENT_STATUS_CHANGED` from → to, `SHIPMENT_EMAIL_SENT`, `SHIPMENT_EMAIL_FAILED`, `SHIPMENT_DELETED`), which also gives the history shown on the shipment page.

## 6. Admin

* Sidebar **Shipments** becomes live (permission `shipments.manage`).
* **List:** search by container number, customer or vehicle; filter by status and shipping line; newest first; each row shows vehicle, customer, container, status badge, last update and "emailed" date.
* **Add shipment:** vehicle (Reserved / Sold vehicles), customer (account search, or name + email), shipping line, optional official tracking link, optional internal notes.
* **Shipment page:** a status stepper with the six statuses, the container number and dates fields that belong to the status (e.g. *In transit* asks for the shipped date), the "Email the customer" box with a live preview, and the history (from the audit log).
* Dashboard: the existing "Shipment overview" counts become real.

## 7. Shipment emails (the branded template)

| Status | Title | Details panel (only values that were entered) | Button |
| --- | --- | --- | --- |
| Shipment booked | Your shipment is booked | Vehicle, Shipping line | View on Track Vehicle (account) or none |
| Tracking information available | Your container number is ready | Vehicle, **Container number**, Shipping line | Track your container (official site) |
| In transit | Your vehicle is on its way | Vehicle, Container number, Shipping line, Shipped, Estimated arrival (D7) | Track your container |
| Arrived | Your vehicle has arrived in Ghana | Vehicle, Container number, Arrived | Contact us about collection |
| Delivered | Your vehicle has been delivered | Vehicle, Delivered | (none; thank-you note) |

Every email ends with: "Location and arrival updates come from the shipping line. For questions about your shipment, contact us" and the company phones. The email never claims a live position or an arrival date the shipping line has not given.

## 8. Customer Track Vehicle page

* Signed-in customer with shipments: one card per shipment (vehicle, status stepper, container number with a copy button, shipping line, entered dates, "Open official tracking").
* Signed-in customer without shipments: today's explanation ("Tracking information is not yet available until your container number has been provided").
* A shipment without a container number says so plainly (business rules 7.4).

## 9. Security

* Admin: `requireAdmin()`, permission check, CSRF on every change, POST only, ids validated, prepared statements, all output escaped, audit log.
* Customer: a customer only ever sees shipments linked to **their own account id** (never matched by an email typed in a URL or form): no IDOR.
* Emails go only to the address recorded on the shipment / account; header injection refused by `EmailService`; failures never shown with technical detail; the administrator sees "Email could not be sent" and can retry.
* Container numbers and tracking links come only from administrators; tracking links must be https.
* No carrier API, no scraping, no automatic status changes.

## 10. Out of scope

Carrier API integration, scraping, live location or maps, SMS or automated WhatsApp messages, customs clearance, payments, documents (bill of lading uploads), multiple vehicles in one shipment record, new statuses.

## 11. Acceptance criteria

* [ ] Administrators can add, update and (D10) delete shipments; every change is audited.
* [ ] Statuses follow the six approved values; going back needs confirmation (D8).
* [ ] Container numbers are validated (D9) and never generated.
* [ ] The "Email the customer" option sends the branded email for that status, only when ticked, only to the recorded address; success and failure are shown to the administrator and audited.
* [ ] Signed-in customers see only their own shipments on Track Vehicle; others see the generic explanation.
* [ ] No page or email shows a location, status or date that an administrator did not enter.
* [ ] Works at 320 px to desktop; keyboard accessible; no console errors.
* [ ] Existing suites still pass (vehicles, customers, dashboard, email).

## 12. Testing

Logic tests (validation incl. container check digit, status changes, IDOR, email content per status, email only when ticked, audit rows) in a rolled-back transaction with a recording email double (no real email); HTTP tests (CSRF, permissions, POST-only); real-Chrome checks of the admin screens and the customer page at 320 to 1366 px; one real test email to the owner's own address only after approval.

## Change of 2026-10-22: automatic customer emails (owner request)

The customer is emailed **automatically on every status change**, with no box to tick. A new shipment also emails automatically when created with a status that has an email.
* "Not yet shipped" still has no email.
* Nothing is sent when there is no email address.
* Edits that don't change the status send nothing.
* The edit form has "Send the email again for the current status" (for example after a failed send).
* The result is still reported to the administrator and audited (SHIPMENT_EMAIL_SENT / FAILED). A mail failure never undoes the update.

## Change of 2026-10-22: Hapag-Lloyd is the official tracking link and the default line (owner request)

* **Customer-facing tracking link:** in emails, on the Track page and on the account dashboard it is always Hapag-Lloyd's official container tracking (`ShipmentService::trackingUrl`).
  * A shipment's own tracking address is used only if it is also a Hapag-Lloyd address.
  * Any other address stored earlier is ignored.
  * The admin form refuses non-Hapag-Lloyd addresses ("leave it empty to use Hapag-Lloyd's container tracking").
* **Shipping line:** defaults to Hapag-Lloyd. New shipments are pre-filled, and customer views and emails show Hapag-Lloyd when none is recorded. The field can still be changed for an exceptional shipment.
* **Emails:** the button reads "Track on Hapag-Lloyd", and the "container number is ready" text names Hapag-Lloyd.
* **Dashboard:** each shipment shows its shipping line and a "Track on Hapag-Lloyd" link once a container number exists.
* **Dev data:** the two test shipments (19, 20) had shipping line "Marcus Inc" and one had tracking address https://morningsidesystems.com. Both were set to Hapag-Lloyd with no own address.
