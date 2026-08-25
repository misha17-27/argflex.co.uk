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
| `data/` | Generated PHP arrays: products, categories, attributes, coupons, posts, pages, SEO |
| `inc/commerce.php` | Currency, tax, delivery zones, payment methods and the email template |
| `assets/` | `css/site.css`, `js/site.js`, images |
| `admin/` | Admin panel: front controller, auth, views, reports, its own stylesheet |
| `storage/` | Orders, settings and the admin account. Denied over HTTP, git ignored |
| `.data/` | Raw API dumps + the build and crawl scripts (not for the server) |

## Pages

| URL | Page |
|---|---|
| `/` | Homepage — hero, categories, featured grid, ticker, industries, blog, CTA |
| `/shop/` | Full catalogue: search, category filter, price slider, sorting, paging |
| `/product-category/{slug}/` and `/{parent}/{child}/` | 12 category listings with subcategory chips and sorting |
| `/product/{slug}/` | 37 product pages: gallery, spec table, variant picker with live total, tabs, reviews, upsells and related |
| `/blog/` | Lead article + grid of the remaining 19 |
| `/{post-slug}/` | 20 articles with share links and prev/next |
| `/about-us/` | Company, process steps, stats |
| `/contacts/` | Contact cards, enquiry form, map |
| `/cart/` | Cart with quantities, VAT, delivery and a discount code box, with cross-sells beneath |
| `/checkout/` | Delivery details, order summary, order placed and stored |
| `/wishlist/` | Saved products |
| `/compare/` | Two products side by side on their specs |
| `/my-account/` | Customer accounts: register, sign in, order history, saved delivery details that prefill the checkout, change password, reset a forgotten one by email |
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
| Reviews | Star ratings with moderation: pending, published or spam, bulk actions, a tally in the sidebar, and a badge for anyone whose email matches a real order |
| System status | What the server offers, which folders are writable, which are denied over HTTP, and what is still unset — a tick per line before going live |
| Reports | Revenue, orders, average order, customers and items over 7, 30 or 90 days, 12 months or all time; a bar chart per day or per month; best sellers and categories by value; where the money splits between goods, discounts, delivery and tax; order status, delivery zones and codes used. CSV of every order in the range |
| Customers | Assembled from the orders and enquiries on file — there are no accounts to manage. Search by name, company or town, sort by spend, orders or date; each person's page shows their orders, their enquiries and what they actually buy. CSV export |
| Orders | Filter by status, open one, set status (new → confirmed → invoiced → shipped → refunded / cancelled), edit the lines — change a quantity, drop one, add one at today's price — with the totals worked out again at the rate the order was placed on, record refunds against it, print the invoice or the delivery note, add an internal note, delete |
| Products | Sale prices with a start and end date, stock quantities with low-stock and backorders, sold-individually, weight and dimensions, a shipping class, upsells and cross-sells, a purchase note and a catalogue position — plus search and filter by category, type and stock; sort by name, price or date; tabs for published, drafts, featured and out of stock; tick rows for bulk publish, draft, feature, stock or delete; CSV import and export; the editor covers name, permalink, SKU, both descriptions, attributes with a one-click option builder, prices, tags, categories with a primary, an image library picker, status, stock, date, featured, its own Google preview with SEO title/description/robots/canonical, plus duplicate and delete |
| Categories | Add form beside a searchable list: name, slug, parent, description, image from the library, order, and that category's SEO title, description and robots. Columns for image, SEO indicator dots, slug, product count and order; bulk delete |
| Discount codes | Percentage or fixed-amount codes with a minimum and maximum order, a date range, a usage limit and an optional limit to certain products or categories; free delivery as a flag. The list flags which are live, expired, not yet started or used up |
| Attributes | Global attributes with their terms — define Length or Inner Diameter once and reuse it. Custom, alphabetical or numeric term ordering, and a count of how many products use each |
| Pages | Every fixed page's wording — headings, intros, buttons, the trust strip, the ticker, the numbers, the policy text — with that page's title and description beside it |
| Blog | Write, edit and delete posts with cover image and date |
| Images | Upload to `assets/img/…` and copy the path for use elsewhere |
| SEO | Title, description, robots and canonical for any URL, including products, categories and posts |
| Enquiries | Every message sent from the site, unread first, with mark-read and delete |
| Security | Cloudflare Turnstile keys, and what protection is already in place |
| Users | Multiple accounts with an Administrator or Editor role |
| Settings | Six tabs — see below |

