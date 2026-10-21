You are continuing development of the Amponsah Autos public website.

IMPORTANT:
This is a specification-driven project.

Implement ONLY this specification.

Do not redesign or rewrite unrelated parts of the website.

Before making any changes, read:

1. CLAUDE.md
2. PROGRESS.md
3. docs/01-project-context.md
4. docs/02-scope-and-business-rules.md
5. docs/03-architecture-and-data.md
6. docs/04-design-and-ux-rules.md
7. docs/05-security-and-integrations.md
8. docs/06-ai-development-and-testing.md

Also inspect the existing implementation of:

- public homepage
- hero/search section
- Browse by Brand section
- public header/navigation
- public footer
- existing CSS architecture
- existing JavaScript architecture
- public routing

==================================================
SPEC 10D — HOMEPAGE HOW IT WORKS
==================================================

OBJECTIVE

Build the next homepage section immediately after the existing:

Hero + Vehicle Search
        ↓
Browse by Brand
        ↓
How It Works

The purpose of this section is to clearly explain how a customer moves from finding a vehicle to receiving/monitoring their shipment.

The section must communicate the actual Amponsah Autos customer journey without inventing business processes.

It should feel premium, trustworthy and simple.

==================================================
1. SECTION HEADING
==================================================

Use:

How It Works

Supporting text:

From finding your vehicle in Canada to tracking its journey to Ghana, we keep the process clear and straightforward.

Keep the copy concise.

Do not make unsupported claims.

Do not use generic marketing phrases such as:

"Fastest delivery"
"Best prices"
"100% guaranteed"
"Seamless experience"
"Trusted by thousands"

unless such claims are supported by actual business information.

==================================================
2. CUSTOMER JOURNEY
==================================================

Use the following five-step journey:

01 — Browse Vehicles

Explore available vehicles and find a vehicle that matches your needs.

02 — Choose Your Vehicle

Review the vehicle's specifications, price, shipping information and other available details.

03 — Contact Amponsah Autos

Contact the company to discuss and proceed with the selected vehicle.

04 — Vehicle Ships to Ghana

After purchase/arrangement, the company arranges shipment. Once the shipping company assigns a container, the container information becomes available.

05 — Track Your Vehicle

Use the provided container/tracking information to access the official shipping provider's tracking service.

IMPORTANT:

Do not imply that Amponsah Autos provides live carrier tracking.

Do not imply that Amponsah Autos generates container numbers.

Do not claim that the website independently tracks the vehicle.

The external shipping provider remains the source of truth for live shipment status.

==================================================
3. VISUAL STRUCTURE
==================================================

Do NOT create five generic Bootstrap cards.

The section should look like a professional automotive/logistics process.

Preferred desktop composition:

                HOW IT WORKS

       From Canada to Ghana, made clear.

01              02              03              04              05

Browse  ─────  Choose  ─────  Contact ─────  Ship ───── Track
Vehicles        Vehicle         Us              to Ghana    Vehicle


Each step should contain:

- step number
- concise title
- short explanation

Use a subtle connecting line between the steps.

The connecting line represents progression through the process.

Do not use a thick colorful line.

Do not use animated progress bars.

Do not use a flashy timeline.

==================================================
4. STEP NUMBERS
==================================================

Use:

01
02
03
04
05

The numbers should be visually prominent but restrained.

Possible styling:

small uppercase/meta label
large number
title
description

Example:

01

BROWSE VEHICLES

Explore available vehicles
from our catalogue.

Do not use emojis.

Do not use random decorative icons.

==================================================
5. ICONS
==================================================

Icons are optional.

If icons are used, they must be extremely restrained and meaningful.

Do NOT install an icon library just for this section.

Do not use:

- emojis
- random automotive icons
- cartoon illustrations
- 3D icons
- glowing icons

The numbered process should be sufficient on its own.

A strong typography-based process is preferred.

==================================================
6. DESKTOP LAYOUT
==================================================

At approximately:

1024px+
1280px
1440px+

use a horizontal process.

Example:

HOW IT WORKS

From Canada to Ghana, made clear.

01          02          03          04          05
Browse  →   Choose  →   Contact →  Ship    →   Track
Vehicles    Vehicle     Us          to Ghana     Vehicle

