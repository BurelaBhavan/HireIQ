<?php
/**
 * AI Services — Phase 5
 * AI Interview Assessment Platform
 *
 * Groq Whisper Large V3 Turbo  → transcription
 * Gemini 2.5 Flash             → answer evaluation
 *
 * Usage:
 *   $transcript = groqTranscribe(string $audioPath): array|null
 *   $evaluation = geminiEvaluate(string $questionText, string $transcript): array|null
 */

declare(strict_types=1);

// ── Groq Whisper Transcription ────────────────────────────────

/**
 * Send an audio file to Groq Whisper Large V3 Turbo.
 *
 * @param  string $audioPath  Absolute path to audio file
 * @return array{text: string, language: string}|null  null on failure
 */
function groqTranscribe(string $audioPath): ?array
{
    $apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
    if ($apiKey === '') {
        error_log('[Groq] GROQ_API_KEY is not set.');
        return null;
    }

    if (!file_exists($audioPath) || !is_readable($audioPath)) {
        error_log('[Groq] Audio file not found: ' . $audioPath);
        return null;
    }

    $fileSize = filesize($audioPath);
    if ($fileSize === false || $fileSize === 0) {
        error_log('[Groq] Audio file is empty: ' . $audioPath);
        return null;
    }

    // Max 25 MB (Groq limit)
    if ($fileSize > 25 * 1024 * 1024) {
        error_log('[Groq] Audio file exceeds 25 MB limit: ' . $audioPath);
        return null;
    }

    $mimeMap = [
        'webm' => 'audio/webm',
        'ogg'  => 'audio/ogg',
        'mp3'  => 'audio/mpeg',
        'mp4'  => 'audio/mp4',
        'm4a'  => 'audio/mp4',
        'wav'  => 'audio/wav',
        'flac' => 'audio/flac',
    ];
    $ext  = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
    $mime = $mimeMap[$ext] ?? 'audio/webm';

    // Build multipart form data
    $boundary = '----GroqBoundary' . bin2hex(random_bytes(8));
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio.{$ext}\"\r\n";
    $body .= "Content-Type: {$mime}\r\n\r\n";
    $body .= file_get_contents($audioPath);
    $body .= "\r\n--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
    $body .= "whisper-large-v3-turbo\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
    $body .= "verbose_json\r\n";
    $body .= "--{$boundary}--\r\n";

    $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[Groq] cURL error: ' . $curlError);
        return null;
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !isset($data['text'])) {
        error_log('[Groq] API error (' . $httpCode . '): ' . $response);
        return null;
    }

    return [
        'text'     => trim($data['text']),
        'language' => $data['language'] ?? 'en',
    ];
}

// ── Gemini 2.5 Flash Evaluation ───────────────────────────────

/**
 * Evaluate a candidate's answer using Gemini 2.5 Flash.
 *
 * @param  string $questionText   The interview question
 * @param  string $transcriptText The candidate's spoken answer (transcribed)
 * @param  string $difficulty     easy | medium | hard
 * @return array{
 *   overall_score: float,
 *   technical_score: float,
 *   communication_score: float,
 *   strengths: string,
 *   weaknesses: string,
 *   summary: string,
 *   model_used: string
 * }|null  null on failure
 */
function geminiEvaluate(string $questionText, string $transcriptText, string $difficulty = 'medium'): ?array
{
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if ($apiKey === '') {
        error_log('[Gemini] GEMINI_API_KEY is not set.');
        return null;
    }

    if (trim($transcriptText) === '') {
        error_log('[Gemini] Empty transcript — skipping evaluation.');
        return null;
    }

    $model = 'gemini-2.5-flash';

    $prompt = <<<PROMPT
You are an expert technical interviewer evaluating a candidate's spoken response.

**Interview Question:**
{$questionText}

**Candidate's Answer (transcribed):**
{$transcriptText}

**Difficulty Level:** {$difficulty}

Evaluate the candidate's answer and return ONLY a valid JSON object with exactly these keys:
- "overall_score": number 0-100 (weighted average)
- "technical_score": number 0-100 (accuracy, depth, correctness)
- "communication_score": number 0-100 (clarity, structure, fluency)
- "strengths": string (2-3 sentences on what was done well)
- "weaknesses": string (2-3 sentences on areas to improve)
- "summary": string (1-2 sentence overall summary)

Do NOT include any markdown, code fences, or extra text. Return ONLY the JSON object.
PROMPT;

    $payload = json_encode([
        'contents' => [
            [
                'parts' => [['text' => $prompt]],
            ],
        ],
        'generationConfig' => [
            'temperature'     => 0.3,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
        ],
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[Gemini] cURL error: ' . $curlError);
        return null;
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        error_log('[Gemini] API error (' . $httpCode . '): ' . $response);
        return null;
    }

    // Extract the text content from Gemini response
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        error_log('[Gemini] Empty response content.');
        return null;
    }

    // Strip markdown code fences if present
    $text = preg_replace('/^```(?:json)?\s*/im', '', $text);
    $text = preg_replace('/```\s*$/im', '', $text);
    $text = trim($text);

    $result = json_decode($text, true);
    if (!is_array($result)) {
        error_log('[Gemini] Failed to parse JSON: ' . $text);
        return null;
    }

    // Sanitise scores — clamp to 0–100
    foreach (['overall_score', 'technical_score', 'communication_score'] as $key) {
        if (isset($result[$key])) {
            $result[$key] = (float) max(0, min(100, $result[$key]));
        }
    }

    $result['model_used'] = $model;
    return $result;
}

