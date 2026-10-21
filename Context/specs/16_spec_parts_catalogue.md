# SPEC 16 — CAR PARTS CATALOGUE (ADMIN + PUBLIC)

Status: APPROVED by the owner on 2026-10-14 (all PROPOSED items accepted as written; sign-in not required; parts: Contact + WhatsApp only; stock: in stock / out of stock only) (written 2026-10-14). Moves "Spare-parts inventory" from out of scope into scope
(owner decision 2026-10-14: the parts catalogue will be in the admin and on the site). Depends on Specs 07 (image pipeline),
14 and 15 (enquiries). Items marked **PROPOSED** need the owner's yes/no.

## 1. Purpose

Let administrators list car parts for popular makes (for example Toyota, Honda, Hyundai), and let customers find a part that fits their car, see its price, and contact the company about it.

## 2. Owner decisions (2026-10-14)

Each part records: compatible make / model / year range, condition (new / used), a public price, a part number and a category.

## 3. Data model (new migration)

* `part_categories`: id, name (unique), slug (unique), display_order, status (active/inactive). **PROPOSED** seed: Engine, Transmission, Brakes, Suspension & Steering, Electrical, Cooling, Body & Lights, Interior, Filters & Service, Wheels & Tyres. Managed in the admin (list / add / edit / deactivate).
* `parts`: id, category_id (FK RESTRICT), name, part_number (NULL allowed), condition ENUM('new','used'), price DECIMAL(12,2) ≥ 0, currency CHAR(3) default GHS, stock_status **PROPOSED** ENUM('in_stock','out_of_stock') (no quantities: no inventory accounting), description TEXT NULL, is_published (default 0), slug (unique), deleted_at, created_at, updated_at.
* `part_fitments`: id, part_id (FK CASCADE), brand_id (FK RESTRICT, existing brands), model VARCHAR(100) NULL (NULL = all models of that make), year_from / year_to SMALLINT NULL (NULL = open). A part can have several fitments ("Toyota Corolla 2014–2018", "Toyota Auris 2013–2018").
* `part_images`: same shape and rules as `vehicle_images` (one primary, ordered, alt text), processed by the existing `ImageService` (WebP + thumbnail).
* `enquiries`: add `part_id` INT UNSIGNED NULL (FK RESTRICT) and enum value `part`; a row links to a vehicle OR a part OR neither (CHECK).

## 4. Admin

* Sidebar item **Parts** (and **Part categories** under it).
* Parts list: search (name, part number), filters (category, make, condition, stock, published), pagination; add, edit, view, publish/unpublish, stock status, archive (soft delete; a part with enquiries is archived, never hard-deleted).
* Form sections: Part (name, part number, category, condition), Fitment (repeatable make / model / year from–to rows), Pricing (price, currency), Stock, Description, Photos (reuse the Spec 07 upload/gallery UI).
* Audit log for create / update / publish / archive.

## 5. Public site

* Navigation: **Parts** added to the main menu (`/parts/`).
* `/parts/`: grid of part cards (photo, name, category, condition, fits "Toyota Corolla 2014–2018" (first fitment + "and N more"), price, stock). Filters: make → model → year (find parts that fit my car), category, condition, price range, search; sort newest / price. Same layout and card system as the Vehicles page.
* `/part/{slug}/`: gallery, name, part number, category, condition, price, stock, full fitment list, description; actions **WhatsApp** (pre-filled message naming the part and link) and **Contact about this part** (enquiry form, `enquiry_type = 'part'`, same protections as Spec 14). **PROPOSED**: no reservation/order for parts.
* Visibility: published, not archived. Out-of-stock parts are shown with "Out of stock" **PROPOSED** (still enquirable), not hidden.
* Homepage: the "Car parts too" trust card links to `/parts/` **PROPOSED**.
* Empty state: "Parts coming soon" when no published parts exist.

## 6. Security

Same as Specs 06/07/14: `requireAdmin()`, CSRF, prepared statements, server validation (prices, year ranges year_from ≤ year_to, 1900–2100, brand ids exist and are active), upload validation via the existing pipeline, output escaping, slug validation, rate-limited public forms.

## 7. Testing

Schema (FKs, CHECKs, cascade/restrict), admin CRUD + fitments + images, publishing, archive rule, public list filters (fitment matching: model NULL = all models, open year ranges), detail page, enquiries linked to parts, security (CSRF, IDOR, XSS, upload), responsive 320–1440, regression (vehicles, brands, enquiries, dashboard).

## 8. Out of scope

Online ordering or payment, cart, stock quantities, supplier management, VIN decoding, automatic fitment data, shipping of parts.

## 9. Phase 1 — implemented 2026-10-16 (owner request + decisions of 2026-10-16)

Owner request: "Latest Parts and Accessories" section after Browse by Brand, with two toggles (Browse by type / Browse by brands); each part shows the vehicles it is compatible with.

Owner decisions (2026-10-16): build the admin + homepage now (phase 1); "brands" = the vehicle makes a part fits (existing brands table); the toggles are tabs that filter the cards (and work without JavaScript); an **Accessories** category is added to the starting list.

Built in phase 1:

* Migration `006_parts_catalogue.sql`: `part_categories` (11 seeded, incl. Accessories), `parts`, `part_fitments`, `part_images` exactly as section 3 (enquiries.part_id is NOT added yet: phase 2).
* Admin (sidebar **Parts**, permission `parts.manage`): list with search / type / make / website / stock filters and pages; add and edit (details, compatible vehicles rows, price, description, up to 8 photos through the shared image pipeline, publish); publish/unpublish and stock from the list; archive with a confirmation page (never hard-deleted); photo remove / make main; part categories page (add, rename, reorder, deactivate; never deleted). Every change audited.
* Homepage **Latest Parts & Accessories** after Browse by Brand: up to 8 newest published parts; tabs "Browse by type" / "Browse by brand" with chips for the types and makes that actually have public parts; each card shows type, condition, part number, stock, price and "Compatible with" (up to 3 vehicles + "and N more"; a part with no compatible vehicles says "Contact us to confirm it fits your vehicle", never "fits all"). Actions: **Ask about this part** (Contact page, topic "Car parts", message naming the part) and WhatsApp when a number is set. "View All Parts & Accessories" goes to `/parts/` (still the coming-soon page).
* Fitment display: model empty = "all models"; open years read "2015 onwards" / "up to 2012".
* Part photos minimum 600 x 450 (vehicle photos stay 800 x 600).

Phase 2 (not built): public `/parts/` list with fit-my-car filters, `/part/{slug}/` page with gallery and enquiry form, `enquiries.part_id`, cards linking to the part page, photo ordering and alt-text editing.
