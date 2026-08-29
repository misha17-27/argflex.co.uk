<?php
/**
 * Admin front controller.
 *
 * Everything under /admin/ lands here. Nothing is reachable without a
 * session, every POST needs a CSRF token, and the whole area is noindex.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/config.php';
require_once ROOT_DIR . '/inc/store.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/page-schema.php';
require_once __DIR__ . '/inc/reports.php';
require_once __DIR__ . '/inc/status.php';

const ATTR_ORDERS = ['custom' => 'Custom ordering', 'name' => 'Name', 'value' => 'Numeric value'];

const SETTINGS_TABS = [
    'general'  => 'General',
    'products' => 'Products',
    'tax'      => 'Tax',
    'shipping' => 'Shipping',
    'payments' => 'Payments',
    'emails'   => 'Emails',
    'advanced' => 'Advanced',
];
require_once ROOT_DIR . '/inc/mail.php';
require_once ROOT_DIR . '/inc/turnstile.php';

admin_session_start();

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin/', PHP_URL_PATH) ?? '/admin/';
$segs = array_values(array_filter(explode('/', trim($path, '/'))));
array_shift($segs);                       // drop "admin"
$route = $segs[0] ?? '';
$arg   = isset($segs[1]) ? rawurldecode($segs[1]) : '';
$sub   = isset($segs[2]) ? rawurldecode($segs[2]) : '';

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
                'customers'  => count(all_customers()),
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

        // the printable documents render on their own, without the admin chrome
        if ($sub === 'invoice' || $sub === 'note') {
            if ($sub === 'invoice') issue_invoice($order);
            $kind   = $sub;
            $values = settings();
            require __DIR__ . '/views/document.php';
            exit;
        }

        if ($post) {
            if (isset($_POST['delete'])) {
                delete_order($arg);
                flash('Order ' . $arg . ' deleted.');
                redirect('/admin/orders');
            }
            // ---- refund
            if (isset($_POST['refund'])) {
                $amount = max(0, (int) round((float) ($_POST['refund_amount'] ?? 0) * 100));
                $with   = add_refund($order, $amount, trim((string) ($_POST['refund_reason'] ?? '')),
                                     (string) (current_user()['email'] ?? ''));
                if ($with === null) {
                    flash($amount <= 0
                        ? 'Enter an amount to refund.'
                        : 'That is more than the ' . money(order_outstanding($order))
                          . ' still owed on this order.', 'bad');
                } else {
                    save_order($with);
                    flash(money($amount) . ' refunded.'
                        . (order_outstanding($with) === 0 ? ' The order is now fully refunded.' : ''));
                }
                redirect('/admin/orders/' . rawurlencode($arg));
            }

            // ---- edit the lines
            if (isset($_POST['relines'])) {
                $items = [];
                foreach ((array) ($_POST['line'] ?? []) as $i => $row) {
                    if (!isset($order['order']['items'][$i])) continue;
                    if (!empty($row['remove'])) continue;
                    $item = $order['order']['items'][$i];
                    $item['qty'] = max(1, min(9999, (int) ($row['qty'] ?? 1)));
                    $items[] = $item;
                }

                // a product added by hand takes today's price
                $adding = trim((string) ($_POST['add_slug'] ?? ''));
                if ($adding !== '' && ($fresh = find_product($adding, true))) {
                    $option = trim((string) ($_POST['add_option'] ?? ''));
                    $price  = effective_min($fresh);
                    foreach ($fresh['variants'] as $v) {
                        if ($v['label'] === $option) { $price = variant_price($v, $fresh); break; }
                    }
                    $qty = max(1, min(9999, (int) ($_POST['add_qty'] ?? 1)));
                    $items[] = ['slug' => $fresh['slug'], 'title' => $fresh['name'],
                                'option' => $option, 'qty' => $qty, 'price' => $price,
                                'line' => $price * $qty];
                }

                if (!$items) {
                    flash('An order needs at least one line.', 'bad');
                    redirect('/admin/orders/' . rawurlencode($arg));
                }

                $order['order']['items']    = $items;
                $order['order']['shipping'] = max(0, (int) round((float) ($_POST['shipping'] ?? 0) * 100));
                $order['edited_at']         = date('c');
                save_order(recalculate_order($order));
                flash('Order updated. The totals have been worked out again.');
                redirect('/admin/orders/' . rawurlencode($arg));
            }

            $status = (string) ($_POST['status'] ?? 'new');
            if (isset(ORDER_STATUSES[$status])) {
                $changed = ($order['status'] ?? 'new') !== $status;
                $order['status'] = $status;
                $order['note']   = trim((string) ($_POST['note'] ?? ''));
                $order['updated_at'] = date('c');
                save_order($order);

                $told = $changed && isset($_POST['notify'])
                     && send_status_email($order, $status, (string) $order['note']);
                flash('Order updated.' . ($told ? ' The customer has been emailed.' : ''));
            }
            redirect('/admin/orders/' . rawurlencode($arg));
        }
        render('order', ['title' => 'Order ' . $order['reference'], 'order' => $order]);
        break;

    /* ------------------------------------------------------------ status */
    case 'status':
        render('status', ['title' => 'System status', 'groups' => status_groups()]);
        break;

    /* ----------------------------------------------------------- reports */
    case 'reports':
        $days   = (string) ($_GET['range'] ?? '30');
        $days   = isset(REPORT_RANGES[$days]) ? (int) $days : 30;
        $orders = orders_in_range($days);

        if ($arg === 'export') { export_orders($orders, $days); }

        render('reports', [
            'title'      => 'Reports',
            'days'       => $days,
            'orders'     => $orders,
            'totals'     => report_totals($orders),
            'series'     => report_series($orders, $days),
            'products'   => report_products($orders),
            'categories' => report_categories($orders),
            'statuses'   => report_statuses($orders),
            'zones'      => report_breakdown($orders, 'shipping_zone'),
            'codes'      => report_breakdown($orders, 'coupon'),
        ]);
        break;

    /* --------------------------------------------------------- customers */
    case 'customers':
        $people = all_customers();

        if ($arg === 'export') { export_customers($people); }

        if ($arg === '') {
            $q    = trim((string) ($_GET['q'] ?? ''));
            $sort = (string) ($_GET['sort'] ?? '');

            if ($q !== '') {
                $needle = lower($q);
                $people = array_filter($people, fn($c) => str_contains(
                    lower($c['name'] . ' ' . $c['email'] . ' ' . $c['company'] . ' '
                        . $c['city'] . ' ' . $c['postcode'] . ' ' . $c['country']), $needle));
            }

            $orderBy = [
                'name'   => fn($a, $b) => strcasecmp($a['name'] ?: $a['email'], $b['name'] ?: $b['email']),
                'orders' => fn($a, $b) => $b['orders'] <=> $a['orders'],
                'spent'  => fn($a, $b) => $b['spent']  <=> $a['spent'],
                'last'   => fn($a, $b) => strcmp($b['last_at'], $a['last_at']),
            ];
            if (isset($orderBy[$sort])) uasort($people, $orderBy[$sort]);

            render('customers', [
                'title'     => 'Customers',
                'customers' => $people,
                'q'         => $q,
                'sort'      => $sort,
                'actions'   => $people
                    ? '<a class="btn ghost" href="/admin/customers/export">Export CSV</a>' : '',
            ]);
            break;
        }

        $customer = find_customer($arg);
        if (!$customer) {
            http_response_code(404);
            render('missing', ['title' => 'Customer not found']);
            break;
        }
        render('customer', [
            'title'     => $customer['name'] !== '' ? $customer['name'] : $customer['email'],
            'customer'  => $customer,
            'orders'    => customer_orders($arg),
            'enquiries' => customer_enquiries($arg),
        ]);
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
            $product = ['manage_stock' => (bool) setting('manage_stock')] + PRODUCT_EXTRAS + ['id' => 0, 'slug' => '', 'name' => '', 'type' => 'simple', 'sku' => '',
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

    /* ------------------------------------------------------------- blog */
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

    /* ---------------------------------------------------------- reviews */
    case 'reviews':
        $reviews = all_reviews();

        if ($post) {
            $wanted = (string) ($_POST['bulk'] ?? '');
            $picked = array_values(array_filter((array) ($_POST['ids'] ?? [])));

            // the per-row buttons carry "action:id" so one form serves both
            if (($one = (string) ($_POST['one'] ?? '')) !== '') {
                [$wanted, $id] = array_pad(explode(':', $one, 2), 2, '');
                $picked = [$id];
            }

            $changed = 0;
            if ($picked && $wanted === 'delete') {
                $before  = count($reviews);
                $reviews = array_values(array_filter($reviews,
                    fn($r) => !in_array($r['id'], $picked, true)));
                $changed = $before - count($reviews);
            } elseif ($picked && isset(REVIEW_STATUSES[$wanted])) {
                foreach ($reviews as $i => $r) {
                    if (!in_array($r['id'], $picked, true) || $r['status'] === $wanted) continue;
                    $reviews[$i]['status'] = $wanted;
                    $changed++;
                }
            }
            if ($changed) {
                save_reviews($reviews);
                flash($changed . ' review' . ($changed === 1 ? '' : 's') . ' updated.');
            }
            redirect('/admin/reviews' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        }

        $counts = ['' => count($reviews)];
        foreach (array_keys(REVIEW_STATUSES) as $key) {
            $counts[$key] = count(array_filter($reviews, fn($r) => $r['status'] === $key));
        }

        $filter = (string) ($_GET['status'] ?? 'pending');
        $q      = trim((string) ($_GET['q'] ?? ''));

        $rows = $reviews;
        if ($filter !== '' && isset(REVIEW_STATUSES[$filter])) {
            $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $filter));
        }
        if ($q !== '') {
            $needle = lower($q);
            $rows = array_values(array_filter($rows, fn($r) => str_contains(
                lower($r['author'] . ' ' . $r['body'] . ' ' . $r['product'] . ' ' . $r['email']), $needle)));
        }
        usort($rows, fn($a, $b) => strcmp($b['id'], $a['id']));

        render('reviews', ['title' => 'Reviews', 'reviews' => $rows, 'counts' => $counts,
                           'filter' => $filter, 'q' => $q]);
        break;

    /* ---------------------------------------------------------- coupons */
    case 'coupons':
        $coupons = all_coupons();

        if ($arg === '') {
            if ($post) {
                $picked = array_values(array_filter((array) ($_POST['codes'] ?? [])));
                $action = (string) ($_POST['bulk'] ?? '');
                if ($picked && $action !== '') {
                    $changed = 0;
                    if ($action === 'delete') {
                        $before  = count($coupons);
                        $coupons = array_values(array_filter($coupons,
                            fn($c) => !in_array($c['code'], $picked, true)));
                        $changed = $before - count($coupons);
                    } else {
                        $want = $action === 'enable';
                        foreach ($coupons as $i => $c) {
                            if (!in_array($c['code'], $picked, true)) continue;
                            if (!empty($c['enabled']) === $want) continue;
                            $coupons[$i]['enabled'] = $want;
                            $changed++;
                        }
                    }
                    if ($changed) save_coupons($coupons);
                    flash($changed . ' code' . ($changed === 1 ? '' : 's') . ' updated.');
                }
                redirect('/admin/coupons');
            }

            $q    = trim((string) ($_GET['q'] ?? ''));
            $rows = $q === '' ? $coupons : array_values(array_filter($coupons,
                fn($c) => str_contains(lower($c['code'] . ' ' . $c['description']), lower($q))));

            render('coupons', [
                'title'   => 'Discount codes',
                'coupons' => $rows,
                'q'       => $q,
                'actions' => '<a class="btn" href="/admin/coupons/new">Add a code</a>',
            ]);
            break;
        }

        $isNew  = $arg === 'new';
        $coupon = $isNew ? coupon_blank() : find_coupon($arg);
        if (!$coupon) {
            http_response_code(404);
            render('missing', ['title' => 'Code not found']);
            break;
        }

        $errors = [];
        if ($post) {
            if (isset($_POST['delete'])) {
                save_coupons(array_values(array_filter($coupons,
                    fn($c) => lower($c['code']) !== lower($coupon['code']))));
                flash('Code ' . $coupon['code'] . ' deleted.');
                redirect('/admin/coupons');
            }
            [$coupon, $errors] = save_coupon_from_post($coupon, $coupons, $isNew);
            if (!$errors) {
                flash('Code saved.');
                redirect('/admin/coupons/' . rawurlencode($coupon['code']));
            }
        }

        render('coupon', [
            'title'  => $isNew ? 'New discount code' : $coupon['code'],
            'coupon' => $coupon,
            'errors' => $errors,
            'isNew'  => $isNew,
        ]);
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
        $tab = $arg === '' ? 'general' : $arg;
        if (!isset(SETTINGS_TABS[$tab])) {
            http_response_code(404);
            render('missing', ['title' => 'Not found']);
            break;
        }

        if ($post) {
            $values = save_settings_tab($tab, settings());
            // any change here can reach the stylesheet or the config block, so
            // move the cache stamp on and visitors see it straight away
            $values['asset_ver'] = (string) ((int) $values['asset_ver'] + 1);
            save_settings($values);

            if ($tab === 'emails' && ($_POST['act'] ?? '') === 'test') {
                $error = '';
                $ok = send_mail((string) $values['mail_to'], 'Test message from ' . SITE_NAME,
                    email_html('Mail is working',
                        '<p style="margin:0">This is a test from the admin panel. If you are reading it, '
                      . 'the site can send order confirmations and enquiries.</p>'),
                    '', $error, true);
                flash($ok ? 'Test message sent to ' . $values['mail_to'] . '.' : 'Could not send: ' . $error,
                      $ok ? 'ok' : 'bad');
            } else {
                flash(SETTINGS_TABS[$tab] . ' settings saved.');
            }
            redirect('/admin/settings' . ($tab === 'general' ? '' : '/' . $tab));
        }

        render('settings', ['title' => 'Settings', 'tab' => $tab, 'values' => settings()]);
        break;

    case 'mail':                       // folded into Settings -> Emails
        redirect('/admin/settings/emails');

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

