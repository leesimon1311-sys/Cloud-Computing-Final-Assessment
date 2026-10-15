<?php
declare(strict_types=1);

/**
 * Minimal .env loader.
 *
 * Deliberately dependency free: the container image does not need Composer,
 * which keeps the cloud build fast and the repository small.
 */

function env_load(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    $first = true;

    foreach ($lines as $line) {
        // Editors on Windows often save UTF-8 with a byte order mark, which
        // would otherwise become part of the first key's name.
        if ($first) {
            $line  = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            $first = false;
        }

        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Strip one layer of matching quotes.
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[-1] === $val[0]) {
            $val = substr($val, 1, -1);
        }

        // Real environment variables always win, so the cloud platform's
        // injected settings are never overwritten by a stray .env file.
        if (getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}
