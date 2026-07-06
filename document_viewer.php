<?php
/**
 * document_viewer.php — Enterprise Secure Document Viewer
 * AI Interview Assessment Platform — Phase 4.5 (Rewrite)
 *
 * Strategy (Udemy-style):
 *  • PDF rendered as canvas pixels via PDF.js (no iframe, no PDF browser toolbar)
 *  • Text layer disabled — content exists as pixels, harder to extract
 *  • Watermark burned directly into each page canvas
 *  • Acknowledgment gate before first view
 *  • Progress tracking: current page, pages read, %, duration, last viewed
 *  • Working Close button (simple href + sendBeacon for analytics)
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/documents.php';

// ── Guards ────────────────────────────────────────────────────
requireAuth();

$userId   = (int) ($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role']  ?? '';
$userName = $_SESSION['user_name']  ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';

// Fetch email if not cached
if (empty($userEmail)) {
    try {
        $stmt = getDB()->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$userId]);
        $userEmail = (string)($stmt->fetchColumn() ?: '');
        $_SESSION['user_email'] = $userEmail;
    } catch (Throwable) {}
}

// ── Document lookup ───────────────────────────────────────────
$docId = (int) ($_GET['id'] ?? 0);
if ($docId <= 0) { http_response_code(400); die('Invalid document ID.'); }
$doc = getDocumentById($docId);
if (!$doc) { http_response_code(404); die('Document not found.'); }

// ── Check acknowledgment ──────────────────────────────────────
$acknowledged = hasUserAcknowledged($docId, $userId);

// ── HMAC-signed stream URL (stateless, no DB token) ───────────
const STREAM_SECRET_V = 'hireiq_secure_stream_phase45_2026';
$streamTs  = time();
$streamSig = hash_hmac('sha256', "{$docId}:{$userId}:{$streamTs}", STREAM_SECRET_V);
$streamUrl = BASE_URL . '/document_stream.php'
           . '?id='  . $docId
           . '&uid=' . $userId
           . '&ts='  . $streamTs
           . '&sig=' . $streamSig;

// ── Start/resume audit session ────────────────────────────────
$logId = startDocumentSession($docId, $userId, $streamSig);

// Mark as read for candidates
if ($userRole === 'candidate') {
    markDocumentAsRead($docId, $userId);
}

// ── Progress (previous session) ───────────────────────────────
$progress    = getDocumentProgress($docId, $userId);
$lastPage    = (int) ($progress['current_page'] ?? 1);
$lastViewed  = $progress['view_start'] ?? null;

// ── Back URL ──────────────────────────────────────────────────
$backUrl = BASE_URL . '/' . ($userRole === 'super_admin' ? 'admin' : 'candidate') . '/documents.php';

// ── Secure JS data ────────────────────────────────────────────
$jsData = json_encode([
    'name'      => $userName,
    'email'     => $userEmail,
    'id'        => $userId,
    'logId'     => $logId,
    'docId'     => $docId,
    'streamTs'  => $streamTs,
    'viewStart' => time(),
    'lastPage'  => $lastPage,
    'baseUrl'   => BASE_URL,
    'backUrl'   => $backUrl,
]);

$displayDate = date('d M Y');
$displayTime = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($doc['title']) ?> | HireIQ Secure Viewer</title>
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; overflow: hidden; }

    :root {
      --navy:    #0f172a;
      --navy2:   #1e293b;
      --blue:    #2563eb;
      --red:     #dc2626;
      --nav-h:   52px;
      --prog-h:  40px;
      --bar-h:   30px;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #1e293b;
      color: #e2e8f0;
      user-select: none;
      -webkit-user-select: none;
    }

    /* ── Acknowledgment Gate ── */
    #ack-gate {
      position: fixed; inset: 0; z-index: 9999;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);
      display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    #ack-gate.hidden { display: none; }
    .ack-card {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 16px;
      padding: 2.5rem 2rem;
      max-width: 580px; width: 100%;
      box-shadow: 0 25px 60px rgba(0,0,0,.6);
    }
    .ack-badge {
      display: inline-flex; align-items: center; gap: .4rem;
      background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.35);
      color: #f87171; padding: .35rem .75rem; border-radius: 99px;
      font-size: .72rem; font-weight: 700; letter-spacing: .5px; margin-bottom: 1.25rem;
    }
    .ack-title {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 1.35rem; font-weight: 700; color: #f1f5f9;
      margin-bottom: .6rem;
    }
    .ack-statement {
      background: rgba(239,68,68,.08);
      border-left: 3px solid #ef4444;
      border-radius: 0 8px 8px 0;
      padding: 1rem 1.1rem;
      font-size: .85rem; color: #cbd5e1; line-height: 1.65;
      margin: 1rem 0 1.25rem;
      font-style: italic;
    }
    .ack-list {
      list-style: none; padding: 0; margin: 0 0 1.5rem;
      display: flex; flex-direction: column; gap: .45rem;
    }
    .ack-list li {
      display: flex; align-items: flex-start; gap: .5rem;
      font-size: .82rem; color: #94a3b8;
    }
    .ack-list li::before { content: '⚠'; color: #f59e0b; flex-shrink: 0; }
    .ack-btn {
      width: 100%;
      padding: .85rem 1.5rem;
      background: var(--blue);
      color: #fff; font-size: .9rem; font-weight: 600;
      border: none; border-radius: 10px; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
      transition: background .15s, transform .1s;
      margin-bottom: .75rem;
    }
    .ack-btn:hover  { background: #1d4ed8; }
    .ack-btn:active { transform: scale(.98); }
    .ack-btn:disabled { background: #475569; cursor: not-allowed; }
    .ack-cancel {
      display: block; text-align: center;
      font-size: .8rem; color: #64748b;
      text-decoration: none;
      padding: .35rem;
      transition: color .15s;
    }
    .ack-cancel:hover { color: #94a3b8; }

    /* ── Top Navbar ── */
    .v-nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 1100;
      height: var(--nav-h);
      background: var(--navy);
      border-bottom: 1px solid #1e293b;
      display: flex; align-items: center;
      padding: 0 1rem; gap: 1rem;
    }
    .v-nav__brand {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 1.1rem; font-weight: 700; color: #f1f5f9;
      text-decoration: none; flex-shrink: 0;
    }
    .v-nav__brand span { color: var(--blue); }
    .v-nav__title {
      flex: 1; min-width: 0;
      font-size: .82rem; color: #94a3b8;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      display: flex; align-items: center; gap: .4rem;
    }
    .v-nav__actions {
      display: flex; align-items: center; gap: .5rem; flex-shrink: 0;
    }
    .v-btn {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .4rem .85rem;
      border-radius: 7px; font-size: .8rem; font-weight: 600;
      cursor: pointer; text-decoration: none;
      border: 1px solid transparent;
      transition: background .15s, color .15s;
    }
    .v-btn--ghost {
      background: transparent;
      border-color: #334155; color: #94a3b8;
    }
    .v-btn--ghost:hover { background: #1e293b; color: #e2e8f0; }
    .v-btn--blue {
      background: var(--blue); color: #fff; border-color: var(--blue);
    }
    .v-btn--blue:hover { background: #1d4ed8; }
    .v-btn--red {
      background: rgba(220,38,38,.12);
      color: #f87171; border-color: rgba(220,38,38,.35);
    }
    .v-btn--red:hover { background: rgba(220,38,38,.22); color: #fca5a5; }
    .secure-chip {
      display: flex; align-items: center; gap: .35rem;
      font-size: .72rem; font-weight: 600; color: #4ade80;
      padding: .3rem .65rem;
      background: rgba(74,222,128,.08);
      border: 1px solid rgba(74,222,128,.2);
      border-radius: 99px;
    }

    /* ── Progress Bar ── */
    .v-progress {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; z-index: 1000;
      height: var(--prog-h);
      background: #0f172a;
      border-bottom: 1px solid #1e293b;
      display: flex; align-items: center;
      padding: 0 1rem; gap: 1.5rem;
    }
    .v-prog-stat {
      display: flex; align-items: center; gap: .4rem;
      font-size: .72rem; color: #64748b;
      white-space: nowrap;
    }
    .v-prog-stat strong { color: #cbd5e1; font-weight: 600; }
    .v-prog-bar-wrap {
      flex: 1; min-width: 80px; max-width: 200px;
      height: 4px; background: #1e293b; border-radius: 99px; overflow: hidden;
    }
    .v-prog-bar-fill {
      height: 100%; background: var(--blue);
      border-radius: 99px; width: 0%;
      transition: width .3s ease;
    }

    /* ── Scrollable PDF Container ── */
    #pdf-scroll {
      position: fixed;
      top: calc(var(--nav-h) + var(--prog-h));
      left: 0; right: 0;
      bottom: var(--bar-h);
      overflow-y: scroll;
      overflow-x: hidden;
      background: #334155;
      -webkit-overflow-scrolling: touch;
    }
    #pdf-scroll::-webkit-scrollbar { width: 10px; }
    #pdf-scroll::-webkit-scrollbar-track { background: #1e293b; }
    #pdf-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 5px; }
    #pdf-scroll::-webkit-scrollbar-thumb:hover { background: #475569; }

    #pdf-pages {
      display: flex; flex-direction: column; align-items: center;
      padding: 1.5rem 0 2rem;
      gap: 12px;
      min-height: 100%;
    }

    .pdf-page-wrap {
      position: relative;
      box-shadow: 0 4px 20px rgba(0,0,0,.4);
      border-radius: 2px;
    }
    .pdf-page-wrap canvas {
      display: block;
      max-width: 100%;
    }
    .pdf-page-label {
      position: absolute; top: -22px; left: 0;
      font-size: .68rem; color: #64748b; font-weight: 500;
      letter-spacing: .3px;
    }

    /* PDF loading skeleton */
    #pdf-loading {
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: 1rem;
      padding: 4rem 1rem;
      color: #64748b;
    }
    .pdf-spinner {
      width: 48px; height: 48px;
      border: 3px solid #334155;
      border-top-color: var(--blue);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .pdf-load-text { font-size: .85rem; }

    /* ── Fixed watermark canvas ── */
    #wm-canvas {
      position: fixed;
      top: calc(var(--nav-h) + var(--prog-h));
      left: 0; right: 0;
      bottom: var(--bar-h);
      width: 100%;
      height: calc(100% - var(--nav-h) - var(--prog-h) - var(--bar-h));
      z-index: 800;
      pointer-events: none;
    }

    /* ── CONFIDENTIAL ticker ── */
    @keyframes ticker {
      0%   { transform: translateX(110vw); }
      100% { transform: translateX(-100%); }
    }
    #conf-ticker {
      position: fixed;
      bottom: var(--bar-h); left: 0; right: 0;
      height: 26px;
      background: rgba(185,28,28,.88);
      backdrop-filter: blur(4px);
      display: flex; align-items: center;
      overflow: hidden; z-index: 900; pointer-events: none;
    }
    #conf-ticker-text {
      white-space: nowrap;
      font-size: .7rem; font-weight: 700;
      color: #fff; letter-spacing: .8px;
      animation: ticker 25s linear infinite;
      padding-right: 100px;
    }

    /* ── Bottom Status Bar ── */
    .v-status {
      position: fixed; bottom: 0; left: 0; right: 0;
      height: var(--bar-h); z-index: 1100;
      background: var(--navy);
      border-top: 1px solid #1e293b;
      display: flex; align-items: center;
      padding: 0 1rem; gap: 1.5rem;
      font-size: .68rem; color: #475569;
    }
    .v-status span { display: flex; align-items: center; gap: .3rem; }
    .v-dot { width: 6px; height: 6px; border-radius: 50%; background: #22c55e; }
    .v-dot--warn { background: #f59e0b; }
    .v-dot--danger { background: #ef4444; }
    .v-status-right { margin-left: auto; display: flex; align-items: center; gap: .3rem; }

    /* ── Overlays ── */
    .overlay {
      display: none; position: fixed; inset: 0; z-index: 2000;
      align-items: center; justify-content: center; flex-direction: column;
      text-align: center; gap: 1rem; padding: 2rem;
      background: rgba(15,23,42,.97);
    }
    .overlay.active { display: flex; }
    #screenshot-overlay {
      background: #000000 !important;
      color: #ef4444;
      z-index: 9995;
    }
    .ov-icon { font-size: 3rem; }
    .ov-title { font-family: 'Space Grotesk', sans-serif; font-size: 1.3rem; font-weight: 700; color: #f1f5f9; }
    .ov-sub { font-size: .85rem; color: #64748b; max-width: 380px; line-height: 1.6; }
    .ov-btn {
      padding: .65rem 1.5rem; border-radius: 8px;
      background: var(--blue); color: #fff;
      font-size: .875rem; font-weight: 600;
      border: none; cursor: pointer; margin-top: .5rem;
    }
    .ov-btn:hover { background: #1d4ed8; }

    canvas, .pdf-page-wrap {
      user-select: none;
      -webkit-user-select: none;
      -webkit-user-drag: none;
      user-drag: none;
    }

    /* ── Toast ── */
    #vt {
      position: fixed; bottom: 44px; left: 50%; transform: translateX(-50%);
      background: rgba(15,23,42,.95); border: 1px solid #334155;
      color: #f87171; padding: .55rem 1.2rem; border-radius: 8px;
      font-size: .8rem; font-weight: 500; z-index: 3000;
      opacity: 0; transition: opacity .2s; pointer-events: none;
      white-space: nowrap;
    }
    #vt.show { opacity: 1; }

    /* ── Error state ── */
    #pdf-error {
      display: none; flex-direction: column; align-items: center;
      justify-content: center; gap: 1rem; padding: 3rem;
      color: #94a3b8; text-align: center;
    }
    #pdf-error.show { display: flex; }
    #pdf-error i { font-size: 3rem; color: #ef4444; }
  </style>
</head>
<body>

<!-- ════════════════════════════════════════════════════════════
     ACKNOWLEDGMENT GATE
     Shown before first view. Hides once user clicks "I Understand".
════════════════════════════════════════════════════════════ -->
<div id="ack-gate" class="<?= $acknowledged ? 'hidden' : '' ?>">
  <div class="ack-card">
    <div class="ack-badge"><i class="bi bi-shield-lock-fill"></i> CONFIDENTIAL DOCUMENT</div>
    <h1 class="ack-title">Document Access Agreement</h1>
    <p style="font-size:.84rem;color:#64748b;margin-bottom:.25rem;">
      Before opening <strong style="color:#cbd5e1;"><?= e($doc['title']) ?></strong>,
      please read and acknowledge the following:
    </p>

    <div class="ack-statement">
      "I understand this document is confidential. Unauthorized sharing, photography,
      screenshots, downloads, or redistribution are prohibited and may be audited."
    </div>

    <ul class="ack-list">
      <li>Your access is logged with IP address, device fingerprint, and exact timestamp</li>
      <li>Every page you view is tracked and reported to administrators</li>
      <li>A visible watermark with your name, email, and session time is overlaid on all pages</li>
      <li>Keyboard shortcuts (Ctrl+C, Ctrl+P, Ctrl+S) and right-click are disabled</li>
      <li>Screenshots cannot be prevented by this system — however, all access is traceable</li>
    </ul>

    <button id="ack-btn" class="ack-btn" onclick="handleAck()">
      <i class="bi bi-check-circle-fill"></i>
      I Understand — Open Document
    </button>
    <a href="<?= e($backUrl) ?>" class="ack-cancel">
      <i class="bi bi-arrow-left"></i> Cancel &amp; Go Back
    </a>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     VIEWER SHELL (shown immediately if acknowledged, else after ack)
════════════════════════════════════════════════════════════ -->
<div id="viewer-shell" style="<?= $acknowledged ? '' : 'display:none' ?>">

  <!-- ── Navbar ── -->
  <nav class="v-nav">
    <a href="<?= e($backUrl) ?>" class="v-nav__brand">Hire<span>IQ</span></a>
    <div class="v-nav__title">
      <i class="bi bi-file-earmark-lock" style="color:#475569;font-size:.85rem;"></i>
      <?= e($doc['title']) ?>
    </div>
    <div class="v-nav__actions">
      <span class="secure-chip"><i class="bi bi-shield-fill-check"></i> Secure View</span>
      <button class="v-btn v-btn--ghost" onclick="enterFullscreen()" title="Fullscreen">
        <i class="bi bi-fullscreen"></i> <span class="d-none d-sm-inline">Fullscreen</span>
      </button>
      <!-- ✅ CLOSE BUTTON — simple anchor, sendBeacon for analytics, no blocking -->
      <a id="close-btn" href="<?= e($backUrl) ?>" class="v-btn v-btn--red" onclick="handleClose(event)">
        <i class="bi bi-x-lg"></i> Close
      </a>
    </div>
  </nav>

  <!-- ── Progress Bar ── -->
  <div class="v-progress">
    <div class="v-prog-stat">
      <i class="bi bi-file-text" style="color:#2563eb;font-size:.8rem;"></i>
      Current Page: <strong id="prog-cur">1</strong> / <strong id="prog-total">—</strong>
    </div>
    <div class="v-prog-stat">
      <i class="bi bi-book" style="color:#0ea5e9;font-size:.8rem;"></i>
      Total Pages Read: <strong id="prog-read">0</strong>
    </div>
    <div class="v-prog-bar-wrap">
      <div class="v-prog-bar-fill" id="prog-bar"></div>
    </div>
    <div class="v-prog-stat">
      <i class="bi bi-percent" style="color:#059669;font-size:.8rem;"></i>
      Percentage Completed: <strong id="prog-pct">0</strong>%
    </div>
    <div class="v-prog-stat">
      <i class="bi bi-clock" style="color:#7c3aed;font-size:.8rem;"></i>
      Reading Duration: <strong id="prog-dur">0:00</strong>
    </div>
    <div class="v-prog-stat" style="margin-left:auto;">
      <i class="bi bi-calendar3" style="font-size:.75rem;color:#f59e0b;"></i>
      Last Viewed: <strong id="prog-last-ts"><?= $lastViewed ? date('d M Y H:i:s', strtotime($lastViewed)) : 'First Time' ?></strong>
    </div>
  </div>

  <!-- ── Scrollable PDF Canvas Area ── -->
  <div id="pdf-scroll">
    <div id="pdf-pages">
      <div id="pdf-loading">
        <div class="pdf-spinner"></div>
        <div class="pdf-load-text">Loading secure document…</div>
      </div>
      <div id="pdf-error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div style="font-size:1rem;font-weight:600;color:#f87171;">Failed to load document</div>
        <div style="font-size:.8rem;color:#64748b;max-width:380px;">
          Could not stream the document. Please check your connection and
          <a href="javascript:location.reload()" style="color:#60a5fa;">reload the page</a>.
        </div>
      </div>
    </div>
  </div>

  <!-- ── Fixed Overlay Watermark (on top of PDF canvas area) ── -->
  <canvas id="wm-canvas" aria-hidden="true"></canvas>

  <!-- ── CONFIDENTIAL Ticker ── -->
  <div id="conf-ticker" aria-hidden="true">
    <span id="conf-ticker-text">
      ⚠ CONFIDENTIAL &nbsp;|&nbsp; <?= e($userName) ?> &nbsp;|&nbsp;
      <?= e($userEmail) ?> &nbsp;|&nbsp; ID: <?= $userId ?> &nbsp;|&nbsp;
      <?= $displayDate ?> <?= $displayTime ?> &nbsp;|&nbsp;
      HireIQ SECURE DOCUMENT — UNAUTHORISED COPYING OR DISTRIBUTION IS STRICTLY PROHIBITED
      &nbsp;|&nbsp; ⚠ CONFIDENTIAL &nbsp;|&nbsp; <?= e($userName) ?> &nbsp;|&nbsp;
      <?= e($userEmail) ?>
    </span>
  </div>

  <!-- ── Security Overlays ── -->
  <div id="screenshot-overlay" class="overlay">
    <div class="ov-icon" style="color:#ef4444;">🔒</div>
    <div class="ov-title" style="color:#f87171;">Capture Prevention Active</div>
    <div class="ov-sub" style="color:#94a3b8;max-width:440px;">Taking screenshots, screen recordings, or losing window focus blurs/hides confidential content. Focus back on this window to resume viewing.</div>
  </div>

  <div id="blur-overlay" class="overlay">
    <div class="ov-icon">👁️</div>
    <div class="ov-title">Document Hidden</div>
    <div class="ov-sub">This document is hidden when you switch tabs or windows. This event has been logged.</div>
    <button class="ov-btn" onclick="document.getElementById('blur-overlay').classList.remove('active');setStatus('ok','Viewing Securely')">
      Resume Viewing
    </button>
  </div>

  <div id="devtools-overlay" class="overlay">
    <div class="ov-icon" style="color:#a78bfa;">⚠️</div>
    <div class="ov-title" style="color:#c4b5fd;">Developer Tools Detected</div>
    <div class="ov-sub" style="color:#7c3aed;">Developer tools are not permitted during secure viewing. This activity has been reported.</div>
  </div>

  <div id="session-overlay" class="overlay">
    <div class="ov-icon">🔒</div>
    <div class="ov-title">Session Expired</div>
    <div class="ov-sub">Your viewing session has expired for security reasons.</div>
    <a href="<?= e($backUrl) ?>" class="ov-btn">← Return to Documents</a>
  </div>

  <!-- ── Status Bar ── -->
  <div class="v-status">
    <span><span class="v-dot" id="status-dot"></span> <span id="status-text">Viewing Securely</span></span>
    <span><i class="bi bi-person"></i> <?= e($userName) ?></span>
    <span><i class="bi bi-clock"></i> <span id="view-timer">0:00</span></span>
    <span><i class="bi bi-exclamation-triangle"></i> Violations: <span id="violation-count">0</span></span>
    <div class="v-status-right">
      <i class="bi bi-lock-fill" style="color:#22c55e;font-size:.75rem;"></i>
      End-to-end protected
    </div>
  </div>

</div><!-- #viewer-shell -->

<!-- ── Toast ── -->
<div id="vt"><span id="vtm"></span></div>

<!-- ════════════════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════════════ -->

<!-- PDF.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>
/* ── Viewer config (from PHP) ── */
window.__VIEWER__ = <?= $jsData ?>;
const V = window.__VIEWER__;

/* ── PDF.js worker ── */
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

/* ════════════════════════════════════════════════════════════
   ACKNOWLEDGMENT
════════════════════════════════════════════════════════════ */
window.handleAck = async function () {
  const btn = document.getElementById('ack-btn');
  if (!btn) return;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Recording…';

  try {
    const r = await fetch(V.baseUrl + '/api/acknowledge.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'doc_id=' + V.docId,
    });
    const data = await r.json();
    if (data.success) {
      document.getElementById('ack-gate').classList.add('hidden');
      document.getElementById('viewer-shell').style.display = '';
      initViewer(); // start PDF.js rendering
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-x-circle"></i> Error — Try Again';
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-x-circle"></i> Network Error — Try Again';
  }
};

/* ════════════════════════════════════════════════════════════
   CLOSE BUTTON — sendBeacon (non-blocking) then navigate
════════════════════════════════════════════════════════════ */
window.handleClose = function (event) {
  if (event) event.preventDefault();
  // Exit fullscreen first to avoid page freezing in fullscreen
  if (document.fullscreenElement || document.webkitFullscreenElement) {
    if (document.exitFullscreen) document.exitFullscreen().catch(() => {});
    else if (document.webkitExitFullscreen) document.webkitExitFullscreen().catch(() => {});
  }
  const elapsed = Math.round((Date.now() - (V.viewStart * 1000)) / 1000);
  const data = new URLSearchParams({
    action:   'close',
    doc_id:   V.docId,
    log_id:   V.logId,
    duration: elapsed,
  });
  navigator.sendBeacon(V.baseUrl + '/api/log_activity.php', data);
  window.location.href = V.backUrl;
};

/* ════════════════════════════════════════════════════════════
   STATUS HELPERS
════════════════════════════════════════════════════════════ */
const elDot  = document.getElementById('status-dot');
const elText = document.getElementById('status-text');
function setStatus(level, text) {
  if (!elDot) return;
  elDot.className = 'v-dot';
  if (level === 'warn')   elDot.classList.add('v-dot--warn');
  if (level === 'danger') elDot.classList.add('v-dot--danger');
  if (elText) elText.textContent = text;
}

/* ════════════════════════════════════════════════════════════
   TOAST
════════════════════════════════════════════════════════════ */
let toastT = null;
function showToast(msg) {
  const el = document.getElementById('vt');
  const em = document.getElementById('vtm');
  if (!el || !em) return;
  em.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastT);
  toastT = setTimeout(() => el.classList.remove('show'), 3000);
}

/* ════════════════════════════════════════════════════════════
   VIOLATION LOGGING
════════════════════════════════════════════════════════════ */
let violations = 0;
function recordViolation(type, detail, msg) {
  violations++;
  const vc = document.getElementById('violation-count');
  if (vc) vc.textContent = violations;
  showToast(msg || 'Action blocked — this event has been logged.');
  fetch(V.baseUrl + '/api/log_activity.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams({ action:'violation', doc_id:V.docId, log_id:V.logId, event_type:type, event_detail:detail||'' }),
  }).catch(() => {});
}

/* ════════════════════════════════════════════════════════════
   SECURITY LAYER & SCREENSHOT BLACKOUT (UDEMY STRATEGY)
════════════════════════════════════════════════════════════ */
// Right click & Copy block
document.addEventListener('contextmenu', e => { e.preventDefault(); recordViolation('right_click','contextmenu','Right-click is disabled.'); return false; }, true);
document.addEventListener('copy',  e => { e.preventDefault(); recordViolation('copy_attempt','copy event','Copying is prohibited.'); }, true);
document.addEventListener('cut',   e => { e.preventDefault(); recordViolation('copy_attempt','cut event','Cutting is prohibited.'); }, true);
document.addEventListener('paste', e => { e.preventDefault(); }, true);

// Window blur blackout (triggers on Alt+Tab, Win+Shift+S, Snipping Tool, or when OS overlay takes focus)
function enableBlackout() {
  document.getElementById('screenshot-overlay')?.classList.add('active');
  setStatus('danger', 'Screen Protected');
}
function disableBlackout() {
  document.getElementById('screenshot-overlay')?.classList.remove('active');
  setStatus('ok', 'Viewing Securely');
}
window.addEventListener('blur', () => {
  enableBlackout();
  recordViolation('screenshot_suspicion', 'window_blur', 'Screenshot protection active.');
});
window.addEventListener('focus', () => {
  disableBlackout();
});

document.addEventListener('keydown', function (e) {
  const k = e.key.toLowerCase();
  const ctrl = e.ctrlKey || e.metaKey;
  const shift = e.shiftKey;

  if (e.key === 'F12') { e.preventDefault(); e.stopPropagation(); recordViolation('devtools_open','F12','DevTools are not permitted.'); return false; }
  if (e.key === 'PrintScreen') { 
    enableBlackout();
    recordViolation('screenshot_suspicion','PrintScreen','Screenshot key detected.'); 
    setTimeout(disableBlackout, 1500);
  }

  if (ctrl) {
    const blocked = { c:'copy_attempt', a:'keyboard_shortcut', s:'keyboard_shortcut', p:'print_attempt', u:'keyboard_shortcut' };
    if (blocked[k]) { e.preventDefault(); e.stopPropagation(); recordViolation(blocked[k], 'Ctrl+'+k.toUpperCase(), 'Action blocked.'); return false; }
    if (shift && ['i','j','c'].includes(k)) { e.preventDefault(); e.stopPropagation(); recordViolation('devtools_open','Ctrl+Shift+'+k,'DevTools shortcut blocked.'); return false; }
  }
}, true);

window.print = () => { recordViolation('print_attempt','window.print','Printing is prohibited.'); return false; };
window.addEventListener('beforeprint', () => { recordViolation('print_attempt','beforeprint','Print event blocked.'); });

// Tab switch / visibility
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    document.getElementById('blur-overlay')?.classList.add('active');
    setStatus('warn', 'Document hidden');
    recordViolation('tab_switch','visibility hidden','Tab switch detected.');
  } else {
    document.getElementById('blur-overlay')?.classList.remove('active');
    setStatus('ok', 'Viewing Securely');
  }
});

