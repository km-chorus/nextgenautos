# SPEC 10B — HOMEPAGE FEATURED VEHICLES / INVENTORY PREVIEW

## 1. PURPOSE

Build the next homepage section immediately below the hero:

**Featured Vehicles / Latest Vehicles**

The section must use **real vehicle data from the existing MySQL database**.

Do not create fake vehicle records or hardcoded business data.

The section should establish Amponsah Autos as a real vehicle-focused business and provide a natural transition from the hero into the actual inventory.

---

# 2. IMPORTANT DESIGN REFERENCE

Continue using DAT AUTOHUS as a **structural and professional automotive reference**.

The goal is to capture the feeling of a serious vehicle business:

* vehicle-first
* clean
* professional
* image-led
* concise
* easy to scan
* useful

Do NOT copy the exact card design, colors, spacing, wording or layout.

Maintain the established Amponsah Autos visual identity.

---

# 3. SECTION POSITION

Place this section directly below the homepage hero.

The visual flow should be:

```text
HEADER
   ↓
HERO / IMAGE CAROUSEL
   ↓
FEATURED VEHICLES
```

Do not insert another unnecessary section between the hero and featured vehicles.

---

# 4. SECTION INTRODUCTION

Use a concise section heading.

Recommended:

**Featured Vehicles**

Supporting text:

**Explore some of the latest vehicles available through Amponsah Autos.**

Keep the introduction short.

Do not use a large paragraph.

The section should get visitors to the vehicles quickly.

---

# 5. REAL DATABASE DATA

Retrieve vehicles from the existing database.

Use the existing architecture:

```text
Homepage
   ↓
Controller
   ↓
Service
   ↓
Repository / Model
   ↓
PDO
   ↓
MySQL
```

Do not place raw SQL queries directly inside the homepage template.

Use prepared statements.

Reuse existing Vehicle Management architecture where appropriate.

Do not create a second vehicle data-access system.

---

# 6. VEHICLE SELECTION

The homepage should display a small curated set rather than the entire inventory.

Recommended:

**4 vehicles on desktop**

The implementation may retrieve a slightly larger number if required for responsive behavior, but do not load the entire inventory.

Order by the most appropriate existing vehicle field, preferably:

```text
created_at DESC
```

unless the existing application already has an approved featured/display-order mechanism.

Do not invent a new "featured" database field just for this specification unless there is a strong architectural reason.

---

# 7. ELIGIBLE VEHICLES

Only show vehicles that should be publicly visible.

Do not expose:

* inactive vehicles
* deleted vehicles
* incomplete records
* vehicles that are explicitly unavailable for public display

Use the existing vehicle status/availability conventions.

Inspect the actual database schema before writing the query.

Do not assume column names if the existing implementation already defines them differently.

---

# 8. VEHICLE CARD INFORMATION

Each vehicle should display only the information that helps a customer make an initial decision.

Recommended:

### Image

Primary vehicle image.

### Vehicle identity

Example structure:

**2023 Toyota RAV4**

Then:

**XLE**

if a trim exists.

### Key specification

For example:

**2.5L • Automatic**

Only display information that actually exists.

### Price

Display the stored price and currency.

Do not calculate or modify the price.

### Availability

Use a subtle status indication where useful.

Do not fill the card with badges.

### CTA

**View Vehicle**

The entire card should not necessarily be one giant clickable area if that harms accessibility. The primary vehicle link should be obvious.

---

# 9. VEHICLE IMAGES

Use the existing vehicle image management system.

Retrieve the appropriate primary image.

Do not:

* create duplicate image systems
* upload new images in this specification
* process images in the homepage
* load every gallery image

Only the required primary image should be used.

If a vehicle does not have a primary image:

Use a clean, lightweight fallback state.

Do not show a broken image.

Do not invent a vehicle photograph.

---

# 10. IMAGE PERFORMANCE

Homepage vehicle images must be optimized.

Use:

* appropriate dimensions
* WebP where available
* responsive image sizing where practical
* lazy loading for below-fold vehicle images

The hero remains the highest image priority.

Do not allow vehicle images to significantly slow down the initial page load.

---

# 11. CARD DESIGN

Cards should be **minimal and editorial**.

Do NOT create generic SaaS-style cards.

Avoid:

* excessive rounded corners
* huge shadows
* floating badges
* excessive icons
* gradient overlays
* decorative shapes
* multiple nested containers
* excessive metadata

Use the image as the dominant visual element.

The information below the image should be clean and easy to scan.

---

# 12. CARD LAYOUT

Recommended structure:

```text
┌──────────────────────────────┐
│                              │
│        VEHICLE IMAGE         │
│                              │
└──────────────────────────────┘

2023 Toyota RAV4
XLE

2.5L • Automatic

GHS / CAD [actual price]

View Vehicle →
```

The exact presentation can be refined visually, but maintain this level of simplicity.

---

# 13. RESPONSIVE LAYOUT

Desktop:

Approximately 4 vehicles in a row where the viewport allows.

Tablet:

Approximately 2 vehicles per row.

Mobile:

Prefer a clean single-column layout.

Do not create a horizontal scrolling carousel for vehicles unless there is a genuine UX reason.

The section should remain lightweight.

---

# 14. VIEW ALL VEHICLES

After the vehicle preview, provide a simple CTA:

**View All Vehicles**

This should lead to the public vehicle inventory page.

Do not make this another huge marketing button.

A restrained button or text link is preferred.

---

# 15. EMPTY STATE

If there are currently no publicly available vehicles, do not display fake cards.

Instead show:

**Vehicles Coming Soon**

Supporting message:

**We're preparing our latest available vehicles. Check back soon.**

