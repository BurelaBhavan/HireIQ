<?php
/**
 * Admin — Test Management
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/tests.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

// ── POST handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title      = trim($_POST['title']       ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $duration   = max(1, (int) ($_POST['duration'] ?? 30));
        $status     = $_POST['status']     ?? 'draft';

        $validStatus = ['draft', 'active', 'archived'];

        if ($title !== '' && in_array($status, $validStatus, true)) {
            createTest($title, $desc, $duration, $status, (int) $_SESSION['user_id']);
            flash('tests', 'Test created successfully.', 'success');
        } else {
            flash('tests', 'Please fill in all required fields.', 'error');
        }

    } elseif ($action === 'edit') {
        $id         = (int) ($_POST['test_id'] ?? 0);
        $title      = trim($_POST['title']       ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $duration   = max(1, (int) ($_POST['duration'] ?? 30));
        $status     = $_POST['status']     ?? 'draft';

        if ($id > 0 && $title !== '') {
            updateTest($id, $title, $desc, $duration, $status);
            flash('tests', 'Test updated successfully.', 'success');
        }

    } elseif ($action === 'status') {
        $id        = (int) ($_POST['test_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $validSt   = ['draft', 'active', 'archived'];
        if ($id > 0 && in_array($newStatus, $validSt, true)) {
            setTestStatus($id, $newStatus);
            $label = ucfirst($newStatus);
            flash('tests', "Test set to {$label}.", 'success');
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['test_id'] ?? 0);
        if ($id > 0 && deleteTest($id)) {
            flash('tests', 'Test deleted.', 'success');
        }
    }

    redirect(BASE_URL . '/admin/tests.php');
}

// ── GET ────────────────────────────────────────────────────────
$showCreate = ($_GET['action'] ?? '') === 'create';
$editId     = (int) ($_GET['edit'] ?? 0);
$editData   = $editId > 0 ? getTestById($editId) : null;

$search  = trim($_GET['q']      ?? '');
$status  = $_GET['status']      ?? '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$result = getAllTests($search, $status, $page, $perPage);
$rows   = $result['rows'];
$total  = $result['total'];
$pages  = (int) ceil($total / $perPage);
$stats  = getTestStats();

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Tests', 'dashboard-page');
renderAdminNav('tests');
?>

<!-- Page header -->
<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">Test Management</h1>
    <p class="page-header__subtitle">Create and manage separate tests with custom questions.</p>
  </div>
  <a href="<?= BASE_URL ?>/admin/tests.php?action=create"
     class="btn btn-sm"
     style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:.4rem;text-decoration:none;">
    <i class="bi bi-plus-lg"></i> New Test
  </a>
</div>

<!-- Flash -->
<?php renderAlert(getFlash('tests')); ?>

<!-- ── Stat mini cards ── -->
<div class="row g-3 mb-4">
  <?php
  $testMini = [
    ['label' => 'Total Tests', 'value' => $stats['total'],  'icon' => 'bi-journal-check', 'color' => 'var(--color-primary)'],
    ['label' => 'Active',      'value' => $stats['active'], 'icon' => 'bi-play-circle',   'color' => 'var(--color-success)'],
    ['label' => 'Draft',       'value' => $stats['draft'],  'icon' => 'bi-pencil',        'color' => 'var(--color-warning)'],
  ];
  foreach ($testMini as $s): ?>
    <div class="col-12 col-md-4">
      <div class="content-card" style="padding:1rem 1.25rem;">
        <div class="d-flex align-items-center gap-3">
          <div style="width:38px;height:38px;border-radius:var(--radius-md);background:var(--color-bg-gray);display:flex;align-items:center;justify-content:center;color:<?= $s['color'] ?>;flex-shrink:0;font-size:1.125rem;">
            <i class="bi <?= $s['icon'] ?>"></i>
          </div>
          <div>
            <div style="font-size:1.375rem;font-weight:700;font-family:var(--font-heading);line-height:1;"><?= $s['value'] ?></div>
            <div style="font-size:.775rem;color:var(--color-text-muted);margin-top:.15rem;"><?= $s['label'] ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ── Create / Edit Form ── -->
<?php if ($showCreate || $editData): ?>
  <?php $isEdit = (bool) $editData; ?>
  <div class="content-card mb-4" id="test-form-card">
    <div class="content-card__header">
      <h2 class="content-card__title"><?= $isEdit ? 'Edit Test' : 'Create New Test' ?></h2>
      <a href="<?= BASE_URL ?>/admin/tests.php"
         style="font-size:.8125rem;color:var(--color-text-muted);">
        <i class="bi bi-x-lg"></i> Cancel
      </a>
    </div>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
      <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'create' ?>" />
      <?php if ($isEdit): ?>
        <input type="hidden" name="test_id" value="<?= $editData['id'] ?>" />
      <?php endif; ?>

      <div class="row g-3">
        <!-- Title -->
        <div class="col-md-8">
          <label class="form-label" for="t-title">Test Title <span style="color:var(--color-danger);">*</span></label>
          <input type="text" id="t-title" name="title" class="form-control" placeholder="e.g. Cognitive Ability Test" value="<?= $isEdit ? e($editData['title']) : '' ?>" required />
        </div>

        <!-- Duration -->
        <div class="col-md-2">
          <label class="form-label" for="t-duration">Duration (mins)</label>
          <input type="number" id="t-duration" name="duration" class="form-control" min="5" max="480" value="<?= $isEdit ? (int) $editData['duration'] : 30 ?>" />
        </div>

        <!-- Status -->
        <div class="col-md-2">
          <label class="form-label" for="t-status">Status</label>
          <select id="t-status" name="status" class="form-select">
            <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($isEdit && $editData['status'] === $val) ? 'selected' : (!$isEdit && $val === 'draft' ? 'selected' : '') ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Description -->
        <div class="col-12">
          <label class="form-label" for="t-desc">Description</label>
          <textarea id="t-desc" name="description" class="form-control" rows="3" placeholder="Describe the test…"><?= $isEdit ? e($editData['description'] ?? '') : '' ?></textarea>
        </div>

        <!-- Submit -->
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm" style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.55rem 1.25rem;font-size:.875rem;font-weight:500;">
            <?= $isEdit ? '<i class="bi bi-check2"></i> Save Changes' : '<i class="bi bi-plus-lg"></i> Create Test' ?>
          </button>
          <a href="<?= BASE_URL ?>/admin/tests.php" class="btn btn-sm" style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.55rem 1rem;font-size:.875rem;color:var(--color-text-secondary);text-decoration:none;">Cancel</a>
        </div>
      </div>
    </form>
  </div>
<?php endif; ?>

<!-- ── Filter bar ── -->
<form method="GET" action="" class="filter-bar mb-0">
  <div class="input-group search-input" style="max-width:280px;">
    <span class="input-group-text" style="background:var(--color-bg);border:1px solid var(--color-border-strong);border-right:none;border-radius:var(--radius-md) 0 0 var(--radius-md);color:var(--color-text-muted);"><i class="bi bi-search"></i></span>
    <input type="text" name="q" class="form-control" placeholder="Search tests…" value="<?= e($search) ?>" style="border-left:none;border-radius:0 var(--radius-md) var(--radius-md) 0;" />
  </div>

  <select name="status" class="form-select" style="width:auto;flex-shrink:0;" onchange="this.form.submit()">
    <option value="" <?= $status === '' ? 'selected' : '' ?>>All Statuses</option>
    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
  </select>

  <button type="submit" class="btn btn-sm" style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem .875rem;font-size:.875rem;font-weight:500;">Search</button>
  <?php if ($search !== '' || $status !== ''): ?>
    <a href="<?= BASE_URL ?>/admin/tests.php" style="font-size:.8125rem;color:var(--color-text-muted);text-decoration:none;display:flex;align-items:center;gap:.25rem;"><i class="bi bi-x-circle"></i> Clear</a>
  <?php endif; ?>
</form>

<!-- ── Table ── -->
<div class="data-table-wrap">
  <table class="data-table" id="tests-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Title</th>
        <th>Duration</th>
        <th>Questions</th>
        <th>Status</th>
        <th>Created</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <i class="bi bi-journal-check empty-state__icon"></i>
              <p class="empty-state__title">No tests found</p>
              <p class="empty-state__sub"><?= ($search !== '' || $status !== '') ? 'Try adjusting your search or filter.' : 'Click "New Test" to create your first test.' ?></p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td style="color:var(--color-text-muted);font-size:.8rem;">#<?= $row['id'] ?></td>
            <td>
              <div style="font-weight:600;font-size:.875rem;color:var(--color-text-primary);"><?= e($row['title']) ?></div>
              <?php if (!empty($row['description'])): ?>
                <div style="font-size:.775rem;color:var(--color-text-muted);margin-top:.15rem;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($row['description']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:.875rem;color:var(--color-text-secondary);"><?= $row['duration'] ?> min</td>
            <td style="font-size:.875rem;"><?= (int) $row['question_count'] ?></td>
            <td><span class="status-badge status-badge--<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);"><?= formatDate($row['created_at']) ?></td>
            <td>
              <div class="d-flex gap-1 justify-content-end">
                <a href="<?= BASE_URL ?>/admin/tests.php?edit=<?= $row['id'] ?>" class="action-btn action-btn--primary" title="Edit details"><i class="bi bi-pencil"></i></a>
                <a href="<?= BASE_URL ?>/admin/test_builder.php?id=<?= $row['id'] ?>" class="action-btn action-btn--success" title="Manage questions"><i class="bi bi-list-check"></i></a>
                <a href="<?= BASE_URL ?>/admin/test_assign.php?id=<?= $row['id'] ?>" class="action-btn" style="background: #8b5cf6; color: white;" title="Assign to candidates"><i class="bi bi-person-plus"></i></a>
                
                <?php if ($row['status'] === 'draft'): ?>
                  <form method="POST" action="" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                    <input type="hidden" name="action" value="status" />
                    <input type="hidden" name="test_id" value="<?= $row['id'] ?>" />
                    <input type="hidden" name="new_status" value="active" />
                    <button type="submit" class="action-btn action-btn--success" title="Publish"><i class="bi bi-play-circle"></i></button>
                  </form>
                <?php elseif ($row['status'] === 'active'): ?>
                  <form method="POST" action="" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                    <input type="hidden" name="action" value="status" />
                    <input type="hidden" name="test_id" value="<?= $row['id'] ?>" />
                    <input type="hidden" name="new_status" value="archived" />
                    <button type="submit" class="action-btn action-btn--warning" title="Archive"><i class="bi bi-archive"></i></button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                    <input type="hidden" name="action" value="status" />
                    <input type="hidden" name="test_id" value="<?= $row['id'] ?>" />
                    <input type="hidden" name="new_status" value="draft" />
                    <button type="submit" class="action-btn" title="Restore to Draft"><i class="bi bi-arrow-counterclockwise"></i></button>
                  </form>
                <?php endif; ?>

                <button class="action-btn action-btn--danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteTestModal" data-test-id="<?= $row['id'] ?>" data-test-title="<?= e($row['title']) ?>"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  
  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div class="pagination-bar">
      <span>Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?></span>
      <div class="d-flex gap-1">
        <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
          <a class="page-link <?= $p === $page ? 'active' : '' ?>" href="?q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteTestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Test</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">
        <p style="color:var(--color-text-secondary);font-size:.9rem;">Are you sure you want to permanently delete <strong id="deleteTestTitle"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm" style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.45rem .875rem;" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="test_id" id="deleteTestId" value="" />
          <button type="submit" class="btn btn-sm btn-danger" style="border-radius:var(--radius-md);padding:.45rem .875rem;">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteTestModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('deleteTestId').value = btn.dataset.testId;
  document.getElementById('deleteTestTitle').textContent = btn.dataset.testTitle;
});
</script>

<?php renderAdminFooter(); ?>
