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
