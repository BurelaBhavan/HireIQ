<?php
/**
 * Application Configuration
 * AI Interview Assessment Platform
 */

declare(strict_types=1);

// ── Environment ───────────────────────────────────────────────
define('APP_ENV',     'development');   // 'production' in live
define('APP_NAME',    'HireIQ');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/ai_interview_platform');

// ── Session settings (call before session_start()) ───────────
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

// ── Error display (disable in production) ────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ── .env loader (Phase 4+) ───────────────────────────────────
// Reads key=value pairs from /.env and sets them as PHP constants.
// Never commit .env to version control.
(static function (): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_readable($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if ($key !== '' && !defined($key)) {
            define($key, $val);
        }
    }
})();

// Fallback constants so code doesn't error if .env is missing
if (!defined('GROQ_API_KEY'))   define('GROQ_API_KEY',   '');
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', '');

// ── Autoload core includes ────────────────────────────────────
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
