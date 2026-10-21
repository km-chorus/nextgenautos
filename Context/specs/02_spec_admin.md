

---

## 1. Purpose

Build the first real user interface of the Vehicle Import Platform: the administrative application shell and administrator login screen.

This specification establishes the visual language and reusable UI foundation for the entire admin application.

The result should feel like a professional internal operations platform used by a serious vehicle import and logistics company.

This is NOT a generic SaaS dashboard.

The design must reflect:

* automotive
* international logistics
* professionalism
* trust
* operational efficiency
* mature business software


# 3. Scope

### In scope

Build:

* admin login UI
* admin application shell
* desktop sidebar
* mobile navigation
* top navigation/header
* page container
* breadcrumbs
* buttons
* form controls
* alerts
* badges/status indicators
* tables foundation
* cards where appropriate
* empty states
* loading/skeleton states
* modal foundation if required
* responsive behavior
* admin design tokens
* typography
* spacing
* icons
* accessibility foundation

### Out of scope

Do NOT implement:

* real authentication
* password verification
* sessions
* login database queries
* authorization
* dashboard data
* vehicle CRUD
* brand CRUD
* shipment CRUD
* enquiry management
* team management
* content management
* settings management
* email
* Brevo
* image upload
* image processing

The login screen is UI-only in this specification.

---

# 4. Design Direction

Use:

**Premium Editorial Automotive + International Logistics**

The public website and admin panel should feel like the same company.

However, the admin interface should prioritize:

**clarity → speed → productivity → information density**

rather than marketing presentation.

The admin UI should feel similar in quality to a professionally designed operations platform.

Avoid generic dashboard aesthetics.

---

# 5. Color System

Use the approved project palette:

```text
Deep Charcoal
#111315

Warm White
#F8F7F4

White
#FFFFFF

Secondary Text
#62666A

Border
#E3E3DF

Primary Accent
#9E2F2F
```

The admin interface should primarily use:

* white
* warm white
* charcoal
* neutral gray

Use the red accent intentionally for:

* primary actions
* selected navigation
* important emphasis
* controlled status indicators

Do NOT turn the entire interface red.

Do NOT introduce purple.

Do NOT introduce random gradients.

Do NOT use generic SaaS blue/purple palettes.

---

# 6. Typography

Use:

**Manrope**

Weights:

* 400
* 500
* 600
* 700

Typography should be highly readable.

Admin interfaces should use slightly tighter typography than the public marketing website where appropriate.

Avoid oversized marketing-style headings inside admin screens.

---

# 7. Icons

Use a consistent professional icon library if one is already available or can be introduced without unnecessary dependencies.

Do not use emojis.

Do not mix multiple unrelated icon styles.

Icons should be:

* simple
* line-based where appropriate
* consistent
* functional

Every icon must have a purpose.

---

# 8. Admin Shell

Create the reusable admin shell.

Desktop structure:

```text
┌──────────────────────────────────────────────────────────────┐
│ Sidebar        │ Top Header                                  │
│                ├──────────────────────────────────────────────┤
│ Logo           │ Page title / breadcrumbs        User       │
│                │                                              │
│ Dashboard      │                                              │
│ Vehicles       │ Main Content                                 │
│ Shipments      │                                              │
│ Enquiries      │                                              │
│ Team           │                                              │
│ Brands         │                                              │
│ Content        │                                              │
│ Settings       │                                              │
│                │                                              │
│ Logout         │                                              │
└──────────────────────────────────────────────────────────────┘
```

The sidebar should remain visually stable while the main content changes.

---

# 9. Sidebar

Create navigation for:

* Dashboard
* Vehicles
* Shipments
* Enquiries
* Team
* Brands
* Content
* Settings

At the bottom:

* administrator/profile area
* logout

Each navigation item should have:

* icon
* label
* active state

The active state should be obvious without being visually aggressive.

---

# 10. Sidebar Behavior

Desktop:

* fixed/stable sidebar
* clear width
* scroll independently if needed

Tablet:

* sidebar may collapse appropriately

Mobile:

Do NOT simply squeeze the desktop sidebar into the screen.

Use a mobile navigation pattern.

Recommended:

* compact top bar
* hamburger button
* slide-over/off-canvas navigation
* clear close button
* accessible focus behavior

The mobile navigation should cover enough of the screen to be usable.

