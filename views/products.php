<?php
/** @var array $result */
/** @var array $filters */
/** @var array $categories */

$rows = $result['rows'];

/** Preserve the current filters when building pagination links. */
$carry = array_filter([
    'q'        => $filters['q'],
    'category' => $filters['category'] > 0 ? $filters['category'] : null,
    'status'   => $filters['status'],
]);
?>

<section class="card">
    <div class="card-head">
        <h2><?= e(number_format($result['total'])) ?> product<?= $result['total'] === 1 ? '' : 's' ?></h2>
        <div class="card-actions">
            <a class="btn" href="<?= e(url('export')) ?>">Export CSV</a>
            <a class="btn btn-primary" href="<?= e(url('product_new')) ?>">Add product</a>
        </div>
    </div>

    <form class="filters" method="get" action="index.php">
        <input type="hidden" name="page" value="products">

        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search by name or product code">

        <select name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e((string) $category['id']) ?>" <?= $filters['category'] === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <?php
            $statuses = [
                ''         => 'Any status',
                'low'      => 'Low stock',
                'out'      => 'Out of stock',
                'expiring' => 'Expiring within 30 days',
                'expired'  => 'Expired',
            ];
            foreach ($statuses as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Filter</button>
        <?php if ($carry !== []): ?>
            <a class="btn btn-ghost" href="<?= e(url('products')) ?>">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <p class="empty">No products matched your search.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th class="num">Price</th>
                    <th class="num">Qty</th>
                    <th>Status</th>
                    <th>Expiry</th>
                    <th class="right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $product):
                    [$stockClass, $stockLabel] = stock_status($product);
                    [$expClass, $expLabel]     = expiry_status($product['expiry_date']);
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('product_edit', ['id' => $product['id']])) ?>"><?= e($product['name']) ?></a>
                            <span class="sku"><?= e($product['sku']) ?></span>
                        </td>
                        <td><?= e($product['category_name'] ?? '-') ?></td>
                        <td><span class="pill"><?= e(PRODUCT_TYPES[$product['type']] ?? $product['type']) ?></span></td>
                        <td class="num"><?= e(money($product['price'])) ?></td>
                        <td class="num"><?= e((string) $product['quantity']) ?></td>
                        <td><span class="badge badge-<?= e($stockClass) ?>"><?= e($stockLabel) ?></span></td>
                        <td>
                            <?php if ($expClass === 'none'): ?>
                                <span class="muted">-</span>
                            <?php else: ?>
                                <span class="badge badge-<?= e($expClass) ?>"><?= e($expLabel) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="right nowrap">
                            <form class="inline" method="post" action="<?= e(url('product_adjust', $carry)) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                <input type="hidden" name="delta" value="-1">
                                <button class="btn btn-icon" type="submit" title="Remove one unit">&minus;</button>
                            </form>
                            <form class="inline" method="post" action="<?= e(url('product_adjust', $carry)) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                <input type="hidden" name="delta" value="1">
                                <button class="btn btn-icon" type="submit" title="Add one unit">+</button>
                            </form>
                            <a class="btn btn-small" href="<?= e(url('product_edit', ['id' => $product['id']])) ?>">Edit</a>
                            <form class="inline" method="post" action="<?= e(url('product_delete')) ?>"
                                  data-confirm="Delete &quot;<?= e($product['name']) ?>&quot;? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                <button class="btn btn-small btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pager">
                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <a class="<?= $i === $result['page'] ? 'current' : '' ?>"
                       href="<?= e(url('products', array_merge($carry, ['p' => $i]))) ?>"><?= e((string) $i) ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
