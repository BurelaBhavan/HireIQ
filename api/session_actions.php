<?php
/**
 * API: Session Actions — Phase 4
 * AI Interview Assessment Platform
 *
 * Accepts JSON POST.  All responses are JSON.
 *
 * Actions:
 *   start_attempt         → start / re-join an interview attempt
 *   submit_attempt        → complete an interview attempt
 *   start_test_attempt    → start / re-join a test attempt
 *   submit_test_attempt   → complete a test attempt
 *   log_tab_switch        → record TAB_HIDDEN / TAB_VISIBLE
 *   log_fullscreen        → record FULLSCREEN_EXIT / FULLSCREEN_ENTER
 *   log_integrity         → record an integrity event
 *   log_presence          → record camera presence snapshot
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/sessions.php';

header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

$candidateId = (int) $_SESSION['user_id'];

// ── Parse request ─────────────────────────────────────────────
$raw    = file_get_contents('php://input');
$body   = json_decode($raw ?: '{}', true) ?? [];
$action = $body['action'] ?? ($_POST['action'] ?? '');

// ── CSRF (relaxed for XHR – validate Origin header) ──────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    $parsedAllowed = parse_url(BASE_URL);
    $allowedOrigin = ($parsedAllowed['scheme'] ?? 'http') . '://' . ($parsedAllowed['host'] ?? 'localhost');
    if (!empty($parsedAllowed['port'])) {
        $allowedOrigin .= ':' . $parsedAllowed['port'];
    }
    if (strcasecmp($origin, $allowedOrigin) !== 0) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Cross-origin request denied']);
        exit;
    }
}

// ── Dispatch ──────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Interview Attempt ─────────────────────────────────
        case 'start_attempt': {
            $interviewId = (int) ($body['interview_id'] ?? 0);
            if ($interviewId <= 0) {
                jsonError('interview_id required');
            }

            // Verify candidate has an Accepted invitation
            $db   = getDB();
            $inv  = $db->prepare(
                "SELECT id FROM interview_invitations
                  WHERE interview_id = :iid AND candidate_id = :cid
                    AND status = 'Accepted' LIMIT 1"
            );
            $inv->execute([':iid' => $interviewId, ':cid' => $candidateId]);
            if (!$inv->fetch()) {
                jsonError('No accepted invitation found', 403);
            }

            $attempt = getOrCreateAttempt($candidateId, $interviewId);
            if (!$attempt) {
                jsonError('Could not create attempt');
            }

            if ($attempt['status'] === 'completed') {
                jsonOk(['attempt' => $attempt, 'already_completed' => true]);
            }

            startAttempt((int) $attempt['id']);
            $attempt = getAttemptById((int) $attempt['id']);
            jsonOk(['attempt' => $attempt]);
        }

        case 'submit_attempt': {
            $attemptId = (int) ($body['attempt_id'] ?? 0);
            if ($attemptId <= 0) {
                jsonError('attempt_id required');
            }

            $attempt = getAttemptById($attemptId);
            if (!$attempt || (int) $attempt['candidate_id'] !== $candidateId) {
                jsonError('Attempt not found', 404);
            }

            completeAttempt($attemptId);
            jsonOk(['message' => 'Interview submitted successfully']);
        }

        // ── Test Attempt ──────────────────────────────────────
        case 'start_test_attempt': {
            $testId = (int) ($body['test_id'] ?? 0);
            if ($testId <= 0) {
                jsonError('test_id required');
            }

            $db  = getDB();
            $inv = $db->prepare(
                "SELECT id FROM test_invitations
                  WHERE test_id = :tid AND candidate_id = :cid
                    AND status = 'Accepted' LIMIT 1"
            );
            $inv->execute([':tid' => $testId, ':cid' => $candidateId]);
            if (!$inv->fetch()) {
                jsonError('No accepted invitation found', 403);
            }

            $ta = getOrCreateTestAttempt($candidateId, $testId);
            if (!$ta) {
                jsonError('Could not create test attempt');
            }

            if ($ta['status'] === 'completed') {
                jsonOk(['attempt' => $ta, 'already_completed' => true]);
            }

            startTestAttempt((int) $ta['id']);
            $ta = getTestAttemptById((int) $ta['id']);
            jsonOk(['attempt' => $ta]);
        }

        case 'submit_test_attempt': {
            $taId = (int) ($body['attempt_id'] ?? 0);
            if ($taId <= 0) {
                jsonError('attempt_id required');
            }

            $ta = getTestAttemptById($taId);
            if (!$ta || (int) $ta['candidate_id'] !== $candidateId) {
                jsonError('Test attempt not found', 404);
            }

            completeTestAttempt($taId);
            jsonOk(['message' => 'Test submitted successfully']);
        }

        case 'autosave_test_answer': {
            $testId     = (int) ($body['test_id'] ?? 0);
            $questionId = (int) ($body['question_id'] ?? 0);
            $answer     = $body['answer'] ?? '';

            if ($testId <= 0 || $questionId <= 0) {
                jsonError('Invalid parameters');
            }

            if (!isset($_SESSION['test_answers'])) {
                $_SESSION['test_answers'] = [];
            }
            if (!isset($_SESSION['test_answers'][$testId])) {
                $_SESSION['test_answers'][$testId] = [];
            }
            $_SESSION['test_answers'][$testId][$questionId] = $answer;

            jsonOk(['saved' => true]);
        }

        // ── Monitoring Events ─────────────────────────────────
        case 'log_tab_switch': {
            $attemptId = (int) ($body['attempt_id'] ?? 0);
            $eventType = $body['event_type'] ?? '';
            if ($attemptId <= 0 || !in_array($eventType, ['TAB_HIDDEN', 'TAB_VISIBLE'], true)) {
                jsonError('Invalid parameters');
            }
            logTabSwitch($attemptId, $eventType);
            jsonOk(['logged' => true]);
        }

        case 'log_fullscreen': {
            $attemptId = (int) ($body['attempt_id'] ?? 0);
            $eventType = $body['event_type'] ?? '';
            if ($attemptId <= 0 || !in_array($eventType, ['FULLSCREEN_EXIT', 'FULLSCREEN_ENTER'], true)) {
                jsonError('Invalid parameters');
            }
            logFullscreen($attemptId, $eventType);
            jsonOk(['logged' => true]);
        }

        case 'log_integrity': {
            $attemptId = (int) ($body['attempt_id'] ?? 0);
            $eventType = trim($body['event_type'] ?? '');
            $severity  = in_array($body['severity'] ?? '', ['warning', 'flag'], true)
                         ? $body['severity'] : 'warning';
            if ($attemptId <= 0 || $eventType === '') {
                jsonError('Invalid parameters');
            }
            logIntegrityEvent($attemptId, $eventType, $severity);
            jsonOk(['logged' => true]);
        }

        case 'log_presence': {
            // Phase 4: camera availability only — face_detected always false.
            // Hook for future MediaPipe: pass face_detected = true|false.
            $attemptId   = (int) ($body['attempt_id'] ?? 0);
            $faceDetected = (bool) ($body['face_detected'] ?? false);
            if ($attemptId <= 0) {
                jsonError('attempt_id required');
            }
            logPresence($attemptId, $faceDetected);
            jsonOk(['logged' => true]);
        }

        default:
            jsonError('Unknown action', 400);
    }
} catch (Throwable $e) {
    error_log('session_actions.php error: ' . $e->getMessage());
    jsonError('Server error', 500);
}

// ── Helpers ───────────────────────────────────────────────────
function jsonOk(array $data = []): never
{
    echo json_encode(['ok' => true, ...$data]);
    exit;
}

function jsonError(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