The connecting line should sit visually behind/between the steps.

Maintain enough spacing so the section does not feel cramped.

Do not make every step a large card.

==================================================
7. TABLET LAYOUT
==================================================

At tablet widths:

768px
834px
1024px

adapt the spacing appropriately.

A horizontal layout can remain if it still has sufficient room.

If the five-step horizontal layout becomes cramped, transition to a more compact arrangement.

Do not allow text to become tiny simply to preserve the desktop layout.

==================================================
8. MOBILE LAYOUT
==================================================

At:

320px
375px
390px
414px

use a vertical process.

Example:

01
BROWSE VEHICLES
Explore available vehicles...

│
│
02
CHOOSE YOUR VEHICLE
Review the vehicle...

│
│
03
CONTACT US
Contact Amponsah Autos...

│
│
04
VEHICLE SHIPS TO GHANA
Shipment is arranged...

│
│
05
TRACK YOUR VEHICLE
Use the provided tracking information...

The connecting line can become vertical.

Make sure the line does not interfere with text.

Do not force a horizontally scrolling process on mobile.

==================================================
9. DESIGN SYSTEM
==================================================

Follow the existing Amponsah Autos design system.

Colors:

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

The section must feel:

- premium
- mature
- professional
- automotive
- trustworthy
- calm
- structured

Avoid:

- purple
- neon
- glassmorphism
- excessive gradients
- excessive shadows
- oversized rounded cards
- excessive red
- decorative clutter
- huge illustrations

==================================================
10. BACKGROUND
==================================================

Do not automatically create a completely different colored block just because this is a new section.

The homepage should feel like one coherent website.

Use either:

Warm White
#F8F7F4

or

White
#FFFFFF

depending on the surrounding Browse by Brand section.

Use subtle separation if necessary.

Do not create alternating backgrounds for every homepage section.

==================================================
11. SPACING
==================================================

Use controlled vertical spacing.

Target approximately:

Desktop:
80–110px top/bottom

Tablet:
64–90px

Mobile:
56–76px

Do not create excessive empty space.

The section should feel substantial without pushing the next content too far down the page.

==================================================
12. TYPOGRAPHY
==================================================

Section heading:

approximately 40–48px desktop

approximately 32–36px mobile

Weight:

500–600

Supporting text:

16–18px

Step number:

approximately 13–14px label + larger numeric treatment if appropriate.

Step title:

18–22px.

Step description:

14–16px.

Maintain readable line lengths.

Do not make the entire section oversized.

==================================================
13. INTERACTION
==================================================

This section does not require complex interaction.

Do NOT add:

- auto-playing animation
- scroll-jacking
- parallax
- animated counters
- animated progress lines
- hover explosions
- heavy motion

Optional:

A very subtle hover/focus state can slightly change the step title or accent.

Keep it lightweight.

==================================================
14. ACCESSIBILITY
==================================================

Use semantic HTML.

Recommended structure:

<section>
    <div>
        <p>How It Works</p>
        <h2>...</h2>
        <p>...</p>
    </div>

    <ol>
        <li>...</li>
        <li>...</li>
        <li>...</li>
        <li>...</li>
        <li>...</li>
    </ol>
</section>

The five steps represent an ordered process, so an ordered list is preferred.

Do not use divs alone when semantic elements are appropriate.

Ensure:

- proper heading hierarchy
- readable contrast
- keyboard accessibility
- visible focus states
- screen-reader friendly structure

==================================================
15. LINKS / CTAs
==================================================

Do not turn every step into a button.

However, the section may include one restrained CTA after the process:

"Browse Vehicles"

This can link to the existing public vehicle listing route.

Only add this CTA if it improves the flow.

Do not add:

- Browse Vehicles
- Track Vehicle
- Contact Us
- Learn More

all together.

Avoid CTA overload.

If one CTA is used, "Browse Vehicles" is the preferred action because the next natural customer action is vehicle discovery.

Use the existing public route.

Do not invent a new routing system.

==================================================
16. TRACKING INFORMATION
==================================================

The final step must accurately reflect the approved tracking process.

Use wording similar to:

