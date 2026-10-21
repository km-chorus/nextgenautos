You are continuing development of the Amponsah Autos public website.

IMPORTANT:
This is a specification-driven project. Do not redesign unrelated parts of the website. Implement ONLY the scope defined in this specification.

Before making any changes, read and follow:

1. CLAUDE.md
2. PROGRESS.md
3. 

Also inspect the existing public website code, especially:

- current homepage
- public layout/header/footer
- existing brand model/repository/service
- existing brand database table
- existing public routing
- existing vehicle filtering
- existing assets structure
- current CSS/JS architecture

Do not assume the current implementation matches the documentation. Inspect the actual code first.

==================================================
SPEC 10C — HOMEPAGE BROWSE BY BRAND
==================================================

OBJECTIVE

Build the next major homepage section immediately below the existing hero/vehicle search section.

The section allows customers to browse vehicles by automotive brand.

The section must display the actual 12 approved brands using real manufacturer logos and allow customers to horizontally swipe/scroll through the brands.

The visual quality must feel like a professional automotive website / digital showroom, not a generic Bootstrap component.

The section must be lightweight, responsive, accessible, database-driven and production-ready.

==================================================
1. HOMEPAGE FLOW
==================================================

The homepage flow should now be:

Hero + Vehicle Search
        ↓
Browse by Brand
        ↓
How It Works
        ↓
Remaining approved homepage sections

Do NOT reintroduce the Featured Vehicles section.

Do NOT add featured vehicle cards.

Do NOT modify the existing hero search functionality unless a change is absolutely required for integration.

Do NOT modify the existing navbar unless required for this section.

==================================================
2. SECTION CONTENT
==================================================

Create a section with approximately:

Heading:

Browse by Brand

Supporting text:

Explore vehicles from the brands we source.

Keep the copy concise.

Do not invent marketing claims such as:

- "The world's leading brands"
- "Over 1,000 vehicles"
- "Best prices"
- "Official dealer"
- "Trusted by thousands"

unless such claims are actually supported by the business.

The section should visually communicate:

Browse → Select Brand → View Vehicles

==================================================
3. BRANDS MUST COME FROM THE DATABASE
==================================================

Do NOT hardcode the brand list in the homepage template.

Use the existing Brands architecture and database.

Retrieve:

- active brands only
- ordered by display_order
- using the existing repository/model/service architecture
- using PDO/prepared statements

The approved brands are:

1. Hyundai
2. Honda
3. Toyota
4. Mazda
5. Ford
6. Chevrolet
7. Dodge
8. Nissan
9. Mercedes-Benz
10. BMW
11. Mitsubishi
12. GMC

Do not create duplicate brand data.

Inspect the existing `brands` table and existing Brand Management implementation before writing new code.

Reuse the existing brand slug.

Do not create another brand data source.

==================================================
4. REAL BRAND LOGOS
==================================================

This is an important requirement.

Claude must install the actual manufacturer logos into the project.

Do NOT use:

- text styled to look like logos
- emoji
- generic automobile icons
- AI-generated fake logos
- one composite image containing all logos
- random low-quality logo screenshots
- logos copied from another website's page layout
- placeholder circles containing letters

Use legitimate, recognizable manufacturer logo assets.

Prefer SVG format wherever possible because the logos are small UI assets and SVG provides:

- sharp rendering
- small file size
- excellent scaling
- good performance

Create/use an asset structure similar to:

/assets/images/brands/

with:

hyundai.svg
honda.svg
toyota.svg
mazda.svg
ford.svg
chevrolet.svg
dodge.svg
nissan.svg
mercedes-benz.svg
bmw.svg
mitsubishi.svg
gmc.svg

IMPORTANT:

Before creating this structure, inspect whether the existing database already contains a brand logo/image path.

If the existing Brands implementation already supports a logo/image reference:

- reuse that system
- do not create a competing logo system
- make the public homepage consume the existing brand logo reference

If the current brand system does NOT contain usable logo references, use a clean public asset mapping compatible with the existing architecture.

The final implementation must make it easy to replace a logo later without changing PHP templates.

==================================================
5. SOURCING BRAND LOGOS
==================================================

When sourcing the logos:

