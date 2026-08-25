<?php
/**
 * Reading and writing the file-backed store.
 *
 * Catalogue data lives in data/*.php as plain PHP arrays. The admin panel
 * rewrites those files; every write goes to a temp file first and is then
 * renamed over the target, so a crash mid-write cannot leave a half-file
 * that would take the whole site down.
 */
declare(strict_types=1);

/** Render a value as PHP source, the same shape the generator produces. */
function php_export($value, int $indent = 0): string
{
    $pad = str_repeat('    ', $indent);
    if ($value === null)     return 'null';
    if (is_bool($value))     return $value ? 'true' : 'false';
    if (is_int($value) || is_float($value)) return (string) $value;
    if (is_array($value)) {
        if (!$value) return '[]';
        $isList = array_is_list($value);
        $parts  = [];
        foreach ($value as $key => $item) {
            $prefix = $isList ? '' : "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $key) . "' => ";
            $parts[] = $pad . '    ' . $prefix . php_export($item, $indent + 1);
        }
        return "[\n" . implode(",\n", $parts) . ",\n" . $pad . ']';
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
}

/** Write a PHP array file atomically. Returns false if anything went wrong. */
function write_php_file(string $path, array $data, string $header): bool
{
    // The same guard the generator writes: if .htaccess is ever ignored, a
    // direct request for one of these still 404s instead of serving the source.
    $body = "<?php\n/**\n * " . str_replace("\n", "\n * ", $header) . "\n */\n"
          . "if (!defined('ROOT_DIR')) { http_response_code(404); exit; }\n\nreturn "
          . php_export($data) . ";\n";

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;

    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;

    // never replace a good file with one that will not parse
    if (@php_check_syntax_shim($tmp) === false) { @unlink($tmp); return false; }

    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
    return true;
}

/** Cheap parse check: include the file and confirm it yields an array. */
function php_check_syntax_shim(string $file): bool
{
    try {
        $result = @include $file;
        return is_array($result);
    } catch (Throwable $e) {
        return false;
    }
}

function save_products(array $products): bool
{
    return write_php_file(ROOT_DIR . '/data/products.php', array_values($products),
        'Products with variants and prices (pence, excl. VAT).');
}

function save_categories(array $categories): bool
{
    return write_php_file(ROOT_DIR . '/data/categories.php', array_values($categories),
        'Product categories.');
}

function save_attributes(array $attributes): bool
{
    return write_php_file(ROOT_DIR . '/data/attributes.php', array_values($attributes),
        "Global product attributes and their terms.\nProducts reference these by name; the terms give every variant its key.");
}

function save_coupons(array $coupons): bool
{
    return write_php_file(ROOT_DIR . '/data/coupons.php', array_values($coupons),
        "Discount codes, written by the admin panel.\n"
      . "Amounts are a percentage for 'percent' coupons and pence for 'fixed' ones.");
}

/** Count one more use of a code. Silent if the code has since been deleted. */
function record_coupon_use(string $code): void
{
    $coupons = all_coupons();
    $want    = lower(trim($code));
    $hit     = false;
    foreach ($coupons as $i => $c) {
        if (lower((string) $c['code']) !== $want) continue;
        $coupons[$i]['used'] = (int) ($c['used'] ?? 0) + 1;
        $hit = true;
        break;
    }
    if ($hit) save_coupons($coupons);
}

function save_posts(array $posts): bool
{
    return write_php_file(ROOT_DIR . '/data/posts.php', array_values($posts), 'Blog posts.');
}

function save_pages(array $pages): bool
{
    return write_php_file(ROOT_DIR . '/data/pages.php', $pages,
        "Editable page copy, written by the admin panel.
Any key left out falls back to the wording in pages/*.php.");
}

function save_seo(array $seo): bool
{
    return write_php_file(ROOT_DIR . '/data/seo.php', $seo,
        "Titles, descriptions and canonicals.\nKeep these matching the live site so search rankings hold.");
}

function save_settings(array $values): bool
{
    return write_php_file(ROOT_DIR . '/storage/settings.php', $values, 'Site settings, written by the admin panel.');
}

/* ----------------------------------------------------------------- orders */

function orders_dir(): string
{
    return ROOT_DIR . '/storage/orders';
}

/** All orders, newest first. */
function all_orders(): array
{
    $dir = orders_dir();
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data) && !empty($data['reference'])) {
            $data['status'] = $data['status'] ?? 'new';
            $out[] = $data;
        }
    }
    usort($out, fn($a, $b) => strcmp($b['placed_at'] ?? '', $a['placed_at'] ?? ''));
    return $out;
}

