# Spec 27 — Parts: one admin-managed catalogue, part numbers and "fit my car"

**Status:** IMPLEMENTED 2026-10-26 (awaiting owner review). Section 9 approved by the owner on 2026-10-26: points 1, 2 and 3 as proposed; point 4 stays out of scope.

**Source:** owner request of 2026-10-26: "We should have a spec for admin to be able to edit parts details and set compatible vehicles and part numbers."

**Depends on:**
* Spec 16 (parts: admin, fitments, photos);
* Spec 26 (the /parts/ page);
* Spec 07 (image pipeline with the hard size limits of 2026-10-26).

## 1. Background

Two parts systems exist today:

| | Admin → Parts (Spec 16) | "Parts we can source" (Spec 26) |
| --- | --- | --- |
| Stored in | Database | A config file (`app/config/parts_catalogue.php`) and `assets/images/parts-catalogue/` |
| Admin can edit | Yes: details, one part number, compatible vehicles, price, stock, photos | No: only by editing code |
| Price, condition | Required | None |

## 2. Owner decisions (2026-10-26)

1. **One parts system.** The 50 catalogue parts move into the database and are managed in Admin → Parts like any other part. The config file and the static picture folder are removed afterwards.
2. **Price on request.**
   * Price and condition become optional.
   * Without a price, the card shows "Price on request" and a **Request** button, as the catalogue does now.
   * With a price, the price is shown.
   * Condition is shown only when set.
3. **Several part numbers per part.** For example the OEM number plus alternative or interchange numbers. All are searchable; the first one is the main number shown on the card.
4. **"Fit my car" filter on /parts/.**
   * The customer chooses make, then model, then year, and sees the parts that fit.
   * Parts with no compatible vehicles set are also shown, marked "Confirm fit with us".

## 3. Data model (migration 012)

* **`parts`:**
  * `price` DECIMAL(12,2) **NULL** (NULL = price on request). CHECK: price IS NULL OR price ≥ 0.
  * `condition` ENUM('new','used') **NULL** (NULL = not stated).
  * `stock_status` ENUM('in_stock','out_of_stock') **NULL** (NULL = not stated; the stock badge is hidden).
  * `currency` stays (used only when a price is set).
  * Existing rows are unchanged.
* **New table `part_numbers`:**
  * columns: id, `part_id` (FK CASCADE), `number` VARCHAR(100), `sort_order` TINYINT;
  * UNIQUE (part_id, number);
  * INDEX on `number` for search;
  * at most 10 numbers per part (validated);
  * allowed characters: letters, digits, space, `-`, `.`, `/`.
  * The existing `parts.part_number` values are copied in as each part's first number. The column is then dropped in the same migration, after the copy has been checked.
* **`part_fitments`:** unchanged (make from the brands table, model optional = all models, years optional = open). Existing limit: up to `PartValidator::FITMENTS_MAX` rows.
* **Model matching:** models are free text today. For the filter they are compared trimmed and case-insensitively ("corolla" = "Corolla").

## 4. Moving the catalogue in (one-off import)

A CLI script, `database/tools/import_parts_catalogue.php`, run once per environment:

1. **Creates each of the 50 catalogue parts** as a part:
   * name and category from the config;
   * slug from the config key;
   * no price, condition, stock, part number or fitments;
   * **published** (they are on the website today).
2. **Puts each picture through the normal upload pipeline** (`ImageService`, kind part). It becomes the part's main photo in `uploads/parts/`, under the same size limits and checks as any upload.
3. **Is safe to repeat:** a slug that already exists is skipped, never duplicated or overwritten. The script reports what it created and skipped, and writes one audit entry per part (`PART_IMPORTED`).
4. **Removes the old sources:** after a successful import, the config file, `PartsCatalogue` and `assets/images/parts-catalogue/` are removed from the code.

## 5. Admin (Admin → Parts)

* **Form changes:**
  * Price, Condition and Stock become optional, each with a "Not set" choice and the hint "Leave empty to show *Price on request*".
  * **Part numbers:** repeatable rows (add, remove, reorder up and down; the first row is the main number), up to 10. It works without JavaScript: saved rows plus blank rows, as for the compatible-vehicles rows.
  * Compatible vehicles: unchanged, but with "Copy from another part" **(PROPOSED)** to speed up entering 50 parts that share vehicles.
* **List changes:**
  * search covers all part numbers;
  * new filters "Price: set / on request" and "Compatibility: set / not set", so the admin can find catalogue parts that still need data;
  * the list shows the main part number and the number of compatible vehicles.
* **Publishing:** a part can be published without a price or compatibility (decision 2).
* **Audit:** create / update / publish / archive as today. The update entry names the changed sections (details, part numbers, compatibility, photos) but not their values.

## 6. Public site

### /parts/ (replaces both sections of Spec 26)

* **Fit my car** (top of the page):
  * Make lists only makes that appear in a published part's compatibility.
  * Model lists the models entered for that make, plus "All models".
  * Year runs from the oldest year in those fitments to the current year + 1.
  * Plain GET form (`?make=&model=&year=`), so it works without JavaScript. JavaScript only fills the model list after a make is chosen.
  * All values are validated against the real options on the server; anything else is ignored.
* **A part matches when either:**
  * a fitment has that make, its model is empty or equal to the chosen one, and the year is within its range (open ends count as unlimited); or
  * the part has no fitments (shown with "Confirm fit with us").
* **Other filters:**
  * category chips, as now;
  * search by name or any part number **(PROPOSED)**.
