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
require __DIR__ . '/inc/page-schema.php';
require ROOT_DIR . '/inc/mail.php';
require ROOT_DIR . '/inc/turnstile.php';

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
require_admin($route);

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

    /* ------------------------------------------------------------- pages */
    case 'pages':
        $schema  = page_schema();
        $file    = ROOT_DIR . '/data/pages.php';
        $content = is_file($file) ? (array) require $file : [];
        $seo     = is_file(ROOT_DIR . '/data/seo.php') ? (array) require ROOT_DIR . '/data/seo.php' : [];
        $which   = (string) ($_GET['p'] ?? '');

        if ($post) {
            $target = (string) ($_POST['path'] ?? '');
            if (isset($schema[$target])) {
                // only keys this page actually declares are accepted
                $allowed = [];
                foreach ($schema[$target]['groups'] as $fields) {
                    foreach ($fields as $field) $allowed[] = $field[0];
                }
                $saved = [];
                foreach ((array) ($_POST['f'] ?? []) as $key => $value) {
                    if (!in_array($key, $allowed, true)) continue;
                    $value = trim((string) $value);
                    if ($value !== '') $saved[$key] = $value;
                }
                if ($saved) $content[$target] = $saved; else unset($content[$target]);
                save_pages($content);

                $entry = $seo[$target] ?? [];
                foreach (['title' => 'seo_title', 'description' => 'seo_description',
                          'canonical' => 'seo_canonical', 'robots' => 'seo_robots'] as $k => $field) {
                    $v = trim((string) ($_POST[$field] ?? ''));
                    if ($v === '') unset($entry[$k]); else $entry[$k] = $v;
                }
                if ($entry) $seo[$target] = $entry; else unset($seo[$target]);
                save_seo($seo);

                flash('Page saved.');
            }
            redirect('/admin/pages?p=' . urlencode($target));
        }

        if ($which !== '' && isset($schema[$which])) {
            render('page', [
                'title'    => $schema[$which]['label'],
                'path'     => $which,
                'def'      => $schema[$which],
                'values'   => $content[$which] ?? [],
                'defaults' => page_defaults($which),
                'seoRow'   => $seo[$which] ?? [],
            ]);
            break;
        }
        render('pages', ['title' => 'Pages', 'content' => $content, 'seo' => $seo]);
        break;

    /* ------------------------------------------------------- submissions */
    case 'submissions':
        $all = all_submissions();
        if ($post) {
            $id  = (string) ($_POST['id'] ?? '');
            $act = (string) ($_POST['act'] ?? '');
            if ($act === 'delete')      { delete_submission($id); flash('Enquiry deleted.'); }
            elseif ($act === 'read')    { mark_submission($id, true); }
            elseif ($act === 'unread')  { mark_submission($id, false); }
            redirect('/admin/submissions' . (isset($_GET['f']) ? '?f=' . urlencode((string) $_GET['f']) : ''));
        }
        $filter = (string) ($_GET['f'] ?? '');
        $rows = $all;
        if ($filter === 'unread')  $rows = array_values(array_filter($rows, fn($r) => empty($r['is_read'])));
        if ($filter === 'product') $rows = array_values(array_filter($rows, fn($r) => !empty($r['product'])));
        render('submissions', ['title' => 'Enquiries', 'rows' => $rows, 'all' => $all,
                               'unread' => unread_submissions(), 'filter' => $filter]);
        break;

    /* ------------------------------------------------------------- users */
    case 'users':
        $errors = [];
        if ($post) {
            $act = (string) ($_POST['act'] ?? '');
            if ($act === 'delete') {
                if (!delete_user((string) ($_POST['email'] ?? ''))) {
                    flash('That account cannot be removed — a site needs at least one administrator.', 'bad');
                } else {
                    flash('Account removed.');
                }
                redirect('/admin/users');
            }
            $email = trim((string) ($_POST['email'] ?? ''));
            $pw    = (string) ($_POST['password'] ?? '');
            $known = isset(users()[strtolower($email)]);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if (!$known && strlen($pw) < 10)                $errors[] = 'New accounts need a password of at least 10 characters.';
            if ($pw !== '' && strlen($pw) < 10)              $errors[] = 'Use a password of at least 10 characters.';
            if (!$errors) {
                create_user($email, $pw, trim((string) ($_POST['name'] ?? '')), (string) ($_POST['role'] ?? 'editor'));
                flash('Account saved.');
                redirect('/admin/users');
            }
        }
        render('users', ['title' => 'Users', 'list' => array_values(users()), 'errors' => $errors]);
        break;

    /* -------------------------------------------------------------- mail */
    case 'mail':
        $result = null;
        if ($post) {
            $values = settings();
            foreach (['mail_to', 'mail_from', 'mail_from_name', 'smtp_host',
                      'smtp_user', 'smtp_pass', 'smtp_secure'] as $k) {
                $values[$k] = trim((string) ($_POST[$k] ?? $values[$k]));
            }
            $values['smtp_port'] = max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587)));
            save_settings($values);

            if (($_POST['act'] ?? '') === 'test') {
                // settings() is already cached, so re-read what we just wrote
                $error = '';
                $ok = send_mail($values['mail_to'], 'Test message from ' . SITE_NAME,
                    "This is a test from the admin panel.\n\nIf you are reading it, mail is working.\n",
                    '', $error);
                flash($ok ? 'Test message sent to ' . $values['mail_to'] . '.'
                          : 'Could not send: ' . $error, $ok ? 'ok' : 'bad');
            } else {
                flash('Mail settings saved.');
            }
            redirect('/admin/mail');
        }
        render('mail', ['title' => 'Mail', 'values' => settings(), 'result' => $result]);
        break;

    /* ---------------------------------------------------------- security */
    case 'security':
        if ($post) {
            $values = settings();
            $values['turnstile_site']   = trim((string) ($_POST['turnstile_site'] ?? ''));
            $values['turnstile_secret'] = trim((string) ($_POST['turnstile_secret'] ?? ''));
            save_settings($values);
            flash('Security settings saved.');
            redirect('/admin/security');
        }
        render('security', ['title' => 'Security', 'values' => settings()]);
        break;

    /* --------------------------------------------------------------- SEO */
    case 'seo':
        $seo = is_file(ROOT_DIR . '/data/seo.php') ? (array) require ROOT_DIR . '/data/seo.php' : [];
        if ($post) {
            $url = (string) ($_POST['url'] ?? '');
            if ($url !== '' && str_starts_with($url, '/')) {
                $entry = $seo[$url] ?? [];
                foreach (['title', 'description', 'canonical', 'robots'] as $k) {
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
                      'address', 'hours_week', 'hours_weekend', 'map_url',
                      'soc1_name', 'soc1_url', 'soc2_name', 'soc2_url',
                      'soc3_name', 'soc3_url', 'soc4_name', 'soc4_url'] as $k) {
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

/**
 * The built-in wording for a page, read from the page_text() calls in its
 * template, so the editor can show it as the placeholder.
 */
function page_defaults(string $path): array
{
    $files = [
        '/' => 'home', '/shop/' => 'shop', '/about-us/' => 'about', '/contacts/' => 'contacts',
        '/blog/' => 'blog', '/refund_returns/' => 'refund-returns', '/cart/' => 'cart',
        '/checkout/' => 'checkout', '/wishlist/' => 'wishlist', '/compare/' => 'compare',
        '/my-account/' => 'my-account', '/404' => '404',
    ];
    $file = ROOT_DIR . '/pages/' . ($files[$path] ?? '') . '.php';
    if (!isset($files[$path]) || !is_file($file)) return [];

    $src = (string) file_get_contents($file);
    $out = [];

    // page_text('/path', 'key', 'the shipped wording')
    $single = '/page_text\(\s*\'[^\']*\'\s*,\s*\'([a-z0-9_]+)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\)/';
    if (preg_match_all($single, $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $out[$hit[1]] = stripcslashes($hit[2]);
        }
    }

    // page_lines('/path', 'key', ['one', 'two'])
    $list = '/page_lines\(\s*\'[^\']*\'\s*,\s*\'([a-z0-9_]+)\'\s*,\s*\[(.*?)\]\s*\)/s';
    if (preg_match_all($list, $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'/', $hit[2], $items);
            $out[$hit[1]] = implode("\n", array_map('stripcslashes', $items[1]));
        }
    }

    return $out;
}

/** Render a view inside the admin chrome. */
function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $viewName = $view;
    require __DIR__ . '/views/layout.php';
}
