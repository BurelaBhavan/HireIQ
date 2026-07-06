<?php
/**
 * Candidate Invitations
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/candidate_layout.php';

requireRole('candidate');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $invId = (int) ($_POST['invitation_id'] ?? 0);
    
    if ($invId > 0 && in_array($action, ['accept', 'decline']) && in_array($type, ['interview', 'test'])) {
        $status = $action === 'accept' ? 'Accepted' : 'Declined';
        $success = false;
        
        if ($type === 'interview') {
            $success = updateInterviewInvitationStatus($invId, $userId, $status);
        } else {
            $success = updateTestInvitationStatus($invId, $userId, $status);
        }
        
        if ($success) {
            flash('invitations', "Invitation {$status}.", 'success');
        } else {
            flash('invitations', "Failed to update invitation.", 'error');
        }
    }
    
    redirect(BASE_URL . '/candidate/invitations.php');
}

$interviewInvs = getCandidateInterviewInvitations($userId);
$testInvs = getCandidateTestInvitations($userId);

// Combine and sort by date descending
$allInvs = [];
foreach ($interviewInvs as $i) {
    $i['inv_type'] = 'interview';
    $allInvs[] = $i;
}
foreach ($testInvs as $t) {
    $t['inv_type'] = 'test';
    $allInvs[] = $t;
}
usort($allInvs, fn($a, $b) => strtotime($b['invitation_date']) - strtotime($a['invitation_date']));

require_once __DIR__ . '/../includes/layout.php';
renderHeader('My Invitations', 'dashboard-page');
renderCandidateNav('invitations');
?>

<div class="page-header">
  <h1 class="page-header__title">My Invitations</h1>
  <p class="page-header__subtitle">Review and accept your interview and test assignments.</p>
</div>

<?php renderAlert(getFlash('invitations')); ?>

<div class="content-card">
  <div class="data-table-wrap" style="margin: 0; box-shadow: none;">
    <table class="data-table">
      <thead>
        <tr>
          <th>Assignment</th>
          <th>Type</th>
          <th>Duration</th>
          <th>Status</th>
          <th>Date Invited</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allInvs)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <i class="bi bi-envelope empty-state__icon"></i>
                <p class="empty-state__title">No invitations yet</p>
                <p class="empty-state__sub">When you are assigned an interview or test, it will appear here.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($allInvs as $inv): ?>
            <tr>
              <td>
                <div style="font-weight:600;font-size:.9rem;color:var(--color-text-primary);"><?= e($inv['title']) ?></div>
                <?php if (!empty($inv['description'])): ?>
                  <div style="font-size:.8rem;color:var(--color-text-muted);margin-top:.15rem;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= e($inv['description']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($inv['inv_type'] === 'interview'): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-camera-video"></i> Interview</span>
                <?php else: ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-card-checklist"></i> Test</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.875rem;color:var(--color-text-secondary);"><?= $inv['duration'] ?> min</td>
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
              <td style="font-size:.8375rem;color:var(--color-text-secondary);"><?= formatDate($inv['invitation_date']) ?></td>
              <td>
                <div class="d-flex gap-2 justify-content-end">
                  <?php if ($inv['status'] === 'Pending'): ?>
                    <form method="POST" action="" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                      <input type="hidden" name="action" value="accept" />
                      <input type="hidden" name="type" value="<?= $inv['inv_type'] ?>" />
                      <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>" />
                      <button type="submit" class="btn btn-sm btn-success">Accept</button>
                    </form>
                    <form method="POST" action="" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                      <input type="hidden" name="action" value="decline" />
                      <input type="hidden" name="type" value="<?= $inv['inv_type'] ?>" />
                      <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">Decline</button>
                    </form>
                  <?php elseif ($inv['status'] === 'Accepted'): ?>
                    <?php if ($inv['inv_type'] === 'interview'): ?>
                      <a href="<?= BASE_URL ?>/candidate/interview_engine.php?invitation_id=<?= $inv['id'] ?>"
                         class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-play-circle"></i> Start Interview
                      </a>
                    <?php else: ?>
                      <a href="<?= BASE_URL ?>/candidate/test_engine.php?invitation_id=<?= $inv['id'] ?>"
                         class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-play-circle"></i> Start Test
                      </a>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:.85rem;">No actions</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderCandidateFooter(); ?>
