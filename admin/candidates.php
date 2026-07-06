<?php
/**
 * Admin — Candidate Management
 * AI Interview Assessment Platform — Phase 2
 *
 * Actions handled via POST (CSRF-protected):
 *   action=toggle  → activate / deactivate
 *   action=delete  → permanent delete
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/candidates.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

// ── POST handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['candidate_id'] ?? 0);

    if ($id > 0) {
        if ($action === 'toggle') {
            $newStatus = toggleCandidateStatus($id);
            flash('candidates', $newStatus ? 'Candidate account activated.' : 'Candidate account deactivated.', 'success');
        } elseif ($action === 'delete') {
            if (deleteCandidate($id)) {
                flash('candidates', 'Candidate deleted successfully.', 'success');
            } else {
                flash('candidates', 'Could not delete candidate.', 'error');
            }
        }
    }
    redirect(BASE_URL . '/admin/candidates.php');
}

// ── GET — build query params ───────────────────────────────────
$search  = trim($_GET['q']      ?? '');
$status  = $_GET['status']      ?? '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$result = getAllCandidates($search, $status, $page, $perPage);
$rows   = $result['rows'];
$total  = $result['total'];
$pages  = (int) ceil($total / $perPage);

// Stats for mini-cards at top
$stats  = getCandidateStats();

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Candidates', 'dashboard-page');
renderAdminNav('candidates');
?>

<!-- Page header -->
<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">Candidates</h1>
    <p class="page-header__subtitle">Manage all registered candidates on the platform.</p>
  </div>
</div>

<!-- Flash -->
<?php renderAlert(getFlash('candidates')); ?>

<!-- ── Mini stat row ── -->
<div class="row g-3 mb-4">
  <?php
  $miniStats = [
    ['label' => 'Total Registered', 'value' => $stats['total'],       'icon' => 'bi-people',        'color' => 'var(--color-primary)'],
    ['label' => 'Active',           'value' => $stats['active'],       'icon' => 'bi-person-check',  'color' => 'var(--color-success)'],
    ['label' => 'Inactive',         'value' => $stats['inactive'],     'icon' => 'bi-person-slash',  'color' => 'var(--color-text-muted)'],
    ['label' => 'Interviewed',      'value' => $stats['interviewed'],  'icon' => 'bi-camera-video',  'color' => '#7C3AED'],
  ];
  foreach ($miniStats as $s): ?>
    <div class="col-6 col-md-3">
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

<!-- ── Filter bar ── -->
<form method="GET" action="" class="filter-bar mb-0">
  <div class="input-group search-input" style="max-width:320px;">
    <span class="input-group-text" style="background:var(--color-bg);border:1px solid var(--color-border-strong);border-right:none;border-radius:var(--radius-md) 0 0 var(--radius-md);color:var(--color-text-muted);">
      <i class="bi bi-search"></i>
    </span>
    <input
      type="text"
      id="candidate-search"
      name="q"
      class="form-control"
      placeholder="Search name or email…"
      value="<?= e($search) ?>"
      style="border-left:none;border-radius:0 var(--radius-md) var(--radius-md) 0;"
    />
  </div>

  <select id="status-filter" name="status" class="form-select" style="width:auto;flex-shrink:0;" onchange="this.form.submit()">
    <option value=""       <?= $status === ''         ? 'selected' : '' ?>>All Statuses</option>
    <option value="active" <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>

  <button type="submit" class="btn btn-sm"
          style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem .875rem;font-size:.875rem;font-weight:500;">
    Search
  </button>

  <?php if ($search !== '' || $status !== ''): ?>
    <a href="<?= BASE_URL ?>/admin/candidates.php"
       style="font-size:.8125rem;color:var(--color-text-muted);text-decoration:none;display:flex;align-items:center;gap:.25rem;">
      <i class="bi bi-x-circle"></i> Clear
    </a>
  <?php endif; ?>
</form>

<!-- ── Table ── -->
<div class="data-table-wrap">
  <table class="data-table" id="candidates-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Candidate</th>
        <th>Registered</th>
        <th>Status</th>
        <th>Last Login</th>
        <th>Interviews</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <i class="bi bi-people empty-state__icon"></i>
              <p class="empty-state__title">No candidates found</p>
              <p class="empty-state__sub">
                <?= ($search !== '' || $status !== '') ? 'Try adjusting your search or filter.' : 'Candidates will appear here once they register.' ?>
              </p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td style="color:var(--color-text-muted);font-size:.8rem;">#<?= $row['id'] ?></td>
            <td>
              <div class="cell-user">
                <div class="cell-user__avatar"><?= e(initials($row['full_name'])) ?></div>
                <div>
                  <div class="cell-user__name">
                    <a href="<?= BASE_URL ?>/admin/candidate_view.php?id=<?= $row['id'] ?>"
                       style="color:var(--color-text-primary);text-decoration:none;">
                      <?= e($row['full_name']) ?>
                    </a>
                  </div>
                  <div class="cell-user__email"><?= e($row['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);">
              <?= formatDate($row['created_at']) ?>
            </td>
            <td>
              <?php if ($row['is_active']): ?>
                <span class="status-badge status-badge--active">Active</span>
              <?php else: ?>
                <span class="status-badge status-badge--inactive">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);">
              <?= timeAgo($row['last_login_at']) ?>
            </td>
            <td style="font-size:.875rem;">
              <?= (int) $row['interview_count'] ?>
            </td>
            <td>
              <div class="d-flex gap-1 justify-content-end">
                <!-- View -->
                <a href="<?= BASE_URL ?>/admin/candidate_view.php?id=<?= $row['id'] ?>"
                   class="action-btn action-btn--primary"
                   title="View profile">
                  <i class="bi bi-eye"></i>
                </a>

                <!-- Toggle status -->
                <form method="POST" action="" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="candidate_id" value="<?= $row['id'] ?>" />
                  <button type="submit"
                          class="action-btn <?= $row['is_active'] ? 'action-btn--warning' : 'action-btn--success' ?>"
                          title="<?= $row['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <i class="bi <?= $row['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                  </button>
                </form>

                <!-- Delete -->
                <button
                  class="action-btn action-btn--danger"
                  title="Delete candidate"
                  data-bs-toggle="modal"
                  data-bs-target="#deleteModal"
                  data-candidate-id="<?= $row['id'] ?>"
                  data-candidate-name="<?= e($row['full_name']) ?>">
                  <i class="bi bi-trash"></i>
                </button>
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
        <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>"
           href="?q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $page - 1 ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
          <a class="page-link <?= $p === $page ? 'active' : '' ?>"
             href="?q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
        <a class="page-link <?= $page >= $pages ? 'disabled' : '' ?>"
           href="?q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $page + 1 ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
  <?php elseif ($total > 0): ?>
    <div class="pagination-bar">
      <span><?= $total ?> candidate<?= $total !== 1 ? 's' : '' ?></span>
    </div>
  <?php endif; ?>
</div>

<!-- ── Delete Confirmation Modal ── -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Delete Candidate</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">
        <p style="color:var(--color-text-secondary);font-size:.9rem;">
          Are you sure you want to permanently delete
          <strong id="deleteModalName"></strong>?
          This action cannot be undone.
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
          <input type="hidden" name="candidate_id" id="deleteModalId" value="" />
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
// Populate delete modal
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
  const btn  = e.relatedTarget;
  document.getElementById('deleteModalId').value   = btn.dataset.candidateId;
  document.getElementById('deleteModalName').textContent = btn.dataset.candidateName;
});
</script>

<?php renderAdminFooter(); ?>
