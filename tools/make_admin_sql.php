<?php
declare(strict_types=1);

/**
 * Generates the SQL statement that creates an application login account.
 *
 * Why this exists: a user row needs a bcrypt password hash, and a hash cannot
 * be written by hand. Committing a ready-made one to the repository would mean
 * publishing a password, so instead this prints a statement you paste into
 * phpMyAdmin's SQL tab, built from a password only you know.
 *
 * Usage:
 *   C:\xampp\php\php.exe tools\make_admin_sql.php <username> <password> [full name] [role]
 *
 * Example:
 *   C:\xampp\php\php.exe tools\make_admin_sql.php admin "MyStrongPassword" "System Administrator" admin
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';
$fullName = $argv[3] ?? 'System Administrator';
$role     = $argv[4] ?? 'admin';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php tools/make_admin_sql.php <username> <password> [full name] [role]\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "The password must be at least 8 characters long.\n");
    exit(1);
}

if (!in_array($role, ['admin', 'staff'], true)) {
    fwrite(STDERR, "Role must be either 'admin' or 'staff'.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Single quotes are the only character that could break out of the literals
// below, and bcrypt hashes never contain them, but escape defensively anyway.
$q = static fn (string $v): string => "'" . str_replace("'", "''", $v) . "'";

echo "-- -------------------------------------------------------------------\n";
echo "-- Paste this into phpMyAdmin:  inventory_db  ->  SQL tab  ->  Go\n";
echo "--\n";
echo "-- The password itself is NOT in this statement, only its bcrypt hash.\n";
echo "-- Running it twice updates the existing account rather than failing.\n";
echo "-- -------------------------------------------------------------------\n\n";

printf(
    "INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `created_at`)\n" .
    "VALUES (%s, %s, %s, %s, NOW())\n" .
    "ON DUPLICATE KEY UPDATE\n" .
    "    `password_hash` = VALUES(`password_hash`),\n" .
    "    `full_name`     = VALUES(`full_name`),\n" .
    "    `role`          = VALUES(`role`);\n\n",
    $q($username),
    $q($hash),
    $q($fullName),
    $q($role)
);

echo "-- Then sign in at http://localhost/inventory/ as: " . $username . "\n";
echo "-- Keep the password out of your report and out of every screenshot.\n";
