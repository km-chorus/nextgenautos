# Specification 07 — Vehicle Image Management

## 1. Purpose

Implement secure, production-ready **Vehicle Image Management** for the Amponsah Autos admin application.

This specification adds the ability for authorized administrators to upload, validate, optimize, manage, reorder, replace, and delete vehicle images associated with existing vehicles.

The implementation must integrate with the existing:

* PHP application architecture
* MySQL database
* PDO database layer
* Admin authentication and authorization
* CSRF protection
* Validation system
* Audit/security logging
* Vehicle Management module
* Existing admin UI/design system

The implementation must preserve all existing functionality.

---

# 2. Important Scope Boundary

This specification is **only for Vehicle Image Management**.

Do not implement:

* Shipments
* Tracking
* Enquiries
* Brevo/email
* Team management
* Content management
* Settings
* Customer accounts
* Payments
* Public vehicle gallery redesign
* AI image generation
* AI image enhancement
* Cloud storage
* CDN integration
* Social media publishing
* Image watermarking unless already required by the existing project
* Vehicle CRUD redesign

Do not modify unrelated modules.

---

# 3. Read Before Implementation

Before writing code, inspect:

```text
CLAUDE.md
PROGRESS.md

Also inspect the existing implementation of:

* Admin authentication
* Admin authorization
* CSRF protection
* Audit logging
* Vehicle Management
* Brand Management
* Database configuration
* Existing `vehicle_images` table
* Existing upload directories
* Existing validation utilities
* Existing UI components

Do not assume the database schema or file structure is exactly as originally planned. Inspect the actual current project.

---

# 4. Existing Architecture Must Be Preserved

Use the established architecture:

```text
Browser
   ↓
Vehicle Image Controller
   ↓
Validation
   ↓
Vehicle Image Service
   ↓
Vehicle Image Repository/Model
   ↓
PDO
   ↓
MySQL
```

Image processing should remain inside a dedicated image service.

Do not put:

* SQL queries inside templates
* image processing inside views
* upload security inside JavaScript only
* business logic inside HTML
* raw filesystem operations throughout controllers

Use the existing service/repository/helper conventions.

---

# 5. Image Processing Architecture

Use the previously approved image-processing direction.

### Browser-side optimization

Use:

```text
browser-image-compression
```

where appropriate for reducing upload size before transmission.

However:

> Browser compression is a performance optimization only. It is never a security boundary.

The server must independently validate every uploaded file.

### Server-side processing

Use:

```text
Intervention Image
```

with:

```text
GD
```

as the initial image engine unless the existing environment already uses a suitable alternative.

Imagick may be supported later if necessary, but do not introduce unnecessary infrastructure during this specification.

---

# 6. Supported Image Formats

Accept only:

```text
JPEG
PNG
WebP
```

Do not allow:

```text
SVG
GIF
TIFF
BMP
ICO
PDF
ZIP
EXE
PHP
HTML
JS
or arbitrary file types
```

The extension alone must never determine whether a file is safe.

Validate the actual uploaded file content.

---

# 7. Upload Security

The server must validate every image before processing.

At minimum validate:

### MIME type

Determine the actual MIME type using server-side inspection such as:

```php
finfo_file()
```

Do not trust:

```text
$_FILES['type']
```

### Image validity

Verify that the file is a valid image.

Use appropriate image inspection methods before processing.

Reject corrupted or malformed images.

### File size

Implement a reasonable server-side maximum upload size.

Recommended initial limit:

```text
10 MB per uploaded source image
```

This is a maximum source upload size, not the target final file size.

If the existing project has a stricter approved limit, preserve it.

### Dimensions

Reject images that are:

* extremely small
* excessively large
* clearly unsuitable for vehicle photography

Recommended minimum:

```text
800 × 600
```

Do not reject legitimate portrait/landscape automotive photography solely because the aspect ratio differs.

Implement protection against decompression-bomb-style images and unreasonable pixel dimensions.

---

# 8. Secure Filename Strategy

Never use the original filename as the stored filename.

Generate secure random filenames.

Example concept:

```text
random-safe-id.webp
```

The original filename may be stored as metadata if useful, but must never become executable storage path information.

Do not allow user input to determine filesystem paths.

Prevent:

* path traversal
* directory traversal
* filename injection
* executable filenames
* duplicate filename collisions

---

# 9. Storage Location

Use the existing approved vehicle upload directory:

```text
/uploads/vehicles/
```

Inspect the current project before implementing.

The final stored files must not be executable as PHP or another server-side script.

If the hosting environment permits it, configure the upload directory so server-side execution is disabled.

The application must never serve uploaded files as executable application code.

---

# 10. Output Format

Vehicle images should be optimized and stored as:

```text
WebP
```

Recommended targets:

### Main vehicle images

```text
Maximum width: approximately 1600px
Quality: approximately 75–80%
Target size: approximately 300–700 KB
```

### Thumbnail images

```text
Maximum width: approximately 600–800px
Quality: approximately 65–75%
```

These are optimization targets, not absolute guarantees.

Do not blindly upscale small images.

Preserve aspect ratio.

Do not distort vehicle images.

---

# 11. Image Processing Pipeline

The expected pipeline is:

```text
Admin selects image(s)
        ↓
