# Specification 09 — Public Website Foundation & Global UI

## 1. Purpose

Implement the foundational public-facing website layer for **Mr Amponsah Autos**.

This specification establishes the shared technical and visual foundation that all public webpages will use later.

The public website must feel:

* Premium
* Professional
* Trustworthy
* Automotive-focused
* Modern
* Clean
* Lightweight
* Fast
* Mobile-first
* Easy to navigate

The website must **look premium without becoming technically heavy**.

This specification does NOT implement the homepage, vehicle inventory, vehicle details, brand pages, contact page, FAQ, tracking page, or other complete public pages.

It establishes the reusable foundation those pages will build upon.

---

# 2. Required Reading Before Implementation

Before changing any code, read:

```text
CLAUDE.md
PROGRESS.md

Also inspect the existing implementation.

Pay particular attention to:

* current application bootstrap
* configuration
* database connection
* helpers
* existing public entry point
* existing admin architecture
* existing CSS
* existing JavaScript
* existing image handling
* existing authentication
* existing routing conventions
* existing reusable includes/components

Do not recreate functionality that already exists.

Reuse established project conventions wherever appropriate.

---

# 3. Core Requirement — Lightweight Public Website

This is one of the most important requirements of this specification.

The public website must be **lightweight by architecture, assets, JavaScript, CSS, and page structure**.

Premium appearance must come from:

* typography
* spacing
* photography
* composition
* hierarchy
* quality content
* restrained colour
* consistency

It must NOT come from:

* heavy JavaScript frameworks
* excessive animation
* large libraries
* video backgrounds
* huge image assets
* unnecessary plugins
* excessive gradients
* glassmorphism
* parallax
* decorative effects
* large client-side bundles

### Public performance principle

```text
Fast > Fancy
Useful > Decorative
Simple > Complex
Content > Effects
```

Do not introduce technology merely because it is available.

Every dependency must have a clear purpose.

---

# 4. Technology Rules

The public website should remain compatible with the approved architecture:

* PHP 8.x
* MySQL
* PDO
* HTML5
* CSS3
* Vanilla JavaScript
* Existing application architecture
* Existing shared configuration/bootstrap

Do NOT introduce:

* React
* Vue
* Angular
* Next.js
* Nuxt
* Laravel
* Node.js backend
* SPA architecture
* jQuery unless an existing dependency absolutely requires it
* unnecessary frontend frameworks
* unnecessary CSS frameworks
* unnecessary animation libraries

The public website is a **server-rendered PHP website**.

JavaScript should enhance the experience rather than become a requirement for basic navigation and content.

---

# 5. Public Architecture

The public side must integrate cleanly with the existing MVC-inspired architecture.

Use this general flow:

```text
Browser
   ↓
Public Entry Point / Route
   ↓
Controller
   ↓
Validation
   ↓
Service
   ↓
Repository / Model
   ↓
PDO
   ↓
MySQL
   ↓
View
```

Views must not contain random database queries.

Controllers should not contain large amounts of business logic.

Services should contain business rules.

Repositories/models should handle database access.

Shared layout components should remain reusable.

---

# 6. Public Directory Structure

Inspect the existing project before creating directories.

Where compatible with the existing structure, establish a public-facing organization similar to:

```text
public/
│
├── index.php
├── vehicles/
├── brands/
├── vehicle/
├── how-it-works/
├── track/
├── about/
├── team/
├── contact/
├── faq/
│
├── assets/
│
└── includes/
```

However, **do not blindly create every page directory and implementation now**.

Only create what is necessary for the foundation.

Future specifications will introduce their respective pages.

The final architecture should support clean public URLs such as:

```text
/
 /vehicles/
 /brands/toyota/
 /vehicle/2024-toyota-camry/
 /how-it-works/
 /track/
 /about/
 /team/
 /contact/
 /faq/
```

The exact routing implementation must follow the existing project architecture and hosting constraints.

Do not introduce a complex routing framework.

---

# 7. Public Shared Layout

Create a reusable public layout system.

At minimum, establish reusable components for:

```text
Public Header
Public Navigation
Public Footer
Page Container
Page Header
Buttons
Alerts
Forms
```

Use the existing PHP include/component conventions.

Avoid duplicating the same header, navigation, or footer markup across pages.

For example:

```text
/includes/
    public-header.php
    public-navigation.php
    public-footer.php