function find_order(string $reference): ?array
{
    if (!preg_match('/^[A-Za-z0-9-]{4,32}$/', $reference)) return null;   // no traversal
    $file = orders_dir() . '/' . $reference . '.json';
    if (!is_file($file)) return null;
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) return null;
    $data['status'] = $data['status'] ?? 'new';
    return $data;
}

function save_order(array $order): bool
{
    $ref = (string) ($order['reference'] ?? '');
    if (!preg_match('/^[A-Za-z0-9-]{4,32}$/', $ref)) return false;
    $dir = orders_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents(
        $dir . '/' . $ref . '.json',
        json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

function delete_order(string $reference): bool
{
    if (!preg_match('/^[A-Za-z0-9-]{4,32}$/', $reference)) return false;
    $file = orders_dir() . '/' . $reference . '.json';
    return is_file($file) ? @unlink($file) : false;
}

const ORDER_STATUSES = ['new' => 'New', 'confirmed' => 'Confirmed', 'invoiced' => 'Invoiced',
                        'shipped' => 'Shipped', 'cancelled' => 'Cancelled'];

/* ------------------------------------------------------------ customers */

/**
 * Everyone who has ordered or written in, keyed by email address.
 *
 * The shop takes orders without a login, so there is no customer table to
 * read — this is assembled from what the orders and enquiries actually
 * contain. Orders arrive newest first, so the newest details win and older
 * ones only fill in what is still blank.
 */
function all_customers(): array
{
    $people = [];

    $touch = function (string $email) use (&$people): ?string {
        $key = lower(trim($email));
        if ($key === '' || !filter_var($key, FILTER_VALIDATE_EMAIL)) return null;
        if (!isset($people[$key])) {
            $people[$key] = [
                'email' => $key, 'name' => '', 'company' => '', 'phone' => '',
                'address' => '', 'city' => '', 'postcode' => '', 'country' => '',
                'orders' => 0, 'cancelled' => 0, 'spent' => 0, 'enquiries' => 0,
                'first_at' => '', 'last_at' => '', 'references' => [],
            ];
        }
        return $key;
    };

    $fill = function (string $key, array $fields) use (&$people): void {
        foreach ($fields as $field => $value) {
            $value = trim((string) $value);
            if ($value !== '' && ($people[$key][$field] ?? '') === '') {
                $people[$key][$field] = $value;
            }
        }
    };

    foreach (all_orders() as $order) {
        $c   = (array) ($order['customer'] ?? []);
        $key = $touch((string) ($c['email'] ?? ''));
        if ($key === null) continue;

        $fill($key, [
            'name'    => $c['name']    ?? '', 'company'  => $c['company']  ?? '',
            'phone'   => $c['phone']   ?? '', 'address'  => $c['address']  ?? '',
            'city'    => $c['city']    ?? '', 'postcode' => $c['postcode'] ?? '',
            'country' => $c['country'] ?? '',
        ]);

        $placed    = (string) ($order['placed_at'] ?? '');
        $cancelled = ($order['status'] ?? 'new') === 'cancelled';

        $people[$key]['orders']++;
        if ($cancelled) $people[$key]['cancelled']++;
        else            $people[$key]['spent'] += (int) ($order['order']['total'] ?? 0);
        $people[$key]['references'][] = (string) ($order['reference'] ?? '');

        if ($placed !== '') {
            if ($people[$key]['last_at'] === '' || $placed > $people[$key]['last_at']) {
                $people[$key]['last_at'] = $placed;
            }
            if ($people[$key]['first_at'] === '' || $placed < $people[$key]['first_at']) {
                $people[$key]['first_at'] = $placed;
            }
        }
    }

    foreach (all_submissions() as $row) {
        $key = $touch((string) ($row['email'] ?? ''));
        if ($key === null) continue;
        $fill($key, ['name' => $row['name'] ?? '', 'phone' => $row['phone'] ?? '']);
        $people[$key]['enquiries']++;
    }

    uasort($people, fn($a, $b) => [$b['spent'], $b['last_at']] <=> [$a['spent'], $a['last_at']]);
    return $people;
}

function find_customer(string $email): ?array
{
    return all_customers()[lower(trim($email))] ?? null;
}

/** That customer's orders, newest first. */
function customer_orders(string $email): array
{
    $want = lower(trim($email));
    return array_values(array_filter(all_orders(),
        fn($o) => lower((string) ($o['customer']['email'] ?? '')) === $want));
}

/** That customer's enquiries, newest first. */
function customer_enquiries(string $email): array
{
    $want = lower(trim($email));
    return array_values(array_filter(all_submissions(),
        fn($s) => lower((string) ($s['email'] ?? '')) === $want));
}

/* ------------------------------------------------------------ enquiries */

function submissions_file(): string
{
    return ROOT_DIR . '/storage/submissions.json';
}

/** Every enquiry sent from the site, newest first. */
function all_submissions(): array
{
    $file = submissions_file();
    if (!is_file($file)) return [];
    $rows = json_decode((string) file_get_contents($file), true);
    if (!is_array($rows)) return [];
    usort($rows, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $rows;
}

function save_submissions(array $rows): bool
{
    $dir = dirname(submissions_file());
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents(submissions_file(),
        json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX) !== false;
}

/** Record one enquiry. Returns its id. */
function add_submission(array $fields): string
{
    $rows = all_submissions();
    $id   = date('ymd-His') . '-' . bin2hex(random_bytes(2));
    array_unshift($rows, array_merge([
        'id'         => $id,
        'created_at' => date('c'),
        'is_read'    => false,
        'source'     => 'contact',
        'name'       => '',
        'email'      => '',
        'phone'      => '',
        'message'    => '',
        'product'    => '',
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
    ], $fields));
    // keep the file from growing without bound
    save_submissions(array_slice($rows, 0, 2000));
    return $id;
}

function find_submission(string $id): ?array
{
    foreach (all_submissions() as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function mark_submission(string $id, bool $read): bool
{
    $rows = all_submissions();
    foreach ($rows as $i => $row) {
        if (($row['id'] ?? '') === $id) { $rows[$i]['is_read'] = $read; return save_submissions($rows); }
    }
    return false;
}

function delete_submission(string $id): bool
{
    $rows = array_values(array_filter(all_submissions(), fn($r) => ($r['id'] ?? '') !== $id));
    return save_submissions($rows);
}

function unread_submissions(): int
{
    return count(array_filter(all_submissions(), fn($r) => empty($r['is_read'])));
}

/* ------------------------------------------------------------------ slugs */

function make_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = str_replace(['&', '+'], ['and', 'plus'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item-' . bin2hex(random_bytes(3));
}

/** A slug not already taken by another entry in $rows. */
function unique_slug(string $slug, array $rows, string $except = ''): string
{
    $taken = array_column($rows, 'slug');
    $taken = array_values(array_diff($taken, [$except]));
    if (!in_array($slug, $taken, true)) return $slug;
    $i = 2;
    while (in_array($slug . '-' . $i, $taken, true)) $i++;
    return $slug . '-' . $i;
}
