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

const ATTR_ORDERS = ['custom' => 'Custom ordering', 'name' => 'Name', 'value' => 'Numeric value'];
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
        $products = all_products(true);

        if ($arg === 'export') { export_products($products); }

        if ($arg === 'import') {
            $report = null;
            if ($post && isset($_FILES['csv'])) {
                $report = import_products($_FILES['csv'], $products);
                flash($report['message'], $report['ok'] ? 'ok' : 'bad');
                redirect('/admin/products');
            }
            render('import', ['title' => 'Import products']);
            break;
        }

        if ($arg === '') {
            if ($post) {
                $picked = array_values(array_filter((array) ($_POST['slugs'] ?? [])));
                $action = (string) ($_POST['bulk'] ?? '');
                if ($picked && $action !== '') {
                    $changed = apply_bulk($products, $picked, $action);
                    save_products($products);
                    flash($changed . ' product' . ($changed === 1 ? '' : 's') . ' updated.');
                }
                redirect('/admin/products' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
            }

            $q      = trim((string) ($_GET['q'] ?? ''));
            $cat    = (string) ($_GET['cat'] ?? '');
            $type   = (string) ($_GET['type'] ?? '');
            $stock  = (string) ($_GET['stock'] ?? '');
            $status = (string) ($_GET['status'] ?? '');
            $sort   = (string) ($_GET['sort'] ?? 'name');

            $rows = $products;
            if ($q !== '') {
                $needle = lower($q);
                $rows = array_values(array_filter($rows,
                    fn($p) => str_contains(lower($p['name'] . ' ' . $p['slug'] . ' ' . $p['sku']), $needle)));
            }
            if ($cat !== '') {
                $inCat = array_column(products_in_category($cat), 'slug');
                $rows = array_values(array_filter($rows, fn($p) => in_array($p['slug'], $inCat, true)));
            }
            if ($type !== '')  $rows = array_values(array_filter($rows, fn($p) => $p['type'] === $type));
            if ($stock !== '') $rows = array_values(array_filter($rows, fn($p) => ($p['stock'] ?? 'instock') === $stock));

            if ($status === 'featured')        $rows = array_values(array_filter($rows, fn($p) => !empty($p['featured'])));
            elseif ($status === 'outofstock')  $rows = array_values(array_filter($rows, fn($p) => ($p['stock'] ?? 'instock') === 'outofstock'));
            elseif ($status !== '')            $rows = array_values(array_filter($rows, fn($p) => ($p['status'] ?? 'published') === $status));

            $desc = str_ends_with($sort, '-desc');
            $key  = $desc ? substr($sort, 0, -5) : $sort;
            usort($rows, function ($a, $b) use ($key) {
                return match ($key) {
                    'price' => $a['price_min'] <=> $b['price_min'],
                    'date'  => strcmp((string) ($a['created'] ?? ''), (string) ($b['created'] ?? '')),
                    default => strcasecmp($a['name'], $b['name']),
                };
            });
            if ($desc) $rows = array_reverse($rows);

            render('products', ['title' => 'Products', 'products' => $products, 'rows' => $rows,
                                'q' => $q, 'cat' => $cat, 'type' => $type, 'stock' => $stock,
                                'status' => $status, 'sort' => $sort]);
            break;
        }

        if ($arg === 'new') {
            $product = ['id' => 0, 'slug' => '', 'name' => '', 'type' => 'simple', 'sku' => '',
                        'cats' => [], 'primary_cat' => '', 'tags' => [], 'images' => [],
                        'short' => '', 'desc' => '', 'price_min' => 0, 'price_max' => 0,
                        'purchasable' => true, 'status' => 'published', 'featured' => false,
                        'stock' => 'instock', 'created' => date('Y-m-d'),
                        'attrs' => [], 'variants' => []];
        } else {
            $product = find_product($arg, true);
            if (!$product) { http_response_code(404); render('missing', ['title' => 'Product not found']); break; }
        }

        $seoAll = is_file(ROOT_DIR . '/data/seo.php') ? (array) require ROOT_DIR . '/data/seo.php' : [];
        $seoKey = '/product/' . $product['slug'] . '/';

        if ($post) {
            if (isset($_POST['delete']) && $arg !== 'new') {
                $products = array_values(array_filter($products, fn($p) => $p['slug'] !== $product['slug']));
                save_products($products);
                unset($seoAll[$seoKey]);
                save_seo($seoAll);
                flash('Product deleted.');
                redirect('/admin/products');
            }

            if (isset($_POST['duplicate']) && $arg !== 'new') {
                $copy = $product;
                $copy['slug']     = unique_slug($product['slug'] . '-copy', $products);
                $copy['name']     = $product['name'] . ' (copy)';
                $copy['id']       = (int) (time() % 100000);
                $copy['status']   = 'draft';
                $copy['featured'] = false;
                $copy['created']  = date('Y-m-d');
                $products[] = $copy;
                save_products($products);
                flash('Copied to a new draft.');
                redirect('/admin/products/' . rawurlencode($copy['slug']));
            }

            [$product, $errors] = save_product_from_post($product, $products, $arg === 'new');
            if (!$errors) {
                // the product's own search appearance lives alongside every other URL
                $key   = '/product/' . $product['slug'] . '/';
                $entry = $seoAll[$key] ?? [];
                foreach (['title' => 'seo_title', 'description' => 'seo_description',
                          'canonical' => 'seo_canonical', 'robots' => 'seo_robots'] as $field => $input) {
                    $value = trim((string) ($_POST[$input] ?? ''));
                    if ($value === '') unset($entry[$field]); else $entry[$field] = $value;
                }
                if ($seoKey !== $key) unset($seoAll[$seoKey]);      // slug changed
                if ($entry) $seoAll[$key] = $entry; else unset($seoAll[$key]);
                save_seo($seoAll);

                flash('Product saved.');
                redirect('/admin/products/' . rawurlencode($product['slug']));
            }
            render('product', ['title' => 'Edit product', 'product' => $product, 'errors' => $errors,
                               'isNew' => $arg === 'new', 'seoRow' => $seoAll[$seoKey] ?? []]);
            break;
        }
        render('product', ['title' => $arg === 'new' ? 'New product' : 'Edit product',
                           'product' => $product, 'errors' => [], 'isNew' => $arg === 'new',
                           'seoRow' => $seoAll[$seoKey] ?? []]);
        break;

    /* -------------------------------------------------------- categories */
    case 'categories':
        $categories = all_categories();
        $seo    = is_file(ROOT_DIR . '/data/seo.php') ? (array) require ROOT_DIR . '/data/seo.php' : [];
        $errors = [];

        if ($post) {
            $act = (string) ($_POST['act'] ?? '');

            if ($act === 'bulk') {
                $picked = array_values(array_filter((array) ($_POST['slugs'] ?? [])));
                if ($picked && ($_POST['bulk'] ?? '') === 'delete') {
                    $categories = array_values(array_filter($categories, fn($c) => !in_array($c['slug'], $picked, true)));
                    save_categories($categories);
                    flash(count($picked) . ' categor' . (count($picked) === 1 ? 'y' : 'ies') . ' deleted.');
                }
                redirect('/admin/categories');
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') $errors[] = 'The category needs a name.';

            $original = (string) ($_POST['original'] ?? '');
            $slug     = make_slug((string) ($_POST['slug'] ?? '') ?: $name);
            $slug     = unique_slug($slug, $categories, $original);
            $parent   = (string) ($_POST['parent'] ?? '');
            if ($parent === $slug) $parent = '';        // a category cannot parent itself

            $image = ltrim(trim((string) ($_POST['image'] ?? '')), '/');
            if ($image !== '' && (!str_starts_with($image, 'assets/img/') || !is_file(ROOT_DIR . '/' . $image))) {
                $image = '';
            }

            if (!$errors) {
                $existing = $original !== '' ? find_category($original) : null;
                $record = [
                    'id'          => $existing['id'] ?? (int) (time() % 100000),
                    'slug'        => $slug,
                    'name'        => $name,
                    'parent'      => $parent,
                    'path'        => $parent !== '' ? $parent . '/' . $slug : $slug,
                    'count'       => $existing['count'] ?? 0,
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'image'       => $image,
                    'sort'        => (int) ($_POST['sort'] ?? 0),
                ];

                $found = false;
                foreach ($categories as $i => $row) {
                    if ($original !== '' && $row['slug'] === $original) { $categories[$i] = $record; $found = true; break; }
                }
                if (!$found) $categories[] = $record;

                // children follow a parent that was renamed
                if ($original !== '' && $original !== $slug) {
                    foreach ($categories as $i => $row) {
                        if ($row['parent'] === $original) {
                            $categories[$i]['parent'] = $slug;
                            $categories[$i]['path']   = $slug . '/' . $row['slug'];
                        }
                    }
                }

                save_categories($categories);

                $key   = category_url($record);
                $entry = $seo[$key] ?? [];
                foreach (['title' => 'seo_title', 'description' => 'seo_description', 'robots' => 'seo_robots'] as $field => $input) {
                    $value = trim((string) ($_POST[$input] ?? ''));
                    if ($value === '') unset($entry[$field]); else $entry[$field] = $value;
                }
                if ($entry) $seo[$key] = $entry; else unset($seo[$key]);
                save_seo($seo);

                flash('Category saved.');
                redirect('/admin/categories?edit=' . urlencode($slug));
            }
        }

        $q       = trim((string) ($_GET['q'] ?? ''));
        $editing = ($e = (string) ($_GET['edit'] ?? '')) !== '' ? find_category($e) : null;

        $rows = $categories;
        if ($q !== '') {
            $needle = lower($q);
            $rows = array_values(array_filter($rows,
                fn($c) => str_contains(lower($c['name'] . ' ' . $c['slug']), $needle)));
        }
        usort($rows, function ($a, $b) {
            // parents first, each followed by its children
            $ka = $a['parent'] !== '' ? $a['parent'] . ' ' . $a['name'] : $a['name'];
            $kb = $b['parent'] !== '' ? $b['parent'] . ' ' . $b['name'] : $b['name'];
            return strcasecmp($ka, $kb);
        });

        render('categories', ['title' => 'Categories', 'categories' => $categories, 'rows' => $rows,
                              'editing' => $editing, 'q' => $q, 'seo' => $seo, 'errors' => $errors]);
        break;

    /* -------------------------------------------------------- attributes */
    case 'attributes':
        $attributes = all_attributes();
        $errors     = [];

        if ($post) {
            $act = (string) ($_POST['act'] ?? '');

            if ($act === 'delete') {
                $slug = (string) ($_POST['slug'] ?? '');
                $attributes = array_values(array_filter($attributes, fn($a) => $a['slug'] !== $slug));
                save_attributes($attributes);
                flash('Attribute deleted.');
                redirect('/admin/attributes');
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') $errors[] = 'The attribute needs a name.';

            $original = (string) ($_POST['original'] ?? '');
            $slug     = substr(make_slug((string) ($_POST['slug'] ?? '') ?: $name), 0, 28);
            $slug     = unique_slug($slug, $attributes, $original);

            $terms = [];
            foreach (preg_split('/[\r\n,]+/', (string) ($_POST['terms'] ?? '')) ?: [] as $term) {
                $term = trim($term);
                if ($term === '') continue;
                $terms[make_slug($term)] = ['name' => $term, 'slug' => make_slug($term)];
            }
            $terms = array_values($terms);

            if (!$errors) {
                $order = (string) ($_POST['order_by'] ?? 'custom');
                if (!isset(ATTR_ORDERS[$order])) $order = 'custom';
                if ($order === 'name')  usort($terms, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
                if ($order === 'value') usort($terms, fn($a, $b) => (float) $a['name'] <=> (float) $b['name']);

                $existing = $original !== '' ? find_attribute($original) : null;
                $record = ['slug' => $slug, 'name' => $name, 'order_by' => $order,
                           'sort' => $existing['sort'] ?? count($attributes), 'terms' => $terms];

                $found = false;
                foreach ($attributes as $i => $row) {
                    if ($original !== '' && $row['slug'] === $original) { $attributes[$i] = $record; $found = true; break; }
                }
                if (!$found) $attributes[] = $record;

                save_attributes($attributes);
                flash('Attribute saved.');
                redirect('/admin/attributes?edit=' . urlencode($slug));
            }
        }

        $editing = ($e = (string) ($_GET['edit'] ?? '')) !== '' ? find_attribute($e) : null;

        // how many products actually use each one
        $usage = [];
        foreach (all_products(true) as $prod) {
            foreach ($prod['attrs'] ?? [] as $a) {
                $usage[$a['name']] = ($usage[$a['name']] ?? 0) + 1;
            }
        }

        render('attributes', ['title' => 'Attributes', 'attributes' => $attributes,
                              'editing' => $editing, 'usage' => $usage, 'errors' => $errors]);
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

    // Attributes first: their terms give every variant a stable key, which is
    // what the buttons on the product page match against.
    $attrs = [];
    foreach ((array) ($_POST['attr'] ?? []) as $row) {
        // deliberately not $name — that already holds the product's own name
        $attrName = trim((string) ($row['name'] ?? ''));
        if ($attrName === '') continue;
        $terms = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($row['terms'] ?? '')))) as $term) {
            $terms[] = ['name' => $term, 'slug' => make_slug($term)];
        }
        if (!$terms) continue;
        $attrs[] = ['name' => $attrName, 'variation' => !empty($row['variation']), 'terms' => $terms];
    }

    /** "Length: 20m" -> the slug of the 20m term, in attribute order. */
    $keyFor = function (string $label) use ($attrs): string {
        $picked = [];
        foreach (explode(',', $label) as $piece) {
            [$attrName, $termName] = array_pad(explode(':', $piece, 2), 2, '');
            $picked[trim($attrName)] = trim($termName);
        }
        $parts = [];
        foreach ($attrs as $a) {
            if (!$a['variation']) continue;
            $want = $picked[$a['name']] ?? '';
            $slug = '';
            foreach ($a['terms'] as $t) {
                if (strcasecmp($t['name'], $want) === 0) { $slug = $t['slug']; break; }
            }
            $parts[] = $slug;
        }
        return $parts ? implode('|', $parts) : make_slug($label);
    };

    $variants = [];
    foreach ((array) ($_POST['variant'] ?? []) as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') continue;
        $variants[] = [
            'key'   => $keyFor($label),
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
        'primary_cat' => in_array((string) ($_POST['primary_cat'] ?? ''), (array) ($_POST['cats'] ?? []), true)
                          ? (string) $_POST['primary_cat'] : '',
        'tags'        => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? ''))))),
        'images'      => $images,
        'short'       => trim((string) ($_POST['short'] ?? '')),
        'desc'        => trim((string) ($_POST['desc'] ?? '')),
        'price_min'   => $prices ? min($prices) : $single,
        'price_max'   => $prices ? max($prices) : $single,
        'purchasable' => ($prices ? min($prices) : $single) > 0,
        'status'      => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        'featured'    => isset($_POST['featured']),
        'stock'       => ($_POST['stock'] ?? 'instock') === 'outofstock' ? 'outofstock' : 'instock',
        'created'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['created'] ?? ''))
                          ? (string) $_POST['created'] : ($product['created'] ?? date('Y-m-d')),
        'attrs'       => $attrs,
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

