# Spec 21 — Public Track Your Vehicle Page

**Status:** IMPLEMENTED 2026-10-21 (awaiting owner review)
**Source:** owner specification of 2026-10-21 ("Amponsah Autos — Public Track Vehicle Page"), building on spec 13 (customer accounts) and spec 20 (shipments).

## What was built

`/track/` ("Track Your Vehicle") with four states, all server-rendered:

* **A. Guest:** explanation that shipment details are only shown to the customer they belong to, with **Sign in** (`/login/`) and **Create an account** (`/register/`). The visit is remembered (customer session `return_to`), so signing in, registering or Google sign-in returns to `/track/`. Previously guests were redirected to `/login/?reason=track`.
* **B. Signed in, no linked shipment:** empty state naming the account's email (shipments link by it) and a Contact link. Nothing internal is revealed.
* **C. Signed in with shipments:** per shipment: vehicle, current status, last-updated time (GMT), container number (with Copy), shipping line, estimated arrival only while not yet arrived and labelled "given by the shipping line; may change", the milestones reached so far (oldest first, the current one marked), the steps still to come, and "Track on {line}'s website" (official tracker) once a container number exists.
* **D. Missing information:** "Not available yet" for the container number, "Not recorded yet" for the shipping line, no tracker link before a container exists, no date on a milestone without a recorded date. Nothing is invented.

## Data and security

* Routes: `GET /track/` (live route `track`, `private`: noindex, `Cache-Control: no-store`; POST refused with 405). Login `/login/`, registration `/register/`.
* Authorization: the customer id comes only from the server-side customer session (`CustomerAuthController::requireCustomer` → `CustomerAuthService::currentCustomer`); the page takes no id from the URL or a form. Shipments are read with `ShipmentRepository::forCustomer(customer_id)` (prepared statement, `WHERE s.customer_id = :c`).
* Fields used: `shipments.customer_id, vehicle_id, status, container_number, shipping_company, tracking_url, shipped_date, estimated_arrival, arrival_date, delivered_date, updated_at`; `vehicles.year, model, trim` (+ `brands.name`); `customers.id, name, email` (the signed-in customer only). Never shown: `internal_notes`, `customer_email` of the shipment, `notified_at`, audit history.
* Tracker link: the shipment's own `tracking_url` (https) or the Settings link (default Hapag-Lloyd `https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html`). No scraping, no live tracking claim.

## Milestones (mapped onto the six approved statuses)

| Status | Shown as | Date shown (only if recorded) |
| --- | --- | --- |
| NOT_YET_SHIPPED | Preparing for shipment | — |
| SHIPMENT_BOOKED | Shipment booked ("The vehicle has not left yet.") | — |
| TRACKING_INFORMATION_AVAILABLE | Container assigned | — |
| IN_TRANSIT | In transit to Ghana | `shipped_date` |
| ARRIVED | Arrived in Ghana ("Our team will contact you about collection.") | `arrival_date` |
| DELIVERED | Delivered | `delivered_date` |

A booked shipment never appears as departed: "In transit" is listed under "Still to come" until an administrator records it.

## Conflicts with approved documents (resolved conservatively; owner to confirm)

1. **Typography:** the request names Manrope; the approved design rules (owner decision 2026-10-14) set **Nunito** for the public site (Manrope is the admin font). The page uses Nunito, like every other public page. Switching the public site to Manrope would be a separate design decision.
2. **"Ready for collection":** not one of the six approved statuses (business rules section 8: new statuses need an approved specification). It is expressed inside "Arrived in Ghana". RECOMMENDATION: if collection readiness must be a separate, dated step, approve a seventh status (schema enum, admin form, email, this page).

## Out of scope

Customer authentication changes, shipment administration, emails, payments, carrier APIs (all excluded by the request).

## Change of 2026-10-22: tracking number field and horizontal status bar (owner request and decisions)

* Guests are sent to `/login/?reason=track` and return to `/track/` after signing in (same day).
* A confirmed customer sees, in this order:
  1. a **tracking number field** (GET `?number=`);
  2. one card per shipment with a **long horizontal status bar**: six steps (Preparing, Booked, Container assigned, In transit, Arrived in Ghana, Delivered), with done / current / still to come and dates only where recorded;
  3. a short "How tracking works" line.
* The vertical "Journey so far" list and the separate "How tracking works" panel are replaced.
* **Search rule (owner choice "Both"):**
  * The number is normalised (spaces and hyphens removed, upper case) and must be 4 letters + 7 digits.
  * It is looked up ONLY among the signed-in customer's own shipments (`ShipmentService::findContainer`), with no other query, so nothing about other customers can be learned.
  * **Found:** only that shipment's card, with "See live position on <shipping line>" (official tracker).
  * **Not found:** "not a shipment on your account" and a link to the official tracking page.
  * **Invalid:** an inline error.
* Nothing is invented: the bar shows the status recorded by the team. Only the shipping line's tracker shows the container's position.

## Change of 2026-10-22 (later the same day): status only after Track; tracking numbers to copy; vehicle in the update

* **Before a search:** no status is shown. The page shows the tracking number field and "Your tracking numbers": each shipment's container number with a Copy button, or "Not available yet" until the shipping line assigns it.
* **After Track (own number found):** the update card shows:
  * the vehicle's main photo (the stored thumbnail, only when the file exists) and a few recorded details (year, make, model, mileage, transmission, fuel, engine; only values that exist);
  * the horizontal status bar;
  * container, shipping line, estimated arrival and the live tracking button.
* **Not found / invalid:** unchanged.
* **Consequence:** a shipment without a container number can't be tracked on the page yet. The customer gets its status updates by email (spec 20 change below).
