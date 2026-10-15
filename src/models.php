<?php
declare(strict_types=1);

/**
 * Data access for the inventory domain.
 *
 * These functions are the web equivalent of the InventoryManager class in the
 * original console prototype: addItem, viewInventory, updateItem, deleteItem
 * and searchItem. Serialised file storage has been replaced by a relational
 * database so that many users can work with the same data concurrently.
 *
 * Every statement uses bound parameters, and the SQL is written so that it is
 * accepted by both MySQL and PostgreSQL.
 */

const PRODUCT_TYPES = ['standard' => 'Standard', 'perishable' => 'Perishable'];
const PER_PAGE = 10;

// --- Categories -------------------------------------------------------------

function categories_all(): array
{
    return q_all('SELECT id, name FROM categories ORDER BY name');
}

function category_exists(int $id): bool
{
    return (int) q_val('SELECT COUNT(*) FROM categories WHERE id = :id', ['id' => $id], 0) > 0;
}

// --- Products ---------------------------------------------------------------

/**
 * Paginated, filtered product listing.
 *
 * $filters: q (search term), category (int), status (low|out|expiring|expired)
 */
function products_search(array $filters, int $page = 1): array
{
    $where  = [];
    $params = [];

    $term = trim((string) ($filters['q'] ?? ''));
    if ($term !== '') {
        // LOWER() on both sides keeps the search case-insensitive on either
        // engine without relying on MySQL's default collation behaviour.
        // Two distinct placeholders are used because a native prepared
        // statement cannot reuse one named marker twice.
        $where[] = '(LOWER(p.name) LIKE :term_name OR LOWER(p.sku) LIKE :term_sku)';
        $params['term_name'] = '%' . strtolower($term) . '%';
        $params['term_sku']  = '%' . strtolower($term) . '%';
    }

    $categoryId = (int) ($filters['category'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'p.category_id = :category';
        $params['category'] = $categoryId;
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status === 'low') {
        $where[] = 'p.quantity > 0 AND p.quantity <= p.reorder_level';
    } elseif ($status === 'out') {
        $where[] = 'p.quantity = 0';
    } elseif ($status === 'expiring') {
        $where[] = "p.expiry_date IS NOT NULL AND p.expiry_date >= :today AND p.expiry_date <= :horizon";
        $params['today']   = date('Y-m-d');
        $params['horizon'] = date('Y-m-d', strtotime('+30 days'));
    } elseif ($status === 'expired') {
        $where[] = 'p.expiry_date IS NOT NULL AND p.expiry_date < :today';
        $params['today'] = date('Y-m-d');
    }

    $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $total = (int) q_val(
        'SELECT COUNT(*) FROM products p' . $clause,
        $params,
        0
    );

    $pages  = max(1, (int) ceil($total / PER_PAGE));
    $page   = max(1, min($page, $pages));
    $offset = ($page - 1) * PER_PAGE;

    $rows = q_all(
        'SELECT p.*, c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id'
        . $clause .
        ' ORDER BY p.name
           LIMIT ' . PER_PAGE . ' OFFSET ' . $offset,
        $params
    );

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function product_find(int $id): ?array
{
    return q_one(
        'SELECT p.*, c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.id = :id',
        ['id' => $id]
    );
}

function sku_taken(string $sku, ?int $exceptId = null): bool
{
    if ($exceptId === null) {
        $count = q_val('SELECT COUNT(*) FROM products WHERE LOWER(sku) = :sku', ['sku' => strtolower($sku)], 0);
    } else {
        $count = q_val(
            'SELECT COUNT(*) FROM products WHERE LOWER(sku) = :sku AND id <> :id',
            ['sku' => strtolower($sku), 'id' => $exceptId],
            0
        );
    }
    return (int) $count > 0;
}

/**
 * Validate submitted product data.
 *
 * Returns [errors, cleanValues]. The non-negative price and quantity rules are
 * carried over directly from the console prototype.
 */
function product_validate(array $input, ?int $exceptId = null): array
{
    $errors = [];

    $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
    if ($sku === '') {
        $errors['sku'] = 'A product code is required.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9\-_]{2,31}$/', $sku)) {
        $errors['sku'] = 'Use 3-32 characters: letters, digits, hyphen or underscore.';
    } elseif (sku_taken($sku, $exceptId)) {
        $errors['sku'] = 'That product code is already in use.';
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'A product name is required.';
    } elseif (mb_strlen($name) > 120) {
        $errors['name'] = 'The product name must be 120 characters or fewer.';
    }

    $categoryId = (int) ($input['category_id'] ?? 0);
    if ($categoryId > 0 && !category_exists($categoryId)) {
        $errors['category_id'] = 'Please choose a valid category.';
    }

    $type = (string) ($input['type'] ?? 'standard');
    if (!array_key_exists($type, PRODUCT_TYPES)) {
        $errors['type'] = 'Please choose a valid product type.';
    }

    $priceRaw = trim((string) ($input['price'] ?? ''));
    if ($priceRaw === '' || !is_numeric($priceRaw)) {
        $errors['price'] = 'Enter the unit price as a number.';
        $price = 0.0;
    } else {
        $price = round((float) $priceRaw, 2);
        if ($price < 0) {
            $errors['price'] = 'Price must be non-negative.';
        } elseif ($price > 99999999) {
            $errors['price'] = 'That price is too large.';
        }
    }

    $quantityRaw = trim((string) ($input['quantity'] ?? ''));
    if ($quantityRaw === '' || !preg_match('/^-?\d+$/', $quantityRaw)) {
        $errors['quantity'] = 'Enter the quantity as a whole number.';
        $quantity = 0;
    } else {
        $quantity = (int) $quantityRaw;
        if ($quantity < 0) {
            $errors['quantity'] = 'Quantity must be non-negative.';
        }
    }

    $reorderRaw = trim((string) ($input['reorder_level'] ?? '0'));
    if ($reorderRaw === '' || !preg_match('/^-?\d+$/', $reorderRaw)) {
        $errors['reorder_level'] = 'Enter the reorder level as a whole number.';
        $reorder = 0;
    } else {
        $reorder = (int) $reorderRaw;
        if ($reorder < 0) {
            $errors['reorder_level'] = 'Reorder level must be non-negative.';
        }
    }

    $expiry = trim((string) ($input['expiry_date'] ?? ''));
    if ($type === 'perishable') {
        if ($expiry === '') {
            $errors['expiry_date'] = 'A perishable product needs an expiry date.';
        } else {
            $parsed = DateTime::createFromFormat('Y-m-d', $expiry);
            if ($parsed === false || $parsed->format('Y-m-d') !== $expiry) {
                $errors['expiry_date'] = 'Enter the expiry date as YYYY-MM-DD.';
            }
        }
    } else {
        $expiry = '';
    }

    $clean = [
        'sku'           => $sku,
        'name'          => $name,
        'category_id'   => $categoryId > 0 ? $categoryId : null,
        'type'          => array_key_exists($type, PRODUCT_TYPES) ? $type : 'standard',
        'price'         => $price,
        'quantity'      => $quantity,
        'reorder_level' => $reorder,
        'expiry_date'   => $expiry !== '' ? $expiry : null,
    ];

    return [$errors, $clean];
}

function product_create(array $data): int
{
    $now = db_now();

    q(
        'INSERT INTO products
            (sku, name, category_id, type, price, quantity, reorder_level, expiry_date, created_at, updated_at)
         VALUES
            (:sku, :name, :category_id, :type, :price, :quantity, :reorder_level, :expiry_date, :created_at, :updated_at)',
        array_merge($data, ['created_at' => $now, 'updated_at' => $now])
    );

    return db_insert_id('products');
}

function product_update(int $id, array $data): void
{
    q(
        'UPDATE products SET
            sku = :sku,
            name = :name,
            category_id = :category_id,
            type = :type,
            price = :price,
            quantity = :quantity,
            reorder_level = :reorder_level,
            expiry_date = :expiry_date,
            updated_at = :updated_at
          WHERE id = :id',
        array_merge($data, ['updated_at' => db_now(), 'id' => $id])
    );
}

function product_delete(int $id): void
{
    q('DELETE FROM products WHERE id = :id', ['id' => $id]);
}

/** Quick stock adjustment from the product list. */
function product_adjust_quantity(int $id, int $delta): ?array
{
    $product = product_find($id);
    if ($product === null) {
        return null;
    }

    $newQuantity = max(0, (int) $product['quantity'] + $delta);
    q(
        'UPDATE products SET quantity = :q, updated_at = :u WHERE id = :id',
        ['q' => $newQuantity, 'u' => db_now(), 'id' => $id]
    );

    $product['quantity'] = $newQuantity;
    return $product;
}

// --- Reporting --------------------------------------------------------------

function inventory_stats(): array
{
    $today   = date('Y-m-d');
    $horizon = date('Y-m-d', strtotime('+30 days'));

    return [
        'products'   => (int) q_val('SELECT COUNT(*) FROM products', [], 0),
        'units'      => (int) q_val('SELECT COALESCE(SUM(quantity), 0) FROM products', [], 0),
        'value'      => (float) q_val('SELECT COALESCE(SUM(price * quantity), 0) FROM products', [], 0),
        'low_stock'  => (int) q_val('SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity <= reorder_level', [], 0),
        'out_stock'  => (int) q_val('SELECT COUNT(*) FROM products WHERE quantity = 0', [], 0),
        'expiring'   => (int) q_val(
            'SELECT COUNT(*) FROM products WHERE expiry_date IS NOT NULL AND expiry_date >= :t AND expiry_date <= :h',
            ['t' => $today, 'h' => $horizon],
            0
        ),
        'expired'    => (int) q_val(
            'SELECT COUNT(*) FROM products WHERE expiry_date IS NOT NULL AND expiry_date < :t',
            ['t' => $today],
            0
        ),
        'categories' => (int) q_val('SELECT COUNT(*) FROM categories', [], 0),
    ];
}

function value_by_category(): array
{
    return q_all(
        "SELECT COALESCE(c.name, 'Uncategorised') AS name,
                COUNT(p.id) AS items,
                COALESCE(SUM(p.price * p.quantity), 0) AS value
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          GROUP BY c.name
          ORDER BY value DESC"
    );
}

function attention_list(int $limit = 8): array
{
    $today   = date('Y-m-d');
    $horizon = date('Y-m-d', strtotime('+30 days'));

    return q_all(
        'SELECT p.*, c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.quantity <= p.reorder_level
             OR (p.expiry_date IS NOT NULL AND p.expiry_date <= :h)
          ORDER BY p.quantity, p.expiry_date
          LIMIT ' . (int) $limit,
        ['h' => $horizon]
    );
}

function products_all_for_export(): array
{
    return q_all(
        "SELECT p.sku, p.name, COALESCE(c.name, '') AS category, p.type,
                p.price, p.quantity, p.reorder_level, p.expiry_date, p.updated_at
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          ORDER BY p.sku"
    );
}

// --- Audit trail ------------------------------------------------------------

function logs_page(int $page = 1, int $perPage = 25): array
{
    $total  = (int) q_val('SELECT COUNT(*) FROM activity_log', [], 0);
    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $rows = q_all(
        'SELECT * FROM activity_log ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset
    );

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function logs_recent(int $limit = 6): array
{
    return q_all('SELECT * FROM activity_log ORDER BY id DESC LIMIT ' . (int) $limit);
}