```

Use naming consistent with the existing project if different conventions are already established.

Do not create duplicate versions of the same component.

---

# 8. Public Header

Create the global public header.

Desktop navigation direction:

```text
Logo
Vehicles
How It Works
Track Vehicle
About
Contact
Browse Vehicles
```

The final visual implementation should remain clean and restrained.

### Primary navigation

The main CTA should be:

```text
Browse Vehicles
```

It should visually stand out without becoming oversized.

### Logo

Use the actual approved company logo asset when available.

Do not recreate the logo using plain text if an approved logo asset exists.

Do not invent a logo.

---

# 9. Mobile Navigation

The mobile experience must be deliberately designed.

Do not simply compress the desktop navigation.

Mobile header should contain:

```text
Logo                     Menu
```

The menu should open into a clean full-screen or near-full-screen navigation experience.

Include:

* Vehicles
* How It Works
* Track Vehicle
* About
* Contact
* Browse Vehicles

Provide a clearly visible close control.

The menu must:

* be keyboard accessible
* have proper focus behavior
* prevent accidental interaction with the page underneath when open
* work at approximately 320px width and above
* not depend on large JavaScript libraries

Use simple vanilla JavaScript.

No hamburger animation is necessary unless it provides genuine usability value.

---

# 10. Footer

Create a reusable global footer.

The footer should provide:

### Company

* About Us
* Our Team
* How It Works

### Vehicles

* Browse Vehicles
* Brands

### Support

* Track Vehicle
* Contact Us
* FAQ

### Company information

Use real company information only when it exists in the database/configuration/project requirements.

Do not invent:

* phone numbers
* email addresses
* physical addresses
* social media accounts
* opening hours

The footer should remain compact.

Do not turn the footer into a massive multi-column content wall.

---

# 11. Global Design System

Use the approved public design direction:

> Premium Editorial Automotive + International Logistics

The public website should communicate:

* quality
* reliability
* transparency
* international sourcing
* professionalism
* confidence

It should not feel like:

* a generic car dealership template
* a generic SaaS website
* a flashy car-rental website
* an automotive marketplace
* a crypto/tech startup

---

# 12. Colour System

Use the approved palette.

### Primary

```text
Deep Charcoal
#111315
```

### Main Light Background

```text
Warm White
#F8F7F4
```

### White

```text
#FFFFFF
```

### Secondary Text

```text
#62666A
```

### Border

```text
#E3E3DF
```

### Primary Accent

```text
#9E2F2F
```

The accent must be restrained.

Do not make the entire website red.

Avoid unnecessary colour variation.

Do not introduce purple.

Do not use random gradients.

Do not introduce additional colours unless required for functional states such as success, warning, or error.

---

# 13. Typography

Use:

```text
Manrope
```

Prefer only the weights actually required.

Target:

```text
400
500
600
700
```

Do not load every available font weight.

Avoid loading multiple font families.

Typography should carry much of the visual identity.

Approximate hierarchy:

```text
H1:
56–72px desktop
42–48px mobile

H2:
40–48px desktop
32–36px mobile

H3:
24–30px

Body:
16–18px

Small / Meta:
13–14px
```

These are starting values, not rigid requirements.

Visual hierarchy is more important than following arbitrary numbers.

---

# 14. Layout System

Use a consistent maximum content width.

Target:

```text
1200px – 1280px
```

Use responsive horizontal padding.

Do not allow content to become excessively wide on large screens.

Suggested spacing scale:

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

Do not create dozens of arbitrary spacing values.

Whitespace should feel intentional.

Avoid both:

* cramped interfaces
* enormous empty spaces that provide no compositional value

---

# 15. Border Radius

Use restrained corner radii.

Suggested:

```text
Buttons / Inputs:
6–8px

