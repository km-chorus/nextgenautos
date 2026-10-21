# Task: Create the Project Architecture

You are working on the Vehicle Import Platform.

The project folder is currently empty.

Your task is to create the **initial project folder and file structure defined by the approved architecture**.

## IMPORTANT

Do NOT build application features yet.

Do NOT create the vehicle management system.

Do NOT create authentication functionality.

Do NOT create the dashboard.

Do NOT create the public website pages.

Do NOT create the database schema yet.

Do NOT invent additional folders or technologies.

At this stage, we are only establishing the project's architectural foundation.

---

# 1. READ THE PROJECT CONTEXT FIRST

Before creating anything, read:

```text
CLAUDE.md

PROGRESS.md

docs/01-project-context.md
docs/02-scope-and-business-rules.md
docs/03-architecture-and-data.md
docs/04-design-and-ux-rules.md
docs/05-security-and-integrations.md
docs/06-ai-development-and-testing.md
```

The architecture defined in:

```text
docs/03-architecture-and-data.md
```

is authoritative for the initial project structure.

---

# 2. APPROVED ARCHITECTURE

The project uses:

> Modular MVC-inspired PHP monolith with MySQL, PDO, server-rendered public pages, a protected administrative application, service-layer business logic, repository/model-based data access, dedicated services for security, validation, media processing and external integrations.

Technology direction:

```text
PHP 8.x
MySQL
PDO
HTML5
CSS3
Vanilla JavaScript
Bootstrap 5 or approved custom CSS
Git/GitHub
cPanel/shared hosting
Brevo
```

Do not introduce another framework or backend architecture.

---

# 3. CREATE THIS STRUCTURE

Create the following directories:

```text
/app
/app/config
/app/controllers
/app/models
/app/services
/app/helpers
/app/validation

/public

/admin
/admin/vehicles
/admin/shipments
/admin/enquiries
/admin/team
/admin/brands
/admin/content
/admin/settings

/assets
/assets/css
/assets/js
/assets/images

/uploads
/uploads/vehicles
/uploads/team

/includes

/docs
/specs
```

The resulting structure should be:

```text
vehicle-import/
│
├── CLAUDE.md
├── PROGRESS.md
│
├── app/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   ├── helpers/
│   └── validation/
│
├── public/
│
├── admin/
│   ├── vehicles/
│   ├── shipments/
│   ├── enquiries/
│   ├── team/
│   ├── brands/
│   ├── content/
│   └── settings/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── uploads/
│   ├── vehicles/
│   └── team/
│
├── includes/
│
├── docs/
│
└── specs/
```

---

# 4. IMPORTANT: DO NOT MOVE EXISTING FILES

If files already exist in the project:

* do not delete them
* do not overwrite them
* do not move them unnecessarily

Inspect them first.

If the project is genuinely empty, create the structure directly.

---

# 5. INITIAL FILES

Create only the minimum architectural placeholder files necessary.

Recommended:

```text
/app/config/database.php
/app/config/app.php

/app/services/EmailService.php
/app/services/ImageService.php

/includes/header.php
/includes/footer.php
/includes/navigation.php
```

However, these files should initially contain only appropriate placeholders/documentation.

Do NOT implement the services yet.

For example, do not connect to MySQL yet.

Do not connect to Brevo yet.

Do not implement image processing yet.

Do not implement authentication yet.

The purpose is to establish the architectural locations.

---

# 6. PUBLIC ENTRY POINT

Create:

```text
/public/index.php
```

It should be a minimal application entry point only.

Do not build the homepage.

Do not add the vehicle catalogue.

Do not add business logic.

Do not add fake content.

---

# 7. ADMIN ENTRY POINT

Create:

```text
/admin/index.php
```

This is only the architectural entry point.

Do not build the admin dashboard yet.

Do not implement authentication yet.

Do not add fake statistics.

Do not create vehicle management functionality.

---

# 8. CONFIGURATION

Create the configuration locations, but do not place real production credentials inside them.

Do not hard-code:

* database passwords
* Brevo credentials
* API keys
* production secrets

Use placeholders/configuration mechanisms appropriate for the eventual hosting environment.

Do not create fake credentials.

---

# 9. UPLOAD DIRECTORIES

Create:

```text
/uploads/vehicles
/uploads/team
```

These directories will eventually contain processed image files.

Do not upload or generate fake vehicle/team images.

Do not implement upload functionality yet.

If the hosting environment requires appropriate protection files for upload directories, create only those that are necessary and explain why.

---

# 10. GIT FOUNDATION

Create an appropriate `.gitignore` for the project.

It must prevent accidental commits of sensitive or generated content.

At minimum consider:

```text
.env
.env.*
vendor/
node_modules/
uploads/*
logs/
cache/
```

However, do not blindly copy a generic `.gitignore`.

Keep it appropriate for this PHP project.

Important:

The project must NOT contain production credentials.

---

# 11. DO NOT CREATE THESE YET

Do not create implementation files for:

* authentication
* registration
* dashboard
* vehicle CRUD
* shipment CRUD
* enquiry processing
* team management
* brand management
* CMS
* database schema
* email sending
* image compression
* duty calculation
* carrier tracking
* payment processing

Those will be implemented through future specifications.

---

# 12. NO DATABASE IMPLEMENTATION YET

Do not create the production database schema during this task.

Do not create:

* `vehicles` table
* `brands` table
* `shipments` table
* `users` table
* `enquiries` table
* `team_members` table

The database schema will be implemented through its own specification.

You may create the database configuration location only.

---

# 13. NO FRONTEND DESIGN YET

Do not design the homepage.

Do not create:

* hero sections
* vehicle cards
* navigation styling
* footer design
* dashboards
* forms
* animations

The visual implementation will happen through later specifications.

---

# 14. NO FAKE CONTENT

Do not populate the application with invented:

* vehicles
* prices
* company information
* team members
* customers
* shipment information
* container numbers

The architecture stage must remain clean.

---

# 15. DIRECTORY RESPONSIBILITIES

Respect these responsibilities.

### `/app/config`

Application configuration and environment-specific configuration.

### `/app/controllers`

Request handling and coordination.

### `/app/models`

Data/domain models and approved data-access abstractions.

### `/app/services`

Business/application services and external integration services.

Examples:

```text
EmailService
ImageService
VehicleService
ShipmentService
```

Only create services when their specification is implemented.

### `/app/helpers`

Small reusable helper functions.

Do not turn this into a dumping ground for business logic.

### `/app/validation`

Reusable validation logic.

### `/public`

Public-facing application entry points/pages.

### `/admin`

Protected administrative application.

### `/assets/css`

Stylesheets.

### `/assets/js`

Frontend JavaScript.

### `/assets/images`

Static project imagery and assets.

### `/uploads`

User/admin-uploaded processed media.

### `/includes`

Shared presentation components where appropriate.

### `/docs`

Permanent project context.

### `/specs`

Feature specifications.

---

# 16. ARCHITECTURAL RULE

The project should eventually follow:

```text
Browser
   ↓
Public/Admin Entry Point
   ↓
Controller
   ↓
Validation
   ↓
Service
   ↓
Model/Repository
   ↓
MySQL
   ↓
Response/View
```

Do not bypass this architecture.

Do not place database queries directly inside views.

Do not place business logic directly inside HTML.

Do not put external API calls directly into random page files.

---

# 17. FILE CONTENT RULE

Do not fill placeholder files with unnecessary code.

A placeholder should clearly indicate its intended responsibility.

Example:

```php
<?php

declare(strict_types=1);

/**
 * Database configuration.
 *
 * Database connection implementation will be added
 * as part of the database foundation specification.
 */
```

Use this principle throughout the initial structure.

---

# 18. VERIFY THE RESULT

After creating the structure:

1. Verify every required directory exists.
2. Verify the required placeholder files exist.
3. Verify `CLAUDE.md` still exists.
4. Verify `PROGRESS.md` still exists.
5. Verify all six files inside `/docs` still exist.
6. Verify no unnecessary application features were implemented.
7. Verify no credentials were added.
8. Verify no fake production data was added.
9. Verify the project remains compatible with PHP/cPanel deployment.

---

# 19. UPDATE PROGRESS

After the architecture is successfully created, update:

```text
PROGRESS.md
```

Change the appropriate project status to indicate:

```text
Project Structure
COMPLETED
```

and update the current task to:

```text
Next: Create and approve Specification 01 — Project/Application Foundation
```

Do not mark the entire project as completed.

Only the architectural folder foundation has been completed.

---

# 20. FINAL REPORT

When finished, report:

```text
## Architecture Setup Complete

### Created
- folders
- placeholder files
- .gitignore

### Not Implemented
- authentication
- database schema
- vehicle management
- dashboard
- public website features
- email integration
- image processing
- tracking integration

### Verification
- structure verified
- project context preserved
- no credentials added
- no fake production data added

### Current Project Status
...

### Next Recommended Specification
...
```

Do not claim anything was tested if it was not actually tested.

---

# FINAL INSTRUCTION

Create the project architecture now.

**Do not start feature development.**

The goal of this task is only to transform the empty project directory into the approved architectural foundation described in `docs/03-architecture-and-data.md`.