- use high-quality official or reputable brand logo assets
- prefer official brand resources where reasonably available
- SVG is strongly preferred
- verify each logo visually and by filename
- ensure the correct logo is assigned to the correct brand
- do not accidentally use dealership logos
- do not use vehicle model logos instead of manufacturer logos

Pay attention to:

Mercedes-Benz vs Mercedes-AMG
BMW vs BMW M
GMC vs individual GMC models
Chevrolet vs Chevrolet dealership logos
Toyota vs Toyota dealership logos

The section is for manufacturer brands.

If a source has usage/licensing restrictions, do not blindly copy protected website assets. Use an appropriate legitimate logo source suitable for website use.

Do not download an entire third-party website or introduce unnecessary external dependencies.

The finished website should serve the logos locally from the project rather than depending on remote image URLs.

==================================================
6. LOGO PRESENTATION
==================================================

The logos should look premium and restrained.

Do not make the logos enormous.

Each brand should have a consistent visual area.

For example:

-----------------------------------------------
|                                             |
|              [ BRAND LOGO ]                 |
|                                             |
|                 Toyota                      |
-----------------------------------------------

However, the brand name should only be displayed if it improves usability/design.

The actual logo must remain the primary visual element.

Use:

- consistent logo container dimensions
- object-fit: contain
- appropriate internal padding
- consistent vertical alignment
- consistent spacing

Do not distort logos.

Do not stretch logos.

Do not crop logos.

Do not use different container sizes for different brands just because some logos are wider.

The logo should remain recognizable at desktop and mobile sizes.

==================================================
7. VISUAL DESIGN
==================================================

The section must follow the existing Amponsah Autos design system.

Use:

Deep Charcoal:
#111315

Warm White:
#F8F7F4

White:
#FFFFFF

Secondary text:
#62666A

Border:
#E3E3DF

Accent:
#9E2F2F

Typography:

Manrope.

Do not introduce another font.

The section should feel:

- premium
- automotive
- established
- clean
- editorial
- calm
- professional

It should NOT feel:

- like a SaaS dashboard
- like Bootstrap
- like a generic e-commerce carousel
- playful
- colorful
- overly rounded
- overly animated

Do not use:

- purple
- neon colors
- glassmorphism
- excessive gradients
- giant rounded cards
- excessive shadows
- glowing effects
- 3D effects
- decorative icons
- emojis

==================================================
8. HORIZONTAL BRAND SCROLLER
==================================================

The brands must be displayed horizontally.

Do NOT squeeze all 12 brands into one desktop row.

The user specifically wants customers to be able to swipe/scroll left and right.

Desktop:

Show a horizontal strip of brand items.

Provide left/right navigation controls.

Mobile:

Allow natural horizontal touch scrolling.

Customers should be able to swipe left/right with their finger.

Use native browser scrolling rather than a heavy carousel library.

Preferred approach:

CSS:

overflow-x: auto;

and, where appropriate:

scroll-snap-type: x mandatory;

JavaScript should only provide lightweight enhancements such as:

- left/right arrow scrolling
- detecting scroll boundaries
- disabling an arrow when the beginning/end is reached

Do NOT install:

- Swiper
- Slick
- Owl Carousel
- Splide
- Embla
- another carousel dependency

The project is intentionally lightweight.

==================================================
9. BRAND ITEM DESIGN
==================================================

Do not make each brand look like a huge generic card.

Think of the section as a premium automotive brand selector.

Example conceptual layout:

Browse by Brand
Explore vehicles from the brands we source.

←

[ Toyota ] [ Honda ] [ Hyundai ] [ Ford ] [ BMW ] [ Mercedes-Benz ] →

The individual brand items can use:

- subtle border
- restrained background
- controlled padding
- clean logo presentation
- subtle hover/focus state

The visual separation should be subtle.

Avoid:

- huge card shadows
- oversized border-radius
- badges
- decorative icons
- unnecessary descriptions
- price information
- vehicle counts unless real data is already available and specifically useful

==================================================
10. BRAND NAVIGATION
==================================================

Every brand item must be clickable.

When a customer selects a brand, they should be taken to the appropriate public vehicle listing/brand page.

Use the existing public routing architecture.

Do NOT invent a new routing system if an existing brand route already exists.

If the current project has dynamic brand routes, use them.

If the current vehicle listing already supports:

/vehicles?brand=toyota