/**
 * Apply one Settings tab's fields to the settings array.
 *
 * Each tab only ever touches its own keys, so saving Shipping cannot disturb
 * what Emails holds even though both live in the same file.
 */
function save_settings_tab(string $tab, array $v): array
{
    $str   = fn(string $k, string $fallback = '') => trim((string) ($_POST[$k] ?? $fallback));
    $pence = fn($raw) => max(0, (int) round((float) $raw * 100));
    $codes2 = $codes = function ($raw): array {
        $out = [];
        foreach ((array) $raw as $code) {
            $code = strtoupper(trim((string) $code));
            if (isset(COUNTRIES[$code])) $out[] = $code;
        }
        return array_values(array_unique($out));
    };

    switch ($tab) {

        case 'general':
            foreach (['site_name', 'site_tag', 'phone', 'phone_href', 'email', 'address',
                      'hours_week', 'hours_weekend', 'map_url',
                      'store_addr1', 'store_addr2', 'store_city', 'store_postcode',
                      'company_number', 'vat_number',
                      'soc1_name', 'soc1_url', 'soc2_name', 'soc2_url',
                      'soc3_name', 'soc3_url', 'soc4_name', 'soc4_url'] as $k) {
                $v[$k] = $str($k, (string) $v[$k]);
            }
            foreach (['store_country', 'default_country'] as $k) {
                if (isset(COUNTRIES[$str($k)])) $v[$k] = $str($k);
            }
            $v['sell_to']        = in_array($str('sell_to'), ['all', 'selected'], true) ? $str('sell_to') : 'all';
            $v['ship_to']        = in_array($str('ship_to'), ['sell', 'selected', 'none'], true) ? $str('ship_to') : 'sell';
            $v['sell_countries'] = $codes($_POST['sell_countries'] ?? []);
            $v['ship_countries'] = $codes($_POST['ship_countries'] ?? []);

            if (isset(CURRENCIES[$str('currency')]))             $v['currency']     = $str('currency');
            if (isset(CURRENCY_POSITIONS[$str('currency_pos')])) $v['currency_pos'] = $str('currency_pos');
            $v['thousand_sep'] = substr((string) ($_POST['thousand_sep'] ?? ','), 0, 2);
            $v['decimal_sep']  = substr((string) ($_POST['decimal_sep'] ?? '.'), 0, 2) ?: '.';
            $v['decimals']     = max(0, min(4, (int) ($_POST['decimals'] ?? 2)));

            $v['enable_taxes']   = isset($_POST['enable_taxes']);
            $v['enable_coupons'] = isset($_POST['enable_coupons']);
            break;

        case 'products':
            $v['default_sort'] = in_array($str('default_sort'),
                ['default', 'name', 'price-asc', 'price-desc', 'new'], true) ? $str('default_sort') : 'default';
            $v['shop_notice']       = $str('shop_notice', (string) $v['shop_notice']);
            $v['enable_wishlist']   = isset($_POST['enable_wishlist']);
            $v['enable_reviews']    = isset($_POST['enable_reviews']);
            $v['review_approval']   = isset($_POST['review_approval']);
            $v['review_verified']   = isset($_POST['review_verified']);
            $v['enable_compare']    = isset($_POST['enable_compare']);
            $v['manage_stock']      = isset($_POST['manage_stock']);
            $v['hide_out_of_stock'] = isset($_POST['hide_out_of_stock']);
            $v['low_stock_qty']     = max(0, min(9999, (int) ($_POST['low_stock_qty'] ?? 2)));
            $v['stock_display']     = isset(STOCK_DISPLAY[$str('stock_display')]) ? $str('stock_display') : 'low';
            if (in_array($str('weight_unit'), ['kg', 'g', 'lbs', 'oz'], true))  $v['weight_unit']    = $str('weight_unit');
            if (in_array($str('dimension_unit'), ['cm', 'mm', 'm', 'in'], true)) $v['dimension_unit'] = $str('dimension_unit');
            break;

        case 'tax':
            $v['vat_rate']     = max(0, min(100, (int) ($_POST['vat_rate'] ?? 20)));
            $v['tax_label']    = $str('tax_label', (string) $v['tax_label']) ?: 'VAT';
            $v['price_suffix'] = $str('price_suffix', (string) $v['price_suffix']);

            $rules = [];
            foreach ((array) ($_POST['rate'] ?? []) as $rule) {
                $label = trim((string) ($rule['label'] ?? ''));
                $codes = $codes2($rule['countries'] ?? []);
                // a rule with no rate, no name and no countries is an empty row
                if ($label === '' && !$codes && (float) ($rule['rate'] ?? 0) <= 0
                    && empty($rule['enabled'])) continue;
                $rules[] = [
                    'countries' => $codes,
                    'rate'      => max(0.0, min(100.0, round((float) ($rule['rate'] ?? 0), 2))),
                    'label'     => $label,
                    'note'      => trim((string) ($rule['note'] ?? '')),
                    'enabled'   => !empty($rule['enabled']),
                ];
            }
            $v['tax_rates'] = $rules;
            break;

        case 'shipping':
            /* Only the classes. Zones and methods were dropped: carriage is
               priced on the metres in a basket, and the flat-price-per-zone
               table this used to write was read by nothing. The bands live in
               data/shipping.php and a model's own price on the model. */
            $names = array_filter(array_map('trim',
                preg_split('/?
/', (string) ($_POST['shipping_classes'] ?? '')) ?: []));
            $v['shipping_classes'] = array_values(array_unique($names));
            break;

        case 'payments':
            $rows = [];
            $seen = [];
            foreach ((array) ($_POST['pay'] ?? []) as $m) {
                $title = trim((string) ($m['title'] ?? ''));
                if ($title === '') continue;
                $id = make_slug((string) ($m['id'] ?? '') !== '' ? (string) $m['id'] : $title);
                while (in_array($id, $seen, true)) $id .= '-2';   // ids identify the order's method
                $seen[] = $id;
                $rows[] = [
                    'id'           => $id,
                    'enabled'      => !empty($m['enabled']),
                    'order'        => max(0, min(99, (int) ($m['order'] ?? 0))),
                    'title'        => $title,
                    'description'  => trim((string) ($m['description'] ?? '')),
                    'instructions' => trim((string) ($m['instructions'] ?? '')),
                ];
            }
            usort($rows, fn($a, $b) => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);
            $v['payment_methods'] = $rows;

            foreach (['invoice_prefix', 'bank_name', 'bank_sort', 'bank_account',
                      'bank_iban', 'bank_bic', 'invoice_terms'] as $k) {
                $v[$k] = trim((string) ($_POST[$k] ?? $v[$k]));
            }
            $v['invoice_days'] = max(0, min(180, (int) ($_POST['invoice_days'] ?? 0)));
            $v['invoice_next'] = max(1, min(999999, (int) ($_POST['invoice_next'] ?? 1)));

            /* Gateway credentials.
               A blank field leaves the stored key alone rather than wiping
               it, because the form never shows a secret back — so saving any
               other setting on this screen would otherwise delete the keys
               and quietly stop the shop taking money. */
            $keys = (array) ($v['gateways'] ?? []);
            foreach ([
                'stripe' => ['test_publishable', 'test_secret', 'live_publishable', 'live_secret', 'webhook_secret'],
                'paypal' => ['sandbox_client_id', 'sandbox_secret', 'live_client_id', 'live_secret'],
            ] as $gateway => $fields) {
                foreach ($fields as $field) {
                    $sent = trim((string) ($_POST['gw'][$gateway][$field] ?? ''));
                    if ($sent !== '') $keys[$gateway][$field] = $sent;
                }
            }
            $keys['stripe']['test_mode'] = !empty($_POST['gw']['stripe']['test_mode']);
            $keys['paypal']['sandbox']   = !empty($_POST['gw']['paypal']['sandbox']);

            // and a way to clear one deliberately
            foreach ((array) ($_POST['gw_clear'] ?? []) as $gateway => $fields) {
                foreach ((array) $fields as $field => $_) unset($keys[$gateway][$field]);
            }
            $v['gateways'] = $keys;
            break;

        case 'emails':
            $emails = [];
            foreach (EMAIL_KINDS as $kind => $meta) {
                $row = (array) ($_POST['email'][$kind] ?? []);
                $emails[$kind] = [
                    'enabled' => !empty($row['enabled']),
                    'to'      => trim((string) ($row['to'] ?? '')),
                    'subject' => trim((string) ($row['subject'] ?? '')),
                    'heading' => trim((string) ($row['heading'] ?? '')),
                ];
            }
            $v['emails'] = $emails;

            foreach (['mail_to', 'mail_from', 'mail_from_name', 'smtp_host', 'smtp_user',
                      'smtp_secure', 'email_footer'] as $k) {
                $v[$k] = trim((string) ($_POST[$k] ?? $v[$k]));
            }
            $v['smtp_port'] = max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587)));

            /* The mailbox password is never rendered back into the page, so a
               blank box means "leave it alone", not "clear it". Clearing is a
               deliberate act with its own checkbox — otherwise every save of
               an unrelated field on this tab would silently unset it. */
            $typed = (string) ($_POST['smtp_pass'] ?? '');
            if (isset($_POST['smtp_pass_clear'])) {
                $v['smtp_pass'] = '';
            } elseif ($typed !== '') {
                $v['smtp_pass'] = $typed;
            }

            $logo = ltrim($str('email_logo'), '/');
            if ($logo === '' || (str_starts_with($logo, 'assets/img/') && is_file(ROOT_DIR . '/' . $logo))) {
                $v['email_logo'] = $logo;
            }
            foreach (['email_accent', 'email_bg', 'email_body_bg', 'email_text'] as $k) {
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $str($k))) $v[$k] = strtolower($str($k));
            }
            break;

        case 'advanced':
            $terms = $str('terms_path');
            if ($terms === '' || preg_match('#^/[a-z0-9_/-]*/$#', $terms)) $v['terms_path'] = $terms;

            $map = [];
            foreach ((array) ($_POST['redir'] ?? []) as $r) {
                $from = trim((string) ($r['from'] ?? ''));
                $to   = trim((string) ($r['to'] ?? ''));
                if ($from === '' || $to === '' || $from === $to) continue;
                if ($from[0] !== '/' || $to[0] !== '/') continue;    // only ever redirect within the site
                $map[$from] = $to;
            }
            $v['redirects'] = $map;
            break;
    }

    return $v;
}

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

        /* The ticked boxes first, in the order the form drew them — which is
           this product's own order, so a save does not re-sort the buttons on
           the product page. Anything typed into the box underneath is added
           after, and a value that is already ticked is not added twice: two
           spellings of one size become two options that never match. */
        /* Not $slug — that is the product's own, settled a few lines above,
           and reusing the name here renamed the product after whichever
           value happened to be last. On this site a slug is an indexed URL,
           so it is the worst thing in this function to get wrong. */
        $terms = [];
        $seen  = [];
        foreach ((array) ($row['pick'] ?? []) as $term) {
            $term = trim((string) $term);
            if ($term === '') continue;
            $termSlug = make_slug($term);
            if (isset($seen[$termSlug])) continue;
            $seen[$termSlug] = true;
            $terms[] = ['name' => $term, 'slug' => $termSlug];
        }
        foreach (array_filter(array_map('trim', explode(',', (string) ($row['terms'] ?? '')))) as $term) {
            $termSlug = make_slug($term);
            if (isset($seen[$termSlug])) continue;
            $seen[$termSlug] = true;
            $terms[] = ['name' => $term, 'slug' => $termSlug];
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

    /* What a variation knows that this form does not ask about.

       A variation carries its metre count, its stock, its ceiling, its
       shipping class and its WooCommerce id, and the editor shows none of
       them. Rebuilding the row from the posted fields alone threw all of it
       away — so the first save of any variable product silently reset every
       length to weight 0, which is the under-five-metre band, and put two
       sold-out fifty-metre coils back on sale. Carrying it forward by key
       is the whole fix. */
    $wasByKey = [];
    foreach ((array) ($product['variants'] ?? []) as $old) {
        $wasByKey[(string) ($old['key'] ?? '')] = $old;
    }

    /** "Length: 20m" -> ['Length' => '20m'], so a new row still knows itself. */
    $attrsFor = function (string $label) use ($attrs): array {
        $out = [];
        foreach (explode(',', $label) as $piece) {
            [$attrName, $termName] = array_pad(explode(':', $piece, 2), 2, '');
            $attrName = trim($attrName);
            $termName = trim($termName);
            if ($attrName === '' || $termName === '') continue;
            foreach ($attrs as $a) {
                if (strcasecmp($a['name'], $attrName) === 0) { $out[$a['name']] = $termName; break; }
            }
        }
        return $out;
    };

    /* An option row names its terms with a list per attribute now, so the
       label is assembled here rather than typed. A label typed by hand is a
       label that can be spelled wrong, and a variation whose spelling does
       not match its attribute can never be selected on the product page.
       The free-text field is still read for a product with no variation
       axes at all. */
    $labelFrom = function (array $row) use ($attrs): string {
        $picked = (array) ($row['pick'] ?? []);
        if (!$picked) return trim((string) ($row['label'] ?? ''));

        $parts = [];
        foreach ($attrs as $a) {                      // in the attributes' own order
            if (empty($a['variation'])) continue;
            $term = trim((string) ($picked[$a['name']] ?? ''));
            if ($term !== '') $parts[] = $a['name'] . ': ' . $term;
        }
        return implode(', ', $parts);
    };

    /* A delivery price of its own, band by band. A blank box means the
       shop's own price, so it is left out rather than stored as zero — zero
       is a real price and would mean free carriage. */
    $deliveryFrom = function ($posted): array {
        $out = [];
        foreach ((array) $posted as $id => $amount) {
            $amount = trim((string) $amount);
            if ($amount === '') continue;
            $out[(int) $id] = max(0, (int) round((float) $amount * 100));
        }
        ksort($out);
        return $out;
    };

    $variants = [];
    $seenKeys = [];
    foreach ((array) ($_POST['variant'] ?? []) as $row) {
        $label = $labelFrom($row);
        if ($label === '') continue;

        $key = $keyFor($label);
        // Two rows can now name the same combination, because the lists let
        // you pick it twice. The first wins; a duplicate would be a variation
        // the product page could never reach past the first.
        if (isset($seenKeys[$key])) continue;
        $seenKeys[$key] = true;

        $was = $wasByKey[$key] ?? [];

        /* A field the form sent wins; one it did not send is carried over.
           The editor asks about all of these now, but a form posted from
           somewhere else — the CSV import, a script — still keeps what the
           variation already knew rather than resetting it to nothing. */
        $sent = fn(string $field, $fallback) => array_key_exists($field, $row)
            ? $row[$field] : ($was[$field] ?? $fallback);

        $image = trim((string) $sent('image', ''));
        $image = ltrim($image, '/');
        // only ever point at a file we actually have
        if ($image !== '' && !(str_starts_with($image, 'assets/img/') && is_file(ROOT_DIR . '/' . $image))) {
            $image = '';
        }

        $variants[] = [
            'key'   => $key,
            'attrs' => $was['attrs'] ?? $attrsFor($label),
            'label' => $label,
            'price' => (int) round((float) ($row['price'] ?? 0) * 100),
            'sale'  => max(0, (int) round((float) ($row['sale'] ?? 0) * 100)),
            'id'             => (int) ($was['id'] ?? 0),
            'image'          => $image,
            'sku'            => trim((string) $sent('sku', '')),
            'weight'         => max(0, (int) $sent('weight', 0)),
            'stock'          => (string) $sent('stock', 'instock') === 'outofstock' ? 'outofstock' : 'instock',
            'manage_stock'   => (bool) $sent('manage_stock', false),
            'stock_qty'      => max(0, min(999999, (int) $sent('stock_qty', 0))),
            'shipping_class' => (string) $sent('shipping_class', ''),
            'delivery'       => array_key_exists('delivery', $row)
                                ? $deliveryFrom($row['delivery'])
                                : (array) ($was['delivery'] ?? []),
        ];
    }
    // Left in the order the editor showed them. Sorting by price here meant
    // the rows jumped about the moment anything was saved.

    $images = array_values(array_filter(array_map(
        fn($src) => ltrim(trim((string) $src), '/'),
        (array) ($_POST['images'] ?? [])
    )));
    // only ever point at files we actually have
    $images = array_values(array_filter($images, fn($src) =>
        str_starts_with($src, 'assets/img/') && is_file(ROOT_DIR . '/' . $src)));

    $single     = (int) round((float) ($_POST['price'] ?? 0) * 100);
    $singleSale = (int) round((float) ($_POST['sale_price'] ?? 0) * 100);
    $prices     = array_column($variants, 'price');

    // A sale price only counts when it is actually below the regular one, so
    // a leftover figure cannot quietly put a product on sale at full price.
    $sales = [];
    foreach ($variants as $v) {
        $sales[] = ($v['sale'] > 0 && $v['sale'] < $v['price']) ? $v['sale'] : $v['price'];
    }
    if ($variants) {
        $saleMin = min($sales);
        $saleMax = max($sales);
        $onSale  = $saleMin < min($prices) || $saleMax < max($prices);
    } else {
        $saleMin = $saleMax = ($singleSale > 0 && $singleSale < $single) ? $singleSale : 0;
        $onSale  = $saleMin > 0;
    }

    $date = function (string $key): string {
        $value = trim((string) ($_POST[$key] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    };
    $decimal = function (string $key): string {
        $value = trim((string) ($_POST[$key] ?? ''));
        return is_numeric($value) && (float) $value > 0
            ? rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') : '';
    };
    $slugList = function (string $key) use ($products): array {
        $known = array_column($products, 'slug');
        $out   = [];
        foreach ((array) ($_POST[$key] ?? []) as $slug) {
            if (in_array((string) $slug, $known, true)) $out[] = (string) $slug;
        }
        return array_values(array_unique($out));
    };

    $updated = array_merge($product, [
        'id'          => $product['id'] ?: (int) (time() % 100000),
        'slug'        => $slug,
        'name'        => $name,
        /* The editor now says which it is, rather than it being guessed from
           whether any option rows happened to survive. A product marked
           variable with nothing to choose from would be unbuyable, so that
           one case still falls back — the guess is the safety net, not the
           rule. */
        'type'        => (string) ($_POST['type'] ?? '') === 'variable' && $variants ? 'variable'
                         : ((string) ($_POST['type'] ?? '') === 'simple' ? 'simple'
                            : ($variants ? 'variable' : 'simple')),
        'sku'         => trim((string) ($_POST['sku'] ?? '')),
        'delivery'    => array_key_exists('delivery', $_POST)
                         ? $deliveryFrom($_POST['delivery'])
                         : (array) ($product['delivery'] ?? []),
        'cats'        => array_values(array_filter((array) ($_POST['cats'] ?? []))),
        'primary_cat' => in_array((string) ($_POST['primary_cat'] ?? ''), (array) ($_POST['cats'] ?? []), true)
                          ? (string) $_POST['primary_cat'] : '',
        'tags'        => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? ''))))),
        'images'      => $images,
        'short'       => trim((string) ($_POST['short'] ?? '')),
        'desc'        => trim((string) ($_POST['desc'] ?? '')),
        'price_min'   => $prices ? min($prices) : $single,
        'price_max'   => $prices ? max($prices) : $single,
        'sale_min'    => $onSale ? $saleMin : 0,
        'sale_max'    => $onSale ? $saleMax : 0,
        'sale_from'   => $date('sale_from'),
        'sale_to'     => $date('sale_to'),
        'purchasable' => ($prices ? min($prices) : $single) > 0,

        'manage_stock'      => isset($_POST['manage_stock']),
        'stock_qty'         => max(0, min(999999, (int) ($_POST['stock_qty'] ?? 0))),
        'backorders'        => isset(BACKORDER_MODES[(string) ($_POST['backorders'] ?? '')])
                                  ? (string) $_POST['backorders'] : 'no',
        'low_stock'         => max(0, min(9999, (int) ($_POST['low_stock'] ?? 0))),
        'sold_individually' => isset($_POST['sold_individually']),

        'weight'         => $decimal('weight'),
        'length'         => $decimal('length'),
        'width'          => $decimal('width'),
        'height'         => $decimal('height'),
        'shipping_class' => trim((string) ($_POST['shipping_class'] ?? '')),
        'virtual'        => isset($_POST['virtual']),

        'upsells'       => $slugList('upsells'),
        'crosssells'    => $slugList('crosssells'),
        'purchase_note' => trim((string) ($_POST['purchase_note'] ?? '')),
        'menu_order'    => max(-999, min(999, (int) ($_POST['menu_order'] ?? 0))),
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

