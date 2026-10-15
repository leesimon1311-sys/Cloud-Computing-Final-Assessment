<?php
/** @var string $content */
/** @var string $pageTitle */
$user = current_user();
$active = $_GET['page'] ?? 'dashboard';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ? $pageTitle . ' - ' . app_name() : app_name()) ?></title>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>&#128230;</text></svg>">
</head>
<body>
<div class="shell">

    <aside class="sidebar">
        <div class="brand">
            <span class="brand-mark">IMS</span>
            <span class="brand-text"><?= e(app_name()) ?></span>
        </div>

        <nav>
            <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>">Dashboard</a>
            <a class="<?= str_starts_with((string) $active, 'product') ? 'active' : '' ?>" href="<?= e(url('products')) ?>">Products</a>
            <a class="<?= $active === 'logs' ? 'active' : '' ?>" href="<?= e(url('logs')) ?>">Activity log</a>
            <a class="<?= $active === 'account' ? 'active' : '' ?>" href="<?= e(url('account')) ?>">My account</a>
        </nav>

        <div class="sidebar-foot">
            <div class="env-badge <?= env('APP_ENV', 'local') === 'production' ? 'env-cloud' : 'env-local' ?>">
                <?= env('APP_ENV', 'local') === 'production' ? 'Cloud deployment' : 'Local deployment' ?>
            </div>
            <p class="muted small"><?= e(db_server_version()) ?></p>
            <p class="muted small">PHP <?= e(PHP_VERSION) ?></p>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1><?= e($pageTitle ?: 'Dashboard') ?></h1>
            <div class="topbar-right">
                <span class="who">
                    <strong><?= e($user['full_name'] ?? '') ?></strong>
                    <span class="role"><?= e(ucfirst((string) ($user['role'] ?? ''))) ?></span>
                </span>
                <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Sign out</a>
            </div>
        </header>

        <main class="content">
            <?php foreach (flash_take() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>

            <?= $content ?>
        </main>

        <footer class="footer">
            <span><?= e(app_name()) ?> &middot; BIT3253 Cloud Computing Final Assessment</span>
            <span>Served <?= e(date('d M Y, H:i:s')) ?> (<?= e(date_default_timezone_get()) ?>)</span>
        </footer>
    </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>