Browser-side compression where appropriate
        ↓
Preview
        ↓
Submit/upload
        ↓
CSRF validation
        ↓
Authentication/authorization check
        ↓
Server upload validation
        ↓
Actual MIME validation
        ↓
Image integrity validation
        ↓
Dimension/pixel validation
        ↓
Intervention Image processing
        ↓
Resize if necessary
        ↓
Convert to WebP
        ↓
Optimize/compress
        ↓
Generate secure filename
        ↓
Save file
        ↓
Create database record
        ↓
Audit log
        ↓
Return success
```

If database insertion fails after a file has successfully been written, clean up the orphaned file.

If image processing fails, do not create a database record.

If multiple images are uploaded and one fails, handle the operation safely and clearly.

Do not leave partially processed uploads without a deliberate cleanup strategy.

---

# 12. Vehicle Image Relationship

Every image must belong to a valid vehicle.

Use the existing relationship:

```text
vehicle_images.vehicle_id
        ↓
vehicles.id
```

Do not allow an administrator to associate an image with a nonexistent vehicle.

Do not accept arbitrary `vehicle_id` values without authorization and validation.

---

# 13. Image Database Record

Inspect the existing `vehicle_images` table.

Reuse its existing schema where possible.

Expected conceptual fields may include:

```text
id
vehicle_id
file_path / image_path
original_filename
display_order
image_type / role
alt_text
created_at
```

Do not recreate the table blindly.

Do not introduce duplicate image storage systems.

If the existing schema lacks something genuinely necessary, make the smallest justified schema change.

---

# 14. Image Roles

Support a clear image role concept if the existing schema supports it.

At minimum the system should be able to distinguish:

```text
PRIMARY
GALLERY
```

If the existing schema uses a different naming convention, preserve that convention.

Rules:

* A vehicle should have at most one primary image.
* Adding a new primary image should safely demote the previous primary image to gallery status.
* If the vehicle has no primary image, the administrator should be able to assign one.
* Do not create multiple primary images through race conditions or inconsistent updates.

Use a transaction where necessary.

---

# 15. Image Ordering

Administrators must be able to control gallery order.

Example:

```text
1 — Front
2 — Side
3 — Rear
4 — Interior
5 — Dashboard
```

Do not assume these descriptions exist automatically.

The administrator controls ordering.

Provide a secure reorder mechanism.

If drag-and-drop is used, it must have an accessible alternative.

Do not rely exclusively on drag-and-drop.

---

# 16. Vehicle Image Management UI

Integrate image management into the existing Vehicle Management UI.

Possible location:

```text
/admin/vehicles/edit.php?id=...
```

or another architecture-compatible vehicle page.

Do not create an unrelated standalone visual system.

The UI should contain:

### Existing images

Display:

* thumbnail
* role
* order
* filename or useful metadata
* upload/processing status if relevant
* actions

Actions:

```text
Set as Primary
Move/Reorder
Delete
```

Where appropriate.

---

# 17. Upload Interface

Provide a professional upload area.

It should support:

* selecting one image
* selecting multiple images
* clear supported-format information
* maximum file size information
* preview before upload
* upload progress where practical
* clear validation errors

Do not use excessive visual decoration.

Follow the existing premium automotive design system.

No emojis.

No unnecessary animations.

---

# 18. Multiple Uploads

Multiple image upload is allowed.

Each file must independently pass validation.

Do not assume that because one image is valid, all selected files are valid.

Example:

```text
5 files selected

