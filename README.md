# argflex.co.uk — redesign (18.08.2026)

Rebuild of [argflex.co.uk](https://argflex.co.uk/) — currently WordPress + WooCommerce
(Woodmart theme) — as a fast, data-driven PHP site.

**Status: the whole site is built.** 86 pages, every internal link resolving.

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
| `/wishlist/` | Saved products |
| `/compare/` | Two products side by side on their specs |
| `/my-account/` | Sign-in and trade account pitch |
| `/refund_returns/` | Refund and returns policy |
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

## Cart and wishlist

Both live in `localStorage` — no session, no database, nothing to install. The cart
computes subtotal, 20% VAT and delivery (free over £250 excl. VAT). The wishlist
page asks `wishlist-items.php` to render cards for the saved slugs.

Connecting a real checkout later means replacing `assets/js/site.js`'s storage layer
and adding an order endpoint; the templates do not change.

## Performance

- No jQuery, Bootstrap, icon fonts or Google Fonts — system font stack
- One CSS file (35 KB) and one JS file (11 KB) for the entire site
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

Last run: **86 pages, 78 links, 0 problems.**

## Design concepts

The three original homepage concepts are kept for reference:

- `concepts.html` — the chooser
- **v1 "Industrial Precision"** — selected, and now the live homepage (`index.php`)
- `home-v2.html` — "Technical Catalogue"
- `home-v3.html` — "Dark Engineering"

## Next steps

1. Wire the contact and enquiry forms to a mail handler.
2. Connect a real checkout and payment provider.
3. Convert product images to AVIF alongside WebP.
4. Add a `sitemap.xml` generator and re-submit it after go-live.
