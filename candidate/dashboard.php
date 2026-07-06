<?php
/**
 * Candidate Dashboard
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/documents.php';
require_once __DIR__ . '/../includes/candidate_layout.php';

requireRole('candidate');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id']);

// Fetch Pending Invitations
$interviewInvs = getCandidateInterviewInvitations($userId, 'Pending');
$testInvs = getCandidateTestInvitations($userId, 'Pending');
$totalPending = count($interviewInvs) + count($testInvs);

// Fetch Unread Documents
$allDocs = getCandidateDocuments($userId);
$unreadDocs = array_filter($allDocs, fn($d) => empty($d['read_at']));

// Fetch Completed Interviews count
$db = getDB();
$completedStmt = $db->prepare("SELECT COUNT(*) FROM attempts WHERE candidate_id = ? AND status = 'completed'");
$completedStmt->execute([$userId]);
$completedInterviewsCount = (int) $completedStmt->fetchColumn();

// Fetch Recent Published Results
$resultsStmt = $db->prepare("
    SELECT ir.*, i.title as interview_title, a.end_time 
    FROM interview_results ir
    JOIN interviews i ON ir.interview_id = i.id
    JOIN attempts a ON ir.attempt_id = a.id
    WHERE ir.candidate_id = ? AND ir.published_at IS NOT NULL
    ORDER BY ir.published_at DESC
    LIMIT 3
");
$resultsStmt->execute([$userId]);
$recentResults = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('My Dashboard', 'dashboard-page');
renderCandidateNav('dashboard');
?>

<div class="page-header">
  <h1 class="page-header__title">
    Welcome, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?>!
  </h1>
  <p class="page-header__subtitle">Track your interview invitations, tests, and progress below.</p>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-card__icon"><i class="bi bi-envelope-open" style="color:var(--color-primary);"></i></div>
      <div class="stat-card__value"><?= $totalPending ?></div>
      <div class="stat-card__label">Pending Invitations</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-card__icon"><i class="bi bi-bell" style="color:var(--color-warning);"></i></div>
      <div class="stat-card__value"><?= getUnreadNotificationCount($userId) ?></div>
      <div class="stat-card__label">Unread Notifications</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-card__icon"><i class="bi bi-file-earmark-text" style="color:var(--color-info);"></i></div>
      <div class="stat-card__value"><?= count($unreadDocs) ?></div>
      <div class="stat-card__label">Unread Documents</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-card__icon"><i class="bi bi-camera-video" style="color:var(--color-success);"></i></div>
      <div class="stat-card__value"><?= $completedInterviewsCount ?></div>
      <div class="stat-card__label">Interviews Completed</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Pending invitations -->
  <div class="col-lg-7">
    <div class="content-card mb-4">
      <div class="content-card__header">
        <h2 class="content-card__title">Pending Action Required</h2>
        <?php if ($totalPending > 0): ?>
          <span class="badge rounded-pill bg-warning text-dark"><?= $totalPending ?> pending</span>
        <?php endif; ?>
      </div>
      
      <div class="p-3">
        <?php if ($totalPending === 0): ?>
          <div class="text-center py-4" style="color:var(--color-text-muted);">
            <i class="bi bi-check-circle" style="font-size:2rem;display:block;margin-bottom:.5rem;color:var(--color-success);"></i>
            <p class="mb-0" style="font-weight:500;color:var(--color-text-secondary);">You're all caught up!</p>
            <p style="font-size:.85rem;">No pending invitations right now.</p>
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($interviewInvs as $inv): ?>
              <div class="p-3 border rounded d-flex justify-content-between align-items-center" style="background:var(--color-bg);">
                <div>
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-camera-video"></i> Interview</span>
                    <span style="font-weight:600;font-size:.95rem;"><?= e($inv['title']) ?></span>
                  </div>
                  <div style="font-size:.8rem;color:var(--color-text-muted);">
                    <i class="bi bi-clock"></i> <?= $inv['duration'] ?> mins &bull; Invited on <?= formatDate($inv['invitation_date']) ?>
                  </div>
                </div>
                <a href="<?= BASE_URL ?>/candidate/invitations.php" class="btn btn-sm btn-primary">Review</a>
              </div>
            <?php endforeach; ?>

            <?php foreach ($testInvs as $inv): ?>
              <div class="p-3 border rounded d-flex justify-content-between align-items-center" style="background:var(--color-bg);">
                <div>
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-card-checklist"></i> Test</span>
                    <span style="font-weight:600;font-size:.95rem;"><?= e($inv['title']) ?></span>
                  </div>
                  <div style="font-size:.8rem;color:var(--color-text-muted);">
                    <i class="bi bi-clock"></i> <?= $inv['duration'] ?> mins &bull; Invited on <?= formatDate($inv['invitation_date']) ?>
                  </div>
                </div>
                <a href="<?= BASE_URL ?>/candidate/invitations.php" class="btn btn-sm btn-primary">Review</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Results -->
    <div class="content-card">
      <div class="content-card__header d-flex justify-content-between align-items-center">
        <h2 class="content-card__title">Recent Assessment Results</h2>
        <a href="<?= BASE_URL ?>/candidate/results.php" style="font-size:.8rem;">View All</a>
      </div>
      
      <div class="p-3">
        <?php if (empty($recentResults)): ?>
          <div class="text-center py-4 text-muted" style="font-size:.85rem;">
            <i class="bi bi-trophy" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            <p class="mb-0">No assessment results published yet.</p>
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($recentResults as $r): ?>
              <?php
              $dec = $r['decision'];
              $bc = match($dec) {
                  'selected' => 'success',
                  'rejected' => 'danger',
                  default    => 'secondary'
              };
              ?>
              <div class="p-3 border rounded d-flex justify-content-between align-items-center" style="background:var(--color-bg); border-left: 4px solid var(--color-<?= $bc ?>);">
                <div>
                  <div style="font-weight:600;font-size:.95rem;" class="mb-1"><?= e($r['interview_title']) ?></div>
                  <div style="font-size:.8rem;color:var(--color-text-muted);">
                    Completed on <?= formatDate($r['end_time']) ?>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-<?= $bc ?>-subtle text-<?= $bc ?>" style="border: 1px solid currentColor; font-weight: 600; font-size: .8rem;">
                    <?= ucfirst($dec) ?>
                  </span>
                  <a href="<?= BASE_URL ?>/candidate/results.php" class="btn btn-sm btn-outline-primary" style="padding: .2rem .5rem; font-size: .75rem;">Feedback</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Documents and Setup -->
  <div class="col-lg-5">
    <div class="content-card mb-4">
      <div class="content-card__header">
        <h2 class="content-card__title">Unread Documents</h2>
        <a href="<?= BASE_URL ?>/candidate/documents.php" style="font-size:.8rem;">View All</a>
      </div>
      <div class="p-3">
        <?php if (empty($unreadDocs)): ?>
          <p class="text-muted text-center my-3" style="font-size:.85rem;">No new documents to read.</p>
        <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach (array_slice($unreadDocs, 0, 3) as $doc): ?>
              <a href="<?= BASE_URL ?>/candidate/documents.php" class="d-flex align-items-center gap-2 text-decoration-none p-2 border rounded hover-bg" style="color:var(--color-text-primary);">
                <i class="bi bi-file-earmark-text text-primary"></i>
                <span style="font-size:.85rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($doc['title']) ?></span>
              </a>
            <?php endforeach; ?>
            <?php if (count($unreadDocs) > 3): ?>
              <div class="text-center mt-2">
                <span class="text-muted" style="font-size:.8rem;">+<?= count($unreadDocs) - 3 ?> more</span>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="content-card">
      <div class="content-card__header">
        <h2 class="content-card__title">Tips to Get Started</h2>
      </div>
      <div class="d-flex flex-column gap-3 p-3">
        <?php
        $tips = [
          ['icon' => 'bi-person-fill',        'text' => 'Complete your candidate profile',    'done' => false],
          ['icon' => 'bi-camera-video-fill',   'text' => 'Test your camera and microphone',    'done' => false],
          ['icon' => 'bi-patch-check-fill',    'text' => 'Wait for your first invitation',     'done' => $totalPending > 0],
        ];
        foreach ($tips as $i => $tip): ?>
          <div class="d-flex align-items-center gap-3">
            <div style="
              width:30px;height:30px;border-radius:50%;
              background:<?= $tip['done'] ? '#DCFCE7' : 'var(--color-bg-gray)' ?>;
              display:flex;align-items:center;justify-content:center;
              color:<?= $tip['done'] ? '#16A34A' : 'var(--color-text-muted)' ?>;
              flex-shrink:0;font-size:.875rem;
            ">
              <i class="bi <?= $tip['done'] ? 'bi-check-lg' : $tip['icon'] ?>"></i>
            </div>
            <span style="font-size:.875rem;color:var(--color-text-secondary);"><?= $tip['text'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php renderCandidateFooter(); ?>