Cards / Images:
8–12px
```

Do not make every element heavily rounded.

Avoid:

```text
30px+
```

pill-shaped UI unless it has a genuine functional purpose.

---

# 16. Buttons

Establish reusable button styles.

Primary:

```text
Filled
Deep Charcoal or approved accent
Clear text
```

Secondary:

```text
Outline or text-based
```

Buttons must:

* have clear hover states
* have visible focus states
* be keyboard accessible
* have adequate touch targets
* not rely on icons alone

Do not use emojis as button icons.

Use simple SVG icons only when they genuinely improve understanding.

---

# 17. Icon Strategy

Use a consistent lightweight icon strategy.

Do not load a large icon library if only a few icons are required.

Prefer:

* inline SVG
* lightweight existing project icon solution
* CSS where appropriate

Do not use:

* emojis
* random icon styles
* mixed icon families

Icons should support meaning rather than decorate every section.

---

# 18. Images and Performance

Images are expected to carry significant visual weight on the public website.

However, images must be aggressively optimized.

Use:

```text
WebP
```

where appropriate.

Support responsive image sizing.

Do not send a 3000–5000px image to a mobile device when a smaller version is sufficient.

Use:

```html
srcset
sizes
```

where appropriate.

Below-the-fold images should normally use:

```html
loading="lazy"
```

Use:

```html
width
height
```

or equivalent aspect-ratio handling to reduce layout shift.

Hero/above-the-fold images should not automatically use lazy loading.

Do not use enormous background images when a responsive `<img>` element is more appropriate.

---

# 19. Image Loading Strategy

Public image rendering should follow this principle:

```text
Small screen
    ↓
small/appropriate image

Medium screen
    ↓
medium image

Large screen
    ↓
large image
```

Do not make every image:

```text
full-resolution original
```

The existing Vehicle Image Management system should eventually provide optimized vehicle images.

Do not bypass that system.

---

# 20. JavaScript Rules

JavaScript must remain minimal.

Use vanilla JavaScript for:

* mobile navigation
* small interactive components
* form enhancements
* lightweight UI interactions
* progressive enhancement

Avoid:

* large JavaScript bundles
* unnecessary animation libraries
* client-side routing
* SPA behavior
* unnecessary DOM manipulation
* polling
* continuous background JavaScript
* expensive scroll listeners

Do not use JavaScript for something that can be achieved efficiently with HTML/CSS.

The website should remain usable when JavaScript is unavailable wherever reasonably possible.

---

# 21. Animation Rules

Animation must be subtle.

Allowed:

* short transitions
* hover transitions
* menu transitions
* small opacity/transform changes

Avoid:

* parallax
* scroll-jacking
* animated gradients
* constant floating elements
* excessive entrance animations
* large-scale motion
* video backgrounds

A visitor should never feel that the website is trying to impress them with animation.

The website should feel confident enough to remain still.

Respect:

```text
prefers-reduced-motion
```

---

# 22. CSS Rules

Keep CSS organized and lightweight.

Do not create massive global stylesheets containing unrelated page-specific rules.

Separate reusable foundation styles from page-specific styles where appropriate.

Avoid unnecessary CSS frameworks.

If an existing framework is already being used in the project, inspect its current usage before adding more dependencies.

Do not introduce Bootstrap solely because it is familiar if the project can remain lighter with existing/custom CSS.

The final public site should load only the CSS it actually needs where practical.

---

# 23. Public Components

Establish reusable components that future specifications can build upon.

Potential components:

```text
Public Header
Public Navigation
Footer
Container
Section
Page Header
Button
Form Field
Alert
Empty State
Loading State
Vehicle Card
Brand Link
```

However, do not fully implement components that depend on future modules.

For example, a complete Vehicle Card should be implemented in the Vehicle Inventory specification rather than invented here.

The foundation may establish the structural pattern only.

---

# 24. Public Page Container

Create a consistent page container.

Example conceptual structure:

```html
<main>
    <div class="container">
        ...
    </div>
</main>
```

The exact implementation can differ.

The container must:

* have a controlled maximum width
* have responsive horizontal padding
* avoid horizontal overflow
* remain readable on mobile
* scale cleanly to large displays

---

# 25. Page Header Foundation

Create a reusable page-header pattern for future pages.

Example:

```text
PAGE LABEL

Page Title

