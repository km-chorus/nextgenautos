# Spec 26 — Parts and Accessories page

**Status:** IMPLEMENTED 2026-10-26; the catalogue part of this spec is **superseded by Spec 27** (the 50 part types now live in Admin → Parts; the config file, pictures folder and PartsCatalogue service were removed).

**Source:** owner request of 2026-10-26. The client sent a picture, "Complete car parts list with pictures (main parts)", showing 50 part types. The client did not say which vehicles the parts fit or which categories they belong to.

## Owner decisions (2026-10-26)

1. **Page model: a request-a-part catalogue.**
   * The 50 part types are shown, grouped by category.
   * Each part has a **Request** button. It opens the Contact form with the topic "Car parts" and the message "Hello, I'm looking for this part: X. My vehicle (make, model, year): ".
   * Each part also has a WhatsApp button that sends the same message to the Ghana WhatsApp number.
   * The team then confirms availability, price and fit.
   * Parts the admin publishes (with price and compatible vehicles) appear above the catalogue as "Available Parts". That section is hidden while none are published.
2. **Pictures:** each tile of the client's picture is cut out and used as that part's picture. (The "Illustration" labels were removed at the owner's request, 2026-10-26.)
   * Steering wheel, trunk lid and airbag show a large Toyota badge, which could suggest Toyota-only parts.
   * Those three show the category icon instead of a picture.
3. **Condition:** not mentioned. The page claims no price, condition, stock or compatibility.

## Category grouping (existing admin part categories; proposed by the developer, to be confirmed by the client)

| Category | Parts |
| --- | --- |
| Engine | Engine, Fuel Tank, Fuel Pump, Catalytic Converter, Exhaust Assembly |
| Transmission | Gearbox, Front Axle / CV Axle |
| Electrical | Alternator, Starter Motor, ECU, ABS Module, Wiring Harness, Instrument Cluster, Infotainment |
| Cooling | Radiator, Cooling Fan, AC Compressor, AC Condenser, Heater Core |
| Suspension & Steering | Front Suspension L/R, Rear Suspension, Steering Rack, Steering Wheel, Wheel Hub / Knuckle, Subframe |
| Brakes | Brake Caliper, Brake Disc |
| Body & Lights | Front/Rear Bumper, Headlight, Tail Light, Front/Rear Door, Bonnet, Trunk Lid, Fender, Side Mirror, Windows, Window Regulator |
| Interior | Front Seats (Driver/Passenger), Rear Seats, Dashboard, Airbag, Seat Belt, Rear View Mirror, Interior Trim |
| Wheels & Tyres | Wheels / Rims, Tyre |

To change the grouping or the parts, edit `app/config/parts_catalogue.php`.

## Page

1. Title "Parts and Accessories" (banner, title only). The "Find the right part for your vehicle" intro with three steps was removed at the owner's request (2026-10-26).
2. Available Parts: published parts (`includes/latest_parts.php`, filters return to `/parts/`). Hidden when none are published.
3. "Parts we can source":
   * category chips with counts; with JavaScript they filter, and `#cat-<slug>` links open one category;
   * cards: two per row on phones, three on tablets, four to five on desktop.
4. Closing band: "Looking for a part that is not listed?", with "Send a request" (`/contact/?request=other`) and WhatsApp.

## Technical

* **Route:** `parts` is live.
  * View: `public/views/parts.php`.
  * CSS: `parts.css` and `parts-page.css`.
  * JS: `parts.js` and `parts-catalogue.js`.
* **`PartsCatalogue`:** reads `app/config/parts_catalogue.php`.
  * Malformed entries are skipped.
  * Pictures are used only when the file is a real WebP.
  * `find()` looks up a `?request=` key in the list, so a URL value is never echoed.
* **Pictures:** `assets/images/parts-catalogue/*.webp` (47 files, 480×360, about 570 KB in total).
* **Controller:**
  * the parts branch loads the published parts, the catalogue and the Ghana WhatsApp number;
  * `requestPrefill()` fills the contact form.
* **Footer:** "Parts and Accessories" added under Vehicles.
* No database changes.

## Tests

* `database/tests/parts_page_test.php`: 26 checks covering:
  * the config and its categories (matched against the DB);
  * input rejection;
  * fake or traversal image paths;
  * the view: counts, links, the "no claims" check, empty states;
  * the route and the prefill.
* All 26 suites pass.
* Chrome at 1366, 768 and 375 px (12/12): 50 cards in 9 groups, column counts, no sideways scroll, no console errors, chip filtering with a screen-reader announcement, and `#cat-` links.

## Owner / client to-do

* Confirm the category grouping and the part list.
* For real listings, add parts in Admin → Parts with price, condition and compatible vehicles. They then appear under "Available Parts".
* The engine picture shows a small Toyota logo on the engine cover.
