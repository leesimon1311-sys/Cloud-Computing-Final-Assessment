<?php
/** @var ?string $error */
/** @var ?string $success */
$user = current_user();
?>

<div class="two-col">
    <section class="card narrow">
        <div class="card-head"><h2>Change password</h2></div>

        <?php if ($error !== null): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success !== null): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('account')) ?>" class="form" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" required>
            </div>

            <div class="field">
                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" required minlength="8">
                <p class="hint">Minimum 8 characters. Stored only as a bcrypt hash, never as plain text.</p>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" required minlength="8">
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Update password</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><h2>Session and environment</h2></div>

        <table class="table kv">
            <tbody>
            <tr><th>Signed in as</th><td><?= e($user['full_name']) ?> (<?= e($user['username']) ?>)</td></tr>
            <tr><th>Role</th><td><?= e(ucfirst((string) $user['role'])) ?></td></tr>
            <tr><th>Environment</th><td><?= e(env('APP_ENV', 'local') === 'production' ? 'Cloud deployment' : 'Local deployment') ?></td></tr>
            <tr><th>Database engine</th><td><?= e(db_server_version()) ?></td></tr>
            <tr><th>PHP version</th><td><?= e(PHP_VERSION) ?></td></tr>
            <tr><th>Web server</th><td><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') ?></td></tr>
            <tr><th>Server host</th><td><?= e($_SERVER['HTTP_HOST'] ?? 'unknown') ?></td></tr>
            <tr><th>Connection</th><td><?= e((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'HTTPS (encrypted)' : 'HTTP (plain text)') ?></td></tr>
            <tr><th>Your IP address</th><td><?= e($_SERVER['REMOTE_ADDR'] ?? 'unknown') ?></td></tr>
            <tr><th>Server time</th><td><?= e(date('d M Y H:i:s')) ?> (<?= e(date_default_timezone_get()) ?>)</td></tr>
            </tbody>
        </table>

        <p class="muted small">
            This panel is useful evidence for the report: take the screenshot once on
            <code>localhost</code> and once on the public cloud URL to show the two
            environments side by side.
        </p>
    </section>
</div>
