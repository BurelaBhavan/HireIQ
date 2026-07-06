<?php
/**
 * Admin — Test Builder
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/tests.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

$testId = (int) ($_GET['id'] ?? 0);
if ($testId <= 0) {
    redirect(BASE_URL . '/admin/tests.php');
}

$test = getTestById($testId);
if (!$test) {
    flash('tests', 'Test not found.', 'error');
    redirect(BASE_URL . '/admin/tests.php');
}

$db = getDB();

// ── Handle Add/Remove ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $qText  = trim($_POST['question_text'] ?? '');
        $diff   = $_POST['difficulty'] ?? 'Medium';
        $cat    = trim($_POST['category'] ?? 'General');
        $marks  = max(0.5, (float) ($_POST['marks'] ?? 1.0));

        if ($qText !== '') {
            $stmt = $db->prepare("INSERT INTO test_questions (test_id, question_text, difficulty, category, marks) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$testId, $qText, $diff, $cat, $marks]);
            flash('test_builder', 'Question added to test.', 'success');
        } else {
            flash('test_builder', 'Question text is required.', 'error');
        }
    } elseif ($action === 'remove') {
        $tqId = (int) ($_POST['tq_id'] ?? 0);
        if ($tqId > 0) {
            $stmt = $db->prepare("DELETE FROM test_questions WHERE id = ? AND test_id = ?");
            $stmt->execute([$tqId, $testId]);
            flash('test_builder', 'Question removed.', 'success');
        }
    } elseif ($action === 'import') {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $marks  = max(0.5, (float) ($_POST['import_marks'] ?? 1.0));
        
        $qInfo = getQuestionById($questionId);
        if ($qInfo) {
            $stmt = $db->prepare("INSERT INTO test_questions (test_id, question_text, difficulty, category, marks) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$testId, $qInfo['question_text'], $qInfo['difficulty'], $qInfo['category'], $marks]);
            flash('test_builder', 'Question imported from bank.', 'success');
        } else {
            flash('test_builder', 'Selected question not found.', 'error');
        }
    }
    
    redirect(BASE_URL . "/admin/test_builder.php?id=$testId");
}

// ── Get Questions ──────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY id ASC");
$stmt->execute([$testId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bankQuestions = getQuestions();

$totalMarks = 0;
$diffCounts = ['Easy' => 0, 'Medium' => 0, 'Hard' => 0];
foreach ($questions as $q) {
    $totalMarks += (float) $q['marks'];
    $diffCounts[$q['difficulty']]++;
}

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Test Builder', 'dashboard-page');
renderAdminNav('tests');
?>

<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <div class="d-flex align-items-center gap-3 mb-2">
      <a href="<?= BASE_URL ?>/admin/tests.php" class="btn btn-sm btn-outline-secondary" style="border-radius:var(--radius-md);">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <h1 class="page-header__title mb-0">Test Builder</h1>
    </div>
    <p class="page-header__subtitle">Manage direct questions for <strong><?= e($test['title']) ?></strong></p>
  </div>
</div>

<?php renderAlert(getFlash('test_builder')); ?>

<!-- ── Stats ── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-primary);"><?= count($questions) ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Total Questions</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-success);"><?= number_format($totalMarks, 1) ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Total Marks</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);"><?= $diffCounts['Medium'] ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Medium Questions</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);"><?= $diffCounts['Hard'] ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Hard Questions</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- ── Current Questions in Test ── -->
  <div class="col-lg-7">
    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Test Questions</h3>
    <?php if (empty($questions)): ?>
      <div class="empty-state" style="background: var(--color-bg); border-radius: var(--radius-md);">
        <i class="bi bi-file-earmark-text empty-state__icon"></i>
        <p class="empty-state__title">No questions added</p>
        <p class="empty-state__sub">Create your first question for this test.</p>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($questions as $index => $q): ?>
          <div class="content-card" style="padding: 1rem; border-left: 4px solid var(--color-primary);">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div style="font-weight: 600; font-size: .95rem;">
                <span class="text-muted me-2">Q<?= $index + 1 ?></span>
                <?= nl2br(e($q['question_text'])) ?>
              </div>
              <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="tq_id" value="<?= $q['id'] ?>" />
                <button class="btn btn-sm text-danger" title="Remove" style="padding: 0;"><i class="bi bi-trash"></i></button>
              </form>
            </div>
            <div class="d-flex gap-2 align-items-center mt-3">
              <span class="badge bg-light text-dark" style="border: 1px solid var(--color-border); font-weight: 500;">
                <i class="bi bi-tag"></i> <?= e($q['category'] ?: 'General') ?>
              </span>
              <?php 
              $dCol = match($q['difficulty']) { 'Easy' => 'success', 'Medium' => 'warning', 'Hard' => 'danger', default => 'secondary' };
              ?>
              <span class="badge bg-<?= $dCol ?>-subtle text-<?= $dCol ?>" style="border: 1px solid currentColor; font-weight: 600;">
                <?= e($q['difficulty']) ?>
              </span>
              <span class="ms-auto" style="font-weight: 600; font-size: .85rem; color: var(--color-text-secondary);">
                <?= number_format((float)$q['marks'], 1) ?> Marks
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Add/Import Forms ── -->
  <div class="col-lg-5">
    
    <!-- Import Form -->
    <div class="content-card mb-4" style="padding: 1.5rem;">
      <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Import from Bank</h3>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="action" value="import" />
        <div class="mb-3">
          <label class="form-label" style="font-size:.875rem; font-weight:500;">Select Question <span class="text-danger">*</span></label>
          <select name="question_id" class="form-select" required>
            <option value="">-- Choose Question --</option>
            <?php foreach ($bankQuestions as $bq): ?>
              <option value="<?= $bq['id'] ?>">
                [<?= e($bq['category']) ?>] <?= e(substr($bq['question_text'], 0, 50)) ?>... (<?= e($bq['difficulty']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" style="font-size:.875rem; font-weight:500;">Marks</label>
          <input type="number" name="import_marks" class="form-control form-control-sm" step="0.5" min="0.5" value="1.0" required>
        </div>
        <button type="submit" class="btn w-100" style="background:var(--color-primary); color:#fff; font-weight:500;">
          <i class="bi bi-download"></i> Import Question
        </button>
      </form>
    </div>

    <!-- Custom Form -->
    <div class="content-card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Create Custom Question</h3>
      
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="action" value="add" />

        <div class="mb-3">
          <label class="form-label" style="font-size:.875rem; font-weight:500;">Question Text <span class="text-danger">*</span></label>
          <textarea name="question_text" class="form-control" rows="3" required placeholder="Type your question here..."></textarea>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label" style="font-size:.875rem; font-weight:500;">Difficulty</label>
            <select name="difficulty" class="form-select form-select-sm">
              <option value="Easy">Easy</option>
              <option value="Medium" selected>Medium</option>
              <option value="Hard">Hard</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label" style="font-size:.875rem; font-weight:500;">Marks</label>
            <input type="number" name="marks" class="form-control form-control-sm" step="0.5" min="0.5" value="1.0" required>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label" style="font-size:.875rem; font-weight:500;">Category</label>
          <input type="text" name="category" class="form-control form-control-sm" placeholder="e.g., Logical Reasoning">
        </div>

        <button type="submit" class="btn btn-sm w-100" style="background:var(--color-primary); border:1px solid var(--color-primary); color:#fff; font-weight:500;">
          <i class="bi bi-plus-lg"></i> Create & Add
        </button>
      </form>
    </div>
  </div>
</div>

<?php renderAdminFooter(); ?>
