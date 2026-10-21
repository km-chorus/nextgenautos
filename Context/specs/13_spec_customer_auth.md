# Spec 13 — Customer Sign-in, Registration and Google Sign-in

Status: `IMPLEMENTED` (2026-10-11). Written after the build, from the owner's instructions, because the project documents
forbid customer accounts unless explicitly approved (CLAUDE.md sections 7 and 28; `05_ai_development_testing.md` section 12).

## 1. Owner decisions (2026-10-11)

* The public **Login** button opens a **customer** sign-in, not the administrator sign-in.
* Scope chosen: **customer registration + sign-in + sign-out only**. What a signed-in customer can do (enquiries, saved
  vehicles, shipment/tracking view) is **not decided** and needs its own specification.
* The administrator sign-in stays at `/admin/login.php`, separate and **not linked** from any public page.
* Sign-in and registration follow the visual style of https://app.retailerspos.com/register (centred card, logo badge,
  Google button, "OR" divider), adapted to the project's colours and design rules.
* Google sign-in is added for customers (OAuth 2.0). Administrators never use it.

## 2. Routes

| Route | Methods | Purpose |
| --- | --- | --- |
| `/login/` | GET, POST | Customer sign-in |
| `/register/` | GET, POST | Customer registration (signs the customer in) |
| `/account/` | GET, POST | Minimal account page; POST signs out (CSRF-protected) |
| `/auth/google/` | GET | Starts Google sign-in (redirect to Google) |
| `/auth/google/callback/` | GET | Finishes Google sign-in (always ends at a fixed local path) |

All are `noindex`, `Cache-Control: no-store`. Only these routes accept POST; every other public route stays GET/HEAD only.

## 3. Data

`customers` (migrations 003 and 004; also in `schema.sql`): `name`, unique lowercase `email`, `password_hash` (NULL for
Google-only accounts), unique `google_id`, `status` (active/disabled), `last_login_at`, timestamps. A CHECK requires a
password hash or a Google id. Customers are a separate table from `admin_users`: a customer can never be an administrator.
Rows are disabled, never deleted.

## 4. Security design

* Separate session cookie `vip_customer_session`, separate CSRF token; a customer session can never satisfy the admin guard.
* Passwords: `password_hash()`/`password_verify()`, existing `PasswordPolicy` (12+ characters), one verify on every
  path (dummy hash for unknown emails), session id regenerated on sign-in.
* Throttling through `audit_logs` (same mechanism as the admin): 10 failures / 15 min per IP, 10 per account;
  5 registrations / hour per IP. No raw emails of failures are stored.
* No "return to" parameter anywhere (no open redirect). Fixed messages only; technical detail goes to the error log.
* Google: authorization-code flow, `state` + `nonce` (single use, 10 minutes), ID token taken directly from Google's token
  endpoint over verified TLS; issuer, audience, expiry, nonce and `email_verified === true` are checked. A Google account is
  linked to an existing customer only through a Google-verified email, and a **different** Google account can never take
  over an already-linked email. The client secret is server-side only. Google buttons appear only when
  `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` and a valid `APP_URL` are all set.

## 5. Known limits / recommendations

* No email verification for password registrations (needs Brevo, which is not configured): anyone can register any address.
  RECOMMENDATION: verify email before an account can do anything beyond signing in (new spec, uses `EmailService`).
* Registration says "an account with this email already exists", which reveals that an email is registered (limited by the
  per-IP registration throttle). The alternative needs email verification (above).
* No customer password reset yet (needs Brevo). RECOMMENDATION: add with email verification.
* Terms of service / privacy links are not shown: no such pages exist. Add when the owner supplies them.
* Optional background photograph: save an approved image as `assets/images/auth/background.webp`; none ships.

## 6. Owner action for Google

1. Google Cloud Console, then APIs & Services, then Credentials, then Create OAuth client ID (Web application).
2. Authorised redirect URI, exactly: `<APP_URL>/auth/google/callback/` (for example `http://localhost:8000/auth/google/callback/`; production must be https).
3. Put the client id and secret in `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`. Never commit them.

## 7. Tests

`database/tests/customer_auth_test.php` (34 checks, rolled back, Google stubbed). `public_site_test.php` updated: the
Login item points at `/login/` and no public link points at the admin.

## 8. Google enforced (owner decision 2026-10-11)

* While Google sign-in is configured, it is the ONLY way customers sign in or register. `/login/` shows only
  "Continue with Google"; `/register/` redirects to `/login/`; any POST to `/login/` or `/register/` is bounced to
  `/login/` without being processed (enforced in `CustomerAuthController`, not just hidden in the view).
* Existing password accounts are linked on their first Google sign-in through the Google-verified email.
* Fallback (owner choice): while Google is NOT configured (a fresh install, or credentials removed), the email/password
  forms are available so the site is not locked out. Consequence: removing the Google settings from `.env` re-opens
  password sign-in, so protect `.env`. A temporary Google outage does not re-open it (the page shows a generic
  "Google sign-in could not be completed" message).
* Email verification and customer password reset are no longer needed for customers once Google is configured.

## 9. Google no longer enforced (owner decision 2026-10-11, later the same day)

Section 8 is superseded. Customers can sign in or register EITHER with Google (button shown only when configured) OR with
email and password; both appear on `/login/` and `/register/`. Account linking by Google-verified email is unchanged.

## 10. Track Vehicle page, signed-in customers only (owner request 2026-10-11)

* `/track/` is live and shows content ONLY to a signed-in customer. The session is validated against the database on
  every request (inactivity timeout, disabled or deleted account are refused); if the check cannot be made the page is
  not shown (fails closed). Signed-out visitors go to `/login/?reason=track` and, after signing in (password, registration
  or Google), return to `/track/` through a server-side one-use return target limited to a fixed list (`['/track/']`):
  there is no `next` URL parameter, so no open redirect.
* Content follows `06_scope_and_business_rules.md` sections 7 and 29 to 31 only: it explains how tracking works, says
  "Tracking information is not yet available until your container number has been provided", and links to the approved
  external provider (`tracking_provider_url` setting, accepted only if https on `hapag-lloyd.com`; fallback to the
  seeded address). It shows no container number, location or status, and does not scrape or embed the provider.
* Out of scope (Spec 11 Shipment Management / Spec 12 Vehicle Tracking remain DEFERRED): per-customer shipments, a
  stored container number per customer, any carrier integration.
