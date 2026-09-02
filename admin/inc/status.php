<?php
/**
 * The checks behind the System status screen.
 *
 * Each returns ['state' => ok|warn|bad, 'label', 'value', 'note']. "bad"
 * means the site will misbehave; "warn" means it works but something is
 * still unset that should not be by the time it is live.
 */
declare(strict_types=1);

function status_row(string $state, string $label, string $value, string $note = ''): array
{
    return ['state' => $state, 'label' => $label, 'value' => $value, 'note' => $note];
}

/** Human size for a byte count. */
function status_size(int $bytes): string
{
    foreach ([['GB', 1 << 30], ['MB', 1 << 20], ['KB', 1 << 10]] as [$unit, $step]) {
        if ($bytes >= $step) return number_format($bytes / $step, $bytes >= $step * 10 ? 0 : 1) . ' ' . $unit;
    }
    return $bytes . ' bytes';
}

/** Total size of a folder, following one level of subfolders. */
function status_folder_size(string $path): array
{
    if (!is_dir($path)) return [0, 0];
    $bytes = 0;
    $count = 0;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($items as $file) {
        if ($file->isFile()) { $bytes += $file->getSize(); $count++; }
    }
    return [$count, $bytes];
}

function status_groups(): array
{
    $groups = [];

    /* ------------------------------------------------------- the server */
    $php = PHP_VERSION;
    $groups['The server'][] = status_row(
        version_compare($php, '8.1', '>=') ? 'ok' : 'bad',
        'PHP version', '<b>' . e($php) . '</b>', 'This build needs 8.1 or newer');

    foreach (['mbstring' => 'Accented characters in names and copy',
              'json'     => 'Orders, enquiries and the basket',
              'openssl'  => 'Sending mail over SMTP with TLS',
              'curl'     => 'Talking to Stripe and PayPal — without it neither can take money',
              'fileinfo' => 'Checking what an uploaded image really is'] as $ext => $why) {
        $has = extension_loaded($ext);
        $groups['The server'][] = status_row(
            $has ? 'ok' : ($ext === 'mbstring' ? 'warn' : 'bad'),
            'Extension: ' . $ext, $has ? 'Loaded' : '<b>Missing</b>', $why);
    }

    $limit = (int) ini_get('memory_limit');
    $groups['The server'][] = status_row($limit === -1 || $limit >= 64 ? 'ok' : 'warn',
        'Memory limit', e((string) ini_get('memory_limit')), 'Plenty at 64M — nothing here is heavy');

    $upload = min(
        (int) preg_replace('/[^0-9]/', '', (string) ini_get('upload_max_filesize')),
        (int) preg_replace('/[^0-9]/', '', (string) ini_get('post_max_size'))
    );
    $groups['The server'][] = status_row($upload >= 4 ? 'ok' : 'warn',
        'Largest upload', e((string) ini_get('upload_max_filesize')),
        'The image uploader refuses anything over 4 MB anyway');

    $groups['The server'][] = status_row(
        str_contains(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'apache') ? 'ok' : 'warn',
        'Web server', e($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
        'The .htaccess rules only apply on Apache — on nginx they need translating');

    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $local = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
    $groups['The server'][] = status_row($https ? 'ok' : ($local ? 'ok' : 'bad'),
        'HTTPS', $https ? 'On' : ($local ? 'Not on localhost, which is fine' : '<b>Off</b>'),
        'Every canonical, sitemap and structured-data URL says https');

    /* ----------------------------------------------------- what we write */
    foreach ([
        'data/'               => 'Products, categories, coupons, page copy and SEO',
        'storage/'            => 'Settings, the admin account and enquiries',
        'storage/orders/'     => 'One JSON file per order',
        'assets/img/products' => 'Product photos from the uploader',
        'assets/img/blog'     => 'Article covers',
        'assets/img/site'     => 'Logo and site furniture',
    ] as $rel => $why) {
        $path = ROOT_DIR . '/' . rtrim($rel, '/');
        $ok   = is_dir($path) && is_writable($path);
        $groups['Folders we write to'][] = status_row(
            $ok ? 'ok' : 'bad', $rel,
            $ok ? 'Writable' : (is_dir($path) ? '<b>Read only</b>' : '<b>Missing</b>'), $why);
    }

    /* -------------------------------------------------- kept out of sight */
    foreach (['data', 'inc', 'pages', 'partials', 'storage', '.data'] as $dir) {
        $file = ROOT_DIR . '/' . $dir . '/.htaccess';
        $has  = is_file($file) && str_contains((string) file_get_contents($file), 'Require all denied');
        $groups['Kept out of sight'][] = status_row($has ? 'ok' : 'bad',
            $dir . '/', $has ? 'Denied over HTTP' : '<b>Not denied</b>',
            $dir === '.data' ? 'Holds the raw API dumps from the old site' : '');
    }

    $guarded = 0;
    $files   = glob(ROOT_DIR . '/data/*.php') ?: [];
    foreach ($files as $file) {
        if (str_contains((string) file_get_contents($file), "defined('ROOT_DIR')")) $guarded++;
    }
    $groups['Kept out of sight'][] = status_row(
        $guarded === count($files) ? 'ok' : 'warn',
        'Data files refuse a direct request', $guarded . ' of ' . count($files),
        'A second lock in case .htaccess is ignored');

    $groups['Kept out of sight'][] = status_row(
        is_file(ROOT_DIR . '/assets/img/.htaccess') ? 'ok' : 'bad',
        'Uploads cannot execute',
        is_file(ROOT_DIR . '/assets/img/.htaccess') ? 'PHP is off in assets/img/' : '<b>Not blocked</b>',
        'An image that is really a script must stay an image');

    /* ------------------------------------------------------- the settings */
    $users = count(users());
    $groups['Settings'][] = status_row($users ? 'ok' : 'bad',
        'Admin accounts', $users ? (string) $users : '<b>None</b>', 'Created on the first visit to /admin/');

    $smtp = trim((string) setting('smtp_host'));
    $groups['Settings'][] = status_row($smtp !== '' ? 'ok' : 'warn',
        'Outgoing mail', $smtp !== '' ? 'SMTP via ' . e($smtp) : "PHP's own mail()",
        'Order confirmations reach an inbox far more reliably over SMTP');

    $from   = (string) setting('mail_from');
    $domain = strtolower(parse_url(SITE_URL, PHP_URL_HOST) ?: '');
    $onSite = $domain !== '' && str_ends_with(strtolower($from), '@' . $domain);
    $groups['Settings'][] = status_row($onSite ? 'ok' : 'warn',
        'Sender address', e($from),
        $onSite ? 'On the shop\'s own domain' : 'Should be an address on ' . $domain);

    $turnstile = trim((string) setting('turnstile_site')) !== ''
              && trim((string) setting('turnstile_secret')) !== '';
    $groups['Settings'][] = status_row($turnstile ? 'ok' : 'warn',
        'Anti-spam', $turnstile ? 'Turnstile keys set' : 'Honeypot only',
        'The contact and checkout forms both use it');

    $groups['Settings'][] = status_row('ok', 'Currency and tax',
        e(setting('currency')) . ' · ' . (tax_enabled()
            ? e(tax_label()) . ' at ' . (int) tax_rate() . '%' : 'no tax'),
        'Settings → General and Tax');

    $zones   = count(shipping_zones());
    $methods = 0;
    foreach (shipping_zones() as $zone) {
        $methods += count(array_filter((array) ($zone['methods'] ?? []), fn($m) => !empty($m['enabled'])));
    }
    $groups['Settings'][] = status_row($methods ? 'ok' : 'bad',
        'Delivery', $zones . ' zone' . ($zones === 1 ? '' : 's') . ', '
                  . $methods . ' method' . ($methods === 1 ? '' : 's') . ' on',
        $methods ? '' : 'Nothing is switched on, so every order quotes free delivery');

    $pay = count(payment_methods());
    $groups['Settings'][] = status_row($pay ? 'ok' : 'warn',
        'Payment', $pay . ' method' . ($pay === 1 ? '' : 's') . ' offered',
        $pay ? '' : 'The checkout will say payment is arranged afterwards');

    $groups['Settings'][] = status_row('ok', 'Asset version', 'v' . e(ASSET_VER),
        'Bumped whenever settings are saved, so nobody gets a stale stylesheet');

    /* ---------------------------------------------------------- the shop */
    $products = all_products(true);
    $live     = array_filter($products, fn($p) => ($p['status'] ?? 'published') !== 'draft');
    $priced   = array_filter($live, fn($p) => $p['price_min'] > 0);
    $groups['The shop'][] = status_row($live ? 'ok' : 'bad', 'Products',
        count($live) . ' published' . (count($products) > count($live)
            ? ', ' . (count($products) - count($live)) . ' draft' : ''),
        count($priced) . ' carry a price, the rest are price on request');

    $noImage = array_filter($live, fn($p) => empty($p['images']));
    $groups['The shop'][] = status_row($noImage ? 'warn' : 'ok', 'Product photos',
        $noImage ? count($noImage) . ' without one' : 'Every product has one');

    $groups['The shop'][] = status_row('ok', 'Categories and attributes',
        count(all_categories()) . ' categories, ' . count(all_attributes()) . ' attributes');

    $codes = all_coupons();
    $onNow = array_filter($codes, fn($c) => !empty($c['enabled']));
    $groups['The shop'][] = status_row('ok', 'Discount codes',
        coupons_enabled()
            ? count($onNow) . ' of ' . count($codes) . ' accepted'
            : 'Switched off',
        'Settings → General decides whether any are accepted');

    $groups['The shop'][] = status_row('ok', 'Blog', count(all_posts()) . ' posts');

    $seo = is_file(ROOT_DIR . '/data/seo.php') ? (array) require ROOT_DIR . '/data/seo.php' : [];
    $groups['The shop'][] = status_row(count($seo) > 50 ? 'ok' : 'warn',
        'Saved metadata', count($seo) . ' URLs',
        'Copied from the live site so the rankings carry over');

    $orders = all_orders();
    $groups['The shop'][] = status_row('ok', 'Orders on file',
        count($orders) . ($orders ? ', newest ' . e(substr((string) $orders[0]['placed_at'], 0, 10)) : ''),
        'storage/orders/, denied over HTTP');

    [$imgCount, $imgBytes] = status_folder_size(ROOT_DIR . '/assets/img');
    $groups['The shop'][] = status_row('ok', 'Images on disk',
        $imgCount . ' files, ' . status_size($imgBytes));

    return $groups;
}
