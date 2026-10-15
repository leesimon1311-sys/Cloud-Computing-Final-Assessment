<?php
/** @var array $stats */
/** @var array $attention */
/** @var array $categories */
/** @var array $recentLogs */
?>

<section class="stat-grid">
    <div class="stat">
        <span class="stat-label">Products</span>
        <span class="stat-value"><?= e((string) $stats['products']) ?></span>
        <span class="stat-note"><?= e((string) $stats['categories']) ?> categories</span>
    </div>
    <div class="stat">
        <span class="stat-label">Units in stock</span>
        <span class="stat-value"><?= e(number_format($stats['units'])) ?></span>
        <span class="stat-note">across all products</span>
    </div>
    <div class="stat">
        <span class="stat-label">Stock value</span>
        <span class="stat-value"><?= e(money($stats['value'])) ?></span>
        <span class="stat-note">price &times; quantity</span>
    </div>
    <div class="stat <?= $stats['low_stock'] + $stats['out_stock'] > 0 ? 'stat-warn' : '' ?>">
        <span class="stat-label">Needs reordering</span>
        <span class="stat-value"><?= e((string) ($stats['low_stock'] + $stats['out_stock'])) ?></span>
        <span class="stat-note"><?= e((string) $stats['out_stock']) ?> out of stock</span>
    </div>
    <div class="stat <?= $stats['expiring'] > 0 ? 'stat-warn' : '' ?>">
        <span class="stat-label">Expiring soon</span>
        <span class="stat-value"><?= e((string) $stats['expiring']) ?></span>
        <span class="stat-note">within 30 days</span>
    </div>
    <div class="stat <?= $stats['expired'] > 0 ? 'stat-danger' : '' ?>">
        <span class="stat-label">Expired</span>
        <span class="stat-value"><?= e((string) $stats['expired']) ?></span>
        <span class="stat-note">remove from shelf</span>
    </div>
</section>

<div class="two-col">

    <section class="card">
        <div class="card-head">
            <h2>Items needing attention</h2>
            <a class="btn btn-small" href="<?= e(url('products', ['status' => 'low'])) ?>">View all</a>
        </div>

        <?php if ($attention === []): ?>
            <p class="empty">Nothing needs attention. Stock levels and expiry dates are healthy.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr><th>Product</th><th>Qty</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($attention as $item):
                    [$stockClass, $stockLabel] = stock_status($item);
                    [$expClass, $expLabel]     = expiry_status($item['expiry_date']);
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('product_edit', ['id' => $item['id']])) ?>"><?= e($item['name']) ?></a>
                            <span class="sku"><?= e($item['sku']) ?></span>
                        </td>
                        <td class="num"><?= e((string) $item['quantity']) ?></td>
                        <td>
                            <span class="badge badge-<?= e($stockClass) ?>"><?= e($stockLabel) ?></span>
                            <?php if (in_array($expClass, ['expired', 'soon'], true)): ?>
                                <span class="badge badge-<?= e($expClass) ?>"><?= e($expLabel) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><h2>Stock value by category</h2></div>

        <?php
        $maxValue = 0.0;
        foreach ($categories as $row) {
            $maxValue = max($maxValue, (float) $row['value']);
        }
        ?>

        <?php if ($categories === []): ?>
            <p class="empty">No products have been recorded yet.</p>
        <?php else: ?>
            <ul class="bars">
                <?php foreach ($categories as $row):
                    $value = (float) $row['value'];
                    $pct   = $maxValue > 0 ? round($value / $maxValue * 100) : 0;
                    ?>
                    <li>
                        <div class="bar-head">
                            <span><?= e($row['name']) ?> <span class="muted small">(<?= e((string) $row['items']) ?>)</span></span>
                            <strong><?= e(money($value)) ?></strong>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= e((string) $pct) ?>%"></div></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <h2>Recent activity</h2>
        <a class="btn btn-small" href="<?= e(url('logs')) ?>">Full log</a>
    </div>

    <?php if ($recentLogs === []): ?>
        <p class="empty">No activity recorded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td class="muted small"><?= e(date('d M H:i', strtotime((string) $log['created_at']))) ?></td>
                    <td><?= e($log['username']) ?></td>
                    <td><code><?= e($log['action']) ?></code></td>
                    <td class="muted"><?= e($log['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
