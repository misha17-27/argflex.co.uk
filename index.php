<?php
/**
 * Front controller. Every request that is not a real file lands here.
 * URLs mirror the original WordPress site so nothing has to be redirected.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

// a copy of the site on another host must not reach the search index
guard_copies();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$segs = array_values(array_filter(explode('/', trim($path, '/')), fn($s) => $s !== ''));

/** Match a slug that may still be percent-encoded in the URL. */
$resolve = function (callable $finder, string $seg) {
    return $finder($seg) ?? $finder(rawurldecode($seg)) ?? $finder(strtolower(rawurldecode($seg)));
};

/* ONE PAGE, ONE SPELLING.
 *
 * The lookup above is forgiving on purpose: it finds asfa-clamps whether the
 * address said ASFA-CLAMPS, Asfa-Clamps or asfa%2Dclamps. But it then SERVED
 * the page at whatever had been asked for, and everything keyed on the path
 * went wrong at once — data/seo.php had no record for that spelling, so the
 * frozen title was replaced by the template's; canonical_url() named the
 * address it was given; robots said index. Every product, category and
 * article was an unlimited supply of indexable duplicates of itself, each
 * under a different title.
 *
 * So a page that was found under a spelling other than the one it is stored
 * under is not served there: it sends the visitor to its own address. Only
 * for GET and HEAD — a form posted to an odd spelling is answered as before,
 * because a redirect would drop what was posted. The query string goes along.
 *
 * A missing slash at the end is NOT a different spelling here. /product/x
 * and /product/x/ have always both answered and that is left alone; compared
 * with the slash stripped from both.
 *
 * The stored spelling is whatever the catalogue holds, byte for byte. One
 * slug is stored percent-encoded in lower case (sandblast-hose-56-mm%c2%b3)
 * and that is the address the sitemap and every link use, so the raw
 * character goes THERE, not the other way about.
 *
 * %C2%B3 for %c2%b3 is the one difference that is NOT redirected, and it was
 * for an afternoon. RFC 3986 calls the two the same address and names the
 * capitals as the normal form, so a client is entitled to send capitals
 * whatever the link said — curl does, since 7.8x — and such a client, sent
 * to the lower-case spelling, asks for the capitals again and is sent again,
 * for ever. .data/crawl.py fetches with curl and could not reach the page at
 * all. So the two are served as one page, and the canonical names the stored
 * spelling whichever was asked for (stored_address() in inc/config.php).
 */
$own_address = function (string $stored) use ($path): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) return;
    if (rtrim($path, '/') === rtrim($stored, '/')) return;

    $escapes_folded = fn(string $address): string => rtrim((string) preg_replace_callback(
        '/%[0-9A-Fa-f]{2}/', fn(array $escape): string => strtoupper($escape[0]), $address), '/');
    if ($escapes_folded($path) === $escapes_folded($stored)) {
        stored_address($stored);
        return;
    }

    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . $stored . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
};

/* Listing tails WordPress hung off every archive it served. Declared before
   the switch because the category route reads it too. */
const WORDPRESS_TAILS = ['feed', 'rss', 'rss2', 'atom', 'amp', 'embed',
                         'trackback', 'print', 'attachment'];

/* THE CATEGORIES THE OLD SHOP HAD AND THIS ONE DOES NOT.
 *
 * WooCommerce served eleven that were empty and the migration dropped them
 * because there was nothing in them to show. Google still has the addresses,
 * so each goes to the nearest thing that exists: its parent, or the shop.
 *
 * This was a rule — "any unknown slug under /product-category/ goes up one" —
 * written as a rule so that it would also cover the ones nobody had found.
 * What it covered was everything: /product-category/anything-at-all/ answered
 * 301, so no address under that prefix could ever say "there is no such
 * page", and a typing mistake or a scraper's invention was reported back as a
 * redirect to keep. The eleven are known — they are every category address in
 * the WordPress export and the old sitemap that has no category here — so
 * they are listed, and anything else is the 404 it is.
 *
 * Where each one lands is still worked out when it is asked for — the parent
 * if this shop has it, the shop if not — so deleting a parent category in the
 * admin cannot leave one of these pointing at a 404.
 *
 * The bare /product-category/ is here as well as in Settings -> Advanced ->
 * Redirects, which is read first: the setting can be emptied, and the address
 * was WooCommerce's own.
 *
 * A category renamed from here on needs a line in that same setting.
 */
const LEGACY_CATEGORIES = [
    '',
    'composite-hoses',
    'industrial-rubber-sheets',
    'sandblasting',
    'uncategorized',
    'pvcpu-hoses/compressed-air-pvcpu-hoses',
    'pvcpu-hoses/suction',
    'pvcpu-hoses/wastewater',
    'rubber-hoses/compressed-air',
    'rubber-hoses/food-products',
    'rubber-hoses/sand-blasting',
    'rubber-hoses/steam',
];

