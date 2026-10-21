# SPEC 14 — PUBLIC VEHICLE DETAIL PAGE (CONTACT, WHATSAPP, RESERVE)

Status: APPROVED by the owner on 2026-10-14 (all PROPOSED items accepted as written; sign-in not required; parts: Contact + WhatsApp only; stock: in stock / out of stock only) (written 2026-10-14 from the owner's request and answers).
Items marked **PROPOSED** are the implementer's suggestions and need the owner's yes/no before implementation.

## 1. Purpose

When a customer clicks a vehicle (homepage Latest Vehicles, Vehicles page), open that vehicle's own page with everything needed to decide, and three ways to act:

* **Contact** — send a question about this vehicle.
* **WhatsApp** — open a WhatsApp chat with the company about this vehicle.
* **Reserve** — ask to reserve this vehicle (owner decision 2026-10-14: a reservation request; the vehicle becomes Reserved only after an administrator confirms it, Spec 15). There is **no online payment**.

## 2. Route

* `/vehicle/{slug}/` (already declared in `app/config/public_routes.php` as `planned`; this spec makes it `live`).
* The slug must match `^[a-z0-9]+(-[a-z0-9]+)*$`, max 160 (existing route rule). Unknown slug → the existing 404 page.
* Visibility = the existing public rule (`VehicleRepository` public queries): published, not archived, status `available` or `reserved`. Anything else (sold, unavailable, unpublished, archived) → 404. **PROPOSED**: no "sold" page in this spec.

## 3. Page content (real data only, nothing invented)

Order: Vehicle identity → Price → Key specifications → Actions → Description / Features → Shipping / Duty.

1. **Gallery**: all images of the vehicle in their admin order, primary first; one large image with a thumbnail strip; previous/next buttons; works with keyboard; without JavaScript the images are listed. Uses the stored WebP files from Spec 07 (main + thumbnail); no new image processing. No images → the existing "Photo coming soon" placeholder.
2. **Identity**: `Year Brand Model Trim` as the H1; brand/year line above.
3. **Price**: stored price and currency, unchanged. "Duty included" only when `duty_included = 1`.
4. **Status**: Available / Reserved (text, not colour only).
5. **Key specifications** (only those that exist): mileage + unit, engine, transmission, drive, fuel.
6. **Description** (`description`, plain text, line breaks kept) and **Features** (`key_features` JSON list).
7. **Shipping information** (`shipping_info`) when present.
8. **Duty**: a link to the approved external Ghana duty calculator (`ExternalLinksService`); the site never calculates duty.
9. Breadcrumb: Home › Vehicles › {vehicle}.
10. SEO: title `{Year Brand Model Trim}`, meta description from real fields, canonical `/vehicle/{slug}/`, Open Graph image = primary image.

## 4. Actions panel

Sticky beside the gallery on desktop; directly under the price on phones.

### 4.1 WhatsApp
* Opens `https://wa.me/{number}?text={message}` in a new tab.
* `{number}` = setting `whatsapp_number` (E.164 digits, editable in the admin Settings page of Spec 15). If not set, the button is not shown (never a broken link).
* `{message}` pre-filled and URL-encoded: `Hello, I'm interested in the {Year Brand Model Trim} ({price}): {absolute page URL}`.

### 4.2 Contact (enquiry form)
* Fields: name (required, 2–150), email (required, valid), phone (optional, 7–40, digits/+/spaces), message (required, 10–2000).
* Saved to `enquiries` with `vehicle_id`, `enquiry_type = 'vehicle'`, `status = 'new'`.

### 4.3 Reserve (reservation request)
* Same fields; message optional (a default line is stored if empty), phone **required** (the team must be able to call back) — **PROPOSED**.
* Saved to `enquiries` with `enquiry_type = 'purchase'` (shown in the admin as "Reservation request"; existing enum, no schema change).
* **Does not** change the vehicle's status. Confirmation is an admin action (Spec 15).
* If the vehicle is already `reserved`, the Reserve form is replaced by "This vehicle is currently reserved" and the server rejects reservation submissions for it; Contact and WhatsApp stay available.
* Success message: "Thank you. We have received your reservation request and will contact you to confirm."

### 4.4 Sign-in
* **PROPOSED**: no customer sign-in required for Contact or Reserve (lower friction). If signed in, name and email are pre-filled from the customer account.

## 5. Security

* CSRF token on both forms; POST only; Post/Redirect/Get after success.
* Server-side validation (client validation is UX only). The vehicle is resolved from the slug on the server; no vehicle id is accepted from the browser.
* Spam: hidden honeypot field; minimum fill time (3 s); rate limit **PROPOSED** 5 submissions per IP per hour and 3 per email per hour, using the existing audit/throttle approach; generic message when limited.
* All output escaped; the WhatsApp text is URL-encoded; external links `rel="noopener noreferrer"`.
* No personal data in logs or URLs. Errors: friendly message to the visitor, detail to the server log.
* Email alert to the company is Spec 15 (EmailService); this spec only stores the enquiry.

## 6. Architecture

`PublicController` (route) → `PublicVehicleDetailService` (view model, reuses `VehicleRepository`, `VehicleImageRepository`) and `PublicEnquiryService` + `EnquiryValidator` → new `EnquiryRepository` (prepared statements) → `public/views/vehicle.php` + includes. CSS `vehicle-detail.css`, JS `vehicle-detail.js` (gallery only, progressive enhancement). No new dependency. No schema change.

## 7. States

Loading (server-rendered; gallery images lazy beyond the first), no images, no specs, missing description, reserved vehicle, WhatsApp not configured, validation errors (inline, focus moves to the first error), rate limited, database unavailable (page 503-style friendly error; form shows "could not be sent, please try again or contact us by phone").

## 8. Responsive

320 → 1440+: gallery full width on phones with swipeable thumbnails; two columns (gallery | details + actions) from 62em. Touch targets ≥ 44 px. No horizontal overflow.

## 9. Testing

Functional (each field, both forms, WhatsApp link content), validation (every rule), security (CSRF, honeypot, rate limit, slug tampering, hostile stored values escaped, reserved-vehicle bypass), visibility (sold/unpublished/archived → 404), responsive (320–1440), accessibility (headings, labels, errors announced, keyboard gallery), regression (public suites, Vehicles page cards now open live pages).

## 10. Out of scope

Online payment, deposits, automatic reservation, sold-vehicle pages, related vehicles, parts (Spec 16), the admin inbox and email alerts (Spec 15).

## Revision 2026-10-21: layout after the owner's reference page

The owner supplied a reference vehicle page (another dealer's site) and asked for the same layout and arrangement. Only the layout was adopted; no third-party branding, header, footer or floating widgets.

Owner decisions (2026-10-21):
* Gallery: **Exterior / Interior** tabs. New column `vehicle_images.photo_type` (migration 007, default exterior), set per photo on the admin Manage Images page. No 360° tab (no 360° photography exists).
* Buttons: full-width **Reserve this vehicle** (or "Ask about this vehicle" when reserved); row: **Ask a question**, **WhatsApp** (when configured), **Import duty** (external calculator). No test drive, quote or brochure buttons: the business does not offer them.
* Detail tiles and the Specifications section show **only real values** (Engine, Transmission, Mileage, Year tiles; cards Engine & drivetrain, Vehicle details, Key features, Shipping & duty). Never "N/A"; no Power field.
* Price and availability status stay visible, under the title (trim as the grey subtitle).
