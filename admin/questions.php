<?php
/**
 * Admin — Question Bank
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

// ── POST handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['question_id'] ?? 0);

    if ($id > 0 && $action === 'delete') {
        if (deleteQuestion($id)) {
            flash('questions', 'Question deleted successfully.', 'success');
        } else {
            flash('questions', 'Could not delete question.', 'error');
        }
    }
    redirect(BASE_URL . '/admin/questions.php');
}

// ── GET params ─────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'bank';
if (!in_array($tab, ['bank', 'interview'])) {
    $tab = 'bank';
}

$filters = [
    'search'          => trim($_GET['q'] ?? ''),
    'difficulty'      => $_GET['difficulty'] ?? '',
    'category'        => $_GET['category'] ?? '',
    'question_source' => $tab
];

$questions  = getQuestions($filters);
$categories = getUniqueCategories();

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Question Bank', 'dashboard-page');
renderAdminNav('questions');
?>

<!-- Page header -->
<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">Question Bank</h1>
    <p class="page-header__subtitle">Manage interview questions and their expected evaluation topics.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/admin/question_edit.php" class="btn btn-sm" style="background:var(--color-primary);color:#fff;border-radius:var(--radius-md);padding:.5rem 1rem;font-weight:500;">
      <i class="bi bi-plus-lg"></i> Add Question
    </a>
  </div>
</div>

<!-- Flash -->
<?php renderAlert(getFlash('questions')); ?>

<!-- Tabs for Question Types -->
<ul class="nav nav-tabs mb-4" id="questionTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <a class="nav-link <?= $tab === 'bank' ? 'active' : '' ?>" href="?tab=bank" style="font-weight: 600; color: <?= $tab === 'bank' ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>;">
      <i class="bi bi-bank me-1"></i> General Question Bank
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link <?= $tab === 'interview' ? 'active' : '' ?>" href="?tab=interview" style="font-weight: 600; color: <?= $tab === 'interview' ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>;">
      <i class="bi bi-camera-video me-1"></i> Interview-Specific Questions
    </a>
  </li>
</ul>

<!-- ── Filter bar ── -->
<form method="GET" action="" class="filter-bar mb-4">
  <input type="hidden" name="tab" value="<?= e($tab) ?>" />
  <div class="input-group search-input" style="max-width:320px;">
    <span class="input-group-text" style="background:var(--color-bg);border:1px solid var(--color-border-strong);border-right:none;border-radius:var(--radius-md) 0 0 var(--radius-md);color:var(--color-text-muted);">
      <i class="bi bi-search"></i>
    </span>
    <input
      type="text"
      name="q"
      class="form-control"
      placeholder="Search questions or topics…"
      value="<?= e($filters['search']) ?>"
      style="border-left:none;border-radius:0 var(--radius-md) var(--radius-md) 0;"
    />
  </div>

  <select name="difficulty" class="form-select" style="width:auto;flex-shrink:0;" onchange="this.form.submit()">
    <option value="">All Difficulties</option>
    <option value="Easy" <?= $filters['difficulty'] === 'Easy' ? 'selected' : '' ?>>Easy</option>
    <option value="Medium" <?= $filters['difficulty'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
    <option value="Hard" <?= $filters['difficulty'] === 'Hard' ? 'selected' : '' ?>>Hard</option>
  </select>

  <select name="category" class="form-select" style="width:auto;flex-shrink:0;" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
    <?php endforeach; ?>
  </select>

  <button type="submit" class="btn btn-sm"
          style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem .875rem;font-size:.875rem;font-weight:500;">
    Search
  </button>

  <?php if (!empty(array_filter($filters))): ?>
    <a href="<?= BASE_URL ?>/admin/questions.php?tab=<?= $tab ?>"
       style="font-size:.8125rem;color:var(--color-text-muted);text-decoration:none;display:flex;align-items:center;gap:.25rem;">
      <i class="bi bi-x-circle"></i> Clear
    </a>
  <?php endif; ?>
</form>

<!-- ── Table ── -->
<div class="data-table-wrap">
  <table class="data-table" id="questions-table">
    <thead>
      <tr>
        <th style="width: 5%">#</th>
        <th style="width: 45%">Question</th>
        <th style="width: 15%">Category</th>
        <th style="width: 10%">Difficulty</th>
        <th style="width: 15%">Created By</th>
        <th style="text-align:right; width: 10%">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($questions)): ?>
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <i class="bi bi-patch-question empty-state__icon"></i>
              <p class="empty-state__title">No questions found</p>
              <p class="empty-state__sub">Create a new question to build your Question Bank.</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($questions as $row): ?>
          <tr>
            <td style="color:var(--color-text-muted);font-size:.8rem;"><?= $row['id'] ?></td>
            <td>
              <div style="font-weight: 500; color: var(--color-text-primary); margin-bottom: 4px;">
                <?= nl2br(e(mb_strimwidth($row['question_text'], 0, 80, '...'))) ?>
              </div>
              <?php if (!empty($row['expected_topics'])): ?>
                <div style="font-size: .8rem; color: var(--color-text-muted);">
                  <i class="bi bi-tags"></i> <?= e($row['expected_topics']) ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($row['interview_title'])): ?>
                <div style="font-size: .8rem; margin-top: 4px;">
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-weight: 500;">
                    <i class="bi bi-camera-video me-1"></i><?= e($row['interview_title']) ?>
                  </span>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-light text-dark" style="font-weight: 500; font-size: .75rem; border: 1px solid var(--color-border);"><?= e($row['category']) ?></span>
            </td>
            <td>
              <?php 
              $diffColor = match($row['difficulty']) {
                  'Easy' => 'success',
                  'Medium' => 'warning',
                  'Hard' => 'danger',
                  default => 'secondary'
              };
              ?>
              <span class="badge bg-<?= $diffColor ?>-subtle text-<?= $diffColor ?>" style="font-weight: 600; font-size: .75rem; border: 1px solid currentColor;">
                <?= e($row['difficulty']) ?>
              </span>
            </td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);">
              <?= e($row['creator_name']) ?><br>
              <small style="color: var(--color-text-muted);"><?= formatDate($row['created_at']) ?></small>
            </td>
            <td>
              <div class="d-flex gap-1 justify-content-end">
                <a href="<?= BASE_URL ?>/admin/question_edit.php?id=<?= $row['id'] ?>"
                   class="action-btn action-btn--primary"
                   title="Edit question">
                  <i class="bi bi-pencil"></i>
                </a>
                <button
                  class="action-btn action-btn--danger"
                  title="Delete question"
                  data-bs-toggle="modal"
                  data-bs-target="#deleteModal"
                  data-question-id="<?= $row['id'] ?>">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ── Delete Confirmation Modal ── -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Delete Question</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">
        <p style="color:var(--color-text-secondary);font-size:.9rem;">
          Are you sure you want to permanently delete this question? This action cannot be undone.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm"
                style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.45rem .875rem;font-size:.875rem;"
                data-bs-dismiss="modal">
          Cancel
        </button>
        <form method="POST" action="" id="deleteForm" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="question_id" id="deleteModalId" value="" />
          <button type="submit"
                  class="btn btn-sm"
                  style="background:var(--color-danger);color:#fff;border:none;border-radius:var(--radius-md);padding:.45rem .875rem;font-size:.875rem;font-weight:500;">
            Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('deleteModalId').value = btn.dataset.questionId;
});
</script>

<?php renderAdminFooter(); ?>
