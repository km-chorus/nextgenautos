SPEC 11C — HOMEPAGE ABOUT + TEAM GRID
============================================================

You are continuing development of the Amponsah Autos public website.

IMPORTANT:
This is a specification-driven project.

Implement ONLY this specification.

Do not implement the FAQ or Contact section yet.
That will be Spec 10G.

Before making any changes, read:

1. CLAUDE.md
2. PROGRESS.md
3. docs/01-project-context.md
4. docs/02-scope-and-business-rules.md
5. docs/03-architecture-and-data.md
6. docs/04-design-and-ux-rules.md
7. docs/05-security-and-integrations.md
8. docs/06-ai-development-and-testing.md

Also inspect the current implementation of:

- public homepage
- public layout
- header/navigation
- footer
- Hero
- Browse by Brand
- How It Works
- Plan Your Vehicle Import
- existing Team Management implementation
- team_members database structure
- public CSS
- public JavaScript
- routing architecture

============================================================
1. OBJECTIVE
============================================================

Create the final major informational section of the homepage before FAQ + Contact.

This section combines:

ABOUT AMponsah AUTOS
+
OUR TEAM

into ONE sophisticated editorial grid composition.

Do NOT create two completely separate sections.

The entire area should feel like one intentional UI/UX composition.

The section should communicate:

1. Who Amponsah Autos is
2. What the company does
3. Who is behind the company

The section must feel like it was designed by an experienced UI/UX designer for a serious automotive/import company.

It must NOT look like a generic "About Us + Team Cards" template.

============================================================
2. CORE DESIGN DIRECTION
============================================================

The design concept is:

EDITORIAL COMPANY INTRODUCTION
+
COMPACT PROFESSIONAL TEAM MINI-CARDS

The About content should occupy the dominant portion of the grid.

The team members should appear in compact, sophisticated mini-cards.

IMPORTANT:

Team members are NOT large portraits.

Team members are NOT large image cards.

Team members are NOT social-media profile cards.

Team members use SMALL PROFESSIONAL AVATARS combined with carefully structured information.

Think:

Small avatar
+
Name
+
Position
+
Role/category
+
Short description
+
small interaction affordance

inside a refined mini-card.

============================================================
3. OVERALL GRID
============================================================

Create a responsive grid composition similar to:

--------------------------------------------------------------
|                                                            |
| ABOUT AMponsah AUTOS                                      |
|                                                            |
| ┌──────────────────────────────────┬─────────────────────┐ |
| │                                  │                     │ |
| │                                  │ TEAM MEMBER         │ |
| │        COMPANY INTRODUCTION      │ ┌──────┐            │ |
| │                                  │ │ AV   │ Name       │ |
| │        Who we are                │ └──────┘ Position   │ |
| │                                  │                     │ |
| │        What we do                │ Role                │ |
| │                                  │ Short bio...        │ |
| │        Canada → Ghana             │                  →  │ |
| │                                  │                     │ |
| │        Learn More →              ├─────────────────────┤ |
| │                                  │ TEAM MEMBER         │ |
| │                                  │ ┌──────┐            │ |
| │                                  │ │ AV   │ Name       │ |
| │                                  │ └──────┘ Position   │ |
| │                                  │                     │ |
| └──────────────────────────────────┴─────────────────────┘ |
|                                                            |
--------------------------------------------------------------

This is an example of the composition.

Do not copy this literally.

Use the existing Amponsah Autos design system and make the final layout visually refined.

The About area should be visually dominant.

The team mini-cards should support it.

============================================================
4. SECTION HEADER
============================================================

Use an eyebrow:

ABOUT AMponsah AUTOS

Primary heading should communicate the company's identity.

Suggested direction:

Vehicles sourced with confidence.
Delivered with clarity.

OR:

Connecting quality vehicles from Canada to Ghana.

Choose the strongest wording based on the existing project context.

Do NOT invent claims.

Supporting text should explain the company in a concise and professional way.

Core approved message:

Amponsah Autos operates between Canada and Ghana, helping customers source quality vehicles and navigate the vehicle import journey.

Focus on:

- vehicle sourcing
- Canada
- Ghana
- quality vehicles
- transparent process
- customer confidence
- shipping/import journey

