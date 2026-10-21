

# 1. PURPOSE

Implement the complete **Brand Management** module inside the protected admin application.

The module must allow authorized administrators to manage the vehicle brands displayed throughout the platform.

Brand Management is the first complete administrative CRUD module.

The implementation must use the existing project architecture, authentication system, database foundation, security controls, and approved admin UI.

Do not redesign the architecture.

Do not introduce a new framework.

Do not implement Vehicle Management in this specification.

---

# 2. PROJECT CONSTITUTION

Before implementation, read:

```text
CLAUDE.md
PROGRESS.md

Also read:

* the previous implementation/specifications
* the existing authentication implementation
* the existing admin UI
* the actual database schema
* existing controller/service/model/repository patterns
* existing validation and security helpers

These existing patterns are authoritative.

Do not invent a second architecture for Brand Management.

---

# 3. SCOPE

Implement:

* Brand listing
* Brand search
* Brand filtering
* Brand creation
* Brand editing
* Brand viewing/details
* Brand activation/deactivation
* Brand ordering
* Brand deletion where safe
* Brand validation
* Brand slugs
* Brand status management
* Brand audit logging
* Responsive admin UI
* Empty states
* Error states
* Success feedback
* Authorization protection
* CSRF protection
* XSS-safe output
* Prepared database queries

Also ensure the existing approved 12 brands are correctly represented in the database.

---

# 4. OUT OF SCOPE

Do NOT implement:

* Vehicle Management
* Vehicle CRUD
* Vehicle image management
* Public vehicle catalogue changes
* Vehicle filtering
* Shipment Management
* Tracking
* Enquiries
* Team Management
* Content Management
* Settings
* Brand-specific public pages if they require new public routing not already established
* Logo upload processing
* image compression
* Brevo
* email notifications
* customer accounts
* APIs
* AI functionality
* new authentication mechanisms

If a requirement appears to require another module, stop and report it rather than implementing it silently.

---

# 5. APPROVED BRANDS

The platform has exactly these initial approved brands:

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

These are the approved initial brand records.

Do not create additional brands as sample/demo data.

Do not invent descriptions or other business information for these brands unless the existing database already contains approved content.

---

# 6. USER ACCESS

Brand Management is an admin-only module.

Unauthenticated users must not be able to access Brand Management routes.

Authenticated users must be authorized according to the existing admin role/permission architecture.

Do not bypass the existing authentication guard.

Use the existing authorization mechanism.

If the current project only has a single administrator role, preserve that architecture while ensuring the code is compatible with future role-based permissions.

---

# 7. ADMIN NAVIGATION

The existing admin sidebar contains:

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

The **Brands** navigation item must now point to the real Brand Management module.

Do not change the appearance of unrelated navigation items.

If the application currently uses placeholder/coming-soon destinations, only replace the Brands destination.

---

# 8. BRAND DATA MODEL

Use the existing `brands` table created in the database foundation.

Expected conceptual fields include:

```text
id
name
slug
description
logo/image reference if already supported
display_order
status
created_at
updated_at
```

Before implementation, inspect the actual schema.

Do not blindly recreate the table.

Do not change existing column names without a documented reason.

If the actual schema differs from this specification, adapt the implementation to the existing approved schema and report the difference.

---

# 9. BRAND STATUS

Use the existing approved status convention.

If the schema supports active/inactive states, use:

```text
ACTIVE
INACTIVE
```

or the project's existing equivalent.

Do not create a second status system.

### Active

An active brand is available for normal use throughout the platform.

### Inactive

An inactive brand remains in the database but should not be treated as an available brand for future vehicle assignment or public-facing selection.

Do not physically delete a brand merely because an administrator wants to hide it.

Prefer deactivation where relationships may exist.

---

# 10. BRAND LIST PAGE

Create the real Brand Management page.

Use the existing admin shell.

Suggested route:

```text
/admin/brands/
```

or the existing architecture-compatible route.

Do not force a routing architecture change simply to match this URL.

---

## 10.1 Page Header

Display:

**Brands**

Supporting text:

A concise description explaining that administrators can manage vehicle brands available on the platform.

Primary action:

**Add Brand**

Do not use excessive introductory text.

---

# 11. BRAND LIST

Display brands in a clean administrative table on desktop.

Suggested columns:

```text
Brand
Slug
Status
Display Order
Created
Actions
```

Do not display unnecessary columns.

The exact columns may be adjusted to the existing UI conventions if usability improves.

---

# 12. BRAND LIST SORTING

Brands should be ordered primarily by:

```text
display_order ASC
```

and secondarily by:

```text
name ASC
```

unless the existing schema or approved project convention specifies another approach.

The ordering must be deterministic.

---

# 13. SEARCH

Implement brand search.

Search should allow administrators to find brands by:

* name
* slug

Search must be performed safely using prepared statements.

Do not construct SQL queries by concatenating raw user input.

The search interface should be simple and responsive.

---

# 14. FILTERING

Provide status filtering:

```text
All
Active
Inactive
```

Do not add unnecessary filters.

---

# 15. PAGINATION

Determine whether pagination is necessary based on the existing architecture.

Because the initial brand list is small, do not introduce complicated pagination unnecessarily.

If pagination is not needed, use a simple complete list.

The implementation should still avoid inefficient query patterns that would become problematic if the number of brands grows.

---

# 16. EMPTY STATES

If no brands exist:

Display a proper empty state.

Example:

> No brands have been added yet.

Provide:

**Add Brand**

Do not insert fake brands just to avoid the empty state.

---

# 17. NO SEARCH RESULTS

If a search/filter produces no results:

Display a clear message such as:

> No brands match your search.

Provide an appropriate way to clear the search/filter.

Do not show a generic server error.

---

# 18. ADD BRAND

Create a real Add Brand form.

Suggested route:

```text
/admin/brands/create
```

or the architecture-compatible equivalent.

Fields:

### Brand Name

Required.

Examples:

```text
Toyota
Mercedes-Benz
Hyundai
```

### Slug

Required or automatically generated from the brand name according to the project's slug convention.

Example:

```text
Mercedes-Benz
```

becomes:

```text
mercedes-benz
```

The final slug must be validated for uniqueness.

### Description

Optional unless the existing schema/business rules require it.

Provide a suitable textarea.

Do not generate business copy automatically.

### Display Order

Numeric value.

Must be validated.

### Status

Allow:

```text
Active
Inactive
```

---

# 19. BRAND NAME VALIDATION

Brand name must:

* not be empty
* be trimmed
* be within a reasonable maximum length based on the database column
* be unique
* not contain unsafe control characters
* be safely stored

Do not reject legitimate brand names because they contain normal punctuation.

For example:

```text
Mercedes-Benz
```

must remain valid.

---

# 20. SLUG VALIDATION

Slugs must:

* be lowercase
* use the project's established slug format
* be URL-safe
* be unique
* not contain spaces
* not contain unsafe characters

Example:

```text
Mercedes-Benz
```

→

```text
mercedes-benz
```

If the user manually edits a slug, validate it.

Do not silently overwrite a manually supplied valid slug.

---

# 21. DUPLICATE BRANDS

The system must prevent duplicate brands.

Duplicate checks must exist at both:

### Application level

Before insertion/update.

### Database level

Where the schema already provides or can safely support a unique constraint.

Do not rely only on application-level checking because concurrent requests can bypass it.

---

# 22. EDIT BRAND

Administrators must be able to edit an existing brand.

Suggested route:

```text
/admin/brands/edit?id={id}
```

or the existing architecture-compatible equivalent.

Editable fields:

* name
* slug
* description
* display order
* status

Do not allow administrators to change the primary key.

---

# 23. BRAND DETAILS / VIEW

Provide a simple brand details/view state if consistent with the existing admin architecture.

Display:

* name
* slug
* description
* status
* display order
* created date
* updated date

Do not create a complicated brand analytics dashboard.

There is currently no requirement for brand-level analytics.

---

# 24. ACTIVATE / DEACTIVATE

Administrators must be able to change brand status.

Use a state-changing POST request.

Do NOT use a GET request for state-changing operations.

Example:

```text
POST /admin/brands/toggle-status
```

The exact route should follow the project's existing routing conventions.

Every state-changing request must include CSRF protection.

---

# 25. DELETE BRAND

Brand deletion must be handled carefully because vehicles will eventually reference brands.

Before implementing deletion, inspect the current foreign-key relationship between:

```text
brands
vehicles
```

If vehicles already reference a brand, deletion must not cause orphaned records or broken relationships.

Preferred behavior:

### If the brand is referenced

Do not allow destructive deletion.

Display a clear message such as:

> This brand cannot be deleted because it is associated with vehicles. Deactivate it instead.

### If the brand has no dependent records

Deletion may be permitted if the existing project rules allow it.

Deletion must require:

* authenticated admin
* authorization
* CSRF token
* explicit confirmation
* server-side validation
* safe database operation
* audit log

Do not use browser-only confirmation as the security mechanism.

---

# 26. DATABASE RELATIONSHIP SAFETY

The Brand module must respect the future vehicle relationship.

Do not:

* set arbitrary foreign keys to null
* cascade-delete vehicles
* delete dependent records automatically
* disable foreign-key checks

Do not weaken referential integrity for convenience.

---

# 27. DISPLAY ORDER

Administrators must be able to control brand ordering.

For this specification, a simple numeric `display_order` field is sufficient.

Do not build drag-and-drop ordering unless the existing implementation already supports it or the specification is explicitly expanded.

Validate that the value is numeric and within a sensible range supported by the database.

---

# 28. SERVER-SIDE VALIDATION

Every create/update request must be validated on the server.

Do not rely on:

```text
required
maxlength
pattern
```

HTML attributes alone.

Validate:

* name
* slug
* description
* display order
* status
* record ID

according to their actual database constraints.

---

# 29. ID VALIDATION

Never trust the brand ID received from the browser.

Validate:

* correct type
* valid format
* existence
* authorization

Do not construct SQL from a raw ID.

Use prepared statements.

---

# 30. CSRF

All state-changing Brand Management operations require CSRF protection.

This includes:

* create
* update
* activate
* deactivate
* delete

Use the project's existing CSRF implementation.

Do not create a second incompatible CSRF system.

---

# 31. XSS PROTECTION

All brand content rendered into HTML must be safely escaped.

This includes:

* name
* slug
* description
* status
* any future image/alt text
* user-controlled query parameters

Do not assume database content is safe simply because it came from an administrator.

---

# 32. SQL INJECTION PROTECTION

All database queries must use PDO prepared statements.

Never do:

```php
$sql = "SELECT * FROM brands WHERE id = " . $_GET['id'];
```

Use parameterized queries.

---

# 33. ARCHITECTURE

Follow the approved architecture:

```text
Browser
   ↓
