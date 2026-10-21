# Specification 10A — Homepage Structure & Hero

## 1. Purpose

Implement the first customer-facing section of the Amponsah Autos homepage.

This specification covers only:

* Homepage route
* Homepage structural foundation
* Existing public header integration
* Hero section
* Hero imagery
* Lightweight image carousel
* Primary calls to action
* Responsive behavior
* Accessibility
* Performance and credibility

Do **not** implement the remaining homepage sections yet.

The next specifications will introduce:

* Featured Vehicles
* Browse by Brand
* How It Works
* Trust/Why Choose Us
* Tracking section
* About section
* Team preview
* Final CTA

---

# 2. Core Objective

The hero is the visitor's first impression of Amponsah Autos.

Its primary purpose is not simply to look attractive.

It must immediately communicate:

> **Amponsah Autos is a professional, legitimate vehicle-import business connecting Canada and Ghana.**

The hero should establish:

1. What the company does.
2. Where vehicles are sourced.
3. Where they are delivered.
4. That the company operates within a real international vehicle/shipping process.
5. What the visitor should do next.

The visual experience should create:

**Credibility → Confidence → Interest → Action**

---

# 3. Design Direction

Follow the approved public design direction:

> **Premium Editorial Automotive + International Logistics**

The hero should feel:

* professional
* established
* trustworthy
* premium
* calm
* realistic
* sophisticated
* automotive
* internationally connected

It should NOT feel:

* flashy
* like a generic car dealership template
* like a car-rental website
* like a SaaS landing page
* like a cryptocurrency website
* like an automotive marketplace
* overly futuristic
* overly animated
* artificial

The hero must communicate confidence rather than trying too hard to impress.

---

# 4. Hero Concept

Use a large, high-quality visual hero with an image carousel.

The carousel should rotate through carefully selected imagery representing the real business.

Recommended visual categories:

### Slide 1 — Premium Vehicle / Dealership

A high-quality vehicle in a professional dealership or automotive environment.

Purpose:

* communicate vehicle quality
* establish automotive credibility
* create immediate visual appeal

### Slide 2 — Canadian Automotive / Logistics Environment

A realistic scene showing vehicles prepared for export or an authentic Canadian automotive/logistics environment.

Purpose:

* reinforce international sourcing
* establish the Canada connection

### Slide 3 — Port / Containers / Shipping

A professional image showing:

* shipping containers
* cargo vessel
* vehicle shipping
* port logistics

Purpose:

* communicate international transportation
* make the import process visually believable

### Slide 4 — Vehicles Being Prepared for Export

A realistic logistics/export scene involving vehicles being prepared for transportation.

Purpose:

* reinforce that vehicles move through a real supply chain
* increase confidence in the company's process

Use approximately **3–4 images**.

Do not create a 10+ slide carousel.

---

# 5. Image Credibility

The hero images are extremely important.

They must look:

* authentic
* professional
* realistic
* high quality
* relevant to vehicle importing
* geographically/logistically believable

Avoid images that obviously look:

* AI generated
* excessively edited
* staged in an unrealistic way
* unrelated to the company's actual business
* generic corporate stock photography

Do not use random luxury-car photographs simply because they look attractive.

Every image must contribute to the story:

```text
Vehicle
   ↓
Canada
   ↓
Logistics
   ↓
Shipping
   ↓
Ghana
```

The imagery should make the business process feel real.

---

# 6. Image Sources

If image assets already exist in the project, inspect and reuse appropriate approved assets.

Do not overwrite existing approved assets.

If suitable hero images do not exist yet:

**Do not invent fake company photography.**

Use clearly structured image placeholders/configuration so approved images can be inserted without redesigning the hero.

If external stock images are temporarily used during development, keep their usage clearly separated from production assets and make replacement straightforward.

Do not hardcode random image URLs throughout the HTML.

---

# 7. Hero Image Optimization

The website must remain lightweight.

Hero imagery must therefore be optimized aggressively without visibly damaging quality.

Preferred format:

```text
WebP
```

Use appropriately sized images.

Do not use 4K/8K images simply because the source image is available at that resolution.

The desktop hero should receive a suitable desktop image.

Mobile should receive an appropriately sized mobile image where practical.

Use responsive image techniques where appropriate:

```html
srcset
sizes
```

Do not load multiple full-resolution hero images unnecessarily.

---

# 8. Carousel Architecture