Do not invent:

- years of operation
- number of vehicles sold
- customer numbers
- awards
- certifications
- partnerships
- market position
- financial claims

============================================================
5. ABOUT CONTENT
============================================================

The company introduction should be concise.

Avoid a large wall of text.

Recommended structure:

Eyebrow

Large heading

2 short paragraphs maximum

Optional small supporting information

Optional CTA

The section should be readable in approximately 10–15 seconds.

Do not make users read an essay.

============================================================
6. ABOUT CTA
============================================================

If an About page already exists:

Use:

Learn More About Us →

and link to the existing route.

If a dedicated About page does not yet exist:

Do not create one in this specification.

The section can simply omit the CTA.

Do not invent a route.

============================================================
7. ABOUT VISUAL
============================================================

The About portion may include a subtle visual element if it improves the composition.

Possible visual:

- vehicle
- Canadian automotive sourcing
- shipping/logistics
- Ghana delivery
- professional automotive environment

However, the visual should NOT compete with the team cards.

Preferred:

A restrained image area integrated into the grid.

Avoid:

- giant hero-like image
- video
- image carousel
- heavy decorative graphics
- generic corporate stock photography
- excessive visual effects

If an image is used:

- use a local asset
- prefer WebP
- optimize dimensions
- use lazy loading when appropriate
- meaningful alt text

============================================================
8. TEAM AREA
============================================================

Within the same grid, introduce the team.

Use:

OUR TEAM

Heading:

The people behind Amponsah Autos.

Supporting text may be:

Meet the people responsible for helping our customers source, manage and move their vehicles from Canada to Ghana.

Keep it concise.

Do not invent individual responsibilities.

============================================================
9. TEAM DATA
============================================================

Team members MUST come from the existing Team Management database.

Use:

team_members

Do NOT hardcode team members.

Use only active team members.

Respect:

- display_order
- full name
- position/title
- category
- bio
- photo

Do not create a second team data structure.

Do not create another database table.

Do not duplicate team information.

============================================================
10. TEAM MINI-CARD DESIGN
============================================================

THIS IS THE MOST IMPORTANT VISUAL REQUIREMENT.

The profile cards must look sophisticated and professionally designed.

They should NOT look like ordinary Bootstrap cards.

They should NOT look like:

[large image]
Name
Position

Instead, use a compact profile composition.

Example:

┌────────────────────────────────────────────┐
│                                            │
│  ┌────────┐                                │
│  │        │   GODFRED MARCUS               │
│  │ AVATAR │   Chief Executive Officer      │
│  │        │                                │
│  └────────┘                                │
│                                            │
│  Leadership                                │
│                                            │
│  Responsible for the strategic direction   │
│  of the company.                           │
│                                            │
│  ───────────────────────────────────────── │
│                                      →     │
└────────────────────────────────────────────┘

The avatar should be approximately:

48px–64px

depending on screen size.

The avatar should remain a supporting identity element.

It must NOT dominate the card.

============================================================
11. TEAM MINI-CARD CONTENT
============================================================

Each card should contain:

1. Small avatar
2. Full name
3. Position/title
4. Team category/role
5. Short biography
6. Small arrow/detail affordance if appropriate

Example hierarchy:

GODFRED MARCUS
Chief Executive Officer

Leadership

Responsible for the strategic direction of
Amponsah Autos.

                         →

Name:
Strong typography

Position:
Smaller secondary typography

Category:
Subtle uppercase/meta treatment

Bio:
Short and restrained

Arrow:
Small and subtle

Do not expose:

- database IDs
- status
- timestamps
- internal admin information

============================================================
12. AVATAR DESIGN
============================================================

Use the existing team member photo where available.

The photo should be displayed as a SMALL professional avatar.

Preferred:

48–64px square or slightly rounded-square.

Do not use:

- giant portraits
- full-height profile photography
- random stock people
- generated faces
- emojis
- generic silhouettes unless absolutely necessary

A subtle 6–10px radius is preferred.

Do NOT automatically make every avatar a circular social-media avatar.

A refined rounded-square treatment is preferred.

If no photo exists:

Create a tasteful neutral placeholder.

Do not fabricate a person's face.

