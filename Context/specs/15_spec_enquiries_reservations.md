# SPEC 15 — ADMIN ENQUIRIES, RESERVATIONS AND CONTACT SETTINGS

Status: APPROVED by the owner on 2026-10-14 (all PROPOSED items accepted as written; sign-in not required; parts: Contact + WhatsApp only; stock: in stock / out of stock only) (written 2026-10-14). Depends on Spec 14 (and Spec 16 for part enquiries).
Items marked **PROPOSED** need the owner's yes/no.

## 1. Purpose

Give administrators one inbox for every customer request (vehicle contact, reservation, and later part enquiries), a safe way to confirm a reservation, email alerts, and an admin page to edit the company contact numbers used on the website.

## 2. Enquiries inbox (`/admin/enquiries.php`)

* Replaces the current "coming soon" placeholder for the Enquiries sidebar item.
* List: newest first; columns date, customer name, type (Vehicle enquiry / Reservation request / Part enquiry / General), subject (vehicle or part name, linked to its admin page), status; pagination (25).
* Filters: status (new / in progress / closed), type; search by customer name, email or phone.
* View page: all fields, the linked vehicle/part with its current status, and actions:
  * change status (new → in progress → closed; can reopen);
  * `mailto:` / `tel:` / WhatsApp links to reply (no email is sent from the inbox in this spec — **PROPOSED**);
  * internal note — **PROPOSED**: one `admin_note` text column (needs a migration); otherwise omit.
* The dashboard "New enquiries" figure (Spec 04) keeps working unchanged.

## 3. Confirming a reservation

On a Reservation request whose vehicle is `available`:

* Button **Confirm reservation** (POST, CSRF, confirmation step).
* In one transaction: vehicle `availability_status` → `reserved`; this enquiry → `in_progress`; audit log entry `VEHICLE_RESERVED` (admin, vehicle, enquiry).
* Re-checked on the server: if the vehicle is no longer `available` (another request was confirmed, it was sold or archived), nothing changes and the admin sees why.
* Other open reservation requests for the same vehicle remain in the inbox, marked "Vehicle already reserved".
* Releasing a reservation is done in the existing vehicle status control (Spec 06) — **PROPOSED**: no separate "cancel reservation" button.

## 4. Email alerts (Brevo)

* When a public enquiry is saved, `EmailService` sends a short alert (type, subject, customer name, link to the admin view; **no** message body or customer contact details in the email — **PROPOSED**, for privacy) to the setting `notification_email`.
* If Brevo or the setting is not configured, the enquiry is still saved and the failure is logged; the customer still sees success.
* No confirmation email to the customer in this spec — **PROPOSED**.

**Amended by the owner on 2026-10-27 (Brevo email integration), implemented the same day:**

* Because the admin inbox (section 2) is not built yet, the alert carries the **full details**: type, vehicle (or Contact page
  topic), customer name, email, phone, the message, time and enquiry reference. This replaces the "no message body or contact
  details" rule above.
* From is always the verified sender (`MAIL_FROM_ADDRESS`); **Reply-To** is the customer's validated email, so staff answer
  by replying. The customer is never put in From.
* Recipient: setting `notification_email`, stored in category `company` (never read by the public site), edited in
  Admin > Settings > Email alerts. Empty = no alert.
* Sent after the enquiry is committed (`EnquiryAlertService`); a failure is logged without customer details and audited
  (`ENQUIRY_ALERT_SENT` / `ENQUIRY_ALERT_FAILED`); the customer still sees success. Honeypot, invalid, throttled and
  refused submissions send nothing.
* Still out of scope: confirmation emails to the customer, replying from the admin.

## 5. Contact settings (`/admin/settings.php`)

* Replaces the Settings placeholder with one section, "Contact":
  * Company phone (`company_phone`, existing key, shown in the utility bar and menu);
  * WhatsApp number (`whatsapp_number`, new key) — digits in international format, validated (e.g. `233XXXXXXXXX`); empty = WhatsApp buttons hidden;
  * Notification email (`notification_email`, new key).
* Existing `settings` table; new keys only, no schema change. Audit log `SETTINGS_UPDATED`. CSRF, server validation, administrator permission.

## 6. Security

`requireAdmin()` on every page; authorization via the existing permission map; prepared statements; output escaped (enquiry content is untrusted); IDs from the browser re-validated; POST + CSRF for every change; no enquiry data in URLs beyond the id; audit logging for status changes and reservations.

## 7. Schema

* None required for sections 2–5 except the optional `admin_note` (PROPOSED).
* Spec 16 adds `part_id` and the `part` enquiry type.

## 8. Testing

Inbox list/filter/search/pagination, view, status changes, reservation confirmation (happy path, vehicle no longer available, double click, two admins at once), audit entries, email alert sent (mock) / not configured, settings validation, security (CSRF, IDOR via id tampering, XSS in stored enquiries), responsive admin tables, regression (dashboard, vehicles, auth).

## 9. Out of scope

Replying by email from the admin, customer confirmation emails, online payment, deposits, automated reservation expiry.