// URLs the old site served under other names, editable under Settings -> Advanced
$redirects  = (array) setting('redirects');
$normalised = '/' . implode('/', $segs) . ($segs ? '/' : '');
if (isset($redirects[$normalised])) {
    header('Location: ' . $redirects[$normalised], true, 301);
    exit;
}

if (($segs[0] ?? '') === 'admin') {          // only reachable if rewrite rules are missing
    require ROOT_DIR . '/admin/index.php';
    exit;
}

$view = null;
$vars = [];

if (!$segs) {
    $view = 'home';
} else {
    switch ($segs[0]) {
        case 'robots.txt':
            // served by PHP only on a copy; the real host has the static file
            if (is_live_host()) break;
            header('Content-Type: text/plain; charset=utf-8');
            echo "User-agent: *\nDisallow: /\n\n# This is a copy of "
               . SITE_URL . ", kept out of the index on purpose.\n";
            exit;

        case 'sitemap.xml':
        case 'sitemap_index.xml':          // the Yoast name, kept so old links resolve
            require ROOT_DIR . '/pages/sitemap.php';
            exit;

        case 'shop':
            $view = 'shop';
            break;

        case 'product':
            if (isset($segs[1]) && ($p = $resolve('find_product', $segs[1]))) {
                if (count($segs) === 2) $own_address(product_url($p));
                $view = 'product';
                $vars['product'] = $p;
                break;
            }

            /* A LISTING RENAMED IN THE ADMIN.
             *
             * Renaming a product changes its slug, and the old address — which
             * is the one Google holds, and the one people have bookmarked —
             * simply starts answering 404. It happened to the submersible fuel
             * hose, which was this shop's best organic position: renamed to
             * …-0-5m-50m, and every click on the best result landed on an
             * error page until somebody noticed.
             *
             * A rule naming that one slug would be a rule to write again by
             * hand for the next rename, and it could only be right on the
             * server the rename happened on: the deploy leaves data/ alone
             * once the catalogue has been edited there, so a slug can be live
             * in one copy of this site and gone from another.
             *
             * So it is answered from the catalogue instead. A rename that adds
             * to the end of a slug — the shape every one of them has taken —
             * leaves exactly one product whose slug begins with the old one
             * and continues with a hyphen. Exactly one: two candidates mean a
             * guess, and a guess sending a buyer to the wrong hose is worse
             * than telling them the page has gone.
             */
            if (isset($segs[1])) {
                $asked = strtolower(rawurldecode($segs[1]));
                $heirs = [];
                foreach (all_products() as $candidate) {
                    if (str_starts_with((string) $candidate['slug'], $asked . '-')) {
                        $heirs[] = $candidate;
                    }
                }
                if (count($heirs) === 1) {
                    header('Location: ' . product_url($heirs[0]), true, 301);
                    exit;
                }
            }
            break;

        case 'product-category':
            $last = end($segs);
            if (count($segs) > 1 && $last && ($c = $resolve('find_category', (string) $last))) {
                /* Found by its last segment alone, so the same check that
                   catches RUBBER-HOSES catches a real category asked for
                   under a parent it does not have. */
                $own_address(category_url($c));
                $view = 'category';
                $vars['category'] = $c;
                break;
            }

            $asked = strtolower(rawurldecode(implode('/', array_slice($segs, 1))));

            // one the old shop had: see LEGACY_CATEGORIES above
            if (in_array($asked, LEGACY_CATEGORIES, true)) {
                $up = count($segs) > 2 ? find_category((string) strtok($asked, '/')) : null;
                header('Location: ' . ($up ? category_url($up) : '/shop/'), true, 301);
                exit;
            }

            /* /product-category/rubber-hoses/feed/ — WordPress advertised one
               for every category, and the old catch-all sent them to the
               category by accident of "go up one". Kept, on purpose now. */
            if (count($segs) > 2 && in_array(strtolower(rawurldecode((string) $last)), WORDPRESS_TAILS, true)
                && ($feedOf = $resolve('find_category', (string) $segs[count($segs) - 2]))) {
                header('Location: ' . category_url($feedOf), true, 301);
                exit;
            }

            // anything else was never a category: the 404 below answers
            break;

        // Attribute archives — /inner-diameter/8mm/ and /length/50m/. Thirty
        // five of these are indexed on the live site and used to 404 here,
        // which is the ordinary way a migration loses its rankings.
        case 'inner-diameter':
        case 'length':
            if (isset($segs[1]) && ($t = find_attribute_term($segs[0], $segs[1]))) {
                /* An archive with ONE product in it goes to that product.
                   Twelve of the thirty-five are like this, and such a page is
                   strictly worse than the one it lists: the same single item,
                   described in less detail, with no price and nothing to buy.
                   The address keeps working and whatever it was worth goes to
                   the page that can take an order. It stops redirecting by
                   itself the day a second product carries the size. */
                if ($lone = attribute_term_lone_product($segs[0], (string) $t['slug'])) {
                    header('Location: ' . product_url($lone), true, 301);
                    exit;
                }
                $view = 'attribute';
                $vars['term'] = $t;
            }
            break;

        case 'blog':
            $view = 'blog';
            break;

        case 'about-us':      $view = 'about';          break;
        case 'contacts':      $view = 'contacts';       break;
        case 'contact-send':  require ROOT_DIR . '/pages/contact-send.php'; exit;
        case 'coupon-check':  require ROOT_DIR . '/pages/coupon-check.php'; exit;
        case 'review-send':   require ROOT_DIR . '/pages/review-send.php'; exit;
        case 'chat-lead':     require ROOT_DIR . '/pages/chat-lead.php'; exit;
        case 'cart':          $view = 'cart';           break;
        case 'checkout':      $view = 'checkout';       break;
        case 'wishlist':      $view = 'wishlist';       break;
        case 'compare':       $view = 'compare';        break;
        case 'refund_returns':
        case 'refund-returns': $view = 'refund-returns'; break;
        /* The three that answered 404 while the checkout collected names,
           addresses and telephone numbers with no privacy notice anywhere on
           the site — and while the consent banner offered a link to nothing. */
        case 'privacy':        $view = 'privacy';        break;
        case 'delivery':       $view = 'delivery';       break;
        case 'terms':          $view = 'terms';          break;
        /* /my-account/orders/, /my-account/details/ and the rest. The section
           is a path segment rather than a query string because these are
           pages a person bookmarks and reads back to somebody. */
        case 'my-account':
            $view = 'my-account';
            $vars['section'] = strtolower($segs[1] ?? '');
            $vars['ref']     = (string) ($segs[2] ?? '');
            break;

        default:
            if (count($segs) === 1 && ($post = $resolve('find_post', $segs[0]))) {
                $own_address(post_url($post));
                $view = 'post';
                $vars['post'] = $post;
            }
    }
}