============================================================
13. CARD STRUCTURE
============================================================

The mini-card should have:

- refined border
- subtle background
- carefully controlled padding
- 6–10px radius
- strong internal spacing
- excellent alignment
- minimal shadow or no shadow
- clear information hierarchy

Do not use huge rounded containers.

Do not use excessive shadows.

Do not use glassmorphism.

Do not use gradients.

Do not use decorative blobs.

Do not use colorful backgrounds.

============================================================
14. CARD HOVER STATE
============================================================

Use subtle interaction.

Possible:

- border becomes slightly darker
- background changes subtly
- arrow moves a few pixels
- slight elevation
- avatar border changes subtly

Keep it understated.

Do NOT use:

- dramatic scaling
- card flipping
- 3D transforms
- glowing effects
- animated gradients

The UI should feel mature.

============================================================
15. TEAM GRID
============================================================

Do not display a huge team directory on the homepage.

Recommended:

4 active team members maximum on the homepage.

If fewer than 4 exist:

Display the available members.

If more than 4 exist:

Use the existing display order and display the first 4.

Do not create fake members.

If a complete team page exists later, it can contain the full team.

============================================================
16. GRID BEHAVIOR
============================================================

Desktop:

Use a sophisticated multi-column grid.

The About block should occupy approximately:

2/3 of the visual width

The team area should occupy approximately:

1/3

or use a carefully balanced asymmetric grid.

Do not force a rigid 50/50 layout if the content would look better asymmetrically.

Team cards can stack vertically inside the team column.

Alternative:

The About area spans two grid columns while team mini-cards occupy the remaining column.

Choose the strongest composition based on the actual content.

============================================================
17. MOBILE
============================================================

On mobile:

The composition becomes a single-column layout.

Recommended order:

ABOUT AMponsah AUTOS
Company introduction
CTA

OUR TEAM
Team member mini-card
Team member mini-card
Team member mini-card
Team member mini-card

Do NOT shrink the desktop grid until it becomes unusable.

Recompose it intentionally for mobile.

Team cards should remain compact.

The avatar remains small.

The content remains readable.

============================================================
18. TYPOGRAPHY
============================================================

Use Manrope.

Main section heading:

40–48px desktop

32–36px mobile

Team heading:

28–36px

Team name:

16–19px

Position:

13–14px

Category:

11–12px uppercase/meta

Bio:

13–15px

Do not overuse bold typography.

============================================================
19. COLORS
============================================================

Use only the approved palette:

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

The cards may use:

White background
+
thin #E3E3DF border

or

Warm White background

The About area may use Deep Charcoal if it creates a stronger composition.

Choose the most sophisticated combination.

Do not introduce new colors.

Do not use purple.

Do not use generic Bootstrap blue.

============================================================
20. BOOTSTRAP RESTRICTION
============================================================

HARD REQUIREMENT:

Do NOT use Bootstrap's default visual components.

Do not rely on:

.card
.card-body
.btn
.btn-primary
.shadow
.rounded
Bootstrap spacing
Bootstrap colors
Bootstrap typography

Bootstrap may remain in the project for other reasons, but this public section must use the custom Amponsah Autos design system.

The result must NOT look like a Bootstrap template.

============================================================
21. ICONS
============================================================

Icons are optional.

Do not add icons just to decorate the cards.

If using an arrow/detail indicator:

Use a lightweight SVG.

Do not install an icon library for this section.

Do not use emoji.

============================================================
22. RESPONSIVE BREAKPOINTS
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

Ensure:

- no horizontal overflow
- grid collapses properly
- team cards remain readable
- avatar sizes remain appropriate
- text does not collide
- CTA remains usable
- spacing remains balanced

============================================================
23. ACCESSIBILITY
============================================================

Use semantic HTML.

Use proper heading hierarchy.

Team member names should not be presented as headings if that creates an incorrect heading structure.

Use meaningful image alt text.

Example:

"Godfred Marcus — Chief Executive Officer"

Ensure:

- keyboard accessibility
- visible focus states
- readable contrast
- touch targets of appropriate size
- no color-only information

============================================================
24. PERFORMANCE
============================================================

Keep the implementation lightweight.

Do not introduce:

- React
- Vue
- animation libraries
- carousel libraries
- large UI frameworks
- remote widgets
- video
- unnecessary JavaScript

Reuse:

- existing CSS
- existing Manrope
- existing team data
- existing public architecture

Avoid unnecessary database queries.

Do not create N+1 queries.

============================================================
25. ARCHITECTURE
============================================================

Use the existing public architecture.

Preferred flow:

Browser
↓
Homepage Controller
↓
Service
↓
Team Repository/Model
↓
PDO/MySQL
↓
Homepage View

Do not place raw SQL in the view.

Do not directly query MySQL from HTML templates.

Escape all dynamic team data before rendering.

============================================================
26. SECURITY
============================================================

This section is read-only.

Do not expose internal team information.

Escape all database content.

Do not trust raw HTML from database fields unless the existing content architecture explicitly sanitizes it.

Do not expose:

- internal IDs
- admin information
- timestamps
- account information
- private employee information

============================================================
27. HOMEPAGE ORDER
============================================================

The homepage should now be:

1. Hero + Vehicle Search
2. Browse by Brand
3. How It Works
4. Plan Your Vehicle Import
5. About + Team Grid
6. FAQ + Contact
7. Footer

Do not add Featured Vehicles.

Do not add testimonials.

Do not add unnecessary statistics.

Do not add partner logo sections.

Do not add newsletter sections.

Do not add social media feeds.

============================================================
28. DO NOT MODIFY OTHER SECTIONS
============================================================

Do not redesign:

- Hero
- Browse by Brand
- How It Works
- Plan Your Vehicle Import
- Header
- Mobile navigation
- Footer

unless a small integration change is required.

============================================================
29. QUALITY STANDARD
============================================================

The final section should look like it was designed by a senior UI/UX designer.

The team mini-card should be the key quality benchmark.

Ask yourself:

Would this look appropriate on the website of an established international automotive company?

If the answer is no, refine it.

Avoid:

- generic cards
- excessive rounded corners
- giant portraits
- avatar circles everywhere
- excessive shadows
- generic icon grids
- Bootstrap styling
- excessive colors
- decorative UI without purpose

Focus on:

- hierarchy
- spacing
- typography
- grid composition
- subtle borders
- compact professional avatars
- precise alignment
- restrained interaction
- visual consistency

============================================================
30. TESTING CHECKLIST
============================================================

Before completion:

[ ] About + Team exists as one unified section.

[ ] Grid composition is sophisticated.

[ ] About area is visually dominant.

[ ] Company introduction is concise.

[ ] No unsupported company claims.

[ ] Team data comes from team_members.

[ ] Only active team members appear.

[ ] Display order is respected.

[ ] Maximum 4 members appear on homepage.

[ ] Small avatars are used.

[ ] Avatars are NOT large portraits.

[ ] Names display correctly.

[ ] Positions display correctly.

[ ] Categories display correctly.

[ ] Bios display correctly.

[ ] Mini-cards have sophisticated custom styling.

[ ] Mini-cards do not resemble Bootstrap cards.

[ ] No excessive shadows.

[ ] No excessive rounded corners.

[ ] No unnecessary icons.

[ ] No emojis.

[ ] No fake team members.

[ ] No internal information exposed.

[ ] Mobile layout is intentionally redesigned.

[ ] Tablet layout works.

[ ] Desktop layout works.

[ ] No horizontal overflow.

[ ] No console errors.

[ ] No unnecessary JavaScript.

[ ] Existing homepage sections still work.

[ ] Admin remains unaffected.

============================================================
31. PROGRESS UPDATE
============================================================

After successful implementation and testing, update:

PROGRESS.md

Record:

Spec 10F — Homepage About + Team Grid

Status:
COMPLETED

Include:

- unified About + Team section
- editorial grid layout
- company introduction
- database-driven team members
- compact professional team avatars
- sophisticated team mini-cards
- responsive behavior
- accessibility
- performance
- regression testing

Then identify:

Next:
Spec 10G — Homepage FAQ + Contact

============================================================
32. STOP
============================================================

Implement ONLY Spec 10F.

Test thoroughly.

Fix issues within the scope of this specification.

Update PROGRESS.md.

Do NOT implement FAQ or Contact yet.

STOP.