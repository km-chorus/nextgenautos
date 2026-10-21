SPEC 11 — PUBLIC VEHICLE INVENTORY & SEARCH
============================================================

You are continuing development of the Amponsah Autos public website.

IMPORTANT:
This is a specification-driven project.

Implement ONLY this specification.

Do NOT implement the individual Vehicle Details page yet.
That will be Spec 12.

Before making changes, read:

1. CLAUDE.md
2. PROGRESS.md

Also inspect:

- current public homepage
- public header/navigation
- public footer
- public CSS/design system
- public JavaScript
- existing Vehicle Management implementation
- vehicles database schema
- brands database/schema
- vehicle image implementation
- existing public routing
- existing vehicle card/component code if any

============================================================
1. VISUAL REFERENCE
============================================================

The user provided a screenshot of the DAT AUTOHUS vehicle
inventory page.

Use that screenshot as the PRIMARY VISUAL REFERENCE for this
inventory page.

Reference characteristics:

- automotive catalogue layout
- left-side filtering panel
- dense three-column vehicle grid
- compact vehicle cards
- large vehicle images
- vehicle information beneath images
- compact metadata
- visible pricing
- status indicators
- large inventory volume
- clean light background
- thin borders
- restrained shadows
- compact spacing
- professional dealership/catalogue feel
- pagination
- highly functional rather than promotional

Recreate the underlying layout principles and information density.

DO NOT copy:

- DAT AUTOHUS branding
- logo
- exact text
- exact vehicle information
- proprietary imagery
- exact colors
- exact UI assets
- exact CSS
- exact card artwork

Adapt the concept completely to Amponsah Autos.

============================================================
2. PAGE OBJECTIVE
============================================================

Create the public:

/vehicles

page.

This is the main Amponsah Autos vehicle inventory/catalogue.

The page should allow customers to:

- browse vehicles
- search vehicles
- filter vehicles
- sort vehicles
- inspect key vehicle information
- open an individual vehicle
- move through multiple pages of inventory

The page must use REAL vehicle data from MySQL.

No fake vehicles.

No hardcoded inventory.

============================================================
3. PAGE STRUCTURE
============================================================

Desktop structure:

------------------------------------------------------------
HEADER
------------------------------------------------------------

USED VEHICLES / VEHICLES FOR SALE

Short supporting text

------------------------------------------------------------
FILTER + INVENTORY
------------------------------------------------------------

┌────────────────┬────────────┬────────────┬────────────┐
│                │            │            │            │
│ FILTERS        │ VEHICLE    │ VEHICLE    │ VEHICLE    │
│                │ CARD       │ CARD       │ CARD       │
│ Brand          │            │            │            │
│ Model          │            │            │            │
│ Year           │            │            │            │
│ Price          │            │            │            │
│ Availability   │            │            │            │
│ Engine         │            │            │            │
│ etc.           │            │            │            │
│                │            │            │            │
└────────────────┴────────────┴────────────┴────────────┘

------------------------------------------------------------
PAGINATION
------------------------------------------------------------

Use a clean, compact pagination control.

============================================================
4. PAGE HEADING
============================================================

Use a professional heading.

Preferred:

VEHICLES

or:

VEHICLES FOR SALE

Choose the wording that best fits the existing public
navigation and brand language.

Supporting text should be concise.

Example:

Explore vehicles sourced from Canada and available through
Amponsah Autos.

Do not make unsupported claims.

============================================================
5. BACKGROUND
============================================================

Use:

Warm White:
#F8F7F4

or White:

#FFFFFF

Choose the option that best integrates with the existing
public header/footer.

The inventory page should be visually calm.

Do not use a dark full-page background.

============================================================
6. FILTER SIDEBAR
============================================================

Desktop:

Place filters on the LEFT.

The sidebar should have a controlled width.

Recommended:

260–300px

Do not make the filter panel excessively wide.

The vehicle grid should receive most of the available width.

============================================================
7. FILTERS
============================================================

Use only filters supported by the existing database.

Primary filters:

1. Brand
2. Model
3. Year
4. Price
5. Availability

Potential additional filters ONLY if the database supports them:

- Engine size
- Fuel type
- Transmission

Do not invent fields.

============================================================
8. BRAND FILTER
============================================================

Brand options MUST come from the existing:

brands

table.

Use active brands only.

Do not hardcode brand names into the template.

The approved brands currently include:

Hyundai
Honda
Toyota
Mazda
Ford
Chevrolet
Dodge
Nissan
Mercedes-Benz
BMW
Mitsubishi
GMC

But query the database rather than hardcoding these values.

