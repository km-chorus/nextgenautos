# Spec 18 — Cookie Consent, Cookie Settings and Session Reliability

**Status:** IMPLEMENTED 2026-10-16 (awaiting owner review)
**Source:** owner request of 2026-10-16: "Add a cookie consent banner that manages how cookies are used on this website; users can manage the cookies when Cookie settings is clicked, they can manage their activities not to be cached. And make sure cookies and sessions work perfectly."
**Depends on:** 09 (public foundation), 13 (customer sessions), 14 (public form token)

---

## 1. What the website actually stores (audit of 2026-10-16)

| Cookie | Set by | Purpose | Lifetime | Category |
| --- | --- | --- | --- | --- |
| `vip_customer_session` | `CustomerSessionService` | Keeps a customer signed in | Browser session; ends after inactivity | Essential |
| `vip_admin_session` | `SessionService` | Keeps an administrator signed in (admin area only) | Browser session; ends after inactivity | Essential |
| `vip_form` | `PublicFormToken` | Protects the public contact and reservation forms against forgery and spam | Browser session | Essential |
| `vip_cookie_consent` | `CookieConsentService` (this spec) | Remembers the visitor's cookie choice | 180 days | Essential |

The public `/cookies/` page lists only the cookies visitors receive: `vip_admin_session` is left out (owner question of 2026-10-16), because visitors never get it and naming it would only advertise the admin area. Showing cookie names is not a weakness in itself (every browser shows them in its developer tools); session security rests on the random, HttpOnly, SameSite, server-validated value.

All are first-party, HttpOnly, SameSite=Lax, Secure under HTTPS. No `localStorage`, `sessionStorage` or IndexedDB is used.
The site uses **no analytics, advertising, tracking or third-party cookies** (the CSP blocks all external scripts).

Because no optional cookie exists, the settings do **not** show toggles for analytics or marketing: presenting choices for things the site does not do would be misleading (rule 21, no fake data). If analytics are ever added (an integration change, security doc §37), a new category is added and the consent version is raised so every visitor is asked again.

## 2. "Browsing activity" (the one optional choice)

Interpretation of "manage their activities not to be cached": the visitor decides whether **their browser may keep copies of the pages they visit** (browser cache and back/forward cache).

* **Off (default, also before any choice):** every public page is sent with `Cache-Control: no-store`; nothing the visitor browsed is kept in the browser's cache. Turning it off also sends `Clear-Site-Data: "cache"`, which removes copies already kept (supported by Chromium and Firefox; Safari ignores it).
* **On:** public pages are sent with `Cache-Control: private, no-cache` (kept only by this browser, always re-checked with the server; never by a shared proxy). Pages load faster on Back.
* Sign-in, account, tracking and any page that repeats what the visitor typed stay `no-store` regardless of the choice. So does every page shown to a signed-in customer.

**PROPOSED (owner to confirm):** default Off (privacy by default); consent remembered 180 days.

## 3. Banner and settings

* Shown at the bottom of every public page until the visitor chooses; not shown on `/cookies/`. Not modal: the site stays usable.
* Buttons of equal size: **Accept all**, **Essential only**, and a **Cookie settings** link.
* **Cookie settings** opens a dialog (JavaScript) or the `/cookies/` page (no JavaScript). The footer always offers "Cookie settings", so the choice can be changed at any time.
* `/cookies/` (live route): plain-language cookie policy (the table in §1) and the settings form.
* Choices are saved by a normal form POST to `/cookies/` (works without JavaScript), answered with Post/Redirect/Get back to the page the visitor was on.

## 4. Security

* The consent cookie holds no personal data: `<version>.<activity 0|1>.<unix time>`, strictly validated; anything else counts as "no choice".
* HttpOnly, SameSite=Lax, Secure under HTTPS, path `/`.
* POST `/cookies/` is refused (403) when the browser reports a cross-site request (`Sec-Fetch-Site` / `Origin`), so another site cannot change a visitor's choice.
* The return address is a hidden field rendered by the server, validated again on POST: a local path (and optional simple query) only; anything else returns to `/`. No open redirect.

## 5. Session reliability fix

Found 2026-10-16: the header showed "Account" whenever a `vip_customer_session` cookie existed, even when its session had expired or never existed (forged / garbage-collected id). The header now asks `CustomerSessionService::currentCustomerId()` (no database read). An empty or expired session is destroyed and its cookie expired, so the visitor sees "Login" again.

## 6. Out of scope

Analytics, marketing pixels, consent logging in the database, geo-based consent rules, a privacy policy / terms page (the owner has not supplied them).

## 7. Acceptance criteria

* [x] Banner appears on first visit; disappears after any choice; reappears never until the consent expires or the version changes.
* [x] Accept all / Essential only / custom settings work with and without JavaScript.
* [x] Activity off: public pages `no-store`; saving Off sends `Clear-Site-Data: "cache"`. Activity on: `private, no-cache`. Private pages always `no-store`.
* [x] Footer "Cookie settings" reopens the choice on every page.
* [x] Cross-site POST refused; invalid return path goes to `/`; tampered consent cookie ignored.
* [x] Dead customer session cookie → header shows Login, cookie expired.
* [x] Keyboard and screen-reader usable; works at 320 px to desktop; no console errors; CSP unchanged.
* [x] Existing auth, customer-auth and public-site suites still pass.
