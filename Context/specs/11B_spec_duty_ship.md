SPEC 11B — HOMEPAGE VEHICLE IMPORT UTILITIES
============================================================

You are continuing development of the Amponsah Autos public website.

IMPORTANT:
This is a specification-driven project.

Implement ONLY this specification.

Do not redesign unrelated sections.

Before making any changes, read:

1. CLAUDE.md
2. PROGRESS.md


Also inspect the current implementation of:

- public homepage
- hero/search section
- Browse by Brand
- How It Works
- public header/navigation
- public footer
- public CSS
- public JavaScript
- public routes
- existing Track Vehicle page/route if implemented

============================================================
1. OBJECTIVE
============================================================

Create the next homepage section after:

Hero + Vehicle Search
        ↓
Browse by Brand
        ↓
How It Works
        ↓
THIS SECTION

The section should provide two important customer actions:

1. Track Your Shipment
2. Calculate Import Duty

These should be presented together as one professionally designed homepage utility section.

The section should feel like a premium automotive/import-business interface rather than two generic cards.

============================================================
2. SECTION HEADING
============================================================

Use:

PLAN YOUR VEHICLE IMPORT

Main heading:

Everything you need to stay informed about your vehicle.

Supporting copy should remain concise.

Suggested:

Track your shipment and estimate your import duty using the tools provided for your vehicle journey.

Do not over-explain.

Do not make unsupported claims.

============================================================
3. TWO-COLUMN STRUCTURE
============================================================

Desktop layout:

------------------------------------------------------------
|                         SECTION                          |
|                                                          |
| PLAN YOUR VEHICLE IMPORT                                 |
| Everything you need to stay informed about your vehicle. |
|                                                          |
| ┌────────────────────────┬─────────────────────────────┐ |
| │                        │                             │ |
| │  TRACK YOUR SHIPMENT   │  CALCULATE IMPORT DUTY      │ |
| │                        │                             │ |
| │  [visual]              │  [visual]                  │ |
| │                        │                             │ |
| │  Follow your vehicle's │  Estimate the import duty  │ |
| │  shipment using the    │  for your vehicle using    │
| │  provided tracking     │  the external calculator.  │
| │  information.          │                             │
| │                        │                             │
| │  Track Shipment →      │  Calculate Duty →           │
| │                        │                             │
| └────────────────────────┴─────────────────────────────┘ |
------------------------------------------------------------

Use two equal or approximately equal columns.

Do not squeeze the content.

Do not make the columns unnecessarily tall.

============================================================
4. TRACK YOUR SHIPMENT COLUMN
============================================================

Title:

Track Your Shipment

Description:

Follow your vehicle's shipment using the tracking information provided by Amponsah Autos.

Keep it concise.

CTA:

Track Shipment →

The CTA should link to the existing public Track Vehicle page/route.

IMPORTANT:

Do not create a new tracking system in this specification.

Do not create live carrier tracking.

Do not scrape Hapag-Lloyd.

Do not create a tracking API.

Do not generate container numbers.

The dedicated tracking page will handle the actual tracking workflow.

The existing approved external carrier is:

Hapag-Lloyd

Official tracking page:

https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html

If the existing public Track Vehicle route is already implemented, link to that route rather than sending users directly to Hapag-Lloyd.

If the Track Vehicle route does not yet exist, inspect the current architecture and create only the appropriate link destination needed for this homepage CTA. Do NOT implement the full tracking system as part of this specification.

============================================================
5. TRACKING VISUAL
============================================================

The tracking column should have a strong visual element.

Preferred options, in order:

1. Existing high-quality local automotive/logistics image.
2. Lightweight local WebP image related to:
   - vehicle shipping
   - container transport
   - port logistics
   - vehicle transportation
3. A professionally designed lightweight icon/graphic if a suitable image does not exist.

Do NOT use:

- random low-quality stock photography
- generic cartoon illustrations
- emojis
- 3D icons
- huge decorative graphics
- remote image URLs

The visual should support the meaning of tracking/shipping.

It should not overpower the content.

If an image is used:

- optimize it
- prefer WebP
- use object-fit appropriately
- avoid unnecessarily large dimensions
- lazy-load if it is below the initial viewport

============================================================
6. CALCULATE IMPORT DUTY COLUMN
============================================================

Title:

Calculate Import Duty

Description:

Estimate the import duty for your vehicle using the external duty calculator.

Add a short supporting sentence if needed:

Have your vehicle details available when using the calculator.

Do not claim the result is exact.

Do not claim Amponsah Autos calculates the duty.

CTA:

Calculate Duty →

The CTA must link directly to:

https://autodutychecker.com/calculator/search-mode/

Use:

target="_blank"

rel="noopener noreferrer"

Use a normal anchor.

Do not use JavaScript navigation.

============================================================
7. DUTY CALCULATOR VISUAL
============================================================

The second column should have a complementary visual.

Possible visual direction:

- vehicle documentation
- vehicle specification sheet
- customs/import document
- calculator/document graphic
- clean automotive-detail visual

Prefer an existing optimized local image if one exists.

Otherwise create a lightweight visual treatment using HTML/CSS or an appropriate local asset.

Do not create a fake screenshot of the external calculator.

Do not embed the external calculator.

Do not iframe the calculator.

Do not scrape the calculator.

Do not create fake duty numbers.

============================================================
8. VISUAL RELATIONSHIP
============================================================

The two columns must feel like one intentional component.

They should not look like:

[ Bootstrap Card ] [ Bootstrap Card ]

Instead, create an editorial composition.

Recommended approach:

LEFT:
Dark/charcoal utility panel

RIGHT:
Warm white/light utility panel

OR:

Use a unified warm-white section with two visually distinct image/content compositions separated by a restrained vertical divider.

Choose the approach that integrates best with the current homepage.

Do not introduce a completely new visual language.

============================================================
9. COLORS
============================================================

Use only the established Amponsah Autos palette:

Deep Charcoal:
#111315

Warm White:
#F8F7F4

White:
#FFFFFF

Secondary Text:
#62666A

Border:
#E3E3DF

Accent:
#9E2F2F

The tracking panel may use Deep Charcoal.

The duty panel may use Warm White/White.

Accent red should be used sparingly for:

- small highlights
- CTA emphasis
- subtle interactive states
- small graphic details

Do not make the entire section red.

Do not use:

- purple
- neon
- bright generic blue
- colorful gradients
- glassmorphism

============================================================
10. ICONS
============================================================

Use professional icons if they genuinely improve the design.

Suggested concepts:

Tracking:
- container
- shipment route
- location/route
- package tracking

Duty:
- calculator
- document
- receipt
- vehicle/import document

Icons should be:

- simple
- modern
- line-based or minimal
- consistent
- appropriately sized

IMPORTANT:

Do not install an entire icon library just for two icons.

If the project already has an icon system, reuse it.

If no icon system exists, use lightweight local SVG icons.

Do not use emoji.

Do not use random icon styles from different libraries.

============================================================
11. NO BOOTSTRAP DEFAULTS
============================================================

This is a HARD REQUIREMENT.

Do not use Bootstrap's default visual styling.

Do NOT rely on:

.btn
.btn-primary
.card
.card-body
.shadow
.rounded
.alert
.default Bootstrap spacing
.default Bootstrap colors
.default Bootstrap typography

The section must use the Amponsah Autos custom design system.

Bootstrap may remain in the project if already used elsewhere, but this section must visually appear custom-designed.

The final result should NOT look like:

"Bootstrap card + Bootstrap button + Bootstrap grid."

Use custom classes and the project's existing public CSS architecture.

============================================================
12. TYPOGRAPHY
============================================================

Use Manrope.

Eyebrow:

12–13px

Section heading:

40–48px desktop

32–36px mobile

Column title:

24–30px desktop

22–26px mobile

Body:

15–17px

CTA:

14–16px

Use weights deliberately.

Do not make every element bold.

============================================================
13. CTA DESIGN
============================================================

Each column gets exactly one primary action.

Tracking:

Track Shipment →

Duty:

Calculate Duty →

Use custom Amponsah button/link styling.

Do not use default Bootstrap buttons.

Avoid:

- giant pills
- excessive border radius
- gradients
- glowing buttons
- giant shadows
- oversized CTA blocks

A subtle arrow can be used as part of the text.

Example:

Track Shipment →

============================================================
14. IMAGE COMPOSITION
============================================================

If using images, do not simply place:

[image]
[title]
[text]

inside a generic card.

Create a composed visual.

Possible structure:

┌──────────────────────────────┐
│                              │
│          IMAGE               │
│                              │
│                    small     │
│                    icon      │
├──────────────────────────────┤
│ TRACK YOUR SHIPMENT          │
│                              │
│ Follow your vehicle...       │
│                              │
│ Track Shipment →             │
└──────────────────────────────┘

But remove the card-like appearance where possible.

The visual and content should feel integrated.

============================================================
15. RESPONSIVE BEHAVIOR
============================================================

Desktop:

Two columns side by side.

Tablet:

Two columns may remain if the content remains comfortable.

Mobile:

Stack vertically.

Order:

1. Track Your Shipment
2. Calculate Import Duty

Each should have sufficient spacing.

Do not create a horizontal scrolling utility section.

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

Ensure:

- no horizontal overflow
- no overlapping text
- no distorted images
- buttons remain accessible
- columns stack correctly
- images remain proportional

============================================================
16. HOVER INTERACTION
============================================================

Keep interaction subtle.

Possible:

- slight image movement
- subtle border change
- slight text/arrow movement
- small opacity transition

Do NOT use:

- dramatic zoom
- parallax
- glowing borders
- animated gradients
- floating cards
- excessive transforms

The website should remain calm and premium.

