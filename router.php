<?php
/**
 * Router for the PHP built-in server:  php -S localhost:8124 router.php
 * Serves real files as-is, sends everything else to the front controller.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . urldecode($path);

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the asset
}

require __DIR__ . '/index.php';
