<?php
/**
 * API: Evaluate — Phase 5
 * AI Interview Assessment Platform
 *
 * POST JSON body:
 *   { "action": "evaluate_answer", "answer_id": <int> }
 *
 * Workflow:
 *   1. Load answer + audio file
 *   2. Send audio to Groq Whisper → get transcript
 *   3. Send transcript + question to Gemini 2.5 Flash → get evaluation
 *   4. Store results in DB
 *   5. Return JSON response
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../includes/ai_services.php';

header('Content-Type: application/json');

// ── Auth — admin only ─────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

// ── Parse request ─────────────────────────────────────────────
$raw    = file_get_contents('php://input');
$body   = json_decode($raw ?: '{}', true) ?? [];
$action = $body['action'] ?? '';

// ── API key check ─────────────────────────────────────────────
function apiKeysConfigured(): array
{
    $missing = [];
    if (!defined('GROQ_API_KEY')   || GROQ_API_KEY   === '') $missing[] = 'GROQ_API_KEY';
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') $missing[] = 'GEMINI_API_KEY';
    return $missing;
}

// ── Dispatch ──────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Single answer evaluation ──────────────────────────
        case 'evaluate_answer': {
            $answerId = (int) ($body['answer_id'] ?? 0);
            if ($answerId <= 0) {
                evalError('answer_id required');
            }

            $missing = apiKeysConfigured();
            if ($missing) {
                evalError('Missing API keys: ' . implode(', ', $missing) . '. Configure them in .env', 503);
            }

            // Load answer
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT a.*, q.question_text, q.difficulty
                   FROM answers a
                   JOIN questions q ON q.id = a.question_id
                  WHERE a.id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $answerId]);
            $answer = $stmt->fetch();

            if (!$answer) {
                evalError('Answer not found', 404);
            }

            if (!$answer['audio_path']) {
                evalError('No audio recording for this answer', 400);
            }

            $audioAbsPath = __DIR__ . '/../' . $answer['audio_path'];
            if (!file_exists($audioAbsPath)) {
                evalError('Audio file missing on server: ' . $answer['audio_path'], 404);
            }

            // Mark job as started
            upsertEvaluationJob($answerId, 'transcribing');

            // Step 1: Transcribe
            $tStart = microtime(true);
            $transcript = groqTranscribe($audioAbsPath);
            if (!$transcript) {
                upsertEvaluationJob($answerId, 'failed', 'Groq Whisper transcription failed');
                evalError('Transcription failed. Check GROQ_API_KEY and audio file.', 500);
            }

            saveTranscript($answerId, $transcript['text'], $transcript['language'] ?? 'en');
            upsertEvaluationJob($answerId, 'evaluating');

            // Step 2: Evaluate
            $evaluation = geminiEvaluate(
                $answer['question_text'],
                $transcript['text'],
                $answer['difficulty'] ?? 'medium'
            );
            if (!$evaluation) {
                upsertEvaluationJob($answerId, 'failed', 'Gemini evaluation failed');
                evalError('Evaluation failed. Check GEMINI_API_KEY.', 500);
            }

            saveEvaluation($answerId, $evaluation);
            upsertEvaluationJob($answerId, 'completed');

            $elapsed = (int) round((microtime(true) - $tStart) * 1000);

            evalOk([
                'answer_id'   => $answerId,
                'transcript'  => $transcript['text'],
                'language'    => $transcript['language'] ?? 'en',
                'evaluation'  => $evaluation,
                'elapsed_ms'  => $elapsed,
            ]);
        }

        // ── Batch evaluate all answers for an attempt ─────────
        case 'batch_evaluate': {
            $attemptId = (int) ($body['attempt_id'] ?? 0);
            if ($attemptId <= 0) {
                evalError('attempt_id required');
            }

            $missing = apiKeysConfigured();
            if ($missing) {
                evalError('Missing API keys: ' . implode(', ', $missing) . '. Configure them in .env', 503);
            }

            // Load all answers with audio
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT a.id AS answer_id, a.audio_path, q.question_text, q.difficulty
                   FROM answers a
                   JOIN questions q ON q.id = a.question_id
                  WHERE a.attempt_id = :aid
                    AND a.audio_path IS NOT NULL
                  ORDER BY a.id ASC"
            );
            $stmt->execute([':aid' => $attemptId]);
            $answers = $stmt->fetchAll();

            if (empty($answers)) {
                evalError('No answers with audio found for this attempt', 404);
            }

            $results = [];
            foreach ($answers as $answer) {
                $aId          = (int) $answer['answer_id'];
                $audioAbsPath = __DIR__ . '/../' . $answer['audio_path'];

                if (!file_exists($audioAbsPath)) {
                    $results[] = ['answer_id' => $aId, 'ok' => false, 'error' => 'Audio file missing'];
                    upsertEvaluationJob($aId, 'failed', 'Audio file not found on disk');
                    continue;
                }

                // Transcribe
                upsertEvaluationJob($aId, 'transcribing');
                $transcript = groqTranscribe($audioAbsPath);
                if (!$transcript) {
                    $results[] = ['answer_id' => $aId, 'ok' => false, 'error' => 'Transcription failed'];
                    upsertEvaluationJob($aId, 'failed', 'Groq Whisper transcription failed');
                    continue;
                }

                saveTranscript($aId, $transcript['text'], $transcript['language'] ?? 'en');
                upsertEvaluationJob($aId, 'evaluating');

                // Evaluate
                $evaluation = geminiEvaluate(
                    $answer['question_text'],
                    $transcript['text'],
                    $answer['difficulty'] ?? 'medium'
                );
                if (!$evaluation) {
                    $results[] = ['answer_id' => $aId, 'ok' => false, 'error' => 'Evaluation failed'];
                    upsertEvaluationJob($aId, 'failed', 'Gemini evaluation failed');
                    continue;
                }

                saveEvaluation($aId, $evaluation);
                upsertEvaluationJob($aId, 'completed');

                $results[] = [
                    'answer_id'        => $aId,
                    'ok'               => true,
                    'transcript'       => $transcript['text'],
                    'overall_score'    => $evaluation['overall_score'] ?? null,
                    'technical_score'  => $evaluation['technical_score'] ?? null,
                    'comm_score'       => $evaluation['communication_score'] ?? null,
                ];
            }

            $success = count(array_filter($results, fn($r) => $r['ok']));
            $failed  = count($results) - $success;

            evalOk([
                'attempt_id'   => $attemptId,
                'total'        => count($results),
                'success'      => $success,
                'failed'       => $failed,
                'results'      => $results,
            ]);
        }

        // ── Check API key status ──────────────────────────────
        case 'check_keys': {
            $missing = apiKeysConfigured();
            evalOk([
                'groq_configured'   => !in_array('GROQ_API_KEY', $missing),
                'gemini_configured' => !in_array('GEMINI_API_KEY', $missing),
                'ready'             => empty($missing),
            ]);
        }

        default:
            evalError('Unknown action', 400);
    }
} catch (Throwable $e) {
    error_log('evaluate.php error: ' . $e->getMessage());
    evalError('Server error: ' . $e->getMessage(), 500);
}

// ── Helpers ───────────────────────────────────────────────────
function evalOk(array $data = []): never
{
    echo json_encode(['ok' => true, ...$data]);
    exit;
}

function evalError(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