### Settings

| Tab | What it covers |
|---|---|
| Products | Reviews on or off with approval and verified-buyer rules, default catalogue sorting, the shop notice, wishlist and compare on or off, whether new products track stock, the low-stock threshold, whether a quantity is shown, whether out-of-stock lines are hidden, and the weight and dimension units |
| General | Contact details and opening hours, the store address, which countries the shop sells and delivers to, and the currency — symbol, position, separators and decimal places |
| Tax | Whether tax is charged at all, the standard rate, what it is called, the note beside catalogue prices, and rules that override the rate for named countries — with a catch-all for everywhere else, which is how zero-rated export is expressed |
| Shipping | Delivery zones, each with its countries and its methods (flat rate, free, collection, quoted); shipping classes with a per-method surcharge; plus a table showing what four sample orders would be quoted right now |
| Payments | The methods offered at checkout, each with a title, a description and the instructions that go into the confirmation email |
| Emails | Five notifications with their recipients, subjects and headings; the sender address; SMTP with a one-click test; and the template — logo, four colours and the footer — with a live preview of a real order |
| Advanced | The store page URLs, the terms page linked from the checkout, the 301 map for the old WordPress URLs, and the asset cache stamp |

Everything on those tabs actually drives the site rather than sitting in a
file. Change the currency and the catalogue reprints in it; add a delivery
zone and the basket, the checkout and the stored order all use it; switch a
payment method on and it appears at checkout, gets stored with the order and
its instructions land in the customer's email.

A sale price only counts while its dates allow it and only when it is actually
below the regular price, so a leftover figure cannot quietly discount anything.
The struck-through price, the percentage flash, the option table, the picker and
the server all read the same helper, which is why the basket and the stored
order can never disagree with what the page showed.

Stock has the last word on the server: a browser can ask for any quantity, and
`price_basket_lines()` caps it at what is left, refuses a line with none and no
backorders, and holds a sold-individually product to one.

Discount codes are checked in one place — `coupon_apply()` — so the cart, the
checkout and the stored order can never disagree about what a code is worth.
The browser only ever says *which* code to try: `/coupon-check` re-prices the
basket from the catalogue before judging it, and the checkout runs the whole
check again before an order is written. A code posted straight at the server
after it expired is simply ignored, and the usage count only moves when an
order is actually stored.

Delivery works the way WooCommerce zones do: a customer falls into the first
zone that lists their country — one with no countries is the catch-all — and
within it the site quotes the **cheapest method the order qualifies for**. A
"free over £250" rule is therefore just a free method with a £250 minimum,
which automatically beats the flat rate once the basket is big enough. The
browser runs the same rule for the running total, and the server runs it again
from scratch when the order is stored.

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

### Customer accounts

Separate from the admin's login in every way that matters: its own file, its
own cookie name, its own session. A customer session cannot reach the admin
panel. Passwords are hashed with `password_hash`, ten characters minimum, and
sign-in compares a hash even when there is no such account so a wrong address
is not faster than a wrong password. A forgotten-password link is a hashed
one-shot token good for an hour, and the site gives the same answer whether or
not the address has an account — otherwise the form would tell anybody who
asked which of their customers shop here.

Nobody needs an account to order. It saves retyping a delivery address and
shows what has been ordered, and that is all it is for. A visitor with no
session cookie is treated as anonymous without being given one, so passers-by
are not handed a cookie that would need explaining in a banner.

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
- `data/`, `inc/`, `pages/`, `partials/`, `storage/` and `.data/` are denied
  over HTTP, and every PHP file under `data/` 404s if requested directly —
  including the ones the admin rewrites, which used to lose that guard
