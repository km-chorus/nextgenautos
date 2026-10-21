# Specification 08 — Team Management

## 1. Purpose

Implement the complete **Team Management module** for the Amponsah Autos administrative application.

The module will allow authorized administrators to manage the people presented on the company's public-facing website.

The company wants to present:

* CEOs
* Investors/Partners
* Managers
* Sales Team
* Other Employees

in a professional and trustworthy way.

Each team member should be able to have:

* Professional photograph
* Full name
* Position/title
* Short professional introduction
* Team category/role
* Display order
* Active/inactive status

The implementation must integrate with the existing:

* PHP application architecture
* MySQL database
* PDO
* Admin authentication
* Admin authorization
* CSRF protection
* Validation
* Audit logging
* Vehicle Image Management patterns
* Existing admin UI/design system

Do not modify unrelated modules.

---

# 2. Important Scope Boundary

This specification covers **Team Management only**.

Implement:

* Team listing
* Search
* Filtering
* Add team member
* Edit team member
* View team member
* Activate/deactivate
* Reorder
* Delete
* Team photo management
* Validation
* Audit logging
* Responsive admin interface
* Empty states
* Error handling
* Security

Do NOT implement:

* Shipments
* Tracking
* Enquiries
* Brevo
* Content Management
* Settings
* Customer accounts
* Payments
* CRM
* Employee payroll
* Employee attendance
* HR management
* Internal employee accounts
* Employee authentication
* AI biographies
* Public website redesign

---

# 3. Read Before Implementation

Before writing code, read:

```text
CLAUDE.md
PROGRESS.md


Also inspect:

* existing Team/`team_members` database table
* Admin Authentication
* Authorization
* Brand Management
* Vehicle Management
* Vehicle Image Management
* Image Service
* Validation patterns
* Audit logging
* Existing admin UI
* Existing form components
* Existing table/list components
* Existing upload/image handling

Do not assume the original schema is unchanged.

Inspect the actual implementation first.

---

# 4. Architecture

Follow the existing architecture:

```text
Browser
   ↓
Team Controller
   ↓
Validation
   ↓
Team Service
   ↓
Team Repository/Model
   ↓
PDO
   ↓
MySQL
```

For team photos:

```text
Browser
   ↓
Team Controller
   ↓
Image Validation
   ↓
Image Service
   ↓
Filesystem
   ↓
Team Repository
   ↓
MySQL
```

Do not place SQL inside templates.

Do not place business logic inside views.

Do not create a second authentication system.

---

# 5. Existing Database

Inspect the current:

```text
team_members
```

table.

The expected conceptual data is:

```text
id
name
position
category/role
bio
image/photo reference
display_order
status
created_at
updated_at
```

However:

> Use the actual current schema.

Do not blindly recreate the table.

If changes are required, make only the smallest necessary schema changes.

---

# 6. Team Member Fields

The admin form should support the following information.

## Full Name

Required.

Examples:

```text
John Mensah
Ama Boateng
Michael Antwi
```

Do not impose unnecessary formatting rules.

Allow normal names containing:

* spaces
* hyphens
* apostrophes
* appropriate international characters

Maximum length should be reasonable and match the database.

---

# 7. Position / Job Title

Required.

Examples:

```text
Chief Executive Officer
Managing Director
Sales Manager
Operations Manager
Sales Consultant
Director
Partner
```

Do not hardcode these options unless the existing business rules require it.

Allow the administrator to enter the appropriate title.

---

# 8. Team Category

Provide a controlled category where appropriate.

Suggested categories:

```text
CEO / Leadership
Investor / Partner
Management
Sales
Operations
Other
```

Inspect the existing database structure first.

If categories already exist, preserve the existing convention.

Do not create unnecessary category-management CRUD in this specification.

A category should help organize the public team presentation.

---

# 9. Biography / Introduction

Provide a short professional introduction.

Example:

```text
John leads the company's vehicle sourcing and customer operations,
with a focus on connecting customers in Ghana with quality vehicles
sourced from Canada.
```

This is not a social media biography.

Keep it professional and concise.

The system should support multiline text.

Do not require HTML.

Plain text is preferred unless the existing content architecture explicitly supports rich text.

---

# 10. Professional Photo

Each team member may have a professional photo.

The photo should use the existing secure image-management approach from Spec 07.

Do NOT create a separate image-processing architecture.

Reuse the existing image service where practical.

Supported formats should remain:

```text
JPEG
PNG
WebP
```

The server must validate the actual file.

Do not trust file extensions or browser-provided MIME types.

---

# 11. Team Photo Storage

Use the approved team upload directory:

```text
/uploads/team/
```

Inspect the existing project before implementing.

Use secure randomly generated filenames.

Never use the user's original filename as the final filesystem filename.

Do not allow:

```text
../
..\ 
absolute paths
PHP filenames
HTML files
JavaScript files
SVG files
```

or other executable content.

---

# 12. Team Photo Processing

Reuse the existing image-processing infrastructure.

The preferred pipeline remains:

```text
Admin selects photo
        ↓
