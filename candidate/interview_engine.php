<?php
/**
 * Candidate — Interview Engine
 * AI Interview Assessment Platform — Phase 4
 *
 * URL: /candidate/interview_engine.php?invitation_id=<id>
 *
 * Workflow:
 *   1. Candidate arrives (from Invitations page)
 *   2. Pre-flight: show instructions + permission check
 *   3. Start Interview → fullscreen + monitoring
 *   4. Answer each question with audio recording
 *   5. Submit
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('candidate');

$candidateId   = (int) ($_SESSION['user_id'] ?? 0);
$invitationId  = (int) ($_GET['invitation_id'] ?? 0);

if ($invitationId <= 0) {
    redirect(BASE_URL . '/candidate/invitations.php');
}

// ── Load invitation + interview ───────────────────────────────
$db  = getDB();
$inv = $db->prepare(
    "SELECT ii.*, i.title, i.description, i.duration, i.difficulty
       FROM interview_invitations ii
       JOIN interviews i ON i.id = ii.interview_id
      WHERE ii.id = :id AND ii.candidate_id = :cid AND ii.status = 'Accepted'
      LIMIT 1"
);
$inv->execute([':id' => $invitationId, ':cid' => $candidateId]);
$invitation = $inv->fetch();

if (!$invitation) {
    flash('invitations', 'Interview invitation not found or not accepted.', 'error');
    redirect(BASE_URL . '/candidate/invitations.php');
}

$interviewId = (int) $invitation['interview_id'];

// ── Load questions ────────────────────────────────────────────
$qStmt = $db->prepare(
    "SELECT q.id, q.question_text, q.difficulty
       FROM interview_questions iq
       JOIN questions q ON q.id = iq.question_id
      WHERE iq.interview_id = :iid
      ORDER BY iq.sequence_order ASC, iq.id ASC"
);
$qStmt->execute([':iid' => $interviewId]);
$questions = $qStmt->fetchAll();

if (empty($questions)) {
    flash('invitations', 'This interview has no questions yet. Please contact your recruiter.', 'warning');
    redirect(BASE_URL . '/candidate/invitations.php');
}

// ── Check existing attempt ────────────────────────────────────
$existingAttempt = null;
$attStmt = $db->prepare(
    "SELECT * FROM attempts WHERE candidate_id = :cid AND interview_id = :iid
     AND status NOT IN ('expired') ORDER BY created_at DESC LIMIT 1"
);
$attStmt->execute([':cid' => $candidateId, ':iid' => $interviewId]);
$existingAttempt = $attStmt->fetch() ?: null;

if ($existingAttempt && $existingAttempt['status'] === 'completed') {
    flash('invitations', 'You have already completed this interview.', 'info');
    redirect(BASE_URL . '/candidate/invitations.php');
}

$resuming = $existingAttempt && $existingAttempt['status'] === 'in_progress';

// ── Encode questions for JS ───────────────────────────────────
$questionsJson = json_encode(array_map(fn($q) => [
    'id'            => (int) $q['id'],
    'question_text' => $q['question_text'],
    'difficulty'    => $q['difficulty'],
], $questions));

$diffLabel = ucfirst($invitation['difficulty']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="HireIQ Interview Session" />
  <title><?= e($invitation['title']) ?> | HireIQ Interview</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css" />

  <style>
    :root {
      --ie-blue:    #2563eb;
      --ie-surface: #f8fafc;
      --ie-border:  #e2e8f0;
      --ie-text:    #0f172a;
      --ie-muted:   #64748b;
    }

    /* ── Engine layout ── */
    body { background: var(--ie-surface); font-family: 'Inter', sans-serif; }
    .ie-topbar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
      background: #fff; border-bottom: 1px solid var(--ie-border);
      padding: .65rem 1.5rem;
      display: flex; align-items: center; gap: 1rem;
    }
    .ie-brand { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 1.1rem; color: var(--ie-text); text-decoration: none; }
    .ie-brand span { color: var(--ie-blue); }
    .ie-title { font-size: .875rem; font-weight: 600; color: var(--ie-muted); margin-left: .5rem; }
    .ie-spacer { flex: 1; }
    .timer-badge {
      font-family: 'Space Grotesk', monospace; font-size: 1.1rem; font-weight: 700;
      color: var(--ie-blue); background: #eff6ff; padding: .3rem .85rem;
      border-radius: 8px; border: 1px solid #bfdbfe; letter-spacing: .05em;
      min-width: 70px; text-align: center;
    }
    .camera-pill {
      display: flex; align-items: center; gap: .4rem;
      font-size: .8rem; font-weight: 500; color: var(--ie-muted);
      background: #f1f5f9; padding: .3rem .75rem; border-radius: 20px;
    }
    .camera-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; }

    /* ── Pre-flight screen ── */
    #preflight-screen { padding-top: 80px; min-height: 100vh; }
    .preflight-card {
      background: #fff; border-radius: 16px; border: 1px solid var(--ie-border);
      padding: 2.5rem; max-width: 680px; margin: 2rem auto; box-shadow: 0 4px 24px rgba(0,0,0,.06);
    }
    .check-row {
      display: flex; align-items: center; gap: 1rem;
      padding: .85rem 1rem; border: 1px solid var(--ie-border);
      border-radius: 10px; margin-bottom: .75rem; background: #f8fafc;
    }
    .check-icon { font-size: 1.25rem; width: 36px; text-align: center; }
    .check-status { margin-left: auto; font-size: .8rem; font-weight: 600; }
    .check-status.ok    { color: #22c55e; }
    .check-status.fail  { color: #ef4444; }
    .check-status.wait  { color: #f59e0b; }

    /* ── Engine screen ── */
    #engine-screen { display: none; padding-top: 72px; min-height: 100vh; }
    .ie-layout { display: grid; grid-template-columns: 1fr 280px; gap: 1.25rem; padding: 1.25rem 1.5rem; max-width: 1200px; margin: 0 auto; }

    .question-card {
      background: #fff; border-radius: 14px; border: 1px solid var(--ie-border);
      padding: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,.05);
    }
    .question-label { font-size: .75rem; font-weight: 700; color: var(--ie-blue); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .75rem; }
    #question-text  { font-size: 1.2rem; font-weight: 600; color: var(--ie-text); line-height: 1.6; margin-bottom: 1.5rem; }

    .recording-bar {
      display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
      padding: 1.25rem; background: #f8fafc; border-radius: 10px; border: 1px solid var(--ie-border);
    }
    #recording-indicator { display: none; align-items: center; gap: .4rem; color: #ef4444; font-weight: 600; font-size: .85rem; }
    .pulse-dot { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; animation: pulse 1s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

    /* ── Sidebar ── */
    .ie-sidebar { display: flex; flex-direction: column; gap: 1rem; }
    .sidebar-card {
      background: #fff; border-radius: 12px; border: 1px solid var(--ie-border);
      padding: 1.25rem; box-shadow: 0 1px 6px rgba(0,0,0,.04);
    }
    .sidebar-card__title { font-size: .75rem; font-weight: 700; color: var(--ie-muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .85rem; }

    /* Camera preview */
    #camera-preview {
      width: 100%; aspect-ratio: 4/3; object-fit: cover;
      border-radius: 8px; background: #0f172a;
    }

    /* Progress dots */
    #progress-dots { display: flex; flex-wrap: wrap; gap: .4rem; }
    .progress-dot {
      width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--ie-border);
      background: #f1f5f9; font-size: .75rem; font-weight: 600; cursor: pointer;
      display: flex; align-items: center; justify-content: center; color: var(--ie-muted);
      transition: all .15s;
    }
    .progress-dot:hover   { background: #e0e7ff; border-color: var(--ie-blue); }
    .progress-dot.active  { background: var(--ie-blue); color: #fff; border-color: var(--ie-blue); }
    .progress-dot.answered { background: #dcfce7; border-color: #22c55e; color: #166534; }
    .progress-dot.active.answered { background: var(--ie-blue); color: #fff; }

    .progress-bar-wrap { height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-top: .75rem; }
    .progress-bar-fill { height: 100%; background: var(--ie-blue); border-radius: 4px; transition: width .3s; }

    /* Diff badge */
    .diff-badge { font-size: .75rem; padding: .2rem .55rem; border-radius: 6px; font-weight: 600; }
    .diff-badge--easy   { background: #dcfce7; color: #166534; }
    .diff-badge--medium { background: #fef3c7; color: #92400e; }
    .diff-badge--hard   { background: #fee2e2; color: #991b1b; }
    .diff-badge--expert { background: #f3e8ff; color: #5b21b6; }

    /* Nav buttons */
    .ie-nav-bar { display: flex; align-items: center; gap: .75rem; margin-top: 1.5rem; }
    .btn-ie-primary {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .6rem 1.25rem; border-radius: 8px; border: none;
      background: var(--ie-blue); color: #fff; font-weight: 600; font-size: .875rem; cursor: pointer;
      transition: opacity .15s;
    }
    .btn-ie-primary:hover { opacity: .88; }
    .btn-ie-primary:disabled { opacity: .5; cursor: not-allowed; }
    .btn-ie-outline {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .6rem 1.25rem; border-radius: 8px; border: 1px solid var(--ie-border);
      background: #fff; color: var(--ie-muted); font-weight: 600; font-size: .875rem; cursor: pointer;
    }
    .btn-ie-submit {
      background: #16a34a; color: #fff; border: none;
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: .875rem; cursor: pointer;
    }

    @media (max-width: 768px) {
      .ie-layout { grid-template-columns: 1fr; }
      .ie-sidebar { order: -1; }
    }
  </style>
</head>
<body>

<!-- ─────────────────────────────────────────────────────────────
     TOP BAR (always visible)
───────────────────────────────────────────────────────────────── -->
<header class="ie-topbar">
  <a href="<?= BASE_URL ?>/candidate/dashboard.php" class="ie-brand">Hire<span>IQ</span></a>
  <span class="ie-title">| <?= e($invitation['title']) ?></span>
  <div class="ie-spacer"></div>
  <div class="camera-pill">
    <div class="dot" id="camera-status-dot"></div>
    <span id="camera-status-text">Camera Off</span>
  </div>
  <div class="timer-badge" id="session-timer">--:--</div>
</header>

<!-- ─────────────────────────────────────────────────────────────
     PRE-FLIGHT SCREEN
───────────────────────────────────────────────────────────────── -->
<div id="preflight-screen">
  <div class="preflight-card">

    <div class="d-flex align-items-center gap-3 mb-3">
      <div style="width:48px;height:48px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--ie-blue);">
        <i class="bi bi-camera-video"></i>
      </div>
      <div>
        <h1 style="font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.3rem;color:#0f172a;margin:0;"><?= e($invitation['title']) ?></h1>
        <div style="font-size:.825rem;color:#64748b;margin-top:.1rem;">
          <?= (int) $invitation['duration'] ?> minutes &bull;
          <?= (int) count($questions) ?> questions &bull;
          <?= e($diffLabel) ?>
        </div>
      </div>
    </div>

    <hr style="border-color:#e2e8f0;margin:1.25rem 0;" />

    <h2 style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:1rem;">Before You Begin</h2>

    <!-- Instructions -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem;color:#1e40af;line-height:1.7;">
      <ul style="margin:0;padding-left:1.25rem;">
        <li>The interview will enter <strong>fullscreen mode</strong> when you start.</li>
        <li>Leaving the tab or exiting fullscreen will generate a <strong>warning</strong>.</li>
        <li>Your <strong>microphone</strong> is used to record voice answers.</li>
        <li>Your <strong>camera</strong> is used for presence monitoring only. No video is stored.</li>
        <li>You have <strong><?= (int) $invitation['duration'] ?> minutes</strong> total. The timer auto-submits when it expires.</li>
        <?php if ($resuming): ?>
        <li style="color:#92400e;"><strong>Resuming:</strong> Your previous progress is saved.</li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Permission checks -->
    <h3 style="font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:.75rem;">System Requirements</h3>
    <div class="check-row" id="check-camera">
      <div class="check-icon">📷</div>
      <div><div style="font-weight:600;font-size:.875rem;">Camera Access</div><div style="font-size:.775rem;color:#64748b;">Required for presence monitoring</div></div>
      <div class="check-status wait" id="check-camera-status">Checking…</div>
    </div>
    <div class="check-row" id="check-mic">
      <div class="check-icon">🎙</div>
      <div><div style="font-weight:600;font-size:.875rem;">Microphone Access</div><div style="font-size:.775rem;color:#64748b;">Required to record your answers</div></div>
      <div class="check-status wait" id="check-mic-status">Checking…</div>
    </div>
    <div class="check-row">
      <div class="check-icon">🔒</div>
      <div><div style="font-weight:600;font-size:.875rem;">Fullscreen Mode</div><div style="font-size:.775rem;color:#64748b;">Will activate automatically on start</div></div>
      <div class="check-status ok">Ready</div>
    </div>

    <div id="preflight-msg" style="margin-top:1rem;font-size:.85rem;color:#ef4444;display:none;"></div>

    <div class="d-flex gap-3 mt-4">
      <button id="btn-check-permissions" class="btn-ie-primary"
              style="padding:.65rem 1.5rem;font-size:.925rem;">
        <i class="bi bi-shield-check"></i> Check Permissions
      </button>
      <button id="btn-start-interview" class="btn-ie-primary" disabled
              style="padding:.65rem 1.5rem;font-size:.925rem;background:#16a34a;">
        <i class="bi bi-play-circle"></i>
        <?= $resuming ? 'Resume Interview' : 'Start Interview' ?>
      </button>
      <a href="<?= BASE_URL ?>/candidate/invitations.php" class="btn-ie-outline">Cancel</a>
    </div>
  </div>
</div>

<!-- ─────────────────────────────────────────────────────────────
     ENGINE SCREEN  (shown after start)
───────────────────────────────────────────────────────────────── -->
<div id="engine-screen">
  <div class="ie-layout">

    <!-- Main question area -->
    <div class="question-card">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="question-label" id="question-counter">Question 1 of <?= count($questions) ?></span>
        <span id="question-difficulty" class="badge diff-badge--medium">Medium</span>
      </div>

      <p id="question-text">Loading question…</p>

      <!-- Audio recording -->
      <div class="recording-bar">
        <button id="record-btn" class="btn-ie-primary btn-primary" type="button">
          <i class="bi bi-mic"></i> Start Recording
        </button>
        <div id="recording-indicator">
          <div class="pulse-dot"></div>
          Recording…
        </div>
        <span id="audio-saved-badge" class="badge bg-success" style="display:none;">✓ Audio saved</span>
      </div>

      <!-- Navigation -->
      <div class="ie-nav-bar">
        <button id="btn-prev-question" class="btn-ie-outline" type="button">
          <i class="bi bi-chevron-left"></i> Previous
        </button>
        <button id="btn-next-question" class="btn-ie-primary" type="button">
          Next <i class="bi bi-chevron-right"></i>
        </button>
        <button id="btn-submit-interview" class="btn-ie-submit" type="button" style="display:none;">
          <i class="bi bi-check2-circle"></i> Submit Interview
        </button>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="ie-sidebar">

      <!-- Camera preview -->
      <div class="sidebar-card">
        <div class="sidebar-card__title">Camera Preview</div>
        <video id="camera-preview" autoplay muted playsinline></video>
        <div style="margin-top:.6rem;font-size:.75rem;color:#64748b;text-align:center;">
          <i class="bi bi-info-circle me-1"></i>Camera for presence only. Not recorded.
        </div>
      </div>

      <!-- Progress -->
      <div class="sidebar-card">
        <div class="sidebar-card__title">Progress</div>
        <div id="progress-dots"></div>
        <div class="progress-bar-wrap mt-2">
          <div class="progress-bar-fill" id="progress-bar-fill" style="width:0%;"></div>
        </div>
        <div style="font-size:.775rem;color:#64748b;margin-top:.5rem;" id="progress-pct">0 / <?= count($questions) ?> answered</div>
      </div>

      <!-- Interview info -->
      <div class="sidebar-card">
        <div class="sidebar-card__title">Details</div>
        <div style="font-size:.825rem;color:#475569;line-height:1.8;">
          <div><strong>Duration:</strong> <?= (int) $invitation['duration'] ?> min</div>
          <div><strong>Questions:</strong> <?= count($questions) ?></div>
          <div><strong>Difficulty:</strong> <?= e($diffLabel) ?></div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Confirm Submit Modal -->
<div class="modal fade" id="confirm-submit-modal" tabindex="-1" aria-labelledby="csmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="csmLabel">Submit Interview</h5></div>
      <div class="modal-body" style="padding:1.5rem;font-size:.9rem;color:#475569;">
        Are you ready to submit? Once submitted, you <strong>cannot</strong> change your answers.
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
        <button id="btn-confirm-submit" class="btn btn-sm btn-success">Submit Now</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<!-- Engine JS -->
<script>
  // PHP → JS bridge
  const BASE_URL    = <?= json_encode(BASE_URL) ?>;
  const INTERVIEW_ID = <?= $interviewId ?>;
  const DURATION_MIN = <?= (int) $invitation['duration'] ?>;
  const QUESTIONS    = <?= $questionsJson ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/interview_engine.js"></script>

<script>
// ── Pre-flight logic ──────────────────────────────────────────
(async function preflightSetup() {
  let cameraOk = false;
  let micOk    = false;

  const setStatus = (elId, state, label) => {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = label;
    el.className   = 'check-status ' + state;
  };

  document.getElementById('btn-check-permissions').addEventListener('click', async () => {
    // Developer bypass check
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('bypass_permissions') === '1') {
      cameraOk = true;
      micOk    = true;
      setStatus('check-camera-status', 'ok', '✓ Granted (Bypassed)');
      setStatus('check-mic-status',    'ok', '✓ Granted (Bypassed)');
      const startBtn = document.getElementById('btn-start-interview');
      startBtn.disabled = false;
      const msgEl    = document.getElementById('preflight-msg');
      msgEl.style.display = 'none';
      return;
    }

    setStatus('check-camera-status', 'wait', 'Requesting…');
    setStatus('check-mic-status',    'wait', 'Requesting…');

    let camError = '';
    let micError = '';

    // Camera check
    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('NotSecureContext');
      }
      const camStream = await navigator.mediaDevices.getUserMedia({ video: true });
      camStream.getTracks().forEach(t => t.stop()); // Just checking permission
      cameraOk = true;
      setStatus('check-camera-status', 'ok', '✓ Granted');
    } catch (err) {
      cameraOk = false;
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        camError = 'Blocked by browser settings';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        camError = 'No camera found';
      } else if (err.message === 'NotSecureContext') {
        camError = 'Insecure context (requires HTTPS/localhost)';
      } else {
        camError = err.message || 'Error';
      }
      setStatus('check-camera-status', 'fail', '✗ Denied (' + camError + ')');
    }

    // Microphone check
    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('NotSecureContext');
      }
      const micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      micStream.getTracks().forEach(t => t.stop());
      micOk = true;
      setStatus('check-mic-status', 'ok', '✓ Granted');
    } catch (err) {
      micOk = false;
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        micError = 'Blocked by browser settings';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        micError = 'No microphone found';
      } else if (err.message === 'NotSecureContext') {
        micError = 'Insecure context';
      } else {
        micError = err.message || 'Error';
      }
      setStatus('check-mic-status', 'fail', '✗ Denied (' + micError + ')');
    }

    const startBtn = document.getElementById('btn-start-interview');
    const msgEl    = document.getElementById('preflight-msg');
    
    let htmlContent = '';
    if (micOk) {
      startBtn.disabled = false;
    } else {
      startBtn.disabled = true;
      htmlContent += '<p class="mb-1"><strong>Microphone access is required</strong> to record your answers. Please allow access and try again.</p>';
    }
    
    if (!cameraOk) {
      htmlContent += '<p class="mb-1"><strong>Camera access was denied.</strong> Your interview will proceed, but a presence monitoring violation will be logged.</p>';
    }
    
    if (!micOk || !cameraOk) {
      if (micError.includes('settings') || camError.includes('settings')) {
        htmlContent += `
          <div class="alert alert-warning border-warning mt-3 text-dark text-start" style="font-size: .825rem; line-height: 1.5; font-family: sans-serif;">
            <div class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i> How to enable blocked permissions in Chrome:</div>
            <ol class="ps-3 mb-0">
              <li>Click the <strong>settings dials / site controls icon</strong> (or the <strong>"Not secure"</strong> label) in your browser's address bar to the left of <code>localhost</code>.</li>
              <li>Toggle <strong>Camera</strong> and <strong>Microphone</strong> to <strong>Allow</strong>.</li>
              <li><strong>Reload the page</strong> and click <strong>Check Permissions</strong> again.</li>
            </ol>
          </div>`;
      }
    }
    
    if (htmlContent) {
      msgEl.innerHTML = htmlContent;
      msgEl.style.display = 'block';
    } else {
      msgEl.style.display = 'none';
      msgEl.innerHTML = '';
    }
  });

  document.getElementById('btn-start-interview').addEventListener('click', async () => {
    document.getElementById('preflight-screen').style.display = 'none';
    document.getElementById('engine-screen').style.display = 'block';
    await SessionManager.launch(INTERVIEW_ID, DURATION_MIN, QUESTIONS);
  });
})();
</script>
</body>
</html>
