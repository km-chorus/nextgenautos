# Go-Live Checklist — Next Gen Autos and Parts

Last updated 2026-10-26. Use this list, in this order, to put the website on the live cPanel server. Tick each box. Steps
marked **OWNER** need a decision or information from the business owner; the others are technical.

---

## A. Before you start (blockers)

- [ ] **OWNER: Change the MySQL root password.** It was committed to GitHub in the first commit (`770f842`, repo
      `km-chorus/nextgenautos`) and is still in the history. Change it on every machine where it is used. To remove it
      from GitHub too, rewrite the history (e.g. `git filter-repo`) or make the repository private and start a new one.
- [ ] **OWNER: Rotate the Brevo SMTP key** (it once sat in `.env.example`; it was never committed, but rotating is cheap).
- [ ] **OWNER: Accident Vehicles page.** Confirm with the Ghana Revenue Authority or a customs broker that the cars may be
      imported (salvaged-vehicle rules, Act 1014). On a new install the page is **off**; switch it on in
      Admin → Settings only after confirmation. (It is currently **on** in the development database.)
- [ ] **OWNER: Office address** for the Contact page map: Admin → Settings → Office address. (Development has
      "Kumasi-Adum, Agyekum Building"; check that it is complete and correct, then enter it on the live site.)

## B. Hosting requirements

- [ ] PHP **8.1 or newer** (developed and tested on 8.4) with these extensions: `pdo_mysql`, `curl`, `fileinfo`, `gd`
      (**with WebP support**), `mbstring`, `openssl`, `sodium`.
- [ ] MySQL 8 (or a compatible MariaDB with CHECK constraints), `utf8mb4`.
- [ ] Apache with `.htaccess` allowed (`AllowOverride All`) and `mod_rewrite`; `mod_alias` recommended.
- [ ] HTTPS certificate for the domain (cPanel → SSL/TLS Status → AutoSSL). `/.well-known/` is left open for it.
- [ ] Composer available (cPanel Terminal) or upload the `vendor/` folder built locally with `composer install --no-dev`.

## C. Files

- [ ] Upload the project to the web root (e.g. `public_html/`), **without** `.env`, `.git/`, `vendor/` (rebuilt next),
      `storage/password_resets/*`, and anything in `uploads/` except the `.htaccess` and `.gitkeep` files.
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Writable by PHP: `uploads/vehicles/`, `uploads/team/`, `uploads/parts/`, `storage/` (the password reset cache is
      created there automatically).
- [ ] The private folders and files are blocked by `.htaccess` (root rule + one deny file per folder). **Verify after
      upload** — each of these must answer 404 or 403:
      `/.env`, `/.git/config`, `/composer.lock`, `/app/config/app.php`, `/database/schema.sql`, `/Context/`,
      `/includes/footer.php`, `/public/views/home.php`, `/vendor/autoload.php`, `/storage/`, `/CLAUDE.md`.
      And these must work: `/`, `/vehicles/`, `/admin/login.php`, `/assets/css/public.css`.

## D. Database

- [ ] Create the database (cPanel → MySQL Databases), collation `utf8mb4_unicode_ci`.
- [ ] Create a **dedicated database user** (not root) and give it only what the website needs:
      `SELECT, INSERT, UPDATE, DELETE` on this database. (Use a separate, stronger user — or cPanel's phpMyAdmin — for
      the one-off install in the next step, which needs `CREATE, ALTER, INDEX, REFERENCES, DROP`.)
- [ ] **New install:** import `database/schema.sql`, then `database/seed.sql`. That is all: the schema already contains
      every migration up to 012. (Do **not** run the migrations on a new database.)
- [ ] **Upgrading an existing database instead:** run the migrations in `database/migrations/` that it does not have
      yet, **in number order** (001 … 012), each once, after a backup.
- [ ] Check: `php database/tests/db_test.php --fresh` (right after a new install) → all PASS. It only reads, and its
      data checks are rolled back.