Browser preview/compression where appropriate
        ↓
Server receives upload
        ↓
Authentication/authorization
        ↓
CSRF
        ↓
MIME validation
        ↓
Image validation
        ↓
Dimension validation
        ↓
Image processing
        ↓
WebP conversion
        ↓
Secure filename
        ↓
Store file
        ↓
Database reference
        ↓
Audit log
```

Do not duplicate image-processing code unnecessarily.

---

# 13. Photo Dimensions

Team photos are primarily professional portrait images.

Use reasonable image-processing dimensions.

Recommended:

```text
Maximum width: approximately 1200px
Maximum height: approximately 1600px
```

Preserve the original aspect ratio.

Do not stretch faces or distort images.

Do not unnecessarily upscale small images.

Do not crop automatically unless the existing image service requires a controlled crop.

---

# 14. Photo Quality

Use WebP output where supported by the existing image service.

Recommended quality:

```text
75–80%
```

This is a target rather than a guarantee.

The objective is:

```text
Professional appearance
+
reasonable file size
+
fast website loading
```

Do not sacrifice image quality excessively.

---

# 15. Add Team Member

Provide a page such as:

```text
/admin/team/create.php
```

or the architecture-compatible equivalent.

The form should include:

```text
Photo
Full Name
Position
Category
Bio
Display Order
Status
```

The exact route should follow existing admin conventions.

---

# 16. Edit Team Member

Administrators should be able to edit:

* photo
* name
* position
* category
* biography
* display order
* status

Existing information should be pre-populated.

If the administrator does not upload a new photo, preserve the existing photo.

Do not delete the existing photo merely because the edit form was submitted without a new upload.

---

# 17. Replace Photo

When replacing a team member's photo:

1. Validate the new image.
2. Process the new image.
3. Save the new image.
4. Update the database reference.
5. Confirm successful database update.
6. Delete the old image after successful replacement.

Do not delete the old photo first.

If processing fails, the old photo must remain intact.

If the database update fails, clean up the newly created file.

---

# 18. Remove Photo

Allow an administrator to remove a team photo if appropriate.

The operation must:

* require authentication
* require authorization
* require CSRF
* use POST
* validate the team member
* remove the database reference
* safely remove the physical file

Do not delete photos through GET requests.

After removal, the team member may remain without a photo.

Do not substitute a fake image.

---

# 19. Team Listing

Create the main Team Management page:

```text
/admin/team/
```

The listing should display useful information such as:

| Member | Position | Category | Status | Order | Actions |
| ------ | -------- | -------- | ------ | ----- | ------- |

Include a small professional thumbnail.

Actions may include:

```text
View
Edit
Activate/Deactivate
Delete
```

Do not make the table unnecessarily crowded.

---

# 20. Search

Allow administrators to search team members.

Search by:

* name
* position

Search should be handled safely using prepared statements.

Do not construct SQL using raw search strings.

---

# 21. Filtering

Provide useful filters such as:

```text
Category
Status
```

Possible status values:

```text
ACTIVE
INACTIVE
```

Use the existing status convention if the database already defines one.

---

# 22. Sorting

Default ordering should prioritize:

```text
display_order ASC
```

Then use a deterministic secondary ordering such as:

```text
name ASC
```

or another existing project convention.

Do not use random ordering.

---

# 23. Display Order

Administrators must be able to control how team members appear publicly.

Example:

```text
1 — CEO
2 — Managing Director
3 — Operations Manager
4 — Sales Manager
5 — Sales Consultant
```

The actual ordering is controlled by the administrator.

Provide a simple mechanism for changing order.

Possible approaches:

* editable display-order field
* drag-and-drop with accessible fallback
* up/down controls

Prefer consistency with existing project patterns.

---

# 24. Reordering Security

If JavaScript drag-and-drop is used:

```text
Browser order
↓
POST
↓
CSRF validation
↓
Authentication
↓
Authorization
↓
Validate IDs
↓
Validate submitted ordering
↓
Database transaction
↓
Update display_order
```

Do not trust the submitted order blindly.

Reject:

* duplicate IDs
* nonexistent IDs
* malformed IDs
* team members outside the expected set
* unauthorized updates

---

# 25. Active / Inactive Status

Team members should support:

```text
ACTIVE
INACTIVE
```

Inactive members should normally not appear on the public website.

However:

> Do not implement the public website in this specification.

The admin system should simply maintain the correct state for future public consumption.

Deactivation should not delete the team member.

---

# 26. Delete Team Member

Provide safe deletion.

Before deletion:

* authenticate
* authorize
* validate ID
* require CSRF
* use POST
* record audit event
* remove associated image safely

If there is any future relationship that prevents deletion, respect the database constraint.

Do not use cascading deletion to hide data-integrity problems.

If there is no reason to permanently delete a member, deactivation should be preferred.

---

# 27. Delete Confirmation

Deleting a team member should require deliberate confirmation.

The confirmation should identify the member clearly.

Example:

```text
Delete John Mensah?

