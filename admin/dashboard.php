<?php
/**
 * Admin Dashboard — Phase 2
 * AI Interview Assessment Platform
 *
 * Live stats: Total Candidates, Interviews, Completed Assessments, Avg AI Score
 * Recent Activity Feed
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/candidates.php';
require_once __DIR__ . '/../includes/interviews.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

// ── Data ──────────────────────────────────────────────────────
$candidateStats  = getCandidateStats();
$interviewStats  = getInterviewStats();
$recentCandidates = getRecentCandidates(4);
$recentInterviews = getRecentInterviews(4);

// Build merged activity feed sorted by created_at desc
$activityFeed = [];
foreach ($recentCandidates as $c) {
    $activityFeed[] = [
        'type'  => 'candidate',
        'icon'  => 'bi-person-plus-fill',
        'color' => 'blue',
        'text'  => $c['full_name'] . ' registered',
        'sub'   => $c['email'],
        'time'  => $c['created_at'],
    ];
}
foreach ($recentInterviews as $iv) {
    $activityFeed[] = [
        'type'  => 'interview',
        'icon'  => 'bi-camera-video-fill',
        'color' => 'green',
        'text'  => 'Interview created: ' . $iv['title'],
        'sub'   => 'By ' . $iv['created_by_name'],
        'time'  => $iv['created_at'],
    ];
}
usort($activityFeed, fn($a, $b) => strcmp($b['time'], $a['time']));
$activityFeed = array_slice($activityFeed, 0, 6);

$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
$firstName = explode(' ', $_SESSION['user_name'])[0];

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Admin Dashboard', 'dashboard-page');
renderAdminNav('dashboard');
?>

<!-- Page header -->
<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">
      Good <?= $greeting ?>, <?= e($firstName) ?> 👋
    </h1>
    <p class="page-header__subtitle">Here's what's happening on your platform today.</p>
  </div>
  <a href="<?= BASE_URL ?>/admin/interviews.php?action=create"
     class="btn btn-sm"
     style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:.4rem;text-decoration:none;">
    <i class="bi bi-plus-lg"></i> New Interview
  </a>
</div>

<!-- ── Stat Cards ── -->
<div class="row g-3 mb-4">

  <!-- Total Candidates -->
  <div class="col-6 col-xl-3">
    <a href="<?= BASE_URL ?>/admin/candidates.php" class="stat-card d-block text-decoration-none">
      <div class="stat-card__icon"><i class="bi bi-people"></i></div>
      <div class="stat-card__value"><?= $candidateStats['total'] ?></div>
      <div class="stat-card__label">Total Candidates</div>
      <div class="mt-2" style="font-size:.775rem;color:var(--color-text-muted);">
        <span style="color:var(--color-success);">●</span> <?= $candidateStats['active'] ?> active
        &nbsp;·&nbsp;
        <span style="color:var(--color-text-muted);">●</span> <?= $candidateStats['inactive'] ?> inactive
      </div>
    </a>
  </div>

  <!-- Total Interviews -->
  <div class="col-6 col-xl-3">
    <a href="<?= BASE_URL ?>/admin/interviews.php" class="stat-card d-block text-decoration-none">
      <div class="stat-card__icon"><i class="bi bi-camera-video"></i></div>
      <div class="stat-card__value"><?= $interviewStats['total'] ?></div>
      <div class="stat-card__label">Total Interviews</div>
      <div class="mt-2" style="font-size:.775rem;color:var(--color-text-muted);">
        <span style="color:var(--color-success);">●</span> <?= $interviewStats['active'] ?> active
        &nbsp;·&nbsp;
        <span style="color:#D97706;">●</span> <?= $interviewStats['draft'] ?> draft
      </div>
    </a>
  </div>

  <!-- Completed Assessments -->
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-card__icon"><i class="bi bi-patch-check"></i></div>
      <div class="stat-card__value"><?= $interviewStats['completed'] ?></div>
      <div class="stat-card__label">Completed Assessments</div>
      <div class="mt-2" style="font-size:.775rem;color:var(--color-text-muted);">
        <span style="color:var(--color-primary);">●</span> <?= $candidateStats['interviewed'] ?> candidates interviewed
      </div>
    </div>
  </div>

  <!-- Average AI Score -->
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-card__icon"><i class="bi bi-stars"></i></div>
      <div class="stat-card__value">—</div>
      <div class="stat-card__label">Average AI Score</div>
      <div class="mt-2" style="font-size:.775rem;color:var(--color-text-muted);">
        Available after assessments
      </div>
    </div>
  </div>

</div>

<!-- ── Content Row ── -->
<div class="row g-3">

  <!-- Recent Activity -->
  <div class="col-lg-7">
    <div class="content-card">
      <div class="content-card__header">
        <h2 class="content-card__title">Recent Activity</h2>
        <a href="<?= BASE_URL ?>/admin/candidates.php" style="font-size:.8125rem;color:var(--color-primary);">
          View all candidates
        </a>
      </div>

      <?php if (empty($activityFeed)): ?>
        <div class="empty-state">
          <i class="bi bi-activity empty-state__icon"></i>
          <p class="empty-state__title">No activity yet</p>
          <p class="empty-state__sub">
            Register candidates and create interviews to see activity here.
          </p>
        </div>
      <?php else: ?>
        <div class="activity-feed">
          <?php foreach ($activityFeed as $ev): ?>
            <div class="activity-item">
              <div class="activity-item__icon activity-item__icon--<?= $ev['color'] ?>">
                <i class="bi <?= $ev['icon'] ?>"></i>
              </div>
              <div class="activity-item__body">
                <div class="activity-item__text"><?= e($ev['text']) ?></div>
                <div class="activity-item__time">
                  <?= e($ev['sub']) ?> · <?= timeAgo($ev['time']) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="col-lg-5">
    <div class="content-card">
      <div class="content-card__header">
        <h2 class="content-card__title">Quick Actions</h2>
      </div>
      <div class="d-flex flex-column gap-2">
        <?php
        $actions = [
          ['icon' => 'bi-camera-video-fill', 'label' => 'Create Interview',    'sub' => 'Build a new assessment interview',     'href' => BASE_URL . '/admin/interviews.php?action=create'],
          ['icon' => 'bi-people-fill',        'label' => 'Manage Candidates',   'sub' => 'View, filter and manage candidates',   'href' => BASE_URL . '/admin/candidates.php'],
          ['icon' => 'bi-clipboard2-plus',    'label' => 'Build Assessment',    'sub' => 'Add questions & scoring rubrics',      'href' => '#'],
          ['icon' => 'bi-bar-chart-line-fill','label' => 'View Reports',        'sub' => 'Analytics and performance insights',   'href' => '#'],
        ];
        foreach ($actions as $a): ?>
          <a href="<?= $a['href'] ?>"
             class="d-flex align-items-center gap-3 p-3 rounded text-decoration-none"
             style="border:1px solid var(--color-border);transition:background var(--transition),border-color var(--transition);"
             onmouseover="this.style.background='var(--color-bg-gray)';this.style.borderColor='var(--color-border-strong)'"
             onmouseout="this.style.background='';this.style.borderColor='var(--color-border)'">
            <div style="width:38px;height:38px;border-radius:var(--radius-md);background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;color:var(--color-primary);flex-shrink:0;">
              <i class="bi <?= $a['icon'] ?>"></i>
            </div>
            <div>
              <div style="font-size:.875rem;font-weight:600;color:var(--color-text-primary);"><?= $a['label'] ?></div>
              <div style="font-size:.775rem;color:var(--color-text-muted);"><?= $a['sub'] ?></div>
            </div>
            <i class="bi bi-chevron-right ms-auto" style="font-size:.75rem;color:var(--color-text-muted);"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Interview Stats breakdown -->
  <div class="col-12">
    <div class="content-card">
      <div class="content-card__header">
        <h2 class="content-card__title">Interview Status Overview</h2>
        <a href="<?= BASE_URL ?>/admin/interviews.php" style="font-size:.8125rem;color:var(--color-primary);">
          Manage interviews
        </a>
      </div>
      <div class="row g-3">
        <?php
        $ivStats = [
          ['label' => 'Active',   'value' => $interviewStats['active'],   'cls' => 'status-badge--active'],
          ['label' => 'Draft',    'value' => $interviewStats['draft'],    'cls' => 'status-badge--draft'],
          ['label' => 'Archived', 'value' => $interviewStats['archived'], 'cls' => 'status-badge--archived'],
          ['label' => 'Completed Sessions', 'value' => $interviewStats['completed'], 'cls' => 'status-badge--active'],
        ];
        foreach ($ivStats as $s): ?>
          <div class="col-6 col-md-3">
            <div style="padding:1rem;background:var(--color-bg-subtle);border-radius:var(--radius-md);border:1px solid var(--color-border);">
              <div style="font-size:1.5rem;font-weight:700;font-family:var(--font-heading);color:var(--color-text-primary);"><?= $s['value'] ?></div>
              <div class="mt-1">
                <span class="status-badge <?= $s['cls'] ?>"><?= $s['label'] ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<?php renderAdminFooter(); ?>
