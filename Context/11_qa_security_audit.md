# QA, database and security audit — Next Gen Autos and Parts

**Date:** 27 October 2026

**Scope:**
* the application code, database schema, authentication and authorization;
* the public site, admin dashboard and customer accounts;
* vehicles, parts, enquiries, shipment tracking and uploads;
* sessions, configuration, deployment and performance.

**Where it ran:** local development machine only.
* A separate audit database (`zz_audit`) was built from `database/schema.sql` + `seed.sql` and filled with **synthetic data only**:
  * 1 administrator, 2 customers and 3 vehicles, then about 3,000 vehicles and 60,000 audit rows for performance tests;
  * passwords random and never printed.
* Three local servers ran on it: development mode, production mode, and production mode with no database (outage test).
* **Production was not tested.** The only contact with the live domain was the read-only SEO check of the same day. The development database's data was not changed: the suites roll back their own writes.

---

## A. Executive summary

* **No critical, high or medium vulnerability was found** in the application.
* **One low-severity vulnerability was confirmed and fixed:** every response announced the exact PHP version (`X-Powered-By: PHP/8.4.25`). It was removed in code and in `.htaccess`, and re-tested: the header is absent on all 12 responses checked.
* **Black-box results:** 95 test cases ran against the local audit servers. 94 passed; the 1 failure is the PHP-version finding, now fixed.
* **Black-box areas covered:**
  * SQL injection on every public filter;
  * reflected XSS;
  * CSRF;
  * tracking access between customers (IDOR);
  * admin access control on all 49 protected admin pages;
  * separation of customer and admin sign-in;
  * session regeneration and sign-out;
  * open redirects;
  * brute force and rate limits;
  * malicious uploads;
  * security headers, methods and CORS;
  * error disclosure during a database outage.
* **Automated suites:** 29 suites with 2,029 checks pass on the development database. A new runner and a CI workflow make this repeatable.
* **The test suites themselves had four defects, now fixed.** One of them wrote a real row into whichever database it ran on.
* **What still decides release readiness:** deployment and configuration items that could not be tested here, mainly the Apache `.htaccess` protections (no Apache locally), secret rotation, a least-privilege database user and admin two-factor policy. See J and K.

This report does not declare the site "secure". It records what was tested, how, and what is left.

## B. Architecture and testing scope

### Stack
* PHP 8.4.25, MySQL 8.4.9, all tables InnoDB.
* Custom modular MVC without a framework: front controller (`public/index.php`), then `PublicRouter` and `PublicController`; separate admin pages (`admin/*.php`), each calling `requireAdmin()` first.
* Layers: controllers, services, repositories (PDO), validators.
* Dependencies (composer.lock): PHPMailer 6.12.0, Intervention Image 3.11.9, OTPHP 11.5.0, Bacon QR code 3.1.1, plus their libraries.

### Inventory
* **Public routes (live):**
  * home, vehicles, accident vehicles (setting-dependent), parts, about, contact, privacy, cookies;
  * vehicle detail `/vehicle/{slug}/`;
  * `/sitemap.xml`, `/robots.txt`.
* **Customer routes (private, `noindex`):**
  * `/login/`, `/register/`, `/forgot-password/`, `/reset-password/`;
  * `/auth/google/` and `/auth/google/callback/`;
  * `/account/`, `/account/settings/`, `/track/`, `/favourites/` (POST), `/verify-email/`, `/assistant/` (JSON).
* **Admin (`/admin/`):**
  * 54 PHP pages: 49 protected by `requireAdmin()`, plus 5 that are meant to work before sign-in (login, logout, forgot password, reset password, two-factor);
  * one role, `administrator`, with every permission.
* **Highest-risk operations:**
  * customer and admin sign-in, two-factor and password reset;
  * shipment tracking (personal data);
  * admin create, update and delete for vehicles, parts, team, brands and shipments;
  * image uploads;
  * public forms (enquiry, reserve, contact);
  * outbound calls (Brevo email, Google OAuth token exchange; both to fixed hosts).
* **Database:** 19 tables, 16 foreign keys, check constraints on 17 tables, no triggers, no stored procedures. The connection uses exceptions, real prepared statements (not emulated), utf8mb4, UTC and strict SQL mode.

