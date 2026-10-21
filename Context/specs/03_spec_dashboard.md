

## 1. Purpose

Convert the existing static Admin Dashboard UI into a functional dashboard that retrieves and displays real information from the MySQL database.

The dashboard should provide administrators with a clear operational overview of:

* Vehicles
* Vehicle availability
* Enquiries
* Shipments
* Recent activity
* Quick administrative actions

The existing admin visual design must be preserved and extended rather than replaced with a new unrelated design.

This specification is **dashboard-only**.

Do not implement the full functionality of Vehicles, Shipments, Enquiries, Team, Brands, Content, or Settings in this specification.

---

# 2. Required Reading Before Implementation

Before writing or modifying code, read:

```text
CLAUDE.md
PROGRESS.md

```

Also read the existing implementation and inspect:

```text
app/
admin/
assets/
includes/
public/
```

Read the existing Admin Dashboard UI before changing it.

Do not assume that files, classes, routes, tables, or functions exist without checking.

---

# 3. Authority

Follow this hierarchy:

1. Approved business requirements
2. Business rules
3. This specification
4. Security rules
5. Architecture rules
6. Design rules
7. Technical convenience

Do not invent business requirements.

Do not silently change the architecture.

Do not introduce unrelated features.

---

# 4. Scope

## 4.1 In Scope

This specification includes:

* Functional dashboard page
* Real database statistics
* Vehicle statistics
* Enquiry statistics
* Shipment statistics
* Recent vehicles
* Recent enquiries
* Shipment overview
* Quick action navigation
* Functional sidebar navigation
* Functional dashboard links
* Database/service integration
* Empty states
* Error states
* Responsive behavior
* Dashboard loading behavior where appropriate
* Secure database querying
* Proper separation between presentation and business/data logic

---

## 4.2 Out of Scope

Do NOT implement:

* Admin authentication
* Admin authorization
* Login/logout
* Session management
* Password management
* Role permissions
* Login rate limiting
* Vehicle CRUD
* Vehicle image management
* Brand CRUD
* Shipment CRUD
* Enquiry management
* Team management
* Content management
* Settings management
* Brevo integration
* Customer accounts
* Payment processing
* Duty calculation
* Live shipment tracking
* Hapag-Lloyd API
* Charts unless an existing approved dashboard design specifically requires one
* New frontend framework
* New backend framework

Authentication and authorization will be handled by a later specification.

---

# 5. Dashboard Information Architecture

The dashboard should contain the following major areas.

```text
Admin Dashboard

├── Overview Statistics
│   ├── Total Vehicles
│   ├── Available Vehicles
│   ├── Total Shipments
│   └── New Enquiries
│
├── Recent Vehicles
│
├── Recent Enquiries
│
├── Shipment Overview
│
└── Quick Actions
```

The existing sidebar should remain visible.

---

# 6. Overview Statistics

The dashboard must retrieve statistics from MySQL.

Do not hardcode statistics.

## 6.1 Total Vehicles

Display the total number of vehicle records.

Query the database using the approved data-access architecture.

The value must change automatically when vehicles are added or removed.

---

## 6.2 Available Vehicles

Display the number of vehicles currently marked as available according to the approved vehicle availability/status values.

Do not invent a new status value.

Use the status definition established by the database/business rules.

---

## 6.3 Total Shipments

Display the number of shipment records.

If no shipments exist:

```text
0
```

is valid.

Do not display fake shipment information.

---

## 6.4 New Enquiries

Display the number of enquiries requiring attention.

Use the enquiry status values established by the database/business rules.

Do not invent a new enquiry status simply for the dashboard.

If the existing schema does not yet define a suitable "new/unread" state, inspect the schema and use the approved existing state rather than modifying the database in this specification.

---

# 7. Statistic Cards

Use the existing dashboard card design.

Each statistic card should contain:

* Clear label
* Current real value
* Optional supporting text only where useful
* Appropriate icon if already established by the design system

Do not introduce excessive visual decoration.

Do not use emojis.

Do not add random gradients.

Do not add unnecessary animations.

Do not create fake percentage changes such as:

```text
+12.5% this month
```