// DevTools detection
let devtoolsShown = false;
setInterval(() => {
  const dw = window.outerWidth  - window.innerWidth;
  const dh = window.outerHeight - window.innerHeight;
  if (dw > 160 || dh > 160) {
    if (!devtoolsShown) { devtoolsShown = true; recordViolation('devtools_open','size-delta','DevTools detected.'); }
    document.getElementById('devtools-overlay')?.classList.add('active');
    setStatus('danger','DevTools Detected');
  } else {
    devtoolsShown = false;
    document.getElementById('devtools-overlay')?.classList.remove('active');
  }
}, 500);

// Screen share
if (navigator.mediaDevices?.getDisplayMedia) {
  const _orig = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
  navigator.mediaDevices.getDisplayMedia = function(c) { recordViolation('screen_share_detected','getDisplayMedia','Screen sharing detected.'); return _orig(c); };
}

/* ════════════════════════════════════════════════════════════
   FULLSCREEN
════════════════════════════════════════════════════════════ */
window.enterFullscreen = function () {
  const el = document.documentElement;
  if (el.requestFullscreen) el.requestFullscreen();
  else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
};

/* ════════════════════════════════════════════════════════════
   WATERMARK CANVAS (fixed overlay on PDF area)
════════════════════════════════════════════════════════════ */
const wmCanvas = document.getElementById('wm-canvas');
let wmTimer = null;
function drawOverlayWatermark() {
  if (!wmCanvas) return;
  const ctx = wmCanvas.getContext('2d');
  wmCanvas.width  = wmCanvas.offsetWidth  || window.innerWidth;
  wmCanvas.height = wmCanvas.offsetHeight || window.innerHeight;
  ctx.clearRect(0, 0, wmCanvas.width, wmCanvas.height);

  const now      = new Date();
  const dateStr  = now.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  const timeStr  = now.toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit', second:'2-digit' });

  const tiles = [
    { t:'CONFIDENTIAL', f:'700 16px "Space Grotesk",sans-serif', a:0.22, c:'220,38,38' },
    { t:V.name||'User', f:'600 13px "Inter",sans-serif',         a:0.20, c:'15,23,42'  },
    { t:V.email||'',    f:'400 11px "Inter",sans-serif',         a:0.18, c:'15,23,42'  },
    { t:'ID: '+V.id,    f:'400 11px "Inter",sans-serif',         a:0.16, c:'15,23,42'  },
    { t:dateStr+' '+timeStr, f:'500 11px "Inter",sans-serif',    a:0.18, c:'15,23,42'  },
    { t:'HireIQ Secure Viewer', f:'700 12px "Space Grotesk",sans-serif', a:0.20, c:'37,99,235' },
  ].filter(x => x.t);

  ctx.save();
  ctx.rotate(-Math.PI / 6);
  const lineH = 18, blockW = 280, stepX = blockW + 80;
  const blockH = tiles.length * lineH + 16, stepY = blockH + 60;
  const nx = Math.ceil(wmCanvas.width  * 2 / stepX) + 3;
  const ny = Math.ceil(wmCanvas.height * 2 / stepY) + 3;
  const ox = -wmCanvas.width  * 0.5;
  const oy = -wmCanvas.height * 0.4;

  for (let ty = 0; ty < ny; ty++) {
    for (let tx = 0; tx < nx; tx++) {
      const bx = ox + tx * stepX;
      const by = oy + ty * stepY;
      tiles.forEach((tile, i) => {
        ctx.font      = tile.f;
        ctx.shadowColor = 'rgba(255,255,255,0.65)';
        ctx.shadowBlur  = 4;
        ctx.fillStyle   = `rgba(${tile.c},${tile.a})`;
        ctx.textAlign   = 'left';
        ctx.fillText(tile.t, bx, by + i * lineH);
        ctx.shadowBlur  = 0;
        ctx.shadowColor = 'transparent';
      });
    }
  }
  ctx.restore();

  // Update ticker with live time
  const ticker = document.getElementById('conf-ticker-text');
  if (ticker) {
    ticker.innerHTML = `⚠ CONFIDENTIAL &nbsp;|&nbsp; ${V.name||''} &nbsp;|&nbsp; ${V.email||''} &nbsp;|&nbsp; ID:${V.id} &nbsp;|&nbsp; ${dateStr} ${timeStr} &nbsp;|&nbsp; HireIQ SECURE — UNAUTHORISED COPY PROHIBITED &nbsp;|&nbsp; ⚠ ${V.name||''} &nbsp;|&nbsp; ${V.email||''}`;
  }

  wmTimer = setTimeout(drawOverlayWatermark, 1000);
}
window.addEventListener('resize', () => { clearTimeout(wmTimer); drawOverlayWatermark(); });

