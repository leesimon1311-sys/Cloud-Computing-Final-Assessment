<?php
declare(strict_types=1);

/**
 * Front controller.
 *
 * Every request enters here and is dispatched on the "page" parameter, which
 * keeps the application working identically under XAMPP and inside the cloud
 * container without depending on URL rewriting.
 */

require __DIR__ . '/../src/bootstrap.php';

$page   = (string) ($_GET['page'] ?? 'dashboard');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// The schema has to exist before anything else can run.
if (!db_is_installed()) {
    http_response_code(503);
    require APP_ROOT . '/views/not_installed.php';
    exit;
}

// --- Public routes ----------------------------------------------------------

if ($page === 'login') {
    if (is_logged_in()) {
        redirect('dashboard');
    }

    $error    = null;
    $username = '';

    if ($method === 'POST') {
        csrf_verify();
        $username = post('username');
        $password = (string) ($_POST['password'] ?? '');

        $lockout = login_lockout_remaining();
        if ($lockout > 0) {
            $error = 'Too many failed attempts. Please wait ' . $lockout . ' seconds and try again.';
        } elseif ($username === '' || $password === '') {
            $error = 'Please enter both your username and password.';
        } elseif (attempt_login($username, $password)) {
            flash('success', 'Welcome back, ' . current_user()['full_name'] . '.');
            redirect('dashboard');
        } else {
            // Deliberately vague: revealing which half was wrong would help an
            // attacker enumerate valid usernames.
            $error = 'Invalid username or password.';
        }
    }

    render_bare('login', ['error' => $error, 'username' => $username], 'Sign in');
    exit;
}

if ($page === 'logout') {
    logout();
    session_boot();
    flash('success', 'You have been signed out.');
    redirect('login');
}

// --- Everything below requires an authenticated session ---------------------

require_login();

switch ($page) {

    case 'dashboard':
        render('dashboard', [
            'stats'      => inventory_stats(),
            'attention'  => attention_list(),
            'categories' => value_by_category(),
            'recentLogs' => logs_recent(6),
        ], 'Dashboard');
        break;

    case 'products':
        $filters = [
            'q'        => query('q'),
            'category' => query_int('category'),
            'status'   => query('status'),
        ];

        render('products', [
            'result'     => products_search($filters, max(1, query_int('p', 1))),
            'filters'    => $filters,
            'categories' => categories_all(),
        ], 'Products');
        break;

    case 'product_new':
    case 'product_edit':
        $isEdit  = $page === 'product_edit';
        $id      = query_int('id');
        $product = null;

        if ($isEdit) {
            $product = product_find($id);
            if ($product === null) {
                flash('error', 'That product no longer exists.');
                redirect('products');
            }
        }

        $errors = [];
        $values = $product ?? [
            'sku' => '', 'name' => '', 'category_id' => null, 'type' => 'standard',
            'price' => '', 'quantity' => '', 'reorder_level' => '5', 'expiry_date' => '',
        ];

        if ($method === 'POST') {
            csrf_verify();
            [$errors, $clean] = product_validate($_POST, $isEdit ? $id : null);

            // Keep what the user typed so the form can be re-displayed.
            $values = array_merge($values, $_POST);

            if ($errors === []) {
                if ($isEdit) {
                    product_update($id, $clean);
                    log_activity('product_update', 'product', $id, $clean['sku'] . ' - ' . $clean['name']);
                    flash('success', 'Product "' . $clean['name'] . '" was updated.');
                } else {
                    $newId = product_create($clean);
                    log_activity('product_create', 'product', $newId, $clean['sku'] . ' - ' . $clean['name']);
                    flash('success', 'Product "' . $clean['name'] . '" was added.');
                }
                redirect('products');
            }
        }

        render('product_form', [
            'isEdit'     => $isEdit,
            'product'    => $product,
            'values'     => $values,
            'errors'     => $errors,
            'categories' => categories_all(),
        ], $isEdit ? 'Edit product' : 'Add product');
        break;

    case 'product_delete':
        if ($method !== 'POST') {
            redirect('products');
        }
        csrf_verify();

        $id      = (int) post('id');
        $product = product_find($id);

        if ($product === null) {
            flash('error', 'That product no longer exists.');
        } else {
            product_delete($id);
            log_activity('product_delete', 'product', $id, $product['sku'] . ' - ' . $product['name']);
            flash('success', 'Product "' . $product['name'] . '" was deleted.');
        }
        redirect('products');
        break;

    case 'product_adjust':
        if ($method !== 'POST') {
            redirect('products');
        }
        csrf_verify();

        $id      = (int) post('id');
        $delta   = (int) post('delta');
        $product = product_adjust_quantity($id, $delta);

        if ($product === null) {
            flash('error', 'That product no longer exists.');
        } else {
            log_activity(
                'stock_adjust',
                'product',
                $id,
                sprintf('%s %s%d, new quantity %d', $product['sku'], $delta > 0 ? '+' : '', $delta, $product['quantity'])
            );
            flash('success', $product['name'] . ' stock is now ' . $product['quantity'] . '.');
        }
        redirect('products', array_filter([
            'q'      => query('q'),
            'status' => query('status'),
            'p'      => query_int('p', 1) > 1 ? query_int('p', 1) : null,
        ]));
        break;

    case 'export':
        $rows = products_all_for_export();
        log_activity('export', 'product', null, count($rows) . ' rows exported to CSV');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['SKU', 'Name', 'Category', 'Type', 'Price', 'Quantity', 'Reorder level', 'Expiry date', 'Last updated']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;

    case 'logs':
        render('logs', [
            'result' => logs_page(max(1, query_int('p', 1))),
        ], 'Activity log');
        break;

    case 'account':
        $error   = null;
        $success = null;

        if ($method === 'POST') {
            csrf_verify();

            $current = (string) ($_POST['current_password'] ?? '');
            $new     = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if ($new !== $confirm) {
                $error = 'The new password and its confirmation do not match.';
            } else {
                $result = change_password((int) current_user()['id'], $current, $new);
                if ($result === true) {
                    $success = 'Your password has been changed.';
                } else {
                    $error = $result;
                }
            }
        }

        render('account', ['error' => $error, 'success' => $success], 'My account');
        break;

    default:
        http_response_code(404);
        render('not_found', [], 'Page not found');
        break;
}
