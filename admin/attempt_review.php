<?php
/**
 * Admin — Attempt Review (Phase 5: AI Evaluation)
 * AI Interview Assessment Platform
 *
 * URL: /admin/attempt_review.php?attempt_id=<id>
 *      /admin/attempt_review.php?interview_id=<id>  (list all attempts)
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../includes/ai_services.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

$attemptId   = (int) ($_GET['attempt_id']   ?? 0);
$interviewId = (int) ($_GET['interview_id'] ?? 0);

// ─────────────────────────────────────────────────────────────
// Mode 1: Single attempt detail
// ─────────────────────────────────────────────────────────────
if ($attemptId > 0) {
    $detail = getAttemptDetail($attemptId);
    if (!$detail) {
        flash('interviews', 'Attempt not found.', 'error');
        redirect(BASE_URL . '/admin/interviews.php');
    }

    // Handle saving / publishing evaluation results
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save_result', 'publish_result'])) {
        verifyCsrf();
        $decision = $_POST['decision'] ?? 'pending';
        $conclusion = trim($_POST['conclusion'] ?? '');
        $isPublish = ($_POST['action'] ?? '') === 'publish_result';
        
        if (!in_array($decision, ['selected', 'rejected', 'pending'])) {
            $decision = 'pending';
        }
        
        $db = getDB();
        
        // Check if result already exists
        $stmt = $db->prepare("SELECT id, published_at FROM interview_results WHERE attempt_id = ?");
        $stmt->execute([$attemptId]);
        $existingResult = $stmt->fetch();
        
        $publishedAt = null;
        if ($existingResult) {
            $publishedAt = $existingResult['published_at'];
        }
        
        if ($isPublish) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        
        if ($existingResult) {
            // Update
            $stmt = $db->prepare("UPDATE interview_results SET decision = ?, conclusion = ?, published_at = ? WHERE attempt_id = ?");
            $stmt->execute([$decision, $conclusion, $publishedAt, $attemptId]);
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO interview_results (attempt_id, candidate_id, interview_id, decision, conclusion, published_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$attemptId, $detail['candidate_id'], $detail['interview_id'], $decision, $conclusion, $publishedAt]);
        }
        
        if ($isPublish) {
            // Send notification
            require_once __DIR__ . '/../includes/notifications.php';
            createNotification(
                (int)$detail['candidate_id'],
                "Interview Result Published",
                "Your result for the interview '" . $detail['interview_title'] . "' has been published. Decision: " . ucfirst($decision),
                "Interview"
            );
            flash('attempt_review', 'Result published to candidate successfully.', 'success');
        } else {
            flash('attempt_review', 'Result draft saved successfully.', 'success');
        }
        
        redirect(BASE_URL . "/admin/attempt_review.php?attempt_id=$attemptId");
    }

    // Fetch existing result
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM interview_results WHERE attempt_id = ?");
    $stmt->execute([$attemptId]);
    $resultData = $stmt->fetch();

    // Phase 5: Load answers with transcript + evaluation data
    $answers   = getEvaluationSummaryForAttempt($attemptId);
    $intEvents = getIntegrityEvents($attemptId);
    $tabLog    = getTabSwitchLog($attemptId);
    $fsLog     = getFullscreenLog($attemptId);

    // Check API keys
    $groqReady   = defined('GROQ_API_KEY')   && GROQ_API_KEY   !== '';
    $geminiReady = defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '';
    $aiReady     = $groqReady && $geminiReady;

    // Compute risk level
    $total    = (int) $detail['total_violations'];
    $riskLevel = match(true) {
        $total === 0 => ['label' => 'Low Risk',    'class' => 'success', 'icon' => 'bi-shield-check'],
        $total <= 2  => ['label' => 'Medium Risk', 'class' => 'warning', 'icon' => 'bi-shield-exclamation'],
        default      => ['label' => 'High Risk',   'class' => 'danger',  'icon' => 'bi-shield-x'],
    };

    $durationSec = (int) $detail['duration_sec'];
    $durationFmt = $durationSec > 0
        ? sprintf('%02d:%02d', intdiv($durationSec, 60), $durationSec % 60)
        : '—';

    // Average overall score (only evaluated answers)
    $evaluatedAnswers = array_filter($answers, fn($a) => $a['overall_score'] !== null);
    $avgScore = count($evaluatedAnswers) > 0
        ? round(array_sum(array_column($evaluatedAnswers, 'overall_score')) / count($evaluatedAnswers), 1)
        : null;

    require_once __DIR__ . '/../includes/layout.php';
    renderHeader('Attempt Review — ' . e($detail['full_name']), 'dashboard-page');
    renderAdminNav('attempt_review');
    ?>

<!-- ── Top header ── -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
  <div>
    <a href="<?= BASE_URL ?>/admin/attempt_review.php?interview_id=<?= $detail['interview_id'] ?>"
       style="font-size:.8125rem;color:var(--color-text-muted);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.5rem;">
      <i class="bi bi-arrow-left"></i> Back to All Attempts
    </a>
    <h1 class="page-header__title"><?= e($detail['full_name']) ?></h1>
    <p class="page-header__subtitle"><?= e($detail['interview_title']) ?> — Attempt Review</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <?php if ($avgScore !== null): ?>
    <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:12px;padding:.5rem 1.1rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.4rem;">
      <i class="bi bi-stars"></i> <?= $avgScore ?>/100
      <span style="font-size:.7rem;font-weight:500;opacity:.85;margin-left:.2rem;">AI Score</span>
    </div>
    <?php endif; ?>
    <span class="badge bg-<?= $riskLevel['class'] ?>-subtle text-<?= $riskLevel['class'] ?>"
          style="font-size:.875rem;padding:.5rem 1rem;border:1px solid currentColor;border-radius:8px;font-weight:700;">
      <i class="bi <?= $riskLevel['icon'] ?> me-1"></i><?= $riskLevel['label'] ?>
    </span>
  </div>
</div>

<!-- ── API Keys banner ── -->
<?php if (!$aiReady): ?>
<div class="alert alert-warning d-flex align-items-start gap-2 mb-4" style="border-radius:10px;">
  <i class="bi bi-exclamation-triangle-fill mt-1"></i>
  <div>
    <strong>AI Evaluation Requires API Keys</strong><br>
    <span style="font-size:.875rem;">
      <?php if (!$groqReady): ?><code>GROQ_API_KEY</code> is not set. <?php endif; ?>
      <?php if (!$geminiReady): ?><code>GEMINI_API_KEY</code> is not set. <?php endif; ?>
      Edit <code><?= e(realpath(__DIR__ . '/../.env')) ?></code> to add your keys, then restart XAMPP.
    </span>
  </div>
</div>
<?php endif; ?>

<!-- ── Summary cards ── -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['icon' => 'bi-clock',               'label' => 'Status',         'value' => ucfirst(str_replace('_', ' ', $detail['status']))],
    ['icon' => 'bi-stopwatch',           'label' => 'Duration',       'value' => $durationFmt],
    ['icon' => 'bi-mic',                 'label' => 'Answers',        'value' => count($answers)],
    ['icon' => 'bi-window',              'label' => 'Tab Switches',   'value' => (int) $detail['tab_switches']],
    ['icon' => 'bi-fullscreen-exit',     'label' => 'FS Exits',       'value' => (int) $detail['fs_exits']],
    ['icon' => 'bi-exclamation-triangle','label' => 'Violations',     'value' => $total],
  ];
  foreach ($cards as $c): ?>
  <div class="col-6 col-md-2">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:.7rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;">
        <?= $c['label'] ?>
      </div>
      <div style="font-size:1.3rem;font-weight:700;font-family:var(--font-heading);"><?= $c['value'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Batch Evaluate Button ── -->
<?php if (!empty($answers) && $aiReady): ?>
<div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
  <button id="btn-batch-eval" class="btn btn-primary d-flex align-items-center gap-2"
          onclick="batchEvaluate(<?= $attemptId ?>)"
          style="background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:8px;font-weight:600;padding:.55rem 1.25rem;">
    <i class="bi bi-stars"></i> Evaluate All Answers with AI
  </button>
  <div id="batch-progress" style="display:none;font-size:.875rem;color:var(--color-text-secondary);">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    <span id="batch-msg">Processing…</span>
  </div>
  <div id="batch-result" style="display:none;font-size:.875rem;font-weight:600;"></div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- ── Left column: Answers + AI ── -->
  <div class="col-lg-7">
    <div class="content-card">
      <div class="content-card__header d-flex align-items-center justify-content-between">
        <h2 class="content-card__title">Recorded Answers & AI Evaluation</h2>
        <?php if ($aiReady && !empty($answers)): ?>
        <span style="font-size:.75rem;color:var(--color-text-muted);">
          <i class="bi bi-stars me-1" style="color:#8b5cf6;"></i>AI Ready
        </span>
        <?php endif; ?>
      </div>
      <?php if (empty($answers)): ?>
        <div class="empty-state">
          <i class="bi bi-mic-mute empty-state__icon"></i>
          <p class="empty-state__title">No answers recorded</p>
          <p class="empty-state__sub">The candidate did not record any audio answers.</p>
        </div>
      <?php else: ?>
        <?php foreach ($answers as $i => $ans): ?>
        <?php
          $hasAudio    = !empty($ans['audio_path']);
          $hasTranscript = !empty($ans['transcript_text']);
          $hasEval     = $ans['overall_score'] !== null;
          $jobStatus   = $ans['job_status'] ?? null;
          $answerId    = (int) $ans['answer_id'];
        ?>
        <div id="answer-card-<?= $answerId ?>" style="padding:1rem;border:1px solid var(--color-border);border-radius:10px;margin-bottom:.85rem;transition:border-color .2s;">

          <!-- Question header -->
          <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <span style="font-size:.75rem;font-weight:700;color:var(--color-primary);text-transform:uppercase;background:rgba(99,102,241,.1);padding:.2rem .5rem;border-radius:6px;">Q<?= $i+1 ?></span>
            <span style="font-weight:600;font-size:.875rem;color:var(--color-text-primary);flex:1;"><?= e($ans['question_text']) ?></span>
            <span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem;"><?= e($ans['difficulty'] ?? '') ?></span>
            <?php if ($ans['response_time']): ?>
            <span style="font-size:.75rem;color:var(--color-text-muted);"><i class="bi bi-clock me-1"></i><?= $ans['response_time'] ?>s</span>
            <?php endif; ?>
          </div>

          <!-- Audio player -->
          <?php if ($hasAudio): ?>
          <audio controls style="width:100%;border-radius:6px;margin-bottom:.75rem;" preload="none">
            <source src="<?= BASE_URL . '/' . e($ans['audio_path']) ?>" />
          </audio>
          <?php else: ?>
          <div style="font-size:.825rem;color:var(--color-text-muted);font-style:italic;margin-bottom:.75rem;">No audio recorded.</div>
          <?php endif; ?>

          <!-- Transcript section -->
          <div id="transcript-<?= $answerId ?>" style="margin-bottom:.75rem;">
            <?php if ($hasTranscript): ?>
            <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.2);border-radius:8px;padding:.75rem;">
              <div style="font-size:.7rem;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;">
                <i class="bi bi-mic me-1"></i>Transcript
                <?php if (!empty($ans['language'])): ?>
                <span style="font-weight:400;color:var(--color-text-muted);">(<?= e($ans['language']) ?>)</span>
                <?php endif; ?>
              </div>
              <p style="font-size:.85rem;color:var(--color-text-primary);margin:0;line-height:1.6;"><?= e($ans['transcript_text']) ?></p>
            </div>
            <?php elseif ($jobStatus === 'transcribing'): ?>
            <div style="font-size:.8rem;color:#f59e0b;"><i class="bi bi-hourglass-split me-1"></i>Transcribing…</div>
            <?php elseif ($jobStatus === 'failed'): ?>
            <div style="font-size:.8rem;color:#ef4444;"><i class="bi bi-x-circle me-1"></i>Transcription failed.</div>
            <?php elseif ($hasAudio && $aiReady): ?>
            <div style="font-size:.8rem;color:var(--color-text-muted);font-style:italic;">
              <i class="bi bi-mic me-1"></i>No transcript yet.
            </div>
            <?php else: ?>
            <div style="font-size:.8rem;color:var(--color-text-muted);font-style:italic;">
              <i class="bi bi-mic me-1"></i>Transcript available after Groq Whisper integration.
            </div>
            <?php endif; ?>
          </div>

          <!-- AI Evaluation section -->
          <div id="eval-<?= $answerId ?>">
            <?php if ($hasEval): ?>
            <?php
              $os = round((float)$ans['overall_score']);
              $ts = round((float)$ans['technical_score']);
              $cs = round((float)$ans['communication_score']);
              $scoreColor = $os >= 75 ? '#22c55e' : ($os >= 50 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:.85rem;">
              <div style="font-size:.7rem;font-weight:700;color:#8b5cf6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem;">
                <i class="bi bi-stars me-1"></i>AI Evaluation
                <?php if (!empty($ans['model_used'])): ?>
                <span style="font-weight:400;color:var(--color-text-muted);">(<?= e($ans['model_used']) ?>)</span>
                <?php endif; ?>
              </div>

              <!-- Score bars -->
              <div class="row g-2 mb-3">
                <?php foreach ([
                  ['label' => 'Overall',       'score' => $os, 'color' => $scoreColor],
                  ['label' => 'Technical',     'score' => $ts, 'color' => '#3b82f6'],
                  ['label' => 'Communication', 'score' => $cs, 'color' => '#8b5cf6'],
                ] as $sc): ?>
                <div class="col-4">
                  <div style="text-align:center;">
                    <div style="font-size:.7rem;color:var(--color-text-muted);margin-bottom:.25rem;"><?= $sc['label'] ?></div>
                    <div style="font-size:1.4rem;font-weight:700;color:<?= $sc['color'] ?>;"><?= $sc['score'] ?></div>
                    <div style="height:4px;background:rgba(0,0,0,.1);border-radius:4px;margin-top:.25rem;">
                      <div style="height:100%;width:<?= $sc['score'] ?>%;background:<?= $sc['color'] ?>;border-radius:4px;"></div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>

              <?php if (!empty($ans['eval_summary'])): ?>
              <div style="font-size:.825rem;color:var(--color-text-primary);margin-bottom:.5rem;line-height:1.5;">
                <strong>Summary:</strong> <?= e($ans['eval_summary']) ?>
              </div>
              <?php endif; ?>

              <?php if (!empty($ans['strengths'])): ?>
              <div style="font-size:.8rem;color:#22c55e;margin-bottom:.35rem;">
                <i class="bi bi-check-circle me-1"></i><strong>Strengths:</strong> <?= e($ans['strengths']) ?>
              </div>
              <?php endif; ?>

              <?php if (!empty($ans['weaknesses'])): ?>
              <div style="font-size:.8rem;color:#f59e0b;">
                <i class="bi bi-exclamation-circle me-1"></i><strong>Improve:</strong> <?= e($ans['weaknesses']) ?>
              </div>
              <?php endif; ?>
            </div>

            <?php elseif ($jobStatus === 'evaluating'): ?>
            <div style="font-size:.8rem;color:#8b5cf6;"><i class="bi bi-hourglass-split me-1"></i>Evaluating…</div>

            <?php elseif ($jobStatus === 'failed'): ?>
            <div style="font-size:.8rem;color:#ef4444;"><i class="bi bi-x-circle me-1"></i>Evaluation failed.</div>

            <?php elseif ($hasAudio && $aiReady): ?>
            <div style="font-size:.8rem;color:var(--color-text-muted);font-style:italic;">
              <i class="bi bi-stars me-1"></i>Not yet evaluated.
            </div>

            <?php else: ?>
            <div style="font-size:.8rem;color:var(--color-text-muted);font-style:italic;">
              <i class="bi bi-stars me-1"></i>AI evaluation available after Phase 5 setup.
            </div>
            <?php endif; ?>
          </div>

          <!-- Single-answer evaluate button -->
          <?php if ($hasAudio && $aiReady && !$hasEval && $jobStatus !== 'completed'): ?>
          <div class="mt-2">
            <button class="btn btn-sm"
                    id="eval-btn-<?= $answerId ?>"
                    onclick="evaluateSingle(<?= $answerId ?>)"
                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:6px;font-size:.8rem;padding:.3rem .75rem;font-weight:600;">
              <i class="bi bi-stars me-1"></i> Evaluate This Answer
            </button>
            <span id="eval-spinner-<?= $answerId ?>" style="display:none;font-size:.8rem;color:var(--color-text-muted);">
              <span class="spinner-border spinner-border-sm me-1"></span>Running AI…
            </span>
          </div>
          <?php endif; ?>

        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Right column: Integrity ── -->
  <div class="col-lg-5">

    <!-- Integrity Events Timeline -->
    <div class="content-card mb-3">
      <div class="content-card__header"><h2 class="content-card__title">Integrity Timeline</h2></div>
      <?php if (empty($intEvents)): ?>
        <div style="text-align:center;padding:1.5rem;color:var(--color-text-muted);font-size:.875rem;">
          <i class="bi bi-shield-check" style="font-size:1.5rem;display:block;margin-bottom:.5rem;color:#22c55e;"></i>
          No integrity violations recorded.
        </div>
      <?php else: ?>
        <div style="max-height:260px;overflow-y:auto;">
          <?php foreach ($intEvents as $ev): ?>
          <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--color-border);">
            <div style="width:8px;height:8px;border-radius:50%;margin-top:.35rem;flex-shrink:0;background:<?= $ev['severity'] === 'flag' ? '#ef4444' : '#f59e0b' ?>"></div>
            <div style="flex:1;">
              <div style="font-size:.8rem;font-weight:600;color:var(--color-text-primary);"><?= e($ev['event_type']) ?></div>
              <div style="font-size:.75rem;color:var(--color-text-muted);"><?= formatDate($ev['event_time'], 'M j, H:i:s') ?></div>
            </div>
            <span class="badge bg-<?= $ev['severity'] === 'flag' ? 'danger' : 'warning' ?>-subtle text-<?= $ev['severity'] === 'flag' ? 'danger' : 'warning' ?>"
                  style="font-size:.7rem;font-weight:700;"><?= strtoupper($ev['severity']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Tab Switch Log -->
    <div class="content-card mb-3">
      <div class="content-card__header"><h2 class="content-card__title">Tab Switch Log</h2></div>
      <?php if (empty($tabLog)): ?>
        <p style="font-size:.825rem;color:var(--color-text-muted);padding:.5rem 0;">No tab switches detected.</p>
      <?php else: ?>
        <div style="max-height:180px;overflow-y:auto;">
          <?php foreach ($tabLog as $t): ?>
          <div style="display:flex;justify-content:space-between;padding:.45rem 0;border-bottom:1px solid var(--color-border);font-size:.8rem;">
            <span style="color:<?= $t['event_type'] === 'TAB_HIDDEN' ? '#ef4444' : '#22c55e' ?>;font-weight:600;"><?= e($t['event_type']) ?></span>
            <span style="color:var(--color-text-muted);"><?= formatDate($t['timestamp'], 'H:i:s') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Fullscreen Log -->
    <div class="content-card">
      <div class="content-card__header"><h2 class="content-card__title">Fullscreen Log</h2></div>
      <?php if (empty($fsLog)): ?>
        <p style="font-size:.825rem;color:var(--color-text-muted);padding:.5rem 0;">No fullscreen events.</p>
      <?php else: ?>
        <div style="max-height:180px;overflow-y:auto;">
          <?php foreach ($fsLog as $f): ?>
          <div style="display:flex;justify-content:space-between;padding:.45rem 0;border-bottom:1px solid var(--color-border);font-size:.8rem;">
            <span style="color:<?= $f['event_type'] === 'FULLSCREEN_EXIT' ? '#ef4444' : '#22c55e' ?>;font-weight:600;"><?= e($f['event_type']) ?></span>
            <span style="color:var(--color-text-muted);"><?= formatDate($f['timestamp'], 'H:i:s') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Evaluation & Result Decision form -->
<div class="row mt-4 mb-4">
  <div class="col-12">
    <div class="content-card" style="padding: 1.5rem; border-top: 4px solid var(--color-primary);">
      <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0;">
          <i class="bi bi-file-earmark-check-fill me-1 text-primary"></i> Candidate Evaluation & Result Decision
        </h3>
        <?php if (!empty($resultData['published_at'])): ?>
          <span class="badge bg-success-subtle text-success border border-success" style="font-size: .8rem; font-weight: 600;">
            <i class="bi bi-check-circle-fill me-1"></i> Published on <?= formatDate($resultData['published_at']) ?>
          </span>
        <?php else: ?>
          <span class="badge bg-warning-subtle text-warning border border-warning" style="font-size: .8rem; font-weight: 600;">
            <i class="bi bi-hourglass-split me-1"></i> Draft (Not published)
          </span>
        <?php endif; ?>
      </div>

      <?php renderAlert(getFlash('attempt_review')); ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label" style="font-weight: 500; font-size: .9rem;">Selection Decision <span class="text-danger">*</span></label>
            <div class="d-flex flex-column gap-2 mt-1">
              <?php
              $currentDecision = $resultData['decision'] ?? 'pending';
              ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="decision_pending" value="pending" <?= $currentDecision === 'pending' ? 'checked' : '' ?>>
                <label class="form-check-label" for="decision_pending" style="font-size: .85rem; font-weight: 500; color: var(--color-text-muted);">
                  <i class="bi bi-clock me-1"></i> Pending Review
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="decision_selected" value="selected" <?= $currentDecision === 'selected' ? 'checked' : '' ?>>
                <label class="form-check-label" for="decision_selected" style="font-size: .85rem; font-weight: 600; color: var(--color-success);">
                  <i class="bi bi-check-circle-fill me-1"></i> Selected
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="decision_rejected" value="rejected" <?= $currentDecision === 'rejected' ? 'checked' : '' ?>>
                <label class="form-check-label" for="decision_rejected" style="font-size: .85rem; font-weight: 600; color: var(--color-danger);">
                  <i class="bi bi-x-circle-fill me-1"></i> Rejected
                </label>
              </div>
            </div>
          </div>

          <div class="col-md-9">
            <label for="conclusion" class="form-label" style="font-weight: 500; font-size: .9rem;">Recruiter Conclusion & Feedback <span class="text-danger">*</span></label>
            <textarea id="conclusion" name="conclusion" class="form-control" rows="4" required placeholder="State your conclusion, feedback, or any remarks about the candidate's performance..."><?= e($resultData['conclusion'] ?? '') ?></textarea>
            <div class="form-text">This description box is where you state the final evaluation. This will be visible to the candidate once the result is published.</div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
          <button type="submit" name="action" value="save_result" class="btn btn-sm btn-outline-secondary" style="border-radius: var(--radius-md); font-weight: 500; padding: .5rem 1.2rem;">
            <i class="bi bi-save me-1"></i> Save Draft
          </button>
          <button type="submit" name="action" value="publish_result" class="btn btn-sm btn-success" style="border-radius: var(--radius-md); font-weight: 600; padding: .5rem 1.5rem;">
            <i class="bi bi-send me-1"></i> Publish Result to Candidate
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Phase 5: AI Evaluation JS ─────────────────────────────────
const EVAL_URL = '<?= BASE_URL ?>/api/evaluate.php';

async function evaluateSingle(answerId) {
  const btn     = document.getElementById('eval-btn-' + answerId);
  const spinner = document.getElementById('eval-spinner-' + answerId);
  const card    = document.getElementById('answer-card-' + answerId);

  if (btn) btn.style.display = 'none';
  if (spinner) spinner.style.display = 'inline-flex';
  if (card) card.style.borderColor = '#8b5cf6';

  try {
    const res  = await fetch(EVAL_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'evaluate_answer', answer_id: answerId }),
    });
    const data = await res.json();

    if (data.ok) {
      // Reload page to show results
      location.reload();
    } else {
      if (spinner) spinner.style.display = 'none';
      if (btn) btn.style.display = 'inline-flex';
      if (card) card.style.borderColor = '#ef4444';
      alert('Evaluation failed: ' + (data.error ?? 'Unknown error'));
    }
  } catch (err) {
    if (spinner) spinner.style.display = 'none';
    if (btn) btn.style.display = 'inline-flex';
    alert('Network error: ' + err.message);
  }
}

async function batchEvaluate(attemptId) {
  const btn     = document.getElementById('btn-batch-eval');
  const progress = document.getElementById('batch-progress');
  const result  = document.getElementById('batch-result');
  const msgEl   = document.getElementById('batch-msg');

  btn.disabled = true;
  btn.style.opacity = '.6';
  progress.style.display = 'flex';
  result.style.display = 'none';

  msgEl.textContent = 'Sending audio to Groq Whisper…';

  try {
    const res  = await fetch(EVAL_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'batch_evaluate', attempt_id: attemptId }),
    });
    const data = await res.json();

    progress.style.display = 'none';
    btn.disabled = false;
    btn.style.opacity = '1';

    if (data.ok) {
      result.style.display = 'inline-flex';
      result.innerHTML =
        '<i class="bi bi-check-circle-fill me-1" style="color:#22c55e;"></i>' +
        data.success + '/' + data.total + ' answers evaluated successfully.';
      // Reload after short delay
      setTimeout(() => location.reload(), 1500);
    } else {
      result.style.display = 'inline-flex';
      result.style.color = '#ef4444';
      result.innerHTML =
        '<i class="bi bi-x-circle me-1"></i>' +
        (data.error ?? 'Batch evaluation failed.');
    }
  } catch (err) {
    progress.style.display = 'none';
    btn.disabled = false;
    btn.style.opacity = '1';
    alert('Network error: ' + err.message);
  }
}
</script>

<?php
    renderAdminFooter();
    exit;
}

// ─────────────────────────────────────────────────────────────
// Mode 2: List all attempts for an interview
// ─────────────────────────────────────────────────────────────
if ($interviewId <= 0) {
    redirect(BASE_URL . '/admin/interviews.php');
}

$db   = getDB();
$ivSt = $db->prepare('SELECT id, title, duration FROM interviews WHERE id = :id LIMIT 1');
$ivSt->execute([':id' => $interviewId]);
$interview = $ivSt->fetch();

if (!$interview) {
    flash('interviews', 'Interview not found.', 'error');
    redirect(BASE_URL . '/admin/interviews.php');
}

$attempts = getAttemptsForInterview($interviewId);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Attempts — ' . e($interview['title']), 'dashboard-page');
renderAdminNav('attempt_review');
?>

<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <a href="<?= BASE_URL ?>/admin/interviews.php"
       style="font-size:.8125rem;color:var(--color-text-muted);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.5rem;">
      <i class="bi bi-arrow-left"></i> Back to Interviews
    </a>
    <h1 class="page-header__title">Attempt Review</h1>
    <p class="page-header__subtitle"><?= e($interview['title']) ?> — <?= count($attempts) ?> submission<?= count($attempts) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<div class="data-table-wrap">
  <table class="data-table" id="attempts-table">
    <thead>
      <tr>
        <th>Candidate</th>
        <th>Status</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Duration</th>
        <th>Tab Switches</th>
        <th>FS Exits</th>
        <th>Camera Flags</th>
        <th>Risk</th>
        <th style="text-align:right;">Review</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($attempts)): ?>
        <tr>
          <td colspan="10">
            <div class="empty-state">
              <i class="bi bi-people empty-state__icon"></i>
              <p class="empty-state__title">No attempts yet</p>
              <p class="empty-state__sub">Candidates have not started this interview yet.</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($attempts as $att): ?>
          <?php
          $total    = (int) $att['total_violations'];
          $risk     = match(true) {
              $total === 0 => ['label' => 'Low',    'class' => 'success'],
              $total <= 2  => ['label' => 'Medium', 'class' => 'warning'],
              default      => ['label' => 'High',   'class' => 'danger'],
          };
          $durSec = (int) $att['duration_sec'];
          $durFmt = $durSec > 0
              ? sprintf('%02d:%02d', intdiv($durSec, 60), $durSec % 60)
              : '—';
          $stClass = match($att['status']) {
              'completed'   => 'success',
              'in_progress' => 'primary',
              'expired'     => 'danger',
              default       => 'secondary',
          };
          ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.875rem;color:var(--color-text-primary);"><?= e($att['full_name']) ?></div>
              <div style="font-size:.775rem;color:var(--color-text-muted);"><?= e($att['email']) ?></div>
            </td>
            <td><span class="badge bg-<?= $stClass ?>-subtle text-<?= $stClass ?>" style="border:1px solid currentColor;font-weight:600;"><?= ucfirst(str_replace('_', ' ', $att['status'])) ?></span></td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);"><?= $att['start_time'] ? formatDate($att['start_time'], 'M j, H:i') : '—' ?></td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);"><?= $att['end_time']   ? formatDate($att['end_time'],   'M j, H:i') : '—' ?></td>
            <td style="font-size:.875rem;"><?= $durFmt ?></td>
            <td style="font-size:.875rem;font-weight:600;color:<?= (int)$att['tab_switch_count'] > 0 ? '#f59e0b' : 'var(--color-text-muted)' ?>;"><?= (int) $att['tab_switch_count'] ?></td>
            <td style="font-size:.875rem;font-weight:600;color:<?= (int)$att['fullscreen_exit_count'] > 0 ? '#f59e0b' : 'var(--color-text-muted)' ?>;"><?= (int) $att['fullscreen_exit_count'] ?></td>
            <td style="font-size:.875rem;font-weight:600;color:<?= (int)$att['camera_violation_count'] > 0 ? '#ef4444' : 'var(--color-text-muted)' ?>;"><?= (int) $att['camera_violation_count'] ?></td>
            <td>
              <span class="badge bg-<?= $risk['class'] ?>-subtle text-<?= $risk['class'] ?>" style="border:1px solid currentColor;font-weight:700;"><?= $risk['label'] ?></span>
            </td>
            <td style="text-align:right;">
              <a href="<?= BASE_URL ?>/admin/attempt_review.php?attempt_id=<?= $att['id'] ?>"
                 class="action-btn action-btn--primary" title="Review attempt">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php renderAdminFooter(); ?>
