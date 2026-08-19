<?php
/**
 * Admin front controller.
 *
 * Everything under /admin/ lands here. Nothing is reachable without a
 * session, every POST needs a CSRF token, and the whole area is noindex.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';
require __DIR__ . '/inc/auth.php';

admin_session_start();

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin/', PHP_URL_PATH) ?? '/admin/';
$segs = array_values(array_filter(explode('/', trim($path, '/'))));
array_shift($segs);                       // drop "admin"
$route = $segs[0] ?? '';
$arg   = isset($segs[1]) ? rawurldecode($segs[1]) : '';

$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) check_csrf();

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/* ---------------------------------------------------------- first run */

if (!has_users()) {
    if ($route !== 'setup') redirect('/admin/setup');

    $errors = [];
    if ($post) {
        $email = trim((string) ($_POST['email'] ?? ''));
        $pw    = (string) ($_POST['password'] ?? '');
        $pw2   = (string) ($_POST['password2'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (strlen($pw) < 10)     $errors[] = 'Use a password of at least 10 characters.';
        if ($pw !== $pw2)         $errors[] = 'The two passwords do not match.';
        if (!$errors) {
            if (create_user($email, $pw, trim((string) ($_POST['name'] ?? '')))) {
                attempt_login($email, $pw);
                flash('Account created. Welcome.');
                redirect('/admin/');
            }
            $errors[] = 'Could not write storage/users.php — check the folder is writable.';
        }
    }
    render('setup', ['errors' => $errors, 'title' => 'Set up the admin account']);
    exit;
}

if ($route === 'login') {
    if (current_user()) redirect('/admin/');
    $error = '';
    $wait  = is_locked_out();
    if ($post && !$wait) {
        if (attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect('/admin/');
        }
        $error = 'Those details were not recognised.';
        $wait  = is_locked_out();
    }
    render('login', ['error' => $error, 'wait' => $wait, 'title' => 'Sign in']);
    exit;
}

if ($route === 'logout') {
    logout();
    redirect('/admin/login');
}

require_login();

/* ------------------------------------------------------------- routes */

switch ($route) {

    case '':
        $orders = all_orders();
        render('dashboard', [
            'title'    => 'Dashboard',
            'orders'   => array_slice($orders, 0, 8),
            'counts'   => [
                'orders'     => count($orders),
                'new'        => count(array_filter($orders, fn($o) => ($o['status'] ?? 'new') === 'new')),
                'products'   => count(all_products()),
                'categories' => count(all_categories()),
                'posts'      => count(all_posts()),
                'revenue'    => array_sum(array_map(
                    fn($o) => ($o['status'] ?? 'new') === 'cancelled' ? 0 : (int) ($o['order']['total'] ?? 0),
                    $orders)),
            ],
        ]);
        break;

    /* ------------------------------------------------------------ orders */
    case 'orders':
        if ($arg === '') {
            $filter = (string) ($_GET['status'] ?? '');
            $orders = all_orders();
            if ($filter !== '' && isset(ORDER_STATUSES[$filter])) {
                $orders = array_values(array_filter($orders, fn($o) => ($o['status'] ?? 'new') === $filter));
            }
            render('orders', ['title' => 'Orders', 'orders' => $orders, 'filter' => $filter]);
            break;
        }
        $order = find_order($arg);
        if (!$order) { http_response_code(404); render('missing', ['title' => 'Order not found']); break; }

        if ($post) {
            if (isset($_POST['delete'])) {
                delete_order($arg);
                flash('Order ' . $arg . ' deleted.');
                redirect('/admin/orders');
            }
            $status = (string) ($_POST['status'] ?? 'new');
            if (isset(ORDER_STATUSES[$status])) {
                $order['status'] = $status;
                $order['note']   = trim((string) ($_POST['note'] ?? ''));
                $order['updated_at'] = date('c');
                save_order($order);
                flash('Order updated.');
            }
            redirect('/admin/orders/' . rawurlencode($arg));
        }
        render('order', ['title' => 'Order ' . $order['reference'], 'order' => $order]);
        break;

    /* ---------------------------------------------------------- products */
    case 'products':
        $products = all_products();

        if ($arg === '') {
            render('products', ['title' => 'Products', 'products' => $products, 'q' => (string) ($_GET['q'] ?? '')]);
            break;
        }

        if ($arg === 'new') {
            $product = ['id' => 0, 'slug' => '', 'name' => '', 'type' => 'simple', 'sku' => '',
                        'cats' => [], 'images' => [], 'short' => '', 'desc' => '',
                        'price_min' => 0, 'price_max' => 0, 'purchasable' => true,
                        'attrs' => [], 'variants' => []];
        } else {
            $product = find_product($arg);
            if (!$product) { http_response_code(404); render('missing', ['title' => 'Product not found']); break; }
        }

        if ($post) {
            if (isset($_POST['delete']) && $arg !== 'new') {
                $products = array_values(array_filter($products, fn($p) => $p['slug'] !== $product['slug']));
                save_products($products);
                flash('Product deleted.');
                redirect('/admin/products');
            }
            [$product, $errors] = save_product_from_post($product, $products, $arg === 'new');
            if (!$errors) {
                flash('Product saved.');
                redirect('/admin/products/' . rawurlencode($product['slug']));
            }
            render('product', ['title' => 'Edit product', 'product' => $product, 'errors' => $errors, 'isNew' => $arg === 'new']);
            break;
        }
        render('product', ['title' => $arg === 'new' ? 'New product' : 'Edit product',
                           'product' => $product, 'errors' => [], 'isNew' => $arg === 'new']);
        break;

    /* -------------------------------------------------------- categories */
    case 'categories':
        $cats = all_categories();
        if ($post) {
            $rows = [];
            foreach ((array) ($_POST['cat'] ?? []) as $row) {
                $slug = make_slug((string) ($row['slug'] ?? ''));
                if ($slug === '' || trim((string) ($row['name'] ?? '')) === '') continue;
                $parent = (string) ($row['parent'] ?? '');
                $rows[] = [
                    'id'          => (int) ($row['id'] ?? 0),
                    'slug'        => $slug,
                    'name'        => trim((string) $row['name']),
                    'parent'      => $parent,
                    'path'        => $parent !== '' ? $parent . '/' . $slug : $slug,
                    'count'       => (int) ($row['count'] ?? 0),
                    'description' => trim((string) ($row['description'] ?? '')),
                ];
            }
            if ($rows) { save_categories($rows); flash('Categories saved.'); }
            redirect('/admin/categories');
        }
        render('categories', ['title' => 'Categories', 'categories' => $cats]);
        break;

    /* ------------------------------------------------------------- posts */
    case 'posts':
        $posts = all_posts();

        if ($arg === '') {
            render('posts', ['title' => 'Blog posts', 'posts' => $posts]);
            break;
        }
        if ($arg === 'new') {
            $item = ['slug' => '', 'title' => '', 'date' => date('Y-m-d'), 'excerpt' => '', 'content' => '', 'image' => null];
        } else {
            $item = find_post($arg);
            if (!$item) { http_response_code(404); render('missing', ['title' => 'Post not found']); break; }
        }

        if ($post) {
            if (isset($_POST['delete']) && $arg !== 'new') {
                $posts = array_values(array_filter($posts, fn($p) => $p['slug'] !== $item['slug']));
                save_posts($posts);
                flash('Post deleted.');
                redirect('/admin/posts');
            }
            [$item, $errors] = save_post_from_post($item, $posts, $arg === 'new');
            if (!$errors) {
                flash('Post saved.');
                redirect('/admin/posts/' . rawurlencode($item['slug']));
            }
            render('post', ['title' => 'Edit post', 'item' => $item, 'errors' => $errors, 'isNew' => $arg === 'new']);
            break;
        }
        render('post', ['title' => $arg === 'new' ? 'New post' : 'Edit post',
                        'item' => $item, 'errors' => [], 'isNew' => $arg === 'new']);
        break;

    /* --------------------------------------------------------------- SEO */
    case 'seo':
        $seo = is_file(ROOT_DIR . '/data/seo.php') ? (array) require ROOT_DIR . '/data/seo.php' : [];
        if ($post) {
            $url = (string) ($_POST['url'] ?? '');
            if ($url !== '' && str_starts_with($url, '/')) {
                $entry = $seo[$url] ?? [];
                foreach (['title', 'description', 'canonical'] as $k) {
                    $v = trim((string) ($_POST[$k] ?? ''));
                    if ($v === '') unset($entry[$k]); else $entry[$k] = $v;
                }
                if ($entry) $seo[$url] = $entry; else unset($seo[$url]);
                save_seo($seo);
                flash('Metadata saved for ' . $url . '.');
            }
            redirect('/admin/seo?url=' . urlencode($url));
        }
        render('seo', ['title' => 'SEO', 'seo' => $seo, 'url' => (string) ($_GET['url'] ?? '')]);
        break;

    /* ---------------------------------------------------------- settings */
    case 'settings':
        if ($post) {
            $values = settings();
            foreach (['site_name', 'site_tag', 'phone', 'phone_href', 'email',
                      'address', 'hours_week', 'hours_weekend'] as $k) {
                $values[$k] = trim((string) ($_POST[$k] ?? $values[$k]));
            }
            $values['free_shipping'] = max(0, (int) round((float) ($_POST['free_shipping'] ?? 0) * 100));
            $values['shipping_flat'] = max(0, (int) round((float) ($_POST['shipping_flat'] ?? 0) * 100));
            $values['vat_rate']      = max(0, min(100, (int) ($_POST['vat_rate'] ?? 20)));
            $values['asset_ver']     = (string) ((int) $values['asset_ver'] + 1);   // bust the CSS/JS cache
            save_settings($values);
            flash('Settings saved.');
            redirect('/admin/settings');
        }
        render('settings', ['title' => 'Settings', 'values' => settings()]);
        break;

    /* ------------------------------------------------------------- media */
    case 'media':
        $message = null;
        if ($post) {
            [$ok, $message] = handle_upload($_FILES['file'] ?? null, (string) ($_POST['folder'] ?? 'products'));
            flash($message, $ok ? 'ok' : 'bad');
            redirect('/admin/media');
        }
        render('media', ['title' => 'Images', 'folders' => ['products', 'blog', 'site']]);
        break;

    case 'account':
        $errors = [];
        if ($post) {
            $pw  = (string) ($_POST['password'] ?? '');
            $pw2 = (string) ($_POST['password2'] ?? '');
            if (strlen($pw) < 10) $errors[] = 'Use a password of at least 10 characters.';
            if ($pw !== $pw2)     $errors[] = 'The two passwords do not match.';
            if (!$errors) {
                create_user(current_user()['email'], $pw, current_user()['name']);
                flash('Password changed.');
                redirect('/admin/account');
            }
        }
        render('account', ['title' => 'Your account', 'errors' => $errors]);
        break;

    default:
        http_response_code(404);
        render('missing', ['title' => 'Not found']);
}

/* ------------------------------------------------------------ handlers */

/** Apply a posted product form, returning [product, errors]. */
function save_product_from_post(array $product, array $products, bool $isNew): array
{
    $errors = [];
    $name   = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') $errors[] = 'The product needs a name.';

    $slug = make_slug((string) ($_POST['slug'] ?? '') ?: $name);
    $slug = unique_slug($slug, $products, $isNew ? '' : $product['slug']);

    $variants = [];
    foreach ((array) ($_POST['variant'] ?? []) as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') continue;
        $variants[] = [
            'key'   => make_slug($label),
            'attrs' => [],
            'label' => $label,
            'price' => (int) round((float) ($row['price'] ?? 0) * 100),
        ];
    }
    usort($variants, fn($a, $b) => $a['price'] <=> $b['price']);

    $images = array_values(array_filter(array_map(
        fn($src) => ltrim(trim((string) $src), '/'),
        (array) ($_POST['images'] ?? [])
    )));
    // only ever point at files we actually have
    $images = array_values(array_filter($images, fn($src) =>
        str_starts_with($src, 'assets/img/') && is_file(ROOT_DIR . '/' . $src)));

    $single = (int) round((float) ($_POST['price'] ?? 0) * 100);
    $prices = array_column($variants, 'price');

    $updated = array_merge($product, [
        'id'          => $product['id'] ?: (int) (time() % 100000),
        'slug'        => $slug,
        'name'        => $name,
        'type'        => $variants ? 'variable' : 'simple',
        'sku'         => trim((string) ($_POST['sku'] ?? '')),
        'cats'        => array_values(array_filter((array) ($_POST['cats'] ?? []))),
        'images'      => $images,
        'short'       => trim((string) ($_POST['short'] ?? '')),
        'desc'        => trim((string) ($_POST['desc'] ?? '')),
        'price_min'   => $prices ? min($prices) : $single,
        'price_max'   => $prices ? max($prices) : $single,
        'purchasable' => ($prices ? min($prices) : $single) > 0,
        'variants'    => $variants,
    ]);

    if ($errors) return [$updated, $errors];

    $found = false;
    foreach ($products as $i => $row) {
        if ($row['slug'] === $product['slug'] && !$isNew) { $products[$i] = $updated; $found = true; break; }
    }
    if (!$found) $products[] = $updated;

    if (!save_products($products)) $errors[] = 'Could not write data/products.php — check it is writable.';
    return [$updated, $errors];
}

/** Apply a posted blog form, returning [post, errors]. */
function save_post_from_post(array $item, array $posts, bool $isNew): array
{
    $errors = [];
    $title  = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') $errors[] = 'The post needs a title.';

    $slug  = make_slug((string) ($_POST['slug'] ?? '') ?: $title);
    $slug  = unique_slug($slug, $posts, $isNew ? '' : $item['slug']);
    $image = ltrim(trim((string) ($_POST['image'] ?? '')), '/');
    if ($image !== '' && (!str_starts_with($image, 'assets/img/') || !is_file(ROOT_DIR . '/' . $image))) {
        $image = '';
    }

    $date = (string) ($_POST['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

    $updated = array_merge($item, [
        'slug'    => $slug,
        'title'   => $title,
        'date'    => $date,
        'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
        'content' => trim((string) ($_POST['content'] ?? '')),
        'image'   => $image !== '' ? $image : null,
    ]);

    if ($errors) return [$updated, $errors];

    $found = false;
    foreach ($posts as $i => $row) {
        if ($row['slug'] === $item['slug'] && !$isNew) { $posts[$i] = $updated; $found = true; break; }
    }
    if (!$found) array_unshift($posts, $updated);

    usort($posts, fn($a, $b) => strcmp($b['date'], $a['date']));
    if (!save_posts($posts)) $errors[] = 'Could not write data/posts.php — check it is writable.';
    return [$updated, $errors];
}

/** Validate and store an uploaded image. Returns [ok, message]. */
function handle_upload(?array $file, string $folder): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, 'Choose a file first.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed (error ' . $file['error'] . ').'];
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        return [false, 'That file is over 4 MB. Resize it and try again.'];
    }
    if (!in_array($folder, ['products', 'blog', 'site'], true)) {
        return [false, 'Unknown destination folder.'];
    }

    // trust the file's actual content, not its name
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($allowed[$info[2]])) {
        return [false, 'That is not a JPEG, PNG, WebP or GIF image.'];
    }

    $name = make_slug(pathinfo((string) $file['name'], PATHINFO_FILENAME)) . '.' . $allowed[$info[2]];
    $dest = ROOT_DIR . '/assets/img/' . $folder . '/' . $name;
    $i = 2;
    while (file_exists($dest)) {
        $name = make_slug(pathinfo((string) $file['name'], PATHINFO_FILENAME)) . '-' . $i++ . '.' . $allowed[$info[2]];
        $dest = ROOT_DIR . '/assets/img/' . $folder . '/' . $name;
    }
    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, 'Could not save the file — check assets/img/' . $folder . ' is writable.'];
    }
    @chmod($dest, 0644);
    return [true, 'Uploaded as assets/img/' . $folder . '/' . $name];
}

/** Render a view inside the admin chrome. */
function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $viewName = $view;
    require __DIR__ . '/views/layout.php';
}