/* AN ADDRESS WITH MORE TO IT THAN THE PAGE IT NAMED.
 *
 * The switch above reads $segs[0] and, for most routes, nothing after it — so
 * /shop/anything/at/all/ fell into `case 'shop'` and answered 200 with the
 * shop, and canonical_url() had it name ITSELF canonical. That is an unlimited
 * supply of indexable duplicates, one per spelling, and the spellings are not
 * hypothetical: WordPress advertised /feed/ with a <link rel="alternate"> on
 * every archive it served, so /shop/feed/ and /blog/feed/ are addresses Google
 * genuinely holds. The .htaccess rule added for /page/2/ anchors immediately
 * after the number and catches none of them.
 *
 * The tails WordPress itself hung off a listing are sent to the listing, which
 * is where their value belongs. Anything else was never a page here and says
 * so. product-category is left out on purpose — its depth varies with the
 * category tree and it has a rule of its own above.
 */
const ROUTE_DEPTH = [
    'shop' => 1, 'blog' => 1, 'cart' => 1, 'checkout' => 1, 'wishlist' => 1,
    'compare' => 1, 'about-us' => 1, 'contacts' => 1,
    'refund_returns' => 1, 'refund-returns' => 1,
    // or /privacy/feed/ serves a duplicate — the fault closed a week ago
    'privacy' => 1, 'delivery' => 1, 'terms' => 1,
    'product' => 2, 'inner-diameter' => 2, 'length' => 2,
];
// WORDPRESS_TAILS is declared above the switch, which needs it first.

if ($view !== null && $segs) {
    $depth = ROUTE_DEPTH[$segs[0]] ?? 0;
    if ($depth > 0 && count($segs) > $depth) {
        $tail = strtolower(rawurldecode((string) $segs[$depth]));
        if (in_array($tail, WORDPRESS_TAILS, true)) {
            header('Location: /' . implode('/', array_slice($segs, 0, $depth)) . '/', true, 301);
            exit;
        }
        $view = null;      // not a page; the 404 below answers for it
    }
}

if ($view === null) {
    http_response_code(404);
    $view = '404';
}

extract($vars, EXTR_SKIP);
require ROOT_DIR . "/pages/{$view}.php";