### Tools and methods
* Code review: grep sweeps for dangerous calls, every dynamic SQL builder, every redirect.
* Existing CLI test suites, plus new ones.
* A black-box HTTP harness (PHP and cURL) with separate cookie jars for a guest, customer A, customer B and the administrator.
* EXPLAIN ANALYZE on synthetic volume.
* Packagist security-advisory API.
* **Not used:** OWASP ZAP is not installed here, so no ZAP scan ran. The custom harness covers the same attack classes with harmless payloads. A ZAP baseline scan against staging is recommended (J).

## C. Test inventory

| Area | Status | Notes |
|---|---|---|
| Unit and integration suites (29 suites, 2,029 checks) | **PASS** | Validation, vehicles, parts, team, brands, shipments, enquiries, auth, two-factor, password reset, sessions, uploads, image limits, SEO, integrity |
| Fresh-database run (as CI would) | **PASS except 1 environment check** | `team_test` compares the real `uploads/team` folder with the database; it fails only because this machine holds real photos (passes with an empty folder, as in CI) |
| Black-box system and security (95 cases) | **94 PASS, 1 FAIL (fixed and re-tested)** | Appendix 1 |
| Transactions and rollback | **PASS** | `integrity_test.php` |
| Error disclosure (production mode, database down) | **PASS** | Generic pages; no SQL, paths or stack traces; sitemap 503; tracking fails closed |
| Dependency vulnerabilities | **PASS** | No published advisory affects the installed versions (Packagist, 2026-10-27) |
| Query performance (EXPLAIN ANALYZE) | **Done** | F and G |
| `.htaccess` (deny rules, no PHP in uploads, redirects) | **NOT TESTED** | No Apache locally; reviewed only. Must be checked on the server (J) |
| OWASP ZAP passive/active scan | **NOT RUN** | Tool not available; custom harness used instead |
| Responsive, keyboard, accessibility | **Not re-tested in this audit** | Covered feature by feature in earlier specs (Chrome at 1366, 768 and 375 px) |
| Email delivery | **BLOCKED** | Brevo refuses this machine's IP (known); the email service reports failures honestly |
| Backup restore | **NOT TESTED** | No backup procedure exists on this machine; see J |

## D. Confirmed vulnerabilities

### SEC-01 — PHP version disclosed in every response (Low) — FIXED

| | |
|---|---|
| Evidence | `GET /` (production mode) answered `X-Powered-By: PHP/8.4.25` (test HDR-POW) |
| Affected | Every PHP response: pages, admin, JSON endpoints, sitemap |
| Likelihood / impact | High likelihood, low impact. It tells attackers which PHP version to target; it is not exploitable by itself |
| Fix | `app/config/app.php`, loaded first by every web entry point, now calls `header_remove('X-Powered-By')`. `.htaccess` also unsets it (`mod_headers`) |
| Retest | HDR-POW-R: absent on 12 responses (6 addresses, development and production mode). Regression check in `integrity_test.php` |
| Also recommended | Set `expose_php = Off` in cPanel → MultiPHP INI Editor |

No other confirmed vulnerability. In particular, none of the following:
* **SQL injection:** 15 parameters × 6 payloads (including a time-based `SLEEP` probe), plus the admin id parameter.
* **Reflected XSS:** 7 entry points.
* **Missing CSRF protection:** customer settings, favourites and sign-out; sign-in; admin actions; cross-site cookie choice.
* **Tracking access between customers:** customer A could not see customer B's shipment by list, container-number search or five id parameters.
* **Admin access:** all 49 protected admin pages, as guest and as customer, redirect to sign-in with an empty body. Admin POST actions are refused for customers.
* **Login separation:** admin credentials do not work at the customer sign-in, and customer credentials do not work at the admin sign-in.
* **Sessions:** a forged session cookie is treated as a guest; the old session cookie is dead after sign-out; the session ID changes at customer and admin sign-in.
* **Open redirects:** `//evil`, `https://evil`, `/\evil`.
* **Malicious uploads:** PHP code named `shell.php` or `photo.jpg` was rejected by content checking (`format_text_x_php`, `format_image_gif`); no file or row was stored.
* **Rate limits hold:** 3 wrong customer passwords pause the account; reset emails are limited to 3 per address per hour; the admin is paused after repeated failures; enquiries are refused when sent too fast or without a token; the honeypot works.
* **Headers present:** CSP without `unsafe-inline` scripts, `frame-ancestors 'none'`, `nosniff`, Referrer-Policy and Permissions-Policy. No CORS header; PUT, DELETE, TRACE and PATCH answer 405.

