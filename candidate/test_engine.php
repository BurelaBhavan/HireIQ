<?php
/**
 * Candidate — Test Engine
 * AI Interview Assessment Platform — Phase 4
 *
 * URL: /candidate/test_engine.php?invitation_id=<id>
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('candidate');

$candidateId  = (int) ($_SESSION['user_id'] ?? 0);
$invitationId = (int) ($_GET['invitation_id'] ?? 0);

if ($invitationId <= 0) {
    redirect(BASE_URL . '/candidate/invitations.php');
}

// ── Load invitation + test ────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare(
    "SELECT ti.*, t.title, t.description, t.duration
       FROM test_invitations ti
       JOIN tests t ON t.id = ti.test_id
      WHERE ti.id = :id AND ti.candidate_id = :cid AND ti.status = 'Accepted'
      LIMIT 1"
);
$stmt->execute([':id' => $invitationId, ':cid' => $candidateId]);
$invitation = $stmt->fetch();

if (!$invitation) {
    flash('invitations', 'Test invitation not found or not accepted.', 'error');
    redirect(BASE_URL . '/candidate/invitations.php');
}

$testId = (int) $invitation['test_id'];

// ── Load test questions ───────────────────────────────────────
$qStmt = $db->prepare(
    "SELECT id, question_text, difficulty, marks
       FROM test_questions
      WHERE test_id = :tid
      ORDER BY id ASC"
);
$qStmt->execute([':tid' => $testId]);
$questions = $qStmt->fetchAll();

if (empty($questions)) {
    flash('invitations', 'This test has no questions yet. Please contact your recruiter.', 'warning');
    redirect(BASE_URL . '/candidate/invitations.php');
}

// ── Check existing attempt ────────────────────────────────────
$taStmt = $db->prepare(
    "SELECT * FROM test_attempts WHERE candidate_id = :cid AND test_id = :tid
     AND status NOT IN ('expired') ORDER BY created_at DESC LIMIT 1"
);
$taStmt->execute([':cid' => $candidateId, ':tid' => $testId]);
$existingTA = $taStmt->fetch() ?: null;

if ($existingTA && $existingTA['status'] === 'completed') {
    flash('invitations', 'You have already completed this test.', 'info');
    redirect(BASE_URL . '/candidate/invitations.php');
}

$resuming = $existingTA && $existingTA['status'] === 'in_progress';

// Auto-save of answers in session for test (text-based)
$savedAnswers = $_SESSION['test_answers'][$testId] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="HireIQ Test Session" />
  <title><?= e($invitation['title']) ?> | HireIQ Test</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css" />

  <style>
    :root { --te-blue:#2563eb; --te-border:#e2e8f0; --te-text:#0f172a; --te-muted:#64748b; }
    body { background:#f8fafc; font-family:'Inter',sans-serif; }

    .te-topbar {
      position:fixed; top:0; left:0; right:0; z-index:1000;
      background:#fff; border-bottom:1px solid var(--te-border);
      padding:.65rem 1.5rem; display:flex; align-items:center; gap:1rem;
    }
    .te-brand { font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:1.1rem; color:var(--te-text); text-decoration:none; }
    .te-brand span { color:var(--te-blue); }
    .timer-badge {
      font-family:'Space Grotesk',monospace; font-size:1.1rem; font-weight:700;
      color:var(--te-blue); background:#eff6ff; padding:.3rem .85rem;
      border-radius:8px; border:1px solid #bfdbfe; letter-spacing:.05em; min-width:70px; text-align:center;
    }

    .te-container { padding-top:72px; max-width:900px; margin:0 auto; padding-left:1.5rem; padding-right:1.5rem; }

    .te-card {
      background:#fff; border-radius:14px; border:1px solid var(--te-border);
      padding:2rem; box-shadow:0 2px 12px rgba(0,0,0,.05); margin-bottom:1.25rem;
    }

    /* Progress bar */
    .progress-wrap { height:6px; background:#e2e8f0; border-radius:4px; overflow:hidden; }
    .progress-fill { height:100%; background:var(--te-blue); border-radius:4px; transition:width .3s; }

    /* Progress nav dots */
    .te-dots { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.75rem; }
    .te-dot {
      width:32px; height:32px; border-radius:6px; border:1px solid var(--te-border);
      background:#f1f5f9; font-size:.75rem; font-weight:600; cursor:pointer;
      display:flex; align-items:center; justify-content:center; color:var(--te-muted); transition:all .15s;
    }
    .te-dot:hover    { background:#e0e7ff; border-color:var(--te-blue); }
    .te-dot.active   { background:var(--te-blue); color:#fff; border-color:var(--te-blue); }
    .te-dot.answered { background:#dcfce7; border-color:#22c55e; color:#166534; }
    .te-dot.active.answered { background:var(--te-blue); color:#fff; }

    /* Answer textarea */
    .te-answer { width:100%; border:1px solid var(--te-border); border-radius:8px; padding:.875rem 1rem; font-family:'Inter',sans-serif; font-size:.9rem; color:var(--te-text); resize:vertical; min-height:140px; outline:none; transition:border .2s; }
    .te-answer:focus { border-color:var(--te-blue); box-shadow:0 0 0 3px rgba(37,99,235,.12); }

    .btn-te-primary { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.25rem; border-radius:8px; border:none; background:var(--te-blue); color:#fff; font-weight:600; font-size:.875rem; cursor:pointer; }
    .btn-te-outline { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.25rem; border-radius:8px; border:1px solid var(--te-border); background:#fff; color:var(--te-muted); font-weight:600; font-size:.875rem; cursor:pointer; }
    .btn-te-submit  { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.25rem; border-radius:8px; border:none; background:#16a34a; color:#fff; font-weight:600; font-size:.875rem; cursor:pointer; }

    /* Preflight */
    #preflight-te { padding-top:80px; }
    .pf-card { background:#fff; border-radius:16px; border:1px solid var(--te-border); padding:2.5rem; max-width:600px; margin:2rem auto; }
  </style>
</head>
<body>

<header class="te-topbar">
  <a href="<?= BASE_URL ?>/candidate/dashboard.php" class="te-brand">Hire<span>IQ</span></a>
  <span style="font-size:.875rem;font-weight:600;color:var(--te-muted);">| <?= e($invitation['title']) ?></span>
  <div style="flex:1;"></div>
  <div class="timer-badge" id="session-timer">--:--</div>
</header>

<!-- ── Pre-flight ── -->
<div id="preflight-te">
  <div class="pf-card">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div style="width:48px;height:48px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--te-blue);">
        <i class="bi bi-journal-check"></i>
      </div>
      <div>
        <h1 style="font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.3rem;color:#0f172a;margin:0;"><?= e($invitation['title']) ?></h1>
        <div style="font-size:.825rem;color:#64748b;margin-top:.1rem;">
          <?= (int) $invitation['duration'] ?> minutes &bull; <?= count($questions) ?> questions
        </div>
      </div>
    </div>

    <hr style="border-color:#e2e8f0;margin:1.25rem 0;" />

    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem;color:#1e40af;line-height:1.7;">
      <ul style="margin:0;padding-left:1.25rem;">
        <li>Answer each question in the text box provided.</li>
        <li>Your answers are <strong>auto-saved</strong> as you type.</li>
        <li>You have <strong><?= (int) $invitation['duration'] ?> minutes</strong>. The timer auto-submits when expired.</li>
        <li>Navigate freely between questions using the dot navigator.</li>
        <?php if ($resuming): ?>
        <li style="color:#92400e;"><strong>Resuming:</strong> Your saved answers are pre-loaded.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="d-flex gap-3">
      <button id="btn-start-test" class="btn-te-primary" style="padding:.65rem 1.5rem;font-size:.925rem;background:#16a34a;">
        <i class="bi bi-play-circle"></i>
        <?= $resuming ? 'Resume Test' : 'Start Test' ?>
      </button>
      <a href="<?= BASE_URL ?>/candidate/invitations.php" class="btn-te-outline">Cancel</a>
    </div>
  </div>
</div>

<!-- ── Engine Screen ── -->
<div id="engine-te" style="display:none;">
  <div class="te-container" style="padding-bottom:2rem;">

    <!-- Progress -->
    <div class="te-card" style="padding:1.25rem 1.5rem;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span style="font-size:.8rem;font-weight:700;color:var(--te-muted);text-transform:uppercase;letter-spacing:.06em;">Progress</span>
        <span style="font-size:.8rem;color:var(--te-muted);" id="te-progress-label">0 / <?= count($questions) ?> answered</span>
      </div>
      <div class="te-dots" id="te-dots"></div>
      <div class="progress-wrap">
        <div class="progress-fill" id="te-progress-bar" style="width:0%;"></div>
      </div>
    </div>

    <!-- Question card -->
    <div class="te-card">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span style="font-size:.75rem;font-weight:700;color:var(--te-blue);text-transform:uppercase;letter-spacing:.08em;" id="te-question-counter">Question 1 of <?= count($questions) ?></span>
        <span id="te-question-marks" style="font-size:.75rem;background:#f1f5f9;padding:.15rem .5rem;border-radius:6px;color:var(--te-muted);font-weight:600;"></span>
      </div>

      <p id="te-question-text" style="font-size:1.1rem;font-weight:600;color:var(--te-text);line-height:1.65;margin-bottom:1.25rem;">
        Loading…
      </p>

      <label style="font-size:.8rem;font-weight:600;color:var(--te-muted);margin-bottom:.5rem;display:block;">Your Answer</label>
      <textarea id="te-answer-input" class="te-answer" placeholder="Type your answer here…"></textarea>
      <div style="font-size:.75rem;color:var(--te-muted);margin-top:.4rem;text-align:right;" id="te-autosave-indicator">
        Auto-saved
      </div>

      <div class="d-flex gap-2 mt-3">
        <button id="te-btn-prev" class="btn-te-outline" type="button">
          <i class="bi bi-chevron-left"></i> Previous
        </button>
        <button id="te-btn-next" class="btn-te-primary" type="button">
          Next <i class="bi bi-chevron-right"></i>
        </button>
        <button id="te-btn-submit" class="btn-te-submit" type="button" style="display:none;">
          <i class="bi bi-check2-circle"></i> Submit Test
        </button>
      </div>
    </div>

  </div>
</div>

<!-- Confirm submit modal -->
<div class="modal fade" id="te-confirm-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Submit Test</h5></div>
      <div class="modal-body" style="padding:1.5rem;font-size:.9rem;color:#475569;">
        Are you ready to submit? You <strong>cannot</strong> change answers after submission.
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
        <button id="te-btn-confirm-submit" class="btn btn-sm btn-success">Submit Now</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<script>
const BASE_URL    = <?= json_encode(BASE_URL) ?>;
const TEST_ID     = <?= $testId ?>;
const DURATION    = <?= (int) $invitation['duration'] ?>;
const TE_QUESTIONS = <?= json_encode(array_map(fn($q) => [
    'id'            => (int) $q['id'],
    'question_text' => $q['question_text'],
    'difficulty'    => $q['difficulty'],
    'marks'         => (float) $q['marks'],
], $questions)) ?>;
const SAVED_ANSWERS = <?= json_encode($savedAnswers) ?>;

// ── State ────────────────────────────────────────────────────
let currentIdx  = 0;
let testAttemptId = null;
const answers   = { ...SAVED_ANSWERS }; // { questionId: text }
let timer       = null;
let remaining   = DURATION * 60;

// ── Start ────────────────────────────────────────────────────
document.getElementById('btn-start-test').addEventListener('click', async () => {
  const res = await apiPost('start_test_attempt', { test_id: TEST_ID });
  if (!res.ok) {
    alert('Failed to start test: ' + (res.error || 'Error'));
    return;
  }
  if (res.already_completed) {
    alert('You have already completed this test.');
    window.location.href = BASE_URL + '/candidate/invitations.php';
    return;
  }
  testAttemptId = res.attempt.id;
  document.getElementById('preflight-te').style.display  = 'none';
  document.getElementById('engine-te').style.display = 'block';
  renderQuestion(0);
  startTimer();
  startAutosave();
});

// ── Question rendering ────────────────────────────────────────
function renderQuestion(idx) {
  const q = TE_QUESTIONS[idx];
  if (!q) return;
  currentIdx = idx;

  document.getElementById('te-question-counter').textContent = `Question ${idx + 1} of ${TE_QUESTIONS.length}`;
  document.getElementById('te-question-text').textContent    = q.question_text;
  document.getElementById('te-question-marks').textContent   = `${q.marks} mark${q.marks !== 1 ? 's' : ''}`;
  document.getElementById('te-answer-input').value           = answers[q.id] || '';

  const btnPrev   = document.getElementById('te-btn-prev');
  const btnNext   = document.getElementById('te-btn-next');
  const btnSubmit = document.getElementById('te-btn-submit');
  btnPrev.disabled        = idx === 0;
  btnNext.style.display   = idx < TE_QUESTIONS.length - 1 ? 'inline-flex' : 'none';
  btnSubmit.style.display = idx === TE_QUESTIONS.length - 1 ? 'inline-flex' : 'none';

  renderDots();
}

// ── Navigation ───────────────────────────────────────────────
document.getElementById('te-btn-prev').addEventListener('click', () => {
  saveCurrentAnswer();
  if (currentIdx > 0) renderQuestion(currentIdx - 1);
});
document.getElementById('te-btn-next').addEventListener('click', () => {
  saveCurrentAnswer();
  if (currentIdx < TE_QUESTIONS.length - 1) renderQuestion(currentIdx + 1);
});
document.getElementById('te-btn-submit').addEventListener('click', () => {
  saveCurrentAnswer();
  const m = new bootstrap.Modal(document.getElementById('te-confirm-modal'));
  m.show();
  document.getElementById('te-btn-confirm-submit').addEventListener('click', () => {
    m.hide();
    submitTest();
  }, { once: true });
});

// ── Autosave ─────────────────────────────────────────────────
function saveCurrentAnswer() {
  const q   = TE_QUESTIONS[currentIdx];
  const val = document.getElementById('te-answer-input').value.trim();
  if (q) {
    answers[q.id] = val;
    // Store in PHP session via fetch
    if (val) {
      fetch(`${BASE_URL}/api/session_actions.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'autosave_test_answer', test_id: TEST_ID, question_id: q.id, answer: val }),
      }).catch(() => {});
    }
    renderDots();
  }
}

function startAutosave() {
  document.getElementById('te-answer-input').addEventListener('input', () => {
    const q   = TE_QUESTIONS[currentIdx];
    const val = document.getElementById('te-answer-input').value.trim();
    if (q) {
      answers[q.id] = val;
      renderDots();
      const ind = document.getElementById('te-autosave-indicator');
      if (ind) ind.textContent = 'Saving…';
      clearTimeout(window._autosaveTimer);
      window._autosaveTimer = setTimeout(() => {
        if (ind) ind.textContent = '✓ Auto-saved';
      }, 800);
    }
  });
}

// ── Dots renderer ────────────────────────────────────────────
function renderDots() {
  const container = document.getElementById('te-dots');
  container.innerHTML = '';
  let answered = 0;
  TE_QUESTIONS.forEach((q, i) => {
    const dot = document.createElement('button');
    dot.className   = 'te-dot';
    dot.textContent = i + 1;
    dot.addEventListener('click', () => { saveCurrentAnswer(); renderQuestion(i); });
    if (i === currentIdx) dot.classList.add('active');
    if (answers[q.id]?.trim()) { dot.classList.add('answered'); answered++; }
    container.appendChild(dot);
  });
  const label = document.getElementById('te-progress-label');
  const bar   = document.getElementById('te-progress-bar');
  if (label) label.textContent = `${answered} / ${TE_QUESTIONS.length} answered`;
  if (bar)   bar.style.width   = `${Math.round((answered / TE_QUESTIONS.length) * 100)}%`;
}

// ── Timer ────────────────────────────────────────────────────
function startTimer() {
  const el = document.getElementById('session-timer');
  timer = setInterval(() => {
    remaining = Math.max(0, remaining - 1);
    const m = Math.floor(remaining / 60), s = remaining % 60;
    el.textContent  = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    el.style.color  = remaining < 300 ? '#ef4444' : '';
    if (remaining <= 0) { clearInterval(timer); alert('Time is up! Submitting…'); submitTest(); }
  }, 1000);
}

// ── Submit ───────────────────────────────────────────────────
async function submitTest() {
  clearInterval(timer);
  const res = await apiPost('submit_test_attempt', { attempt_id: testAttemptId });
  if (res.ok) {
    document.body.innerHTML = `
      <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8fafc;font-family:'Inter',sans-serif;">
        <div style="text-align:center;padding:3rem;max-width:480px;">
          <div style="width:80px;height:80px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem;">✓</div>
          <h1 style="font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.75rem;color:#0f172a;margin:0 0 .75rem;">Test Submitted!</h1>
          <p style="color:#64748b;font-size:.95rem;margin:0 0 2rem;line-height:1.6;">Your answers have been submitted. Results will be available after review.</p>
          <a href="${BASE_URL}/candidate/dashboard.php"
             style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.75rem;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
            Return to Dashboard
          </a>
        </div>
      </div>`;
  } else {
    alert('Submission failed: ' + (res.error || 'Please try again'));
  }
}

// ── API helper ───────────────────────────────────────────────
async function apiPost(action, payload) {
  try {
    const r = await fetch(`${BASE_URL}/api/session_actions.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...payload }),
    });
    return await r.json();
  } catch { return { ok: false, error: 'Network error' }; }
}
</script>
</body>
</html>
