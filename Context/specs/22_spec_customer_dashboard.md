# Spec 22 — Site Clean-up, Customer Email Confirmation and Customer Dashboard

**Status:** IMPLEMENTED 2026-10-21/22 (awaiting owner review)
**Source:** owner request of 2026-10-21: remove "How It Works", remove the homepage team section, and give signed-in customers a small dashboard (tracking details, favourite vehicles, reserved vehicles). Owner decisions the same day: remove How It Works everywhere; verify emails; favourites on cards and the vehicle page; "reserved vehicles" = the customer's reservation requests.

## 1. Removed

* "How It Works": menu item, footer link, the planned `/how-it-works/` page (now 404) and the homepage section (`includes/how_it_works.php` deleted). `app/config/how_it_works.php` stays: the site assistant answers "how does buying work" from it.
* The team block of the homepage About section (team members are still managed in the admin; the planned `/team/` page is unchanged).

## 2. Email confirmation (security fix)

Found 2026-10-21: password sign-ups did not prove they own their email (spec 13 noted it), yet spec 20 linked shipments to an account by email. Someone could register a buyer's address first and see the buyer's shipment.

* `customers.email_verified_at` (migration 009). Google accounts are verified (existing ones marked by the migration).
* Registration sends a branded "Confirm your email" link (`CustomerVerificationService`): `/verify-email/?t=<id>.<expiry>.<HMAC>`, 48 hours, tied to the email, no table. At most 3 links per hour per account; "Send the link again" on the dashboard and the Track page.
* Shipments are linked only to ACTIVE, VERIFIED accounts (`ShipmentRepository::activeCustomerIdByEmail`), and shown only to verified accounts (also enforced in `forCustomer`). Confirming the email (or signing in with Google) links shipments that were waiting under that email. The migration unlinks any shipment linked to an unverified account (none in the dev database).
* Pre-hijack defence: when Google sign-in is linked to an account whose email was never confirmed, that account's password is removed (Google becomes the way in).

## 3. Customer dashboard (`/account/`)

For the signed-in account only (id from the server-side session):

* Confirm-email notice with "Send the link again" (unverified accounts).
* **Shipment tracking:** each linked shipment (vehicle, status, container number or "not available yet", last update) and a link to `/track/`. Only for verified accounts.
* **Favourite vehicles:** saved with the heart on every vehicle card and the "Save" button on the vehicle page (`POST /favourites/`, customer CSRF token, JSON for `favourites.js`, else Post/Redirect/Get). Only public vehicles can be saved; a favourite that is later sold or hidden stays listed as "No longer available" without link or price. Guests are sent to sign in and back to the vehicle. Max 100.
* **Reservation requests:** reservation requests (enquiry type `purchase`) sent while signed in, with Received / In progress / Closed and the note "A request is a reservation once our team has confirmed it with you by phone". Enquiries now record `customer_id` from the session; older ones cannot be shown.
* Account details (email with Confirmed / Not confirmed) and Sign out.

## 4. Data (migration 009)

`customers.email_verified_at`; `enquiries.customer_id` (FK customers RESTRICT); `customer_favourites (customer_id, vehicle_id, created_at, PK both; vehicle CASCADE, customer RESTRICT)`.

## 5. Tests

`customer_dashboard_test.php` 43/43 (links, expiry, tampering, old email, throttling, linking only when verified, defence in depth, Google pre-hijack, favourites public-only / limit / sold / ownership, reservation requests ownership, return targets); `shipment_test` 65/65 (test customers now verified); `public_vehicles_test` (card has `slug`), `vehicle_test` (counts relative to real data), `public_site_test` (nav without How It Works). HTTP: `/how-it-works/` 404, homepage without the two sections, guest favourite → sign in (303 / 401 JSON), invalid and malformed confirmation links handled. Chrome: dashboard (verified and unverified), hearts on cards, vehicle Save button, guest Save → sign-in notice, 375 and 1366 px, no console errors on live pages.

## Change of 2026-10-22: welcome toast, header icons, account settings, sign-in goes to Track (owner request)

* **Welcome toast:** after signing in, a toast says "Welcome back, {name}!" ("Welcome, {name}!" for a new account, including Google), once, on the first page.
  * It comes from a one-time session flag (`CustomerSessionService::establish` / `consumeWelcome`).
  * It is dismissible (button, Escape) and hides after 6 seconds, staying open while hovered or focused.
* **Where sign-in lands:** the Track page by default (sign-in, registration, Google, visiting /login/ while signed in). A remembered destination still wins (for example after saving a favourite).
* **Header when signed in:** a settings icon (`/account/settings/`) and a sign-out icon replace the user icon. Sign-out is a POST form with the customer CSRF token. The mobile menu shows "Account settings" and "Sign out".
* **`/account/settings/`** (`CustomerAccountService`):
  * **Name:** change it (registration rules).
  * **Password:** the current password is required. A Google-only account may set its first one. PasswordPolicy applies, and the new password is typed twice. Five wrong current passwords per account in 15 minutes throttle further attempts. The session id is renewed after a change.
  * **Email:** shown, not editable here, because shipments are linked to the confirmed email; customers contact the team to change it.
  * **Audit:** CUSTOMER_NAME_CHANGED, CUSTOMER_PASSWORD_CHANGED, CUSTOMER_PASSWORD_CHANGE_FAILED. No values are recorded.
* **Cards:** the cards on the account dashboard, account settings and Track pages are white, with no border line, on the grey card area.
* **Tests:** `account_settings_test.php` 26/26. Browser run with a temporary TEST customer (removed): sign in → Track with toast → toast once → settings (name saved, wrong current password refused) → sign out from the header, at 1366 and 375 px. Card styles checked on all three pages.