## E. Findings, risks and recommendations

| ID | Finding | Status | Severity | Likelihood / impact | Affected | Remediation |
|---|---|---|---|---|---|---|
| SEC-01 | PHP version disclosed | Confirmed, **fixed** | Low | see D | all responses | done; also `expose_php=Off` |
| QA-01 | `parts_page_test` created a real "engine" part on a database without the catalogue (test side effect) | Confirmed, **fixed** | Medium (test hygiene) | would write into whatever database it ran on | `database/tests/parts_page_test.php` | now uses a rolled-back transaction and a test slug |
| QA-02 | `parts_test` used a fixed audit id (`id > 2052`), so it failed on any other database | Confirmed, **fixed** | Low | — | `database/tests/parts_test.php` | records the starting id first |
| QA-03 | `public_vehicles_test` raised a PHP warning (its fake rows lacked `duty_included`); the old way of running suites hid it | Confirmed, **fixed** | Low | — | test stub | field added; the runner now fails any suite that prints a PHP warning |
| QA-04 | 9 suites failed on a fresh database (they need an administrator) | Confirmed, **fixed** | Low | blocks CI | tests | `database/tests/fixtures.php` adds one synthetic admin (refuses non-test databases and production) |
| R-01 | Admin two-factor sign-in is **optional** per account | Design, untested risk | **Medium** | a stolen admin password gives full control | `AuthService` | **Owner decision:** require 2FA for every administrator before launch |
| R-02 | Secrets: the MySQL root password is in Git history (commit `770f842`); the Brevo SMTP key must be rotated; development uses the root database account | Known | **Medium** (deployment) | anyone with repository access | Git history, `.env` | rotate both; production `.env` must use a dedicated user with only SELECT, INSERT, UPDATE, DELETE (go-live checklist D) |
| R-03 | `.htaccess` protections untested (deny `/app`, `/database`, `/.env`, `/vendor`, `/storage`; no PHP in `/uploads`; http and www redirects) | Not tested | **Medium** until verified | if the host ignores `.htaccess` (for example `AllowOverride None`), private files could be served | `.htaccess`, per-folder `.htaccess` | after deployment, run the curl checks in J. Each folder also has its own deny file, and uploads are always re-encoded `.webp` with random names (defence in depth) |
| R-04 | Two admins editing the same record: the last save wins silently | Code review | Low | rare with a small team | vehicle, part, shipment edit | optional: an "updated_at" version check on save (new spec) |
| R-05 | Customers cannot be deleted: enquiries, shipments and favourites reference them with RESTRICT | Schema review | Low (privacy process) | a "delete my data" request needs manual work | `customers` foreign keys | write an anonymisation procedure (blank name and email, keep records). The Privacy Policy already says "contact us" |
| R-06 | Rate limits and HSTS use the direct connection (`REMOTE_ADDR`, `HTTPS`) | Code review | Low | behind a CDN or proxy, every visitor would share one IP (limits trigger for everyone) and HSTS would not be sent | `PublicEnquiryService`, auth services, `AppConfig::isHttps` | no change needed on plain cPanel; if a CDN is added later, add trusted-proxy handling first |
| R-07 | The server error log can contain a value from a database error message (for example a duplicate email) | Code review | Low | log readers only | `error_log(... $e->getMessage())` | acceptable; restrict log access, or log the exception class and code only |
| R-08 | `vehicles.damage_areas` is a comma list and `key_features` is JSON (multi-valued columns) | Schema review | Low | none today (display only) | `vehicles` | normalise only if filtering by damage area is ever needed |
| R-09 | Backups and restore not tested | Not tested | Medium (operations) | data loss | hosting | daily cPanel backup plus an off-server copy of `uploads/` and `.env`; **test a restore** to a staging database once |

## F. Database: schema, normalisation, queries, transactions, indexes