/** Apply a bulk action in place. Returns how many rows changed. */
function apply_bulk(array &$products, array $slugs, string $action): int
{
    if ($action === 'delete') {
        $before = count($products);
        $products = array_values(array_filter($products, fn($p) => !in_array($p['slug'], $slugs, true)));
        return $before - count($products);
    }

    $changes = [
        'publish'    => ['status', 'published'],
        'draft'      => ['status', 'draft'],
        'feature'    => ['featured', true],
        'unfeature'  => ['featured', false],
        'instock'    => ['stock', 'instock'],
        'outofstock' => ['stock', 'outofstock'],
    ];
    if (!isset($changes[$action])) return 0;
    [$field, $value] = $changes[$action];

    $n = 0;
    foreach ($products as $i => $row) {
        if (!in_array($row['slug'], $slugs, true)) continue;
        if (($products[$i][$field] ?? null) === $value) continue;
        $products[$i][$field] = $value;
        $n++;
    }
    return $n;
}

/** Stream the catalogue as CSV. */
function export_products(array $products): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="argflex-products-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");           // BOM, so Excel opens it as UTF-8
    fputcsv($out, ['slug', 'name', 'sku', 'status', 'featured', 'stock', 'categories',
                   'price_min', 'price_max', 'options', 'short', 'description', 'images', 'created']);

    foreach ($products as $p) {
        $options = implode(' | ', array_map(
            fn($v) => $v['label'] . ' = ' . number_format($v['price'] / 100, 2, '.', ''),
            $p['variants']));
        fputcsv($out, [
            $p['slug'], $p['name'], $p['sku'],
            $p['status'] ?? 'published', !empty($p['featured']) ? 'yes' : 'no',
            $p['stock'] ?? 'instock', implode(' | ', $p['cats']),
            number_format($p['price_min'] / 100, 2, '.', ''),
            number_format($p['price_max'] / 100, 2, '.', ''),
            $options, $p['short'], $p['desc'],
            implode(' | ', $p['images']), $p['created'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

/**
 * Read a CSV back in. Rows are matched on slug: known slugs are updated,
 * new ones are added, and anything missing from the file is left alone —
 * an import can never silently empty the catalogue.
 */
function import_products(array $file, array $products): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Choose a CSV file first.'];
    }
    $handle = @fopen($file['tmp_name'], 'r');
    if (!$handle) return ['ok' => false, 'message' => 'That file could not be read.'];

    $head = fgetcsv($handle);
    if (!$head) { fclose($handle); return ['ok' => false, 'message' => 'The file is empty.']; }
    $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
    $cols = array_flip(array_map('trim', $head));
    if (!isset($cols['slug'])) {
        fclose($handle);
        return ['ok' => false, 'message' => 'The file needs a "slug" column — export first to see the format.'];
    }

    $bySlug = [];
    foreach ($products as $i => $row) $bySlug[$row['slug']] = $i;

    $added = $updated = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $get = fn(string $k, $fallback = '') => isset($cols[$k], $row[$cols[$k]]) ? trim((string) $row[$cols[$k]]) : $fallback;

        // Keep an existing slug exactly as it is — a few were imported from
        // WordPress percent-encoded (mm%c2%b3), and re-slugifying those would
        // not match, so the row would be added again as a duplicate.
        $raw  = $get('slug');
        $slug = isset($bySlug[$raw]) ? $raw : make_slug($raw);
        if ($slug === '' || $get('name') === '') continue;

        $variants = [];
        foreach (array_filter(array_map('trim', explode('|', $get('options')))) as $piece) {
            [$label, $price] = array_pad(explode('=', $piece, 2), 2, '0');
            $label = trim($label);
            if ($label === '') continue;
            $variants[] = ['key' => make_slug($label), 'attrs' => [], 'label' => $label,
                           'price' => (int) round((float) trim($price) * 100)];
        }
        usort($variants, fn($a, $b) => $a['price'] <=> $b['price']);
        $prices = array_column($variants, 'price');
        $single = (int) round((float) $get('price_min', '0') * 100);

        $images = array_values(array_filter(array_map(
            fn($src) => ltrim(trim($src), '/'),
            explode('|', $get('images'))
        ), fn($src) => $src !== '' && is_file(ROOT_DIR . '/' . $src)));

        $record = [
            'id'          => 0,
            'slug'        => $slug,
            'name'        => $get('name'),
            'type'        => $variants ? 'variable' : 'simple',
            'sku'         => $get('sku'),
            'cats'        => array_values(array_filter(array_map('trim', explode('|', $get('categories'))))),
            'images'      => $images,
            'short'       => $get('short'),
            'desc'        => $get('description'),
            'price_min'   => $prices ? min($prices) : $single,
            'price_max'   => $prices ? max($prices) : $single,
            'purchasable' => ($prices ? min($prices) : $single) > 0,
            'status'      => $get('status', 'published') === 'draft' ? 'draft' : 'published',
            'featured'    => in_array(strtolower($get('featured')), ['yes', '1', 'true'], true),
            'stock'       => $get('stock', 'instock') === 'outofstock' ? 'outofstock' : 'instock',
            'created'     => $get('created') ?: date('Y-m-d'),
            'attrs'       => [],
            'variants'    => $variants,
        ];

        if (isset($bySlug[$slug])) {
            $keep = $products[$bySlug[$slug]];
            $record['id']    = $keep['id'];
            $record['attrs'] = $keep['attrs'];          // the picker layout is not in the CSV
            $products[$bySlug[$slug]] = $record;
            $updated++;
        } else {
            $record['id'] = (int) (time() % 100000) + $added;
            $products[]   = $record;
            $added++;
        }
    }
    fclose($handle);

    if (!$added && !$updated) return ['ok' => false, 'message' => 'No usable rows found in that file.'];
    if (!save_products($products)) return ['ok' => false, 'message' => 'Could not write data/products.php.'];

    return ['ok' => true, 'message' => "Import finished: {$updated} updated, {$added} added."];
}

/** Render a view inside the admin chrome. */
function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $viewName = $view;
    require __DIR__ . '/views/layout.php';
}