This will permanently remove this team member from the admin system.
```

Do not use browser-only confirmation as the sole security control.

The server must still validate the request.

---

# 28. Authorization

Only authorized administrators may manage team members.

Reuse the existing authorization system.

Do not create team-specific authentication.

Every state-changing operation must verify:

```text
Authenticated admin
+
Authorized action
+
Valid team member
```

---

# 29. CSRF

All state-changing actions require CSRF protection.

This includes:

* create
* update
* delete
* activate
* deactivate
* reorder
* photo upload
* photo replacement
* photo removal

Never perform these through GET.

---

# 30. Validation

Server-side validation is mandatory.

Validate:

### Name

* required
* reasonable length
* safe input

### Position

* required
* reasonable length

### Category

* required if category is mandatory in the schema
* must be one of the allowed categories

### Bio

* reasonable maximum length
* plain text unless existing architecture requires otherwise

### Display order

* integer
* reasonable value
* no invalid negative/garbage values

### Status

* valid allowed status

### Photo

Use the existing secure image validation.

---

# 31. XSS Protection

Escape all team-member content when displayed.

This includes:

* name
* position
* category
* biography
* filenames
* error messages

Do not render biography as raw HTML unless explicitly supported by the existing content architecture.

---

# 32. SQL Injection

All database operations must use PDO prepared statements.

Never concatenate:

* IDs
* search terms
* filters
* names
* categories
* status values

directly into SQL.

---

# 33. IDOR Protection

Test requests such as:

```text
/admin/team/edit.php?id=123
```

and:

```text
/admin/team/delete.php?id=123
```

against unauthorized or nonexistent IDs.

The server must validate the resource.

Do not assume that because an administrator knows an ID, they are automatically authorized to modify it.

---

# 34. Audit Logging

Use the existing audit/security logging system.

Recommended events:

```text
TEAM_MEMBER_CREATED
TEAM_MEMBER_UPDATED
TEAM_MEMBER_DELETED
TEAM_MEMBER_ACTIVATED
TEAM_MEMBER_DEACTIVATED
TEAM_MEMBER_REORDERED
TEAM_MEMBER_PHOTO_UPLOADED
TEAM_MEMBER_PHOTO_REPLACED
TEAM_MEMBER_PHOTO_REMOVED
```

Include useful metadata:

* admin ID
* team member ID
* action
* timestamp
* result where appropriate

Never log:

* passwords
* session secrets
* CSRF tokens
* authentication secrets

---

# 35. Error Handling

User-facing errors must be clear.

Examples:

```text
We couldn't save this team member. Please check the highlighted fields.
```

```text
The selected photo is not supported.
```

```text
The photo could not be processed.
```

```text
We couldn't delete this team member. Please try again.
```

Do not expose:

```text
SQLSTATE
PDOException
stack traces
filesystem paths
server configuration
database credentials
```

Technical details should go into secure logs.

---

# 36. Empty State

If no team members exist:

```text
No team members yet.