Admin Entry/Controller
   ↓
Validation
   ↓
Brand Service
   ↓
Brand Model/Repository
   ↓
PDO
   ↓
MySQL
   ↓
Response/View
```

Do not place raw SQL inside views.

Do not put business logic inside HTML templates.

Do not place large amounts of business logic directly inside entry-point files.

---

# 34. RECOMMENDED COMPONENT RESPONSIBILITIES

Use the project's existing naming conventions.

Conceptually:

### Brand Controller

Responsible for:

* receiving requests
* checking authentication/authorization
* calling validation/service logic
* preparing responses

### Brand Service

Responsible for:

* business rules
* duplicate handling
* status changes
* deletion rules
* ordering rules
* coordinating repository operations

### Brand Repository/Model

Responsible for:

* database queries
* insert
* update
* find
* list
* search
* filtering
* existence checks

### Validation

Responsible for:

* field validation
* normalization
* error messages

Do not duplicate the same validation logic unnecessarily.

---

# 35. TRANSACTIONS

Use database transactions where multiple database operations must succeed or fail together.

Simple single-row operations do not need unnecessary transactions.

Deletion/status operations must respect existing database constraints.

---

# 36. AUDIT LOGGING

Use the existing audit/security logging system.

Log important administrative actions.

Suggested events:

```text
BRAND_CREATED
BRAND_UPDATED
BRAND_ACTIVATED
BRAND_DEACTIVATED
BRAND_DELETION_ATTEMPTED
BRAND_DELETED
```

Where useful, include safe contextual information such as:

* admin user ID
* brand ID
* action
* timestamp
* result

Never log:

* passwords
* CSRF tokens
* session IDs
* database credentials
* encryption keys
* TOTP secrets
* recovery codes

---

# 37. ERROR HANDLING

Handle expected errors gracefully.

Examples:

### Duplicate name

> A brand with this name already exists.

### Duplicate slug

> This slug is already in use.

### Brand not found

> The requested brand could not be found.

### Referenced brand deletion

> This brand cannot be deleted because it is associated with vehicles. Deactivate it instead.

### Database failure

Show a safe user-facing message.

Do not expose:

* SQL statements
* database credentials
* filesystem paths
* stack traces
* internal exception details

Detailed information should remain in appropriate server/application logs.

---

# 38. SUCCESS FEEDBACK

After successful operations, provide clear feedback.

Examples:

> Brand created successfully.

> Brand updated successfully.

> Brand activated successfully.

> Brand deactivated successfully.

> Brand deleted successfully.

Use the existing alert/notification pattern.

Do not rely solely on color to communicate success or failure.

---

# 39. FORM UX

Forms should:

* have clear labels
* show validation errors near relevant fields
* preserve submitted values when validation fails
* clearly indicate required fields
* prevent accidental duplicate submissions where appropriate
* work with keyboard navigation
* have visible focus states
* use appropriate input types
* provide clear cancel/back actions

Do not over-design the forms.

---

# 40. DELETE CONFIRMATION

Deletion should require explicit confirmation.

The confirmation must clearly identify the brand.

Example:

> Delete Toyota?

Supporting message:

> This action cannot be undone.

If the brand has dependent vehicles, the server must still reject deletion regardless of what the browser does.

---

# 41. RESPONSIVE DESIGN

Brand Management must work at:

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

Desktop:

* table layout
* clear actions
* efficient use of horizontal space

Mobile:

* no horizontal overflow
* usable brand rows/cards
* actions remain accessible
* forms become one-column
* filters remain usable
* page header adapts cleanly

Do not simply shrink the desktop table until it becomes unusable.

---

# 42. UI DESIGN

Follow the existing approved admin design system.

Use:

* Deep Charcoal `#111315`
* Warm White `#F8F7F4`
* White `#FFFFFF`
* Secondary text `#62666A`
* Border `#E3E3DF`
* Primary Accent `#9E2F2F`

