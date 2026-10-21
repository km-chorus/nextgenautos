# 04 — Design and UX Rules

## 1. Purpose

This document defines the visual identity, user experience, interface behavior, consistency standards, and interaction principles for the Vehicle Import Platform.

These rules apply to both the public website and the administrative interface unless a specific rule is clearly limited to one area.

The goal is a professional, clean, modern, trustworthy automotive experience that is easy to use without unnecessary visual complexity.

---

# 2. Overall Design Direction

The visual direction is:

**Premium Editorial Automotive + International Logistics**

The website should communicate:

* Trust
* Quality
* Professionalism
* Reliability
* Transparency
* Simplicity
* International business
* Automotive expertise

The design should feel established and premium without looking extravagant.

It should not look like:

* A generic car marketplace
* A cheap dealership template
* A government portal
* A cryptocurrency website
* A generic SaaS dashboard
* An over-designed technology demo

Note (2026-10-14): the owner approved autotrader.com.gh as the visual reference for the public site (palette, Nunito, flat bordered surfaces, dark search panel). Only the visual language is adopted. The site must still read as one importer's showroom, not a multi-seller marketplace.

---

# 3. Core UX Principle

Every element should help the user do at least one of these:

**Browse → Understand → Trust → Act → Track**

If an element does not meaningfully contribute to the user's journey, it should be questioned or removed.

Do not add sections simply because there is available space.

---

# 4. Explicitly Out of Scope — Visual Design

The following are prohibited unless explicitly approved:

* Emojis in the UI
* Generic/random color combinations
* Excessive colors
* Heavy text blocks
* Visually noisy layouts
* Inconsistent spacing
* Inconsistent typography
* Inconsistent buttons
* Inconsistent forms
* Inconsistent navigation
* Generic/template-looking layouts
* Excessive cards
* Excessive rounded containers
* Glassmorphism
* Neon effects
* Unnecessary gradients
* 3D effects
* Decorative blobs
* Excessive icons
* Unnecessary illustrations
* Excessive animations
* Huge text used without purpose
* Repeated information
* Excessive badges
* Excessive shadows
* Decorative elements that compete with vehicle photography
* UI elements that do not serve a clear purpose

---

# 5. No Emojis

Emojis must not be used as part of the website interface.

This includes:

* Navigation
* Buttons
* Headings
* Vehicle cards
* Forms
* Alerts
* Notifications
* Dashboard
* Empty states
* Error messages
* Success messages

Use professional typography, spacing, icons, and visual hierarchy instead.

---

# 6. Color System

The interface must use a controlled color system.

## Approved Foundation (owner decision, 2026-10-14)

The public website's visual system is referenced from autotrader.com.gh (colours, typography, flat border-driven surfaces, search-led hero panel), adapted to this company's import business. It is a reference, not a copy: no branding, wording, marketplace structure (regions, dealers, "Post an Ad", multi-category listings) or assets are taken from that site.

Scope: **public website only.** The admin interface keeps its existing styling (`assets/css/admin.css`, Manrope) until a separate decision.

Tokens live in `assets/css/public.css` (`:root`). Use the tokens, never raw hex values, in public stylesheets.

| Token | Value | Use |
| --- | --- | --- |
| `--ink` | `#2B2F35` | Text, dark charcoal panels (footer, hero search panel, auth band) |
| `--ink-dark` | `#1F2227` | Photo overlays, hover on dark |
| `--page` / `--surface` | `#FFFFFF` | Page background and content surfaces |
| `--tint` | `#F8F9FA` | Subtle fills (placeholders, disabled fields) |
| `--muted` | `#6B7280` | Secondary text (4.8:1 on white) |
| `--line` | `#E5E7EB` | Borders and dividers |
| `--line-strong` | `#C9CDD3` | Hover borders |
| `--accent` | `#D42A25` | The one action colour: primary buttons, current nav item, emphasis links, errors |
| `--accent-dark` | `#B0221E` | Accent hover |
| `--neutral` | `#6C757D` | Secondary (neutral) buttons |

The reference red `#E3342F` gives only 4.4:1 with white text, which fails WCAG AA for normal text, so it was darkened to `#D42A25` (about 5.0:1) by owner decision.

Previous foundation (superseded on 2026-10-14): charcoal `#111315`, warm white `#F8F7F4`, muted `#62666A`, border `#E3E3DF`, burgundy accent `#9E2F2F`.