* **One grid of part cards**, 20 per page with SQL pagination (as Vehicles). Each card shows:
  * photo, category and name;
  * the main part number, if set;
  * "Fits: Toyota Corolla 2014–2018" (up to 3, then "and N more") or "Confirm fit with us";
  * the price, or "Price on request";
  * condition and stock only when set;
  * actions: **Request** (Contact form pre-filled with the part, its main number and, when the filter was used, the customer's car) and WhatsApp (Ghana number).
* **Empty result:** "No parts match your vehicle yet", with a "Request a part" link.
* **Search engines:** filtered and later pages get `noindex,follow`.

### Elsewhere

* **Homepage "Latest Parts & Accessories":** see section 9, point 1.
* **Contact prefill:** `?part=<slug>` (existing) for any published part, including the vehicle line. `?request=` is removed with the config file, and old links fall back to an empty form.

## 7. Security

* Admin pages: `requireAdmin()` + `parts.manage`, CSRF, prepared statements.
* Server-side validation of everything:
  * part numbers: count, length, characters, duplicates;
  * years between 1900 and next year, with from ≤ to;
  * brand ids exist;
  * price ≥ 0 when given.
* Public filter values are only matched against the real options, never put into SQL directly.
* Output is escaped.
* The import script is CLI only.
* Photos go only through the existing pipeline: hard size limits, read-back check.

## 8. Testing

* **Migration:** nullable columns, the part number copy, constraints, rollback check on a copy of the database.
* **Import:** creates 50 parts with photos within the size limits; running it again creates nothing; existing parts are untouched.
* **Admin:** create and edit with and without a price; part number rows (add, remove, reorder, 10 maximum, duplicates rejected); compatibility; the list filters and part-number search; CSRF and permission checks; audit entries.
* **Public:**
  * fit matching: all models, open years, case-insensitive models, parts without fitments;
  * invalid filter values are ignored;
  * pagination;
  * price on request and condition hidden when not set;
  * the prefill;
  * no claims the admin did not enter.
* **Regression:** homepage parts, contact form, vehicle pages, image limits.
* **Chrome** at 1366, 768 and 375 px; works without JavaScript.

## 9. Open points (owner to decide before building)

1. **Homepage "Latest Parts".** After the import it would show 8 of the 50 catalogue parts (no prices). Choose one:
   * **(PROPOSED)** only parts that have a price;
   * or any published part, newest first.
2. **Copy compatibility from another part** in the admin form: build it now? **(PROPOSED: yes)**
3. **Public search** by name or part number on /parts/: build it now? **(PROPOSED: yes)**
4. **Part detail page `/part/{slug}/`** (Spec 16 phase 2) stays out of scope here. Cards keep linking to Request and WhatsApp.

## 10. Out of scope

* Online ordering, payment or cart.
* Stock quantities.
* VIN decoding or automatic compatibility data.
* Importing compatibility from suppliers.
* The part detail page (section 9, point 4).

## 11. Implementation notes (2026-10-26)

### Owner decisions on section 9
* **1:** only parts with a price on the homepage.
* **2:** copy compatibility, yes.
* **3:** public search, yes.
* **4:** stays out of scope.

### Built
* **Migration 012** (`database/migrations/012_parts_numbers_optional_price.sql`), plus `database/schema.sql` for new installs.
  * price, condition and stock are optional;
  * `part_numbers` table;
  * the existing part number is copied in, then `parts.part_number` is dropped.
* **Import:**
  * `database/tools/import_parts_catalogue.php` (with `--dry-run`);
  * data in `database/import/parts-catalogue/`: the list and 47 pictures, which the web server never serves;
  * on the development database it created 50 parts; a second run created 0.
* **Admin form:**
  * part-number rows: Add, Up, Down and Remove; the first row is labelled "Main part number";
  * "Not set" for condition and stock; optional price;
  * "Copy compatible vehicles from another part": a second submit button that keeps everything typed, adds the other part's vehicles without duplicates (up to 20) and saves nothing;
  * a hidden Save button comes first in the form, so pressing Enter always saves.
* **Admin list:**
  * filters for Price (set / on request), Compatibility (vehicles entered / none yet) and Stock "Not set";
  * search covers every part number, ignoring spaces, hyphens, dots and case;
  * the list shows the main number and "+N more".
* **/parts/:**
  * "Find parts for your vehicle": make, model and year appear once any compatibility exists; until then, search only;
  * category chips; 20 per page; one shared card (`includes/part_card.php`).
  * With a vehicle chosen:
    * the parts that fit come first, then the "Confirm fit with us" parts;
    * the count reads "N parts fit your Toyota Corolla 2016 · M more to confirm with us".
  * The Request link carries the part and the validated vehicle, and the contact message includes the customer's car.
* **Homepage Latest Parts:** only parts with a price, so the section stays hidden for now. The type and make chips follow the same rule.
* **Assistant:** searches every published part and all part numbers; shows "Price on request".

### Tests
* `parts_page_test.php` rewritten: 68 checks.
* `parts_test.php` updated: 89 checks.
* Fresh install: `db_test --fresh` on a scratch database, 106/106.
* All 27 suites pass.
* Chrome:
  * public page at 1366, 768 and 375 px: 15/15;
  * vehicle search, with one temporary compatibility row removed afterwards: 7/7;
  * admin form (part-number rows, Enter saves): 11/11.
* Copy action through the controller, CSRF included: checked.
