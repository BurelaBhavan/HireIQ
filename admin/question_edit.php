<?php
/**
 * Admin — Create / Edit Question
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$question = null;

if ($isEdit) {
    $question = getQuestionById($id);
    if (!$question) {
        flash('questions', 'Question not found.', 'error');
        redirect(BASE_URL . '/admin/questions.php');
    }
}

// ── Form Processing ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'question_text'   => trim($_POST['question_text'] ?? ''),
        'expected_topics' => trim($_POST['expected_topics'] ?? ''),
        'difficulty'      => $_POST['difficulty'] ?? 'Medium',
        'category'        => trim($_POST['category'] ?? ''),
        'created_by'      => $_SESSION['user_id'] ?? 0
    ];

    $errors = [];
    if (empty($data['question_text'])) {
        $errors[] = "Question text is required.";
    }
    if (empty($data['category'])) {
        $errors[] = "Category is required.";
    }

    if (empty($errors)) {
        if ($isEdit) {
            $success = updateQuestion($id, $data);
            $msg = 'Question updated successfully.';
        } else {
            $success = createQuestion($data);
            $msg = 'Question created successfully.';
        }

        if ($success) {
            flash('questions', $msg, 'success');
            redirect(BASE_URL . '/admin/questions.php');
        } else {
            $errors[] = "Database error. Could not save question.";
        }
    }
}

require_once __DIR__ . '/../includes/layout.php';
renderHeader($isEdit ? 'Edit Question' : 'Create Question', 'dashboard-page');
renderAdminNav('questions');
?>

<div class="page-header d-flex align-items-center gap-3">
  <a href="<?= BASE_URL ?>/admin/questions.php" class="btn btn-sm btn-outline-secondary" style="border-radius:var(--radius-md);">
    <i class="bi bi-arrow-left"></i> Back
  </a>
  <h1 class="page-header__title mb-0"><?= $isEdit ? 'Edit Question' : 'Create New Question' ?></h1>
</div>

<div class="row">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="content-card" style="padding: 2rem;">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        
        <div class="mb-3">
          <label for="category" class="form-label" style="font-weight: 500;">Category <span class="text-danger">*</span></label>
          <input type="text" id="category" name="category" class="form-control" required
                 placeholder="e.g., Technical, Behavioral, System Design" 
                 value="<?= e($_POST['category'] ?? $question['category'] ?? '') ?>">
          <div class="form-text">Group similar questions together.</div>
        </div>

        <div class="mb-3">
          <label for="difficulty" class="form-label" style="font-weight: 500;">Difficulty <span class="text-danger">*</span></label>
          <?php $currentDiff = $_POST['difficulty'] ?? $question['difficulty'] ?? 'Medium'; ?>
          <select id="difficulty" name="difficulty" class="form-select" required>
            <option value="Easy" <?= $currentDiff === 'Easy' ? 'selected' : '' ?>>Easy</option>
            <option value="Medium" <?= $currentDiff === 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="Hard" <?= $currentDiff === 'Hard' ? 'selected' : '' ?>>Hard</option>
          </select>
        </div>

        <div class="mb-3">
          <label for="question_text" class="form-label" style="font-weight: 500;">Question Text <span class="text-danger">*</span></label>
          <textarea id="question_text" name="question_text" class="form-control" rows="4" required placeholder="What is the question?"><?= e($_POST['question_text'] ?? $question['question_text'] ?? '') ?></textarea>
        </div>

        <div class="mb-4">
          <label for="expected_topics" class="form-label" style="font-weight: 500;">Expected Topics (Keywords)</label>
          <textarea id="expected_topics" name="expected_topics" class="form-control" rows="2" placeholder="e.g., Data, Patterns, Prediction, Training"><?= e($_POST['expected_topics'] ?? $question['expected_topics'] ?? '') ?></textarea>
          <div class="form-text">These keywords will be used later by the AI to evaluate candidate responses.</div>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <a href="<?= BASE_URL ?>/admin/questions.php" class="btn" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: .5rem 1rem;">Cancel</a>
          <button type="submit" class="btn" style="background: var(--color-primary); color: #fff; border-radius: var(--radius-md); padding: .5rem 1rem; font-weight: 500;">
            <?= $isEdit ? 'Save Changes' : 'Create Question' ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php renderAdminFooter(); ?>