/* ════════════════════════════════════════════════════════════
   HEARTBEAT
════════════════════════════════════════════════════════════ */
let violations_copy = 0, violations_print = 0, violations_tab = 0, violations_fs = 0;
setInterval(() => {
  const elapsed = Math.round((Date.now() - (V.viewStart * 1000)) / 1000);
  navigator.sendBeacon(V.baseUrl + '/api/log_activity.php',
    new URLSearchParams({ action:'heartbeat', doc_id:V.docId, log_id:V.logId, duration:elapsed })
  );
}, 30000);

/* ════════════════════════════════════════════════════════════
   VIEW TIMER
════════════════════════════════════════════════════════════ */
const timerEl = document.getElementById('view-timer');
setInterval(() => {
  if (!timerEl) return;
  const s = Math.round((Date.now() - (V.viewStart * 1000)) / 1000);
  timerEl.textContent = Math.floor(s/60) + ':' + String(s%60).padStart(2,'0');
}, 1000);

/* ════════════════════════════════════════════════════════════
   PDF.js CANVAS RENDERER
════════════════════════════════════════════════════════════ */
let totalPages  = 0;
let pagesViewed = new Set(); // track which pages were scrolled to
let currentPage = 1;

function getViewerWidth() {
  const scroll = document.getElementById('pdf-scroll');
  return scroll ? Math.min(scroll.clientWidth - 32, 900) : 800;
}

