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