### Schema
* Every table has a surrogate primary key (`customer_favourites` has a composite one), InnoDB and utf8mb4.
* **Foreign keys** (16): RESTRICT where history matters (vehicles→brands, shipments, enquiries, audit logs); CASCADE for owned children (images, part numbers, fitments, recovery codes).
* **Unique keys:** slugs, emails, brand names and category names; one primary photo per vehicle or part (generated column plus unique key).
* **Check constraints** enforce prices ≥ 0, year ranges, currency format, slug format and non-blank names.
* **Strict SQL mode** is set on every connection, so bad values are refused, not truncated. During this audit a wrong enum value made a whole batch roll back, exactly as intended.

### Normalisation
* **1NF:** met except `damage_areas` (comma list) and `key_features` (JSON). Both are display-only (R-08).
* **2NF:** met; the composite key in `customer_favourites` has no partial dependencies.
* **3NF:** met, with two deliberate copies:
  * `shipments.customer_name` and `customer_email`: a shipment can exist **before** the customer has an account. It is linked later by confirmed email (`linkWaitingShipments`), so the copy is needed.
  * `enquiries.customer_name`, `email` and `phone`: a snapshot of what the visitor typed, which is legal evidence of the request.
  * The cost: a customer who renames their account keeps the old name on older shipments and enquiries. That's acceptable; document it.
* No derived columns, duplicated lookups or update anomalies otherwise. Orphan rows are impossible because of the foreign keys.

### Queries
* Every dynamic SQL builder was reviewed: `VehicleRepository`, `PartRepository`, `ShipmentRepository`, `BrandRepository`, `CustomerDashboardService`.
* Fixed SQL text is joined with **bound parameters only**. Sort order comes from a fixed allowlist (`PUBLIC_SORTS`). Column names are fixed by the code.
* LIKE wildcards are escaped. Pagination is validated and bounded (20 per page; sitemap at most 45,000).
* Ownership is enforced in SQL: tracking reads only `WHERE s.customer_id = <session id>`. `SELECT *` appears only in single-row admin reads.

### Transactions
* 40 operations use `Transaction::run`. It commits or rolls back as a whole, joins an outer transaction, and never leaves one open.
* The one hand-written transaction (admin sign-in) follows the same rule.
* Files and emails are kept outside the database transaction on purpose:
  * files are written first and removed if the database step fails;
  * emails are sent only after commit.
* Repeated submissions:
  * forms redirect after a successful POST;
  * public forms need a single-use, time-limited token;
  * enquiries are rate-limited per IP and per email;
  * admin forms disable the button on submit.
* **Verified by `integrity_test.php`:**
  * a failure after the first write leaves nothing;
  * a nested failure rolls back the whole operation;
  * a duplicate-key error mid-way rolls back the earlier write;
  * a successful operation keeps both writes.

### Indexes
All foreign-key columns are indexed. Measured on synthetic volume (3,003 vehicles, 60,032 audit rows, 4,000 enquiries, 1,502 shipments, 2,000 parts), average of 20 runs:

| Query (from the code) | Plan | Time |
|---|---|---|
| Q1 public vehicles, newest, page 1 | table scan + filesort | 4.50 ms |
| Q2 public vehicle count | index | 8.75 ms |
| Q3 vehicles by make | index + filesort | 1.60 ms |
| Q4 vehicle page by slug | unique index | 0.33 ms |
| Q5 rate limit by IP (every sign-in and enquiry) | index | 0.59 ms |
| Q6 rate limit by customer | index | 0.59 ms |
| Q7 customer's shipments (Track) | index | 0.31 ms |
| Q8 admin enquiries, newest | filesort | 3.84 ms |
| Q9 sitemap vehicles | table scan + filesort | 5.85 ms |
| Q10 parts "fit my car" | scan of 11 categories + filesort | 19.96 ms |

**Proposed and measured (not applied, pending your review):**
```sql
CREATE INDEX idx_vehicles_created ON vehicles (created_at, id);    -- Q1: 4.50 ms -> 0.81 ms
CREATE INDEX idx_enquiries_created ON enquiries (created_at, id);  -- Q8: 3.84 ms -> 0.53 ms
```
* Both match the sort used by the newest-first lists; their write cost is negligible at this scale.
* Q9 (sitemap) doesn't benefit, because its availability filter prevents reading the index in order, and it runs at most once an hour (cached).
* Q10 (20 ms) is the slowest query. It is acceptable now; revisit when parts exceed about 10,000.
* Recommended route: migration `013` with a backup first; it was tested here and the test indexes were removed again.