Short supporting description.
```

Keep it concise.

Do not automatically create huge hero sections for ordinary internal pages.

The homepage will have its own hero treatment in Spec 10.

---

# 26. Forms Foundation

Establish consistent public form styling.

Forms should include:

* labels
* inputs
* textarea
* select
* validation states
* error messages
* success messages
* required indicators where appropriate
* focus states

Do not use placeholder text as the only label.

Forms must be accessible.

Do not implement specific Contact or Enquiry functionality yet.

That belongs to the appropriate later specification.

---

# 27. Accessibility

Public foundation must follow basic accessibility principles.

Requirements:

* semantic HTML
* logical heading hierarchy
* keyboard navigation
* visible focus states
* accessible labels
* meaningful button text
* appropriate link text
* alt text for meaningful images
* decorative images appropriately marked
* sufficient colour contrast
* mobile touch targets
* reduced-motion support

Do not use colour alone to communicate important information.

---

# 28. SEO Foundation

Establish reusable SEO foundations.

Every public page should eventually support:

```text
<title>
<meta name="description">
canonical URL
Open Graph metadata where appropriate
```

Use semantic HTML:

```text
<header>
<nav>
<main>
<section>
<article>
<footer>
```

Avoid generic `<div>` structures when semantic elements are appropriate.

Create a reusable mechanism for page metadata rather than hardcoding the same `<title>` on every page.

Do not generate fake SEO content.

---

# 29. URL and Slug Rules

Public URLs should be:

* readable
* lowercase
* descriptive
* stable
* SEO-friendly

Examples:

```text
/vehicles/
/brands/toyota/
/vehicle/2024-toyota-camry/
/how-it-works/
/about/
/team/
/contact/
/faq/
```

Vehicle URLs should use the approved vehicle slug.

Do not expose unnecessary database IDs in public URLs when a stable slug is available.

Do not invent slug formats that conflict with the existing vehicle management implementation.

---

# 30. Error and Empty States

Establish lightweight reusable patterns for:

### Not Found

Example concept:

```text
Page Not Found

The page you're looking for doesn't exist or may have moved.