Typography:

```text
Manrope
```

Do not introduce purple.

Do not introduce random colors.

Do not use emojis.

Use the existing icon system.

Do not create unnecessary decorative illustrations.

---

# 43. TABLE UX

The brand table should prioritize productivity.

Actions should be clear but restrained.

Suggested actions:

```text
View
Edit
Activate/Deactivate
Delete
```

Do not display all actions as oversized buttons.

Use the project's existing action/menu pattern.

---

# 44. ACCESSIBILITY

Ensure:

* semantic headings
* labels associated with inputs
* keyboard accessibility
* visible focus
* meaningful button text
* accessible status indicators
* adequate contrast
* confirmation dialogs accessible to keyboard users
* error messages associated with fields

Do not rely solely on color for status.

---

# 45. PERFORMANCE

Use efficient queries.

Do not:

* query the database once per table row unnecessarily
* retrieve unused columns
* perform unnecessary repeated queries
* load unnecessary assets

The initial brand dataset is small, but implementation should establish good patterns for future modules.

---

# 46. SECURITY REQUIREMENTS

Brand Management must preserve all existing security controls.

Required:

* authentication
* authorization
* secure sessions
* CSRF
* server-side validation
* prepared statements
* XSS prevention
* ID validation
* rate limiting where appropriate
* audit logging
* safe error handling
* secure redirects

