<?php
/** @var ?string $detail */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something went wrong</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1>Something went wrong</h1>
    <p class="muted">
        The request could not be completed. The technical details have been written to the
        server log rather than shown here.
    </p>

    <?php if (!empty($detail)): ?>
        <pre class="trace"><?= e($detail) ?></pre>
        <p class="muted small">Details are visible because <code>APP_ENV</code> is not set to <code>production</code>.</p>
    <?php endif; ?>

    <p><a class="btn btn-primary" href="index.php">Return to the application</a></p>
</div>
</body>
</html>
