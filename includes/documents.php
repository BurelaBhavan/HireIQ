<?php
/**
 * Document Management — Phase 4.5 Secure Document Viewer
 * AI Interview Assessment Platform
 *
 * Adds:
 *  - generateViewToken()      Short-lived HMAC token for streaming auth
 *  - validateViewToken()      Verify & optionally consume a token
 *  - streamDocument()         Authenticated PHP file streamer
 *  - startDocumentSession()   Create audit log row
 *  - endDocumentSession()     Close audit log row with stats
 *  - logViolation()           Record a security violation event
 *  - getDocumentAnalytics()   Admin analytics queries
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// ─────────────────────────────────────────────────────────────
// Basic CRUD
// ─────────────────────────────────────────────────────────────

function getAllDocuments(): array
{
    $db  = getDB();
    $sql = "SELECT d.*, u.full_name AS uploader_name,
              (SELECT COUNT(*) FROM document_reads      WHERE document_id = d.id) AS read_count,
              (SELECT COUNT(*) FROM document_activity_logs WHERE document_id = d.id) AS view_count
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            ORDER BY d.uploaded_at DESC";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getDocumentById(int $id): ?array
{
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function uploadDocument(string $title, string $description, array $file, int $userId): array
{
    $allowedMimes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
    ];
    $maxSize = 10 * 1024 * 1024; // 10 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error.'];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File is too large. Max size is 10 MB.'];
    }

    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'docx', 'doc', 'ppt', 'pptx'];
    if (!in_array($ext, $allowedExts, true)) {
        return ['success' => false, 'error' => 'Invalid file extension. Only PDF, DOCX, and PPT allowed.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes, true)
        && !in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
        return ['success' => false, 'error' => 'Invalid file type detected. Only PDF, DOCX, PPT allowed.'];
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    $filename = uniqid('doc_', true) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }

    // Determine simple file type label
    $typeMap  = ['pdf' => 'pdf', 'docx' => 'docx', 'doc' => 'docx', 'pptx' => 'pptx', 'ppt' => 'pptx'];
    $fileType = $typeMap[$ext] ?? $ext;

    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO documents (title, description, file_path, file_size, file_type, original_name, is_restricted, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)"
    );
    $dbPath = 'uploads/' . $filename;

    if ($stmt->execute([$title, $description, $dbPath, $file['size'], $fileType, $file['name'], $userId])) {
        return ['success' => true, 'id' => (int) $db->lastInsertId()];
    }

    @unlink($destPath);
    return ['success' => false, 'error' => 'Database error during upload.'];
}

function deleteDocument(int $id): bool
{
    $db  = getDB();
    $doc = getDocumentById($id);
    if (!$doc) {
        return false;
    }
    $filePath = __DIR__ . '/../' . $doc['file_path'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
    return $stmt->execute([$id]);
}

function getCandidateDocuments(int $candidateId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT d.*,
                (SELECT read_at FROM document_reads WHERE document_id = d.id AND candidate_id = :cid) AS read_at
         FROM documents d
         ORDER BY d.uploaded_at DESC"
    );
    $stmt->execute([':cid' => $candidateId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markDocumentAsRead(int $documentId, int $candidateId): bool
{
    $db   = getDB();
    $stmt = $db->prepare("SELECT read_at FROM document_reads WHERE document_id = ? AND candidate_id = ?");
    $stmt->execute([$documentId, $candidateId]);
    if ($stmt->fetch()) {
        return true; // already read
    }
    $stmt = $db->prepare("INSERT INTO document_reads (document_id, candidate_id) VALUES (?, ?)");
    return $stmt->execute([$documentId, $candidateId]);
}

// ─────────────────────────────────────────────────────────────
// View Tokens  (short-lived, HMAC-signed, single-use stream auth)
// ─────────────────────────────────────────────────────────────

/** TTL for view tokens in seconds */
const DOC_TOKEN_TTL = 300; // 5 minutes; refreshed via heartbeat