Do not bypass any security mechanism for convenience.

---

# 47. FIRST ADMIN / AUTHENTICATION INTEGRATION

Use the authentication system that was implemented in the previous specification.

Do not modify authentication unless a genuine integration bug is discovered.

Do not create another login mechanism.

Do not create brand-specific authentication.

---

# 48. INITIAL BRAND DATA

Verify the existing database contains the approved 12 brands.

If the database foundation already seeded them correctly:

* preserve them
* do not duplicate them
* do not change them unnecessarily

If the records are missing or incorrect, determine whether this is a database-foundation issue before making changes.

Do not create fake descriptions or logos.

---

# 49. SLUG COLLISION HANDLING

If an administrator attempts:

```text
Toyota
```

and:

```text
toyota
```

already exists, reject the duplicate.

When editing a brand, allow the brand to retain its own existing slug.

Do not incorrectly flag the current record as a duplicate of itself.

---

# 50. BRAND NOT FOUND

For invalid/nonexistent brand IDs:

* do not throw raw PHP warnings
* do not expose database errors
* return a proper not-found state
* provide a route back to Brand Management

---

# 51. UNSAFE HTTP METHODS

State-changing actions must not be performed through GET requests.

Do not implement:

```text
/admin/brands/delete?id=5
```

as the actual destructive action.