unless the underlying data and requirement explicitly support it.

---

# 8. Recent Vehicles

Create a dashboard section showing the most recently added vehicles.

Display a reasonable limited number of records, such as the latest 5.

Do not load the entire vehicle table just to display recent vehicles.

Suggested information:

```text
Vehicle
Year
Make / Model
Price
Availability
Action
```

The exact fields should follow the existing database schema and dashboard design.

---

## 8.1 Vehicle Image

If the vehicle has a main image available, display it.

If no image exists:

Display a clean neutral placeholder.

Do not use fake vehicle photographs.

Do not use external random image URLs.

---

## 8.2 Vehicle Action

Each vehicle row/card should have an appropriate action such as:

```text
View
```

or

```text
Manage
```

The action should navigate to the future vehicle-management interface.

Do not implement vehicle editing in this specification.

If the destination does not yet exist, use a controlled placeholder route/page rather than creating partial vehicle-management functionality.

---

# 9. Recent Enquiries

Display the latest customer enquiries.

Use a limited number of records, such as the latest 5.

Suggested information:

```text
Customer
Vehicle / Subject
Date
Status
Action
```

Use actual enquiry records.

Do not create demo customers.

Do not expose unnecessary personal information.

---

## 9.1 Enquiry Action

Provide an action such as:

```text
View
```

or

```text
Manage
```

The action should point toward the future enquiry management interface.

Do not implement full enquiry management in this specification.

---

# 10. Shipment Overview

Display a concise summary of shipment statuses.

The approved shipment statuses are:

```text
NOT YET SHIPPED
SHIPMENT BOOKED
TRACKING INFORMATION AVAILABLE
IN TRANSIT
ARRIVED
DELIVERED
```

The dashboard should count shipment records by these statuses.

Example structure:

```text
Shipment Overview

Not Yet Shipped              4
Shipment Booked              2
Tracking Information Available 1
In Transit                   5
Arrived                      2
Delivered                    8
```

The numbers must come from MySQL.

Do not invent shipment records.

Do not display fake tracking locations.

Do not display live tracking information.

---

# 11. Shipment Actions

The shipment overview may include a link:

```text
View All Shipments
```

This should point to the future shipment management page.

Do not implement shipment management yet.

---

# 12. Quick Actions

Create a useful Quick Actions section using the existing design language.

Suggested actions:

```text
Add Vehicle
Add Shipment
View Enquiries
Manage Team
```

However, only actions whose destination has been defined by the architecture should be linked.

For modules that have not yet been implemented:

* Do not build the module now.
* Do not fabricate functionality.
* Do not create fake forms.
* Either point to an appropriate planned route if already established or use a controlled "Coming Soon" state.

The Quick Actions section should help the administrator move quickly to common tasks.

---

# 13. Sidebar Navigation

The sidebar must now be genuinely clickable.

Required navigation items:

```text
Dashboard
Vehicles
Shipments
Enquiries
Team
Brands
Content
Settings
```

The Dashboard link must point to the dashboard.

For modules not yet implemented, do not create incomplete functionality simply to make the links appear functional.

Use a controlled placeholder state where necessary.

The current page should have a clear active state.

Example:

```text
Dashboard
```

should visually indicate that it is the active page when the administrator is on the dashboard.

---

# 14. Header

Preserve the existing header design.

It should support:

* Page title
* Breadcrumb if already part of the UI
* Mobile menu control
* Admin/profile area if already implemented visually

Do not add fake:

* notifications
* unread counts
* messages
* activity
* admin names

unless those values are actually available from the database/session.

Authentication is out of scope for this specification.

---

# 15. Database Access

Use the architecture defined in:

```text
docs/03-architecture-and-data.md
```

Expected flow:

```text
Dashboard Page
      ↓
Controller
      ↓
Dashboard Service
      ↓
Model/Repository
      ↓
PDO
      ↓
MySQL
```

Do not put SQL queries directly inside HTML/PHP presentation files.

Do not use:

```php
mysqli
```

Do not concatenate user input into SQL queries.

Use PDO prepared statements.

---

# 16. Dashboard Service

Create a dedicated service if the existing architecture requires it.

A suitable implementation could be:

```text
/app/services/DashboardService.php
```

The service should provide dashboard data such as:

```text
getOverviewStatistics()
getRecentVehicles()
getRecentEnquiries()
getShipmentSummary()
```

Use the existing architecture conventions rather than blindly copying these method names if equivalent structures already exist.

The service should not contain presentation markup.

---

# 17. Database Queries

Dashboard queries must be optimized for their purpose.

Do not:

* retrieve entire tables unnecessarily
* perform calculations in PHP when a simple database aggregate is more appropriate
* make one database query for every individual row
* introduce N+1 query behavior

Use appropriate:

```sql
COUNT()
ORDER BY
LIMIT
GROUP BY
WHERE
```

where appropriate.

Only retrieve fields required by the dashboard.

---

# 18. Empty States

The dashboard must handle a completely new installation gracefully.

Example:

```text
No vehicles have been added yet.
```

```text
No enquiries have been received yet.
```

```text
No shipments have been created yet.
```

Empty states should provide a useful next action where appropriate.

Example:

```text
No vehicles yet.

Add your first vehicle to start building the inventory.

[ Add Vehicle ]
```

Do not use fake records to make an empty dashboard look populated.

---

# 19. Error Handling

If a dashboard query fails:

* do not expose SQL
* do not expose database credentials
* do not expose filesystem paths
* do not expose stack traces in production
* display a clean user-facing error state
* log appropriate technical information according to the project logging strategy

Example:

```text
We couldn't load this information right now.
Please try again.
```

The failure of one non-critical dashboard section should not unnecessarily break the entire dashboard if the architecture allows graceful degradation.

---

# 20. Loading States

If the implementation uses asynchronous requests for dashboard sections, provide appropriate loading states.

If the dashboard is server-rendered, do not introduce AJAX simply for the sake of animation.

Use the simplest architecture that satisfies the requirement.

---

# 21. Responsive Requirements

The dashboard must work at:

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

## Mobile

The dashboard should:

* use the existing mobile navigation
* stack statistic cards appropriately
* avoid horizontal overflow
* make tables readable
* allow horizontal scrolling for genuinely tabular data when necessary
* maintain readable typography
* preserve clear actions
* avoid shrinking everything excessively

## Desktop

The dashboard should:

* use the existing sidebar
* use available horizontal space effectively
* maintain a clear hierarchy
* avoid excessive empty space
* avoid unnecessarily dense tables

---

# 22. Accessibility

Ensure:

* semantic HTML
* keyboard-accessible links/buttons
* visible focus states
* sufficient contrast
* meaningful button/link labels
* table headers where tables are used
* form labels where forms exist
* appropriate ARIA attributes where necessary
* icons do not carry the only meaning of an action

Do not use emojis as icons.

---

# 23. Visual Design

Follow:

```text
docs/04-design-and-ux-rules.md
```

Do not redesign the dashboard from scratch.

The established direction is:

> Premium Editorial Automotive + International Logistics

Maintain:

* Deep Charcoal
* Warm White
* White
* Secondary text
* subtle borders
* approved red accent
* Manrope typography
* restrained radius
* clean spacing
* professional iconography

Do not introduce purple.

Do not introduce random color palettes.

Do not make the dashboard resemble a generic SaaS admin template.

---

# 24. Security

Authentication and authorization are explicitly out of scope for this specification.

However, all database and output code must still follow secure coding rules.

Required:

* PDO prepared statements
* server-side validation where input exists
* output escaping
* no secrets in source code
* no credentials in JavaScript
* no raw SQL errors shown to users
* no sensitive information unnecessarily displayed

Do not weaken any existing security controls.

Do not create a bypass mechanism that will later be difficult to remove.

---

# 25. Fake Data Rule

Absolutely no fake business data.

Do not create:

```text
Toyota Camry 2024
GHS 250,000
John Doe
Container ABC123
```

just to populate the dashboard.

The dashboard must accurately represent the current database state.

If the database is empty, show empty states.

---

# 26. Files and Components

Before creating files, inspect the existing structure.

Potential files may include:

```text
app/controllers/DashboardController.php
app/services/DashboardService.php
app/models/... 
admin/index.php
assets/css/...
assets/js/...
```