/**
 * Generate and persist a view token for a user/document pair.
 * Returns the token string (128-char hex).
 */
function generateViewToken(int $documentId, int $userId): string
{
    $db    = getDB();
    $token = bin2hex(random_bytes(48)); // 96 bytes → 192 hex? — use 32 for 64 hex
    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $expires = date('Y-m-d H:i:s', time() + DOC_TOKEN_TTL);

    // Invalidate older unused tokens for this user/doc combo
    $db->prepare(
        "DELETE FROM document_view_tokens WHERE user_id = ? AND document_id = ? AND used = 0"
    )->execute([$userId, $documentId]);

    $db->prepare(
        "INSERT INTO document_view_tokens (token, document_id, user_id, expires_at) VALUES (?, ?, ?, ?)"
    )->execute([$token, $documentId, $userId, $expires]);

    return $token;
}

/**
 * Validate a view token.
 * @param bool $consume  If true, mark the token as used after validation.
 * Returns the token row on success, null on failure.
 */
function validateViewToken(string $token, int $documentId, int $userId, bool $consume = false): ?array
{
    if (empty($token)) {
        return null;
    }
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM document_view_tokens
         WHERE token = ? AND document_id = ? AND user_id = ?
           AND expires_at > NOW() AND used = 0
         LIMIT 1"
    );
    $stmt->execute([$token, $documentId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    if ($consume) {
        $db->prepare("UPDATE document_view_tokens SET used = 1 WHERE id = ?")->execute([$row['id']]);
    }

    return $row;
}

/**
 * Refresh a view token (extend TTL) — called by heartbeat.
 * Creates a new token, returns it.
 */
function refreshViewToken(int $documentId, int $userId): string
{
    return generateViewToken($documentId, $userId);
}

// ─────────────────────────────────────────────────────────────
// Document Streaming
// ─────────────────────────────────────────────────────────────

/**
 * Stream a document file to the browser with secure headers.
 * Terminates execution after sending.
 */
function streamDocument(array $doc): void
{
    $filePath = __DIR__ . '/../' . $doc['file_path'];

    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        exit('Document not found.');
    }

    $ext      = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
    $mimeMap  = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt'  => 'application/vnd.ms-powerpoint',
    ];
    $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

    // Security headers — no caching, no download, same-origin only
    header('Content-Type: '         . $mimeType);
    header('Content-Disposition: inline; filename="document.' . $ext . '"');
    header('Content-Length: '       . filesize($filePath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: default-src \'self\'');
    header('X-Robots-Tag: noindex, nofollow');

    // Disable output buffering for clean streaming
    if (ob_get_level()) {
        ob_end_clean();
    }

    readfile($filePath);
    exit;
}

// ─────────────────────────────────────────────────────────────
// Audit Logging
// ─────────────────────────────────────────────────────────────

/**
 * Create a new activity log row when a user opens a document.
 * Returns the new log row ID.
 */
function startDocumentSession(int $documentId, int $userId, string $token = ''): int
{
    $db  = getDB();
    $ip  = getClientIP();
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Simple browser/device detection
    [$browser, $device] = detectBrowserDevice($ua);

    $stmt = $db->prepare(
        "INSERT INTO document_activity_logs
             (document_id, user_id, session_token, ip_address, user_agent, browser, device_type)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$documentId, $userId, $token, $ip, substr($ua, 0, 500), $browser, $device]);
    return (int) $db->lastInsertId();
}

/**
 * Close an activity log row with final stats.
 */
