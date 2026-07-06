<?php
/**
 * Candidate Notifications
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
    
    if ($action === 'mark_all_read') {
        markAllNotificationsRead($userId);
        flash('notifications', 'All notifications marked as read.', 'success');
    } elseif ($action === 'mark_read') {
        $notifId = (int) ($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            markNotificationRead($notifId, $userId);
        }
    }
    
    redirect(BASE_URL . '/candidate/notifications.php');
}

$notifications = getUserNotifications($userId);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Notifications', 'dashboard-page');
renderCandidateNav('notifications');
?>

<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">Notifications</h1>
    <p class="page-header__subtitle">Updates about your interviews, tests, and documents.</p>
  </div>
  <?php if (!empty($notifications)): ?>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
      <input type="hidden" name="action" value="mark_all_read" />
      <button type="submit" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-check-all"></i> Mark all as read
      </button>
    </form>
  <?php endif; ?>
</div>

<?php renderAlert(getFlash('notifications')); ?>

<div class="content-card" style="padding: 0;">
  <?php if (empty($notifications)): ?>
    <div class="empty-state" style="padding: 4rem 2rem;">
      <i class="bi bi-bell-slash empty-state__icon"></i>
      <p class="empty-state__title">No notifications</p>
      <p class="empty-state__sub">You're all caught up! New notifications will appear here.</p>
    </div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($notifications as $notif): ?>
        <?php 
        $isUnread = (int)$notif['is_read'] === 0;
        $icon = match($notif['type']) {
          'Interview' => 'bi-camera-video text-primary',
          'Test' => 'bi-card-checklist text-info',
          'Document' => 'bi-file-earmark-text text-warning',
          'System' => 'bi-info-circle text-secondary',
          default => 'bi-bell text-secondary'
        };
        ?>
        <div class="list-group-item d-flex gap-3 py-3 <?= $isUnread ? 'bg-light' : '' ?>" style="<?= $isUnread ? 'border-left: 4px solid var(--color-primary);' : 'border-left: 4px solid transparent;' ?>">
          <div style="font-size: 1.5rem; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: var(--color-bg-gray); border-radius: 50%;">
            <i class="bi <?= $icon ?>"></i>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex w-100 justify-content-between align-items-center">
              <h5 class="mb-1" style="font-size: .95rem; font-weight: <?= $isUnread ? '700' : '600' ?>; color: var(--color-text-primary);">
                <?= e($notif['title']) ?>
              </h5>
              <small style="color: var(--color-text-secondary); font-size: .75rem;"><?= formatDate($notif['created_at']) ?></small>
            </div>
            <p class="mb-1" style="font-size: .85rem; color: <?= $isUnread ? 'var(--color-text-primary)' : 'var(--color-text-muted)' ?>;">
              <?= nl2br(e($notif['message'])) ?>
            </p>
            
            <?php if ($isUnread): ?>
              <form method="POST" action="" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
                <input type="hidden" name="action" value="mark_read" />
                <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>" />
                <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none" style="font-size: .8rem;">Mark as read</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php renderCandidateFooter(); ?>
