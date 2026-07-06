<?php
/**
 * Global Helper Functions
 * AI Interview Assessment Platform
 */

declare(strict_types=1);

/**
 * Sanitise output to prevent XSS.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL and terminate.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Flash a one-time message into the session.
 */
function flash(string $key, string $message, string $type = 'info'): void
{
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

/**
 * Retrieve and clear a flash message.
 * Returns null if no message exists for the key.
 *
 * @return array{message: string, type: string}|null
 */
function getFlash(string $key): ?array
{
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * Verify a CSRF token submitted with a POST request.
 * Terminates the request with 403 on failure.
 */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return BASE_URL-relative asset path.
 */
function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Validate email format.
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format a datetime string for display.
 */
function formatDate(string|null $datetime, string $format = 'M j, Y'): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($datetime))->format($format);
    } catch (Exception) {
        return '—';
    }
}

/**
 * Human-readable "time ago" from a datetime string.
 */
function timeAgo(string|null $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return 'Never';
    }
    try {
        $now  = new DateTimeImmutable();
        $then = new DateTimeImmutable($datetime);
        $diff = $now->diff($then);

        if ($diff->y > 0)  return $diff->y  . 'y ago';
        if ($diff->m > 0)  return $diff->m  . 'mo ago';
        if ($diff->d > 1)  return $diff->d  . 'd ago';
        if ($diff->d === 1) return 'Yesterday';
        if ($diff->h > 0)  return $diff->h  . 'h ago';
        if ($diff->i > 0)  return $diff->i  . 'm ago';
        return 'Just now';
    } catch (Exception) {
        return '—';
    }
}

/**
 * Clamp an integer to a valid pagination page number.
 */
function pageNum(mixed $raw, int $maxPage = PHP_INT_MAX): int
{
    $n = (int) ($raw ?? 1);
    return max(1, min($n, $maxPage));
}

/**
 * Return initials (up to 2 chars) from a full name.
 */
function initials(string $name): string
{
    $words = array_filter(explode(' ', trim($name)));
    return strtoupper(
        implode('', array_map(fn($w) => $w[0], array_slice($words, 0, 2)))
    );
}