/** The shape a new discount code starts from. */
function coupon_blank(): array
{
    return [
        'code' => '', 'description' => '', 'enabled' => true,
        'type' => 'percent', 'amount' => 10.0, 'free_shipping' => false,
        'min_spend' => 0, 'max_spend' => 0,
        'starts' => '', 'expires' => '',
        'usage_limit' => 0, 'used' => 0,
        'products' => [], 'categories' => [],
    ];
}

/** Apply a posted coupon form, returning [coupon, errors]. */
function save_coupon_from_post(array $coupon, array $coupons, bool $isNew): array
{
    $errors = [];
    $code   = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['code'] ?? '')) ?? '');
    if ($code === '') $errors[] = 'The code needs at least one letter or number.';

    foreach ($coupons as $row) {
        $clash = lower($row['code']) === lower($code)
              && ($isNew || lower($row['code']) !== lower($coupon['code']));
        if ($clash) { $errors[] = 'That code is already in use.'; break; }
    }

    $type   = (string) ($_POST['type'] ?? 'percent');
    $type   = isset(COUPON_TYPES[$type]) ? $type : 'percent';
    $amount = (float) ($_POST['amount'] ?? 0);

    // a percentage stays a percentage; a fixed amount is stored in pence
    $date = function (string $key): string {
        $value = trim((string) ($_POST[$key] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    };
    $slugs = function (string $key, array $known): array {
        $out = [];
        foreach ((array) ($_POST[$key] ?? []) as $slug) {
            $slug = (string) $slug;
            if (in_array($slug, $known, true)) $out[] = $slug;
        }
        return array_values(array_unique($out));
    };

    $updated = array_merge($coupon, [
        'code'          => $code,
        'description'   => trim((string) ($_POST['description'] ?? '')),
        'enabled'       => isset($_POST['enabled']),
        'type'          => $type,
        'amount'        => $type === 'percent'
                              ? round(max(0, min(100, $amount)), 2)
                              : max(0, (int) round($amount * 100)),
        'free_shipping' => isset($_POST['free_shipping']),
        'min_spend'     => max(0, (int) round((float) ($_POST['min_spend'] ?? 0) * 100)),
        'max_spend'     => max(0, (int) round((float) ($_POST['max_spend'] ?? 0) * 100)),
        'starts'        => $date('starts'),
        'expires'       => $date('expires'),
        'usage_limit'   => max(0, min(100000, (int) ($_POST['usage_limit'] ?? 0))),
        'used'          => isset($_POST['reset_used']) ? 0 : (int) ($coupon['used'] ?? 0),
        'products'      => $slugs('products',   array_column(all_products(true), 'slug')),
        'categories'    => $slugs('categories', array_column(all_categories(), 'slug')),
    ]);

    if ($updated['starts'] !== '' && $updated['expires'] !== '' && $updated['expires'] < $updated['starts']) {
        $errors[] = 'The end date is before the start date.';
    }
    if ($updated['max_spend'] > 0 && $updated['max_spend'] < $updated['min_spend']) {
        $errors[] = 'The maximum order is below the minimum.';
    }
    if ($errors) return [$updated, $errors];

    $found = false;
    foreach ($coupons as $i => $row) {
        if (!$isNew && lower($row['code']) === lower($coupon['code'])) {
            $coupons[$i] = $updated;
            $found = true;
            break;
        }
    }
    if (!$found) $coupons[] = $updated;

    if (!save_coupons($coupons)) $errors[] = 'Could not write data/coupons.php — check it is writable.';
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

/** Stream a range of orders as CSV, one row per order. */
function export_orders(array $orders, int $days): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="argflex-orders-'
         . ($days ? $days . 'd-' : 'all-') . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");           // BOM, so Excel opens it as UTF-8
    fputcsv($out, ['reference', 'placed', 'status', 'name', 'company', 'email', 'phone',
                   'city', 'postcode', 'country', 'zone', 'delivery', 'payment',
                   'coupon', 'discount', 'goods', 'shipping', 'tax', 'total', 'items']);

    foreach ($orders as $o) {
        $order = $o['order'];
        $c     = $o['customer'];
        $lines = implode(' | ', array_map(
            fn($i) => $i['qty'] . ' x ' . $i['title'] . ($i['option'] !== '' ? ' (' . $i['option'] . ')' : ''),
            $order['items']));

        fputcsv($out, [
            $o['reference'], substr((string) $o['placed_at'], 0, 16), $o['status'] ?? 'new',
            $c['name'] ?? '', $c['company'] ?? '', $c['email'] ?? '', $c['phone'] ?? '',
            $c['city'] ?? '', $c['postcode'] ?? '', $c['country'] ?? '',
            $order['shipping_zone'] ?? '', $order['shipping_title'] ?? '',
            $o['payment']['title'] ?? '',
            $order['coupon'] ?? '',
            number_format((int) ($order['discount'] ?? 0) / 100, 2, '.', ''),
            number_format((int) $order['subtotal'] / 100, 2, '.', ''),
            number_format((int) $order['shipping'] / 100, 2, '.', ''),
            number_format((int) $order['vat'] / 100, 2, '.', ''),
            number_format((int) $order['total'] / 100, 2, '.', ''),
            $lines,
        ]);
    }
    fclose($out);
    exit;
}

/** Stream the customer list as CSV. */
function export_customers(array $people): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="argflex-customers-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");           // BOM, so Excel opens it as UTF-8
    fputcsv($out, ['name', 'company', 'email', 'phone', 'address', 'city', 'postcode',
                   'country', 'orders', 'cancelled', 'spent', 'enquiries',
                   'first_order', 'last_order', 'references']);

    foreach ($people as $c) {
        fputcsv($out, [
            $c['name'], $c['company'], $c['email'], $c['phone'], $c['address'],
            $c['city'], $c['postcode'], $c['country'],
            $c['orders'], $c['cancelled'],
            number_format($c['spent'] / 100, 2, '.', ''),
            $c['enquiries'],
            substr($c['first_at'], 0, 10), substr($c['last_at'], 0, 10),
            implode(' | ', $c['references']),
        ]);
    }
    fclose($out);
    exit;
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
    $viewName = $view;
    $viewVars = $vars;
    $title    = (string) ($vars['title'] ?? 'Admin');   // the layout prints these two
    $actions  = (string) ($vars['actions'] ?? '');
    require __DIR__ . '/views/layout.php';
}

/**
 * Include a view with only its own variables in scope.
 *
 * Sharing a scope with the layout meant a name they both used — $groups,
 * $label, $icon — silently belonged to whichever ran last. That has cost
 * real bugs more than once, so a view now gets nothing it did not ask for.
 */
function render_view(string $name, array $vars): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/views/' . $name . '.php';
}