/**
 * Burn watermark directly INTO a PDF page canvas.
 * This makes the watermark part of the rendered pixels (Udemy style).
 */
function burnWatermark(ctx, w, h, pageNum) {
  const now     = new Date();
  const dateStr = now.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  const timeStr = now.toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit' });

  ctx.save();
  ctx.globalAlpha = 0.18;
  ctx.translate(w / 2, h / 2);
  ctx.rotate(-Math.PI / 6);
  ctx.textAlign = 'center';

  const lines = [
    { t: 'CONFIDENTIAL',      f: 'bold 22px "Space Grotesk",sans-serif', c: '#b91c1c', dy: -45 },
    { t: V.name || 'User',    f: 'bold 15px "Inter",sans-serif',         c: '#0f172a', dy: -20 },
    { t: V.email || '',       f: '13px "Inter",sans-serif',              c: '#0f172a', dy:  0  },
    { t: `ID: ${V.id}`,      f: '12px "Inter",sans-serif',              c: '#0f172a', dy: 18  },
    { t: `${dateStr} ${timeStr}`, f: '12px "Inter",sans-serif',          c: '#0f172a', dy: 36  },
    { t: 'HireIQ Secure Viewer', f: 'bold 13px "Space Grotesk",sans-serif', c: '#1e40af', dy: 56  },
  ];

  lines.filter(l => l.t).forEach(line => {
    ctx.font        = line.f;
    ctx.fillStyle   = line.c;
    ctx.shadowColor = 'rgba(255,255,255,0.8)';
    ctx.shadowBlur  = 6;
    ctx.fillText(line.t, 0, line.dy);
  });

  ctx.restore();
  ctx.globalAlpha = 1;
}

