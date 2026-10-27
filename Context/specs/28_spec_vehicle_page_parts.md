# Spec 28 — Compatible parts on the vehicle page

**Status:** IMPLEMENTED 2026-10-26 (awaiting owner review).

**Source:** owner request of 2026-10-26: "We will display parts under vehicles that are compatible with. But this should be on the detailed vehicle page. Parts are present in admin dashboard to set whether available or not available."

**Depends on:** Spec 14 (vehicle page), Spec 27 (parts in the admin: compatible vehicles, stock).

## Owner decisions (2026-10-26)

1. **Available only.** A part appears on a vehicle's page only when it is marked **In stock** in Admin → Parts. Parts marked Out of stock, or with stock "Not set", are left out.
2. **Only listed fits.** A part appears only when the admin has entered this vehicle as compatible. Parts without compatible vehicles ("Confirm fit with us") are never shown here; nothing is guessed.

## Rule

A part is shown on a vehicle's page when all of these are true:
* it is published and not archived;
* its stock is In stock;
* one of its compatible-vehicle rows has:
  * the vehicle's make;
  * an empty model ("all models") or the vehicle's model (ignoring case and extra spaces);
  * a year range that includes the vehicle's year (an empty year means no limit).

## Page

* **"Parts for this vehicle"** sits after Specifications, before the Reserve and Ask forms (accident vehicles included).
  * It shows up to 8 parts in category order, using the same cards as the Parts page.
  * "See all N parts" links to `/parts/?make=…&model=…&year=…` when there are more.
  * The section is left out when no part matches.
* **Request:** opens the Contact form with the part, its main number and this exact vehicle (`?part=<slug>&vehicle=<vehicle slug>`).
  * The vehicle name is looked up from the public vehicle's address, never copied from the URL.
  * Example: "My vehicle (make, model, year): 2023 Honda Accord".
* **WhatsApp:** goes to the Ghana number with the same message.

## Technical

* `PartRepository::publicForVehicle()` (prepared statements, 2 queries).
* `PublicPartService::forVehicle()`.
* `PublicVehicleService::detail()` returns `fit` (make id and slug, model, year; never printed) and has a new `titleBySlug()`.
* `PublicController::vehiclePage()`: if the parts cannot be read, the section is left out and the problem is logged.
* The vehicle route also loads `parts.css`.
* "Price on request" styling moved to `parts.css`, so it looks the same everywhere.

## Tests

* `parts_page_test.php`, new section with 6 checks:
  * shown: in stock, published, and listing this make with this model, any case or "all models", and this year;
  * never shown: out of stock, stock not set, unpublished, other model, other years, other make, no list;
  * the 8-card limit and total;
  * the "see all" address;
  * an empty result;
  * vehicle-name lookup refuses junk.
* All 27 suites pass.
* Chrome at 1366 and 375 px, 6/6: section and card, Request names "2023 Honda Accord", no section on a vehicle without parts.
  * For this check the Radiator was temporarily set to In stock and compatible with Honda Accord 2020–2024, then restored to its previous state (stock not set, no compatible vehicles).
