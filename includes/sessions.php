<?php
/**
 * Session Data Access — Phase 4
 * AI Interview Assessment Platform
 *
 * Covers: attempts, test_attempts, answers, presence_logs,
 *         tab_switch_logs, fullscreen_logs, integrity_events
 *
 * All queries use prepared statements — never interpolate input.
 */

declare(strict_types=1);

// ── Attempt helpers ───────────────────────────────────────────

/**
 * Find or create an attempt row for a candidate / interview pair.
 * Returns the attempt array, or null on DB error.
 *
 * @return array<string,mixed>|null
 */
function getOrCreateAttempt(int $candidateId, int $interviewId): ?array
{
    $db = getDB();

    // Fetch existing non-expired attempt
    $stmt = $db->prepare(
        "SELECT * FROM attempts
          WHERE candidate_id = :cid AND interview_id = :iid
            AND status NOT IN ('expired')
          ORDER BY created_at DESC
          LIMIT 1"
    );
    $stmt->execute([':cid' => $candidateId, ':iid' => $interviewId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    // Create fresh attempt
    $ins = $db->prepare(
        "INSERT INTO attempts (candidate_id, interview_id, status)
         VALUES (:cid, :iid, 'not_started')"
    );
    $ins->execute([':cid' => $candidateId, ':iid' => $interviewId]);
    $newId = (int) $db->lastInsertId();

    return getAttemptById($newId);
}

/**
 * Fetch a single attempt by its primary key.
 *
 * @return array<string,mixed>|null
 */
function getAttemptById(int $id): ?array
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM attempts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mark an attempt as in_progress and record start_time (once only).
 */
function startAttempt(int $attemptId): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE attempts
            SET status = 'in_progress',
                start_time = COALESCE(start_time, NOW())
          WHERE id = :id AND status IN ('not_started','in_progress')"
    );
    $stmt->execute([':id' => $attemptId]);
    return $stmt->rowCount() > 0;
}

/**
 * Mark an attempt as completed and record end_time.
 */
function completeAttempt(int $attemptId): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE attempts
            SET status = 'completed', end_time = NOW()
          WHERE id = :id AND status = 'in_progress'"
    );
    $stmt->execute([':id' => $attemptId]);
    return $stmt->rowCount() > 0;
}

// ── Test attempt helpers ──────────────────────────────────────

/**
 * Find or create a test_attempt row.
 *
 * @return array<string,mixed>|null
 */
function getOrCreateTestAttempt(int $candidateId, int $testId): ?array
{
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT * FROM test_attempts
          WHERE candidate_id = :cid AND test_id = :tid
            AND status NOT IN ('expired')
          ORDER BY created_at DESC
          LIMIT 1"
    );
    $stmt->execute([':cid' => $candidateId, ':tid' => $testId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $ins = $db->prepare(
        "INSERT INTO test_attempts (candidate_id, test_id, status)
         VALUES (:cid, :tid, 'not_started')"
    );
    $ins->execute([':cid' => $candidateId, ':tid' => $testId]);
    $newId = (int) $db->lastInsertId();

    $s2 = $db->prepare('SELECT * FROM test_attempts WHERE id = :id LIMIT 1');
    $s2->execute([':id' => $newId]);
    return $s2->fetch() ?: null;
}

/**
 * Fetch a single test_attempt by primary key.
 *
 * @return array<string,mixed>|null
 */
function getTestAttemptById(int $id): ?array
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM test_attempts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function startTestAttempt(int $taId): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE test_attempts
            SET status = 'in_progress',
                start_time = COALESCE(start_time, NOW())
          WHERE id = :id AND status IN ('not_started','in_progress')"
    );
    $stmt->execute([':id' => $taId]);
    return $stmt->rowCount() > 0;
}

function completeTestAttempt(int $taId): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE test_attempts
            SET status = 'completed', end_time = NOW()
          WHERE id = :id AND status = 'in_progress'"
    );
    $stmt->execute([':id' => $taId]);
    return $stmt->rowCount() > 0;
}

// ── Answer helpers ────────────────────────────────────────────

/**
 * Upsert an answer row (create or update audio_path + response_time).
 */
function saveAnswer(int $attemptId, int $questionId, ?string $audioPath, ?int $responseTime): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO answers (attempt_id, question_id, audio_path, response_time)
              VALUES (:aid, :qid, :ap, :rt)
         ON DUPLICATE KEY UPDATE
              audio_path    = VALUES(audio_path),
              response_time = VALUES(response_time)"
    );
    return $stmt->execute([
        ':aid' => $attemptId,
        ':qid' => $questionId,
        ':ap'  => $audioPath,
        ':rt'  => $responseTime,
    ]);
}

/**
 * Return all answers for an attempt, joined with question text.
 *
 * @return array[]
 */
function getAnswersForAttempt(int $attemptId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT a.*, q.question_text, q.difficulty
           FROM answers a
           JOIN questions q ON q.id = a.question_id
           LEFT JOIN attempts att ON att.id = a.attempt_id
           LEFT JOIN interview_questions iq ON iq.interview_id = att.interview_id AND iq.question_id = a.question_id
          WHERE a.attempt_id = :aid
          ORDER BY iq.sequence_order ASC, q.id ASC"
    );
    $stmt->execute([':aid' => $attemptId]);
    return $stmt->fetchAll();
}

// ── Monitoring log helpers ────────────────────────────────────

/**
 * Log a camera presence snapshot.
 * Face detection is kept as a hook for future MediaPipe integration.
 * For Phase 4 only camera permission / availability is tracked.
 *
 * @param int  $attemptId
 * @param bool $faceDetected  Always false in Phase 4 (MediaPipe not integrated)
 */