Keep the empty state visually clean.

Do not make it look like an error.

---

# 16. PARTIAL INVENTORY

If fewer than four eligible vehicles exist:

* display the vehicles that actually exist
* do not duplicate cards
* do not create placeholders
* do not fabricate inventory

The layout should gracefully handle:

* 1 vehicle
* 2 vehicles
* 3 vehicles
* 4+ vehicles

---

# 17. VEHICLE DETAIL LINK

Each vehicle must link to its actual public vehicle detail URL.

Use the existing slug/routing architecture.

Do not construct unsafe URLs directly from unsanitized database values.

The URL should be SEO-friendly.

Example concept:

```text
/vehicles/2023-toyota-rav4-xle
```

Use the actual routing convention already established by the project.

---

# 18. DATA QUERY PERFORMANCE

Only retrieve the fields needed by the homepage.

Do not use:

```sql
SELECT *
```

unless the existing repository architecture genuinely requires it and there is a documented reason.

Prefer selecting:

* vehicle ID
* brand
* model
* year
* trim
* engine size
* price
* currency
* status/availability
* slug
* primary image information

Use an efficient query.

Avoid N+1 queries.

If vehicle images require a separate query, design it efficiently rather than querying once per vehicle.

---

# 19. ARCHITECTURE

Follow the existing application architecture.

Do not put business logic inside:

```text
/public/index.php
```

Do not put SQL inside:

```text
homepage.php
```

Use the appropriate:

```text
Controller
Service
Repository / Model
View
```

layers already established.

---

# 20. PUBLIC VISIBILITY

Only publicly appropriate vehicle information should be displayed.

Do not expose internal admin information.

Do not expose:

* internal IDs unnecessarily
* internal notes
* audit information
* admin-only fields
* supplier information
* private customer information
* internal shipment information

The public card should contain customer-facing information only.

---

# 21. TYPOGRAPHY

Continue the lighter typography direction established in Spec 10A.

Use Manrope.

Prefer:

* 400
* 500
* 600

Avoid making every vehicle name extremely bold.

The vehicle name can receive moderate emphasis.

Metadata should be lighter and smaller.

---

# 22. COLORS

Continue using:

```text
Deep Charcoal  #111315
Warm White     #F8F7F4
White          #FFFFFF
Secondary      #62666A
Border         #E3E3DF
Accent         #9E2F2F
```

No purple.

No unnecessary gradients.

No generic automotive blue theme.

Use the accent color sparingly.

---

# 23. SPACING

Maintain the existing spacing system:

```text
4
8
12
16
24
32
40
48
64
80
96
120
```

The section should have comfortable whitespace without becoming unnecessarily tall.

---

# 24. NO EXCESSIVE BADGES

Do not turn each vehicle card into a collection of badges.

Avoid displaying:

* Featured
* Hot
* New
* Popular
* Best Deal
* Canada
* Imported
* Verified

unless there is an actual approved business/data reason for the specific badge.

The vehicle itself should be the focus.

---

# 25. NO FAKE DATA

This is critical.

Do not create:

* fake vehicle names
* fake prices
* fake mileage
* fake specifications
* fake availability
* fake images
* fake customer reviews

If the database contains no public vehicles, use the approved empty state.

---

# 26. ACCESSIBILITY

Ensure:

* semantic section heading
* proper heading hierarchy
* accessible links
* descriptive image alt text
* keyboard navigation
* visible focus states
* adequate contrast
* no information conveyed through color alone

Vehicle links must clearly communicate what vehicle they lead to.

---

# 27. PERFORMANCE

Keep this section lightweight.

Do not introduce:

* vehicle carousel libraries
* animation libraries
* unnecessary JavaScript
* huge images
* unnecessary API requests

Prefer server-rendered HTML.

The vehicle cards should be usable even if JavaScript is disabled.

---

# 28. ANIMATION

Keep motion extremely subtle.

Optional:

* small image transition
* subtle hover state
* slight link transition

Do not use:

* cards flying into view
* parallax
* scroll-triggered animation libraries
* excessive hover transformations
* image zoom animations
* bouncing elements

The website should feel fast and mature.

---

# 29. TESTING

Test with:

### Database

* 0 vehicles
* 1 vehicle
* 2 vehicles
* 3 vehicles
* 4 vehicles
* more than 4 vehicles

### Vehicle states

Confirm inactive/unavailable records are handled correctly according to the existing public visibility rules.

### Images

Test:

* vehicle with primary image
* vehicle without image
* missing image file
* multiple vehicle images

### Responsive

Test:

```text
320px
375px
390px
414px
768px
1024px
1280px
1440px+
```

### Security

Verify:

* prepared statements
* output escaping
* safe vehicle URLs
* no internal data exposure

### Performance

Check:

* number of DB queries
* image loading
* page load
* no unnecessary JavaScript
* no console errors

### Regression

Verify:

* Spec 09 public foundation
* Spec 10A hero
* admin login
* admin dashboard
* vehicle management
* team management

continue working correctly.

---

# 30. PROGRESS UPDATE

After successful implementation and testing, update:

```text
PROGRESS.md
```

Record:

**Spec 10B — Homepage Featured Vehicles / Inventory Preview — Completed**

Include:

* implementation summary
* files changed
* database/query changes
* testing performed
* known limitations

Next specification:

**Spec 10C — Homepage Browse by Brand**

---

# 31. STOP CONDITION

After completing Spec 10B:

**STOP.**

Do not automatically implement:

* Browse by Brand
* How It Works
* About
* Team
* Tracking
* Contact
* FAQ
* additional homepage sections

Wait for the next approved specification.