---

# 7. Color Rules

* Use a limited palette.
* One primary accent should dominate.
* Do not introduce random colors for individual sections.
* Do not use a different color for every action.
* Semantic colors may be used for success, warning, error, and information.
* Semantic colors must remain consistent throughout the application.
* Color must not be the only way information is communicated.
* Maintain sufficient contrast.
* Avoid visually heavy backgrounds.
* Avoid excessive colored containers.
* Do not use purple as a default design solution.
* Do not use gradients merely to make the interface look more modern.

The approved color system should be treated as a design token system rather than manually chosen colors throughout individual pages.

---

# 8. Typography

Primary typeface (public website, 2026-10-14):

**Nunito** (self-hosted variable font, `assets/fonts/nunito-latin-wght.woff2`, SIL OFL). The admin keeps Manrope.

Typography should create clear hierarchy without making every heading enormous.

Approved hierarchy:

| Role | Size / line height | Weight |
| --- | --- | --- |
| H1 / display | 32px (28px on phones) / 1.2 | 700 |
| Homepage hero title | 28–40px | 700 |
| H2 | 22px / 1.2 | 500 |
| H3 | 20px / 1.2 | 500 |
| Body | 15px / 24px | 400 |
| Small / labels | 12–14.4px | 400–600 |

Section headings use medium weight (500), not heavy bold. Letter spacing stays 0 for normal text; uppercase only for small utility labels.

---

# 9. Typography Rules

* Use a consistent font family.
* Use weight to establish hierarchy.
* Avoid excessive font weights.
* Avoid unnecessary all-caps text.
* Avoid long paragraphs where shorter content is possible.
* Break complex information into scannable sections.
* Do not use oversized text simply to fill space.
* Maintain readable line lengths.
* Ensure sufficient contrast between primary and secondary text.

---

# 10. Content Density

The interface must avoid heavy text.

Information should be:

* Concise
* Structured
* Scannable
* Relevant
* Easy to understand

Prefer:

* Short paragraphs
* Clear headings
* Lists
* Specification groups
* Meaningful whitespace

Avoid:

* Large walls of text
* Repeating the same information
* Unnecessary marketing language
* Long explanations where a concise explanation is sufficient

The goal is not to remove useful information. The goal is to present useful information efficiently.

---

# 11. Layout System

Preferred maximum content width:

**1200–1280px**

Layouts should have:

* Clear alignment
* Consistent margins
* Predictable spacing
* Strong visual hierarchy
* Comfortable whitespace

The interface should not feel cramped.

It should also not contain excessive empty space without purpose.

---

# 12. Spacing System

Use a consistent spacing scale.

Preferred base values:

```text id="a7o4x1"
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

Avoid arbitrary spacing values when an existing spacing value provides the same result.

---

# 13. Border Radius

Use restrained corner radii (2026-10-14):

* Buttons and inputs: 4px (`--radius`)
* Cards/images: 8px (`--radius-lg`)
* Links and flat utility elements: 0
* Full (pill/circle) radius only for small dots and round icon controls

Avoid making every element heavily rounded.

The interface should feel professional rather than playful.

## Elevation

The public interface is flat. No drop shadows on cards, panels or buttons. Depth comes from background contrast (white against charcoal), borders and padding. The only permitted shadow-like effect is a focus ring.

Photographs under text use a charcoal overlay (`--ink-dark`) for legibility, not a coloured tint.

---

# 14. Cards

Cards should be used only when they provide a meaningful grouping of information.

Do not turn every section into a card.

Avoid:

* Card inside card
* Card grids for simple information
* Excessive shadows
* Excessive rounded containers
* Repeated identical cards that provide little value

Vehicle cards are appropriate because vehicles are distinct items.

---

# 15. Photography

Vehicle photography should carry significant visual weight.

Images should:

* Be high quality.
* Be properly cropped.
* Maintain consistent presentation.
* Load efficiently.
* Use appropriate responsive sizing.
* Avoid unnecessary overlays.

The design should allow vehicle photography to be one of the strongest visual elements of the site.

---

# 16. Buttons

Buttons must have a consistent visual system.

Primary actions should be visually prominent.

Approved button system (2026-10-14):

* `.btn--primary`: solid red (`--accent`), white text. The one strong action per area.
* `.btn--secondary`: solid neutral grey (`--neutral`), white text.
* `.btn--outline`: transparent with an ink border (light border on dark panels).
* Text link: underlined, no chrome.

Examples:

* Browse Vehicles
* View Vehicle
* Contact Us
* Request Information
* Calculate Import Duty
* Track Vehicle

Secondary actions may use:

* Outline buttons
* Text buttons
* Subtle links

Do not create a unique button style for every page.

---

# 17. Navigation

Desktop navigation should remain simple.

A possible structure is:

```text
Logo
Vehicles
How It Works
Track Vehicle
About
Contact
Browse Vehicles
```

Mobile navigation should provide a clean menu.

Navigation should remain consistent across the public website.

---

# 18. Homepage Composition

The homepage may follow this general hierarchy:

1. Navigation
2. Hero
3. Featured Vehicles
4. Browse by Brand
5. How It Works
6. Track Your Vehicle
7. About / Trust
8. Why Choose Us
9. Team Preview
10. Contact CTA
11. Footer

Not every section needs a different background color.

Sections should transition naturally through spacing, typography, imagery, and subtle separation.

---

# 19. Hero Design

The hero should immediately communicate:

* What the company does.
* What customers can do.
* Where the business operates.

Possible messaging direction:

**Find Your Next Vehicle.**

Supporting direction:

**Quality vehicles sourced from Canada and delivered to Ghana.**

Primary actions:

* Browse Vehicles
* Track Your Vehicle

The final copy belongs to the relevant homepage specification.

---

# 20. Vehicle Cards

Vehicle cards should prioritize:

* Vehicle image
* Year
* Make/model
* Trim or key specification
* Price
* Availability where appropriate
* View Vehicle action

Do not display every vehicle field on the card.

The purpose of the card is to encourage the user to understand the vehicle and open its details.

---

# 21. Vehicle Detail Pages

Vehicle detail pages should prioritize:

* Large vehicle photography
* Vehicle identity
* Price
* Key specifications
* Features
* Shipping information
* Duty information
* Contact/purchase action

The page should provide detailed information without becoming a spreadsheet.

---

# 22. Forms

Forms must be:

* Clear
* Simple
* Consistent
* Accessible
* Responsive

Each field should have:

* A meaningful label
* Appropriate input type
* Useful validation
* Clear error feedback

Avoid asking users for information that is not necessary for the specific task.

---

# 23. Form Validation

Validation should happen:

* Client-side for immediate UX feedback.
* Server-side for actual security and data integrity.

Client-side validation must never be treated as a security boundary.

---

# 24. Clean Error Feedback

Errors must be understandable and actionable.

A good error should communicate:

1. What went wrong.
2. What the user should do next, when possible.

Avoid vague messages.

Bad:

> Invalid.

Better:

> Enter a valid email address.

For system-level failures, provide a safe user-facing message without exposing internal technical details.

---

# 25. Success Feedback

Successful actions must provide clear confirmation.

Examples:

* Enquiry submitted successfully.
* Vehicle saved successfully.
* Images uploaded successfully.
* Password updated successfully.

Success feedback should not be excessive or disruptive.

---

# 26. Loading States

Interactive operations should provide appropriate loading feedback when a user may otherwise wonder whether the system is responding.

Examples:

* Form submission
* Image upload
* Admin save
* Search/filter operations
* Data loading

Do not show unnecessary loading animations for operations that complete instantly.

---

# 27. Skeleton Loading

Skeleton loading should be used where content genuinely requires loading time and a placeholder improves the experience.

Skeletons should:

* Match the approximate structure of the content.
* Prevent major layout shifts.
* Be visually subtle.
* Use restrained animation if animation is needed.
* Disappear when actual content becomes available.

Skeleton loading must not become decorative animation.

---

# 28. Empty States

When a page has no data, the interface must explain the state.

Examples:

* No vehicles currently available.
* No enquiries found.
* No shipment information available yet.

An empty state should tell the user what happened and what they can do next where applicable.

Do not display a completely blank page.

---

# 29. Disabled States

Disabled controls must look and behave consistently.

A disabled control should clearly communicate that the action is temporarily unavailable.

Do not use disabled styling to hide important information from users.

---

# 30. Security Feedback UI

Security-related interfaces may include:

* Password-strength indicators
* Password requirements
* Password visibility controls
* Upload validation feedback
* Session/security notices
* Login attempt feedback
* Rate-limit feedback

Security feedback should improve understanding without revealing sensitive implementation details.

---

# 31. Password Strength

Where passwords are created or changed, the interface should communicate password requirements clearly.

A password-strength indicator may provide progressive feedback such as:

* Weak
* Fair
* Good
* Strong

The indicator is a user-experience aid.

The server remains responsible for enforcing the actual password requirements.

---

# 32. Status Indicators

Status indicators must be consistent.

Examples:

* Available
* Reserved
* Sold
* Shipment Booked
* In Transit
* Arrived
* Delivered

Status should use:

* Consistent labels
* Consistent semantic colors
* Text labels in addition to color where appropriate

Do not use random badge colors.

---

# 33. Icons

Icons may be used when they improve comprehension or navigation.

Rules:

* Use one consistent icon style.
* Icons must have a purpose.
* Do not replace important text with ambiguous icons.
* Do not fill the interface with icons simply for decoration.
* Do not use emojis as icons.

---

# 34. Animation and Motion

Motion should be subtle and purposeful.

Acceptable:

* Button transitions
* Menu transitions
* Gallery transitions
* Accordion transitions
* Small content reveals
* Hover feedback

Avoid:

* Heavy parallax
* Animated gradients
* Floating objects
* Constant movement
* Excessive page transitions
* Distracting animations

---

# 35. Responsive Design

The website must be designed mobile-first.

Mobile must not be treated as an afterthought.

The design must work across:

* Small mobile phones
* Large mobile phones
* Tablets
* Laptops
* Desktop screens

Avoid:

* Horizontal overflow
* Tiny text
* Tiny touch targets
* Broken tables
* Cropped essential information
* Overcrowded navigation
* Desktop-only interactions

---

# 36. Accessibility

The interface should:

* Use semantic HTML.
* Maintain readable contrast.
* Provide visible focus states.
* Use meaningful labels.
* Support keyboard navigation where appropriate.
* Provide useful alternative text for meaningful images.
* Avoid communicating important information through color alone.
* Provide accessible form feedback.
* Maintain usable touch targets.

Accessibility should be considered during implementation rather than added at the end.

---

# 37. Admin Interface Design

The admin interface should prioritize productivity rather than visual decoration.

The dashboard should provide useful information such as:

* Total Vehicles
* Available Vehicles
* Reserved Vehicles
* Sold Vehicles
* Active Shipments
* New Enquiries
* Recent Vehicles
* Recent Enquiries

Avoid unnecessary charts and decorative dashboard widgets.

---

# 38. Admin Forms

Admin forms may be more information-dense than public pages, but they must remain organized.

Use clear sections such as:

* Vehicle Information
* Pricing
* Shipping
* Description
* Features
* Photos

Complex forms should be divided into logical groups rather than presented as one overwhelming block.

---

# 39. Image Upload UX

Vehicle image upload should support:

* Multiple selection
* Drag and drop where appropriate
* Image previews
* Reordering
* Removal
* Optimization feedback where useful
* Clear validation errors
* Upload progress where technically appropriate

The interface should not require administrators to manually resize every vehicle image before uploading.

---

# 40. Visual Hierarchy

Every page should have a clear hierarchy:

1. Primary purpose
2. Important information
3. Primary action
4. Supporting information
5. Secondary actions

Do not give every element equal visual importance.

---

# 41. Content Hierarchy

Important information should be easier to notice.

For vehicle pages, for example:

**Vehicle identity → Price → Key specifications → Features → Shipping/Duty → Action**

The exact hierarchy may vary by specification, but information should always be organized intentionally.

---

# 42. Consistency Rule

When a component already exists, reuse it.

Do not create:

* A second button system.
* A second alert system.
* A second input style.
* A second card style.
* A second navigation style.
* A second typography system.

unless there is a documented reason.

---

# 43. Design Review Rule

Before a feature is considered visually complete, check:

* Is the layout consistent?
* Is the typography consistent?
* Are colors restrained?
* Is the content easy to scan?
* Is there unnecessary text?
* Are there unnecessary components?
* Are loading states handled?
* Are errors handled?
* Are empty states handled?
* Does it work on mobile?
* Does it follow the established design system?
* Does every element have a purpose?

---

# 44. Final Design Principle

The platform should be:

**Clean without being empty.
Premium without being flashy.
Informative without being heavy.
Modern without being trendy.
Consistent without being repetitive.
Professional without being boring.**