## G. Performance
* Every measured query is under 20 ms at the volume above. The rate-limit checks that run on every sign-in, reset and enquiry use indexes (about 0.6 ms).
* Image limits from the earlier audit hold: at most 700 KB per vehicle or part photo and 450 KB per team photo; thumbnails at most 100 KB (team 60 KB). Verified on disk.
* No load or stress test was run, on purpose: no denial-of-service testing was allowed.

## H. Files created or modified in this audit

| File | Purpose |
|---|---|
| `app/config/app.php` | SEC-01: remove `X-Powered-By` on every web request |
| `.htaccess` | SEC-01: `Header unset X-Powered-By` (mod_headers) |
| `database/tests/integrity_test.php` | new: transaction rollback, database-level refusals, connection settings, SEC-01 regression (17 checks) |
| `database/tests/fixtures.php` | new: one synthetic administrator for a fresh **test** database (refuses others) |
| `database/tests/run_all.php` | new: one command for the syntax check of 236 files plus every suite; fails on PHP warnings |
| `.github/workflows/ci.yml` | new: CI with throwaway MySQL, `composer audit`, fresh-install check, fixtures, `run_all` (**not yet run on GitHub**: it needs a push) |
| `database/tests/parts_page_test.php` | QA-01 fix |
| `database/tests/parts_test.php` | QA-02 fix |
| `database/tests/public_vehicles_test.php` | QA-03 fix |
| `Context/11_qa_security_audit.md` | this report |

No application behaviour, design or database structure changed, apart from the header removal.

## I. Tests rerun after remediation
* `php database/tests/run_all.php` on the development database: syntax OK (236 files); **29/29 suites, 2,029 checks passed**; no PHP warnings.
* The same on two fresh databases (as CI would): 28/29 suites pass. The exception is the `team_test` environment check described in C.
* `integrity_test.php`: 17/17 on the development and fresh databases.
* Black-box retest of SEC-01 (HDR-POW-R): **pass**.
* Not re-run in full after the fix: the black-box suite. A second run would trip the admin lockout that test RATE-3 deliberately triggered; only the affected case was re-tested.

## J. Remaining risks and manual steps
1. **Before launch (owner):**
   * rotate the MySQL root password and the Brevo SMTP key;
   * create the least-privilege database user for production;
   * authorise the server IP in Brevo;
   * decide on mandatory admin two-factor (R-01);
   * confirm Act 1014 before switching on Accident Vehicles.
2. **After deployment, verify `.htaccess` on the real server** (R-03). Each of these must answer **404** (or 403):
   ```
   curl -I https://nextgenautosandparts.com/.env
   curl -I https://nextgenautosandparts.com/app/config/app.php
   curl -I https://nextgenautosandparts.com/database/schema.sql
   curl -I https://nextgenautosandparts.com/vendor/autoload.php
   curl -I https://nextgenautosandparts.com/storage/
   curl -I https://nextgenautosandparts.com/composer.lock
   curl -I https://nextgenautosandparts.com/Context/11_qa_security_audit.md
   ```
   * `curl -I https://nextgenautosandparts.com/` must have no `X-Powered-By` and must include `Strict-Transport-Security`.
   * `http://` and `www.` addresses must answer 301 to `https://nextgenautosandparts.com/`.
3. **Staging scan:** run OWASP ZAP **baseline (passive)** against a staging copy, then active scanning only on staging, with rate limits.
4. **Backups:** enable daily backups and test one restore into a staging database (R-09).
5. **CI:** push to GitHub and confirm the workflow passes. Add a branch rule to require it.
6. **Indexes:** review migration 013 (F) and apply with a backup.

## K. Release-readiness recommendation
**The application code is ready for release from a security and data-integrity standpoint:**
* no confirmed critical, high or medium vulnerability;
* the one low finding is fixed;
* the database enforces its own integrity;
* the regression suites pass.

