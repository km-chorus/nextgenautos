# Vehicle Import Platform — Project Progress Tracker

## 1. Purpose

This file is the project's central progress tracker.

It records:

* current project status
* completed work
* work currently in progress
* next implementation task
* upcoming tasks
* blocked tasks
* specification status
* testing status
* success criteria
* outstanding decisions
* known issues

This file must be kept up to date throughout development.

It is intended to be readable by both the project owner and AI development tools.

---

# 2. Current Project Status

**Overall Status:** Homepage Structure & Hero (Spec 10A) Implemented and tested, awaiting owner review (no approved hero photographs exist yet: the hero shows its plain panel until they are added) — Public Website Foundation (Spec 09) Implemented and tested, awaiting owner review — Team Management (Spec 08) Implemented and tested, awaiting owner review — Vehicle Image Management (Spec 07) Implemented and tested, awaiting owner review — Vehicle Management (Spec 06) Implemented and tested, awaiting owner review — Brand Management (Spec 05) Implemented and tested, awaiting owner review — Foundation Complete — Project Structure Complete — Database Foundation Implemented — Admin UI Foundation Implemented — Functional Admin Dashboard Implemented — Admin Authentication Implemented (all admin pages require sign-in) — Authentication Fix + TOTP 2FA + Password Reset Implemented (awaiting owner review; no administrator exists yet in the real database)

**Current Phase:** Specification Planning / early implementation

**Current Specification:** None active. Last implemented: Spec 10A — Homepage Structure & Hero (`Context/specs/10_spec_homepage_hero.md`, 2026-10-08, `IMPLEMENTED`). Before that: Spec 09 — Public Website Foundation & Global UI (`Context/specs/09_spec_webpage_ui.md`, 2026-10-08, `IMPLEMENTED`). Before that: Spec 08 — Team Management (`Context/specs/08_spec_team.md`, 2026-10-08, `IMPLEMENTED`). Before that: Spec 07 — Vehicle Image Management (`Context/specs/07_spec_vehicle_image.md`, 2026-10-08, `IMPLEMENTED`). Before that: Spec 06 — Vehicle Management (`Context/specs/06_spec_vehicle_managt`, 2026-10-08, `IMPLEMENTED`). Before that: Spec 05 — Brand Management (`Context/specs/05_spec_brand_crud.md`, 2026-10-08, `IMPLEMENTED`). Before that: Authentication Fix + 2FA + Password Reset (owner request of 2026-10-08, row 03C), on top of Spec 03B — Admin Authentication & Security Foundation (`Context/specs/04_spec-_admin_auth.md`). Implemented earlier: Spec 04 — Functional Admin Dashboard (`Context/specs/03_spec_dashboard.md`), 03A Admin UI Foundation (`Context/specs/02_spec_admin.md`), Database Foundation (`Context/specs/01_spec_database.md`), Project Structure (`Context/specs/00_spec_structure.md`, `COMPLETED`).

**Current Task:** Owner review of the redesigned vehicle detail page (open any vehicle; mark interior photos on Manage Images to see the Interior tab). Older notes: Owner review of Spec 16 phase 1 — Parts admin (sidebar "Parts") and the homepage Latest Parts & Accessories section (shows "Coming Soon" until a part is published). Older notes: Owner review of Spec 18 — Cookie Consent (open any public page: banner at the bottom; footer "Cookie settings"; `/cookies/`). Confirm the PROPOSED defaults (Known Issue 31). Older notes: Owner review of Spec 10A — Homepage Hero (open `/`). OWNER ACTION: supply approved hero photographs (3-4 WebP files, see Known Issue 30); the carousel appears automatically when two or more exist. Next planned specification: Spec 10B — Homepage Featured Vehicles (not started). Older notes: Owner review of Spec 09 — Public Website Foundation (open `/` and any header/footer link; in development also `/styleguide/`). Next planned specification: Spec 10 — Public Homepage (not started; it must not begin before this foundation is accepted). Older notes: Owner review of Spec 08 — Team Management (`/admin/team.php`; one real member with a photo already exists, created by the owner). Next planned specification: Spec 09 — Enquiries (not started). Shipment Management and Tracking are DEFERRED pending clarification of the company's actual shipping/tracking workflow (not removed from the roadmap). Older notes: RESTART your PHP server (the dev `php.ini` now enables GD, fileinfo and exif and sets upload limits), then review Spec 07 — Vehicle Image Management (open a vehicle, then "Manage images"). Next planned module: see Open Decisions (the Spec 07 text calls Shipment Management "Spec 08"; this tracker lists Public Vehicle Catalogue as 08) — not started. Older note: Owner review of Spec 06 — Vehicle Management (`/admin/vehicles.php`) and Spec 05 — Brand Management (`/admin/brands.php`). Next planned module after acceptance: Spec 07 — Vehicle Image Management (not started). Older note: OWNER ACTION FIRST: restart the PHP server, run `php database/create_admin.php` to create the first administrator (none exists: this was the cause of the failed first login), sign in, then turn on 2FA at Account security. Then, after review, Spec 05 — Brand Management (not started: the owner said not to begin it until authentication is accepted). Owner review of the Database Foundation, Admin UI Foundation, Dashboard and Authentication is pending. FIRST ACTION for the owner: create the first administrator with `php database/create_admin.php` (none exists yet; the test accounts were removed). Specification 01 — Project/Application Foundation is still not written.

**Next Major Milestone:** Owner acceptance of authentication (login, 2FA, password reset), then Brand management (Spec 05). Before any deployment: resolve Known Issues 1, 2, 9 and 15 to 19 (credentials file, database user, web-root exposure, Brevo configuration, APP_KEY, access logs).

**Production Status:** Not started

---

# 3. Status Definitions

Use the following statuses consistently.

| Status           | Meaning                                                         |
| ---------------- | --------------------------------------------------------------- |
| `NOT STARTED`    | Work has not begun                                              |
| `PLANNED`        | Work has been defined and is waiting for implementation         |
| `IN PROGRESS`    | Currently being implemented                                     |
| `BLOCKED`        | Cannot continue because of an unresolved dependency or decision |
| `IMPLEMENTED`    | Code has been written                                           |
| `TESTING`        | Implementation exists and is currently being tested             |
| `PASSED`         | Requirements and acceptance criteria have been verified         |
| `NEEDS REVISION` | Testing/review identified problems                              |
| `DEFERRED`       | Intentionally postponed                                         |
| `OUT OF SCOPE`   | Not part of the approved project                                |
| `COMPLETED`      | Fully implemented, tested, reviewed, and accepted               |

`COMPLETED` should only be used when the feature has passed its acceptance criteria.

---

# 4. Project Phase Tracker

| Phase                    | Status        | Success Criteria                                      |
| ------------------------ | ------------- | ----------------------------------------------------- |
| Project Foundation       | `COMPLETED`   | Six foundational context documents established        |
| Architecture             | `COMPLETED`   | Approved PHP/MySQL architecture documented            |
| Business Rules           | `COMPLETED`   | Vehicle, shipment, tracking and duty rules documented |
| Design System            | `COMPLETED`   | UI/UX rules documented                                |
| Security Foundation      | `COMPLETED`   | Security and integration rules documented             |
| AI Development Rules     | `COMPLETED`   | AI development/testing rules documented               |
| Specification Planning   | `IN PROGRESS` | Implementation backlog defined and prioritized        |
| Project Structure        | `COMPLETED`   | Approved folders, placeholders and .gitignore created and verified |
| Database Foundation      | `IMPLEMENTED` | Initial production schema implemented and tested (77/77 DB tests pass on MySQL 8.4; awaiting owner review) |
| Admin UI Foundation      | `IMPLEMENTED` | Admin shell, login UI and component system built; UI only (102/102 browser checks pass; awaiting owner review) |
| Authentication           | `IMPLEMENTED` | Login, sessions, CSRF, throttling, audit (Spec 03B) + TOTP 2FA, recovery codes, password reset, security settings (03C). Tests: 64 + 111 logic, 255 HTTP end-to-end, 114 real-Chrome checks pass. Real email delivery (Brevo) NOT verified: no Brevo account configured. Awaiting owner review |
| Admin Foundation         | `IMPLEMENTED` | Admin dashboard and navigation working (Spec 04: reads real MySQL data; 85 populated + 33 empty + 32 error-state browser checks and 44 data-layer checks pass; awaiting owner review; no authentication) |
| Vehicle Management       | `IMPLEMENTED` | Vehicle CRUD (Spec 06: 246 logic + 206 HTTP + 134 real-Chrome checks) and image management (Spec 07: 156 logic + 148 HTTP + 95 real-Chrome checks) working; awaiting owner review |
| Public Vehicle Catalogue | `PLANNED`     | Customers can browse and filter vehicles              |
| Vehicle Details          | `PLANNED`     | Complete vehicle detail experience working            |
| Duty Calculator          | `PLANNED`     | Correct external duty workflow implemented            |
| Shipment Management      | `PLANNED`     | Admin can manage real shipment records                |
| Tracking                 | `PLANNED`     | Customer can access approved external tracking        |
| Enquiries                | `PLANNED`     | Customer enquiries are securely recorded and emailed  |
| Team / Company Content   | `IN PROGRESS` | Team Management implemented (Spec 08: 188 logic + 186 HTTP + 143 real-Chrome checks pass; awaiting owner review). Company content pages are later specs |
| Public Content Pages     | `PLANNED`     | About, How It Works, FAQ and Contact are complete     |
| Email Integration        | `PLANNED`     | Brevo transactional email working                     |
| Security Hardening       | `PLANNED`     | Security review completed                             |
| Responsive QA            | `PLANNED`     | Mobile/tablet/desktop workflows verified              |
| Performance QA           | `PLANNED`     | Images, queries and assets optimized                  |
| Production Deployment    | `PLANNED`     | Production deployment successfully completed          |
| Final Acceptance         | `PLANNED`     | All launch criteria passed                            |

---

# 5. Foundation Documents

| Document                                | Status      | Purpose                             |
| --------------------------------------- | ----------- | ----------------------------------- |
| `docs/01-project-context.md`            | `COMPLETED` | Defines project identity and goals  |
| `docs/02-scope-and-business-rules.md`   | `COMPLETED` | Defines scope and business rules    |
| `docs/03-architecture-and-data.md`      | `COMPLETED` | Defines architecture and data model |
| `docs/04-design-and-ux-rules.md`        | `COMPLETED` | Defines visual and UX standards     |
| `docs/05-security-and-integrations.md`  | `COMPLETED` | Defines security and integrations   |
| `docs/06-ai-development-and-testing.md` | `COMPLETED` | Defines AI development workflow     |

---

# 6. Specification Tracker

All implementation work must be represented by a specification.

