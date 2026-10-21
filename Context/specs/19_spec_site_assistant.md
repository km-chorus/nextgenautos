# Spec 19 — Site Assistant (guided chat, no AI)

**Status:** IMPLEMENTED 2026-10-21 (awaiting owner review)
**Source:** owner request of 2026-10-21 with a reference screenshot (a dealer's chat window: header, greeting, quick-reply chips, message box, round launcher button).
**Owner decision 2026-10-21:** a **guided assistant without AI**. "AI chatbot" stays out of scope (scope document, Automation/AI); an AI mode would need its own approved specification (external integration, API key, cost, privacy).

## 1. What it is

A chat window opened from a round button at the bottom right of every public page. It answers common questions **only from information the website really has**: the live vehicle list and prices, the parts catalogue, the contact details and opening hours from the admin Settings, the How It Works steps, and the fixed business rules (duty, tracking, reservation). It never invents prices, delivery times, duty amounts, container numbers, addresses or services. When it has no answer it says so and offers WhatsApp, the contact form and the phone numbers.

It says plainly that it is an automated assistant. It is not a person and does not store conversations.

## 2. Likely questions (from the codebase and business rules)

| Topic | Example questions | Answer source |
| --- | --- | --- |
| Vehicles | What cars do you have? Any Toyota? 2023 Accord? | Public vehicle list (published, available or reserved) |
| Prices | How much is the Accord? Vehicle prices? | Same list, with price and status; duty note from each vehicle's "duty included" field |
| Reserve / buy | How do I reserve / buy? Deposit? | Reservation process of spec 14 (request, team calls to confirm, no online payment) |
| Process | How does it work? How do I import a car? | `app/config/how_it_works.php` |
| Shipping | How does shipping work? How long does it take? | Business process; shipping times are NOT on the site, so it says so and hands over |
| Tracking | How do I track my car? Where is my container? | Tracking rules (container number from the shipping line via the company; official tracking site) |
| Import duty | How much is the duty? Is duty included? | The site does not calculate duty: external calculator link |
| Parts | Do you sell parts? Brake pads for Corolla? | Published parts (name, type, compatible vehicles, price) |
| Contact | Phone number? WhatsApp? Email? | Settings (company phones, WhatsApp, email) |
| Hours | When are you open? | Settings (opening hours), or "not listed yet" |
| Location | Where are you located? | No address is stored: says so and offers contact |
| Payment / financing | Can I pay online? Financing? | No payment on the website; discuss with the team |
| Account | How do I sign in / create an account? | Customer account pages |
| About / brands | Who are you? Which brands? | About text; active brands |
| Test drive / warranty / anything else | | Not offered or not stated on the site: hand over to the team |

## 3. Behaviour

* Quick-reply chips (no emojis, per the design rules): Vehicles, Prices, How buying works, Shipping & tracking, Import duty, Car parts, Contact us, Opening hours. Every answer ends with a few relevant follow-up chips.
* Typed questions: up to 200 characters, matched on the server by keywords and by the makes, models and years of the vehicles really listed (and the names and compatible makes of published parts).
* Endpoint: `GET /assistant/?intent=<id>` or `?q=<text>`, JSON, `Cache-Control: no-store`, `noindex`. Nothing is written to the database or the logs (the question text is never logged).
* Links in answers are built by the server only: site paths, `tel:`, `mailto:`, WhatsApp, the approved duty calculator and tracking provider.
* Without JavaScript the launcher is not shown (the Contact page and WhatsApp remain available everywhere).
* Accessible: launcher button with `aria-expanded`; the window is a labelled dialog (non-modal); messages are a polite live log; Escape closes and returns focus; works at 320 px (full-width sheet) to desktop.

## 4. Security

* CSP: `connect-src` changes from `'none'` to `'self'` so the page can call `/assistant/` (no external connection is allowed).
* Input untrusted: length cap, control characters removed, intents from a fixed list. Output inserted with `textContent` only; links validated client-side against the same allowed schemes.
* No session, no cookie, no storage of conversations.

## 5. Out of scope

AI / LLM answers, live human chat, storing conversations, booking or payment through the chat.
