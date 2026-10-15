<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database not initialised</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-card wide">
    <h1>Database not initialised</h1>
    <p class="muted">
        The application connected to its configuration but the inventory tables do not exist yet.
    </p>

    <h2 class="h3">Local deployment (XAMPP)</h2>
    <p class="muted small">
        Run <code>tools\setup_local.ps1</code> from PowerShell, or import
        <code>sql/schema_mysql.sql</code> through phpMyAdmin and then open
        <code>install.php</code> with your installer token.
    </p>

    <h2 class="h3">Cloud deployment</h2>
    <p class="muted small">
        Visit <code>/install.php?token=YOUR_INSTALL_TOKEN</code> once, using the
        <code>INSTALL_TOKEN</code> value stored in the platform's environment variable
        settings. The installer is skipped automatically if that variable is empty.
    </p>
</div>
</body>
</html>
