# Spec 25 — About Us page

**Status:** IMPLEMENTED 2026-10-26 (awaiting owner review). **Source:** owner request of 2026-10-26: "The about would contain about the business and also showcase team members."

## Content (only approved, true facts; nothing invented)

1. **Title:** "About Us" (banner, title only).
2. **Who we are:**
   * a headline and two short paragraphs: vehicle imports from Canada to Ghana and car parts for popular makes (owner statement of 2026-10-14); clear steps from choosing to tracking;
   * four fact cards: Vehicles from Canada; Car parts; Shipping with Hapag-Lloyd (owner, 2026-10-22); Our office (the office address from Admin → Settings; the card is left out when no address is set).
3. **How we work:** the five buying steps from `app/config/how_it_works.php`, numbered (one row of five on desktop, two per row on tablets, one column on phones).
4. **Meet the team:**
   * every **active** member from Admin → Team, in the admin's order;
   * each card shows the photo (thumbnail plus the large file for sharp screens), department, name, position and biography (up to 600 characters);
   * a neutral placeholder when there is no photo;
   * two cards per row on phones;
   * the whole section is left out when no member is active;
   * the footer link "Our Team" points to `/about/#team`.
5. **Closing band:** "Ready to find your next vehicle?", with Browse Vehicles and Contact us.

No company history, founding year, figures, awards or superlatives: none have been approved. Add them only from the owner.

## Technical

* **Route:** `about` is live (`/about/`, `public/views/about.php`, `assets/css/about-page.css`).
* **Controller:** `PublicController` loads the steps, `PublicTeamService::forAboutPage()` and the address. A part that cannot be read is left out and logged.
* **Homepage:** the About section's secondary link is now "More about us" (→ `/about/`).

## Tests

* All test suites pass.
* Chrome at 1366, 768 and 375 px, all passing (7/7): title, four facts, five steps, team cards, About highlighted in the menu, no sideways scroll, no console errors; the footer "Our Team" link opens the team section.

## Owner to-do before launch

The team data in the development database is test content:
* "GODFRED MARCUS ANTWI" has a video-game cover as photo;
* "jgjbgh / mbvmn" is placeholder text with a stock image.

Replace them with real photos and details, or set them inactive, in Admin → Team.

## Change of 2026-10-26 (owner request): photo-only team cards; admin-fed sections hidden when empty

* **Team card:** only the photo (4:5, rounded), with the department pill, name and role on a dark gradient at the bottom. The photo zooms in slowly on hover or keyboard focus (off with reduced motion). The biography is no longer shown on the card. Two per row on phones.
* **Members:** only those with an uploaded photo are shown (the card is the photo). With none, the team section is left out entirely.
* **Rule for every section fed by the admin:** it is left out when there is nothing uploaded. That covers Meet the team, the "Our office" card, Latest Vehicles, Latest Parts and Browse by Brand. Browse by Brand used to show "Brand selection is currently unavailable" and now disappears.
* **Tests:** `public_team_test` 30/30. Six new checks cover each empty case. An older check passed only by accident once real members existed (12-member limit); it is fixed by setting the real members inactive inside its rolled-back transaction.