============================================================
9. MODEL FILTER
============================================================

Model options should be based on actual vehicle data.

When a brand is selected:

The model filter should show only models associated with
that brand where practical.

Avoid unnecessary AJAX if the existing architecture does not
need it.

A normal server-side query/filter implementation is preferred.

Do not create a complex frontend filtering framework.

============================================================
10. YEAR FILTER
============================================================

Year values should come from actual vehicle data.

Do not hardcode:

2020
2021
2022
2023
etc.

unless those values actually exist in the database.

Order appropriately.

============================================================
11. PRICE FILTER
============================================================

Price filtering must use the actual vehicle price field.

Currency:

GHS

The website must not invent exchange rates.

Use a practical interface such as:

Minimum Price
Maximum Price

or an appropriate range control if it can be implemented
lightweight.

Do not introduce a heavy slider library.

============================================================
12. AVAILABILITY FILTER
============================================================

Use the existing vehicle availability/status values.

Approved statuses from Vehicle Management include:

AVAILABLE
RESERVED
SOLD
INACTIVE

For the public inventory:

INACTIVE vehicles should NOT appear.

SOLD vehicles may be shown only if the public business rules
allow them to remain visible.

If the current business rule does not explicitly require SOLD
vehicles in public inventory, default to active inventory only.

Do not expose internal administrative states incorrectly.

============================================================
13. FILTER SUBMISSION
============================================================

Prefer normal GET-based filtering.

Example:

/vehicles?brand=toyota&year=2024&max_price=500000

Benefits:

- shareable URLs
- SEO-friendly
- browser navigation works
- no unnecessary JavaScript
- easy server-side implementation

Do not use a SPA filtering system.

Do not require JavaScript for core filtering.

============================================================
14. SEARCH
============================================================

Provide a vehicle search input.

Search should work against relevant vehicle fields such as:

- make
- model
- trim
- possibly description

depending on the actual schema.

Do not search arbitrary database fields.

Use prepared statements.

Do not perform raw string concatenation in SQL.

============================================================
15. SORTING
============================================================

Provide a compact sorting control.

Recommended:

Newest
Price: Low to High
Price: High to Low

Only include options that can be reliably implemented.

Do not use JavaScript sorting on only the currently visible
cards.

Sorting must happen server-side.

Use whitelist validation for sort parameters.

Never allow arbitrary SQL ORDER BY input from the URL.

============================================================
16. VEHICLE GRID
============================================================

Desktop:

Three vehicle cards per row.

This is important.

The page should visually resemble the density of the supplied
reference.

Do not use four or five columns on large desktop screens if
that makes cards too small.

Three columns should remain the primary desktop layout.

At very wide screens, increase the page container width and
card breathing room rather than adding excessive columns.

============================================================
17. VEHICLE CARD
============================================================

The vehicle card should be compact and information-rich.

Recommended structure:

┌──────────────────────────┐
│                          │
│      VEHICLE IMAGE       │
│                          │
├──────────────────────────┤
│ 2024                     │
│ Toyota Land Cruiser      │
│ VX                       │
│                          │
│ 3.5L • Automatic         │
│                          │
│ GHS 1,250,000            │
│                          │
│ AVAILABLE          →     │
└──────────────────────────┘

Adapt this to the actual database fields.

============================================================
18. VEHICLE IMAGE
============================================================

Use the vehicle's existing primary image.

Do NOT use random images.

Image source should come from the existing vehicle image
system.

Preferred display:

aspect ratio approximately 4:3

or another ratio that best matches the reference and current
design.

Maintain consistent card image height.

Use:

object-fit: cover

where appropriate.

If no primary image exists:

Display a refined neutral placeholder.

Do NOT use broken image icons.

============================================================
19. VEHICLE CARD INFORMATION
============================================================

Display only useful customer-facing information.

Recommended:

- Year
- Make
- Model
- Trim
- Key specification
- Engine size if available
- Price
- Availability

Do not display every database field.

Do not expose:

- internal IDs
- timestamps
- admin information
- audit information

============================================================
20. PRICE
============================================================

Display price clearly.

Currency:

GHS

Use appropriate formatting.

Example:

GHS 1,250,000

Do not display fake converted currencies.

Do not calculate import duty here.

Do not include duty in the price unless the vehicle's
duty_included field explicitly says so.

If duty is included:

Clearly but subtly indicate:

Duty Included

If duty is not included:

Do not incorrectly imply that it is.

============================================================
21. AVAILABILITY
============================================================

Use subtle status indicators.

Examples:

AVAILABLE
RESERVED
SOLD

