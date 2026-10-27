# Spec 29 — Privacy Policy page, smaller header logo, FAQ removed

**Status:** IMPLEMENTED 2026-10-27 (awaiting owner review; the policy text needs the owner's legal review).

**Source:** owner request of 2026-10-27: "The site logo is big, reduce the size a bit and make it look good on the header. Get rid of faq page and let's structure privacy policy page that concerns cookies, sign ups, authentication details and anything privacy. We won't show on the page yet. You add it to the footer."

## 1. Header logo
* **Size:** 40px tall on desktop (was 52px) and 32px on phones and tablets (was 40px). The width follows the logo's own proportions.
* **Header:** the desktop header bar is now 64px tall (was 70px), so the logo sits balanced with the menu.

## 2. FAQ removed
* The planned `/faq/` route is gone, so the address now returns "page not found".
* The footer's "FAQ" link under Support is removed.

## 3. Privacy Policy (`/privacy/`)
* **Where it is linked:** only from the footer's bottom line, next to "Cookie settings". It is not in the main menu (owner: "We won't show on the page yet").
* **Sections:**
  1. information we collect (contact and reservation forms, accounts and Google sign-in, account use, shipments, security records);
  2. how we use it;
  3. accounts, sign-in and passwords (hashing, confirmation link, reset link and code, wrong-password pause, sessions);
  4. cookies (summary, linking to Cookie settings; Google Maps on the Contact page);
  5. services we work with (Brevo, Google, WhatsApp; Hapag-Lloyd and the duty calculator as outside sites);
  6. how long we keep it;
  7. your choices;
  8. how we protect it;
  9. changes to this policy;
  10. contact us.
* **Only true statements:** each one describes what the site really does. The figures come from the code that enforces them:
  * reset link and code: 30 minutes;
  * confirmation link: 48 hours;
  * cookie choice: 180 days;
  * wrong passwords before a pause: 3.
* **Contact details:** the company email and office address come from Admin → Settings.
* **Correction during the build:** staff two-step sign-in is optional per staff account, so the policy does not claim it is required.

## Owner to-do (before relying on the page)
1. **Legal review** of the wording.
2. **Laws and registration:** the page names no law and no registration (for example Ghana's Data Protection Act, 2012 (Act 843), or registration with the Data Protection Commission). Add these only if the owner confirms them.
3. **Retention periods** for accounts, messages and shipment details: the page currently says "for as long as we need them … or until you ask us to delete them". Give fixed periods if wanted.
4. **Company email:** set it in Admin → Settings. Until then the Contact section shows the office address and a link to the Contact page.
5. **Account deletion:** customers ask by contacting the company. There is no self-service delete button; that would need a new spec.

## Tests
* All 27 suites pass. The footer and route checks in `public_site_test` cover the new link and the FAQ removal.
* Chrome at 1366, 768 and 375 px, 10/10:
  * logo height 40 or 32px;
  * Privacy Policy only in the footer; no FAQ link;
  * the page's 10 sections and contents links, no sideways scroll, no console errors;
  * a contents link scrolls to its section below the sticky header.
