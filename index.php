<?php
/**
 * Front controller. Every request that is not a real file lands here.
 * URLs mirror the original WordPress site so nothing has to be redirected.
 */
declare(strict_types=1);

require __DIR__ . '/inc/config.php';

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

        case 'blog':
            $view = 'blog';
            break;

        case 'about-us':      $view = 'about';          break;
        case 'contacts':      $view = 'contacts';       break;
        case 'contact-send':  require ROOT_DIR . '/pages/contact-send.php'; exit;
        case 'coupon-check':  require ROOT_DIR . '/pages/coupon-check.php'; exit;
        case 'cart':          $view = 'cart';           break;
        case 'checkout':      $view = 'checkout';       break;
        case 'wishlist':      $view = 'wishlist';       break;
        case 'compare':       $view = 'compare';        break;
        case 'refund_returns':
        case 'refund-returns': $view = 'refund-returns'; break;
        case 'my-account':    $view = 'my-account';     break;

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
