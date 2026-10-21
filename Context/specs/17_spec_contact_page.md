# SPEC 17 — CONTACT PAGE AND ADMIN CONTACT SETTINGS

Status: APPROVED by the owner on 2026-10-14 (owner answers: details come from an admin Contact settings page; show opening
hours; the form has a topic list). Brings section 5 of Spec 15 (contact settings) forward. Depends on Spec 14 (enquiry
pipeline). Minor implementation details below are the implementer's, consistent with the approved architecture.

## 1. Purpose

A public Contact page where customers can send a message, open a WhatsApp chat with the business directly, and find the
business email, phone, opening hours and social media profiles. All business details are entered by an administrator;
nothing is invented and anything not filled in is simply not shown.

## 2. Public page `/contact/` (route becomes live; GET + POST)

* Page banner "Contact Us" with a one-line introduction.
* **Contact details** (each only when set):
  * **Chat on WhatsApp** button: `https://wa.me/{whatsapp_number}?text=Hello, I have a question for {site name}.` (new tab). Opens the business's WhatsApp chat.
  * Phone (`tel:` link), email (`mailto:` link), opening hours (multi-line text).
  * Social media: Facebook, Instagram, TikTok, X, YouTube, LinkedIn — a link per platform that has a URL (new tab, `rel="noopener noreferrer"`).
  * Nothing set at all → "Our contact details will be available here soon. You can send us a message with the form."
* **Contact form**: name (required), email (required), phone (optional), topic (required), message (required). Topics map to the
  existing `enquiries.enquiry_type`: General question → `general`; Buying a vehicle → `vehicle`; Car parts → `part` (new value,
  §4); Shipping & tracking → `shipping`. `vehicle_id` is NULL.
* Same protection and behaviour as Spec 14: session-free CSRF token (purpose `contact-page`), 3 s minimum, honeypot, rate limits
  (5/IP, 3/email per hour, shared with the vehicle forms), server validation, Post/Redirect/Get (`?sent=1`), success message
  "Thank you. We have received your message and will reply soon.", friendly errors, audit `ENQUIRY_SUBMITTED`.

## 3. Admin `/admin/settings.php` (replaces the Settings placeholder)

* Section **Contact**: Company phone (`company_phone`, existing), WhatsApp number (`whatsapp_number`), Email address
  (`contact_email`), Opening hours (`opening_hours`, up to 500 characters, line breaks kept).
* Section **Social media** (category `social`): `social_facebook`, `social_instagram`, `social_tiktok`, `social_x`,
  `social_youtube`, `social_linkedin` — full `https://` profile URLs whose host must belong to that platform
  (e.g. facebook.com / fb.com for Facebook; x.com / twitter.com for X).
* Validation: phone and WhatsApp numbers must be real numbers (local Ghana `0XX…` or international); email valid; URLs https,
  no user/password, platform host only, up to 300 characters. Empty field = setting removed (not shown on the site).
* Saving: only changed keys are written, in one transaction; audit `SETTINGS_UPDATED` listing the changed key names (never the
  values). `requireAdmin()`, permission `settings.manage`, CSRF, Post/Redirect/Get with a flash message.
* Uses the existing `settings` table (categories `contact` and `social` already exist). No new table.

## 4. Schema

* Migration `005_enquiry_type_part.sql`: add `part` to `enquiries.enquiry_type` (also used by Spec 16). Backward compatible.

## 5. Out of scope

Office address and map (not chosen), a Canada contact block (not chosen), email alerts and the admin inbox (Spec 15), social
links in the footer (RECOMMENDATION, needs a decision), chat widgets or embedded social feeds.

## 6. Testing

Public page with no / some / all details, WhatsApp link format, every form rule and topic, tampered topic, CSRF/honeypot/rate
limits, admin settings validation (each field, wrong platform host, http, javascript: URLs, credentials in URL), changed-keys-only
saving and audit, CSRF on the admin form, XSS in stored values, responsive 320–1440, regression.