image 1 → valid
image 2 → valid
image 3 → invalid
image 4 → valid
image 5 → invalid
```

The interface should clearly identify failed files.

Do not silently discard failures.

Do not expose sensitive filesystem information in errors.

---

# 19. Delete Image

Deleting an image must require:

* authenticated admin
* authorization
* CSRF protection
* POST request
* valid image ID
* ownership/relationship validation

Never delete images through a GET request.

Example:

```text
DELETE /admin/vehicles/image/delete?id=123
```

must NOT be implemented as a destructive GET action.

Use an appropriate POST action instead.

When deleting:

```text
Database record
        +
physical image file
```

must be handled safely.

If the physical file is missing but the DB record exists, the system should still be able to clean up the database record safely and log the condition.

---

# 20. Primary Image Deletion

If an administrator attempts to delete the current primary image:

Choose a safe behavior consistent with the existing UX.

Preferred behavior:

* warn that it is the primary image
* require deliberate confirmation
* if other gallery images exist, optionally promote another image according to an explicit deterministic rule
* otherwise allow the vehicle to temporarily have no primary image

Do not silently assign an arbitrary image without informing the administrator.

Do not create an invalid database state.

---

# 21. Reordering

Provide a secure ordering mechanism.

If using JavaScript drag-and-drop:

```text
Browser order
      ↓
POST
      ↓
CSRF validation
      ↓
Validate vehicle ownership/relationship
      ↓
Validate image IDs
      ↓
Validate complete ordering
      ↓
Database transaction
      ↓
Update display_order
```

Do not trust the submitted ordering blindly.

Reject:

* duplicate image IDs
* image IDs belonging to another vehicle
* missing images
* malformed values
* unauthorized vehicle IDs

---

# 22. Image Replacement

If replacement is implemented within this specification, it must be treated as a controlled operation.

The replacement process must:

1. Validate the new image.
2. Process the new image.
3. Store the new file safely.
4. Update the database.
5. Delete the old file only after successful replacement.
6. Roll back/clean up if an intermediate operation fails.

Never delete the old image first and then attempt processing.

That could cause accidental image loss.

---

# 23. Alt Text

Every vehicle image should support useful alt text.

Do not automatically generate meaningless values such as:

```text
image1
image2
photo123
```

If the administrator does not enter alt text, the system may generate a sensible fallback based on vehicle information, for example:

```text
2024 Toyota Camry front view
```

But avoid inventing specific details not actually known.

Alt text must be safely escaped when rendered.

---

# 24. Image URL/Path Handling

Do not expose internal filesystem paths.

For example, do not expose:

```text
C:\xampp\htdocs\...
```

or server paths.

Use application-relative/public paths.

Do not allow arbitrary path input from the browser.

---

# 25. Authorization

Only authorized administrators may manage vehicle images.

Reuse the existing admin authentication and authorization system.

Do not create a second authentication mechanism.

Every image operation must verify:

```text
authenticated admin
        +
authorized action
        +