============================================================
17. ACCESSIBILITY
============================================================

Use semantic HTML.

The section should contain:

<section>
    heading
    supporting text
    two utility items
</section>

Use real anchor elements for both CTAs.

External duty link:

target="_blank"
rel="noopener noreferrer"

Provide meaningful image alt text.

Decorative images should use empty alt attributes.

Ensure:

- keyboard navigation
- visible focus state
- sufficient contrast
- readable text
- accessible link labels

============================================================
18. PERFORMANCE
============================================================

Keep the section lightweight.

No:

- React
- new frontend framework
- carousel library
- animation library
- external widget
- iframe
- remote tracking widget
- remote duty widget

Use:

- HTML
- CSS
- minimal vanilla JS only if actually necessary

Ideally, this section requires no JavaScript.

Optimize all images.

Prefer WebP or SVG.

============================================================
19. SECURITY
============================================================

Tracking and duty links are fixed destinations.

Do not allow user input to control these URLs.

Use:

rel="noopener noreferrer"

for external links opened in a new tab.

Do not proxy external websites.

Do not scrape external services.

============================================================
20. NO DATABASE CHANGES
============================================================

This section does not require new database tables.

Do not create:

- duty tables
- calculator tables
- tracking tables
- utility tables

Use existing public routes.

============================================================
21. DO NOT IMPLEMENT LIVE TRACKING
============================================================

This specification is NOT the tracking implementation.

Do not add:

- Hapag-Lloyd API
- carrier API
- live shipment status
- GPS
- tracking database changes
- container generation
- automated tracking lookup

Only link the customer to the appropriate existing Track Vehicle experience.

============================================================
22. DO NOT IMPLEMENT DUTY CALCULATION
============================================================

This specification is NOT a duty calculator implementation.

Do not add:

- customs formulas
- tax formulas
- duty percentages
- valuation algorithms
- Ghana Customs API
- AutoDutyChecker API
- scraping
- iframe

The CTA simply directs the customer to the approved external calculator.

============================================================
23. HOMEPAGE INTEGRATION
============================================================

The homepage order must now be:

1. Hero + Vehicle Search
2. Browse by Brand
3. How It Works
4. Plan Your Vehicle Import
   - Track Your Shipment
   - Calculate Import Duty
5. Next approved homepage section
6. Footer

Do not reintroduce Featured Vehicles.

Do not add the previously proposed Canada → Ghana process section.

============================================================
24. REGRESSION PROTECTION
============================================================

Do not modify:

- hero
- hero carousel
- hero gradient
- hero search
- navbar
- mobile navigation
- Browse by Brand
- How It Works
- admin system

unless absolutely necessary for integration.

============================================================
25. QUALITY STANDARD
============================================================

The final result should look like a senior UI engineer designed it for a serious international automotive company.

It must NOT look like:

- a student project
- generic Bootstrap
- generic SaaS
- two basic cards
- a template section
- an icon grid

It should feel:

- premium
- intentional
- automotive
- trustworthy
- modern
- lightweight
- professionally composed

Use visual hierarchy, typography, photography/icons, spacing and color to create the quality.

Do not add complexity just to make it look "professional."

============================================================
26. TESTING CHECKLIST
============================================================

Before completion:

[ ] Section appears after How It Works.

[ ] Section heading is present.

[ ] Supporting copy is concise.

[ ] Two columns exist.

[ ] Tracking column exists.

[ ] Duty column exists.

[ ] Tracking CTA works.

[ ] Duty CTA works.

[ ] Duty CTA points to the approved external calculator.

[ ] External duty link uses target="_blank".

[ ] External duty link uses rel="noopener noreferrer".

[ ] Tracking does not claim live tracking on this section.

[ ] No duty calculation exists.

[ ] No fake duty values exist.

[ ] No Bootstrap default styling is visible.

[ ] Custom Amponsah styling is used.

[ ] Icons/images are high quality.

[ ] Images are optimized.

[ ] No unnecessary dependencies were added.

[ ] Mobile layout works.

[ ] Tablet layout works.

[ ] Desktop layout works.

[ ] No horizontal page overflow.

[ ] No console errors.

[ ] Existing homepage sections still work.

[ ] Admin remains unaffected.

============================================================
27. PROGRESS UPDATE
============================================================

After implementation and testing, update:

PROGRESS.md

Record:

Spec 10E — Homepage Vehicle Import Utilities

Status:
COMPLETED

Include:

- two-column utility section
- Track Your Shipment CTA
- Calculate Import Duty CTA
- approved external duty calculator link
- existing Track Vehicle integration
- custom Amponsah styling
- professional icons/images
- responsive implementation
- accessibility
- performance
- regression testing

Then identify the next logical homepage specification.

============================================================
28. STOP
============================================================

Implement ONLY Spec 11B.

Test thoroughly.

Fix issues within the scope of this specification.

Update PROGRESS.md.

Do NOT automatically start the next homepage specification.

STOP.