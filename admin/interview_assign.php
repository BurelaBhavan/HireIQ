<?php
/**
 * Admin — Assign Interview to Candidates
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/interviews.php';
require_once __DIR__ . '/../includes/candidates.php';
require_once __DIR__ . '/../includes/notifications.php';
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

// ── Handle Assignment ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    verifyCsrf();
    $candidateIds = $_POST['candidates'] ?? [];
    $adminId = (int) ($_SESSION['user_id'] ?? 0);
    
    if (empty($candidateIds)) {
        flash('interview_assign', 'Please select at least one candidate.', 'error');
    } else {
        $assignedCount = 0;
        foreach ($candidateIds as $cId) {
            if (createInterviewInvitation($interviewId, (int)$cId, $adminId)) {
                $assignedCount++;
            }
        }
        
        if ($assignedCount > 0) {
            flash('interview_assign', "Successfully sent invitations to $assignedCount candidate(s).", 'success');
        } else {
            flash('interview_assign', 'No new invitations sent. Candidates may already be assigned.', 'warning');
        }
    }
    redirect(BASE_URL . "/admin/interview_assign.php?id=$interviewId");
}

// Handle remove invitation (optional advanced feature, skipping for now)

// Get all active candidates
$candidatesData = getAllCandidates('', 'active', 1, 1000); // Hacky way to get all for dropdown
$candidates = $candidatesData['rows'];

// Get current invitations for this interview
$db = getDB();
$stmt = $db->prepare("SELECT ii.*, u.full_name, u.email 
                      FROM interview_invitations ii 
                      JOIN users u ON ii.candidate_id = u.id 
                      WHERE ii.interview_id = ? 
                      ORDER BY ii.invitation_date DESC");
$stmt->execute([$interviewId]);
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Assign Interview', 'dashboard-page');
renderAdminNav('interviews');
?>

<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <div class="d-flex align-items-center gap-3 mb-2">
      <a href="<?= BASE_URL ?>/admin/interviews.php" class="btn btn-sm btn-outline-secondary" style="border-radius:var(--radius-md);">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <h1 class="page-header__title mb-0">Assign Interview</h1>
    </div>
    <p class="page-header__subtitle">Assign <strong><?= e($interview['title']) ?></strong> to candidates</p>
  </div>
</div>

<?php renderAlert(getFlash('interview_assign')); ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="content-card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Invite Candidates</h3>
      
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="action" value="assign" />

        <div class="mb-3">
          <label class="form-label" style="font-size:.875rem; font-weight:500;">Select Candidates <span class="text-danger">*</span></label>
          <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.5rem; background: var(--color-bg);">
            <?php if (empty($candidates)): ?>
              <div class="text-muted" style="font-size: .85rem; padding: .5rem;">No active candidates found.</div>
            <?php else: ?>
              <?php foreach ($candidates as $c): ?>
                <div class="form-check mb-2 pb-2" style="border-bottom: 1px solid var(--color-border-subtle);">
                  <input class="form-check-input" type="checkbox" name="candidates[]" value="<?= $c['id'] ?>" id="candidate_<?= $c['id'] ?>">
                  <label class="form-check-label d-flex flex-column" for="candidate_<?= $c['id'] ?>" style="font-size: .85rem; cursor: pointer;">
                    <span style="font-weight: 500; color: var(--color-text-primary);"><?= e($c['full_name']) ?></span>
                    <span style="color: var(--color-text-muted);"><?= e($c['email']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
              <div class="form-check mt-3 pt-2" style="border-top: 1px solid var(--color-border-strong);">
                <input class="form-check-input" type="checkbox" id="select_all_candidates">
                <label class="form-check-label" for="select_all_candidates" style="font-size: .85rem; font-weight: 600; cursor: pointer; color: var(--color-primary);">
                  Select All Candidates
                </label>
              </div>
            <?php endif; ?>
          </div>
          <div class="form-text">Select one or more candidates to send an interview invitation. This will generate a notification for them.</div>
        </div>

        <button type="submit" class="btn btn-sm w-100" style="background:var(--color-primary); color:#fff; font-weight:500;" <?= empty($candidates) ? 'disabled' : '' ?>>
          Send Invitations
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="content-card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Current Invitations</h3>
      
      <div class="data-table-wrap" style="margin: 0; box-shadow: none;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Candidate</th>
              <th>Status</th>
              <th>Invited On</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invitations)): ?>
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No invitations sent yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($invitations as $inv): ?>
                <tr>
                  <td>
                    <div style="font-weight: 500; font-size: .85rem; color: var(--color-text-primary);"><?= e($inv['full_name']) ?></div>
                    <div style="font-size: .75rem; color: var(--color-text-muted);"><?= e($inv['email']) ?></div>
                  </td>
                  <td>
                    <?php
                    $sc = match($inv['status']) {
                      'Pending' => 'warning',
                      'Accepted' => 'success',
                      'Declined' => 'danger',
                      'Expired' => 'secondary',
                      default => 'secondary'
                    };
                    ?>
                    <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?>" style="border: 1px solid currentColor; font-weight: 600;">
                      <?= $inv['status'] ?>
                    </span>
                  </td>
                  <td style="font-size: .8rem; color: var(--color-text-secondary);">
                    <?= formatDate($inv['invitation_date']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectAll = document.getElementById('select_all_candidates');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      const checkboxes = document.querySelectorAll('input[name="candidates[]"]');
      checkboxes.forEach(cb => {
        cb.checked = this.checked;
      });
    });
  }
});
</script>

<?php renderAdminFooter(); ?>