| #  | Specification                    | Status    | Dependencies          | Success Criteria                                       |
| -- | -------------------------------- | --------- | --------------------- | ------------------------------------------------------ |
| 00 | Project Structure (`Context/specs/01_spec_structure.md`) | `COMPLETED` | Foundation docs | Folders, placeholder files and .gitignore exist; no features, credentials or fake data |
| 01 | Project / Application Foundation | `PLANNED` | Foundation docs       | Application structure and base configuration work      |
| 02 | Database Foundation (`Context/specs/01_spec_database.md`) | `IMPLEMENTED` | 01 (spec not yet written; schema built without it) | Required schema created and verified. 10 tables, FKs, indexes, 12 brands seeded; 77 DB tests pass. Awaiting owner review. |
| 03A | Admin UI Foundation (`Context/specs/02_spec_admin.md`) | `IMPLEMENTED` | 02 | **UI foundation only.** Admin shell, sidebar, mobile nav, login screen UI, components. Authentication logic NOT implemented. Awaiting owner review. |
| 03 | Admin Authentication & Security Foundation (spec 03B, `Context/specs/04_spec-_admin_auth.md`) | `IMPLEMENTED` | 02, 03A | Real login/logout, hashed passwords, CSRF, session security + timeout, throttling, audit logging, reusable guard `requireAdmin()`, authorization foundation, CLI admin setup. No schema change. Awaiting owner review. |
| 03C | Authentication Fix + 2FA + Password Reset (owner request of 2026-10-08; no spec file) | `IMPLEMENTED` | 03B | Root cause of the failed first login found (no administrator existed). Setup script made reliable on Windows. TOTP 2FA with encrypted secrets and recovery codes, secure password reset, security settings page. Schema migration 001. Awaiting owner review. |
| 13 | Customer Sign-in, Registration and Google Sign-in (`Context/specs/13_spec_customer_auth.md`; owner request of 2026-10-11) | `IMPLEMENTED` | 03B, 09 | Login button opens a customer sign-in; admin sign-in separate and unlinked; migrations 003 and 004; 34 logic checks + HTTP checks pass; Google flow tested only with a stub (no Google credentials configured). Awaiting owner review |
| 04 | Functional Admin Dashboard (`Context/specs/03_spec_dashboard.md`) | `IMPLEMENTED` | 02, 03A (built before 03B authentication at the owner's direction; authentication is explicitly out of its scope) | Dashboard reads real MySQL data: 4 statistics, recent vehicles, recent enquiries, shipment overview, quick actions, working sidebar navigation. Awaiting owner review. |
| 05 | Brand Management (`Context/specs/05_spec_brand_crud.md`) | `IMPLEMENTED` | 02, 03B, 03C | List, search, status filter, create, edit, view, activate/deactivate, delete-where-safe, audit logging, on the real admin shell. No schema change. 167 logic + 170 HTTP + 120 real-Chrome checks pass. Awaiting owner review. |
| 06 | Vehicle Management (`Context/specs/06_spec_vehicle_managt`) | `IMPLEMENTED` | 02, 03B, 03C, 05 | List with search, brand/status/year filters and pages; add, edit, view; status changes; delete where safe; audit logging. No schema change. 246 logic + 206 HTTP + 134 real-Chrome checks pass. Awaiting owner review. |
| 07 | Vehicle Image Management (`Context/specs/07_spec_vehicle_image.md`) | `IMPLEMENTED` | 06 | Secure multi-image upload (content-checked, re-encoded to WebP with thumbnail), gallery, primary image, ordering, alt text, delete. No schema change; adds `intervention/image`. 156 logic + 148 HTTP + 95 real-Chrome checks pass. Awaiting owner review. |
| 09F | Public Website Foundation & Global UI (`Context/specs/09_spec_webpage_ui.md`, the owner's "Spec 09") | `IMPLEMENTED` | 02 | Front controller + clean-URL router, shared header/navigation/mobile menu/footer, design tokens, components, SEO meta, responsive image helper, 404/500 pages. No database change, no new dependency. 61 logic + 116 HTTP/browser checks (Chrome and Edge). Awaiting owner review. |
| 14V | Public Vehicle Detail Page with Contact, WhatsApp and Reserve (`Context/specs/14_spec_vehicle_detail.md`, approved 2026-10-14) | `IMPLEMENTED` 2026-10-14 (awaiting owner review) | 06, 07, 11 | Clicking a vehicle opens its page; enquiries and reservation requests are stored; WhatsApp opens a pre-filled chat |
| 15E | Admin Enquiries, Reservations and Contact Settings (`Context/specs/15_spec_enquiries_reservations.md`, draft 2026-10-14) | `PLANNED` (approved 2026-10-14) | 14V | Inbox, confirm reservation (vehicle becomes Reserved), Brevo alert, editable phone / WhatsApp / notification email |
| 17C | Contact Page and Admin Contact Settings (`Context/specs/17_spec_contact_page.md`, approved 2026-10-14) | `IMPLEMENTED` 2026-10-14 (awaiting owner review) | 14V | `/contact/` live: WhatsApp chat button, phone, email, opening hours, social links (only what is set), contact form with topic; admin Settings page edits all of it; migration 005 adds the `part` enquiry type |
| 18C | Cookie Consent, Cookie Settings and Session Reliability (`Context/specs/18_spec_cookie_consent.md`; owner request of 2026-10-16) | `IMPLEMENTED` 2026-10-16 (awaiting owner review) | 09, 13, 14V | Banner (Accept all / Essential only / Cookie settings) on every public page; settings dialog + `/cookies/` policy page; one optional choice "Browsing activity" (off = `Cache-Control: no-store` + `Clear-Site-Data: "cache"`; on = `private, no-cache`); consent cookie HttpOnly/Lax/180 days; cross-site POST refused; no open redirect; fix: dead customer session cookie no longer shows "Account". 60 logic + 40 HTTP + 11 session-flow + 91 real-Chrome checks |
| 16P | Car Parts Catalogue, admin + public (`Context/specs/16_spec_parts_catalogue.md`, draft 2026-10-14) | Phase 1 (admin + homepage section) `IMPLEMENTED` 2026-10-16, awaiting owner review; phase 2 (public /parts/ and part pages) `PLANNED` | 07, 14V, 15E | Parts with category, part number, condition, price, fitments; public list with fit-my-car filters and part pages |
| 08 | Public Vehicle Catalogue         | `PLANNED` | 06–07                 | Customers can browse/search/filter inventory           |
| 09 | Vehicle Details                  | `PLANNED` | 08                    | Complete vehicle information is displayed              |
| 10 | Duty Calculator Workflow         | `PLANNED` | 09                    | Correct external duty workflow works                   |
| 11 | Shipment Management              | `DEFERRED` (owner, 2026-10-08) | 02–06                 | Admin can manage shipment records. Deferred until the company's actual shipping/tracking workflow is clarified; still part of the roadmap |
| 12 | Vehicle Tracking                 | `DEFERRED` (owner, 2026-10-08) | 11                    | Customers can access approved external tracking. Deferred together with Shipment Management; still part of the roadmap |
| 13 | Customer Enquiries (the owner's next specification is called "Spec 09 — Enquiries") | `PLANNED` | 06–09                 | Enquiries are securely captured                        |
| 14 | Brevo Email Integration          | `PLANNED` | 13                    | Approved transactional emails work                     |
| 15 | Team Management (`Context/specs/08_spec_team.md`, the owner's "Spec 08") | `IMPLEMENTED` | 03, 07 (image pipeline) | Team CRUD, category, status, ordering, search/filters and photo upload/replace/remove on the shared image pipeline. One schema change (migration 002: category). 188 logic + 186 HTTP + 143 real-Chrome checks pass. Awaiting owner review. |
| 16 | About / Company Content          | `PLANNED` | 03                    | Approved company information is manageable             |
| 17 | How It Works / FAQ / Content     | `PLANNED` | 03                    | Public content pages work                              |
| 18 | Contact Page                     | `PLANNED` | 13–14                 | Contact workflow works                                 |
| 19 | Public Homepage (owner specs 10A to 10x: `Context/specs/10_spec_homepage_hero.md` is 10A) | `IN PROGRESS` | Major public features | 10A Homepage Structure & Hero `IMPLEMENTED` (awaiting owner review and approved photographs); 10B Latest Vehicles `IMPLEMENTED` 2026-10-14 (awaiting owner review); remaining homepage sections are later specifications |
| 20 | Security Hardening               | `PLANNED` | Core application      | Security review passes                                 |
| 21 | Responsive & Accessibility QA    | `PLANNED` | Public application    | Main workflows work across target devices              |
| 22 | Performance Optimization         | `PLANNED` | Major features        | Performance requirements satisfied                     |
| 23 | Production Deployment            | `PLANNED` | QA complete           | Production environment works                           |
| 24 | Final Acceptance                 | `PLANNED` | 23                    | All launch criteria pass                               |

The specification order may be changed only through an approved project decision.

---

# 7. Completed Work

## Foundation

* [x] Project identity established
* [x] Target markets defined
* [x] Supported vehicle brands defined
* [x] Customer journey defined
* [x] Public website scope defined
* [x] Admin scope defined
* [x] Duty calculator responsibility defined
* [x] Shipment/tracking responsibility defined
* [x] Container number business process defined
* [x] Architecture selected
* [x] Data architecture established
* [x] Design direction established
* [x] Security foundation established
* [x] Brevo selected as email provider
* [x] AI development rules established
* [x] Specification-driven workflow established

---

# 8. Current Work

## Active Work

**Task:** Specification Planning

**Status:** `IN PROGRESS`

### Objective

Create the final implementation backlog and confirm the order in which the specifications will be implemented.

### Project Structure (spec 00) — `COMPLETED` 2026-10-07

Created: all folders from `Context/specs/01_spec_structure.md` plus `/app/repositories`; placeholder files (`app/config/database.php`, `app/config/app.php`, `app/services/EmailService.php`, `app/services/ImageService.php`, `includes/header.php`, `footer.php`, `navigation.php`); entry points `public/index.php` and `admin/index.php`; `.gitignore`; `uploads/.htaccess` (disables script execution in uploads); `.gitkeep` in `uploads/vehicles` and `uploads/team`.

Decisions applied:

* Root `docs/` and `specs/` were NOT created; the foundation documents and specs stay under `Context/`. Existing files were not moved.
* `/app/repositories` was added, per `Context/02_architecture.md`.
* Tracked as spec 00 to avoid clashing with Specification 01 (Project/Application Foundation).

Verification: directories and files confirmed present; `CLAUDE.md` and all `Context/` files intact; `.gitignore` rules checked with `git check-ignore`; no credentials, schema or fake data added. PHP syntax check NOT run (PHP is not installed on this machine). Files are comment-only placeholders.

### Database Foundation (`01_spec_database.md`) — `IMPLEMENTED` 2026-10-07

Files: `database/schema.sql`, `database/seed.sql`, `database/tests/db_test.php`, `app/config/database.php` (PDO `Database` class, credentials from environment/`.env`), `.env.example`.

Tables (10): `admin_users`, `brands`, `vehicles`, `vehicle_images`, `shipments`, `enquiries`, `team_members`, `site_content`, `settings`, `audit_logs`. InnoDB, utf8mb4, UTC timestamps.

Relationships: brands→vehicles (RESTRICT), vehicles→vehicle_images (CASCADE, deliberate), vehicles→shipments (RESTRICT), vehicles→enquiries (RESTRICT, nullable for general enquiries), admin_users→audit_logs (RESTRICT, nullable). Soft deletion (`deleted_at`) on `vehicles` only. Prices `DECIMAL(12,2)` + `currency` CHAR(3) default `GHS`. Features stored as one JSON array column (no feature table). Shipment status is an ENUM of the six approved values; container number is nullable and never generated. FAQ entries are rows in `site_content`.

Seed data: the 12 approved brands and the two approved external URLs (`tracking_provider_url`, `duty_calculator_url`) in `settings`. No vehicles, customers, shipments, enquiries, team members or admin users.

Decisions applied (owner-approved or documented): customer name/email columns added to `shipments`; mileage, transmission, fuel type, drive type and `is_published` added to `vehicles` (taken from the context documents; owner did not explicitly answer this question, so please confirm); enquiry statuses `new / in_progress / closed` chosen by the implementer (no source defines them).

Verification: schema and seed loaded into an empty local MySQL 8.4.9 database; `php database/tests/db_test.php` (run with `-d extension=pdo_mysql`) = 77 passed, 0 failed (connection, invalid-credentials, schema, FKs, unique/CHECK constraints, RESTRICT/CASCADE behaviour, seed counts, no leftover data). Not tested: MariaDB/older MySQL compatibility, least-privilege DB user.

### Admin UI Foundation, spec 03A (`02_spec_admin.md`) — `IMPLEMENTED` 2026-10-07 (UI only)

Files: `assets/css/admin.css` (tokens + components), `assets/js/admin.js`, `assets/images/icons.svg` (Lucide-style line icons, ISC), `assets/fonts/manrope-latin-wght.woff2` + `OFL.txt` (self-hosted Manrope), `app/helpers/html.php`, `app/helpers/admin_ui.php`, `includes/admin/shell_start.php`, `includes/admin/shell_end.php`, `admin/login.php`, `admin/dashboard.php` (shell demo, no data), `admin/ui-kit.php` (dev-only component library, returns 404 when `APP_ENV=production`).

Built: desktop sidebar, off-canvas mobile navigation (focus handling, Escape, backdrop, inert background), top header with breadcrumbs, login UI (show/hide password, validation states), buttons, forms, alerts, status badges, responsive tables, cards, empty states, skeletons. Authentication, sessions, database access, CRUD and dashboard data were NOT implemented. Login fields have no `name` attributes and the form never submits.

Decisions applied (owner-approved): Manrope self-hosted; icons as an inline SVG sprite (no icon package); custom CSS (no Bootstrap); tracked as row 03A so existing numbering is unchanged. Nav items for modules that do not exist yet are shown as unavailable instead of linking to missing pages. "Remember me" omitted (not approved by an authentication design). Modal foundation not built (not required yet). Temporary text brand "Mr. AmponsahAutos" (no logo asset exists). Admin pages send a strict CSP (no inline scripts/styles allowed), `nosniff`, `no-store`, `noindex`.

Verification (real Chrome via DevTools Protocol, local PHP server): 102 checks passed, 0 failed: no horizontal overflow and no console errors/CSP violations/failed requests on login, dashboard and component library at 320, 375, 390, 414, 768, 1024, 1280, 1440px; mobile menu open/close/Escape/backdrop/focus return/resize reset; desktop sidebar; password toggle; validation states; no network request on submit; Tab order; focus rings; table stacking; dismissible alert; loading state; JavaScript-disabled fallback; reduced motion. Contrast ratios computed for 22 colour pairs, all meet WCAG AA. Screenshots reviewed by the implementer. NOT tested: other browsers (Firefox/Safari), real touch devices, screen readers (NVDA/VoiceOver), cPanel/subfolder deployment.

### Spec 04 — Functional Admin Dashboard (`03_spec_dashboard.md`) — `IMPLEMENTED` 2026-10-07

Files created: `app/controllers/DashboardController.php`, `app/services/DashboardService.php`, `app/repositories/DashboardRepository.php`, `admin/coming-soon.php`, `database/tests/dashboard_test.php`.
Files modified: `admin/dashboard.php` (placeholder replaced by the functional page), `app/helpers/admin_ui.php` (nav links, enquiry statuses in the badge system, link-capable empty states, display formatters), `includes/admin/shell_start.php` (every sidebar item is now a link), `assets/css/admin.css` (dashboard section, plus table fixes below).
Database changes: none (schema untouched; temporary test rows were removed and AUTO_INCREMENT counters reset).

Flow: `admin/dashboard.php` -> `DashboardController` -> `DashboardService` -> `DashboardRepository` -> PDO -> MySQL. No SQL in templates; all four queries are prepared statements; no `mysqli`.

Definitions used (taken from the existing schema, nothing invented): Total vehicles = non-archived vehicles (`deleted_at IS NULL`); Available = those with `availability_status = 'available'`; Total shipments = all shipment rows; New enquiries = `status = 'new'`. Recent = latest 5 by id (auto-increment = creation order, uses the primary key). Recent enquiries deliberately omit email, phone and message. Shipment overview always lists all six approved statuses (zero-filled). Vehicle images only render when the stored path is a plain file inside `uploads/vehicles/`; anything else shows the neutral placeholder.

Decisions applied (implementer's, no business impact): modules without pages (Vehicles, Shipments, Enquiries, Team, Brands, Content, Settings) open one controlled placeholder page, `admin/coming-soon.php?module=...` (allow-listed against the nav, unknown values return 404, input never echoed); quick actions and "View" links use the same placeholder; page is server-rendered (no AJAX, so no loading states needed); each section fails independently with "We couldn't load this information right now. Please try again." and the technical detail goes to the PHP error log; two-column layout only from 1440px (a five-column table does not fit beside the side panels at 1280px).

Also fixed (existing 03A table CSS, found while testing real data): stacked tables could widen past the viewport at 320px (long unbroken values, and hidden header cells escaping their scroll container). Header cells are now individually hidden, stacked cells wrap, and `.table-wrap` is a positioned container.

Verification (real MySQL 8.4.9, real Chrome via DevTools Protocol, local PHP server):
* `database/tests/dashboard_test.php`: 44 passed, 0 failed (counts exact incl. archived vehicles, newest-first order, one row per vehicle despite multiple images, zero-filled shipment summary, limits clamped, no personal fields, one failing section does not break the others, image-path validation); test rows rolled back.
* Browser, empty database: 33 passed. Browser, populated with temporary labelled TEST DATA: 85 passed (exact stats 6/3/9/3, recent lists, badges, image loads, malicious stored image path `../.env` never rendered, stored `<script>`/`<img onerror>` in names shown as text and never executed, all 17 internal links return 200, every sidebar item opens a real page with the correct active state, click-through, keyboard focus, stacked tables on mobile, 2/4-column stat grids, placeholder-route hardening incl. traversal/array/script payloads, security headers). Browser, database unavailable (wrong password): 32 passed (page still renders, four friendly error states, no SQL/credentials/paths/host in the response; detail only in the server log).
* No horizontal overflow, console errors, CSP violations, failed or broken requests at 320, 375, 390, 414, 768, 1024, 1280, 1440px in all three scenarios.
* Regression: 03A admin UI suite re-run, 102/102 passed; database suite re-run, 77/77 passed (also confirms the database is back to 12 brands + 2 settings and no test data).
* NOT tested: Firefox/Safari, real touch devices, screen readers, MariaDB/older MySQL, large datasets (query performance with thousands of rows), cPanel/subfolder deployment.

### Spec 03B — Admin Authentication & Security Foundation (`04_spec-_admin_auth.md`) — `IMPLEMENTED` 2026-10-07

Files created: `app/services/AuthService.php`, `SessionService.php`, `AuthorizationService.php`, `PasswordPolicy.php`; `app/repositories/AdminUserRepository.php`, `AuditLogRepository.php`; `app/controllers/AuthController.php`; `app/helpers/auth.php` (the guard `requireAdmin()`), `app/helpers/csrf.php`; `admin/logout.php`; `database/create_admin.php` (CLI-only setup); `database/tests/auth_test.php`.
Files modified: `app/config/app.php` (now `AppConfig`: environment, session timeout, throttle limits, error visibility, shared `.env` reader), `app/config/database.php` (uses `AppConfig::env`, behaviour unchanged), `admin/login.php` (existing UI wired to real authentication), `admin/index.php`, `admin/dashboard.php`, `admin/coming-soon.php`, `admin/ui-kit.php` (guard added), `includes/admin/shell_start.php` (real administrator name, POST logout form), `app/helpers/admin_ui.php` (X-Frame-Options, stronger cache headers), `assets/js/admin.js` (login submits for real, double-submit guard), `assets/css/admin.css` (small additions), `.env.example` (documents `APP_ENV`, `SESSION_TIMEOUT_SECONDS`).
Database changes: **none** (existing `admin_users` and `audit_logs` used as designed; no new tables or columns).

Security controls: passwords via `password_hash()`/`password_verify()` (PHP default algorithm, auto-rehash on login), policy 12 to 72 bytes; no default/hardcoded admin; first admin only through the CLI script (hidden prompt or `--password-stdin`, refuses to run on the web, requires `--allow-additional` for more admins); generic "Invalid email or password." for unknown email / wrong password / disabled account, with one `password_verify` on every path (dummy hash) for timing; CSRF token (random_bytes, hash_equals) on login and logout; PHP sessions (HttpOnly, SameSite=Lax, Secure in production or on HTTPS, strict mode, no trans-sid), `session_regenerate_id(true)` on login, minimal session content, 30-minute inactivity timeout (`SESSION_TIMEOUT_SECONDS`, values under 300 honored only in development), account re-checked in the database on every request (disabling an admin ends the session immediately; role re-read, never trusted from the session); logout is a POST form that works without JavaScript and destroys the session server-side; throttling in three layers in the existing audit table (5 failures per IP+account, 10 per IP, 30 per account across all IPs, 15-minute window; throttled attempts are not counted so a lockout cannot be extended; success resets the account counters; a different IP can still sign in so an attacker cannot lock the owner out); events logged: LOGIN_SUCCESS, LOGIN_FAILURE, ACCOUNT_DISABLED_LOGIN_ATTEMPT, RATE_LIMIT_TRIGGERED (once per burst), CSRF_FAILURE, SESSION_TIMEOUT, LOGOUT, ADMIN_CREATED (no passwords, hashes, tokens, session ids or raw emails of failed attempts are stored); guard fails closed (503, nothing rendered) if the database is down; every admin page calls `requireAdmin()` first; headers: CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, no-store/no-cache; PHP errors hidden from the browser in production. Authorization: explicit role->permission map (`administrator` = all; unknown roles = nothing).
Headers intentionally deferred: Strict-Transport-Security (set when the production domain serves HTTPS), Permissions-Policy.

Decisions applied: failed logins re-render the form with HTTP 200 (a 4xx page makes Chrome print a console error on every typo); abnormal cases keep honest codes (CSRF 400, throttled 429 + Retry-After, failure 500). Throttle bookkeeping stores a one-way 60-bit fingerprint of the submitted email in `audit_logs.entity_id` (no raw email). "Remember me" and password-reset email are out of scope and not present; "Forgot password?" stays visibly disabled.

Verification (real MySQL 8.4.9, real Chrome via DevTools Protocol, plain `php -S`):
* `database/tests/auth_test.php`: 64/64 (config incl. production-safe defaults and cookie flags, password policy, authorization, CSRF helpers, login outcomes, throttling scenarios, hash upgrade, timing, session resolution/timeout/disabled/role tamper, logging hygiene); rolled back.
* HTTP end-to-end (4 servers: dev, 6-second timeout, production mode, broken database): 107/107, including direct access to every admin URL while signed out (302 to login, empty body, no cookie), setup script behaviour, cookie attributes, session fixation with an attacker-chosen id, enumeration (identical pages), CSRF cases, throttling incl. after-window recovery and password spraying, timeout/expiry/replay, logout/replay of a stolen cookie, account disabled mid-session, production Secure cookie and ui-kit 404, database-down fail-closed, no passwords/tokens/session ids in any server log or audit row.
* Real Chrome: 32/32 (8 viewports no overflow/console errors, validation, error page, triple-click sends one request, cookie flags via CDP, JS cannot read the cookie, Back button after logout shows login, JavaScript-disabled login and logout).
* Regression, signed in: dashboard suite empty 33/33 and populated 84/85 (the single miss was an outdated exact-string expectation for Cache-Control, deliberately strengthened here; the new header is verified by the HTTP suite); 03A admin UI suite 96 passed, 6 intentionally superseded checks (old UI-only login behaviour and the old "unavailable nav items"); DB suite 77/77, dashboard data suite 44/44. All test accounts, audit rows and test business data removed afterwards (admin_users 0, audit_logs 0).
* NOT tested: the interactive hidden-password prompt (needs a real terminal; the `--password-stdin` path is tested), Firefox/Safari, real HTTPS (Secure flag verified by header and unit test only), proxy/load-balancer deployments, cPanel/Apache, concurrent logins, screen readers.

### Authentication Fix + 2FA + Password Reset (owner request of 2026-10-08) — `IMPLEMENTED` 2026-10-08

**Diagnosis of the failed first login.** The login code was not at fault. `admin_users` was EMPTY: the test accounts used to verify Spec 03B had been removed afterwards, and the first administrator had never been created. Every attempt therefore returned the generic "Invalid email or password." The audit log confirms it: only `LOGIN_FAILURE` events described as "no matching account" (15 of them by 2026-10-08 01:56 UTC, plus one `RATE_LIMIT_TRIGGERED`). Contributing defect found and fixed: `database/create_admin.php` refused to run unless STDIN was a real terminal, which Git Bash and some editor terminals are not. Nothing in the login path was weakened to fix this.

**First administrator.** `php database/create_admin.php` now asks for name, email and a hidden password twice (hidden console prompt on a real Windows console; a masked password window when there is no console; documented PowerShell pipe recipe as the universal fallback, tested). It validates (12 to 72 bytes), hashes with `password_hash()`, creates an active `administrator`, logs `ADMIN_CREATED`, never prints or logs the password, refuses to run over the web, and asks for `--allow-additional` when an administrator already exists.

Files created: `app/services/TwoFactorService.php`, `SecretBox.php`, `PasswordResetService.php`, `Transaction.php`; `app/repositories/RecoveryCodeRepository.php`, `PasswordResetRepository.php`; `app/controllers/PasswordResetController.php`, `SecurityController.php`; `admin/two-factor.php`, `forgot-password.php`, `reset-password.php`, `security.php`; `includes/admin/auth_start.php`, `auth_end.php`; `database/migrations/001_auth_2fa_and_password_reset.sql`; `database/generate_app_key.php`; `database/tests/twofactor_reset_test.php`; `composer.json`, `composer.lock`.
Files modified: `app/config/app.php` (APP_KEY, APP_URL, reset lifetime, Brevo settings, new limits), `app/services/AuthService.php` (two-step login, second-factor and re-authentication throttling, session invalidation), `SessionService.php` (pending 2FA / enrollment / reset-binding state), `EmailService.php` (placeholder replaced by the Brevo implementation), `app/repositories/AdminUserRepository.php`, `AuditLogRepository.php`, `app/controllers/AuthController.php`, `app/helpers/admin_ui.php`, `admin/login.php` (now on the shared auth layout; "Forgot password?" is real), `includes/admin/shell_start.php` (Account security link), `assets/css/admin.css`, `assets/js/admin.js` (password strength, double-submit guard), `assets/images/icons.svg`, `database/schema.sql`, `database/create_admin.php`, `database/tests/db_test.php`, `auth_test.php`, `.env.example`.

Database changes (migration 001, applied to the real database; `schema.sql` updated and proven identical by loading it into a throwaway database): `admin_users` + `two_factor_enabled`, `two_factor_secret_encrypted`, `two_factor_confirmed_at`, `two_factor_last_step`, `password_changed_at` and a CHECK that 2FA cannot be "enabled" without a secret and confirmation; new tables `admin_recovery_codes` (HMAC hashes, one row per code) and `password_reset_tokens` (SHA-256 hashes). Existing data preserved.

Dependencies (justified: TOTP and QR generation are security-sensitive and must not be hand-written): `spomky-labs/otphp` 11.5.0 (RFC 6238; verified against the RFC test vectors) and `bacon/bacon-qr-code` 3.1.1 (local SVG QR, nothing sent to a third party), plus four small support packages; Composer reports no security advisories. `vendor/` stays out of Git; `composer.json` and `composer.lock` are committed. Deployment must run `composer install --no-dev` (or upload `vendor/`). PHP extensions required: `pdo_mysql`, `sodium`, `openssl`, `curl`, `mbstring` (the dev machine's minimal `php.ini` now enables them and points `curl.cainfo`/`openssl.cafile` at a PEM exported from the Windows certificate store; TLS verification is never disabled).

Security controls added: TOTP secret encrypted at rest with libsodium secretbox using `APP_KEY` (kept in the environment, never in the database or Git); no plaintext fallback (without a valid key 2FA is unavailable and says so); 160-bit secrets, standard 30-second codes with a one-step drift window, replay protection (each time-step usable once, atomic); 2FA enabled only after a valid confirmation code; password step creates only a short-lived (5 minutes) restricted "pending" state with no admin id (the guard treats it as signed out) and the session id is regenerated at the password step and again after the second factor; 10 one-time recovery codes (50 bits each, shown once, stored only as keyed HMAC hashes, regeneration invalidates all old codes); disabling 2FA, regenerating codes and changing the password need the current password plus a current code; second-factor and re-authentication failures share one throttle (5 per administrator since last sign-in, 10 per IP, 15-minute window, temporary); password reset tokens are 256 random bits, only the SHA-256 hash is stored, 30-minute lifetime (configurable 5 to 120), single use (atomic), a new request replaces older links, the token leaves the URL immediately after validation (redirect), requests are throttled (3 per account and 5 per IP per hour, counted whether or not the email exists), the confirmation is always the same sentence and every request takes at least 1.2 seconds so timing does not reveal the account, links are built from the configured `APP_URL` (never from the request, so a forged Host header cannot redirect a link; https required in production); a reset changes only the password: 2FA stays enabled (no bypass) and every session created before the change (including one created in the same second) is ended via `password_changed_at`; new events: `2FA_SETUP_STARTED`, `2FA_SETUP_COMPLETED`, `2FA_FAILURE`, `2FA_DISABLED`, `RECOVERY_CODE_USED`, `RECOVERY_CODES_REGENERATED`, `REAUTH_FAILURE`, `PASSWORD_CHANGED`, `PASSWORD_RESET_REQUESTED`, `PASSWORD_RESET_COMPLETED`, `PASSWORD_RESET_FAILED`, `PASSWORD_RESET_EMAIL_FAILED` (never a password, hash, code, secret, token or key).

Brevo: all email goes through `EmailService` (`POST /v3/smtp/email`, `api-key` header, 10 second timeout, TLS verified). Configured by `BREVO_API_KEY`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`, `APP_URL` in the environment. If they are missing, "Forgot password?" says plainly that email is not configured and sends nothing (it does NOT claim a message was sent). The request shape, headers, content and link were verified against a local mock Brevo server; **delivery through the real Brevo service has NOT been tested** (no Brevo account/key exists in this project yet).

Verification (real MySQL 8.4.9; real Chrome via DevTools Protocol; an independent TOTP implementation in Node acted as the authenticator app; all end-to-end runs used a throwaway database, never the real one):
* Logic suites: `twofactor_reset_test.php` 111/111 (RFC 6238 vectors, drift window, replay, encryption and tamper detection, enrollment, recovery codes, throttling, token lifecycle, 2FA-survives-reset, session invalidation, uniform responses and timing, logging hygiene); `auth_test.php` 64/64; `db_test.php` 83/83; `dashboard_test.php` 44/44. All rolled back.
* HTTP end-to-end: 149/149 against four servers (normal with a mock Brevo, email unconfigured, unusable APP_KEY, database down): enrollment not enabled until confirmed, QR/key, login with restricted pending state, session ids at every step, wrong/replayed codes, recovery single use, throttling and recovery of the window, sensitive-action re-authentication, CSRF on every new endpoint, password change ending other sessions, forgot-password (identical pages for known and unknown emails, mock Brevo request content, hashed token, expiry, replacement, single use, throttles, provider outage, unconfigured honesty), no secret in any application log or audit row.
* Real Chrome: 82/82 (every new screen at 320, 375, 390, 414, 768, 1024, 1280, 1440px: no overflow, console errors or CSP violations; strength indicator; QR renders; triple-click sends one request; JavaScript-disabled forgot/reset). Screenshots reviewed.
* Regression: earlier HTTP auth suite 106/107 and browser auth suite 31/32 (the one miss in each is a check that described the old disabled "Forgot password?" link or counted eight nav links, both changed on purpose); dashboard suite 33/33; admin UI suite 95/96 (same superseded check).
* Compatibility: a PHP process started WITHOUT the new extensions (like a server started before the php.ini change) still logs in and shows the dashboard; 2FA and email report themselves unavailable; no fatal errors.
* NOT tested: real delivery through Brevo; scanning the QR with a physical authenticator app (the independent Node authenticator and the RFC vectors stand in); the masked password dialog on a desktop and the hidden console prompt (need a person at a keyboard); Firefox/Safari; real HTTPS; production hosting; concurrent logins at scale.

### Spec 05 — Brand Management (`05_spec_brand_crud.md`) — `IMPLEMENTED` 2026-10-08

Flow: page (`admin/brand*.php`) -> `BrandController` -> `BrandValidator` + `BrandService` -> `BrandRepository` -> PDO -> MySQL. No SQL in templates; every query is a prepared statement. Reference pattern for later admin CRUD modules.

Files created: `app/controllers/BrandController.php`, `app/services/BrandService.php`, `app/repositories/BrandRepository.php`, `app/validation/BrandValidator.php`, `app/helpers/flash.php` (one-time messages for Post/Redirect/Get); pages `admin/brands.php` (list), `brand-create.php`, `brand-edit.php`, `brand-view.php`, `brand-status.php` (POST only), `brand-delete.php` (GET = confirmation page, POST = delete); `includes/admin/brand_form.php`, `brand_problem.php`; `database/tests/brand_test.php`.
Files modified: `app/helpers/admin_ui.php` (Brands nav now a real link, `admin_format_datetime`), `assets/css/admin.css` (section 11), `assets/js/admin.js` (Add Brand: slug follows the name until edited; progressive enhancement).
Database changes: **none.** The existing `brands` table already has UNIQUE(name), UNIQUE(slug), a slug CHECK, status ENUM('active','inactive') and the RESTRICT foreign key from vehicles. Verified: exactly the 12 approved brands, correct order, no invented descriptions or logos.

Decisions applied (implementer's, no business impact): flat routes (`brands.php`, `brand-edit.php?id=`...) to match the existing admin routing instead of `/admin/brands/` (spec: do not force a routing change); delete uses a server-rendered confirmation page (works without JavaScript) and the POST must carry a `confirm=yes` field; status change sends an explicit target (`active`/`inactive`) so a repeated click cannot flip it back; vehicle count shown for a brand (read from the existing relationship) to explain why deletion is refused; validation failures re-render the form with HTTP 200 (same as login); display order range 0 to 9999; description max 2000 characters; slug is lowercased and must match the existing format, blank slug is generated from the name, a typed slug is never overwritten; the permission name is `brands.manage` (administrator holds all permissions, no role map change).

Security: requireAdmin() first on every page; permission check in the controller (403 for roles without it); CSRF on create, edit, status and delete (failures return 400 and are audited as CSRF_FAILURE); state changes only by POST (GET to the status endpoint returns 405); ids validated as digits 1 to 4294967295 and looked up (not found = safe 404); redirects after status changes are built from validated values only; all output escaped with e(); prepared statements with LIKE wildcards escaped; duplicate checks in the service AND the database UNIQUE keys (a race is turned into the same field error); deletion in one transaction on the locked row, refused when any vehicle (including archived) references the brand, with the foreign key as the last guard (no cascade, no nulling, foreign key checks stay on); audit events BRAND_CREATED, BRAND_UPDATED, BRAND_ACTIVATED, BRAND_DEACTIVATED, BRAND_DELETION_ATTEMPTED, BRAND_DELETED written in the same transaction as the change (admin id, brand id, IP; no secrets); technical errors only in the server log. Not added: rate limiting on these endpoints (authenticated, CSRF-protected admin-only actions; see Known Issue 22).

Verification (all against a throwaway database `vip_e2e_test`, since dropped, or rolled-back transactions on the real one):
* `database/tests/brand_test.php`: 167/167 (validator, approved brands, search/filter/order, injection strings, create/duplicate/edit/status/delete rules, database constraints, audit rows, controller permission refusal and error containment); rolled back, counters restored.
* HTTP end-to-end: 170/170 (signed-out access to every URL and POST, listing, search, filter, create/edit/duplicates/invalid, tampered ids, status, CSRF missing/wrong/foreign/after logout, GET refused, delete confirmation and refusal, referenced brand, XSS and SQL text stored as data and escaped on every page, audit rows, regression: login, logout, dashboard, security page, placeholders).
* Real Chrome: 120/120 (list, filtered, no-results, empty, add, edit, details, delete confirmation, not-found at 320, 375, 390, 414, 768, 1024, 1280, 1440px: no overflow, console errors, CSP violations or failed requests; stacked mobile rows with 44px actions; mobile menu; search; slug helper; triple-click sends one request; focus and keyboard order; JavaScript disabled). Screenshots reviewed.
* NOT tested: Firefox/Safari, real touch devices, screen readers, MariaDB, concurrent edits by two administrators at once, the 403 page through a browser (only one role exists; tested at controller level), large brand lists.

### Spec 06 — Vehicle Management (`06_spec_vehicle_managt`) — `IMPLEMENTED` 2026-10-08

Flow: page (`admin/vehicle*.php`) -> `VehicleController` -> `VehicleValidator` + `VehicleService` -> `VehicleRepository` -> PDO -> MySQL. Same pattern as Brand Management. No SQL in templates; every query (including LIMIT/OFFSET) is a prepared statement; lists load vehicle and brand in one JOIN.

**Owner decisions obtained before coding (AskUserQuestion, 2026-10-08):** (1) keep the database's four statuses `available / reserved / sold / unavailable` (the spec's "INACTIVE" is the existing `unavailable`, shown as "Unavailable"); (2) include a "Published on website" Yes/No control (`is_published`, default No); (3) include the optional mileage (with km/mi), transmission, fuel type and drive type fields; (4) Delete = permanent delete only when unreferenced, otherwise refuse and point to a status change (no archive feature).

Files created: `app/controllers/VehicleController.php`, `app/services/VehicleService.php`, `app/repositories/VehicleRepository.php`, `app/validation/VehicleValidator.php`; pages `admin/vehicles.php` (list), `vehicle-create.php`, `vehicle-edit.php`, `vehicle-view.php`, `vehicle-status.php` (POST only), `vehicle-delete.php` (GET = confirmation page, POST = delete); `includes/admin/vehicle_form.php`, `vehicle_problem.php`; `database/tests/vehicle_test.php`.
Files modified: `app/helpers/admin_ui.php` (Vehicles nav now a real link, `admin_vehicle_label` with trim, Published/Unpublished badges), `assets/css/admin.css` (section 12), `database/tests/dashboard_test.php` (one assertion updated: the navigation check expected Vehicles to be a placeholder; it now expects Vehicles and Brands to link to their pages, the rest to the placeholder).
Database changes: **none.** The existing `vehicles` table already has everything: brand_id FK (RESTRICT), UNIQUE(slug), CHECKs (year 1900 to 2100, price >= 0, slug format), DECIMAL(12,2) price, `currency` default GHS, JSON `key_features`, `duty_included`, `is_published`, status ENUM. Differences from the spec text: there is **no `make` column** (brand_id is the only brand reference, so no contradictory data is possible); the status set is the database's (see decisions).

Vehicle data model: identity = `brand_id` + `year` + `model` + `trim`; `price` stored as exact decimal text (never floating point), `currency` (single currency GHS preserved, the form offers only the record's allowed value), `duty_included`, optional `engine_size`, `mileage` + `mileage_unit`, `transmission`, `fuel_type`, `drive_type`, `description` (plain text, max 5000), `shipping_info` (max 2000), `key_features` (the existing JSON array: one feature per line, max 40, each max 100, blank lines and repeats dropped; empty = NULL), `availability_status`, `is_published`, `slug`. Archived rows (`deleted_at`) are never shown or changed by this module.

Rules applied: only ACTIVE brands can be chosen for a new vehicle or when changing a vehicle's brand; a vehicle keeps its brand (and remains editable) when the brand is later deactivated, and its brand stays in its own drop-down marked "(inactive)"; latest model year = next calendar year, computed (not hardcoded), never beyond the database limit of 2100; price text must be digits with up to two decimals (no commas or currency text); blank slug on create = generated from year + brand + model + trim, and a generated slug that is taken gets -2, -3... (identical units are legitimate stock), while a typed slug is never altered and is rejected if taken ("This vehicle URL is already in use."); no restriction on status transitions (any status to any other; documented); deletion refused when any shipment, enquiry or image row refers to the vehicle (images are checked explicitly because their foreign key would cascade); duty is never calculated (the form and details say so); no shipment, tracking, container or image functionality exists here.

Security: requireAdmin() first on every page; permission `vehicles.manage` checked in the controller (403 for roles without it; administrator holds all); CSRF on create, edit, status and delete (failures return 400, keep the typed values on forms, and are audited as CSRF_FAILURE); state changes only by POST (GET to the status endpoint returns 405; the delete URL only shows the confirmation); ids validated (digits 1 to 4294967295) then looked up (not found = safe 404); redirects after status changes are built from validated values only (no open redirect); the status and brand values from the browser are always re-validated; all output escaped with e(); LIKE wildcards escaped; deletion in one transaction on the locked row with the foreign keys as the last guard (no cascade, foreign key checks stay on); audit events VEHICLE_CREATED, VEHICLE_UPDATED (names the changed fields), VEHICLE_STATUS_CHANGED (from -> to), VEHICLE_DELETION_ATTEMPTED, VEHICLE_DELETED, written in the same transaction as the change (admin id, vehicle id, IP; no secrets); technical errors only in the server log. Not added: rate limiting on these endpoints (authenticated, CSRF-protected; see Known Issue 24).

Verification (HTTP and browser suites against a throwaway database `vip_e2e_test`, since dropped; the logic suite in a rolled-back transaction on the real one):
* `database/tests/vehicle_test.php`: 246/246 (validator incl. prices, years, ids, flags, features, slugs, filters; brand relationship and active-brand rule; slug generation and uniqueness; create/edit/status/delete rules; shipment, enquiry and image references; database constraints; search, filters, pagination and order; injection strings; audit rows; controller permission refusal and error containment). Counts are relative to a baseline so existing real vehicles are never touched.
* HTTP end-to-end: 206/206 (signed-out access to every URL and POST; every create/edit validation case from the spec; CSRF missing/wrong/foreign; GET refused; tampered ids; all status moves; delete confirmation, forged delete, referenced vehicles; search, each filter, combined filters, 45-row pagination, junk parameters; XSS and SQL text stored as data and escaped on every page; audit rows; regression: brands, dashboard, security page, logout).
* Real Chrome: 134/134 (list, filtered, no-results, empty, add, edit, details, delete confirmation, referenced delete, not found at 320, 375, 390, 414, 768, 1024, 1280, 1440px: no overflow, console errors, CSP violations or failed requests; very long model, trim, description and feature values wrap; stacked mobile rows with 44px controls; mobile menu; search, filters and paging; create/edit/status/delete flows; triple-click sends one request; keyboard order and radio arrow keys; JavaScript disabled). Screenshots reviewed. Two layout bugs found by these tests and fixed: long unbroken values overflowing the breadcrumb, headings and buttons, and form fields misaligned by neighbouring hints.
* Regression: brand logic 167/167, authentication 64/64, 2FA and reset 111/111, dashboard data 44/44 (after the one updated assertion), Brand HTTP 169/170 (the one miss is a scratch check that used Vehicles as its placeholder example, superseded on purpose), Brand browser suite (earlier run, before this work) 120/120.
* NOT tested: Firefox/Safari, real touch devices, screen readers, MariaDB, two administrators editing the same vehicle at once, the 403 page through a browser (one role exists; tested at controller level), large inventories (thousands of rows).
* Real data note: the owner created a real vehicle (2023 Honda Accord, GHS 12,388.00, published) through the new pages while this work was in progress. It was left untouched; no test row remains in the real database.

### Spec 07 — Vehicle Image Management (`07_spec_vehicle_image.md`) — `IMPLEMENTED` 2026-10-08

Flow: pages (`admin/vehicle-images.php`, `vehicle-image-upload.php`, `vehicle-image-update.php`, `vehicle-image-delete.php`) -> `VehicleImageController` -> `ImageValidator` + `VehicleImageService` -> `VehicleImageRepository` -> PDO -> MySQL; all image decoding/encoding and file storage is in `ImageService` (the existing placeholder file, now implemented). No SQL in templates, no image processing in views.

Files created: `app/controllers/VehicleImageController.php`, `app/services/VehicleImageService.php`, `app/repositories/VehicleImageRepository.php`, `app/validation/ImageValidator.php`; pages `admin/vehicle-images.php` (gallery + upload), `vehicle-image-upload.php` (POST only), `vehicle-image-update.php` (POST only: primary, alt text, reorder), `vehicle-image-delete.php` (GET = confirmation, POST = delete); `assets/js/vehicle-images.js`; `assets/vendor/browser-image-compression.js` (+ licence and README); `admin/.user.ini` (upload limits for cPanel); `database/tests/vehicle_image_test.php`, `database/tests/image_fixtures.php`.
Files modified: `app/services/ImageService.php` (placeholder implemented), `app/helpers/flash.php` (one-time per-file result list), `app/helpers/admin_ui.php` (Primary / Gallery badges), `app/services/VehicleService.php` and `app/controllers/VehicleController.php` (the details page now lists the vehicle's images), `admin/vehicle-view.php` (Images section: thumbnails, "Manage images", empty state; replaces the "managed separately" placeholder), `includes/admin/vehicle_problem.php` (optional fixed title, e.g. "Image not found"), `assets/css/admin.css` (section 13), `composer.json` / `composer.lock`.
Database changes: **none.** The existing `vehicle_images` table already has `file_path`, `original_filename`, `alt_text`, `display_order`, `is_primary`, the CASCADE foreign key to `vehicles` and a generated column with a UNIQUE key that makes the database itself refuse a second primary image per vehicle. "Role" is `is_primary` (PRIMARY / GALLERY). The thumbnail path is derived from the main path (`<id>.webp` and `<id>-t.webp`), so no thumbnail column was needed.

Dependencies: `intervention/image` 3.11.9 (+ `intervention/gif` 4.2.4), approved in the spec; Composer reports no security advisories. `ext-gd` (with WebP) and `ext-fileinfo` are now declared in `composer.json`. `browser-image-compression` 2.0.2 (MIT) is copied unmodified into `assets/vendor/` (the admin Content-Security-Policy allows only same-origin scripts; no npm or build step was introduced). Production must run `composer install --no-dev` (or upload `vendor/`).
Dev machine change (outside the project, announced here): the dev `php.ini` next to `php.exe` now also enables `fileinfo`, `gd`, `exif` and sets `upload_max_filesize=10M`, `post_max_size=64M`, `max_file_uploads=20`, `output_buffering=4096` (a backup of the previous file is next to it, `php.ini.bak-before-spec07`). The running server must be restarted.

Image processing: accepted JPEG, PNG, WebP only (decided from the file's real content with `finfo` + the image header, never from the name, extension or browser-supplied type). Limits: 10 MB per file (checked first), at least 800 x 600 pixels in either orientation, at most 12000 pixels per side and 20 megapixels in total (decided from the header BEFORE decoding, so a tiny file claiming huge dimensions is refused without being expanded), animated WebP refused, 10 files per request, 64 MB per request. Processing (Intervention Image + GD): EXIF orientation applied, scaled DOWN to fit 1600 x 1600 (never up, aspect ratio kept), WebP quality 78 stepping to 68 then 58 only if the result is over about 700 KB, plus a 640 px WebP thumbnail at quality 70. Re-encoding discards all metadata (EXIF, GPS), and a real image carrying a PHP payload comes out as a clean WebP. The original upload is not kept. Storage: `uploads/vehicles/<32 hex random>.webp` and `-t.webp`, created with exclusive-create so a name can never overwrite a file; deletion accepts only that exact shape; `uploads/.htaccess` (existing) disables script execution.

Behaviour: upload one or many images, each judged independently, with a per-file result list (Uploaded / Not uploaded + reason) and a success/warning summary; the first image of a vehicle becomes its primary image and the message says so; set-as-primary (transaction on the locked vehicle row, old primary demoted); gallery order by Move up / Move down (each button posts the COMPLETE new order, which the server validates against the vehicle's own image ids; no drag-and-drop, so no separate accessible alternative is needed); editable alt text (empty = the vehicle's own label is used, nothing is invented); delete with a confirmation page, an extra warning for the primary image and an explicit, pre-ticked choice to promote the next image (the one after it, or before it when it is last; clearing the box leaves the vehicle with "Primary image not set", which is stated in words); the record is deleted first, then both files (a record whose file is already missing is still removed and the condition is logged and audited); the gap in the order is closed. Browser side (progressive enhancement, not a security control): preview, type/count/size hints, and compression of files over 1.5 MB with browser-image-compression (no web worker, because the CSP forbids blob workers); with JavaScript off the form still uploads natively.

Security: requireAdmin() first on every page; permission `vehicles.manage`; CSRF on upload, set-primary, alt text, reorder and delete (failures return 400, are audited as CSRF_FAILURE); POST only for every change (GET to the upload/update endpoints returns 405; the delete URL only shows the confirmation); every image is reached only through its vehicle (vehicle id AND image id in every query, so knowing an image id is not enough: IDOR tested in both directions for primary, alt text, reorder and delete); `is_uploaded_file()` enforced, so the browser cannot name a server path; file names are random and the original name is only escaped metadata (a slash ends the base name, control characters removed, 100 characters); no path, file name or other user input ever reaches the filesystem or a SQL string; throttling: 10 files per request and 60 upload attempts per administrator per 10 minutes (counted from the audit table); failed saves remove the files already written; decoding runs with a raised memory limit only for the duration of one image; user-facing messages are the fixed sentences from the spec, technical detail only in the server log; audit events VEHICLE_IMAGE_UPLOADED, VEHICLE_IMAGE_UPLOAD_REJECTED (reason code only), VEHICLE_IMAGE_PRIMARY_CHANGED, VEHICLE_IMAGE_REORDERED, VEHICLE_IMAGE_DELETED, plus VEHICLE_IMAGE_ALT_UPDATED and VEHICLE_IMAGE_FILE_MISSING (ids and counts only: no file names, no secrets). The Spec 06 rule is unchanged: a vehicle that has images still cannot be deleted.

Verification (HTTP and browser suites against a throwaway database `vip_e2e_test`, since dropped, using real multipart uploads into `uploads/vehicles` and removing every file afterwards; the logic suite in a rolled-back transaction with a temporary image folder; all images are generated with GD, nothing personal or committed):
* `database/tests/vehicle_image_test.php`: 156/156 (validator against real JPEG/PNG/WebP and 12 hostile files; dimension and size limits; processing: WebP output, ratio, no upscaling, portrait, transparency, EXIF rotation, metadata removal, PHP-payload image, random names, safe path deletion; service: relationship, order, primary rules and the database's unique key, alt text, reorder with 12 malformed or foreign lists, delete incl. primary and missing file, IDOR, mixed uploads, cleanup after a failed save, throttling, controller refusal and error containment).
* HTTP end-to-end: 148/148 (signed-out access; JPEG/PNG/WebP uploads; stored files verified as real WebP and served; mixed and over-limit uploads incl. 11 files and a 69 MB request; script, double-extension, .htaccess, GIF/PHP polyglot, SVG, HTML, PDF, EXE, corrupt, 20000 x 20000 and 25 MP files all rejected; CSRF missing/wrong/foreign on every action; GET refused; IDOR; SQL injection; path traversal fields ignored; XSS in alt text and file names escaped on every page; audit rows; throttle; regression incl. dashboard showing the primary image).
* Real Chrome: 95/95 (empty and full gallery, delete confirmations, not-found pages at 320, 375, 390, 414, 768, 1024, 1280, 1440px: no overflow, console errors or CSP violations; an 8.5 MB photo reduced in the browser with the self-hosted library under the CSP; previews; client hints; mixed upload report; primary/move/alt/delete flows; keyboard order and focus; JavaScript disabled). Screenshots reviewed. One layout bug found and fixed (lazy-loading image placeholders made cards taller and the page wider).
* Regression: vehicle logic 246/246, brand logic 167/167, authentication 64/64, 2FA and reset 111/111, dashboard 44/44, Brand browser 120/120; Vehicle HTTP 205/206 and Vehicle browser 132/134 (the misses are checks for the old "managed separately" placeholder, replaced on purpose, plus one idle-time font-preload warning, Known Issue 25); Brand HTTP 169/170 (a scratch check that used Vehicles as its placeholder example); `db_test.php` 81/83 (Known Issue 23).
* NOT tested: Firefox/Safari, real touch devices, screen readers; Imagick; real cPanel/Apache (`.htaccess` and `.user.ini` effects) or nginx; very large galleries; two administrators changing one gallery at once; an image-processing failure from a truly exhausted server memory.

**Upload failure diagnosed and hardened (owner report, 2026-10-08).** Symptom: uploading a vehicle image showed a generic "Something went wrong" page. Root cause (proved, not assumed): the PHP server the owner was running on `localhost:8000` had been started BEFORE the dev `php.ini` was changed for Spec 07, so that process had no `gd` and no `fileinfo` (a temporary diagnostic page on the live server showed gd=false, fileinfo=false, finfo_open missing, upload_max_filesize=2M, post_max_size=8M). The upload therefore died with `Error: Call to undefined function finfo_open()`, which the controller's catch-all turned into the generic 500 page. The same upload against a freshly started server (new ini) succeeds. Nothing was wrong with the form field name (`images[]`), `enctype`, CSRF, authorization, the vehicle id, Intervention Image v3 usage, GD WebP, the temp or final directories (both writable), Windows paths, MIME checks or the database insert (each exercised by the 155 HTTP and 98 browser checks). Fix: a server that lacks a component now says so plainly instead of failing (`ImageService::missingRequirements()` is checked before any upload is touched; the images page shows a warning and disables the upload controls; an upload sent anyway returns a redirect with "Image uploads are not available on this server yet: the PHP component(s) gd, fileinfo are not enabled. Enable them in php.ini and restart the PHP server"); one file's unexpected error no longer turns the whole request into an error page; PHP upload error codes map to clear messages (including "too large for the server configuration" when PHP's limit is below the application's 10 MB); the page shows the real server limit, and the browser refuses to send a selection whose total exceeds `post_max_size` (PHP would otherwise discard the request and, with display_errors on, print its own warning before the app can answer); in development only (APP_ENV=development) the error page adds a one-line "Development detail" with the exception class and message with file paths removed (production stays generic; the full text is always in the server log); the upload, update and delete endpoints use `ignore_user_abort(true)` and the upload endpoint allows up to 180 s, so a disconnect or a slow host cannot stop a multi-step change half way. Files changed: `app/services/ImageService.php`, `app/validation/ImageValidator.php`, `app/services/VehicleImageService.php`, `app/controllers/VehicleImageController.php`, `admin/vehicle-images.php`, `admin/vehicle-image-upload.php`, `admin/vehicle-image-update.php`, `admin/vehicle-image-delete.php`, `includes/admin/vehicle_problem.php`, `assets/js/vehicle-images.js`, `database/tests/vehicle_image_test.php`. No database, Composer or security-control change (CSRF, authorization, content-based MIME checks, random file names, execution block all unchanged). Re-verified: image logic 164/164, image HTTP 155/155 (now including a server started with the OLD ini), image browser 98/98 (twice in a row, no orphan files), vehicle logic 246/246, brand logic 167/167, dashboard 44/44, authentication 64/64, 2FA + reset logic 111/111 and 2FA/reset/login HTTP 149/149. NOT reproduced: once, a browser run left two image pairs on disk with no database record; two further full runs and a deliberate disconnect-mid-upload test (with and without the `ignore_user_abort` hardening) produced none, so the cause is unknown and the orphan check stays in the test suite. **The owner still has to restart the PHP server** (stop the `php -S localhost:8000` window and start it again) for gd, fileinfo, exif and the upload limits to take effect.

### Spec 08 — Team Management (`08_spec_team.md`) — `IMPLEMENTED` 2026-10-08

Flow: pages (`admin/team.php`, `team-create.php`, `team-edit.php`, `team-view.php`, `team-status.php`, `team-order.php`, `team-photo.php`, `team-delete.php`) -> `TeamController` -> `TeamValidator` + `TeamService` -> `TeamRepository` -> PDO -> MySQL; photos go through the SAME image pipeline as vehicle images (`ImageValidator` -> `ImageService` kind "team"), not a second one. No SQL in templates; every query is a prepared statement.

Files created: `app/controllers/TeamController.php`, `app/services/TeamService.php`, `app/repositories/TeamRepository.php`, `app/validation/TeamValidator.php`, `app/helpers/diagnostics.php` (the development-only error detail, shared); pages listed above; `includes/admin/team_form.php`, `includes/admin/team_problem.php`; `database/migrations/002_team_category.sql`; `database/tests/team_test.php`.
Files modified: `app/services/ImageService.php` (storage/size profiles: vehicles unchanged, "team" added: `uploads/team`, main image fits 1200 x 1600, thumbnail 400 x 533; the default behaviour and every vehicle test are unchanged; unused constants removed), `app/validation/ImageValidator.php` (optional minimum-size arguments; the vehicle wording and 800 x 600 default are unchanged), `app/controllers/VehicleImageController.php` (its development-detail helper now delegates to the shared one), `app/helpers/admin_ui.php` (Team nav is a real link, `admin_team_photo_url`), `assets/css/admin.css` (section 14), `database/schema.sql` (category column).
Reused unchanged: `assets/js/vehicle-images.js` and the self-hosted `browser-image-compression` (preview, client checks, browser-side shrinking; with `data-max-files="1"`), `uploads/.htaccess` (script execution blocked), `uploads/team/` (already existed).

**Database change (the only one): migration 002, applied to the real database.** The `team_members` table had NO category column, but the spec requires a controlled category and a category filter, and `Context/02_architecture.md` lists "Category/group" for team data. Added `category ENUM('leadership','investor_partner','management','sales','operations','other') NOT NULL DEFAULT 'other'` after `position`. Safe: existing rows keep working (default 'other'; the table had 0 rows when applied); no index added (a handful of rows; the existing (status, display_order) index already serves the public ordering); rollback = `ALTER TABLE team_members DROP COLUMN category;`. `schema.sql` is updated and was proven by loading it into a fresh database (columns identical to the migrated table). The other columns used: name, position, biography, photo_path, display_order, status. Production must run the same migration. Category display names: CEO / Leadership, Investor / Partner, Management, Sales, Operations, Other.

Behaviour: list with a small thumbnail per member (never the full image), search (name, position), filters (category, status), deterministic order (display_order, name, id), no fake members or photos (a member without a photo shows an empty icon frame, "No photo"); add / edit / view; activate / deactivate (explicit target, idempotent, the member is kept); ordering by Move up / Move down buttons that each post the COMPLETE new order of all members (only shown when no search or filter is active; no drag-and-drop, so no separate accessible alternative is needed) plus the editable display-order field; delete with a confirmation page ("Delete <name>? ... cannot be undone"), the record removed first, then the photo files; photo: optional on create, kept when an edit does not choose a new one, replaced (new file written first, database updated, OLD file removed last; a failed validation, failed processing or failed save leaves the old photo untouched and the new file removed) or removed (POST). Validation: name and position required (150), category from the list, biography optional plain text (1500), display order 0 to 9999, status; photo minimum 400 x 400 pixels (the vehicle rule of 800 x 600 does not suit portraits and small squares; the owner may change this), 10 MB, JPEG/PNG/WebP by real content. The query for the future public page exists and is tested (`TeamService::publicMembers()`: active members in display order with id, name, position, category, biography, photo, display order, status); no public page was built.

Security: requireAdmin() first on every page; permission `team.manage` (administrator holds all); CSRF on create, edit, status, reorder, photo removal and delete (failures return 400, keep typed values on forms, audited as CSRF_FAILURE); all state changes POST only (GET to status/order/photo returns 405; the delete URL only shows the confirmation); ids validated then looked up (tampered or unknown ids = safe 404); reorder lists must contain exactly the existing ids once each; redirects rebuilt from validated values only; photos judged by real content (finfo + image header), never by name/extension/browser type, re-encoded to WebP with a random name (so a real image carrying PHP code is stored clean), exclusive file creation, deletion accepts only the generated path shape (a tampered `photo_path` can never delete another file: tested with `../../.env`); all output escaped; audit events TEAM_MEMBER_CREATED, _UPDATED, _DELETED, _ACTIVATED, _DEACTIVATED, _REORDERED, _PHOTO_UPLOADED, _PHOTO_REPLACED, _PHOTO_REMOVED and _PHOTO_REJECTED (reason code only), ids and the member's name only (no file names, no secrets); technical errors only in the server log (development only: a path-free one-line detail on the error page); a server without gd/fileinfo says so in words and still lets members be created without a photo. Not added: rate limiting on these endpoints (authenticated, CSRF-protected).

Verification (HTTP and browser suites against a throwaway database `vip_e2e_test`, since dropped, with real multipart uploads into `uploads/team`; the logic suite in a rolled-back transaction with a temporary image folder; all images generated with GD, nothing personal):
* `database/tests/team_test.php`: 188/188 (validator incl. international names; the column; create/edit/status/reorder/delete rules; photo upload, 11 hostile files, scaling and thumbnails, EXIF-free WebP, replacement order of operations incl. failures at validation, processing and save; removal; search/filter/order and injection strings; public query; audit rows; controller refusal and error containment).
* HTTP end-to-end: 186/186 (signed-out access; every create/edit validation case; JPEG/PNG/WebP photos stored as real WebP and served; 14 rejected photo types including script, double extension, .htaccess, polyglot, SVG, GIF, PDF, EXE, corrupt, bomb, too small, oversized; request over the server total; keep/replace/remove photo; CSRF missing/wrong/foreign on every action; GET refused; reorder with 8 malformed or foreign lists; search/filter combinations and injection strings; XSS on every page; delete with files; audit rows; a server started without gd/fileinfo; regression). The owner's real team photo was verified byte-for-byte unchanged.
* Real Chrome: 143/143 (every screen at 320, 375, 390, 414, 768, 1024, 1280, 1440px: no overflow, console errors or CSP violations, incl. very long names and titles; browser-side shrinking of an 8.5 MB photo; previews; client checks; create/replace/remove photo; search and filters; move up/down; status; delete; keyboard order and focus; mobile menu; JavaScript disabled). Screenshots reviewed; one spacing defect found and fixed.
* Regression: vehicle image logic 164/164, vehicle logic 246/246, brand logic 167/167, dashboard 44/44, authentication 64/64, 2FA + reset 111/111; HTTP: vehicle images 155/155, 2FA/reset/login 149/149, vehicles 205/206 and brands 169/170 (each miss is a scratch check for an older placeholder: superseded on purpose); `db_test.php` 80/83 (Known Issue 23).
* NOT tested: Firefox/Safari, real touch devices, screen readers, MariaDB, real hosting (`.htaccess`/`.user.ini`), two administrators editing at once, a large team.
* Real data note: the owner created a real team member with a photo (id 7, uploaded through the new pages while this work was in progress). It was left untouched; no test row or file remains.

### Spec 09 — Public Website Foundation & Global UI (`09_spec_webpage_ui.md`) — `IMPLEMENTED` 2026-10-08

Scope kept to the foundation only: no homepage, vehicle, brand, how-it-works, about, team, contact, FAQ, tracking or enquiry pages (the homepage is Spec 10).

Architecture: root `index.php` -> `public/index.php` (front controller) -> `PublicController` -> `PublicRouter` (pure; route table in `app/config/public_routes.php`) -> layout (`includes/header.php`, `navigation.php`, `footer.php`) + view in `public/views/`. Presentation helpers in `app/helpers/public_ui.php`. `.htaccess` sends anything that is not an existing file or folder to the front controller (`/admin/`, `/assets/`, `/uploads/` untouched). The admin and public layers share no markup or styles. No database access, no sessions or cookies on public pages.

Routes: only GET/HEAD (else 405 + Allow); path limited to letters, digits, hyphens, slashes (max 200); lowercase + trailing slash enforced by a 301 only when the canonical URL is a real route; "//host" paths rejected. Pages that later specifications build (home, vehicles, brands, how-it-works, track, about, team, contact, faq, brand/vehicle slug URLs) are `planned`: they show a neutral "not available yet" page (HTTP 200, `noindex,follow`) so no header/footer link is ever broken. Slugs are validated, never echoed or queried. Development-only `/styleguide/` (component library) and `/styleguide/error/` (500 preview) are 404 in production.

Design system: tokens from the spec palette (#111315, #F8F7F4, #FFFFFF, #62666A, #E3E3DF, accent #9E2F2F), Manrope (self-hosted, same URL in preload and @font-face), fluid type with clamp(), spacing scale, radii 6-10px, max width 1240px, buttons >= 44px (primary/accent/secondary/disabled), forms with hint/error states, alerts (colour never the only signal), empty state, page header, inline SVG icons, `:focus-visible`, skip link, `prefers-reduced-motion`. Header: wordmark text (no logo asset exists), Vehicles, How It Works, Track Vehicle, About, Contact, CTA "Browse Vehicles"; full-screen mobile menu (aria-expanded, focus moved/trapped/restored, Escape, inert background, scroll lock, resets on resize). Footer: Company / Vehicles / Support groups and copyright only; no phone, e-mail, address or social links (none approved or stored; nothing invented).

SEO: title with site name, optional description (never invented), robots, canonical and Open Graph built from the configured APP_URL (never the Host header), image path validated. Responsive `public_image()` helper (srcset, width/height, lazy/priority, alt) accepts only `uploads/vehicles|team/<32 hex>[-t].webp`.

Security: CSP (`default-src 'none'`, same-origin script/style/font/img only; no inline script, style or handlers), nosniff, X-Frame-Options DENY, Referrer-Policy; output escaped; errors logged server-side and shown as a clean 500 (path-free detail only in development).

Performance: one CSS file (~10 KB) and one JS file (~3 KB) uncompressed, deferred JS, one font file (25 KB), no framework, no icon library, no external request, HTML ~5 KB.

Files created: `index.php`, `.htaccess`, `app/config/public_routes.php`, `app/services/PublicRouter.php`, `app/controllers/PublicController.php`, `app/helpers/public_ui.php`, `public/views/{placeholder,not-found,error,styleguide}.php`, `assets/css/public.css`, `assets/css/public-nojs.css`, `assets/js/public.js`, `database/tests/public_site_test.php`.
Files modified: `public/index.php`, `includes/header.php`, `includes/navigation.php`, `includes/footer.php` (all were empty placeholders). Admin files were not touched. Dependencies added: none. Database changes: none.

Verification:
* `database/tests/public_site_test.php`: 61/61 (router incl. hostile paths, route table, header/footer link integrity, metadata escaping, image helper, alerts). One bug found and fixed by it: a request URI starting with "//" was parsed as host + path and served the home page.
* HTTP + real browser (scratch script, no database), 116/116 in Chrome and 116/116 in Edge: every route; headers; 301/404/405/HEAD; hostile paths; production hides the styleguide; Host-header spoof does not change the canonical URL; no inline script/style/handlers; no admin links or secrets in HTML; crawl of 16 internal URLs with none broken; 8 viewports (320, 375, 390, 414, 768, 1024, 1280, 1440) on home, a placeholder page, 404 and the component library with no overflow and a clean console; menu open/close/Escape/focus/trap/inert/scroll-lock/resize; keyboard order and visible focus; JavaScript disabled; reduced motion; WCAG AA contrast of every palette pairing.
* Regression: logic suites team 188, vehicle image 164, vehicle 246, brand 167, dashboard 44, auth 64, 2FA + reset 111 (all pass); HTTP: image 155/155, team 186/186, brand 169/170, vehicle 205/206, 2FA/login/reset suite 78 passed 0 failed; real Chrome: team 143/143, image 98/98. The 2 failing HTTP checks are old assertions that expect admin placeholders which later specs replaced (not caused by this work). The old `auth_e2e.mjs` fails only because an administrator already exists (superseded by the 2FA suite).
* NOT tested: Firefox and Safari (not installed), real touch devices, screen readers, Lighthouse, real Apache (`.htaccess` rewrite untested; the PHP development server's equivalent fallback was verified), production hosting.

### Spec 10A — Homepage Structure & Hero (`10_spec_homepage_hero.md`) — `IMPLEMENTED` 2026-10-08

Scope kept to the hero only: no Featured Vehicles, Browse by Brand, How It Works, Why Choose Us, Tracking, About, Team preview, Final CTA, FAQ or Contact section.

Homepage route: `/` is now a live route (view `public/views/home.php`) in `app/config/public_routes.php`, with the title "Vehicles Sourced from Canada to Ghana | Mr Amponsah Autos", a description (vehicles, Canada, Ghana) and page-specific `homepage.css` / `homepage.js` (loaded on the homepage only). It reuses the Spec 09 header, navigation, mobile menu and footer unchanged; the CTAs take their URLs from the route table (`/vehicles/`, `/track/`, both still "not available yet" pages until their specifications exist).

Hero: one H1 "Find Your Next Vehicle.", "Quality vehicles sourced from Canada and delivered to Ghana.", primary CTA Browse Vehicles, secondary CTA Track Your Vehicle. Height about 560-680 px on phones and 650-760 px from 768 px (not 100vh). Flat restrained overlay (rgba 17,19,21,0.58: white text stays above 4.5:1 even on a white photograph); no gradient, video, parallax or other effects. Mobile: headline and buttons at the bottom, buttons stacked (>= 44 px), controls below.

Hero images (important): NO photograph has been approved or supplied, and none was invented or downloaded. Slides are configured in `app/config/homepage_hero.php` and prepared by `app/services/HomepageHeroService.php`: save approved WebP files in `assets/images/hero/` as `hero-n-desktop.webp` (required, about 1600 x 900), `hero-n-mobile.webp` (optional crop, about 800 x 1000) and `hero-n-wide.webp` (optional, about 2200 x 1000), n = 1 to 4, and set each slide's `alt` and `focus` in the config. Only existing, valid WebP files with those exact names become slides (nothing user-controlled reaches the page). With no images the hero is the plain dark panel (headline and CTAs only; no carousel, no controls, no preload). One image gives a static hero.

Carousel (vanilla JS, `assets/js/homepage.js`, 4.9 KB, no library): soft opacity crossfade (0.8 s), rotation every 6 s with a timeout chain (no restart on DOM events), previous / next / pause-play / indicator buttons with accessible names (inline SVG icons), slide 1 always opens (revised: predictable for visitors and search engines; put the strongest image in slot 1) and the order is fixed. An image that fails to load is dropped from the rotation; with fewer than two working images the controls hide and the plain panel / single image stays. Pauses on hover, while focus is inside the controls, while the tab is hidden and when the visitor presses Pause; with `prefers-reduced-motion` it never rotates by itself and the crossfade is instant (manual controls still work). Controls are hidden until JavaScript runs (no dead buttons without it); slides carry carousel/slide roles with "n of N" labels; inactive slides are aria-hidden; live announcements only while paused.

Performance: first slide is a normal `<img>` with `fetchpriority="high"` and a media-conditional `<link rel="preload">` (one per layout, first slide only); other slides use a transparent placeholder and are fetched progressively by JavaScript (next slide at once, the rest 1.5 s apart); `<picture>` serves the mobile crop to phones and desktop/wide `srcset` to larger screens; width and height are read from the real file (no layout shift); homepage CSS 3.6 KB and JS 4.9 KB on top of the Spec 09 files; no new dependency.

Also changed for this: `includes/header.php` (optional per-page CSS/JS lists with strict file-name patterns, and first-image preload links), `app/controllers/PublicController.php` (live routes pass description/css/js; the home route loads the hero), `app/helpers/public_ui.php` (`public_hero_preloads()`, pause/play icons). One bug found while testing: a loop variable in the new header code overwrote the controller's `$file` and broke the page; renamed.

Files created: `app/config/homepage_hero.php`, `app/services/HomepageHeroService.php`, `public/views/home.php`, `assets/css/homepage.css`, `assets/js/homepage.js`, `assets/images/hero/.gitkeep`, `database/tests/homepage_hero_test.php`.
Files modified: `app/config/public_routes.php`, `app/controllers/PublicController.php`, `app/helpers/public_ui.php`, `includes/header.php`. Admin files: not touched. Database changes: none. Dependencies added: none.

Verification:
* `database/tests/homepage_hero_test.php`: 19/19 (service with a throw-away folder: no images, invalid/missing/non-image files skipped, mobile/wide fallbacks, rotation, hostile configuration, max 4 slides, preload helper). `database/tests/public_site_test.php`: 61/61 (still passes).
* Real browser suite `hero_browser` with TEMPORARY test-panel images (flat coloured fixtures that I generated, never real photographs, deleted afterwards; no image file ships): 93/93 in Chrome and 93/93 in Edge: HTTP markup (one H1, copy, CTAs, no inline script/style, no fake claims, no external hosts); 8 widths (320, 375, 390, 414, 768, 1024, 1280, 1440) with no overflow, clean console, CLS 0, height ranges, stacked mobile buttons; first-paint image requests limited to the visible slide and its successor, phones download only mobile crops; auto-rotation after about 6 s; next/previous/dots/wrap; pause/resume; hover and focus pause; hidden tab stops the timer; Enter key; focus rings; tab order; resize while rotating; reduced motion; mobile menu over the hero; JavaScript disabled; fast-3G (FCP and CLS). Same suite with NO images: 55/55 in Chrome and in Edge (plain hero). Spec 09 browser suite updated for the live homepage: 115/115 in both.
* Regression: logic suites team 188, vehicle image 164, vehicle 246, brand 167, dashboard 44, auth 64, 2FA + reset 111 all pass; HTTP: 2FA/login/reset 78/78, team 186/186, image 155/155, brand 169/170 and vehicle 205/206 (the 2 failures are old assertions that expect admin placeholders later replaced, unchanged since Spec 09); real Chrome: team 143/143, image 98/98; Spec 09 public suite 115/115 in Chrome and Edge.
* NOT tested: Firefox and Safari, real touch devices, screen readers, Lighthouse (no CLI available), a real hero photograph (composition, object-position focus values and file sizes can only be judged with the approved images), a real tab switch (hidden tab was simulated), real Apache.

Revision of 2026-10-08 (owner instructions after the first implementation, applied to the public header, footer and typography; they supersede the Spec 09 header described above):
* Header: white, flat, thin top and bottom borders, no shadow, about 100 px tall on desktop (64 px on phones). Desktop structure follows the supplied reference screenshot: name far left, page links centred (Vehicles, How It Works, About, Contact), Login (simple outline user icon + text, no button) far right. No Track Vehicle item, no Browse Vehicles button in the header (both remain as hero buttons; Track Vehicle remains in the footer). Active page = weight 600, no pill; hover = colour change only (no underline or line of any kind). Navigation weight 400, brand 600; headings 600, buttons 500 (the heaviest public weight is now 600).
* Login goes to the existing administrator sign-in page (`/admin/login.php`); no customer account system exists or was created. It is the only admin link on public pages (Spec 09 had none).
* Mobile: the header is the name + an icon-only menu button (accessible name "Open menu"); the open menu shows an icon-only close button ("Close menu"); items Vehicles, How It Works, About, Contact, Login; no Track Vehicle, no Browse Vehicles, no text beside either icon.
* Footer: optional decorative photograph band (lazy-loaded, alt="", 144-224 px tall, no overlay) from `assets/images/footer/footer.webp` (optional `footer-mobile.webp`); without a valid WebP the footer has no image. Because no approved footer photograph exists, `footer.webp` was generated from the file the owner placed at `assets/images/hero/hero.jpg` (a port with two cargo ships; original left untouched). It is only 700 px wide, so it is upscaled on wide screens, and its provenance/licence is unconfirmed (see Known Issue 30). Replace it with an approved image.
* The site name is still the text "Mr Amponsah Autos": no logo asset exists in the project (searched), so none was used or invented; add the logo file and it replaces the text.
* Files changed by the revision: `app/helpers/public_ui.php` (navigation items, user icon, `public_footer_image()`, `public_cta()` removed), `includes/header.php`, `includes/navigation.php`, `includes/footer.php`, `assets/css/public.css`, `assets/css/public-nojs.css`, `assets/css/homepage.css`, `assets/js/homepage.js`, `app/controllers/PublicController.php`, `app/config/public_routes.php`, `app/config/homepage_hero.php`; created `assets/images/footer/footer.webp`.
* Re-tested after the revision: `public_site_test.php` 62/62, `homepage_hero_test.php` 19/19; public foundation browser suite 126/126 in Chrome and 126/126 in Edge (adds header structure, borders, centred links, far-right Login, no underline/border on hover, icon-only mobile buttons, footer image); hero suite with temporary test panels 95/95 in Chrome and Edge (adds image-failure handling and slide-1-first); without hero images 55/55 in Chrome and Edge (one transient failure of the external-host check in one Chrome run did not repeat in two reruns). Admin regression unchanged and passing: logic suites team 188, vehicle image 164, vehicle 246, brand 167, dashboard 44, auth 64, 2FA + reset 111; HTTP 2FA/login/reset 81/81, team 186/186, image 155/155, brand 169/170, vehicle 205/206 (the two failures are old assertions expecting admin placeholders, unchanged); real Chrome team 143/143, image 98/98.

### Public Design System Change (owner request of 2026-10-14; no spec file) — `IMPLEMENTED` 2026-10-14

Owner decision: use autotrader.com.gh as the visual reference for the public website, adapted to this business. Owner answers: visual tokens only (page layouts and the approved Spec 10A hero kept), red primary buttons, red `#D42A25` (the reference `#E3342F` fails WCAG AA with white text at 4.4:1; `#D42A25` is about 5.0:1), public site only (admin unchanged). The owner then asked for the reference's layout as well; see Open Decisions.

Applied: palette (ink `#2B2F35`, white page, muted `#6B7280`, border `#E5E7EB`, tint `#F8F9FA`, accent `#D42A25`, neutral `#6C757D`); Nunito replaces Manrope on the public site (self-hosted `assets/fonts/nunito-latin-wght.woff2`, SIL OFL, `OFL-Nunito.txt`; Manrope kept for the admin); type scale H1 32px/700, H2 22px/500, H3 20px/500, body 15px/24px; radii 4px controls / 8px cards; flat (account card shadow removed); button system `.btn--primary` red, `.btn--secondary` grey, new `.btn--outline`; current nav item in red; hero and inner-page photo overlays recoloured from burgundy to charcoal; hero search panel is now a dark charcoal panel with white fields and a red Search button; section headings that bypassed the tokens (How It Works, utilities, About) normalised.

Files modified: `assets/css/public.css`, `homepage.css`, `vehicles.css`, `account.css`, `about.css`, `utilities.css`, `how-it-works.css`, `brand-strip.css`, `track.css` (radius tokens only), `includes/header.php` (font preload, theme-color), `public/views/styleguide.php`, `Context/03_design_ui _rules.md` (sections 2, 6, 8, 13, 16 updated; previous palette recorded as superseded). Files created: `assets/fonts/nunito-latin-wght.woff2`, `assets/fonts/OFL-Nunito.txt`. No database change, no PHP logic change, no new package.

Verification: real Chrome via DevTools Protocol, 9 public pages (home, vehicles, login, track, about, how-it-works, contact, styleguide, 404) at 320/390/768/1024/1440px: 45/45 pass (no horizontal overflow, Nunito loaded, no console errors or CSP violations); screenshots reviewed. Regression: public_site 62/62, homepage_hero 24/24, brand_strip 73/73, public_vehicles 58/58, public_team 24/24, customer_auth 34/34, external_links 30/30, company_contact 33/33. NOT tested: Firefox/Safari, real devices, screen readers; the admin was not re-tested (admin.css untouched). Not changed: the password-reset email template in `app/services/EmailService.php` still uses the old palette inline.

Layout follow-up, same day (owner chose: dark utility bar, Latest Vehicles section, centred section headings; NOT Browse by Body Type, which would need a body-type field):
* Utility bar (`includes/utility_bar.php`, included by `includes/header.php`): thin charcoal band, desktop (62em+) only, with the company phone, the approved Duty Calculator link (external, new tab, from the validated `duty_calculator_url` setting via `PublicController::render`) and Login/Account. On desktop the phone and user icon leave the white header; on phones nothing changes (they stay in the menu). Track Vehicle stays in the main navigation (not duplicated).
* Latest Vehicles = **Spec 10B** (`Context/specs/10B-spec_feat_vehicles.md`), placed directly below the hero: `PublicVehicleService::latest()` (the first page of the unfiltered public inventory: same visibility rule, same two queries, no new SQL), `PublicController::latestVehicles()` (logs and returns null on failure), `includes/latest_vehicles.php` reusing the shared `includes/vehicle_card.php`; 1/2/4 columns; "View All Vehicles"; "Vehicles Coming Soon" empty state; friendly note when the database is unavailable. Spec 10B's own colour/Manrope sections are superseded by the 2026-10-14 design system; its card is the bordered card of the Vehicles page (owner chose the reference's flat bordered cards) rather than the borderless editorial card.
* Centred section headings: new shared `.section-head` in `public.css`; Browse by Brand, How It Works (also its CTA) and the utilities section centred.
* Logo: the owner's file `assets/images/Next Gen Automotive Gear Logo.png` (1774x887, 684 KB, transparent) kept untouched; a trimmed web copy `assets/images/logo.webp` (275x96, 9.8 KB) is what the header and mobile menu use (40px tall on phones, 52px on desktop). The empty placeholder `assets/images/logo.svg` was removed (it would otherwise have been picked first). Not used on dark backgrounds (footer, admin sidebar, auth band): its black lettering would disappear there; those keep the text name.
* Verification: 15 scratch checks (latest limit/visibility/order, no id leaked, lazy photos, empty and database-down states, escaping of hostile stored values, utility-bar content, signed-in state, escaping); `php -l` on the four changed PHP files; browser 45/45 again (9 pages x 5 widths); screenshots reviewed at 390 and 1440; regression 338/338 as above.

### Required output

* finalized specification list
* dependency order
* specification priorities
* launch-critical vs secondary features
* acceptance criteria for each specification

---

# 9. What Is Next

The next project actions are:

### Step 1 — Finalize specification backlog

Define every implementation specification and its dependencies.

### Step 2 — Write Specification 01

Create the first implementation specification.

### Step 3 — Review Specification 01

Verify that it agrees with:

* project context
* business rules
* architecture
* design system
* security rules
* AI development rules

### Step 4 — Give Specification 01 to Claude Code

Claude Code implements only Specification 01.

### Step 5 — Test Specification 01

Verify:

* functional requirements
* security
* responsive behavior
* errors
* acceptance criteria

### Step 6 — Mark Specification 01

Only after successful verification:

```text
PLANNED
→ IN PROGRESS
→ IMPLEMENTED
→ TESTING
→ PASSED
→ COMPLETED
```

### Step 7 — Move to Specification 02

Repeat the same process.

---

# 10. Feature Completion Rule

A feature is **not completed** simply because:

* the code exists
* the page opens
* the form submits
* Claude says it is finished

A feature becomes `COMPLETED` only when:

1. requirements are implemented
2. acceptance criteria are satisfied
3. security requirements are verified
4. responsive behavior is checked
5. error states are checked
6. relevant regression testing is completed
7. no known critical issues remain

---

# 11. Overall Project Success Criteria

The project will be considered successfully completed when all launch-critical requirements are satisfied.

## Business

* [ ] Customers can understand what the company does
* [ ] Customers can browse available vehicles
* [ ] Vehicle information is accurate and complete
* [ ] Customers can understand shipping information
* [ ] Customers can access the approved duty calculator
* [ ] Customers can understand the tracking process
* [ ] Customers can access external shipment tracking
* [ ] Customers can contact the company
* [ ] Company/team information builds trust
* [ ] Admin can manage the information shown publicly

## Vehicle Inventory

* [ ] All approved vehicle brands can be represented
* [ ] Vehicles contain required specifications
* [ ] Vehicle images work correctly
* [ ] Vehicle pricing works correctly
* [ ] Availability status works
* [ ] Vehicle detail pages work
* [ ] Search/filter works where specified

## Shipping

* [ ] Shipment records can be managed
* [ ] Real container numbers can be recorded
* [ ] Container numbers are never fabricated
* [ ] Customers can access the approved tracking provider
* [ ] Tracking-unavailable states are handled correctly

## Administration

* [ ] Admin authentication works
* [ ] Admin authorization works
* [ ] Vehicle management works
* [ ] Image management works
* [ ] Shipment management works
* [ ] Enquiries can be managed
* [ ] Team information can be managed
* [ ] Content can be managed where specified

## Security

* [ ] Passwords are securely hashed
* [ ] Sessions are protected
* [ ] CSRF protection is implemented
* [ ] SQL injection protection is implemented
* [ ] XSS protection is implemented
* [ ] Upload security is implemented
* [ ] Authorization checks are enforced
* [ ] Secrets are protected
* [ ] Production errors do not expose sensitive information
* [ ] Security logging is implemented where required

## UX

* [ ] Mobile experience is intentionally designed
* [ ] Desktop experience is polished
* [ ] Navigation is clear
* [ ] Forms are understandable
* [ ] Errors are clear
* [ ] Loading states are appropriate
* [ ] Empty states are handled
* [ ] Accessibility has been reviewed
* [ ] Visual system is consistent

## Performance

* [ ] Vehicle images are optimized
* [ ] Database queries are reasonable
* [ ] Pages load efficiently
* [ ] Unnecessary dependencies are avoided
* [ ] Unnecessary external requests are avoided

## Deployment

* [ ] Production configuration is secure
* [ ] HTTPS is enabled
* [ ] Database is configured
* [ ] Email integration works
* [ ] File uploads work
* [ ] Backups are configured
* [ ] Production smoke testing passes

---

# 12. Launch Blockers

The following should prevent production launch until resolved:

* critical security vulnerability
* broken admin authentication
* unauthorized access to protected functionality
* exposed credentials
* broken database integrity
* fabricated vehicle/tracking information
* broken customer enquiry workflow
* broken production email workflow where required
* major mobile usability failure
* major data-loss risk
* failed critical acceptance criteria

---

# 13. Known Decisions

| Decision                                | Status   |
| --------------------------------------- | -------- |
| PHP backend                             | Locked   |
| MySQL database                          | Locked   |
| PDO database access                     | Locked   |
| Server-rendered public pages            | Locked   |
| Vanilla JavaScript                      | Locked   |
| cPanel/shared hosting                   | Locked   |
| Brevo email                             | Locked   |
| External duty calculator                | Locked   |
| External shipment tracking              | Locked   |
| No automatic carrier tracking           | Locked   |
| No internal duty calculation            | Locked   |
| Mobile-first design                     | Locked   |
| No purple UI                            | Locked   |
| No emoji UI                             | Locked   |
| Public visual system referenced from autotrader.com.gh (palette, Nunito, flat, accent `#D42A25`); admin unchanged | Approved (owner, 2026-10-14) |
| Specification-driven development        | Locked   |
| Claude Code as AI development assistant | Approved |

---

# 14. Open Decisions

Only decisions that genuinely remain unresolved should be listed here.

| Decision                                      | Status | Required Before          |
| --------------------------------------------- | ------ | ------------------------ |
| Final CSS approach: Bootstrap 5 vs custom CSS | `OPEN` (admin uses custom CSS since spec 03A; confirm for the public site) | Public UI implementation |
| Exact production hosting configuration        | `OPEN` | Deployment               |
| Final production domain                       | `OPEN` | Deployment               |
| Final company branding assets                 | `OPEN` | Public UI implementation |
| Business name: the supplied logo reads "NEXT GEN — Autos and Parts" while the site name everywhere (title, alt text, footer, copy) is "Mr Amponsah Autos". Which is the trading name? | `OPEN` (2026-10-14) | Launch |
| Browse by Body Type (needs a `body_type` vehicle field + admin form) | `OPEN` (not chosen on 2026-10-14) | That section |
| Should the admin interface and the password-reset email adopt the new palette and Nunito? | `OPEN` | Admin restyle |
| Final company/team information                | `OPEN` | Content implementation   |
| Shipping / tracking workflow: the company's actual shipping and tracking workflow must be clarified before Shipment Management and Tracking are specified (both are `DEFERRED`, not removed) | `OPEN` (owner) | Shipment Management and Tracking |
| Spec numbering: the owner's specification files now run 07 Vehicle Image Management, 08 Team Management, 09 Enquiries, which differs from the numbering in section 6 of this tracker (8 catalogue, 11 shipments, 15 team). The tracker keeps its own row numbers and names the owner's spec number in each row | `OPEN` (owner may want the table renumbered) | Housekeeping |

Do not fill this section with decisions that have already been made.

---

# 15. Blockers

**Current blockers:** None.

## Known Issues (open)

| # | Issue | Severity | Action needed |
| - | ----- | -------- | ------------- |
| 1 | `.env.example` still contains the real local database user (`root`) and password (re-checked 2026-10-07 after the dashboard work). The file is NOT git-ignored (`!.env.example`), so it would be committed. The repository has no commits yet, so nothing has leaked. | High before first commit | Owner: restore `.env.example` to blank placeholders (real values belong only in `.env`, which is ignored). Consider changing the password if it was shared. |
| 6 | RESOLVED 2026-10-07 (Spec 03B): admin pages now require sign-in. | Resolved | Review pending. Future modules must call `requireAdmin()` first (and a permission check once roles are split). |
| 7 | Dashboard "recent" lists order by id (creation order). If records are ever imported with back-dated `created_at`, "recent" means most recently inserted, not most recently dated. | Low | Revisit if bulk import is ever added. |
| 8 | Dashboard statistics are live `COUNT(*)` queries per page load (no caching, by design). Only tested with small data. | Low | Re-check query performance if vehicle/enquiry volume grows large. |
| 2 | Local dev connects as MySQL `root`. The spec requires a least-privilege database user. | Medium | Create a limited user before any shared/production use. |
| 3 | Admin asset/link paths are relative to the page (`../assets`) and assume admin pages sit directly in `/admin`. Deeper pages must set `$adminRoot`. Base URL/config belongs to the not-yet-written Application Foundation spec. | Low | Revisit with Specification 01. |
| 4 | `admin/ui-kit.php` is a development page. It now requires sign-in and 404s when `APP_ENV=production` (read through `AppConfig`, so `.env` counts). | Low | Still remove it before launch if no longer needed. |
| 9 | **`.env` (database password) is downloadable from the web root.** On the PHP dev server `GET /.env` returns HTTP 200 because the project root is the document root; the same is true on cPanel if the document root is the project root. The same applies to `database/*.sql`, `app/` source and `Context/`. The architecture has not yet fixed what the web root is. | **Launch blocker** | RECOMMENDATION (not implemented, outside Spec 03B): decide the web root (serve only `public/`, `admin/`, `assets/`, `uploads/` and keep the rest outside it) or add server rules denying dotfiles, `.sql` and non-public folders. Needs an approved deployment/foundation spec. |
| 10 | The interactive password prompt (hidden console input, or the masked password window when there is no console) of `database/create_admin.php` could not be tested here (no person at a keyboard). The `--password-stdin` mode and the documented PowerShell recipe ARE tested. | Low | Owner: run `php database/create_admin.php` once and confirm the password is not echoed. |
| 11 | The schema default for `admin_users.role` is `'admin'` but the authorization role is `'administrator'` (set by the setup script). A row inserted by hand with the default role has NO access (deliberately not aliased). | Low | Align the schema default in a future schema revision. |
| 12 | Client IP is `REMOTE_ADDR` only. Behind a reverse proxy/CDN all visitors could share one IP (and one throttle bucket). | Low | Revisit at deployment when the hosting topology is known. |
| 13 | Pretty URLs (`/admin/login`, `/admin/logout`) are not routed: the pages are `login.php`, `logout.php`. | Low | Decide with the routing/foundation spec. |
| 14 | Throttle fingerprints of emails are an unsalted truncated SHA-256 in `audit_logs.entity_id` (no raw email stored). | Low | Acceptable for now; key it with an application secret if a stronger privacy stance is required. |
| 15 | **Reset tokens appear in the web server's ACCESS log.** An emailed reset link is a GET with `?token=...`, so Apache/nginx/`php -S` access logs record it (21 such lines in the test run). The application's own logs, the audit log and error logs never contain tokens. Mitigations in place: single use, 30-minute expiry, only the hash is stored, the token leaves the URL immediately. | Medium | RECOMMENDATION (not implemented): restrict access to web-server logs; optionally move the token into a URL fragment read by JavaScript and POSTed (keeps it out of server logs, but the reset page would then need JavaScript). Needs a decision. |
| 16 | **Real email delivery through Brevo is untested.** Verified only against a local mock (request shape, headers, content, link). Until `BREVO_API_KEY`, `MAIL_FROM_EMAIL` and `APP_URL` are set, "Forgot password?" says email is not configured. The sender address must also be verified inside Brevo. | Medium | Owner: create the Brevo account/key, set the three variables, send a real reset to a real inbox. |
| 17 | **`APP_KEY` must be backed up.** It encrypts every 2FA secret and keys the recovery-code hashes. If it is lost or changed, existing 2FA enrollments stop working and those administrators must be helped to set 2FA up again (no automatic recovery exists). A key is already generated in the local `.env`; production needs its own, stored outside Git. | Medium | Owner: back up the production key securely when it is created (`php database/generate_app_key.php`). |
| 18 | There is no command-line way to reset an administrator's password or turn off a locked-out administrator's 2FA (not requested). If the only administrator loses both the authenticator and the recovery codes, recovery needs direct database work. | Medium | RECOMMENDATION (not implemented): a small CLI-only recovery script, plus advising at least two administrators in production. Needs a specification. |
| 19 | The running dev server must be RESTARTED to pick up the PHP extensions enabled for this work (`sodium`, `openssl`, `curl`, `mbstring`, `zip`). A server started earlier still works (verified) but shows 2FA/email as unavailable. Composer was installed only in a scratch folder (not in the project or on PATH); `vendor/` is git-ignored and `/vendor`, `composer.json` and `composer.lock` are served from the web root like `.env` (see Known Issue 9). | Low | Restart `php -S`. Install Composer yourself if you want to update packages. |
| 20 | A person who knows an administrator's password can make that administrator's second-factor step unusable for up to 15 minutes by entering 5 wrong codes (the per-administrator limit). A temporary denial of service only; it grants nothing. | Low | Accepted trade-off; documented. |
| 21 | At 1024px the four row actions on the brand list wrap onto two lines (readable, a little untidy). The Created column is hidden between 640 and 1279px for space. | Low | Cosmetic; revisit with the responsive QA spec. |
| 22 | Brand endpoints have no rate limiting and no protection against two administrators editing the same brand at the same time (last save wins). Names are matched with the database collation (case and accent insensitive). Slug generation keeps only a-z and 0-9 (accented letters are dropped; a slug can be typed by hand). | Low | Acceptable for a single trusted administrator group; revisit if more administrators are added. |
| 23 | `database/tests/db_test.php` checks "no seeded rows in vehicles", "no seeded rows in team_members" and "tests left no business data behind" assume a pristine database, so they fail (3 checks) now that a real vehicle and a real team member exist. The vehicle and brand logic suites are baseline-relative and unaffected. | Low | Run `db_test.php` only against an empty database, or relax those two checks when convenient (not changed here: unrelated to Spec 06). |
| 24 | Vehicle module limits: no rate limiting on its endpoints and no protection against two administrators editing the same vehicle at once (last save wins); slugs keep only a-z and 0-9 (accented letters are dropped); the list hides the Created column below 1440px and Brand below 1280px for space; the dashboard "Add Vehicle" quick action opens the vehicle list (its Add Vehicle button), not the form; the public pages do not read the new `is_published` flag yet (Spec 08). | Low | Acceptable for now; revisit with Specs 08 and 21. |
| 25 | Existing admin shell (Spec 03A): every admin page preloads the font as `manrope-latin-wght.woff2?v=<mtime>` but the stylesheet requests it without `?v=`, so the preload is never "used". Chrome logs a warning after about 3 seconds idle and the font may be downloaded twice. Harmless; found while testing Spec 07 (not caused by it). | Low | Fix in a later UI pass by making the preload and the @font-face URL identical (not changed here: unrelated module). |
| 26 | **Image uploads depend on hosting configuration (this is what caused the reported upload failure on the dev machine: a PHP server started before the ini change).** Production needs PHP with GD (WebP), fileinfo and exif; `upload_max_filesize=10M`, `post_max_size=64M`, `max_file_uploads=20` (`admin/.user.ini` carries these for hosts that read it, untested here) and a writable `uploads/vehicles`. The script-execution block in `uploads/.htaccess` works on Apache only: on nginx or another server the same rule must be added to the server configuration. A hosting memory limit under about 256 MB can make large (up to 20 MP) photos fail with the generic "could not be processed" message. | Medium before deployment | Owner: confirm the hosting meets this at deployment time. |
| 27 | Image module limits: animated WebP and other formats are refused; there is no image replacement function (delete and upload again), no drag-and-drop ordering (Move up / Move down buttons), and no per-vehicle image count limit (only per-request and per-time limits). If the file cannot be deleted after its record was deleted, an orphan file stays on disk (logged and audited as VEHICLE_IMAGE_FILE_MISSING). Last save wins if two administrators edit one gallery at once. | Low | Acceptable for now; replacement or drag-and-drop would need a decision. |
| 30 | Homepage hero and footer images (Spec 10A): FOOTER/HERO PROVENANCE: `assets/images/footer/footer.webp` (live on every public page) is derived from the owner-supplied `hero.jpg`, which shows third-party ships and container branding (e.g. a ship name and a shipping line logo); its licence and suitability are unconfirmed, and 700 px is soft when upscaled. Owner to confirm or replace. Homepage hero: no approved hero photographs exist, so the hero is a plain dark panel and the carousel is dormant. The spec requires 3-4 authentic, credible, optimized images (Canada dealership/vehicle, Canadian logistics, port/containers, vehicles prepared for export), at most about 150 KB each. They must be real photographs the company has the right to use (not AI-generated or random stock). On 2026-10-08 the owner placed `assets/images/hero/hero.jpg` (JPEG, 700 x 433, 59 KB). It was left untouched and is NOT used: it does not follow the file naming/WebP format of the spec, and 700 px is too small for a full-width hero (it would be upscaled and soft). Owner to confirm whether it is a placeholder and supply the real images (or a larger original to convert). Alt text and focus point per slide are set in `app/config/homepage_hero.php`. Also: the hero is judged only with flat test panels, so overlay strength and mobile crops need a visual check once real photographs are in place. | Medium (launch blocker for the intended design) | Owner to supply or approve the photographs. |
| 29 | Public Website Foundation (Spec 09): (a) the `.htaccess` rewrite is untested on real Apache/cPanel; (b) no logo asset exists, so the header uses the text name; (c) no approved phone, e-mail, address or social links exist, so the footer shows none; (d) Firefox/Safari, real devices and screen readers were not tested; (e) the project root is the document root (see issue 9: `/.env` is still downloadable, now also confirmed via encoded paths on the PHP server); a deny rule was deliberately NOT added without owner approval; (f) planned pages show a noindex placeholder until their specifications are built. | Medium (e) / Low | Owner to approve a deny rule for dotfiles/`app/`/`database/` (or set the document root to a public folder) before deployment; supply logo and company contact details when approved. |
| 28 | Team module notes: photos need at least 400 x 400 pixels (chosen by the implementer, not defined by the spec); the upload page script is still named `vehicle-images.js` because it is shared with the team form (a rename was avoided to keep Spec 07 untouched); Move up/down only works on the unfiltered list; no drag-and-drop; no rate limiting; last save wins if two administrators edit one member; categories are a fixed list (no category management, as specified). **Migration 002 must also be run in production.** | Low | Owner: confirm the 400 x 400 minimum; run migration 002 at deployment. |
| 31 | Cookie consent (Spec 18): (a) PROPOSED defaults await owner confirmation: "Browsing activity" off until the visitor allows it, consent kept 180 days. (b) With activity off, pages are `no-store`, so the Back button re-loads pages instead of showing them instantly (that is the purpose of the setting). (c) `Clear-Site-Data` is ignored by Safari and only works over HTTPS or localhost. (d) No privacy policy page exists yet (owner to supply). (e) The end-to-end test left 3 audit rows (ids 1585-1587, user agent `curl`, CUSTOMER_REGISTER_ATTEMPT / CUSTOMER_REGISTERED / CUSTOMER_LOGOUT for a deleted test customer); deleting audit rows was deliberately not done. | Low | Owner to confirm (a); supply privacy policy text when ready. |
| 5 | RESOLVED 2026-10-07: the dev machine's PHP had no `php.ini`, so `pdo_mysql` was not loaded and the dashboard showed "We couldn't load this information right now." A minimal `php.ini` (enables only `pdo_mysql`) was created next to `php.exe`. Production/cPanel must have `pdo_mysql` enabled (default on cPanel). | Resolved (dev) | `Database::connect()` now logs a clear "pdo_mysql is not enabled" message if this ever recurs. |

If a blocker appears, record:

```text
Blocker:
Date:
Affected Specification:
Description:
Why It Blocks Progress:
Required Decision:
Owner:
Status:
```

---

# 16. Change Log

Record meaningful project-level changes here.

| Date       | Change                                       | Impact                                 |
| ---------- | -------------------------------------------- | -------------------------------------- |
| 2026-10-07 | Six foundational context documents completed | Foundation established                 |
| 2026-10-07 | Progress tracker created                     | Project progress now centrally tracked |
| 2026-10-07 | Claude Code operating rules established      | AI development workflow standardized   |
| 2026-10-07 | Project Structure spec added (`Context/specs/01_spec_structure.md`) | Registered as spec 00 |
| 2026-10-07 | Project Structure implemented and verified | Folder foundation `COMPLETED` |
| 2026-10-07 | Database Foundation implemented; 77/77 DB tests pass on MySQL 8.4 | Schema + seed available; `IMPLEMENTED`, awaiting review |
| 2026-10-07 | Admin UI Foundation (spec 03A) implemented as UI only; 102/102 browser checks pass | Admin shell, login UI, components available; no authentication yet |
| 2026-10-07 | Tracker row 03A added for the admin UI spec | Existing numbering of 03 (Admin Authentication) unchanged |
| 2026-10-07 | Spec 04 Functional Admin Dashboard implemented (controller/service/repository, real MySQL data, working sidebar, placeholder route); 44 data-layer + 150 browser checks pass; regression 102/102 and 77/77 | Dashboard live on real data; `IMPLEMENTED`, awaiting review; next task is 03B Admin Authentication |
| 2026-10-07 | Fixed stacked-table overflow at 320px in the 03A table CSS | Tables now fit narrow screens with long values |
| 2026-10-07 | Spec 03B Admin Authentication & Security Foundation implemented; 64 + 107 + 32 checks pass, regression suites pass; no schema change | All admin pages protected; first admin must be created with `database/create_admin.php`; `.env` now needs `APP_ENV`; next task is Spec 05 Brand Management |
| 2026-10-08 | Authentication Fix + 2FA + Password Reset implemented. Login failure diagnosed (no administrator existed); setup script made reliable on Windows; migration 001 applied; TOTP 2FA with encrypted secrets, recovery codes, security settings; secure password reset with Brevo (mock-verified); new dependencies spomky-labs/otphp and bacon/bacon-qr-code; PHP extensions enabled on the dev machine. 111 + 149 + 82 new checks pass, regression suites pass | First administrator can be created and signed in; 2FA and reset available; owner review pending; real Brevo delivery and the physical authenticator app still to be verified by the owner |
| 2026-10-08 | Spec 05 Brand Management implemented (list, search, filter, create, edit, view, activate/deactivate, delete where safe, audit); Brands sidebar item is now real; no schema change; 167 + 170 + 120 checks pass | Brands manageable from the admin; owner review pending; next module Spec 06 Vehicle Management (not started) |
| 2026-10-08 | Spec 06 Vehicle Management implemented after owner decisions (statuses, publish control, extra spec fields, delete rule); list/search/filters/pages, add, edit, view, status, delete-where-safe, audit; Vehicles sidebar item is now real; no schema change; 246 + 206 + 134 checks pass | Inventory manageable from the admin; owner review pending; next module Spec 07 Vehicle Image Management (not started) |
| 2026-10-08 | Spec 07 Vehicle Image Management implemented: secure multi-image upload (content-checked, re-encoded to WebP with thumbnails), gallery, primary image, ordering, alt text, delete; new dependency intervention/image; dev php.ini now enables gd, fileinfo, exif and sets upload limits; no schema change; 156 + 148 + 95 checks pass | Vehicles can have photographs; the dashboard now shows each vehicle's primary image; owner review pending; the dev PHP server must be restarted; next module to be confirmed (see Open Decisions) |
| 2026-10-08 | Vehicle image upload failure diagnosed: the owner's PHP server predated the php.ini change (no gd, no fileinfo, 2M/8M upload limits). Upload now reports missing server components and real server limits in plain words, dev-only error detail added, disconnect-safe endpoints; no security control changed; all suites re-verified | Owner must restart the PHP server once; uploads then work (verified on a restarted server) |
| 2026-10-14 | Public design system changed to an autotrader.com.gh-referenced system (owner decision): new palette with accent `#D42A25`, Nunito, 4/8px radii, flat surfaces, red primary buttons, charcoal hero search panel; public site only; design rules document updated; 45/45 responsive browser checks and 338/338 public regression checks pass | Owner to review visually |
| 2026-10-14 | Layout after the reference: desktop charcoal utility bar (phone, Duty Calculator, Login), Spec 10B Latest Vehicles below the hero (real data, shared card, empty/error states), centred section headings; owner's logo installed as a trimmed `logo.webp` (placeholder `logo.svg` removed); 15 + 45 + 338 checks pass | Spec 10B `IMPLEMENTED`, awaiting owner review; business name vs logo name to be confirmed |
| 2026-10-14 | Hero revised (owner request): height now fits its content (fixed min-heights removed), title enlarged (36 to 60px), four "why choose us" trust cards (Sourced from Canada, Clear vehicle details, Official duty check, Real shipment tracking: factual only, no statistics or badges) placed after the hero and pulled up over its bottom edge by `--trust-offset` in `homepage.css` (2.5rem, 3.25rem from 62em) so they overlap the hero and the next section; new `calculator` icon. This deviates from spec 10A section 26 ("no trust badges") at the owner's explicit request. Browser 45/45, regression suites pass | Owner to review; the tracking card describes the approved tracking process, but Shipment Management is still DEFERRED |
| 2026-10-14 | Business also sells car parts (owner statement). Copy updated: homepage title and meta description, hero line, a "Car parts too" trust card (replaces "Clear vehicle details"), About section (title, paragraph, "Car parts" point, team text, statement), footer, admin sign-in tagline. Homepage hero wording and trust cards moved to `app/config/homepage_content.php` (keeps brand names out of templates, as `brand_strip_test` requires). Recorded in `01_project_context.md` and `06_scope_and_business_rules.md`; a spare-parts inventory/catalogue stays OUT OF SCOPE. Regression and browser 45/45 pass | Owner to confirm parts details (sourcing, new/used, makes, how to enquire) and whether a parts catalogue should become a specification |
| 2026-10-14 | Specs 14 (vehicle detail), 15 (enquiries inbox, reservation confirmation, contact settings) and 16 (parts catalogue) written and approved by the owner (Reserve = request confirmed by an admin; WhatsApp number editable in admin; requests to admin inbox + email; parts: category, part number, condition, public price, fitments; no sign-in; parts Contact + WhatsApp only; stock in/out only) | Build order 14 -> 15 -> 16 |
| 2026-10-14 | **Spec 14 implemented**: `/vehicle/{slug}/` live (gallery, identity, price, status, specs, description, features, shipping, duty link), Reserve request (phone required; saved as `purchase`; never changes the vehicle status; refused on the server for a reserved vehicle), Contact form (`vehicle`), WhatsApp link with pre-filled message (hidden until `whatsapp_number` is set, spec 15). Session-free CSRF (`PublicFormToken`: HttpOnly cookie + HMAC, 3 s minimum, 2 h maximum) so the header never turns into "Account" for visitors; honeypot; rate limits 5/IP and 3/email per hour; Post/Redirect/Get; audit `ENQUIRY_SUBMITTED` / `ENQUIRY_RATE_LIMITED`. New: `EnquiryRepository`, `EnquiryValidator`, `PublicEnquiryService`, `PublicFormToken`, `public/views/vehicle.php`, `vehicle-detail.css/.js`; changed `PublicController`, `PublicVehicleService` (detail), `VehicleRepository` (publicBySlug), `CompanyContactService` (WhatsApp), `public_routes.php`, `public_ui.php` (message icon). No schema change, no dependency. Also: "Calculate Ghana import duty" button on every vehicle card (owner request), and the duty calculator is now described as "external" everywhere (it is autodutychecker.com, not a government site). Tests: 39 HTTP end-to-end + 28 logic (scratch scripts; test rows removed), browser 55/55 (11 pages x 5 widths), regression suites pass; `db_test.php` 6 failures are pre-existing (it expects an empty database; the dev database holds the owner's vehicles, team and customers) | Owner review; Spec 15 next. The multi-photo gallery was verified only by code and the no-JS layout (every dev vehicle has one photo) |
| 2026-10-16 | **Spec 18 implemented** (cookie consent + session reliability). New: `CookieConsentService`, `includes/cookie_banner.php`, `includes/cookie_choices.php`, `public/views/cookies.php`, `database/tests/cookie_consent_test.php`, spec file. Changed: `PublicController` (cookies page, consent-driven Cache-Control + `Vary: Cookie`, live-session check for the header), `public_routes.php` (`/cookies/`), `footer.php` (Cookie settings link + banner), `public.css`, `public.js`. No schema change, no dependency, CSP unchanged. Session bug fixed: a dead/forged `vip_customer_session` cookie made the header show "Account" (now Login, and the cookie is expired). `vip_admin_session` deliberately not listed on the public cookie page (owner question). Tests: cookie consent 60/60, auth 64/64, customer auth 34/34, public site 62/62, 2FA/reset 111/111, public vehicles 58/58, contact 33/33, hero 24/24, brand strip 73/73, external links 30/30, team 24/24; HTTP 40/40 + customer sign-in/out flow (replayed cookie dead after sign-out); real Chrome 91/91 at 320/375/768/1366 px incl. keyboard and no-JavaScript | Owner review of Spec 18 |
| 2026-10-16 | **Header, phones and Parts button** (owner request, no spec file; layout and content change only). Company phone set to `+1 416-577-3963` (was `+233 24 123 4567`); new optional setting `company_phone_2` (`055 660 5451`, dials +233556605451), editable in admin Settings. Utility bar: phone left, "Parts and Accessories" button right (links to new `/parts/` route, `planned` = coming-soon page until spec 16P). Header: menu links centred, Duty Calculator + Login on the right. Mobile menu: Parts and Accessories, phone, Duty Calculator, Login. Contact page and footer list both numbers. Changed: `CompanyContactService` (`phones()`, `phone2`), `ContactSettingsValidator`, `includes/admin/settings_form.php`, `PublicController`, `public_routes.php`, `utility_bar.php`, `navigation.php`, `footer.php`, `contact.php`, `public.css`. No schema change. Tests: company contact 33/33, public site 62/62, cookie 60/60 + Chrome 91/91, customer auth 34/34, auth 64/64, 2FA 111/111, vehicles 58/58, hero 24/24, brand strip 73/73, links 30/30, team 24/24; Chrome header checks 9/9 (992-1366 px one row, links centred; 375 px menu) | Owner review |
| 2026-10-16 | **Spec 16 phase 1 implemented** (owner request: Latest Parts & Accessories after Browse by Brand, tabs Browse by type / Browse by brand, compatible vehicles on each part; decisions: admin + homepage now, brand = vehicle make, tabs filter the cards, Accessories category added). Migration 006 (4 tables, 11 categories) applied to the dev database; schema.sql + seed.sql updated. New: `PartRepository`, `PartService`, `PublicPartService`, `PartValidator`, `PartController`, `admin/parts.php`, `part-create/edit/state/photo/archive/categories.php`, `includes/admin/part_form.php`, `part_problem.php`, `includes/latest_parts.php`, `parts.css`, `parts.js`, `part-form.js`, `uploads/parts/`, wrench icon; changed: `ImageService` (part kind), `admin_ui.php` (Parts nav, part image URL), `admin.css`, `PublicController` (homepage parts, contact `?part=` pre-fill), `public_routes.php`, `home.php`, `.gitignore`, dashboard test (nav now 9 items). Tests: parts 89/89 (rolled back), real Chrome 51/51 homepage section (320-1366 px, tabs, chips, keyboard, no-JS, contact pre-fill) using temporary TEST parts that were removed afterwards, admin form checked by rendering it (not over HTTP: no test administrator was created); regression suites pass. Known: 4 CSRF_FAILURE audit rows (ids 2049-2052, user agent parts_test) left by the first version of the parts test, kept on purpose; `vehicle_image_test` fails 1 check because it counts real image-rejection audit rows already in the dev database (pre-existing data, not this change); `db_test` keeps its 6 pre-existing failures (now also listing the parts tables and company_phone_2) | Owner review: sign in, Parts in the sidebar, add a real part, publish it, check the homepage |
| 2026-10-21 | **Vehicle detail page redesigned** after the owner's reference (an MG dealer page; layout only, no third-party branding). Owner decisions 2026-10-21: Exterior/Interior photo tabs, buttons Reserve / Ask / WhatsApp / Import duty (no test drive, quote or brochure: not offered), only real specifications (no Power field, never N/A), price and status under the title. Layout: Back to Vehicles; gallery (tabs, large photo with 'Exterior · 1/2' counter, prev/next, thumbnails) beside the summary (year · fuel, make + model, trim, price + status, full-width Reserve, description, detail tiles Engine / Transmission / Mileage / Year when present, action row); grey Specifications band of cards (Engine & drivetrain, Vehicle details, Key features, Shipping & duty); Reserve and Ask forms below (unchanged). Migration 007: `vehicle_images.photo_type` ENUM('exterior','interior') default exterior (applied to dev; all 4 existing photos exterior); admin Manage Images has a Photo type selector (audited VEHICLE_IMAGE_TYPE_UPDATED). Changed: `vehicle.php` view, `vehicle-detail.css/.js`, `PublicVehicleService::detail`, `VehicleImageRepository`, `VehicleImageService`, `VehicleImageController`, `admin/vehicle-images.php`, `public_ui.php` (calendar, arrow-left, camera icons), `schema.sql`. Tests: real Chrome 48/48 (320-1366 px, tabs, thumbnails, arrows, keyboard, single-photo vehicle, no-JS; one dev photo was set to interior for the test and set back), photo-type service 11/11 (rolled back); regression suites pass except the known vehicle_image_test audit-count check | Owner review: open a vehicle; mark interior photos on Manage Images. Before deployment run migrations 006 and 007 |
| 2026-10-14 | **Spec 17 implemented** (owner request: Contact page with form, WhatsApp button, email and social media). `/contact/` live: details from the new admin **Settings** page (`admin/settings.php`: company phone, WhatsApp number, email, opening hours; Facebook, Instagram, TikTok, X, YouTube, LinkedIn; each validated, social URLs must be https on the platform's own domain; only changed keys saved; audit `SETTINGS_UPDATED` with key names only), each detail shown only when set and valid; WhatsApp = wa.me chat with the business number; form with topic (General / Buying a vehicle / Car parts / Shipping & tracking -> enquiry types general / vehicle / part / shipping) on the Spec 14 pipeline (session-free CSRF, honeypot, rate limits, PRG). Migration `005_enquiry_type_part.sql` applied to the dev database (add `part`); `schema.sql` updated. Shared form helpers `public_enquiry_field()` / `public_enquiry_hidden()` and `form-guard.js` now used by the vehicle page too. Admin labels: `purchase` shown as "Reservation request", `part` as "Part enquiry". Tests: 35 Spec 17 checks (logic rolled back; HTTP with a temporary administrator, removed afterwards; settings restored exactly), vehicle 39 + 28 re-run, browser 55/55 public + 10/10 contact/settings, 13 regression suites pass | Owner: fill in Admin > Settings (WhatsApp, email, hours, socials) and confirm the stored phone +233 24 123 4567 is real (it looks like an example). Apply migration 005 on any other database. Messages are stored but there is no inbox screen yet (Spec 15) |
| 2026-10-11 | Customer accounts (spec 13): public Login now opens `/login/` (customer); `/register/`, `/account/` added; admin sign-in stays at `/admin/login.php` and is no longer linked publicly; customers table (migrations 003, 004), separate customer session, throttling, Google OAuth (needs owner credentials), redesigned single-card UI; `customer_auth_test.php` 34/34, `public_site_test.php` 62/62 (old Login-to-admin assertion updated), auth 64/64, 2FA/reset 111/111. Dev database: my test customers and a customer row for jbgoddy340@gmail.com were deleted by mistake during cleanup (re-register to recreate) | Owner to review the pages, create Google OAuth credentials, decide what customers can do after signing in |
| 2026-10-08 | Spec 10A revised by the owner: light white header following the supplied reference (name left, Vehicles / How It Works / About / Contact centred, Login with user icon right, about 100 px, thin borders, no Track Vehicle or Browse Vehicles in the header, no underline hover, icon-only mobile menu and close), lighter typography (max 600), footer photograph band (from the owner-supplied image; replace with an approved one), slide 1 always first, image-failure handling, title "Vehicles Sourced from Canada to Ghana"; public suites 126/126 (Chrome, Edge), hero suites 95/95 and 55/55, admin regression passes; no logo asset exists so the text name is kept | Awaiting owner review of the revised header/footer and approved photographs |
| 2026-10-08 | Spec 10A Homepage Structure & Hero implemented: live homepage route, hero with approved copy and CTAs, dependency-free crossfade carousel (auto-rotation, pause, previous/next, indicators, reduced motion), image service/config for 3-4 approved WebP slides with mobile crops and progressive loading; no approved photographs exist so the hero is currently the plain panel; 19 logic + 93 Chrome + 93 Edge (with temporary test panels) + 55 + 55 (no images) checks pass; no database change, no dependency | Homepage hero ready for owner review and photographs; next specification: Spec 10B Homepage Featured Vehicles (not started) |
| 2026-10-08 | Spec 09 Public Website Foundation implemented: front controller and router, shared header/navigation/mobile menu/footer, design tokens and components, SEO meta, image helper, 404/500 pages; no database change, no dependency; 61 logic + 116 Chrome + 116 Edge checks pass, admin regression passes | Public site has a stable base; owner review pending; next specification: Spec 10 Public Homepage (not started) |
| 2026-10-08 | Spec 08 Team Management implemented: team CRUD, category, status, ordering, search/filters, photo upload/replace/remove on the shared image pipeline; migration 002 (team category) applied; Team sidebar item is real; 188 + 186 + 143 checks pass; Shipment Management and Tracking marked DEFERRED by the owner | Team manageable from the admin; owner review pending; next specification: Spec 09 Enquiries (not started) |
| 2026-10-07 | Dashboard load failure diagnosed: PHP had no `pdo_mysql` (no php.ini). Created minimal php.ini; `Database::connect()` now logs a clear message when the driver is missing | Dashboard loads real data on a plain `php -S`; earlier test runs had masked this by passing the extension on the command line |

---

# 17. Update Rules

This file must be updated whenever:

* a specification starts
* a specification is completed
* a feature is blocked
* a major requirement changes
* architecture changes
* a major security issue is discovered
* testing reveals a significant issue
* a launch blocker appears
* an important project decision is made

Do not update progress simply to make the project appear further along.

Progress must represent the actual state of the application.

---

# 18. Current Next Action

> **Owner: review Spec 10A — Homepage Hero (open `/`) and supply 3-4 approved hero photographs (Known Issue 30; until then the hero is a plain panel). Next specification after acceptance: Spec 10B — Homepage Featured Vehicles (not started). Earlier: review Spec 09 — Public Website Foundation (open `/`, use the header and footer links; in development open `/styleguide/`). Next specification after acceptance: Spec 10 — Public Homepage (not started). Decision needed: approve a web-server deny rule for `.env`, `app/` and `database/` (Known Issue 9). Earlier: review Spec 08 — Team Management at `/admin/team.php`, Spec 07 — Vehicle Image Management, Spec 06 — Vehicle Management and Spec 05 — Brand Management (and authentication). Next specification after acceptance: Spec 09 — Enquiries (not started). Shipment Management and Tracking are deferred until the shipping/tracking workflow is clarified.**
>
> Before any deployment also run `database/migrations/002_team_category.sql` on the production database.
>
> 1. Stop and restart your PHP server (so it loads the new extensions), for example `php -S localhost:8000` in the project folder.
> 2. In a terminal in the project folder run `php database/create_admin.php` and choose your own name, email and password (at least 12 characters). Nothing is hardcoded and no default account exists.
> 3. Open `http://localhost:8000/admin/login.php` and sign in. Open **Account security** (sidebar) and turn on two-factor authentication; save the recovery codes.
> 4. Before any deployment resolve Known Issues 1 (credentials in `.env.example`), 2 (root database user), 9 (`.env` reachable from the web root), 15 to 19. Specification 01 (Project/Application Foundation) is still unwritten.

No production feature should be implemented before the corresponding specification has been approved.