async function renderPDF(streamUrl) {
  const container  = document.getElementById('pdf-pages');
  const loadingEl  = document.getElementById('pdf-loading');
  const errorEl    = document.getElementById('pdf-error');

  try {
    const pdf = await pdfjsLib.getDocument({
      url: streamUrl,
      withCredentials: true,   // send session cookie
    }).promise;

    totalPages = pdf.numPages;
    document.getElementById('prog-total').textContent = totalPages;
    if (loadingEl) loadingEl.remove();

    for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
      const page     = await pdf.getPage(pageNum);
      const vWidth   = getViewerWidth();
      const baseVP   = page.getViewport({ scale: 1 });
      const scale    = vWidth / baseVP.width;
      const viewport = page.getViewport({ scale });

      const wrap   = document.createElement('div');
      wrap.className = 'pdf-page-wrap';
      wrap.id = `page-${pageNum}`;
      wrap.dataset.page = pageNum;

      const label = document.createElement('div');
      label.className = 'pdf-page-label';
      label.textContent = `Page ${pageNum} of ${totalPages}`;

      const canvas  = document.createElement('canvas');
      canvas.width  = viewport.width;
      canvas.height = viewport.height;
      canvas.style.maxWidth = '100%';

      const ctx = canvas.getContext('2d');

      // 1. Render PDF page content
      await page.render({ canvasContext: ctx, viewport }).promise;

      // 2. Burn watermark into the canvas (Udemy style — pixels, not DOM overlay)
      burnWatermark(ctx, viewport.width, viewport.height, pageNum);

      wrap.appendChild(label);
      wrap.appendChild(canvas);
      container.appendChild(wrap);

      // Jump to last viewed page after first page loads
      if (pageNum === 1 && V.lastPage > 1) {
        setTimeout(() => scrollToPage(V.lastPage), 100);
      }
    }

    setupPageTracking();

  } catch (err) {
    console.error('PDF.js error:', err);
    if (loadingEl) loadingEl.remove();
    if (errorEl) errorEl.classList.add('show');
  }
}

