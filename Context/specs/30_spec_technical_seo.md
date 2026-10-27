# Spec 30 — Technical SEO

**Status:** IMPLEMENTED 2026-10-27 and tested locally. Not yet live: the domain still serves a separate "Coming Soon" page.

**Source:** owner request of 2026-10-27, "Act as a senior technical SEO engineer…", with the production domain `https://nextgenautosandparts.com`.

## What already existed (kept)
* One `<head>` helper (`public_meta()`) for every public page: `<title>` ("Page | Next Gen Autos and Parts"), robots, description, canonical link, Open Graph tags.
* Viewport and `theme-color` (#2B2F35).
* Router: lower-case addresses with a trailing slash (301 to the clean form); unknown addresses return 404.
* `noindex` on filtered and later list pages, "coming soon" pages, error pages, and every private page (sign-in, register, account, tracking, favourites, assistant: `noindex,nofollow`).
* The admin area sends `X-Robots-Tag: noindex, nofollow` and requires a signed-in administrator.

## Changes
1. **One official address:** `AppConfig::SITE_URL = 'https://nextgenautosandparts.com'`.
   * Canonical links, `og:url`, `og:image`, structured data, the sitemap and robots.txt always use it.
   * Before, the canonical link and `og:url` depended on `APP_URL`, and disappeared when it was not set.
   * A `noindex` page no longer prints a canonical link.
2. **Homepage:**
   * title "Next Gen Autos and Parts | Canadian Vehicles & Car Parts in Ghana";
   * description (150 characters), covering only services the site confirms: vehicles sourced in Canada and shipped to Ghana, and car parts for Toyota, Honda, Hyundai and other popular makes;
   * share image: the first hero photo (1440×810) with its alt text;
   * `twitter:card` on every page (`summary_large_image` when there is an image).
3. **Parts page description** shortened to 146 characters, so search results don't cut it off.
4. **`/sitemap.xml`** (`SitemapService`, answered by `PublicController` before routing). It is generated on each request, so new or sold vehicles are reflected immediately.
   * **Pages listed:** home, Vehicles, Accident Vehicles (only while switched on), Parts, About, Contact, Privacy, Cookies.
   * **Vehicles listed:** every public vehicle page (published, available or reserved, not archived), with `lastmod` = the later of the vehicle's last change and its newest photo.
   * **Not included:** priority, changefreq, private pages, "coming soon" pages, filtered pages, development pages, admin pages.
   * **Responses:** GET and HEAD only (others get 405). If the database cannot be read, it answers 503 rather than a short sitemap that would suggest the vehicles are gone. It carries `X-Robots-Tag: noindex` and is cached for 1 hour.
5. **`/robots.txt`** (a file in the web root): allows everything and declares the sitemap. It is not used for security.
6. **`.htaccess`:** 301 redirects from `http://` and `www.` to `https://nextgenautosandparts.com`, keeping the path.
   * They apply only to that domain, so local copies are unaffected.
   * They never touch `/.well-known/`, so certificate renewal keeps working.
7. **Structured data** (JSON-LD data blocks, not affected by the Content-Security-Policy):
   * **Homepage:** Organization and WebSite.
   * **Contact page:** AutoDealer.
   * **Vehicle pages:** Car with an Offer.
   * **Sources:** only data the administrator entered and the page shows: phones, email, office address, social links (HTTPS only), each vehicle's price and specifications, and its own photos.
   * **Never added:** a country, opening hours, ratings or reviews. A reserved vehicle states no availability.

## Verified locally (2026-10-27)
* `seo_test.php`, 31/31:
  * the sitemap rules, checked with test vehicles inside a rolled-back transaction;
  * every public route is either listed or knowingly left out;
  * XML structure, robots.txt and metadata rules;
  * JSON-LD escaping and "nothing invented";
  * the redirect rules in `.htaccess`.
* All 28 suites pass.
* **Local crawl** (a PHP server set up like the `.htaccess` rewrite) of all sitemap addresses and the private pages:
  * every listed page answers 200 and is indexable, with exactly one title, one robots tag, one description, a correct canonical link and one H1;
  * no image without alt text; valid JSON-LD;
  * no broken internal link out of 78 (only private pages redirect to sign-in);
  * the sitemap XML parses.
* Chrome at 1366 and 375 px, 10/10: no console or CSP errors and no sideways scroll on the homepage, Contact, a vehicle, Parts and Privacy.
* **Live domain (read-only check):** `/robots.txt` and `/sitemap.xml` answer 404, `http://` is not redirected, and the homepage is a "Coming Soon" page. **This build is not deployed yet.**

## Remaining (owner)
* Deploy, then follow checklist H (SEO checks) and I (Google Search Console).
* Set `APP_URL=https://nextgenautosandparts.com` in the server's `.env`.
* **Favicon:** none exists yet (the page uses an empty placeholder). It needs a square icon from the logo designer (at least 512×512). It was not created here.
* The footer links to "Brands", which is still a "coming soon" page (`noindex`). Consider hiding the link until the page exists.
* Parts have no pages of their own, so individual parts cannot appear in search results (Spec 16 phase 2).
* The development database's test vehicles must not be copied to production; the sitemap lists whatever is published.