- A view is included with only its own variables in scope, so a name it shares
  with the layout cannot silently belong to whichever ran last
- The admin is `noindex, nofollow` and disallowed in `robots.txt`

Verified: every route redirects to login without a session; POSTs without a
token return 419; a forged token returns 419; `../../inc/config` as an order
reference returns 404; a PHP script renamed `.jpg` is rejected on upload while a
real JPEG is accepted.

## Performance

- No jQuery, Bootstrap, icon fonts or Google Fonts — system font stack
- Charts are inline SVG rectangles, so Reports pulls in no library at all
- One CSS file (44 KB) and one JS file (17 KB) for the entire site, built by
  `python .data/build_css.py` from `v1.css`, `pages.css` and `checkout.css`
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

It also checks statically that no page calls a function from an include it
never required. That is a fatal error on one code path only, so a crawl of GET
requests walks straight past it — it had already shipped four times.

`.data/check_admin.py` walks every admin screen behind a login and fails on a
non-200, a PHP notice or a view that renders without its layout. A route can
otherwise be lost while the sidebar keeps linking to it, which is how the Blog
screen 404ed for two commits before this check existed:

```bash
ARGFLEX_ADMIN_EMAIL=you@example.com ARGFLEX_ADMIN_PASSWORD=... python .data/check_admin.py
```

Last run: **27 screens, 0 problems.**

`.data/test_coupons.py` drives discount codes end to end — creating them in the
admin, every rule that can reject one, a tampered basket, and an order that
uses one. Last run: **41 checks, all passing.**

`.data/test_customers.py` places real orders, checks the list folds them into
people, and cleans up after itself. Last run: **30 checks, all passing.**

`.data/test_reports.py` places orders, backdates one out of range, cancels
another, and checks every figure, the chart, the rankings and the CSV. Last
run: **31 checks, all passing.**

`.data/test_linked.py` links two products, checks the upsells appear on one
page and not another, that the cross-sell endpoint drops what is already in the
basket and anything out of stock, and that the viewed product survives the card
loops. Last run: **20 checks, all passing.**

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

## Going live

**[DEPLOY-CPANEL-GIT.md](DEPLOY-CPANEL-GIT.md)** is the way to do it: cPanel
clones this repository and copies it into place on a button press, so there is
no password to type and every deploy is a named commit.
**[UPLOAD-FTP.md](UPLOAD-FTP.md)** covers the same thing over FTP instead. **[DEPLOY.md](DEPLOY.md)** is the full checklist: what to upload and what to leave
behind, permissions, the first five minutes in the admin, switching the domain
over, and what to look at when something misbehaves.

Before uploading anything, run everything at once:

```bash
ARGFLEX_ADMIN_EMAIL=you@example.com ARGFLEX_ADMIN_PASSWORD=... python .data/preflight.py --full
```

It lints every PHP file, rebuilds the stylesheet and checks it was already
current, crawls all 90 pages, diffs the metadata against the live site, walks
all 30 admin screens, and runs the discount-code, customer and report suites.
It refuses to say "ready" unless all nine pass.

Once it is uploaded, check it from the outside:

```bash
python .data/check_deploy.py https://new.argflex.co.uk
```

That fetches the real address and reports what a browser and a search engine
get — pages, refused folders, assets and cache headers, and whether a staging
copy is properly kept out of the index.

**System status** in the admin then reports what the server
itself is missing — PHP version and extensions, writable folders, private
folders that are actually private, SMTP, Turnstile.

## Next steps

1. Fill in the SMTP details under Settings → Emails and send the test.
2. Add the Turnstile keys under Security.
3. Add a payment provider after the order is stored, if card payment is wanted.
4. Convert product images to AVIF alongside WebP.
5. Re-submit `sitemap.xml` after go-live.
