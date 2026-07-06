<?php
/**
 * api/log_activity.php — Document Security Event Logger
 * AI Interview Assessment Platform — Phase 4.5
 *
 * AJAX endpoint — POST only, JSON responses.
 *
 * Actions:
 *   heartbeat  — update session counters
 *   violation  — log a single security violation event
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/documents.php';

// ── AJAX only ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── Session guard ─────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

$userId = (int) $_SESSION['user_id'];
$action = trim($_POST['action'] ?? '');
$docId  = (int) ($_POST['doc_id']  ?? 0);
$logId  = (int) ($_POST['log_id']  ?? 0);

if ($docId <= 0 || $logId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing doc_id or log_id']);
    exit;
}

// Verify document exists
$doc = getDocumentById($docId);
if (!$doc) {
    http_response_code(404);
    echo json_encode(['error' => 'Document not found']);
    exit;
}

// ── Route by action ────────────────────────────────────────────
switch ($action) {

    case 'progress':
        $currentPage = (int) ($_POST['current_page'] ?? 1);
        $pagesRead   = (int) ($_POST['pages_read']   ?? 0);
        $totalPages  = (int) ($_POST['total_pages']  ?? 0);
        updateDocumentProgress($logId, $currentPage, $pagesRead, $totalPages);
        echo json_encode(['success' => true, 'action' => 'progress']);
        break;

    case 'heartbeat':
        $counters = [
            'tab_switch'      => (int) ($_POST['tab_switch']      ?? 0),
            'fullscreen_exit' => (int) ($_POST['fullscreen_exit'] ?? 0),
            'copy_attempt'    => (int) ($_POST['copy_attempt']    ?? 0),
            'print_attempt'   => (int) ($_POST['print_attempt']   ?? 0),
            'right_click'     => (int) ($_POST['right_click']     ?? 0),
            'devtools'        => (int) ($_POST['devtools']        ?? 0),
            'screenshot'      => (int) ($_POST['screenshot']      ?? 0),
        ];
        updateDocumentSession($logId, $counters);
        echo json_encode(['success' => true, 'action' => 'heartbeat', 'ts' => time()]);
        break;

    case 'violation':
        $allowedTypes = [
            'right_click', 'copy_attempt', 'print_attempt', 'keyboard_shortcut',
            'tab_switch', 'fullscreen_exit', 'devtools_open', 'screenshot_suspicion',
            'screen_share_detected', 'drag_attempt', 'selection_attempt',
            'session_expired', 'heartbeat_failed',
        ];
        $eventType   = $_POST['event_type']   ?? '';
        $eventDetail = $_POST['event_detail'] ?? '';

        if (!in_array($eventType, $allowedTypes, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid event type']);
            break;
        }

        logViolation($logId, $docId, $userId, $eventType, $eventDetail);
        echo json_encode(['success' => true, 'action' => 'violation', 'type' => $eventType]);
        break;

    case 'close':
        // Final close event — end the document session
        $duration = (int) ($_POST['duration'] ?? 0);
        endDocumentSession($logId, [
            'duration_seconds' => $duration,
            'tab_switch'       => (int) ($_POST['tab_switch']      ?? 0),
            'fullscreen_exit'  => (int) ($_POST['fullscreen_exit'] ?? 0),
            'copy_attempt'     => (int) ($_POST['copy_attempt']    ?? 0),
            'print_attempt'    => (int) ($_POST['print_attempt']   ?? 0),
            'right_click'      => (int) ($_POST['right_click']     ?? 0),
            'devtools'         => (int) ($_POST['devtools']        ?? 0),
            'screenshot'       => (int) ($_POST['screenshot']      ?? 0),
        ]);
        echo json_encode(['success' => true, 'action' => 'close']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