Do not create duplicate architecture.

Do not create files that are not required.

Do not modify unrelated modules.

If an existing file already performs the required responsibility, extend it rather than creating a competing implementation.

---

# 27. Existing UI Preservation

The current Admin Dashboard UI has already been designed and approved.

Do not:

* replace it with a different template
* change the entire color system
* change the typography system
* remove the sidebar
* redesign the dashboard unnecessarily
* introduce a new framework
* rewrite unrelated components

Only make the changes necessary to turn the UI into a functional dashboard.

---

# 28. Testing

Before declaring the specification complete, test:

### Database

* Dashboard connects successfully to MySQL.
* Statistics are correct.
* Empty database produces valid empty states.
* Shipment counts are correct.
* Recent vehicles are correct.
* Recent enquiries are correct.

### Navigation

* Dashboard sidebar link works.
* Sidebar active state works.
* Future module links behave intentionally.
* Quick actions behave intentionally.
* No dead links are introduced without a controlled state.

### Responsive

Test at:

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

Check:

* no horizontal overflow
* sidebar/mobile navigation
* statistic cards
* recent records
* shipment summary
* buttons
* tables

### Security

Verify:

* prepared statements
* escaped output
* no secrets exposed
* no raw database errors
* no credentials in frontend code

### Browser

Verify:

* no console errors
* no broken asset requests
* no broken images
* no PHP warnings/notices displayed to users
* no broken navigation

---

# 29. Manual Test Data

If testing requires database records, do not permanently seed fake production/business data.

You may create temporary local test records strictly for testing.

Clearly identify them as test data and remove them before considering the specification complete.

The approved 12 brands from the database foundation are legitimate seed data and should not be treated as fake vehicle inventory.

---

# 30. Acceptance Criteria

Specification 04 is complete only when:

* [ ] Dashboard loads successfully.
* [ ] Dashboard reads real MySQL data.
* [ ] Total vehicle count is accurate.
* [ ] Available vehicle count is accurate.
* [ ] Shipment count is accurate.
* [ ] New enquiry count is accurate.
* [ ] Recent vehicles are real database records.
* [ ] Recent enquiries are real database records.
* [ ] Shipment summary uses real shipment records.
* [ ] Empty states work correctly.
* [ ] Dashboard does not contain fabricated business data.
* [ ] Sidebar navigation is clickable.
* [ ] Dashboard active navigation state works.
* [ ] Quick actions behave correctly.
* [ ] Future modules are not partially implemented.
* [ ] Database access follows the approved architecture.
* [ ] PDO prepared statements are used.
* [ ] No raw database errors are exposed.
* [ ] Existing visual design is preserved.
* [ ] Mobile layout works.
* [ ] Desktop layout works.
* [ ] No horizontal overflow at required breakpoints.
* [ ] No browser console errors.
* [ ] No unnecessary dependencies were introduced.
* [ ] No unrelated functionality was modified.

---

# 31. PROGRESS.md Update

Only after implementation and testing pass, update:

```text
Spec 04 — Functional Admin Dashboard
Status: IMPLEMENTED
```

Record:

* implementation date
* files changed
* database changes, if any
* tests performed
* known issues, if any

Then set the next task to:

```text
Spec 03B — Admin Authentication
```

Do not mark Spec 04 as completed simply because the page loads.

---

# 32. Stop Condition

STOP after completing this specification.

Do not automatically continue to:

* Admin Authentication
* Brand Management
* Vehicle Management
* Shipment Management
* Enquiries
* Team
* Content
* Settings

The next specification will be handled separately.

---

# 33. Required Completion Report

After implementation, report:

```text
IMPLEMENTED
- ...

FILES CHANGED
- ...

DATABASE CHANGES
- ...

NAVIGATION
- ...

SECURITY
- ...

TESTING
- ...

RESPONSIVE TESTING
- ...

KNOWN ISSUES
- ...

OUT OF SCOPE
- ...

PROGRESS UPDATED
- Yes/No
```

Do not claim anything was tested if it was not actually tested.
Do not claim functionality was implemented if it was only mocked.