Back to Home
```

### Empty Content

Example:

```text
Nothing available yet.
```

Do not create fake vehicles or fake content to make pages look populated.

### System Error

Do not expose:

* SQL errors
* stack traces
* filesystem paths
* PHP warnings
* database credentials
* internal implementation details

Show a clean user-facing message.

Detailed information belongs in server-side logs.

---

# 31. Loading States

Use loading states only where asynchronous interaction genuinely exists.

Do not create skeleton loaders for static server-rendered content merely because modern websites use them.

For normal server-rendered page navigation:

```text
No artificial loading screen.
```

The page should simply load quickly.

Skeletons may later be used for specific dynamic interactions if they provide real UX value.

---

# 32. No Fake Content

Do not create:

* fake vehicles
* fake prices
* fake team members
* fake customer reviews
* fake company statistics
* fake testimonials
* fake addresses
* fake phone numbers
* fake social media accounts
* fake shipping data

The public website must eventually consume real data from the database or approved project content.

---

# 33. Public/Admin Separation

The public website must not expose admin functionality.

Do not:

* reuse admin navigation publicly
* expose admin URLs through public UI
* expose admin data
* expose internal audit logs
* expose security information
* expose database information
* expose internal IDs unnecessarily

The public layout and admin layout should remain visually and structurally separate.

---

# 34. Security

Continue using the existing security foundation.

Public pages must still:

* use prepared statements
* validate input
* escape output
* prevent SQL injection
* prevent XSS
* avoid exposing internal errors
* validate route parameters
* avoid unsafe filesystem access

Public forms introduced in later specifications must use the existing CSRF/security conventions where state-changing requests are involved.

Do not weaken existing authentication or security mechanisms while implementing the public layer.

---

# 35. Database Usage

The foundation itself should not introduce new database tables.

Do not create fake public data.

Future public pages will consume existing entities such as:

```text
Brands
Vehicles
Vehicle Images
Team Members
Website Content
Settings
```

Use the existing database schema.

Do not create duplicate public versions of existing entities.

---

# 36. Performance Budget Mindset

Treat performance as a first-class requirement.

Before adding a dependency, ask:

```text
Can this be done with HTML?
Can this be done with CSS?
Can this be done with a small vanilla JS function?
Can an existing project utility handle it?
Is this dependency actually necessary?
```

Prefer the smallest practical solution.

Avoid unnecessary network requests.

Avoid loading assets that are not required for the current page.

Avoid rendering large amounts of unused HTML.

Avoid excessive DOM complexity.

---

# 37. Responsive Requirements

The public foundation must work at:

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

Test:

* navigation
* typography
* spacing
* buttons
* forms
* images
* footer
* overflow
* menu behavior

Do not simply shrink desktop layouts.

The mobile layout should be deliberately composed.

---

# 38. Browser Requirements

Test in current versions of:

* Chrome
* Edge
* Firefox

The site should also remain usable on modern mobile browsers.

Do not introduce browser-specific hacks unless necessary.

---

# 39. Public Homepage Is NOT Part of This Specification

Do NOT build the complete homepage yet.

Do not implement:

* hero content
* featured vehicles
* browse-by-brand section
* how-it-works homepage section
* why-choose-us section
* homepage team section
* homepage contact CTA
* homepage-specific content blocks

Those belong to:

```text
Spec 10 — Public Homepage
```

The foundation should only make those sections easy to build later.

---

# 40. Do NOT Implement Future Modules

Do not implement:

* Vehicle Inventory
* Vehicle Details
* Brand Pages
* How It Works page
* About page
* Team public page
* Contact page
* FAQ
* Public Tracking
* Enquiry processing
* Brevo integration
* shipment tracking logic
* customer accounts
* payments
* booking system

Those will be introduced in separate specifications.

---

# 41. Lightweight Dependency Policy

Before installing any package, library, framework, or external asset:

1. Determine whether it is actually necessary.
2. Check whether the existing stack already solves the problem.
3. Consider its impact on page size and requests.
4. Prefer native browser functionality.
5. Prefer small dependencies over large frameworks.
6. Document the reason if a dependency is introduced.

Do not install dependencies simply because a tutorial uses them.

---

# 42. Testing

After implementation, test:

### Layout

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

### Navigation

Test:

* desktop navigation
* mobile menu
* opening menu
* closing menu
* keyboard navigation
* focus
* links
* no horizontal overflow

### Visual

Check:

* typography
* spacing
* colours
* buttons
* footer
* forms
* responsive behavior
* image behavior

### Technical

Check:

* no PHP warnings
* no PHP notices
* no fatal errors
* no JavaScript console errors
* no broken asset paths
* no broken internal links
* no database credentials exposed
* no debug information exposed

### Performance

Check that the foundation does not introduce:

* unnecessary libraries
* large JavaScript bundles
* excessive CSS
* unnecessary requests
* unoptimized images
* blocking scripts where avoidable

Use browser DevTools to inspect:

```text
Network
Console
Performance
Lighthouse
```

Do not chase arbitrary Lighthouse scores at the expense of real functionality, but address obvious performance problems.

---

# 43. Regression Testing

After implementation, verify that existing functionality still works:

* admin login
* 2FA
* password reset
* logout
* admin authorization
* dashboard
* Brands
* Vehicles
* Vehicle Image Management
* Team Management if already implemented

Do not modify existing admin functionality unnecessarily.

---

# 44. PROGRESS.md

After successful implementation and testing, update:

```text
PROGRESS.md
```

Record:

```text
Spec 09 — Public Website Foundation & Global UI
Status: Implemented
```

Include a concise summary of:

* public architecture
* shared layout
* navigation
* responsive foundation
* design system
* SEO foundation
* performance/lightweight strategy
* testing completed

Set the next planned specification to:

```text
Spec 10 — Public Homepage
```

Only update this after the implementation has actually been tested.

---

# 45. Definition of Done

Spec 09 is complete only when:

* public foundation exists
* public and admin layouts are separated
* shared header exists
* shared navigation exists
* responsive mobile menu works
* shared footer exists
* design tokens are established
* typography is established
* button/form foundations exist
* responsive system works
* SEO foundation exists
* public error/empty-state foundation exists
* accessibility basics are implemented
* lightweight architecture is preserved
* no unnecessary frontend framework has been introduced
* no heavy animation system has been introduced
* no fake business content has been introduced
* existing admin functionality still works
* browser console is clean
* PHP warnings/errors are resolved
* responsive testing passes
* performance has been reviewed
* `PROGRESS.md` has been updated

---

# 46. Important Stop Rule

After completing and testing Spec 09:

**STOP.**

Do not implement Spec 10.

Do not build the homepage yet.

Report:

1. What was implemented
2. Files created/modified
3. Dependencies added, if any
4. Performance decisions
5. Tests performed
6. Issues found and fixed
7. Regression status
8. `PROGRESS.md` update

Then wait for the next specification.