function endDocumentSession(int $logId, array $stats = []): void
{
    $db       = getDB();
    $viewEnd  = date('Y-m-d H:i:s');
    $duration = (int) ($stats['duration_seconds'] ?? 0);

    $db->prepare(
        "UPDATE document_activity_logs SET
            view_end              = ?,
            duration_seconds      = ?,
            tab_switch_count      = tab_switch_count      + ?,
            fullscreen_exit_count = fullscreen_exit_count + ?,
            copy_attempt_count    = copy_attempt_count    + ?,
            print_attempt_count   = print_attempt_count   + ?,
            right_click_count     = right_click_count     + ?,
            devtools_detected     = ?,
            screenshot_suspicion  = screenshot_suspicion  + ?
         WHERE id = ?"
    )->execute([
        $viewEnd,
        $duration,
        (int) ($stats['tab_switch']      ?? 0),
        (int) ($stats['fullscreen_exit'] ?? 0),
        (int) ($stats['copy_attempt']    ?? 0),
        (int) ($stats['print_attempt']   ?? 0),
        (int) ($stats['right_click']     ?? 0),
        (int) ($stats['devtools']        ?? 0),
        (int) ($stats['screenshot']      ?? 0),
        $logId,
    ]);
}

/**
 * Update counters on an existing log row (used by heartbeat AJAX).
 */
function updateDocumentSession(int $logId, array $counters): void
{
    $db = getDB();
    $db->prepare(
        "UPDATE document_activity_logs SET
            tab_switch_count      = tab_switch_count      + ?,
            fullscreen_exit_count = fullscreen_exit_count + ?,
            copy_attempt_count    = copy_attempt_count    + ?,
            print_attempt_count   = print_attempt_count   + ?,
            right_click_count     = right_click_count     + ?,
            devtools_detected     = IF(? = 1, 1, devtools_detected),
            screenshot_suspicion  = screenshot_suspicion  + ?
         WHERE id = ?"
    )->execute([
        (int) ($counters['tab_switch']      ?? 0),
        (int) ($counters['fullscreen_exit'] ?? 0),
        (int) ($counters['copy_attempt']    ?? 0),
        (int) ($counters['print_attempt']   ?? 0),
        (int) ($counters['right_click']     ?? 0),
        (int) ($counters['devtools']        ?? 0),
        (int) ($counters['screenshot']      ?? 0),
        $logId,
    ]);
}

/**
 * Log an individual security violation event.
 */
