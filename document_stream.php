<?php
/**
 * document_stream.php — HMAC-Authenticated Document Streamer
 * AI Interview Assessment Platform — Phase 4.5 (Fixed)
 *
 * Stateless HMAC signature — no DB token lookup, no race conditions.
 *
 * URL params: ?id=DOCID&uid=USERID&ts=TIMESTAMP&sig=HMAC_SHA256
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/documents.php';

// Shared signing secret (hardcoded fallback; override via .env STREAM_SECRET=...)
const STREAM_SECRET = 'hireiq_secure_stream_phase45_2026';

// ── 1. Session guard ──────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in again.';
    exit;
}

$sessionUserId = (int) $_SESSION['user_id'];

// ── 2. Read + validate inputs ─────────────────────────────────
$docId  = (int)  ($_GET['id']  ?? 0);
$urlUid = (int)  ($_GET['uid'] ?? 0);
$ts     = (int)  ($_GET['ts']  ?? 0);
$sig    = trim(  $_GET['sig']  ?? '');

if ($docId <= 0 || $urlUid <= 0 || $ts <= 0 || strlen($sig) < 32) {
    http_response_code(400);
    echo 'Invalid stream parameters.';
    exit;
}

// ── 3. URL user must match logged-in session user ─────────────
if ($sessionUserId !== $urlUid) {
    http_response_code(403);
    echo 'Access denied: user mismatch.';
    exit;
}

// ── 4. Signature freshness check (max 10 minutes) ─────────────
if (time() - $ts > 600) {
    http_response_code(403);
    echo 'Stream URL expired. Please reload the viewer page.';
    exit;
}

// ── 5. Verify HMAC-SHA256 signature ───────────────────────────
$payload  = "{$docId}:{$urlUid}:{$ts}";
$expected = hash_hmac('sha256', $payload, STREAM_SECRET);

if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    echo 'Invalid signature. Access denied.';
    exit;
}

// ── 6. Fetch document record ───────────────────────────────────
$doc = getDocumentById($docId);
if (!$doc) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

// ── 7. Stream file with security headers ──────────────────────
streamDocument($doc);
