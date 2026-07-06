<?php
/**
 * Candidate Layout Helpers
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);

function renderCandidateNav(string $activePage = 'dashboard'): void
{
    require_once __DIR__ . '/notifications.php';
    $name     = $_SESSION['user_name'] ?? 'Candidate';
    $userId   = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
    $initials = initials($name);

    $unreadNotifs = getUnreadNotificationCount($userId);

    $nav = [
        ['key' => 'dashboard',    'href' => BASE_URL . '/candidate/dashboard.php',     'icon' => 'bi-grid-1x2',       'label' => 'Dashboard'],
        ['key' => 'invitations',  'href' => BASE_URL . '/candidate/invitations.php',   'icon' => 'bi-envelope',       'label' => 'Invitations'],
        ['key' => 'results',      'href' => BASE_URL . '/candidate/results.php',       'icon' => 'bi-trophy',         'label' => 'My Results'],
        ['key' => 'documents',    'href' => BASE_URL . '/candidate/documents.php',     'icon' => 'bi-file-earmark',   'label' => 'Documents'],
        ['key' => 'notifications','href' => BASE_URL . '/candidate/notifications.php', 'icon' => 'bi-bell',           'label' => 'Notifications', 'badge' => $unreadNotifs],
    ];
    ?>
<!-- ── Navbar ── -->
<header class="app-navbar">
  <a href="<?= BASE_URL ?>/candidate/dashboard.php" class="brand-logo">
    Hire<span>IQ</span>
  </a>

  <div class="app-navbar__actions">
    <a href="<?= BASE_URL ?>/candidate/notifications.php"
       class="btn btn-sm position-relative"
       style="border:1px solid var(--color-border);border-radius:var(--radius-md);color:var(--color-text-secondary);font-size:.875rem;padding:.4rem .75rem;"
       title="Notifications"
    >
      <i class="bi bi-bell"></i>
      <?php if ($unreadNotifs > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: .6rem; padding: .25em .4em;">
          <?= $unreadNotifs ?>
        </span>
      <?php endif; ?>
    </a>

    <div class="dropdown">
      <button class="d-flex align-items-center gap-2 btn btn-sm" style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.35rem .75rem;" data-bs-toggle="dropdown">
        <div class="avatar"><?= e($initials) ?></div>
        <span style="font-size:.875rem;font-weight:500;color:var(--color-text-primary);"><?= e($name) ?></span>
        <i class="bi bi-chevron-down" style="font-size:.7rem;color:var(--color-text-muted);"></i>
      </button>

      <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="border-color:var(--color-border);border-radius:var(--radius-md);min-width:180px;margin-top:.5rem;">
        <li><span class="dropdown-item-text fw-600" style="font-size:.875rem;padding:.5rem 1rem;color:var(--color-text-primary);"><?= e($name) ?></span></li>
        <li><hr class="dropdown-divider" style="margin:.25rem 0;" /></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php" style="font-size:.875rem;"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
      </ul>
    </div>
  </div>
</header>

<!-- ── Main layout ── -->
<div class="dashboard-layout">
  <nav class="sidebar" aria-label="Candidate navigation">
    <span class="sidebar__label">My Journey</span>
    <?php foreach ($nav as $item): ?>
      <a href="<?= $item['href'] ?>" class="sidebar__link<?= $activePage === $item['key'] ? ' active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
        <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
          <span class="badge bg-danger ms-auto rounded-pill" style="font-size: .65rem;"><?= $item['badge'] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <a href="<?= BASE_URL ?>/logout.php" class="sidebar__link mt-auto" style="margin-top:auto !important;">
      <i class="bi bi-box-arrow-right"></i> Sign out
    </a>
  </nav>

  <main class="dashboard-main" id="main-content">
<?php
}

function renderCandidateFooter(): void
{
    ?>
  </main>
</div>
<?php renderFooter(); ?>
<?php
}