Implement the carousel using **lightweight vanilla JavaScript**.

Do NOT install:

* Swiper
* Slick
* Owl Carousel
* Splide
* large animation libraries
* React carousel packages

The carousel is simple enough to implement without a dependency.

The carousel should:

* automatically rotate
* transition smoothly
* remain lightweight
* pause when appropriate
* support manual navigation
* support keyboard interaction where controls exist
* support reduced motion
* not interfere with CTA interaction

---

# 9. Carousel Transition

Use a subtle transition.

Preferred behavior:

```text
Current image
     ↓
soft crossfade
     ↓
next image
```

Avoid:

* dramatic sliding
* 3D rotations
* zoom effects
* spinning
* parallax
* complex transformations

The transition should feel premium and restrained.

Suggested transition duration:

```text
600ms – 1000ms
```

The exact value can be adjusted during testing.

---

# 10. Automatic Rotation

The hero should automatically change images.

Target interval:

```text
5–7 seconds
```

Do not change images too quickly.

Visitors must have enough time to understand the slide and interact with the page.

The carousel should not restart unnecessarily every few seconds due to DOM updates or other events.

---

# 11. Starting Slide

The hero should not always feel identical on every visit.

Use a lightweight starting-slide strategy.

For example, the starting slide may be selected based on a small randomized index when the page initializes.

However:

* do not make the hero unpredictable
* do not change the text with every image
* do not create a different CTA on every slide

The core message must remain consistent.

---

# 12. Core Hero Content

The primary headline should be:

> **Find Your Next Vehicle.**

Supporting copy:

> **Quality vehicles sourced from Canada and delivered to Ghana.**

Keep this copy concise.

Do not create a large paragraph explaining the entire company in the hero.

The visitor should understand the proposition immediately.

---

# 13. Primary CTA

Primary button:

> **Browse Vehicles**

Destination:

```text
/vehicles/
```

The link should use the actual public vehicle route established by the project.

Do not hardcode a route if the existing routing system uses a different approved structure.

---

# 14. Secondary CTA

Secondary button:

> **Track Your Vehicle**

Destination should point to the public tracking page foundation.

If the tracking page has not yet been implemented, establish the route/link according to the approved architecture without implementing the tracking functionality in this specification.

Do not invent shipment information.

---

# 15. Hero Text Positioning

The text must remain highly readable regardless of the background image.

Use a restrained dark overlay or equivalent contrast technique.

The overlay should:

* improve readability
* preserve the photograph
* avoid making the image look artificially dark

Do not use a heavy black overlay that destroys the image.

Do not use gradients simply for decoration.

The content should have strong contrast with the background.

---

# 16. Hero Layout

Desktop concept:

```text
┌───────────────────────────────────────────────────────┐
│ Header                                                 │
├───────────────────────────────────────────────────────┤
│                                                       │
│                                                       │
│        FIND YOUR NEXT VEHICLE.                        │
│        Quality vehicles sourced from                 │
│        Canada and delivered to Ghana.                 │
│                                                       │
│        [ Browse Vehicles ] [ Track Your Vehicle ]     │
│                                                       │
│                                      [hero image]      │
│                                                       │
└───────────────────────────────────────────────────────┘
```

The exact composition may be adjusted according to the selected photography.

Do not force a split-screen layout if the photograph works better as a full-width background.

The final design should prioritize the photograph and readable typography.

---

# 17. Hero Height

The hero should be substantial but not excessive.

Desktop target:

```text
Approximately 650–760px
```

Mobile target:

```text
Approximately 560–680px
```

Adjust according to:

* viewport height
* image composition
* text length
* navigation height

Do not create a hero that consumes the entire screen unnecessarily.

Avoid:

```text
100vh
```

as a rigid requirement.

The visitor should be able to understand that more content exists below the hero.

---

# 18. Header Integration

Use the public header created in Spec 09.

Do not recreate the header.

The hero must sit naturally below or integrate with the existing public navigation according to the established layout.

Desktop navigation remains:

```text
Logo
Vehicles
How It Works
Track Vehicle
About
Contact
Browse Vehicles
```

Mobile navigation remains the existing Spec 09 implementation.

Do not redesign the global navigation as part of this specification unless an integration issue is discovered.

---

# 19. Mobile Hero

The mobile hero must be deliberately composed.

Do not simply scale down the desktop version.

At mobile widths:

