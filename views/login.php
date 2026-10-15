<?php
/** @var ?string $error */
/** @var string $username */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in - <?= e(app_name()) ?></title>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>&#128230;</text></svg>">
</head>
<body class="login-body">
<div class="login-card">
    <div class="login-head">
        <span class="brand-mark big">IMS</span>
        <h1><?= e(app_name()) ?></h1>
        <p class="muted">Sign in to manage stock, suppliers and reorder levels.</p>
    </div>

    <?php foreach (flash_take() as $message): ?>
        <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login')) ?>" autocomplete="off">
        <?= csrf_field() ?>

        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>

    <p class="login-foot muted small">
        Running on the <strong><?= env('APP_ENV', 'local') === 'production' ? 'cloud' : 'local' ?></strong>
        infrastructure environment.
    </p>
</div>
</body>
</html>
