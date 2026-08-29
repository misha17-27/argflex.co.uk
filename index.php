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
            }
            break;

        case 'product-category':
            $last = end($segs);
            if ($last && ($c = $resolve('find_category', (string) $last))) {
                $view = 'category';
                $vars['category'] = $c;
            }
            break;

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

if ($view === null) {
    http_response_code(404);
    $view = '404';
}

extract($vars, EXTR_SKIP);
require ROOT_DIR . "/pages/{$view}.php";
