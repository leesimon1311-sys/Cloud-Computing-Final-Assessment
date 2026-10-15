<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Database access layer.
 *
 * The same code runs against two engines:
 *   - MySQL/MariaDB  : local XAMPP deployment
 *   - PostgreSQL     : managed instance on the cloud platform
 *
 * The cloud platform injects a single DATABASE_URL connection string, so that
 * takes priority. When it is absent the discrete DB_* values from .env are
 * used instead. Every query in the application goes through the prepared
 * statement helpers below, which is what protects the system against SQL
 * injection.
 */

function db_config(): array
{
    $url = env('DATABASE_URL');

    if ($url !== null) {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('DATABASE_URL is not a valid connection string.');
        }

        $scheme = strtolower($parts['scheme'] ?? 'pgsql');
        $driver = in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true) ? 'pgsql' : 'mysql';

        $sslmode = null;
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $sslmode = $query['sslmode'] ?? null;
        }

        return [
            'driver'  => $driver,
            'host'    => $parts['host'],
            'port'    => (int) ($parts['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)),
            'name'    => ltrim($parts['path'] ?? '', '/'),
            'user'    => isset($parts['user']) ? urldecode($parts['user']) : '',
            'pass'    => isset($parts['pass']) ? urldecode($parts['pass']) : '',
            'sslmode' => $sslmode,
        ];
    }

    $driver = env('DB_DRIVER', 'mysql') === 'pgsql' ? 'pgsql' : 'mysql';

    return [
        'driver'  => $driver,
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => (int) env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
        'name'    => env('DB_NAME', 'inventory_db'),
        'user'    => env('DB_USER', 'inventory_app'),
        'pass'    => env('DB_PASS', ''),
        'sslmode' => env('DB_SSLMODE'),
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = db_config();

    if ($c['driver'] === 'pgsql') {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $c['host'], $c['port'], $c['name']);
        if (!empty($c['sslmode'])) {
            $dsn .= ';sslmode=' . $c['sslmode'];
        }
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'],
            $c['port'],
            $c['name']
        );
    }

    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Real prepared statements rather than client-side emulation.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function db_driver(): string
{
    return (string) db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** Human readable engine name and version, shown on the dashboard. */
function db_server_version(): string
{
    try {
        $engine = db_driver() === 'pgsql' ? 'PostgreSQL' : 'MySQL / MariaDB';
        return $engine . ' ' . (string) db()->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (Throwable $e) {
        return 'unknown';
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch the first column of the first row. */
function q_val(string $sql, array $params = [], $default = null)
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

/**
 * PostgreSQL needs the sequence name to report the generated id, MySQL does
 * not. This hides that difference from the rest of the application.
 */
function db_insert_id(string $table): int
{
    if (db_driver() === 'pgsql') {
        return (int) db()->lastInsertId($table . '_id_seq');
    }
    return (int) db()->lastInsertId();
}

/** Timestamp string in the format both engines accept as a bound parameter. */
function db_now(): string
{
    return date('Y-m-d H:i:s');
}

/** True when the core tables are present. */
function db_is_installed(): bool
{
    try {
        q_val('SELECT COUNT(*) FROM products');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