- [ ] **Parts catalogue (spec 27), once:** `php database/tools/import_parts_catalogue.php --dry-run`, then without
      `--dry-run`. It adds the 50 part types from the client's picture to Admin → Parts (published, price on request)
      with their photos. Running it again changes nothing. Needs the `uploads/parts/` folder to be writable.

## E. Configuration (`.env` on the server, never in Git)

Copy `.env.example` to `.env` and set:

- [ ] `APP_ENV=production` — turns on secure cookies, HTTPS-only headers (HSTS) and hides technical errors.
- [ ] `APP_URL=https://nextgenautosandparts.com` — no trailing slash, no www; used for links in emails and the Google callback. (Canonical links and the sitemap always use this address on their own: `AppConfig::SITE_URL`.)
- [ ] `APP_KEY=` — generate with `php database/generate_app_key.php`. **Back it up somewhere safe**: losing it disables
      existing two-factor sign-ins.
- [ ] `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` — the dedicated user from D.
- [ ] Email (Brevo): `MAIL_MAILER=smtp`, `SMTP_HOST=smtp-relay.brevo.com`, `SMTP_PORT=587`, `SMTP_ENCRYPTION=tls`,
      `SMTP_USERNAME`, `SMTP_PASSWORD` (a **new** SMTP key), `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME=Next Gen Autos and Parts`.
- [ ] Google sign-in (optional): `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`; in Google Cloud Console register exactly
      `https://<domain>/auth/google/callback/` as the redirect URI. Without these the Google buttons are simply hidden.
- [ ] Do **not** set `APP_DEBUG` in production.

## F. Email (Brevo)

- [ ] **OWNER:** In Brevo → Settings → Security → Authorized IPs: authorise the **server's** IP address, or turn IP
      blocking off. Brevo checks every connection: on 2026-10-27 the development PC was accepted once and refused once
      ("525 5.7.1 Unauthorized IP address") because its internet connection changes address. Ask CheapGhanaHosting for the
      server's fixed outgoing IP and authorise exactly that one.
- [ ] **OWNER:** Send from an address on the company's own domain and add Brevo's **SPF and DKIM** records to the domain's
      DNS (Brevo → Senders, Domains). Otherwise mail lands in spam.
- [ ] Check: `php database/send_test_email.php --check` (connects and logs in, sends nothing), then
      `php database/send_test_email.php <your address>` once.
- [ ] **OWNER:** Add a DMARC record for the domain (Brevo shows one, e.g. `v=DMARC1; p=none; rua=mailto:<your address>`).
- [ ] **Admin → Settings → Email alerts:** enter the Notification email (a mailbox the team reads). Then send one enquiry
      from the live Contact page: the alert arrives, and pressing Reply answers the customer's address. Admin can check
      the result in the audit log (`ENQUIRY_ALERT_SENT` / `ENQUIRY_ALERT_FAILED`).
- [ ] Private files: `curl -I https://nextgenautosandparts.com/.env`, `/admin/error_log` and `/error_log` all answer `404`.

## G. First administrator

- [ ] `php database/create_admin.php --name="…" --email=… --password-stdin` (on the server, via Terminal).
- [ ] Sign in at `/admin/login.php`, set up two-factor authentication, then fill in **Admin → Settings**: phones,
      WhatsApp number (Ghana), email, opening hours, office address, social links, and the Notification email for enquiry alerts. Leave the Accident Vehicles page **off**
      until A is done.

## H. Smoke test on the live site

- [ ] Home, Vehicles, a vehicle page, Contact (map shows), Track (sends you to sign in), Cookie settings.
- [ ] Create a customer account with a real address → the confirmation email arrives → confirm.
- [ ] "Forgot your password?" → the email with link and code arrives → set a new password with the code.
- [ ] Admin: add a vehicle with photos, publish it, see it on the site; create a shipment with a container number →
      the customer gets the status email; the customer sees it on the Track page.