or an equivalent structure, reuse that architecture.

The important behavior is:

Click Toyota
        ↓
Toyota vehicle listing
        ↓
Customer sees Toyota vehicles

Click BMW
        ↓
BMW vehicle listing

etc.

The selected brand must actually filter the vehicles.

Do not create fake brand pages containing hardcoded vehicles.

==================================================
11. DESKTOP ARROWS
==================================================

Provide left/right controls for the horizontal brand strip on desktop.

Requirements:

- visually subtle
- vertically centered against the brand strip
- accessible
- keyboard accessible
- proper button elements
- clear accessible labels
- disabled state when no more content exists in that direction

Example accessible labels:

"Scroll brands left"

"Scroll brands right"

Do not use text arrows like:

< >
unless they are presented as proper accessible controls.

Use the project's existing icon approach.

Do not introduce an icon library just for this section.

==================================================
12. MOBILE BEHAVIOR
==================================================

At mobile widths:

- 320px
- 375px
- 390px
- 414px

The brand strip must remain usable.

Users should be able to:

- swipe horizontally
- tap a brand
- see enough of the next brand item to understand that horizontal scrolling exists

Do not force all brands into a tiny row.

Do not shrink logos until they become unreadable.

Do not create horizontal page overflow outside the brand strip.

The section itself must remain inside the viewport.

If appropriate, hide the browser scrollbar visually while preserving native scrolling and accessibility.

==================================================
13. SCROLL EXPERIENCE
==================================================

The horizontal interaction should feel intentional.

Use:

- smooth scrolling where appropriate
- scroll snapping on touch devices if it improves the experience
- reasonable scroll distance for arrow controls

Do not use:

- auto-scrolling brands
- infinite looping
- continuous marquee
- automatic movement
- aggressive animation

The user should control the interaction.

==================================================
14. RESPONSIVE LAYOUT
==================================================

Test at:

320px
375px
390px
414px
768px
1024px
1280px
1440px+

Desktop should feel spacious but controlled.

Tablet should remain comfortable.

Mobile should prioritize touch interaction.

Do not simply shrink the desktop layout.

==================================================
15. PERFORMANCE
==================================================

This section must remain lightweight.

Requirements:

- local assets
- SVG logos preferred
- no carousel library
- no external JavaScript library
- no unnecessary dependencies
- minimal JavaScript
- no large image files
- no unnecessary animation
- no remote logo requests during normal page rendering

The 12 logos are small assets and should not materially affect page performance.

Do not load enormous PNG/JPEG versions when an SVG equivalent is available.

==================================================
16. ACCESSIBILITY
==================================================

The section must be accessible.

Requirements:

- semantic section
- proper heading hierarchy
- actual links for brand navigation
- keyboard navigation
- visible focus states
- accessible arrow buttons
- meaningful alt text for logos

Example:

alt="Toyota"

Do not use:

alt="image"

Do not use empty alt text if the logo itself is the interactive brand link unless the brand name is already exposed accessibly in the link.

Ensure keyboard users can access every brand.

Ensure focus is visible.

==================================================
17. SECURITY
==================================================

Continue following the existing security architecture.

Do not introduce:

- raw SQL in views
- unsafe query construction
- unsanitized output
- user-controlled file paths
- arbitrary remote image URLs

Brand slugs and database values must be safely handled.

Escape output correctly.

==================================================
18. ARCHITECTURE
==================================================

Follow:

Browser
   ↓
Public Controller/Entry
   ↓
Service
   ↓
Repository/Model
   ↓
PDO
   ↓
MySQL
   ↓
View

Do not put database queries directly into the homepage template.

Do not create a second database connection.

Do not duplicate the Brand repository.

Reuse existing services and models where possible.

Avoid N+1 queries.

One efficient brand query should be sufficient.

==================================================
19. EMPTY / FAILURE STATE
==================================================

The homepage must not break if no active brands are available.

Create a graceful fallback such as:

"Brand selection is currently unavailable."

However, do not display an empty decorative carousel.

Do not insert fake brands.

If one individual logo is missing:

- do not break the entire section
- use the existing fallback strategy if one exists
- log/flag the missing asset appropriately during development
- do not replace it with a fake logo

Because this project requires actual manufacturer logos, missing logo assets should be treated as an implementation issue to resolve before considering the section complete.