Use POST or the project's established secure method.

---

# 52. POST/REDIRECT/GET

After successful create/update/delete/status operations, use the existing application pattern for redirecting after POST where appropriate.

Avoid accidental form resubmission when the browser refreshes.

---

# 53. FILE STRUCTURE

Follow the current project architecture.

Potential files may include:

```text
app/controllers/BrandController.php
app/services/BrandService.php
app/models/Brand.php
app/validation/BrandValidator.php

admin/brands/index.php
admin/brands/create.php
admin/brands/edit.php
admin/brands/view.php

assets/css/...
assets/js/...
```

These are examples, not mandatory filenames.

Use the existing naming/routing conventions.

Do not create duplicate abstractions if equivalent project components already exist.

---

# 54. DATABASE CHANGES

Before changing the database:

1. Inspect the current schema.
2. Determine whether required constraints/indexes already exist.
3. Only make changes that are actually required.

Potentially required:

* unique brand name constraint
* unique slug constraint
* appropriate indexes

Do not modify the database unnecessarily.

Do not remove foreign keys.

If a schema change is required, document:

* exact change
* reason
* SQL
* impact
* rollback consideration

---

# 55. TESTING

Perform actual tests after implementation.

## Authentication

### Test 1

Unauthenticated user visits Brand Management.

Expected:

```text
Redirected/blocked by admin authentication.
```

### Test 2

Authenticated authorized admin visits Brand Management.

Expected:

```text
Brand Management loads successfully.
```

---

## Listing

### Test 3

All approved brands display.

### Test 4

Search by:

```text
Toyota
```

returns Toyota.

### Test 5

Search by slug:

```text
mercedes-benz
```

returns Mercedes-Benz.

### Test 6

Active/inactive filtering works.

---

## Creation

### Test 7

Create a valid new test brand only if the database/business rules permit test data.

Do not leave fake/test data in the production dataset.

### Test 8

Duplicate brand name is rejected.

### Test 9

Duplicate slug is rejected.

### Test 10

Invalid data is rejected server-side.

---

## Editing

### Test 11

Existing brand can be edited.

### Test 12

Duplicate slug/name during editing is rejected.

### Test 13

Editing preserves unrelated fields.

---

## Status

### Test 14

Active brand can be deactivated.

### Test 15

Inactive brand can be activated.

### Test 16