function logPresence(int $attemptId, bool $faceDetected): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO presence_logs (attempt_id, face_detected)
         VALUES (:aid, :fd)'
    );
    return $stmt->execute([':aid' => $attemptId, ':fd' => (int) $faceDetected]);
}

/**
 * Log a tab visibility change event.
 *
 * @param string $eventType  'TAB_HIDDEN' | 'TAB_VISIBLE'
 */
function logTabSwitch(int $attemptId, string $eventType): bool
{
    $allowed = ['TAB_HIDDEN', 'TAB_VISIBLE'];
    if (!in_array($eventType, $allowed, true)) {
        return false;
    }
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO tab_switch_logs (attempt_id, event_type)
         VALUES (:aid, :et)'
    );
    return $stmt->execute([':aid' => $attemptId, ':et' => $eventType]);
}

/**
 * Log a fullscreen state change event.
 *
 * @param string $eventType  'FULLSCREEN_EXIT' | 'FULLSCREEN_ENTER'
 */
function logFullscreen(int $attemptId, string $eventType): bool
{
    $allowed = ['FULLSCREEN_EXIT', 'FULLSCREEN_ENTER'];
    if (!in_array($eventType, $allowed, true)) {
        return false;
    }
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO fullscreen_logs (attempt_id, event_type)
         VALUES (:aid, :et)'
    );
    return $stmt->execute([':aid' => $attemptId, ':et' => $eventType]);
}

/**
 * Record an integrity event (warning or flag).
 *
 * @param string $eventType  e.g. 'TAB_SWITCH', 'FULLSCREEN_EXIT', 'CAMERA_DISABLED'
 * @param string $severity   'warning' | 'flag'
 */
function logIntegrityEvent(int $attemptId, string $eventType, string $severity = 'warning'): bool
{
    if (!in_array($severity, ['warning', 'flag'], true)) {
        $severity = 'warning';
    }
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO integrity_events (attempt_id, event_type, severity)
         VALUES (:aid, :et, :sv)'
    );
    return $stmt->execute([':aid' => $attemptId, ':et' => $eventType, ':sv' => $severity]);
}

// ── Admin read helpers ────────────────────────────────────────

/**
 * Get all attempts for an interview with candidate info.
 *
 * @return array[]
 */
function getAttemptsForInterview(int $interviewId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT a.*,
                u.full_name, u.email,
                TIMESTAMPDIFF(SECOND, a.start_time, COALESCE(a.end_time, NOW())) AS duration_sec,
                (SELECT COUNT(*) FROM tab_switch_logs  tsl WHERE tsl.attempt_id = a.id AND tsl.event_type = 'TAB_HIDDEN')    AS tab_switch_count,
                (SELECT COUNT(*) FROM fullscreen_logs  fsl WHERE fsl.attempt_id = a.id AND fsl.event_type = 'FULLSCREEN_EXIT') AS fullscreen_exit_count,
                (SELECT COUNT(*) FROM integrity_events ie  WHERE ie.attempt_id  = a.id AND ie.event_type = 'CAMERA_DISABLED') AS camera_violation_count,
                (SELECT COUNT(*) FROM integrity_events ie2 WHERE ie2.attempt_id = a.id)                                       AS total_violations
           FROM attempts a
           JOIN users u ON u.id = a.candidate_id
          WHERE a.interview_id = :iid
          ORDER BY a.created_at DESC"
    );
    $stmt->execute([':iid' => $interviewId]);
    return $stmt->fetchAll();
}

/**
 * Get full attempt detail for admin review.
 *
 * @return array<string,mixed>|null
 */
function getAttemptDetail(int $attemptId): ?array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT a.*,
                u.full_name, u.email,
                i.title AS interview_title, i.duration AS interview_duration,
                TIMESTAMPDIFF(SECOND, a.start_time, COALESCE(a.end_time, NOW())) AS duration_sec,
                (SELECT COUNT(*) FROM tab_switch_logs  t WHERE t.attempt_id  = a.id AND t.event_type = 'TAB_HIDDEN')     AS tab_switches,
                (SELECT COUNT(*) FROM fullscreen_logs  f WHERE f.attempt_id  = a.id AND f.event_type = 'FULLSCREEN_EXIT') AS fs_exits,
                (SELECT COUNT(*) FROM integrity_events ie WHERE ie.attempt_id = a.id AND ie.event_type = 'CAMERA_DISABLED') AS camera_violations,
                (SELECT COUNT(*) FROM integrity_events ie2 WHERE ie2.attempt_id = a.id) AS total_violations
           FROM attempts a
           JOIN users u ON u.id = a.candidate_id
           JOIN interviews i ON i.id = a.interview_id
          WHERE a.id = :id
          LIMIT 1"
    );
    $stmt->execute([':id' => $attemptId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get integrity events timeline for an attempt.
 *
 * @return array[]
 */
function getIntegrityEvents(int $attemptId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM integrity_events
          WHERE attempt_id = :aid
          ORDER BY event_time ASC'
    );
    $stmt->execute([':aid' => $attemptId]);
    return $stmt->fetchAll();
}

/**
 * Get tab switch log for an attempt.
 *
 * @return array[]
 */
function getTabSwitchLog(int $attemptId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM tab_switch_logs WHERE attempt_id = :aid ORDER BY timestamp ASC'
    );
    $stmt->execute([':aid' => $attemptId]);
    return $stmt->fetchAll();
}

/**
 * Get fullscreen log for an attempt.
 *
 * @return array[]
 */
function getFullscreenLog(int $attemptId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM fullscreen_logs WHERE attempt_id = :aid ORDER BY timestamp ASC'
    );
    $stmt->execute([':aid' => $attemptId]);
    return $stmt->fetchAll();
}
