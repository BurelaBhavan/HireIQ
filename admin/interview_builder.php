<?php
/**
 * Admin — Interview Builder
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/interviews.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

$interviewId = (int) ($_GET['id'] ?? 0);
if ($interviewId <= 0) {
    redirect(BASE_URL . '/admin/interviews.php');
}

$interview = getInterviewById($interviewId);
if (!$interview) {
    flash('interviews', 'Interview not found.', 'error');
    redirect(BASE_URL . '/admin/interviews.php');
}

// ── Database operations for interview_questions ──────────────────
$db = getDB();

// Handle add question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $difficulty = $_POST['difficulty'] ?? 'Medium';
    
    if ($questionId > 0) {
        $stmt = $db->prepare("SELECT MAX(sequence_order) FROM interview_questions WHERE interview_id = ?");
        $stmt->execute([$interviewId]);
        $seq = (int) $stmt->fetchColumn() + 1;
        
        $stmt = $db->prepare("INSERT INTO interview_questions (interview_id, question_id, difficulty, sequence_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$interviewId, $questionId, $difficulty, $seq]);
        flash('builder', 'Question added to interview.', 'success');
    }
    redirect(BASE_URL . "/admin/interview_builder.php?id=$interviewId");
}

// Handle remove question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    verifyCsrf();
    $iqId = (int) ($_POST['iq_id'] ?? 0);
    if ($iqId > 0) {
        $stmt = $db->prepare("DELETE FROM interview_questions WHERE id = ? AND interview_id = ?");
        $stmt->execute([$iqId, $interviewId]);
        flash('builder', 'Question removed from interview.', 'success');
    }
    redirect(BASE_URL . "/admin/interview_builder.php?id=$interviewId");
}

// Handle create inline question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_inline') {
    verifyCsrf();
    $qText = trim($_POST['question_text'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'Medium';
    $expectedTopics = trim($_POST['expected_topics'] ?? '');
    
    if (empty($qText) || empty($category)) {
        flash('builder', 'Question text and Category are required for inline questions.', 'error');
    } else {
        $data = [
            'question_text' => $qText,
            'category' => $category,
            'difficulty' => $difficulty,
            'expected_topics' => $expectedTopics,
            'created_by' => $_SESSION['user_id'] ?? 0,
            'question_source' => 'interview',
            'interview_id_ref' => $interviewId
        ];
        
        $qId = createQuestion($data);
        if ($qId > 0) {
            $stmt = $db->prepare("SELECT MAX(sequence_order) FROM interview_questions WHERE interview_id = ?");
            $stmt->execute([$interviewId]);
            $seq = (int) $stmt->fetchColumn() + 1;
            
            $stmt = $db->prepare("INSERT INTO interview_questions (interview_id, question_id, difficulty, sequence_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$interviewId, $qId, $difficulty, $seq]);
            
            flash('builder', 'Interview-specific question created and added.', 'success');
        } else {
            flash('builder', 'Failed to create interview-specific question.', 'error');
        }
    }
    redirect(BASE_URL . "/admin/interview_builder.php?id=$interviewId");
}

// Get current questions in interview
$stmt = $db->prepare("
    SELECT iq.*, q.question_text, q.category 
    FROM interview_questions iq
    JOIN questions q ON iq.question_id = q.id
    WHERE iq.interview_id = ?
    ORDER BY iq.sequence_order ASC
");
$stmt->execute([$interviewId]);
$interviewQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$diffCounts = ['Easy' => 0, 'Medium' => 0, 'Hard' => 0];
foreach ($interviewQuestions as $iq) {
    $diffCounts[$iq['difficulty']]++;
}
$totalQ = count($interviewQuestions);

// Get available questions from Question Bank
$filters = [
    'search' => trim($_GET['q'] ?? ''),
    'category' => $_GET['cat'] ?? ''
];
$allQuestions = getQuestions($filters);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Interview Builder', 'dashboard-page');
renderAdminNav('interviews');
?>

<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <div class="d-flex align-items-center gap-3 mb-2">
      <a href="<?= BASE_URL ?>/admin/interviews.php" class="btn btn-sm btn-outline-secondary" style="border-radius:var(--radius-md);">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <h1 class="page-header__title mb-0">Interview Builder</h1>
    </div>
    <p class="page-header__subtitle">Manage questions for <strong><?= e($interview['title']) ?></strong></p>
  </div>
</div>

<?php renderAlert(getFlash('builder')); ?>

<!-- ── Stats ── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-primary);"><?= $totalQ ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Total Questions</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-success);"><?= $diffCounts['Easy'] ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Easy</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);"><?= $diffCounts['Medium'] ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Medium</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="content-card" style="padding:1rem;">
      <div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);"><?= $diffCounts['Hard'] ?></div>
      <div style="font-size:.775rem;color:var(--color-text-muted);">Hard</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- ── Current Questions in Interview ── -->
  <div class="col-lg-7">
    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Selected Questions</h3>
    <?php if (empty($interviewQuestions)): ?>
      <div class="empty-state" style="background: var(--color-bg); border-radius: var(--radius-md);">
        <i class="bi bi-card-checklist empty-state__icon"></i>
        <p class="empty-state__title">No questions added</p>
        <p class="empty-state__sub">Add questions from the Question Bank on the right.</p>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($interviewQuestions as $index => $iq): ?>
          <div class="content-card" style="padding: 1rem; border-left: 4px solid var(--color-primary);">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div style="font-weight: 600; font-size: .95rem;">
                <span class="text-muted me-2">Q<?= $index + 1 ?></span>
                <?= nl2br(e($iq['question_text'])) ?>
              </div>
              <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="iq_id" value="<?= $iq['id'] ?>" />
                <button class="btn btn-sm text-danger" title="Remove" style="padding: 0;"><i class="bi bi-x-circle"></i></button>
              </form>
            </div>
            <div class="d-flex gap-2">
              <span class="badge bg-light text-dark" style="border: 1px solid var(--color-border); font-weight: 500;"><?= e($iq['category']) ?></span>
              <?php 
              $dCol = match($iq['difficulty']) { 'Easy' => 'success', 'Medium' => 'warning', 'Hard' => 'danger', default => 'secondary' };
              ?>
              <span class="badge bg-<?= $dCol ?>-subtle text-<?= $dCol ?>" style="border: 1px solid currentColor; font-weight: 600;">
                Assigned: <?= e($iq['difficulty']) ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Inline Create Question Form -->
    <div class="content-card mt-4" style="padding: 1.5rem; border-top: 4px solid var(--color-primary);">
      <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--color-text-primary);">
        <i class="bi bi-plus-circle-fill me-1 text-primary"></i> Create Interview-Specific Question
      </h4>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="action" value="create_inline" />
        
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label for="category" class="form-label" style="font-size: .8rem; font-weight: 500; margin-bottom: .25rem;">Category <span class="text-danger">*</span></label>
            <input type="text" id="category" name="category" class="form-control form-control-sm" required placeholder="e.g. Technical, Behavioral">
          </div>
          <div class="col-md-6">
            <label for="difficulty" class="form-label" style="font-size: .8rem; font-weight: 500; margin-bottom: .25rem;">Difficulty <span class="text-danger">*</span></label>
            <select id="difficulty" name="difficulty" class="form-select form-select-sm" required>
              <option value="Easy">Easy</option>
              <option value="Medium" selected>Medium</option>
              <option value="Hard">Hard</option>
            </select>
          </div>
        </div>
        
        <div class="mb-3">
          <label for="question_text" class="form-label" style="font-size: .8rem; font-weight: 500; margin-bottom: .25rem;">Question Text <span class="text-danger">*</span></label>
          <textarea id="question_text" name="question_text" class="form-control form-control-sm" rows="3" required placeholder="Type your interview-specific question here..."></textarea>
        </div>
        
        <div class="mb-3">
          <label for="expected_topics" class="form-label" style="font-size: .8rem; font-weight: 500; margin-bottom: .25rem;">Expected Topics / Keywords</label>
          <input type="text" id="expected_topics" name="expected_topics" class="form-control form-control-sm" placeholder="e.g. loops, arrays, big-o (comma separated)">
        </div>
        
        <button type="submit" class="btn btn-sm btn-primary w-100" style="font-weight: 500;">
          Create and Add to Interview
        </button>
      </form>
    </div>
  </div>

  <!-- ── Question Bank Search ── -->
  <div class="col-lg-5">
    <div class="content-card" style="padding: 1rem; position: sticky; top: 1rem;">
      <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Question Bank</h3>
      
      <form method="GET" action="" class="mb-3 d-flex gap-2">
        <input type="hidden" name="id" value="<?= $interviewId ?>">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= e($filters['search']) ?>">
        <button type="submit" class="btn btn-sm btn-primary">Search</button>
      </form>

      <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;" class="d-flex flex-column gap-2">
        <?php foreach ($allQuestions as $q): ?>
          <div class="border rounded p-2" style="font-size: .85rem; background: var(--color-bg);">
            <div style="font-weight: 500; margin-bottom: .5rem;"><?= nl2br(e($q['question_text'])) ?></div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <span class="badge bg-light text-dark border"><?= e($q['category']) ?></span>
              <form method="POST" action="" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                <input type="hidden" name="action" value="add" />
                <input type="hidden" name="question_id" value="<?= $q['id'] ?>" />
                
                <select name="difficulty" class="form-select form-select-sm" style="width: 85px;">
                  <option value="Easy" <?= $q['difficulty'] === 'Easy' ? 'selected' : '' ?>>Easy</option>
                  <option value="Medium" <?= $q['difficulty'] === 'Medium' ? 'selected' : '' ?>>Med</option>
                  <option value="Hard" <?= $q['difficulty'] === 'Hard' ? 'selected' : '' ?>>Hard</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary py-1 px-2" title="Add to Interview"><i class="bi bi-plus-lg"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($allQuestions)): ?>
          <div class="text-muted text-center py-3">No questions found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php renderAdminFooter(); ?>