---

# 11. Top Header

Create a reusable top header containing:

* mobile menu button where appropriate
* page title
* optional breadcrumb
* administrator/profile area

Do not overload the header with unnecessary controls.

Do not create fake notifications.

Do not create fake message counts.

Do not create fake user data.

---

# 12. Admin Logo

Use the actual company logo if an approved asset exists.

If the final logo does not yet exist:

create a clean temporary text-based placeholder that can easily be replaced.

Do NOT invent a final company logo.

Do not generate fake branding.

---

# 13. Main Content Area

The main content area should have:

* consistent max width
* appropriate horizontal padding
* consistent vertical spacing
* clear page hierarchy

The interface should breathe without wasting excessive screen space.

Use the spacing scale from the project design rules.

---

# 14. Admin Login UI

Create:

```text
/admin/login.php
```

or the appropriate architecture-compatible entry/view structure.

This is a UI screen only.

The screen should include:

* company branding
* Email
* Password
* Show/hide password control
* Remember me if approved by authentication design
* Sign In button
* Forgot password link

Do not implement actual authentication.

Do not query the database.

Do not submit real credentials.

---

# 15. Login Layout

Desktop:

Use a polished split or centered layout.

A possible direction:

```text
┌───────────────────────┬─────────────────────────┐
│                       │                         │
│  Brand / Automotive   │       Sign in           │
│  visual area          │                         │
│                       │  Email                  │
│                       │  Password               │
│                       │                         │
│                       │  [ Sign In ]            │
│                       │                         │
└───────────────────────┴─────────────────────────┘
```

Do not make the login page unnecessarily decorative.

If imagery is used, use a professional automotive image placeholder only if an approved asset is available.

Do not use random stock images.

---

# 16. Password Field

The password input should include:

* show/hide functionality
* accessible button
* proper input type
* accessible label
* focus state

Do not expose passwords.

Do not store password values in localStorage.

Do not implement authentication in this specification.

---

# 17. Forms

Establish reusable form styling.

Inputs should have:

* label
* input
* helper text when necessary
* validation/error area
* focus state
* disabled state

Avoid placeholder-only labels.

Do not rely solely on color to communicate validation.

---

# 18. Buttons

Create reusable button styles:

### Primary

For major actions.

### Secondary

For supporting actions.

### Ghost/Text

For lower-priority actions.

### Destructive

For destructive actions.

Buttons should have:

* hover
* focus
* active
* disabled
* loading states where appropriate

Do not create excessive button variations.

---

# 19. Status Badges

Create a reusable status badge system.

Examples for future use:

```text
AVAILABLE
SOLD
RESERVED
NOT YET SHIPPED
IN TRANSIT
ARRIVED
DELIVERED
ACTIVE
INACTIVE
PENDING
RESOLVED
```

Do not hard-code random colors for each status.

Establish a controlled status visual system that can be reused later.

---

# 20. Tables

Create a reusable responsive table foundation.

It should support:

* headings
* rows
* actions
* sorting indicators where required later
* empty state
* loading state

On mobile, tables must not become unreadably compressed.

Use:

* horizontal scrolling
* responsive alternative layout
* or another appropriate mobile presentation

depending on the content.

---

# 21. Cards

Cards should be used selectively.

Do not create a dashboard where every piece of information is inside a rounded card.

Cards are appropriate for:

* grouped information
* summaries
* forms
* vehicle previews
* operational sections

Avoid excessive nesting.

---

# 22. Empty States

Create reusable empty states.

Example concept:

```text
No vehicles yet

Vehicles added to the inventory will appear here.

[ Add Vehicle ]
```

However, do not make the actual vehicle management functionality yet.

For UI demonstration, actions may be disabled or non-functional where appropriate.

Do not create fake records simply to fill the screen.

---

# 23. Loading States

Create reusable skeleton/loading patterns for future screens.

They should be subtle and professional.

Do not use large animated loaders everywhere.

---

# 24. Alerts

Create reusable:

* success
* error
* warning
* information

messages.

They must remain readable and accessible.

Do not depend solely on color.

---

# 25. Responsive Requirements

The admin application must work properly at:

* 320px
* 375px
* 390px
* 414px
* 768px
* 1024px
* 1280px
* 1440px+

Mobile is a first-class layout.

Do not merely scale the desktop design down.

---