// ── DB helpers for Phase 5 ────────────────────────────────────

/**
 * Save or update a transcript row.
 */
function saveTranscript(int $answerId, string $text, string $language = 'en'): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO transcripts (answer_id, transcript_text, language)
              VALUES (:aid, :txt, :lang)
         ON DUPLICATE KEY UPDATE
              transcript_text = VALUES(transcript_text),
              language        = VALUES(language)'
    );
    return $stmt->execute([':aid' => $answerId, ':txt' => $text, ':lang' => $language]);
}

/**
 * Save or update an ai_evaluations row.
 */
function saveEvaluation(int $answerId, array $eval): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO ai_evaluations
              (answer_id, overall_score, technical_score, communication_score,
               strengths, weaknesses, summary, model_used)
         VALUES (:aid, :os, :ts, :cs, :str, :weak, :sum, :mod)
         ON DUPLICATE KEY UPDATE
              overall_score       = VALUES(overall_score),
              technical_score     = VALUES(technical_score),
              communication_score = VALUES(communication_score),
              strengths           = VALUES(strengths),
              weaknesses          = VALUES(weaknesses),
              summary             = VALUES(summary),
              model_used          = VALUES(model_used),
              created_at          = CURRENT_TIMESTAMP'
    );
    return $stmt->execute([
        ':aid'  => $answerId,
        ':os'   => $eval['overall_score']       ?? null,
        ':ts'   => $eval['technical_score']     ?? null,
        ':cs'   => $eval['communication_score'] ?? null,
        ':str'  => $eval['strengths']           ?? null,
        ':weak' => $eval['weaknesses']          ?? null,
        ':sum'  => $eval['summary']             ?? null,
        ':mod'  => $eval['model_used']          ?? 'gemini-2.5-flash',
    ]);
}

/**
 * Upsert evaluation_jobs status.
 */
function upsertEvaluationJob(int $answerId, string $status, ?string $errorMsg = null): void
{
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO evaluation_jobs (answer_id, status, error_msg, started_at)
              VALUES (:aid, :st, :em, IF(:st2 IN ('transcribing','evaluating'), NOW(), NULL))
         ON DUPLICATE KEY UPDATE
              status       = VALUES(status),
              error_msg    = VALUES(error_msg),
              started_at   = COALESCE(started_at, IF(status IN ('transcribing','evaluating'), NOW(), NULL)),
              completed_at = IF(:st3 IN ('completed','failed'), NOW(), completed_at)"
    );
    $stmt->execute([
        ':aid' => $answerId,
        ':st'  => $status,
        ':em'  => $errorMsg,
        ':st2' => $status,
        ':st3' => $status,
    ]);
}

/**
 * Get transcript for an answer.
 *
 * @return array<string,mixed>|null
 */
function getTranscriptForAnswer(int $answerId): ?array
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM transcripts WHERE answer_id = :aid LIMIT 1');
    $stmt->execute([':aid' => $answerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get AI evaluation for an answer.
 *
 * @return array<string,mixed>|null
 */
function getEvaluationForAnswer(int $answerId): ?array
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM ai_evaluations WHERE answer_id = :aid LIMIT 1');
    $stmt->execute([':aid' => $answerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get evaluation job status for an answer.
 *
 * @return array<string,mixed>|null
 */
function getEvaluationJob(int $answerId): ?array
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM evaluation_jobs WHERE answer_id = :aid LIMIT 1');
    $stmt->execute([':aid' => $answerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get full evaluation summary for an attempt (all answers + transcripts + scores).
 *
 * @return array[]
 */
function getEvaluationSummaryForAttempt(int $attemptId): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT
           a.id            AS answer_id,
           a.audio_path,
           a.response_time,
           q.question_text,
           q.difficulty,
           iq.sequence_order,
           t.transcript_text,
           t.language,
           ae.overall_score,
           ae.technical_score,
           ae.communication_score,
           ae.strengths,
           ae.weaknesses,
           ae.summary      AS eval_summary,
           ae.model_used,
           ej.status       AS job_status
         FROM answers a
         JOIN questions q  ON q.id = a.question_id
         LEFT JOIN attempts att ON att.id = a.attempt_id
         LEFT JOIN interview_questions iq
                ON iq.interview_id = att.interview_id
               AND iq.question_id  = a.question_id
         LEFT JOIN transcripts    t  ON t.answer_id  = a.id
         LEFT JOIN ai_evaluations ae ON ae.answer_id = a.id
         LEFT JOIN evaluation_jobs ej ON ej.answer_id = a.id
        WHERE a.attempt_id = :aid
        ORDER BY iq.sequence_order ASC, a.id ASC"
    );
    $stmt->execute([':aid' => $attemptId]);
    return $stmt->fetchAll();
}
