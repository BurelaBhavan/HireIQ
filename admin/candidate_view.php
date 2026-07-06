<?php
/**
 * Admin — Candidate Profile View
 * AI Interview Assessment Platform — Phase 2
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/candidates.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

$id        = (int) ($_GET['id'] ?? 0);
$candidate = $id > 0 ? getCandidateById($id) : null;

if (!$candidate) {
    flash('candidates', 'Candidate not found.', 'error');
    redirect(BASE_URL . '/admin/candidates.php');
}

require_once __DIR__ . '/../includes/layout.php';
renderHeader(e($candidate['full_name']) . ' — Profile', 'dashboard-page');
renderAdminNav('candidates');
?>

<!-- Page header -->
<div class="page-header">
  <div class="d-flex align-items-center gap-2 mb-1" style="font-size:.8125rem;color:var(--color-text-muted);">
    <a href="<?= BASE_URL ?>/admin/candidates.php" style="color:var(--color-text-muted);text-decoration:none;">
      <i class="bi bi-arrow-left"></i> Back to Candidates
    </a>
  </div>
  <h1 class="page-header__title"><?= e($candidate['full_name']) ?></h1>
  <p class="page-header__subtitle">Candidate profile &amp; activity</p>
</div>

<div class="row g-3">

  <!-- Profile card -->
  <div class="col-lg-8">
    <div class="content-card">
      <div class="profile-card-header">
        <div class="profile-avatar-lg"><?= e(initials($candidate['full_name'])) ?></div>
        <div>
          <div style="font-size:1.125rem;font-weight:700;color:var(--color-text-primary);">
            <?= e($candidate['full_name']) ?>
          </div>
          <div style="font-size:.875rem;color:var(--color-text-muted);"><?= e($candidate['email']) ?></div>
          <div class="mt-2">
            <?php if ($candidate['is_active']): ?>
              <span class="status-badge status-badge--active">Active Account</span>
            <?php else: ?>
              <span class="status-badge status-badge--inactive">Inactive Account</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="profile-meta-grid">
        <div>
          <div class="profile-meta-item__label">Candidate ID</div>
          <div class="profile-meta-item__value">#<?= $candidate['id'] ?></div>
        </div>
        <div>
          <div class="profile-meta-item__label">Email Address</div>
          <div class="profile-meta-item__value" style="font-size:.875rem;"><?= e($candidate['email']) ?></div>
        </div>
        <div>
          <div class="profile-meta-item__label">Registration Date</div>
          <div class="profile-meta-item__value"><?= formatDate($candidate['created_at'], 'M j, Y') ?></div>
        </div>
        <div>
          <div class="profile-meta-item__label">Last Login</div>
          <div class="profile-meta-item__value"><?= timeAgo($candidate['last_login_at']) ?></div>
        </div>
        <div>
          <div class="profile-meta-item__label">Interviews Completed</div>
          <div class="profile-meta-item__value"><?= (int) $candidate['interview_count'] ?></div>
        </div>
        <div>
          <div class="profile-meta-item__label">Average Score</div>
          <div class="profile-meta-item__value">
            <?= $candidate['avg_score'] !== null
              ? number_format((float) $candidate['avg_score'], 1) . '%'
              : '—' ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions sidebar -->
  <div class="col-lg-4">
    <div class="content-card">
      <h2 class="content-card__title mb-3">Account Actions</h2>

      <!-- Toggle active status -->
      <form method="POST" action="<?= BASE_URL ?>/admin/candidates.php" class="mb-2">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="action" value="toggle" />
        <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>" />
        <button
          type="submit"
          class="btn w-100 btn-sm"
          style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.55rem;font-size:.875rem;font-weight:500;display:flex;align-items:center;justify-content:center;gap:.5rem;">
          <?php if ($candidate['is_active']): ?>
            <i class="bi bi-pause-circle text-warning"></i> Deactivate Account
          <?php else: ?>
            <i class="bi bi-play-circle text-success"></i> Activate Account
          <?php endif; ?>
        </button>
      </form>

      <!-- Delete -->
      <button
        class="btn w-100 btn-sm"
        style="border:1px solid #FECACA;color:var(--color-danger);border-radius:var(--radius-md);padding:.55rem;font-size:.875rem;font-weight:500;display:flex;align-items:center;justify-content:center;gap:.5rem;background:transparent;"
        data-bs-toggle="modal"
        data-bs-target="#deleteModal"
        data-candidate-id="<?= $candidate['id'] ?>"
        data-candidate-name="<?= e($candidate['full_name']) ?>">
        <i class="bi bi-trash"></i> Delete Account
      </button>
    </div>

    <!-- Account meta -->
    <div class="content-card mt-3">
      <h2 class="content-card__title mb-3">Account Details</h2>
      <div class="d-flex flex-column gap-3">
        <div>
          <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-muted);margin-bottom:.25rem;">Member Since</div>
          <div style="font-size:.875rem;"><?= formatDate($candidate['created_at'], 'F j, Y \a\t g:i A') ?></div>
        </div>
        <div>
          <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-muted);margin-bottom:.25rem;">Account Status</div>
          <div>
            <?php if ($candidate['is_active']): ?>
              <span class="status-badge status-badge--active">Active</span>
            <?php else: ?>
              <span class="status-badge status-badge--inactive">Inactive</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Interview History -->
  <div class="col-12">
    <div class="content-card">
      <div class="content-card__header">
        <h2 class="content-card__title">Interview History</h2>
      </div>
      <div class="empty-state">
        <i class="bi bi-camera-video empty-state__icon"></i>
        <p class="empty-state__title">No interviews yet</p>
        <p class="empty-state__sub">This candidate has not participated in any interviews.</p>
      </div>
    </div>
  </div>

</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Delete Candidate</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">
        <p style="color:var(--color-text-secondary);font-size:.9rem;">
          Are you sure you want to permanently delete
          <strong id="deleteModalName"><?= e($candidate['full_name']) ?></strong>?
          This cannot be undone.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm"
                style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.45rem .875rem;font-size:.875rem;"
                data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="<?= BASE_URL ?>/admin/candidates.php">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>" />
          <button type="submit" class="btn btn-sm"
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
  if (btn) {
    document.getElementById('deleteModalName').textContent = btn.dataset.candidateName;
  }
});
</script>

<?php renderAdminFooter(); ?>