# 26. Accessibility

Implement the foundation for:

* semantic HTML
* proper labels
* keyboard navigation
* visible focus states
* accessible buttons
* accessible navigation
* sufficient contrast
* meaningful ARIA labels where needed
* no icon-only controls without accessible names

Do not use accessibility as an afterthought.

---

# 27. JavaScript

Use vanilla JavaScript.

JavaScript should handle only UI behavior required by this specification, such as:

* mobile navigation
* password visibility
* dismissible alerts
* simple UI interactions

Do not introduce a JavaScript framework.

Do not create application state management.

Do not introduce unnecessary dependencies.

---

# 28. CSS Architecture

Keep the CSS maintainable.

Create reusable variables/tokens for:

* colors
* typography
* spacing
* borders
* radii
* shadows
* transitions

Avoid repeating arbitrary values throughout the stylesheet.

Do not use inline styles unless genuinely necessary.

---

# 29. Assets

Use the approved project asset structure:

```text
/assets/css/
/assets/js/
/assets/images/
```

Do not download random external assets.

Do not add unnecessary image files.

If the final logo/brand assets are unavailable, use restrained placeholders.

---

# 30. Security Boundary

Although authentication is out of scope, remember that `/admin` will eventually be protected.

Do not create a false impression that the current UI is secure simply because it looks like an admin panel.

Clearly separate:

**UI foundation**

from:

**authentication/authorization**

Authentication will be implemented in a later specification.

---

# 31. No Fake Data

Do not create fake:

* vehicles
* shipments
* customers
* enquiries
* employees
* statistics
* sales figures
* revenue
* shipment counts

If a screen needs demonstration content, use clearly identified structural placeholders rather than pretending they are real company data.

---

# 32. Testing

Test:

* desktop layout
* tablet layout
* mobile layout
* sidebar
* mobile navigation
* login UI
* password visibility
* form focus states
* buttons
* alerts
* empty states
* skeleton states
* keyboard navigation
* accessibility basics
* PHP syntax
* broken asset paths
* console errors

There should be no JavaScript console errors.

---

# 33. Acceptance Criteria

Specification 03 is complete when:

* [ ] Admin visual design system exists.
* [ ] Admin sidebar exists.
* [ ] Admin top header exists.
* [ ] Admin main content shell exists.
* [ ] Mobile navigation exists.
* [ ] Admin login UI exists.
* [ ] Password show/hide works.
* [ ] Form components are reusable.
* [ ] Button system exists.
* [ ] Status badge foundation exists.
* [ ] Table foundation exists.
* [ ] Empty states exist.
* [ ] Loading/skeleton states exist.
* [ ] Alerts exist.
* [ ] Responsive behavior works.
* [ ] Keyboard navigation works.
* [ ] No emojis are used.
* [ ] No purple is used.
* [ ] No fake business data is used.
* [ ] No authentication logic is implemented.
* [ ] No database CRUD is implemented.
* [ ] No unnecessary dependency is introduced.
* [ ] No console errors exist.

---

# 34. PROGRESS.md

After implementation and testing:

Change:

```text
03 Admin Authentication — PLANNED
```

to:

```text
03 Admin Authentication — UI FOUNDATION IMPLEMENTED
```

Do NOT mark authentication itself as implemented.

Also update the current task to indicate that the admin UI foundation has been completed and the next task is authentication implementation.

If the project's status vocabulary does not support `UI FOUNDATION IMPLEMENTED`, use `IMPLEMENTED` and explicitly state in the notes that only the UI foundation is implemented and authentication logic remains pending.

---

# 35. Completion Report

Report:

## Implemented

List the UI components implemented.

## Files Changed

List every created/modified file.

## Database Changes

State:

```text
None
```

## Authentication

State:

```text
UI only — authentication logic not implemented.
```

## Testing

List the responsive and functional tests performed.

## Dependencies

List any dependencies added.

## Known Issues

List unresolved issues.

## Out of Scope

Confirm:

* authentication
* authorization
* dashboard data
* CRUD
* database operations

were not implemented.

## Next Specification

```text
Specification 03B — Admin Authentication
```

---

# 36. Stop Rule

Stop after completing this specification.

Do not automatically build:

* authentication
* dashboard
* vehicles
* shipments
* brands
* enquiries
* team
* content
* settings

The next specification will be provided separately after review.
