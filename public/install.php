<?php
declare(strict_types=1);

/**
 * One-time installer.
 *
 * Creates the schema (when the connected account is allowed to) and loads the
 * demonstration data. It is reachable only with the INSTALL_TOKEN secret that
 * is stored in the environment, and it refuses to run at all when that
 * variable is empty - so leaving the file deployed is not an open door.
 *
 * Usage:  /install.php?token=YOUR_TOKEN[&admin_password=YourStrongPassword]
 */

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$expected = env('INSTALL_TOKEN');
$supplied = (string) ($_GET['token'] ?? '');

if ($expected === null) {
    http_response_code(403);
    exit("Installer disabled.\n\nSet the INSTALL_TOKEN environment variable to a long random value, then retry.\n");
}

if (!hash_equals($expected, $supplied)) {
    http_response_code(403);
    error_log('install.php rejected a request with an invalid token from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    exit("Forbidden.\n");
}

$driver = db_driver();
echo "Inventory Management System - installer\n";
echo str_repeat('=', 48) . "\n";
echo 'Engine : ' . db_server_version() . "\n";
echo 'Time   : ' . date('Y-m-d H:i:s T') . "\n\n";

// --- 1. Schema --------------------------------------------------------------

if (db_is_installed()) {
    echo "[skip] Tables already exist.\n";
} else {
    $file = APP_ROOT . '/sql/' . ($driver === 'pgsql' ? 'schema_pgsql.sql' : 'schema_mysql.sql');
    $sql  = file_get_contents($file);

    if ($sql === false) {
        http_response_code(500);
        exit("[fail] Could not read {$file}\n");
    }

    // Drop comment lines, then split on statement terminators.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        try {
            db()->exec($statement);
        } catch (Throwable $e) {
            http_response_code(500);
            echo "[fail] Statement failed: " . $e->getMessage() . "\n\n";
            echo "If this is a privilege error, create the schema with a database\n";
            echo "administrator account first (see sql/ and tools/setup_local.ps1),\n";
            echo "then run this installer again to load the sample data.\n";
            exit;
        }
    }

    echo "[ ok ] Schema created (users, categories, products, activity_log).\n";
}

// --- 2. Reference data ------------------------------------------------------

$categories = ['Beverages', 'Dry Goods', 'Fresh Produce', 'Household', 'Stationery'];
$inserted   = 0;

foreach ($categories as $name) {
    $exists = (int) q_val('SELECT COUNT(*) FROM categories WHERE name = :n', ['n' => $name], 0);
    if ($exists === 0) {
        q('INSERT INTO categories (name) VALUES (:n)', ['n' => $name]);
        $inserted++;
    }
}
echo "[ ok ] Categories ready ({$inserted} added, " . count($categories) . " total).\n";

// --- 3. Demonstration products ---------------------------------------------

$productCount = (int) q_val('SELECT COUNT(*) FROM products', [], 0);

if ($productCount > 0) {
    echo "[skip] Products table already contains {$productCount} rows.\n";
} else {
    $catId = [];
    foreach (q_all('SELECT id, name FROM categories') as $row) {
        $catId[$row['name']] = (int) $row['id'];
    }

    $soon  = date('Y-m-d', strtotime('+12 days'));
    $later = date('Y-m-d', strtotime('+8 months'));
    $past  = date('Y-m-d', strtotime('-5 days'));

    $seed = [
        ['SKU-1001', 'Mineral Water 500ml',       'Beverages',     'perishable', 1.20,   240, 50, $later],
        ['SKU-1002', 'Instant Coffee 200g',       'Beverages',     'perishable', 18.90,  36,  10, $soon],
        ['SKU-1003', 'Green Tea Box (25 bags)',   'Beverages',     'standard',   12.50,  8,   10, null],
        ['SKU-2001', 'Jasmine Rice 5kg',          'Dry Goods',     'standard',   27.00,  64,  15, null],
        ['SKU-2002', 'Wheat Flour 1kg',           'Dry Goods',     'perishable', 4.80,   120, 25, $later],
        ['SKU-2003', 'Sugar 1kg',                 'Dry Goods',     'standard',   3.60,   0,   20, null],
        ['SKU-3001', 'Fresh Milk 1L',             'Fresh Produce', 'perishable', 7.40,   42,  20, $soon],
        ['SKU-3002', 'Chicken Eggs (tray of 30)', 'Fresh Produce', 'perishable', 13.80,  18,  12, $past],
        ['SKU-4001', 'Dish Washing Liquid 900ml', 'Household',     'standard',   9.90,   55,  15, null],
        ['SKU-4002', 'Laundry Detergent 2kg',     'Household',     'standard',   22.50,  12,  10, null],
        ['SKU-5001', 'A4 Paper Ream 80gsm',       'Stationery',    'standard',   14.90,  90,  20, null],
        ['SKU-5002', 'Ballpoint Pen (box of 12)', 'Stationery',    'standard',   8.40,   4,   10, null],
    ];

    foreach ($seed as [$sku, $name, $category, $type, $price, $quantity, $reorder, $expiry]) {
        product_create([
            'sku'           => $sku,
            'name'          => $name,
            'category_id'   => $catId[$category] ?? null,
            'type'          => $type,
            'price'         => $price,
            'quantity'      => $quantity,
            'reorder_level' => $reorder,
            'expiry_date'   => $expiry,
        ]);
    }

    echo '[ ok ] Loaded ' . count($seed) . " demonstration products.\n";
}

// --- 4. Administrator account ----------------------------------------------

$userCount = (int) q_val('SELECT COUNT(*) FROM users', [], 0);

if ($userCount > 0) {
    echo "[skip] {$userCount} user account(s) already exist; no account was changed.\n";
    echo "\nInstallation complete. Open index.php to sign in.\n";
    exit;
}

$chosenPassword = (string) ($_GET['admin_password'] ?? '');
$generated = false;

if (strlen($chosenPassword) >= 8) {
    $password = $chosenPassword;
} else {
    // No weak built-in default: a strong password is generated and shown once.
    $password = bin2hex(random_bytes(6)) . '-' . bin2hex(random_bytes(3));
    $generated = true;
}

q(
    'INSERT INTO users (username, password_hash, full_name, role, created_at)
     VALUES (:u, :h, :f, :r, :c)',
    [
        'u' => 'admin',
        'h' => password_hash($password, PASSWORD_DEFAULT),
        'f' => 'System Administrator',
        'r' => 'admin',
        'c' => db_now(),
    ]
);

echo "[ ok ] Administrator account created.\n\n";
echo "    Username : admin\n";
echo '    Password : ' . $password . "\n\n";

if ($generated) {
    echo "This password was generated randomly and is shown ONCE. Save it now.\n";
}
echo "Change it from the My account page after signing in, and do not include\n";
echo "it in your report or in any screenshot.\n\n";
echo "Installation complete. Open index.php to sign in.\n";