Do not use giant colored badges.

Use compact typography or restrained labels.

Use the established accent color sparingly.

============================================================
22. CARD LINK
============================================================

Each vehicle card should link to its individual vehicle page.

Use the vehicle's unique slug.

Example:

/vehicles/toyota-land-cruiser-2024

The exact routing must follow the existing project architecture.

Do not expose database IDs in public URLs if slug routing is
already supported.

Use semantic anchors.

============================================================
23. CARD HOVER
============================================================

Keep interaction subtle.

Possible:

- image slightly darkens
- arrow moves slightly
- border changes
- title becomes slightly stronger

Do not use:

- dramatic zoom
- 3D transforms
- glow
- excessive shadows
- animated gradients

============================================================
24. GRID SPACING
============================================================

The vehicle catalogue should be relatively dense like the
reference.

Do not create excessive whitespace between cards.

However, do not make the cards touch each other.

Use consistent:

- column gap
- row gap
- image spacing
- text spacing

The result should feel like a professional automotive
inventory system.

============================================================
25. FILTER DESIGN
============================================================

The filter sidebar should NOT look like a Bootstrap form.

Use:

- custom labels
- custom inputs
- custom select styling
- restrained borders
- subtle focus states
- clean spacing

Avoid:

- giant rounded fields
- default browser selects
- Bootstrap blue
- excessive shadows

============================================================
26. FILTER ACTIONS
============================================================

Provide:

Apply Filters

and:

Clear Filters

Apply Filters should submit the GET form.

Clear Filters should return to:

/vehicles

Use a normal link for clear filters where possible.

Do not require JavaScript.

============================================================
27. MOBILE FILTER EXPERIENCE
============================================================

On mobile:

Do NOT display the full sidebar permanently above the
vehicle grid.

Instead provide:

[ FILTERS ] [ SORT ]

or an equivalent compact control row.

Clicking FILTERS can open a lightweight filter drawer or
expandable panel.

Use vanilla JavaScript only if required.

The core filter form should still work without complex JS.

The drawer must:

- be easy to close
- trap focus if implemented as a modal
- have a visible close button
- not create horizontal overflow
- preserve selected filter values

Do not use a third-party modal/drawer library.

============================================================
28. MOBILE VEHICLE GRID
============================================================

Recommended:

Two columns on larger mobile widths where cards remain
readable.

One column on very narrow screens if necessary.

Do not force three columns on mobile.

Example:

320px:
1 column

375px:
1 or 2 depending on actual card readability

390px:
2 columns if comfortable

414px:
2 columns

768px:
2–3 columns

Desktop:
3 columns

Use actual visual testing to determine the breakpoint.

============================================================
29. PAGINATION
============================================================

Do not load the entire vehicle inventory at once.

Use server-side pagination.

Recommended:

12 vehicles per page

or another appropriate number based on the final visual
density.

Pagination must preserve active filters and sorting.

Example:

/vehicles?page=2&brand=toyota&sort=price_asc

Use prepared queries.

Avoid loading hundreds of vehicle records into PHP.

============================================================
30. RESULT COUNT
============================================================

Show a concise result count.

Example:

24 vehicles

or:

Showing 24 vehicles

Do not fake the number.

The count must come from the database query.

============================================================
31. EMPTY STATE
============================================================

If no vehicles match the filters:

Display a professional empty state.

Example:

No vehicles match your current filters.

Try adjusting your search criteria or clearing some filters.

Provide:

Clear Filters

Do not display a blank page.

Do not create fake fallback vehicles.

============================================================
32. LOADING
============================================================

Because the primary implementation is server-rendered,
do not create an unnecessary client-side loading system.

If AJAX is not used:

No loading spinner is required.

Use normal browser navigation.

============================================================
33. SEO
============================================================

The Vehicles page should have:

- meaningful title
- meta description
- canonical URL
- semantic headings
- crawlable vehicle links

Example title:

Vehicles for Sale | Amponsah Autos

Do not duplicate titles dynamically without reason.

Filtered URLs may use appropriate canonical handling
depending on the SEO strategy.

Do not create duplicate-content problems unnecessarily.

============================================================
34. PERFORMANCE
============================================================

This page must remain lightweight.

HARD REQUIREMENTS:

- server-rendered PHP
- MySQL/PDO
- vanilla JavaScript
- minimal CSS
- optimized WebP images
- lazy-load vehicle images below the first visible row
- responsive image dimensions where practical
- server-side pagination
- efficient queries
- indexed filter columns where already supported
- no unnecessary API calls

Do NOT add:

- React
- Vue
- Next.js
- jQuery
- vehicle carousel library
- heavy filtering library
- animation library
- infinite scroll

============================================================
35. DATABASE QUERY PERFORMANCE
============================================================

Use the existing repository/service architecture.

Do not query each vehicle's images individually.

Avoid N+1 queries.

Fetch the primary image efficiently.

Use:

JOIN

or an appropriate efficient query.

Do not retrieve full image galleries for the inventory page.

Only retrieve the image needed for each card.

============================================================
36. SECURITY
============================================================

All filters and query parameters are untrusted input.

Requirements:

- prepared statements
- parameter binding
- whitelist sort values
- validate numeric ranges
- validate year values
- validate brand IDs
- validate pagination
- escape output
- no raw SQL concatenation
- no arbitrary ORDER BY input
- no user-controlled SQL

============================================================
37. BOOTSTRAP RESTRICTION
============================================================

HARD REQUIREMENT:

Do not use Bootstrap's default visual appearance.

The page must NOT look like:

Bootstrap navbar
+
Bootstrap sidebar
+
Bootstrap cards
+
Bootstrap buttons.

Use the Amponsah Autos custom design system.

Bootstrap may exist in the project if already required,
but public visual styling must be custom.

============================================================
38. DESIGN LANGUAGE
============================================================

The inventory page is more functional than the homepage.

Use the supplied reference for:

- density
- catalogue structure
- filter placement
- card proportions
- information hierarchy

Use Amponsah Autos for:

- typography
- colors
- branding
- buttons
- borders
- status styling
- spacing
- overall visual identity

The result should feel like:

PREMIUM DIGITAL SHOWROOM

not:

GENERIC E-COMMERCE STORE.

============================================================
39. RESPONSIVE TESTING
============================================================

Test:

320px
375px
390px
414px
768px
834px
1024px
1280px
1440px+

Verify:

[ ] No horizontal overflow
[ ] Filter works
[ ] Search works
[ ] Sort works
[ ] Pagination works
[ ] Vehicle cards remain readable
[ ] Images maintain proportions
[ ] Price remains visible
[ ] Vehicle title does not overflow badly
[ ] Mobile filter opens/closes correctly
[ ] Selected filters persist
[ ] Empty state works
[ ] Clear filters works
[ ] Desktop three-column grid works
[ ] Tablet grid works
[ ] Mobile grid works

============================================================
40. FUNCTIONAL TESTING
============================================================

Test:

[ ] All active vehicles appear.

[ ] Inactive vehicles are excluded.

[ ] Brand filtering works.

[ ] Model filtering works.

[ ] Year filtering works.

[ ] Price filtering works.

[ ] Availability filtering works.

[ ] Search works.

[ ] Sorting works.

[ ] Pagination works.

[ ] Result count is accurate.

[ ] Filters persist between pages.

[ ] Vehicle cards link to correct slugs.

[ ] Missing images are handled.

[ ] Missing optional fields do not break cards.

[ ] No fake data exists.

============================================================
41. DO NOT IMPLEMENT
============================================================

Do NOT implement:

- Vehicle Details page
- vehicle booking
- purchasing
- payment
- duty calculation
- live tracking
- shipment management
- customer accounts
- wishlist
- compare vehicles
- financing
- auction functionality
- chat
- AI recommendations

These are outside Spec 11.

============================================================
42. HOMEPAGE INTEGRATION
============================================================

The homepage vehicle search should eventually link into this
page.

However, do not redesign the homepage in this specification.

Ensure the Vehicles page accepts compatible GET parameters
where practical, such as:

brand
model
year
min_price
max_price
availability
sort
page

This will allow the existing homepage search form to connect
cleanly later.

============================================================
43. PROGRESS UPDATE
============================================================

After successful implementation and testing, update:

PROGRESS.md

Record:

Spec 11 — Public Vehicle Inventory & Search

Status:
COMPLETED

Include:

- inventory page
- real database-driven vehicle catalogue
- left filter sidebar
- search
- filtering
- sorting
- three-column desktop grid
- responsive mobile grid
- mobile filter experience
- pagination
- result counts
- vehicle cards
- real vehicle images
- SEO
- accessibility
- performance
- security
- testing

Then identify:

Next:
Spec 12 — Public Vehicle Details

============================================================
44. STOP
============================================================

Implement ONLY Spec 11.

Use the uploaded AutoHUS screenshot as the visual reference
for the inventory layout and density.

Do not copy its branding or content.

Test thoroughly.

Fix all issues within this specification.

Update PROGRESS.md.

Do NOT implement Vehicle Details yet.

STOP.