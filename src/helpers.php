<?php
declare(strict_types=1);

/** Escape a value for safe output in HTML. Guards against stored XSS. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_name(): string
{
    return env('APP_NAME', 'Inventory Management System');
}

function currency(): string
{
    return env('APP_CURRENCY', 'RM');
}

function money($amount): string
{
    return currency() . ' ' . number_format((float) $amount, 2);
}

/** Build a URL back into the front controller. */
function url(string $page = 'dashboard', array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

// --- CSRF protection --------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort the request when a POST arrives without a valid token. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        error_log('CSRF token mismatch on ' . ($_SERVER['REQUEST_URI'] ?? '?')
            . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Security token expired or invalid.\n\nPlease go back, reload the page and try again.\n");
    }
}

// --- Flash messages ---------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// --- Input helpers ----------------------------------------------------------

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

/** Render a view inside the shared layout. */
function render(string $view, array $data = [], string $title = ''): void
{
    extract($data, EXTR_SKIP);
    $pageTitle = $title;

    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();

    require APP_ROOT . '/views/layout.php';
}

/** Render a view with no chrome (used by the login screen). */
function render_bare(string $view, array $data = [], string $title = ''): void
{
    extract($data, EXTR_SKIP);
    $pageTitle = $title;
    require APP_ROOT . '/views/' . $view . '.php';
}

/** Classify a product row for the status badge shown in the tables. */
function stock_status(array $product): array
{
    $quantity = (int) $product['quantity'];
    $reorder  = (int) $product['reorder_level'];

    if ($quantity === 0) {
        return ['out', 'Out of stock'];
    }
    if ($quantity <= $reorder) {
        return ['low', 'Low stock'];
    }
    return ['ok', 'In stock'];
}

/** Expiry classification for perishable products. */
function expiry_status(?string $expiryDate): array
{
    if ($expiryDate === null || $expiryDate === '') {
        return ['none', ''];
    }

    $expiry = strtotime($expiryDate);
    $today  = strtotime(date('Y-m-d'));

    if ($expiry === false) {
        return ['none', ''];
    }
    if ($expiry < $today) {
        return ['expired', 'Expired'];
    }

    $days = (int) floor(($expiry - $today) / 86400);
    if ($days <= 30) {
        return ['soon', 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's')];
    }

    return ['ok', date('d M Y', $expiry)];
}
