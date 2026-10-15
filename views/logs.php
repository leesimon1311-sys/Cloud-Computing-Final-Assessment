<?php
/** @var array $result */
$rows = $result['rows'];
?>

<section class="card">
    <div class="card-head">
        <h2>Activity log</h2>
        <span class="muted small"><?= e(number_format($result['total'])) ?> entries</span>
    </div>

    <p class="muted">
        Every sign-in attempt and stock change is recorded here. In the local deployment this
        table is the only audit trail available; the cloud deployment adds the platform's own
        request logs and resource metrics on top of it.
    </p>

    <?php if ($rows === []): ?>
        <p class="empty">No activity has been recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>Source IP</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $log): ?>
                    <tr>
                        <td class="nowrap muted small"><?= e(date('d M Y H:i:s', strtotime((string) $log['created_at']))) ?></td>
                        <td><?= e($log['username']) ?></td>
                        <td>
                            <code class="<?= $log['action'] === 'login_failed' ? 'code-warn' : '' ?>"><?= e($log['action']) ?></code>
                        </td>
                        <td class="muted"><?= e($log['entity'] ?? '-') ?></td>
                        <td class="muted"><?= e($log['details'] ?? '') ?></td>
                        <td class="muted small"><?= e($log['ip_address'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pager">
                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <a class="<?= $i === $result['page'] ? 'current' : '' ?>"
                       href="<?= e(url('logs', ['p' => $i])) ?>"><?= e((string) $i) ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