```text
320px
375px
390px
414px
```

Test:

* headline wrapping
* supporting text
* button widths
* image composition
* navigation
* carousel
* overlay
* vertical spacing

Buttons may stack vertically where necessary.

Example:

```text
[ Browse Vehicles ]

[ Track Your Vehicle ]
```

Do not allow buttons to become too small.

---

# 20. Mobile Image Composition

Mobile photography must remain meaningful.

A desktop photograph may crop badly on mobile.

Where necessary, provide mobile-specific image crops.

For example:

```text
Desktop:
wide vehicle/port composition

Mobile:
vehicle or focal subject positioned centrally
```

Do not allow the subject of the photograph to be hidden behind the text.

Use CSS `object-position` or appropriate responsive image assets.

---

# 21. Carousel Controls

Keep controls minimal.

Possible controls:

* previous
* next
* slide indicators

Do not fill the hero with UI.

If indicators are used, keep them subtle.

The controls must be accessible.

Buttons must have meaningful accessible labels such as:

```text
Previous slide
Next slide
```

Do not use emojis for carousel controls.

Use lightweight SVG icons if icons are necessary.

---

# 22. Pause Behavior

The carousel should pause when appropriate.

At minimum:

* pause when the user hovers over the carousel on desktop
* pause while keyboard focus is inside the carousel controls
* stop or reduce motion when `prefers-reduced-motion: reduce` is enabled

If practical, also pause when the browser tab is hidden.

Do not continuously run timers in a hidden tab.

---

# 23. Reduced Motion

Respect:

```css
@media (prefers-reduced-motion: reduce)
```

When reduced motion is enabled:

* disable automatic animated transitions
* avoid unnecessary movement
* allow a simple image change or static first slide
* maintain all content and functionality

Accessibility takes priority over animation.

---

# 24. Hero Overlay

Use a subtle overlay to ensure text contrast.

The overlay must be responsive to the selected image.

Do not apply an excessively dark filter.

The goal is:

```text
Readable text
+
Visible photography
```

not:

```text
Dark background
+
barely visible photograph
```

---

# 25. Trust Signals

Do not add fake statistics or claims.

Do NOT invent:

```text
10,000+ vehicles sold
98% customer satisfaction
15 years experience
500+ happy customers
```

unless such information is already verified and approved in the project.

The hero establishes credibility through:

* authentic photography
* clear business proposition
* professional design
* clear navigation
* transparent CTAs

Do not manufacture credibility.

---

# 26. No Testimonials Yet

Do not add:

* testimonials
* customer reviews
* star ratings
* fake quotes
* trust badges

These belong to later homepage/content specifications if approved.

---

# 27. No Hero Video

Do not use:

* background video
* autoplay video
* video overlays
* YouTube embeds
* Vimeo embeds

The carousel must use optimized images.

This is important for:

* performance
* mobile data usage
* page speed
* reliability

---

# 28. No Heavy Effects

Do not implement:

* parallax
* animated gradients
* floating shapes
* particles
* glassmorphism
* 3D objects
* animated vehicles
* complex masks
* excessive blur
* continuous zoom animations

The photography itself provides the visual impact.

---

# 29. SEO

The homepage should have appropriate metadata.

Suggested title:

```text
Amponsah Autos | Quality Vehicles Sourced from Canada
```

The exact title can be refined based on the project's SEO configuration.

Suggested description should clearly communicate:

* vehicles
* Canada
* Ghana
* importing/delivery

Do not keyword-stuff the page.

The hero headline should be an appropriate semantic heading, preferably:

```html
<h1>Find Your Next Vehicle.</h1>
```

There should only be one primary H1 on the homepage.

---

# 30. Accessibility

The hero must:

* use semantic HTML
* have one H1
* provide alt text for meaningful images
* mark decorative imagery appropriately
* provide accessible carousel controls
* support keyboard interaction
* have visible focus states
* maintain sufficient contrast
* respect reduced motion

If the background image is implemented using CSS, ensure the equivalent image information is not necessary for understanding the content.

If an image is purely decorative, do not create unnecessary screen-reader text.

---

# 31. Performance Requirements

Performance is a hard requirement.

The implementation must:

* use optimized images
* avoid large libraries
* avoid unnecessary JavaScript
* avoid unnecessary requests
* avoid loading all images at full resolution
* avoid blocking JavaScript
* avoid unnecessary fonts
* avoid video
* avoid heavy animation libraries

