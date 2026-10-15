<?php
declare(strict_types=1);

/**
 * Authentication, session hardening and the activity audit trail.
 *
 * Passwords are never stored in plain text: password_hash() applies a bcrypt
 * hash with a per-user salt, and password_verify() performs the comparison in
 * constant time.
 */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 120;

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // cookie is HTTPS-only once deployed to the cloud
        'httponly' => true,     // JavaScript cannot read the session cookie
        'samesite' => 'Lax',    // mitigates cross-site request forgery
    ]);

    session_name('IMS_SESSION');
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please sign in to continue.');
        redirect('login');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('error', 'That action requires an administrator account.');
        redirect('dashboard');
    }
}

/** Seconds remaining on the brute-force lockout, or 0 when not locked. */
function login_lockout_remaining(): int
{
    $until = (int) ($_SESSION['login_locked_until'] ?? 0);
    $remaining = $until - time();
    return $remaining > 0 ? $remaining : 0;
}

function attempt_login(string $username, string $password): bool
{
    if (login_lockout_remaining() > 0) {
        return false;
    }

    $user = q_one(
        'SELECT id, username, password_hash, full_name, role FROM users WHERE username = :u',
        ['u' => $username]
    );

    if ($user === null || !password_verify($password, $user['password_hash'])) {
        $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;

        if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
            $_SESSION['login_attempts'] = 0;
        }

        log_activity('login_failed', 'user', null, 'Username: ' . $username, $username);
        return false;
    }

    // Defeats session fixation: the visitor gets a brand new session id the
    // moment their privilege level changes.
    session_regenerate_id(true);

    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];

    log_activity('login', 'user', (int) $user['id'], 'Successful sign in');
    return true;
}

function logout(): void
{
    if (is_logged_in()) {
        log_activity('logout', 'user', (int) current_user()['id'], 'Signed out');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function change_password(int $userId, string $current, string $new): string|true
{
    $user = q_one('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);
    if ($user === null || !password_verify($current, $user['password_hash'])) {
        return 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        return 'The new password must be at least 8 characters long.';
    }

    q(
        'UPDATE users SET password_hash = :h WHERE id = :id',
        ['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]
    );

    log_activity('password_change', 'user', $userId, 'Password updated');
    return true;
}

/**
 * Append a row to the audit trail. Used as monitoring evidence for the local
 * deployment, where there is no provider-supplied dashboard.
 */
function log_activity(
    string $action,
    ?string $entity = null,
    ?int $entityId = null,
    ?string $details = null,
    ?string $usernameOverride = null
): void {
    try {
        $user = current_user();

        q(
            'INSERT INTO activity_log (user_id, username, action, entity, entity_id, details, ip_address, created_at)
             VALUES (:user_id, :username, :action, :entity, :entity_id, :details, :ip, :created_at)',
            [
                'user_id'    => $user['id'] ?? null,
                'username'   => $usernameOverride ?? ($user['username'] ?? 'guest'),
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'details'    => $details !== null ? mb_substr($details, 0, 255) : null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => db_now(),
            ]
        );
    } catch (Throwable $e) {
        // Logging must never break the request it is recording.
        error_log('activity_log failure: ' . $e->getMessage());
    }
}
