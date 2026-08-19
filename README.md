# argflex.co.uk — redesign (18.08.2026)

Rebuild of [argflex.co.uk](https://argflex.co.uk/) — currently WordPress + WooCommerce
(Woodmart theme) — as a fast, data-driven PHP site.

**Status: the whole site is built.** 90 pages, every internal link resolving,
metadata verified identical to the live site.

## Running it

Double-click **`start-server.bat`** — it starts PHP and opens the site in your
browser. Close the window to stop it.

Or from a terminal:

```bash
php -S localhost:8124 -t . router.php
```

Then open <http://localhost:8124/>. The batch file looks for PHP at
`D:\argflex\php\php.exe` and falls back to `php` on PATH.

On a normal Apache host just upload the folder — `.htaccess` handles routing,
compression and cache headers. Requires PHP 8.1+.

## Structure

| Path | What it is |
|---|---|
| `index.php` | Front controller — parses the URL and includes the right page |
| `router.php` | Same routing for PHP's built-in server |
| `.htaccess` | Apache rewrite, gzip/brotli, cache headers, security headers |
| `inc/config.php` | Data access, price/URL helpers, page state |
| `inc/header.php`, `inc/footer.php` | Shared chrome, nav, drawer, breadcrumbs |
| `partials/` | `product-card.php`, `post-card.php` |
| `pages/` | One file per page type |
| `data/` | Generated PHP arrays: products, categories, posts |
| `assets/` | `css/site.css`, `js/site.js`, images |
| `admin/` | Admin panel: front controller, auth, views, its own stylesheet |
| `storage/` | Orders, settings and the admin account. Denied over HTTP, git ignored |
| `.data/` | Raw API dumps + the build and crawl scripts (not for the server) |

## Pages

| URL | Page |
|---|---|
| `/` | Homepage — hero, categories, featured grid, ticker, industries, blog, CTA |
| `/shop/` | Full catalogue: search, category filter, price slider, sorting |
| `/product-category/{slug}/` and `/{parent}/{child}/` | 12 category listings with subcategory chips and sorting |
| `/product/{slug}/` | 37 product pages: gallery, spec table, variant picker with live total, tabs, related |
| `/blog/` | Lead article + grid of the remaining 19 |
| `/{post-slug}/` | 20 articles with share links and prev/next |
| `/about-us/` | Company, process steps, stats |
| `/contacts/` | Contact cards, enquiry form, map |
| `/cart/` | Cart with quantities, VAT and delivery calculation |
| `/checkout/` | Delivery details, order summary, order placed and stored |
| `/wishlist/` | Saved products |
| `/compare/` | Two products side by side on their specs |
| `/my-account/` | Sign-in and trade account pitch |
| `/refund_returns/` | Refund and returns policy |
| `/sitemap.xml` | Generated from the catalogue |
| `/robots.txt` | Static |
| anything else | 404 with search and category links |

URLs match the original WordPress structure exactly, so nothing needs redirecting
and existing search rankings carry over.

## Data

`data/*.php` is generated from the live site by `.data/build_data.py`:

- **37 products** with 151 priced variants, categories, images and full descriptions
- **12 categories** with parent/child relationships and counts
- **20 blog posts** with body copy and featured images
- Prices are stored in pence as integers, so no floating point rounding

The generator also repairs the double-encoded characters the live site serves
(`-40Â°C` becomes `-40°C`) and demotes `<h1>` inside imported body copy so each
page has exactly one.

To refresh from the live site:

```bash
python .data/build_data.py
```

## Cart, mini cart and checkout

The basket lives in `localStorage` — no session, no database, nothing to install.
Adding a product slides out a mini cart with the lines, the subtotal and a route
straight to checkout; the header cart icon opens the same panel. The cart page
computes subtotal, 20% VAT and delivery (free over £250 excl. VAT).

Checkout posts the basket back with the form and **re-prices every line from
`data/products.php`** — the browser never gets to decide what anything costs, so a
tampered basket is simply repriced. Valid orders are written to
`storage/orders/{reference}.json` (the folder is denied over HTTP by its own
`.htaccess`) and the customer lands on a confirmation carrying the reference.

No card details are collected: the flow ends with a proforma invoice, which is
what the business actually does today. Wiring a payment provider means adding a
step after the order is stored — the templates do not change.

## SEO — keeping the existing rankings

The single biggest risk in this migration is losing positions Google already
holds, so metadata is not rewritten, it is **copied from the live site**.
`.data/fetch_seo.py` fetches all 80 live URLs and stores their `<title>`,
description and canonical in `data/seo.php`. `set_page()` then lets the live
value win over anything a template would have produced.

`.data/check_seo.py` re-fetches every page from the local server and diffs it
against the saved live HTML. Last run:

| | |
|---|---|
| URLs compared | 75 (basket and account pages excluded by design) |
| Titles identical to live | **75 / 75** |
| Descriptions identical to live | **58 / 58** the live site has |
| Descriptions added where live had none | 17 |
| Canonical present | 75 / 75 |
| Differences | **0** |

Basket and account pages keep our own titles and are `noindex, follow` — the
live site serves `/checkout/` under the title "Cart" because WooCommerce
redirects it, and copying that across would be wrong. This matches what
WooCommerce does by default and costs nothing, because those pages do not rank.

Added on top, none of which the live site had:

- `rel=canonical` on every page
- Open Graph and Twitter card tags, using the product or article image
- JSON-LD: `Organization` sitewide, `Product` with real price offers,
  `Article` on posts, `BreadcrumbList` wherever there are breadcrumbs,
  `WebSite` with a search action on the homepage
- `sitemap.xml` generated from the catalogue, `/sitemap_index.xml` kept as an
  alias so the URL Yoast registered still resolves
- `robots.txt` disallowing basket and account paths
- 301s for URL shapes the WordPress site also answered
  (`/contact/`, `/about/`, `/refund-returns/`, `/news/`, `/products/`)
- `noindex` on the 404 page

## Admin panel

`/admin/` manages the whole site. On first visit it asks you to create the
account, which is written to `storage/users.php` — a file the server denies and
git ignores.

| Screen | What it does |
|---|---|
| Dashboard | Order and catalogue counts, ordered value, latest orders |
| Orders | Filter by status, open one, set status (new → confirmed → invoiced → shipped / cancelled), add an internal note, delete |
| Products | Search, edit name, slug, SKU, descriptions, categories, images, single price or a list of priced options; add and delete |
| Categories | Edit names, slugs, parents and descriptions inline; add and remove |
| Pages | Every fixed page's wording — headings, intros, buttons, the trust strip, the ticker, the numbers, the policy text — with that page's title and description beside it |
| Blog | Write, edit and delete posts with cover image and date |
| Images | Upload to `assets/img/…` and copy the path for use elsewhere |
| SEO | Title, description and canonical for any URL, including products, categories and posts |
| Settings | Contact details, opening hours, VAT rate, delivery charge and free-delivery threshold |

Editing writes back to `data/*.php`. Every write goes to a temp file, is parsed
to confirm it still returns an array, and only then renamed over the target —
so a failed save can never take the site down with a half-written file.

Page copy works by exception: templates call `page_text($path, $key, $default)`
with the wording they ship, and only fields actually edited are stored in
`data/pages.php`. The editor shows the shipped wording as the field's
placeholder, and clearing a field restores it. `page_text()` escapes what it
prints; the two fields meant to carry markup — the homepage heading and the
returns policy — go through `page_raw()`.

Settings feed the site rather than sitting in a file nobody reads: changing the
VAT rate or the delivery threshold updates the cart, the checkout summary and
the server-side order pricing together, and bumps the asset version so visitors
see it immediately.

### Security

- Passwords hashed with `password_hash`; login compares a hash even when the
  account does not exist, so a wrong address is not faster than a wrong password
- Eight failed attempts locks that IP out for fifteen minutes
- Session cookie is HttpOnly, SameSite=Strict, Secure over HTTPS, scoped to
  `/admin/`, with the id rotated on login and every 30 minutes
- Every POST carries a CSRF token; a missing or forged one is refused with 419
- Order references are matched against `[A-Za-z0-9-]{4,32}`, so no path traversal
- Uploads are checked by reading the image itself, not by trusting the filename;
  `assets/img/.htaccess` refuses to execute anything in there
- `data/`, `inc/`, `pages/`, `partials/` and `storage/` are denied over HTTP,
  and every generated PHP file 404s if requested directly
- The admin is `noindex, nofollow` and disallowed in `robots.txt`

Verified: every route redirects to login without a session; POSTs without a
token return 419; a forged token returns 419; `../../inc/config` as an order
reference returns 404; a PHP script renamed `.jpg` is rejected on upload while a
real JPEG is accepted.

## Performance

- No jQuery, Bootstrap, icon fonts or Google Fonts — system font stack
- One CSS file (43 KB) and one JS file (16 KB) for the entire site
- All icons are inline SVG — zero icon requests
- `loading="lazy"` below the fold, `fetchpriority="high"` + preload on each page's hero
- Explicit `width`/`height` on every image, so layout shift stays at zero
- Product images pulled at 768 px rather than the 1536 px originals
- `?v=` asset versioning so CSS/JS can be cached for a year
- `prefers-reduced-motion` respected

## Verification

`.data/crawl.py` walks every URL on the local server and checks for PHP
errors/warnings/notices, HTTP status, exactly one `<h1>`, missing `<title>`,
missing asset files and dead internal links:

```bash
python .data/crawl.py
```

Last run: **90 pages, 79 links, 0 problems.**

### Responsive sweep

`.data/responsive-check.js` pastes into the browser console and loads every
page in a fixed-width iframe, reporting any that push the document wider than
the viewport and naming the elements responsible. Every template is checked at
320, 360, 390, 768, 900, 1024 and 1280px; every remaining URL at 320px, the
width that breaks first.

Last run: **235 checks, 0 problems.** It found two real faults, both fixed:

- the product selects on `/compare/` were sized by their longest option, so
  they pushed the page 87px wide at 320px
- a table inside one imported article overflowed by 70px at 320px

Marquee, off-canvas panels, the v3 carousel and table scrollers are excluded —
they are clipped on purpose.

## Design concepts

The three original homepage concepts are kept for reference:

- `concepts.html` — the chooser
- **v1 "Industrial Precision"** — selected, and now the live homepage (`index.php`)
- `home-v2.html` — "Technical Catalogue"
- `home-v3.html` — "Dark Engineering"

## Next steps

1. Wire the contact form and new-order notifications to a mail handler.
2. Add a payment provider after the order is stored, if card payment is wanted.
3. Convert product images to AVIF alongside WebP.
4. Add a `sitemap.xml` generator and re-submit it after go-live.
