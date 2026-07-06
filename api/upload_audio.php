<?php
/**
 * API: Upload Audio — Phase 4
 * AI Interview Assessment Platform
 *
 * Receives raw audio blob (webm/ogg/mp4) from MediaRecorder
 * and stores it under uploads/audio/.
 *
 * POST fields:
 *   attempt_id   INT    required
 *   question_id  INT    required
 *   response_time INT   seconds candidate spent (optional)
 *   audio_blob   FILE   the audio recording
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/sessions.php';

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}
$candidateId = (int) $_SESSION['user_id'];

// ── Input validation ──────────────────────────────────────────
$attemptId    = (int) ($_POST['attempt_id']   ?? 0);
$questionId   = (int) ($_POST['question_id']  ?? 0);
$responseTime = isset($_POST['response_time']) ? (int) $_POST['response_time'] : null;

if ($attemptId <= 0 || $questionId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'attempt_id and question_id required']);
    exit;
}

// Verify the attempt belongs to this candidate
$attempt = getAttemptById($attemptId);
if (!$attempt || (int) $attempt['candidate_id'] !== $candidateId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

// ── File handling ─────────────────────────────────────────────
$uploadDir = __DIR__ . '/../uploads/audio/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if file was uploaded
if (empty($_FILES['audio_blob']) || $_FILES['audio_blob']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No audio file received']);
    exit;
}

$tmp      = $_FILES['audio_blob']['tmp_name'];
$fileSize = $_FILES['audio_blob']['size'];
$maxSize  = 50 * 1024 * 1024; // 50 MB per answer

if ($fileSize > $maxSize) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Audio file too large (max 50 MB)']);
    exit;
}

// Detect MIME and choose extension
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmp);
$extMap   = [
    'audio/webm'        => 'webm',
    'audio/ogg'         => 'ogg',
    'video/webm'        => 'webm',   // Chrome uses video/webm for audio+video
    'audio/mp4'         => 'mp4',
    'audio/mpeg'        => 'mp3',
    'application/octet-stream' => 'webm', // Fallback for some browsers
];
$ext = $extMap[$mimeType] ?? 'webm';

// Sanitised filename: attempt_{id}_q{qid}_{timestamp}.{ext}
$filename  = sprintf(
    'attempt_%d_q%d_%s.%s',
    $attemptId,
    $questionId,
    date('Ymd_His'),
    $ext
);
$destPath  = $uploadDir . $filename;
$audioPath = 'uploads/audio/' . $filename;

if (!move_uploaded_file($tmp, $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save audio file']);
    exit;
}

// ── Save to DB ────────────────────────────────────────────────
$saved = saveAnswer($attemptId, $questionId, $audioPath, $responseTime);
if (!$saved) {
    // Clean up file if DB write failed
    @unlink($destPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to record answer in database']);
    exit;
}

echo json_encode([
    'ok'         => true,
    'audio_path' => $audioPath,
    'message'    => 'Audio saved successfully',
]);