- [ ] Browser developer tools → no console errors; response headers include `Strict-Transport-Security`,
      `Content-Security-Policy`, `Permissions-Policy`.
- [ ] SEO checks (Spec 30), from any computer:
      - `curl -I https://nextgenautosandparts.com/robots.txt` → `200`, `Content-Type: text/plain`; the file ends with
        `Sitemap: https://nextgenautosandparts.com/sitemap.xml`.
      - `curl -I https://nextgenautosandparts.com/sitemap.xml` → `200`, `Content-Type: application/xml`; open it in a
        browser: the pages and every published vehicle, all starting with `https://nextgenautosandparts.com/`.
      - `curl -I http://nextgenautosandparts.com/` and `curl -I https://www.nextgenautosandparts.com/` → `301` with
        `Location: https://nextgenautosandparts.com/`. (The www address needs a DNS record and an HTTPS certificate; cPanel
        AutoSSL covers www when the DNS record exists.)
      - View the homepage source: one `<title>`, `<link rel="canonical" href="https://nextgenautosandparts.com/">`,
        `robots` = `index,follow`.
      - Google's Rich Results Test (search.google.com/test/rich-results) on the homepage, Contact and one vehicle page.

## I. After launch

- [ ] **Google Search Console** (search.google.com/search-console):
      1. Add property → **Domain** → `nextgenautosandparts.com` → add the TXT record it shows at the domain's DNS
         provider → Verify (can take up to a day).
      2. Sitemaps → enter `sitemap.xml` → Submit. Status should become "Success" with the number of pages found.
      3. URL inspection → `https://nextgenautosandparts.com/` → Test live URL → **Request indexing**. Do the same for
         Vehicles, Parts, About and Contact. New vehicles are found through the sitemap; no need to request each one.
      4. Check Pages (indexing) after a few days. Indexing is Google's decision and is never guaranteed.

- [ ] Daily database backup (cPanel → Backups) and a copy of `uploads/` and `.env` (with `APP_KEY`) kept off the server.
- [ ] Keep PHP and the Composer packages updated (`composer update --no-dev` after testing locally).
- [ ] Watch the admin **Audit log** for repeated failed sign-ins.

---

### What the website already does (for reference)

* Security: CSRF tokens on every form, prepared SQL only, strict Content-Security-Policy, rate limits on every sign-in
  route (3 wrong passwords per account, 3 reset emails per address per hour), password reset link + code kept only as
  hashes in a 30-minute file cache, other sessions ended after a password change, two-factor sign-in for administrators,
  uploads checked and re-encoded, private folders denied.
* Known limitation: on Apache only `.htaccess` protects the private folders. If the host ever serves the project from a
  web root you cannot configure, move everything except `index.php`, `admin/`, `assets/` and `uploads/` one level above
  the web root instead.

## Deployment package (2026-10-27)

- Build: `php database/tools/build_deploy.php <folder outside the project>` creates `public_html/` + `public_html.zip` from an
  allowlist (no .env, docs, tests, CI, migrations, import data, dev-only pages) and stops if it finds a secret from .env or a
  Brevo key. Last build: 976 files, 3.3 MB zip, 0 findings (the scan was proven to stop on a planted secret).
- Database for import: the cleaned development database, exported to `AmponsahAutos_deploy/database-import/nextgen_database.sql`
  (restore-tested: identical; 90 database checks pass). Contains the admin password hash: delete it from the server after import.
- Clean-up done before the export (backup restore-tested, kept in `Desktop/AmponsahAutos_backups/2026-10-27-before-cleanup/`):
  all customers, the 3 test admins, demo vehicles and their photos, team members and placeholder photos, enquiries, shipments,
  favourites and development audit history removed; all parts set In stock; Accident Vehicles page switched off.
- [ ] **OWNER:** change the local MySQL root password (it is in Git history, first commit) and use a new, limited database
      user in production.
