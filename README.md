# argflex.co.uk — redesign (18.08.2026)

Redesign of [argflex.co.uk](https://argflex.co.uk/) (currently WordPress + WooCommerce, Woodmart theme)
into a fast static/PHP front end.

**Stage 1 (this commit): three homepage concepts in plain HTML/CSS.**

## Files

| File | What it is |
|---|---|
| `index.html` | Concept chooser — open this first |
| `home-v1.html` | **Version 1 — "Industrial Precision"** (navy + orange, photo hero, filterable grid) — **selected as the base**, now also carries the marquee ticker from v3 and the "Industries we supply" row from v2, both restyled into the v1 palette |
| `home-v2.html` | **Version 2 — "Technical Catalogue"** (light, Swiss grid, blue, hose finder + quote form) |
| `home-v3.html` | **Version 3 — "Dark Engineering"** (dark theme, lime/cyan, bento grid, product rail) |
| `assets/css/v1.css` `v2.css` `v3.css` | One stylesheet per concept |
| `assets/img/site/` | Logo, hero, about photo |
| `assets/img/products/` | 24 product photos taken from the live site |

Open `index.html` in a browser — no build step, no server required.

## Content source

Everything on the pages is real data pulled from the live site via the WooCommerce Store API
and the Yoast sitemaps:

- **36 products**, live price ranges (excl. VAT), real product photos
- **12 categories**: Rubber hoses (26), PVC/PU hoses (9), Hose couplings (4) + subcategories
- **20 blog posts**, 8 pages (`/shop/`, `/about-us/`, `/contacts/`, `/blog/`, `/wishlist/`, `/refund_returns/`, `/compare/`)
- Real contact details, opening hours and address

All internal links already point at the existing URL structure
(`/product-category/…/`, `/product/…/`, `/blog/`, `/contacts/`), so nothing breaks
when the front end is swapped and SEO/URLs are preserved.

## Performance approach

The current WordPress site loads Woodmart + WooCommerce + Elementor assets on every page.
These concepts deliberately ship almost nothing:

- No jQuery, no Bootstrap, no icon font, no Google Fonts (system font stack)
- One CSS file per page (~8–12 KB uncompressed), no CSS framework
- Under 40 lines of inline JavaScript per page (tab filter / carousel scroll / counters)
- All inline SVG icons — zero icon requests
- `loading="lazy"` on every below-the-fold image, `fetchpriority="high"` + `<link rel=preload>` on the hero
- Explicit `width`/`height` on images → no layout shift
- `prefers-reduced-motion` respected

## Next steps

1. Pick one of the three concepts (or mix sections between them).
2. Convert it to PHP: `header.php`, `footer.php`, `partials/product-card.php`, `partials/category-tile.php`.
3. Move products/categories/posts into MySQL (or a generated JSON/PHP array if the catalogue stays this size).
4. Rebuild the inner pages: shop + category listing with filters, product page, blog, about, contacts, cart/checkout.
5. Add caching (OPcache + full-page HTML cache), WebP/AVIF conversion of product photos, Brotli/gzip.
6. Keep the existing URLs and 301 anything that changes; re-submit the sitemap.
