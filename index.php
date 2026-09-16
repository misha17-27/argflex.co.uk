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
            if ($last && ($c = $resolve('find_category', (string) $last))) {
                $view = 'category';
                $vars['category'] = $c;
                break;
            }

            /* A category the old shop had and this one does not.
               WooCommerce served eleven that were empty — composite-hoses,
               industrial-rubber-sheets, rubber-hoses/steam and the rest — and
               the migration dropped them because there was nothing in them to
               show. Google still has the addresses.

               Sending them to the parent rather than answering 404 keeps
               whatever those pages were worth, and keeps Search Console from
               reporting a site full of dead category URLs. The parent is one
               hop away and known to exist; anything else goes to the shop, so
               there is no second redirect to follow and no loop to fall into.

               A rule rather than a list, because it also covers the ones I
               have not found and any category renamed from here on. */
            $up = count($segs) > 2 ? $resolve('find_category', (string) $segs[count($segs) - 2]) : null;
            header('Location: ' . ($up ? category_url($up) : '/shop/'), true, 301);
            exit;

        // Attribute archives — /inner-diameter/8mm/ and /length/50m/. Thirty
        // five of these are indexed on the live site and used to 404 here,
        // which is the ordinary way a migration loses its rankings.
        case 'inner-diameter':
        case 'length':
            if (isset($segs[1]) && ($t = find_attribute_term($segs[0], $segs[1]))) {
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
const WORDPRESS_TAILS = ['feed', 'rss', 'rss2', 'atom', 'amp', 'embed',
                         'trackback', 'print', 'attachment'];

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
