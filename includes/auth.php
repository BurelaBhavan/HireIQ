<?php
/**
 * Authentication Helper
 * AI Interview Assessment Platform
 *
 * All session writes happen here.  No other file should call
 * session_start() — this file handles it.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// candidates.php provides updateLastLogin()
if (!function_exists('updateLastLogin')) {
    require_once __DIR__ . '/candidates.php';
}

// ── Public API ────────────────────────────────────────────────

/**
 * Attempt to log a user in by email + plain-text password.
 * Returns the user row on success, null on failure.
 *
 * @return array<string,mixed>|null
 */
function loginUser(string $email, string $password): ?array
{
    try {
        $db  = getDB();
        $sql = 'SELECT id, full_name, email, password_hash, role, is_active
                FROM users
                WHERE email = :email
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        if (!(bool)$user['is_active']) {
            return null;   // Account disabled
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Rotate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];

        // Record last login timestamp
        try { updateLastLogin((int) $user['id']); } catch (Throwable) {}

        return $user;

    } catch (PDOException $e) {
        error_log('loginUser PDO error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Register a new candidate account.
 * Returns true on success, or a string error message on failure.
 */
function registerCandidate(string $fullName, string $email, string $password): true|string
{
    try {
        $db = getDB();

        // Duplicate e-mail guard
        $check = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);

        if ($check->fetch()) {
            return 'An account with this email already exists.';
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $insert = $db->prepare(
            'INSERT INTO users (full_name, email, password_hash, role)
             VALUES (:name, :email, :hash, :role)'
        );
        $insert->execute([
            ':name'  => $fullName,
            ':email' => $email,
            ':hash'  => $hash,
            ':role'  => 'candidate',
        ]);

        return true;

    } catch (PDOException $e) {
        error_log('registerCandidate PDO error: ' . $e->getMessage());
        return 'A system error occurred. Please try again.';
    }
}

/**
 * Destroy the current session and redirect to login.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    redirect(BASE_URL . '/login.php');
}

// ── Guards ────────────────────────────────────────────────────

/**
 * Require the visitor to be authenticated; redirect to login otherwise.
 */
function requireAuth(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * Require a specific role; redirect to appropriate dashboard otherwise.
 */
function requireRole(string $role): void
{
    requireAuth();

    if ($_SESSION['user_role'] !== $role) {
        $map = [
            'super_admin' => BASE_URL . '/admin/dashboard.php',
            'candidate'   => BASE_URL . '/candidate/dashboard.php',
        ];
        $dest = $map[$_SESSION['user_role']] ?? BASE_URL . '/login.php';
        redirect($dest);
    }
}

/**
 * True when someone is currently logged in.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Redirect already-authenticated users to their dashboard.
 */
function redirectIfLoggedIn(): void
{
    if (isLoggedIn()) {
        $map = [
            'super_admin' => BASE_URL . '/admin/dashboard.php',
            'candidate'   => BASE_URL . '/candidate/dashboard.php',
        ];
        $dest = $map[$_SESSION['user_role']] ?? BASE_URL . '/';
        redirect($dest);
    }
}

/**
 * Store a password-reset token.
 * Returns the token on success, null on failure.
 */
function createPasswordResetToken(string $email): ?string
{
    try {
        $db = getDB();

        // Verify e-mail exists
        $check = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);
        if (!$check->fetch()) {
            return null;   // Don't reveal whether email exists
        }

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Invalidate old tokens for this email
        $db->prepare('DELETE FROM password_resets WHERE email = :email')
           ->execute([':email' => $email]);

        $db->prepare(
            'INSERT INTO password_resets (email, token, expires_at)
             VALUES (:email, :token, :expires)'
        )->execute([':email' => $email, ':token' => $token, ':expires' => $expires]);

        return $token;

    } catch (PDOException $e) {
        error_log('createPasswordResetToken error: ' . $e->getMessage());
        return null;
    }
}
