<?php
declare(strict_types=1);

/**
 * Command line password reset.
 *
 * Usage:
 *   C:\xampp\php\php.exe tools\set_password.php <username> <new-password>
 *
 * Run this if you lose the administrator password that the installer printed.
 * It only works from a terminal, never over HTTP.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/../src/env.php';
env_load(dirname(__DIR__) . '/.env');
require __DIR__ . '/../src/db.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php tools/set_password.php <username> <new-password>\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "The password must be at least 8 characters long.\n");
    exit(1);
}

$user = q_one('SELECT id, full_name FROM users WHERE username = :u', ['u' => $username]);

if ($user === null) {
    fwrite(STDERR, "No account found with the username \"{$username}\".\n");
    exit(1);
}

q(
    'UPDATE users SET password_hash = :h WHERE id = :id',
    ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]
);

echo "Password updated for {$user['full_name']} ({$username}).\n";
echo "Sign in and change it again from the My account page if anyone else saw this terminal.\n";
