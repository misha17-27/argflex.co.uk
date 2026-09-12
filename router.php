<?php
/**
 * Router for the PHP built-in server:  php -S localhost:8124 router.php
 * Serves real files as-is, sends everything else to the front controller.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . urldecode($path);

// mirror what .htaccess denies in production
foreach (['/storage', '/data', '/.data', '/inc', '/partials', '/pages'] as $private) {
    if (str_starts_with($path, $private . '/') || $path === $private) {
        http_response_code(404);
        echo 'Not found';
        return true;
    }
}

// The 301s .htaccess serves for the addresses WordPress used. Mirrored for the
// same reason robots.txt is below: a redirect that exists only in .htaccess
// cannot be checked until it is live. /blog/page/2/ answering 200 with page
// one's content, and naming itself canonical, is exactly the fault that
// reached production because nothing here could see it — the rule covered
// shop, length and inner-diameter and had never listed blog.
$legacy = [
    '#^/product-category/(.+?)/page/[0-9]+/?$#'           => '/product-category/$1/',
    '#^/(shop|blog|length|inner-diameter)/page/[0-9]+/?$#' => '/$1/',
    '#^/(length|inner-diameter)/([^/]+)/page/[0-9]+/?$#'   => '/$1/$2/',
    '#^/category/[^/]+(?:/page/[0-9]+)?/?$#'               => '/blog/',
    '#^/author/[^/]+(?:/page/[0-9]+)?/?$#'                 => '/blog/',
];
foreach ($legacy as $pattern => $to) {
    if (preg_match($pattern, $path)) {
        header('Location: ' . preg_replace($pattern, $to, $path), true, 301);
        return true;
    }
}

// .htaccess routes robots.txt through PHP on any host that is not the real
// shop, so a copy can say Disallow: /. Mirror that here or local testing
// would not match production.
if ($path === '/robots.txt') {
    $host = strtolower(strtok((string) ($_SERVER['HTTP_HOST'] ?? ''), ':'));
    if (!in_array($host, ['argflex.co.uk', 'www.argflex.co.uk'], true)) {
        require __DIR__ . '/index.php';
        return true;
    }
}

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the asset
}

if (str_starts_with($path, '/admin')) {
    require __DIR__ . '/admin/index.php';
    return true;
}

require __DIR__ . '/index.php';