State-changing request without valid CSRF is rejected.

---

## Deletion

### Test 17

Unreferenced brand can only be deleted if deletion is permitted by the business rules.

### Test 18

Referenced brand cannot be deleted.

### Test 19

Delete request without valid CSRF is rejected.

### Test 20

Unauthenticated delete request is rejected.

---

## Security

### Test 21

Attempt SQL injection through search/name/slug.

Expected:

```text
No SQL injection.
```

### Test 22

Attempt HTML/JavaScript injection through brand fields.

Expected:

```text
Output safely escaped.
```

### Test 23

Tamper with a brand ID.

Expected:

```text
Invalid/nonexistent brand handled safely.
```

### Test 24

Attempt state-changing action through GET.

Expected:

```text
Rejected/not supported.
```

### Test 25

Verify audit logs are generated.

---

## Responsive

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

Verify:

* no horizontal overflow
* usable forms
* usable table/actions
* usable filters
* sidebar/navigation remains functional

---

# 56. BROWSER/CONSOLE TESTING

Verify:

* no JavaScript console errors
* no broken assets
* no broken links
* no PHP warnings/notices
* no visible SQL errors
* no accidental debug output

---

# 57. NO FAKE DATA

Do not populate:

* fake vehicles
* fake shipments
* fake enquiries
* fake team members
* fake brand descriptions
* fake analytics

Only the approved initial brand records should exist.

---

# 58. PRESERVE EXISTING FUNCTIONALITY

Before implementation, understand the existing dashboard and authentication.

After implementation, verify:

* login still works
* logout still works
* dashboard still works
* existing navigation still works
* session protection still works
* authentication/2FA is not broken
* existing database functionality still works

Do not modify unrelated modules unnecessarily.

---

# 59. PROGRESS TRACKING

After successful implementation and testing, update:

```text
PROGRESS.md
```

Mark:

```text
Spec 05 — Brand Management
```

as implemented only if the complete module has been tested.

Record:

* implementation status
* major files changed
* database changes
* security controls
* tests performed
* known issues

Set the next planned module to:

```text
Spec 06 — Vehicle Management
```

Do not claim completion if tests fail.

---

# 60. REQUIRED COMPLETION REPORT

When finished, provide:

## IMPLEMENTED

Summarize Brand Management functionality.

## FILES CHANGED

List every created/modified file.

## DATABASE CHANGES

List schema/constraint/index/seed changes.

## BRAND DATA

Confirm the 12 approved brands.

## SECURITY

Explain:

* authentication
* authorization
* CSRF
* validation
* SQL injection protection
* XSS protection
* ID validation
* audit logging
* safe errors

## TESTING

Report actual test results as:

```text
PASS
FAIL
NOT TESTED
```

Do not claim tests that were not performed.

## RESPONSIVE TESTING

List tested viewport sizes.

## KNOWN ISSUES

List any remaining issues.

## OUT OF SCOPE

Confirm that Vehicle Management and other future modules were not implemented.

## PROGRESS

Confirm whether `PROGRESS.md` was updated.

---

# 61. STOP CONDITION

After Brand Management is implemented, tested, and documented:

**STOP.**

Do not begin:

* Vehicle Management
* Vehicle Image Management
* Shipment Management
* Tracking
* Enquiries
* Brevo
* Team Management
* Content Management
* Settings

The next module will be implemented under a separate specification.

---

# 62. FINAL IMPLEMENTATION PRINCIPLE

The objective is not simply to make CRUD operations work.

Build Brand Management as the **reference implementation for future admin CRUD modules**.

Future modules should be able to follow the same established patterns for:

```text
Controller
    ↓
Validation
    ↓
Service
    ↓
Repository/Model
    ↓
PDO
    ↓
View
```

with consistent:

```text
Authentication
Authorization
CSRF
Validation
Error handling
Audit logging
Responsive UI
Testing
```

Do not sacrifice security or architecture for speed.

Do not invent requirements.

Implement only this specification.