valid vehicle/image relationship
```

Prevent IDOR vulnerabilities.

Example:

Admin A must not be able to modify:

```text
/admin/vehicles/image/delete?id=456
```

simply because they know image ID 456.

The system must verify that the image exists and belongs to the intended vehicle and that the current administrator is authorized to manage it.

---

# 26. CSRF Protection

All state-changing operations require CSRF protection.

This includes:

* upload
* delete
* set primary
* reorder
* replace
* update alt text
* update image role

GET requests must never perform state-changing operations.

---

# 27. XSS Protection

Escape all user-controlled image metadata when rendered.

This includes:

* original filename
* alt text
* vehicle information
* error messages
* uploaded metadata

Do not insert untrusted data into HTML using unsafe JavaScript methods.

---

# 28. SQL Injection Protection

All database queries must use:

```text
PDO prepared statements
```

Never concatenate request parameters into SQL.

---

# 29. Rate Limiting / Abuse Protection

Image uploads can consume significant server resources.

Apply reasonable protections against abuse.

At minimum:

* authenticated access only
* upload size limits
* file count limits per request
* server-side validation
* reasonable request throttling where existing infrastructure supports it

Do not build an unnecessarily complex rate-limiting system for this specification.

Reuse existing security infrastructure.

---

# 30. Database Transactions

Use transactions where database consistency requires them.

Especially consider transactions for:

* primary image changes
* image reordering
* replacement
* operations affecting multiple image records

Remember:

> Filesystem operations are not transactional in the same way as database operations.

Implement explicit cleanup/rollback handling for filesystem failures.

---

# 31. Audit Logging

Use the existing audit/security logging system.

Record meaningful image-management events.

Suggested events:

```text
VEHICLE_IMAGE_UPLOADED
VEHICLE_IMAGE_DELETED
VEHICLE_IMAGE_REPLACED
VEHICLE_IMAGE_PRIMARY_CHANGED
VEHICLE_IMAGE_REORDERED
VEHICLE_IMAGE_UPLOAD_REJECTED
```

Log useful metadata such as:

* admin ID
* vehicle ID
* image ID where available
* timestamp
* action/result

Do not log:

* passwords
* CSRF tokens
* secrets
* raw authentication credentials
* unnecessary sensitive file contents

---

# 32. Error Handling

User-facing errors must be clear without exposing internal details.

Examples:

```text
This image format is not supported.
```

```text
The image is too large. Maximum size is 10 MB.
```

```text
The uploaded file is not a valid image.
```

```text
The image could not be processed. Please try another image.
```

```text
We couldn't delete this image. Please try again.
```

Do not expose:

```text
SQLSTATE...
PDOException...
/var/www/...
C:\...
stack traces
```

Production users must never see raw exceptions.

Log technical details securely on the server.

---

# 33. Empty State

If a vehicle has no images:

Display a clean empty state such as:

```text
No vehicle images yet.