==================================================
20. DO NOT ADD VEHICLE COUNTS
==================================================

Do not display:

Toyota
24 vehicles

BMW
13 vehicles

etc.

unless the existing product requirements specifically require this and the data can be retrieved efficiently.

The purpose of this section is brand discovery, not statistics.

Keep it visually clean.

==================================================
21. DO NOT MODIFY EXISTING HOMEPAGE SECTIONS
==================================================

Do not redesign:

- hero
- hero carousel
- hero search/filter
- navbar
- footer
- other sections

unless a small integration change is required.

The hero and navbar have already gone through several approved revisions.

Preserve them.

Do not bring back:

- Featured Vehicles
- Browse Vehicles hero button
- Track Vehicle hero button

The Track Vehicle navigation item remains in the navbar as already approved.

==================================================
22. BRAND LOGO ASSET QUALITY CHECK
==================================================

Before declaring this specification complete, verify all 12 logos individually:

[ ] Hyundai
[ ] Honda
[ ] Toyota
[ ] Mazda
[ ] Ford
[ ] Chevrolet
[ ] Dodge
[ ] Nissan
[ ] Mercedes-Benz
[ ] BMW
[ ] Mitsubishi
[ ] GMC

For each:

- correct manufacturer
- correct logo
- correct filename
- valid SVG/image
- renders correctly
- no broken asset path
- no distortion
- no unexpected white/black background
- no external dependency
- correct alt text
- clickable brand link works

==================================================
23. TESTING
==================================================

Test:

Database:
- active brands load
- inactive brands do not appear
- display_order works
- no duplicate brands
- no fake brands

Navigation:
- every brand links correctly
- brand filtering works
- invalid/missing brand does not crash the site

UI:
- desktop
- tablet
- mobile
- horizontal scrolling
- arrow controls
- keyboard navigation
- focus states

Assets:
- all 12 logos load
- no 404s
- no external remote dependencies
- no distorted logos

Performance:
- no unnecessary JS
- no large assets
- no console errors
- no horizontal page overflow

Regression:
- homepage hero still works
- hero search still works
- navbar still works
- mobile menu still works
- footer still works
- admin remains unaffected

==================================================
24. CODE QUALITY
==================================================

Keep implementation clean and maintainable.

Do not create:

- giant homepage PHP files
- duplicated brand arrays
- duplicated database queries
- inline CSS everywhere
- inline JavaScript everywhere
- unnecessary helper functions
- unnecessary dependencies

Use the existing project's conventions.

Keep the component reusable if practical.

Example conceptual structure:

Brand data
    ↓
Homepage controller/service
    ↓
Homepage view
    ↓
Brand strip component
    ↓
Brand logo/link

Use the actual architecture already established in the project.

==================================================
25. FINAL VISUAL STANDARD
==================================================

The finished section should look like something created by a senior UI engineer for a serious automotive/import company.

The standard is NOT:

"12 logos placed in boxes."

The standard is:

A refined automotive brand-selection experience with:

- excellent spacing
- authentic manufacturer logos
- consistent sizing
- strong typography
- restrained borders
- controlled whitespace
- horizontal interaction
- smooth responsive behavior
- clear navigation
- premium visual hierarchy

It should look natural directly underneath the hero.

The section should feel like part of the same professional website, not a separate component pasted onto it.

==================================================
26. STOP CONDITION
==================================================

Do not proceed to the next homepage feature after completing this specification.

After implementation:

1. Test the section thoroughly.
2. Fix any issues found within this specification.
3. Confirm all 12 brand logos are installed and working.
4. Confirm brand links work.
5. Confirm mobile swipe works.
6. Confirm desktop arrows work.
7. Confirm no homepage regression.
8. Confirm no console errors.
9. Update PROGRESS.md.

Update PROGRESS.md to record:

Spec 10C — Homepage Browse by Brand

Status:
COMPLETED

Include:
- database-driven brand selection
- 12 approved brands
- local manufacturer logo assets
- responsive horizontal brand scroller
- desktop navigation controls
- mobile swipe support
- brand-to-vehicle navigation
- accessibility
- performance
- testing completed

Then identify the next logical homepage specification.

DO NOT IMPLEMENT THE NEXT SPECIFICATION.

STOP.