Only the assets required for the hero should be loaded.

If appropriate, preload the first hero image because it is above the fold.

Do not preload every carousel image.

---

# 32. Image Preloading Strategy

Only the initial hero image should receive priority.

Additional slides should load progressively where practical.

Possible strategy:

```text
Initial slide
    ↓
high priority

Next slide
    ↓
preload/load shortly afterward

Remaining slides
    ↓
load progressively
```

Do not create a large initial network burst.

---

# 33. Layout Stability

The hero must not cause major layout shifts.

Reserve the appropriate image area.

Use:

* fixed/aspect-ratio containers
* explicit dimensions where appropriate
* predictable typography
* stable navigation height

Do not allow the page to jump when the next image loads.

---

# 34. Browser and Device Testing

Test at minimum:

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

* first load
* carousel rotation
* manual controls
* mobile menu
* CTA links
* keyboard navigation
* reduced motion
* slow network
* image loading
* browser resize

---

# 35. Performance Testing

Use browser DevTools to inspect:

```text
Network
Console
Performance
Lighthouse
```

Check:

* image sizes
* request count
* JavaScript size
* CSS size
* console errors
* layout shifts
* render blocking
* mobile performance

If the hero is visually impressive but unnecessarily heavy, optimize it.

The requirement is:

> **Premium visual quality without premium-level page weight.**

---

# 36. Security

Do not introduce security weaknesses.

Continue using:

* existing application bootstrap
* existing output escaping
* existing configuration system
* existing security conventions

Do not expose:

* database credentials
* server paths
* environment variables
* debug output

---

# 37. Files and Architecture

Before creating files, inspect the existing public foundation.

Reuse:

* public header
* navigation
* footer
* CSS tokens
* responsive utilities
* JS utilities
* application bootstrap

Do not duplicate them.

Only create files necessary for the homepage hero.

Potential additions may include:

```text
homepage controller
homepage view
homepage-specific CSS
homepage-specific JS
hero image configuration/data
```

Use the project's actual naming conventions.

Do not create all future homepage sections yet.

---

# 38. Data

This specification does not require database queries for:

* vehicles
* brands
* team members
* enquiries
* shipments

Do not create fake database records.

The hero itself can use approved static configuration/content while the dynamic homepage sections are introduced later.

---

# 39. Testing Existing Functionality

After implementation, verify that:

* admin login works
* 2FA works
* password reset works
* dashboard works
* brands work
* vehicles work
* vehicle images work
* team management works if implemented
* public navigation remains functional

Do not allow homepage work to break the admin system.

---

# 40. Definition of Done

10A is complete only when:

* homepage route works
* existing public header is integrated
* hero section is implemented
* hero headline is correct
* supporting copy is correct
* Browse Vehicles CTA works
* Track Your Vehicle CTA points to the appropriate public route
* hero carousel works
* 3–4 appropriate image slots are supported
* images are optimized
* carousel automatically rotates
* transition is subtle
* manual controls work if implemented
* carousel is accessible
* reduced motion is respected
* mobile composition works
* desktop composition works
* no horizontal overflow exists
* no heavy libraries are introduced
* no video is used
* no fake trust claims are used
* no fake business information is used
* page remains lightweight
* browser console is clean
* PHP warnings/errors are resolved
* existing admin functionality remains intact
* `PROGRESS.md` is updated only after successful testing

---

# 41. PROGRESS.md

After successful implementation and testing, update:

```text
PROGRESS.md
```

Record:

```text
Spec 10A — Homepage Structure & Hero
Status: Implemented
```

Include:

* homepage route
* hero implementation
* carousel
* responsive behavior
* performance strategy
* accessibility testing
* regression testing

Set the next planned task to:

```text
Spec 10B — Homepage Featured Vehicles
```

---

# 42. Important Stop Rule

After completing and testing 10A:

**STOP.**

Do NOT implement:

* Featured Vehicles
* Browse by Brand
* How It Works
* Why Choose Us
* Tracking section
* About section
* Team preview
* Final CTA
* FAQ
* Contact section

Those belong to later specifications.

Report:

1. What was implemented
2. Files created/modified
3. Hero image implementation
4. Carousel implementation
5. Performance decisions
6. Accessibility testing
7. Responsive testing
8. Issues found and fixed
9. Regression status
10. `PROGRESS.md` update

Then wait for the next specification.