Add your first team member to begin building the company profile.
```

Provide:

```text
Add Team Member
```

Do not create fake team members.

---

# 37. No Fake Content

Do not seed:

* fake CEOs
* fake employees
* fake biographies
* fake photos
* fake positions

The team information must come from the administrator.

---

# 38. Admin UI

Follow the existing admin design system.

Use the approved palette:

```text
Deep Charcoal  #111315
Warm White     #F8F7F4
White          #FFFFFF
Secondary      #62666A
Border         #E3E3DF
Accent         #9E2F2F
```

Typography:

```text
Manrope
```

Do not introduce:

* purple
* neon colors
* unnecessary gradients
* emoji icons
* excessive rounded cards
* generic SaaS styling
* excessive animation

The interface should feel like a professional automotive company administration platform.

---

# 39. Team Member Form Layout

The form should be organized logically.

Suggested structure:

```text
Team Member
────────────────────────────

Professional Photo

[ Upload Photo ]

Basic Information

Full Name
Position
Category

Professional Introduction

Biography

Publishing

Display Order
Status

[Save]
[Cancel]
```

Do not make the form unnecessarily complicated.

---

# 40. Photo Preview

When selecting a new photo:

* show a preview
* allow the administrator to understand which file was selected
* display validation errors clearly

The preview must not be treated as proof of file safety.

The server must independently validate the actual upload.

---

# 41. Existing Photo

When editing a team member with an existing photo:

Display:

* current photo
* replace option
* remove option where appropriate

Do not force the administrator to upload a new photo every time they edit text.

---

# 42. Responsive Requirements

Test:

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

The team interface must remain usable on mobile.

On small screens:

* table may transform into a more appropriate list/card representation
* actions must remain accessible
* forms should become one column
* photo preview should not overflow
* buttons should remain usable
* no horizontal page scrolling

Do not simply squeeze the desktop table onto a 320px screen.

---

# 43. Accessibility

Support:

* keyboard navigation
* visible focus states
* accessible labels
* meaningful button names
* accessible confirmation dialogs
* alt text for team photos
* sufficient contrast
* status not communicated through color alone

If drag-and-drop is implemented, provide an accessible alternative.

---

# 44. Public Website Compatibility

Do not redesign or implement the public Team page in this specification.

However, structure the team data so the future public page can easily retrieve:

```text
id
name
position
category
bio
photo
display_order
status
```

The future public page should be able to query active team members in display order.

Do not hardcode team members into HTML.

---

# 45. Performance

Avoid unnecessary queries.

The listing should not perform an N+1 query pattern.

Team photos should use optimized images.

Do not load full-resolution images into the listing when thumbnails are sufficient.

---

# 46. Database Changes

Inspect the current `team_members` schema first.

Only make schema changes if genuinely required.

Potential indexes:

```text
status
category
display_order
```

Use only those that are useful for actual queries.

Do not introduce unnecessary database complexity.

Do not remove existing foreign keys or constraints.

---

# 47. Dependencies

Before adding anything:

Inspect:

```text
composer.json
composer.lock
```

Reuse the existing image-processing dependency from Spec 07.

Do not install another image-processing package.

Do not introduce an unnecessary frontend framework.

Do not add React, Vue, Angular, jQuery, or another framework.

Use the existing:

```text
PHP
HTML
CSS
Vanilla JavaScript
```

architecture.

---

# 48. Testing

Test:

## Create

* valid member
* missing name
* missing position
* invalid category
* invalid status
* invalid display order
* valid photo
* invalid photo

## Edit

* change name
* change position
* change category
* change biography
* change order
* change status
* replace photo
* edit without changing photo

## Status

* activate
* deactivate
* inactive member remains stored

## Delete

* valid delete
* unauthorized delete
* missing ID
* invalid ID
* GET deletion attempt rejected
* CSRF failure rejected
* associated image handled correctly

## Reordering

* valid reorder
* invalid IDs
* duplicate IDs
* unauthorized reorder
* CSRF failure

## Search

* name search
* position search
* empty search
* special characters
* SQL injection attempts

## Security

Test:

* CSRF
* XSS
* SQL injection
* IDOR
* path traversal
* malicious upload
* executable upload
* oversized upload
* invalid MIME type

---

# 49. Browser Testing

Test the actual admin interface.

Verify:

* no broken assets
* no console errors
* forms work
* photo preview works
* upload works
* edit works
* deletion confirmation works
* status changes work
* search works
* filters work
* ordering works
* responsive layout works

Do not rely only on PHP syntax checks.

---

# 50. Regression Testing

After implementing Team Management, verify that the following still work:

### Authentication

* login
* logout
* 2FA
* password reset
* session timeout

### Dashboard

* statistics
* recent vehicles
* recent enquiries
* shipment summary
* navigation

### Brands

* listing
* create
* edit
* status
* deletion rules

### Vehicles

* listing
* search
* filters
* create
* edit
* status
* view
* deletion rules
* brand relationship
* pricing
* slug

### Vehicle Images

* upload
* validation
* primary image
* reorder
* delete
* image processing

Do not allow Team Management to break existing functionality.

---

# 51. Security Review

Before completion explicitly verify:

```text
Authentication
Authorization
CSRF
SQL Injection
XSS
IDOR
Path Traversal
Executable Upload Prevention
MIME Validation
File Size Limits
Secure Filenames
Secure Image Storage
Audit Logging
Safe Error Handling
```

Do not disable an existing security control to make a feature work.

---

# 52. PROGRESS.md

After successful implementation and testing, update:

```text
PROGRESS.md
```

Record:

```text
Spec 08 — Team Management: IMPLEMENTED
```

Include:

* team CRUD
* team photo management
* ordering
* status
* search/filter
* security
* testing

The next planned specification should be:

```text
Spec 09 — Enquiries
```

Do NOT mark Spec 09 as implemented.

Also explicitly note that:

```text
Shipment Management
Tracking
```

are **deferred pending clarification of the company's actual shipping/tracking workflow**.

Do not remove them from the broader project roadmap.

---

# 53. Final Implementation Report

When finished, report:

## Implemented

List actual functionality completed.

## Files Changed

List every created or modified file.

## Database Changes

State exactly what changed.

## Image Handling

Explain:

* supported formats
* validation
* processing
* WebP conversion
* storage location
* replacement behavior
* deletion behavior

## Security

Explain:

* authentication
* authorization
* CSRF
* MIME validation
* upload security
* IDOR prevention
* SQL injection prevention
* XSS protection
* audit logging

## Testing

Report actual tests performed and their results.

Do not claim tests that were not performed.

## Known Issues

List any remaining issues.

## Out of Scope

Confirm that the following were NOT implemented:

* Shipments
* Tracking
* Enquiries
* Brevo
* Content
* Settings
* Customer accounts
* Payments
* HR/payroll functionality
* AI functionality

---

# 54. Critical AI Development Rule

Do not invent team information.

Do not invent:

* employees
* positions
* biographies
* photos
* categories
* business relationships

Do not modify the existing architecture without justification.

If an ambiguity affects:

* database structure
* permissions
* security
* image storage
* public visibility
* deletion behavior

inspect the existing implementation and make the smallest safe decision.

If the decision would materially affect the business rules, stop and report the ambiguity instead of guessing.

---

# 55. Stop Condition

After Spec 08 is fully implemented and tested:

**STOP.**

Do not implement:

* Enquiries
* Brevo
* Content
* Settings
* Shipments
* Tracking

Wait for the next specification.

# End of Specification 08