function scrollToPage(pageNum) {
  const el = document.getElementById('page-' + pageNum);
  if (el) el.scrollIntoView({ behavior: 'smooth' });
}

function setupPageTracking() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && entry.intersectionRatio > 0.4) {
        const p = parseInt(entry.target.dataset.page || '1');
        if (!isNaN(p)) {
          pagesViewed.add(p);
          currentPage = p;
          updateProgress();
        }
      }
    });
  }, {
    root: document.getElementById('pdf-scroll'),
    threshold: [0.4],
  });

  document.querySelectorAll('.pdf-page-wrap').forEach(el => observer.observe(el));
}

function updateProgress() {
  const cur  = document.getElementById('prog-cur');
  const read = document.getElementById('prog-read');
  const bar  = document.getElementById('prog-bar');
  const pct  = document.getElementById('prog-pct');

  if (cur) cur.textContent = currentPage;
  if (read) read.textContent = pagesViewed.size;
  const pctVal = totalPages > 0 ? Math.round((pagesViewed.size / totalPages) * 100) : 0;
  if (bar) bar.style.width = pctVal + '%';
  if (pct) pct.textContent = pctVal;

  // Report to server every page change
  const elapsed = Math.round((Date.now() - (V.viewStart * 1000)) / 1000);
  fetch(V.baseUrl + '/api/log_activity.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams({
      action:       'progress',
      doc_id:       V.docId,
      log_id:       V.logId,
      current_page: currentPage,
      pages_read:   pagesViewed.size,
      total_pages:  totalPages,
    }),
  }).catch(() => {});
}

/* ════════════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════════════ */
window.initViewer = function () {
  drawOverlayWatermark();
  renderPDF('<?= $streamUrl ?>');
};

// Auto-start if already acknowledged
<?php if ($acknowledged): ?>
window.addEventListener('load', initViewer);
<?php endif; ?>

// beforeunload (sendBeacon, no confirm dialog)
window.addEventListener('beforeunload', () => {
  const elapsed = Math.round((Date.now() - (V.viewStart * 1000)) / 1000);
  navigator.sendBeacon(V.baseUrl + '/api/log_activity.php',
    new URLSearchParams({ action:'close', doc_id:V.docId, log_id:V.logId, duration:elapsed })
  );
});
</script>
</body>
</html>