"Once tracking information is available, use the provided container number to access the shipping provider's tracking service."

Do not say:

"Track your vehicle live on our website."

Do not say:

"See your vehicle location in real time."

Do not fabricate shipment statuses.

The approved external tracking provider is:

Hapag-Lloyd

Official tracking page:

https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html

However, do NOT necessarily put this external URL prominently inside this homepage section.

The detailed tracking experience will be handled in the dedicated public tracking specification later.

==================================================
17. DUTY CALCULATION
==================================================

Do not add duty calculation to this section.

The approved process remains:

Customer views vehicle details
        ↓
Customer uses the approved external duty calculator
        ↓
Customer can contact Amponsah Autos

Do not create or imply an internal duty calculator.

==================================================
18. DATABASE
==================================================

This section does not require new database tables.

Do not create:

how_it_works table
process_steps table
workflow table

unless an existing approved architecture already requires dynamic content for this section.

For the current implementation, static explanatory content is acceptable.

Do not introduce unnecessary database queries.

==================================================
19. PERFORMANCE
==================================================

This section must be extremely lightweight.

Do not add:

- image-heavy process illustrations
- video
- Lottie
- animation libraries
- carousel libraries
- external JavaScript libraries

Prefer:

HTML
CSS
minimal/no JavaScript

The section should require essentially no JavaScript.

==================================================
20. RESPONSIVE TESTING
==================================================

Test at:

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

- no horizontal page overflow
- no overlapping connecting lines
- readable text
- consistent spacing
- correct step order
- mobile vertical flow
- desktop horizontal flow
- good alignment
- no broken layout

==================================================
21. VISUAL QUALITY STANDARD
==================================================

The final section must NOT look like:

[icon] Browse
[icon] Choose
[icon] Contact
[icon] Ship
[icon] Track

inside five generic cards.

Instead, it should feel like a carefully designed editorial/logistics process.

Think:

Automotive showroom
+
International logistics
+
Premium editorial layout

The visual hierarchy should come from:

- typography
- spacing
- alignment
- numbering
- subtle lines
- restrained accent color

Not from:

- cards
- shadows
- icons
- gradients
- decorative effects

==================================================
22. INTEGRATION
==================================================

Place the section immediately after:

Browse by Brand

and before the next approved homepage section.

Do not disturb the existing:

- hero carousel
- hero gradient overlay
- vehicle search form
- navbar
- mobile menu
- Browse by Brand section
- footer

The existing hero should remain exactly as currently approved.

==================================================
23. TESTING CHECKLIST
==================================================

Before completion verify:

[ ] Section appears immediately after Browse by Brand.

[ ] Heading is correct.

[ ] Supporting text is concise.

[ ] Five steps are present.

[ ] Step order is correct.

[ ] Process reflects the actual Amponsah Autos workflow.

[ ] No unsupported claims.

[ ] No fake business statistics.

[ ] No duty calculation is introduced.

[ ] No live tracking claim is introduced.

[ ] Desktop uses horizontal process.

[ ] Mobile uses vertical process.

[ ] Connecting line works correctly.

[ ] No horizontal page overflow.

[ ] No console errors.

[ ] No unnecessary JavaScript.

[ ] No new external dependencies.

[ ] No emojis.

[ ] No purple.

[ ] No excessive cards.

[ ] No excessive animation.

[ ] Typography follows the design system.

[ ] Existing homepage sections still work.

[ ] Existing navbar still works.

[ ] Existing hero search still works.

[ ] Existing Browse by Brand section still works.

[ ] Admin area remains unaffected.

==================================================
24. PROGRESS UPDATE
==================================================

After implementation and testing, update:

PROGRESS.md

Record:

Spec 10D — Homepage How It Works

Status:
COMPLETED

Include:

- five-step customer journey
- responsive desktop/mobile process layout
- Canada-to-Ghana workflow
- accessible semantic structure
- lightweight implementation
- responsive testing completed
- regression testing completed

Then identify the next logical homepage specification.

==================================================
25. STOP
==================================================

Do NOT continue automatically to the next homepage specification.

Complete only Spec 10D.

Test it.

Fix issues within the scope of Spec 10D.

Update PROGRESS.md.

Then STOP.