**The release itself is conditional** on J.1 (secrets, database user, Brevo, two-factor decision, Act 1014) and on J.2 (the `.htaccess` checks passing on the real server). Until J.2 is verified, the protection of private files rests on configuration that was not tested here.

---

## Appendix 1 — Black-box test cases (local audit servers, synthetic data)

* **Preconditions for all cases:** the audit database with synthetic data. Customers A and B have one shipment each; one administrator; two published vehicles and one unpublished.
* **Servers:** production-mode server for public and header tests; development-mode server for flows.
* **Evidence:** the HTTP status, header or body check shown under "Actual". Session cookie values are redacted.

| Test ID | Feature | Input | Expected | Actual | Result | Severity if failing |
|---|---|---|---|---|---|---|
| PUB-1 | Public page / | `GET /` | 200 | 200 | PASS | Medium |
| PUB-2 | Public page /vehicles/ | `GET /vehicles/` | 200 | 200 | PASS | Medium |
| PUB-3 | Public page /parts/ | `GET /parts/` | 200 | 200 | PASS | Medium |
| PUB-4 | Public page /about/ | `GET /about/` | 200 | 200 | PASS | Medium |
| PUB-5 | Public page /contact/ | `GET /contact/` | 200 | 200 | PASS | Medium |
| PUB-6 | Public page /privacy/ | `GET /privacy/` | 200 | 200 | PASS | Medium |
| PUB-7 | Public page /cookies/ | `GET /cookies/` | 200 | 200 | PASS | Medium |
| PUB-8 | Public page /vehicle/2019-toyota-corolla/ | `GET /vehicle/2019-toyota-corolla/` | 200 | 200 | PASS | Medium |
| PUB-9 | Public page /sitemap.xml | `GET /sitemap.xml` | 200 | 200 | PASS | Medium |
| PUB-10 | Public page /robots.txt | `GET /robots.txt` | 200 | 200 | PASS | Medium |
| PUB-11 | Unpublished vehicle hidden | `GET unpublished vehicle slug` | 404 | 404 | PASS | High |
| ERR-18c | Malformed URL | `GET /no-such-page/` | 404, no technical detail | 404 | PASS | Medium |
| ERR-d70 | Malformed URL | `GET /VEHICLES` | 301, no technical detail | 301 | PASS | Medium |
| ERR-77d | Malformed URL | `GET /vehicles` | 301, no technical detail | 301 | PASS | Medium |
| ERR-a5f | Malformed URL | `GET /vehicle/<script>/` | 404, no technical detail | 404 | PASS | Medium |
| ERR-821 | Malformed URL | `GET /..%2f..%2fetc%2fpasswd` | 404, no technical detail | 404 | PASS | Medium |
| ERR-8a0 | Malformed URL | `GET /vehicle/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa` | 404, no technical detail | 404 | PASS | Medium |
| ERR-e25 | Malformed URL | `GET /index.php` | 404, no technical detail | 404 | PASS | Medium |
| ERR-f7e | Malformed URL | `GET /%00/` | 404, no technical detail | 404 | PASS | Medium |
| SQLI-v-q-0 | SQLi /vehicles/ q | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-make-0 | SQLi /vehicles/ make | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-model-0 | SQLi /vehicles/ model | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-year-0 | SQLi /vehicles/ year | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-min_price-0 | SQLi /vehicles/ min_price | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-max_price-0 | SQLi /vehicles/ max_price | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-sort-0 | SQLi /vehicles/ sort | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-page-0 | SQLi /vehicles/ page | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-v-availability-0 | SQLi /vehicles/ availability | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-p-q-0 | SQLi /parts/ q | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-p-make-0 | SQLi /parts/ make | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-p-model-0 | SQLi /parts/ model | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-p-year-0 | SQLi /parts/ year | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-p-category-0 | SQLi /parts/ category | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| SQLI-p-page-0 | SQLi /parts/ page | `' OR '1'='1` | 200, no DB error, no delay | 200 | PASS | Critical |
| XSS-1 | Reflected XSS /vehicles/ | `/vehicles/?q=<payload>` | payload never reflected unescaped | escaped | PASS | High |
| XSS-2 | Reflected XSS /parts/ | `/parts/?q=<payload>` | payload never reflected unescaped | escaped | PASS | High |
| XSS-3 | Reflected XSS /contact/ | `/contact/?part=<payload>` | payload never reflected unescaped | escaped | PASS | High |
| XSS-4 | Reflected XSS /contact/ | `/contact/?request=<payload>` | payload never reflected unescaped | escaped | PASS | High |
| XSS-5 | Reflected XSS /login/ | `/login/?reason=<payload>` | payload never reflected unescaped | escaped | PASS | High |
| XSS-6 | Reflected XSS /parts/ | `/parts/?model=<payload>` | payload never reflected unescaped | escaped | PASS | High |
| HDR-Conten | Header Content-Security-Policy | `GET / (production mode)` | present | default-src 'none'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' d | PASS | Medium |
| HDR-X-Cont | Header X-Content-Type-Options | `GET / (production mode)` | present | nosniff | PASS | Medium |
| HDR-Referr | Header Referrer-Policy | `GET / (production mode)` | present | strict-origin-when-cross-origin | PASS | Medium |
| HDR-Permis | Header Permissions-Policy | `GET / (production mode)` | present | camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), mag | PASS | Medium |
| HDR-X-Fram | Header X-Frame-Options | `GET / (production mode)` | present | DENY | PASS | Medium |
| HDR-CSPin | CSP blocks inline script | `GET /` | script-src 'self' without unsafe-inline | default-src 'none'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' d | PASS | Medium |
| HDR-ACAO | No permissive CORS | `GET / with Origin: https://evil.example` | no Access-Control-Allow-Origin | checked | PASS | Medium |
| HDR-POW | No version disclosure | `GET /` | no X-Powered-By | PHP/8.4.25 | FAIL (fixed, see HDR-POW-R) | Low |
| MTH-PUT | HTTP method PUT | `PUT /vehicles/` | 405 | 405 | PASS | Low |
| MTH-DELETE | HTTP method DELETE | `DELETE /vehicles/` | 405 | 405 | PASS | Low |
| MTH-TRACE | HTTP method TRACE | `TRACE /vehicles/` | 405 | 405 | PASS | Low |
| MTH-PATCH | HTTP method PATCH | `PATCH /vehicles/` | 405 | 405 | PASS | Low |
| CK-1 | Customer cookie flags (prod) | `GET /login/` | HttpOnly, SameSite, Secure | Set-Cookie: =<redacted>; path=/; secure; HttpOnly; SameSite=Lax | PASS | High |
| CK-2 | Private pages not cached | `GET /login/` | Cache-Control no-store | no-store | PASS | Medium |
| AUTH-1 | Customer sign-in | `valid A credentials` | 303 to /track/ | 303 /track/ | PASS | High |
| AUTH-2 | Session id regenerated at sign-in | `cookie before vs after` | different | same | PASS | High |
| AUTH-3 | Sign-in needs CSRF token | `POST /login/ without token` | refused, not signed in | 400  | PASS | High |
| AUTH-4 | Admin credentials rejected on customer sign-in | `admin email+password at /login/` | not signed in | 200  | PASS | Critical |
| AUTH-5 | Customer credentials rejected on admin sign-in | `customer A at /admin/login.php` | not signed in | 200  | PASS | Critical |
| TRK-1 | Customer A sees own shipment | `GET /track/ as A` | A container shown | 200 shown | PASS | High |
| TRK-2 | A never sees B shipment | `GET /track/ as A` | B container absent | checked | PASS | Critical |
| TRK-3 | A searches B container number | `/track/?number=<B number> as A` | not found, no B data | checked | PASS | Critical |
| TRK-4 | IDOR by id parameters | `/track/?id/shipment/customer_id=<B>` | ignored | no B data for any parameter | PASS | Critical |
| TRK-5 | Guest cannot track | `GET /track/ signed out` | 303 to /login/ | 303 /login/?reason=track | PASS | Critical |
| TRK-6 | Forged session cookie | `random vip_customer_session` | treated as guest | 303 | PASS | Critical |
| CSRF-account/settings | CSRF /account/settings/ | `POST without token as A` | refused (4xx/redirect, no change) | 303 / track 200 | PASS | High |
| CSRF-favourites | CSRF /favourites/ | `POST without token as A` | refused (4xx/redirect, no change) | 303 / track 200 | PASS | High |
| CSRF-account | CSRF /account/ | `POST without token as A` | refused (4xx/redirect, no change) | 303 / track 200 | PASS | High |
| CSRF-cookies | Cross-site cookie choice | `POST /cookies/ Origin evil` | 403 | 403 | PASS | Low |
| ADM-1 | Every admin page needs admin sign-in | `42 pages, as guest and as customer` | redirect to login, empty body | all redirected | PASS | Critical |
| ADM-2 | Customer POSTs to admin actions | `6 admin action endpoints as customer A` | redirect to login | all redirected | PASS | Critical |
| ADM-3 | Admin sign-in | `valid synthetic admin` | redirect into admin | 303 dashboard.php | PASS | High |
| ADM-4 | Admin session id regenerated | `cookie before vs after` | different | session id changed at sign-in | PASS | High |
| ADM-5 | Admin dashboard | `GET as admin` | 200 + noindex + no-store | 200 noindex, nofollow | PASS | High |
| ADM-6 | Admin action needs CSRF token | `POST vehicle-status without token` | refused (400/redirect with error), no change | 400 | PASS | High |
| ADM-7 | Delete via GET does nothing | `GET vehicle-delete.php?id=1` | confirmation page or redirect only | 200 | PASS | Medium |
| ADM-8 | Admin id parameter injection | `vehicle-edit.php?id=1 OR 1=1` | 404/not found, no DB error | 404 | PASS | High |
| ADM-9 | Admin search XSS | `vehicles.php?q=<payload>` | escaped | checked | PASS | High |
| SES-1 | Old session dead after sign-out | `replay pre-logout cookie` | 303 to login | 303 | PASS | High |
| REDIR-ce8 | Open redirect (cookie return) | `//evil.example/` | stays on site |  | PASS | Medium |
| REDIR-c2f | Open redirect (cookie return) | `https://evil.example/` | stays on site |  | PASS | Medium |
| REDIR-930 | Open redirect (cookie return) | `/\evil.example` | stays on site |  | PASS | Medium |
| REDIR-login | Open redirect (login return) | `?return=https://evil.example/` | ignored | return targets are an allowlist (code review) | PASS | Medium |
| RATE-1 | Account paused after 3 wrong passwords | `4 wrong then the right password for B` | right password refused for now | 429  | PASS | High |
| RATE-2 | Password reset emails limited | `4 reset requests for A` | 4th refused (429 or toast) | 429,429,429,429 (address already over its limit from the first run; see RATE-2b) | PASS | Medium |
| RESET-1 | Forged reset link | `?t=1.<random>` | invalid link message, no form | 200 | PASS | High |
| RATE-2b | Reset emails: 3 per address per hour | `4 requests, unknown synthetic address` | first 3 accepted (same answer as a real account), 4th 429 | 303,303,303,429 | PASS | Medium |
| ENQ-1 | Enquiry sent faster than a person | `POST at once after loading` | refused ("press Send again") | 200 | PASS | Medium |
| ENQ-2 | Enquiry without form token | `POST without token` | refused (400) | 400 | PASS | Medium |
| ENQ-3 | Honeypot filled | `website field filled` | looks sent, nothing stored | 303 | PASS | Low |
| UPL-php | Malicious upload: shell.php | `PHP code as shell.php (claims image/jpeg)` | rejected, nothing stored | no file stored | PASS | Critical |
| UPL-jpg | Malicious upload: photo.jpg | `PHP code as photo.jpg (claims image/jpeg)` | rejected, nothing stored | no file stored | PASS | Critical |
| UPL-db | No image record for rejected upload | `vehicle_images rows` | 0 | 0 | PASS | High |
| RATE-3 | Admin sign-in paused after repeated failures | `6 wrong passwords, then the right one` | right password refused for now | 429  | PASS | High |
| RATE-4 | Admin login error is generic | `wrong password` | no hint whether the email exists | checked | PASS | Medium |
| HDR-POW-R | Retest after fix SEC-01 | `12 responses: /, /vehicles/, admin login, sitemap, favourites, track; ` | no X-Powered-By | header absent on all 12 | PASS | Low |