Upload images to create this vehicle's gallery.
```

Provide the upload action.

Do not display fake placeholder vehicle images.

---

# 34. Vehicle Without Primary Image

A vehicle may temporarily exist without a primary image.

The admin interface should clearly communicate this.

Example:

```text
Primary image not set
```

This is preferable to displaying fake imagery.

---

# 35. Performance

Optimize for shared hosting/cPanel constraints.

Avoid:

* huge original images being stored permanently
* generating excessive image variants
* unnecessary processing
* loading full-resolution images into admin thumbnails
* N+1 queries

Use thumbnails/previews where appropriate.

The admin gallery should remain usable with a reasonable number of images.

---

# 36. Public-Site Integration

Do not redesign the public website in this specification.

However, the image system must produce data that the existing/public vehicle detail page can consume later.

The system should support:

```text
primary image
gallery images
display order
alt text
```

Do not implement a separate public gallery redesign.

---

# 37. Image Count

Do not impose an unnecessarily restrictive vehicle image count unless the existing business rules require one.

However, protect the server against abuse with:

* upload size limits
* per-request file limits
* authenticated access
* reasonable validation

If a practical per-vehicle maximum is introduced, document it clearly and do not silently invent a business rule.

---

# 38. Dependencies

Before adding dependencies:

1. Inspect `composer.json`.
2. Inspect existing packages.
3. Reuse installed packages where possible.
4. Add only dependencies required by this specification.

Expected image-processing dependency:

```text
intervention/image
```

If using Intervention Image, choose a version compatible with the project's PHP version and existing Composer setup.

Do not add an image-processing framework.

For browser-side compression, use the approved `browser-image-compression` approach only if the project's asset/dependency strategy supports it cleanly.

Do not introduce npm/build tooling solely for this feature if the existing project does not use it.

---

# 39. Database Changes

Before modifying the database:

1. Inspect the actual `vehicle_images` table.
2. Confirm foreign keys.
3. Confirm indexes.
4. Confirm current columns.
5. Determine whether `display_order`, role/type, and alt text already exist.

Only make necessary changes.

Potential requirements include:

```text
vehicle_id index
display_order index where useful
```

If a primary-image role is represented in the database, enforce consistency as far as the current MySQL design reasonably allows.

Do not weaken foreign keys.

Do not remove constraints simply to make image management easier.

---

# 40. File Structure

Follow the existing project conventions.

Likely affected areas may include:

```text
app/controllers/
app/services/
app/models/
app/validation/
app/helpers/
admin/vehicles/
assets/js/
assets/css/
uploads/vehicles/
```

Do not blindly create duplicate controller/service patterns.

Inspect how Spec 05 and Spec 06 were implemented and follow the established conventions.

---

# 41. UI Requirements

The UI must follow the existing design system.

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

Do not introduce:

* purple
* neon colors
* gradients for decoration
* emoji icons
* oversized rounded cards
* unnecessary glassmorphism
* generic SaaS dashboard styling

The image itself should carry most of the visual weight.

---

# 42. Responsive Requirements

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

Mobile requirements:

* upload controls must remain usable
* image thumbnails must not overflow
* actions must remain accessible
* reorder controls must remain usable
* delete/primary actions must be easy to understand
* no horizontal page scrolling
* confirmation dialogs must fit small screens

Do not simply compress the desktop interface.

---

# 43. Accessibility

Support:

* keyboard-accessible controls
* visible focus states
* meaningful labels
* accessible buttons
* accessible confirmation dialogs
* meaningful alt text
* sufficient text contrast
* no color-only indication of state

If drag-and-drop is used, provide a keyboard/accessibility alternative.

---

# 44. Testing Requirements

Before declaring completion, test all of the following.

### Authentication

* unauthenticated upload blocked
* unauthenticated delete blocked
* unauthenticated reorder blocked
* authenticated authorized admin succeeds

### Upload

* valid JPEG succeeds
* valid PNG succeeds
* valid WebP succeeds
* unsupported file rejected
* renamed malicious file rejected
* oversized file rejected
* invalid/corrupt image rejected
* unreasonable dimensions rejected
* multiple upload works
* mixed valid/invalid uploads handled correctly

### Processing

* output is WebP
* aspect ratio preserved
* no unnecessary upscaling
* output dimensions reasonable
* optimized files are created

### Database

* image record created
* correct vehicle relationship
* display order correct
* role correct
* alt text saved
* timestamps correct

### Primary image

* one primary image only
* setting another image as primary demotes previous primary
* deleting primary image handled safely

### Reordering

* valid reorder succeeds
* invalid image IDs rejected
* cross-vehicle image IDs rejected
* duplicate IDs rejected
* unauthorized requests rejected

### Delete

* POST required
* CSRF required
* database record removed
* physical file removed
* missing physical file handled safely
* unauthorized image deletion blocked

### Security

Test:

* CSRF bypass
* IDOR
* SQL injection
* XSS
* path traversal
* executable upload attempts
* malicious MIME types
* manipulated file extensions
* oversized payloads

Do not weaken any security control simply to make testing pass.

---

# 45. Regression Testing

After implementing Spec 07, verify that these still work:

### Admin authentication

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

### Brand Management

* listing
* create
* edit
* status
* deletion rules

### Vehicle Management

* listing
* search
* filtering
* create
* edit
* status
* view
* deletion rules
* brand relationship
* pricing
* slug handling

Do not allow the new image system to break existing modules.

---

# 46. Browser Testing

Test the actual application in a browser.

Verify:

* no broken image URLs
* no broken CSS
* no broken JS
* no console errors
* upload preview works
* validation messages display correctly
* image gallery behaves correctly
* buttons work
* confirmation flows work
* mobile layout works
* admin navigation still works

Do not rely only on PHP syntax checks.

---

# 47. PHP/Code Quality

Before completion:

Run appropriate checks such as:

```text
PHP syntax checks
Composer validation where applicable
```

Check for:

* PHP warnings
* undefined variables
* undefined indexes
* unhandled exceptions
* dead code
* duplicated logic
* unsafe filesystem operations
* raw SQL
* insecure output
* missing CSRF checks

---

# 48. No Fake Data

Do not create fake:

* vehicle images
* database image records
* filenames
* vehicles
* users
* gallery records

Use real uploaded images for testing and remove temporary test data afterward where appropriate.

Do not commit personal test images into the repository unless intentionally required.

---

# 49. Security Review Before Completion

Before reporting completion, explicitly verify:

```text
Authentication
Authorization
CSRF
SQL Injection
XSS
IDOR
Path Traversal
Executable Uploads
MIME Validation
File Size Limits
Dimension Limits
Secure Filenames
Secure Storage
Session Security
Audit Logging
Error Handling
```

Image uploads are a high-risk area.

Do not treat client-side validation as security.

---

# 50. Acceptance Criteria

Spec 07 is complete only when:

* [ ] Admin can upload vehicle images.
* [ ] Multiple images can be uploaded safely.
* [ ] JPEG/PNG/WebP are supported.
* [ ] Unsupported files are rejected.
* [ ] Actual MIME type is validated server-side.
* [ ] Corrupt images are rejected.
* [ ] Oversized images are rejected.
* [ ] Excessive dimensions/pixel counts are handled safely.
* [ ] Images are converted to optimized WebP.
* [ ] Aspect ratios are preserved.
* [ ] Secure random filenames are used.
* [ ] Upload directory cannot execute uploaded scripts.
* [ ] Image records are linked to the correct vehicle.
* [ ] Admin can view vehicle images.
* [ ] Admin can set a primary image.
* [ ] Only one primary image exists per vehicle.
* [ ] Admin can reorder images.
* [ ] Admin can delete images.
* [ ] Primary-image deletion is handled safely.
* [ ] CSRF protection works.
* [ ] Authorization works.
* [ ] IDOR protection works.
* [ ] SQL injection protection works.
* [ ] XSS protection works.
* [ ] Audit events are recorded.
* [ ] Errors do not expose sensitive internals.
* [ ] Empty states work.
* [ ] Responsive layouts work.
* [ ] Existing authentication remains functional.
* [ ] Existing dashboard remains functional.
* [ ] Existing Brand Management remains functional.
* [ ] Existing Vehicle Management remains functional.
* [ ] No later module has been implemented accidentally.

---

# 51. PROGRESS.md

After all implementation and testing succeeds, update:

```text
PROGRESS.md
```

Record:

```text
Spec 07 — Vehicle Image Management: IMPLEMENTED
```

Include a concise summary of:

* upload functionality
* validation
* optimization
* gallery management
* primary image handling
* ordering
* deletion
* security
* testing

Set the next planned specification to:

```text
Spec 08 — Shipment Management
```

Do not mark Spec 08 as implemented.

---

# 52. Final Implementation Report

When finished, report:

## Implemented

List the actual functionality completed.

## Files Changed

List every created or modified file.

## Database Changes

State exactly what changed.

If nothing changed:

```text
No database schema changes were required.
```

## Dependencies

List any Composer/frontend dependencies added.

## Image Processing

Explain:

* accepted formats
* server validation
* processing library
* output format
* resize/compression behavior
* storage location

## Security

Explain:

* authentication
* authorization
* CSRF
* MIME validation
* file validation
* filename security
* executable upload prevention
* IDOR prevention
* SQL injection protection
* XSS protection
* audit logging

## Testing

Report actual tests performed and their results.

Do not claim a test was performed if it was not.

## Known Issues

List any remaining issues honestly.

## Out of Scope

Confirm that these were not implemented:

* Shipments
* Tracking
* Enquiries
* Brevo
* Team
* Content
* Settings
* Customer accounts
* Payments
* AI image processing

---

# 53. Critical AI Development Rule

Do not make assumptions about the existing project.

If you discover a conflict between this specification and the current implementation:

1. Inspect the existing code.
2. Determine whether the existing implementation already satisfies the requirement.
3. Preserve working functionality.
4. Make the smallest safe change necessary.
5. Do not silently redesign the architecture.

If a conflict materially affects:

* database structure
* security
* authorization
* image storage
* existing business rules
* data integrity

stop and report the conflict rather than guessing.

---

# 54. Stop Condition

After Spec 07 is implemented and tested:

**STOP.**

Do not begin:

```text
Spec 08 — Shipment Management
```

Do not implement tracking.

Do not implement enquiries.

Do not implement Brevo.

Do not implement Team.

Do not implement Content.

Do not implement Settings.

Wait for the next specification.

# End of Specification 07