function logViolation(int $logId, int $documentId, int $userId, string $eventType, string $detail = ''): void
{
    $db   = getDB();
    $ip   = getClientIP();
    $stmt = $db->prepare(
        "INSERT INTO document_violations (log_id, document_id, user_id, event_type, event_detail, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$logId, $documentId, $userId, $eventType, substr($detail, 0, 255), $ip]);
}

// ─────────────────────────────────────────────────────────────
// Analytics queries (admin)
// ─────────────────────────────────────────────────────────────

function getDocumentViewStats(): array
{
    $db  = getDB();
    $sql = "SELECT
              d.id,
              d.title,
              d.file_type,
              d.uploaded_at,
              COUNT(DISTINCT dal.id)            AS total_views,
              COUNT(DISTINCT dal.user_id)       AS unique_viewers,
              COALESCE(AVG(dal.duration_seconds), 0) AS avg_duration,
              SUM(dal.copy_attempt_count)        AS total_copy_attempts,
              SUM(dal.print_attempt_count)       AS total_print_attempts,
              SUM(dal.fullscreen_exit_count)     AS total_fullscreen_exits,
              SUM(dal.tab_switch_count)          AS total_tab_switches,
              SUM(dal.devtools_detected)         AS devtools_count
            FROM documents d
            LEFT JOIN document_activity_logs dal ON dal.document_id = d.id
            GROUP BY d.id
            ORDER BY total_views DESC";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getHighRiskUsers(int $limit = 20): array
{
    $db  = getDB();
    $stmt = $db->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            SUM(dal.copy_attempt_count)    AS copy_attempts,
            SUM(dal.print_attempt_count)   AS print_attempts,
            SUM(dal.fullscreen_exit_count) AS fullscreen_exits,
            SUM(dal.tab_switch_count)      AS tab_switches,
            SUM(dal.devtools_detected)     AS devtools_count,
            SUM(dal.screenshot_suspicion)  AS screenshot_events,
            (SUM(dal.copy_attempt_count) + SUM(dal.print_attempt_count)
             + SUM(dal.fullscreen_exit_count) + SUM(dal.devtools_detected) * 3
             + SUM(dal.screenshot_suspicion) * 2) AS risk_score,
            COUNT(DISTINCT dal.id)         AS total_sessions
         FROM users u
         JOIN document_activity_logs dal ON dal.user_id = u.id
         WHERE u.role = 'candidate'
         GROUP BY u.id
         ORDER BY risk_score DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDocumentActivityLogs(int $documentId, int $limit = 100): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT dal.*, u.full_name, u.email
         FROM document_activity_logs dal
         JOIN users u ON u.id = dal.user_id
         WHERE dal.document_id = ?
         ORDER BY dal.view_start DESC
         LIMIT ?"
    );
    $stmt->execute([$documentId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOverallStats(): array
{
    $db  = getDB();
    $row = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM document_activity_logs)            AS total_views,
            (SELECT COUNT(DISTINCT user_id) FROM document_activity_logs) AS unique_viewers,
            (SELECT COALESCE(AVG(duration_seconds),0) FROM document_activity_logs WHERE view_end IS NOT NULL) AS avg_duration,
            (SELECT COALESCE(SUM(copy_attempt_count),0) FROM document_activity_logs)    AS total_copy_attempts,
            (SELECT COALESCE(SUM(print_attempt_count),0) FROM document_activity_logs)   AS total_print_attempts,
            (SELECT COALESCE(SUM(fullscreen_exit_count),0) FROM document_activity_logs) AS total_fullscreen_exits,
            (SELECT COUNT(*) FROM documents)                          AS total_documents,
            (SELECT COUNT(*) FROM document_violations)                AS total_violations"
    )->fetch(PDO::FETCH_ASSOC);
    return $row ?: [];
}

// ─────────────────────────────────────────────────────────────
// Utility
// ─────────────────────────────────────────────────────────────

function getClientIP(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function detectBrowserDevice(string $ua): array
{
    $browser = 'Unknown';
    $device  = 'Desktop';

    if (str_contains($ua, 'Edg/'))         $browser = 'Edge';
    elseif (str_contains($ua, 'Chrome/'))  $browser = 'Chrome';
    elseif (str_contains($ua, 'Firefox/')) $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari/'))  $browser = 'Safari';
    elseif (str_contains($ua, 'Opera/') || str_contains($ua, 'OPR/')) $browser = 'Opera';

    if (str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone')) {
        $device = 'Mobile';
    } elseif (str_contains($ua, 'Tablet') || str_contains($ua, 'iPad')) {
        $device = 'Tablet';
    }

    return [$browser, $device];
}

function formatDuration(int $seconds): string
{
    if ($seconds < 60)   return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

// ─────────────────────────────────────────────────────────────
// Acknowledgment
// ─────────────────────────────────────────────────────────────

function hasUserAcknowledged(int $docId, int $userId): bool
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id FROM document_acknowledgments WHERE document_id=? AND user_id=? LIMIT 1"
        );
        $stmt->execute([$docId, $userId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────
// Progress tracking (page-level)
// ─────────────────────────────────────────────────────────────

function getDocumentProgress(int $docId, int $userId): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT current_page, pages_read, total_pages, view_start
             FROM document_activity_logs
             WHERE document_id=? AND user_id=?
             ORDER BY view_start DESC LIMIT 1"
        );
        $stmt->execute([$docId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['current_page'=>1,'pages_read'=>0,'total_pages'=>0,'view_start'=>null];
    } catch (Throwable) {
        return ['current_page'=>1,'pages_read'=>0,'total_pages'=>0,'view_start'=>null];
    }
}

function updateDocumentProgress(int $logId, int $currentPage, int $pagesRead, int $totalPages): void
{
    try {
        $db = getDB();
        $db->prepare(
            "UPDATE document_activity_logs
             SET current_page=?, pages_read=?, total_pages=?
             WHERE id=?"
        )->execute([$currentPage, $pagesRead, $totalPages, $logId]);
    } catch (Throwable) {}
}
