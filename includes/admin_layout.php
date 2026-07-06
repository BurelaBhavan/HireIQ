<?php
/**
 * Admin Layout Helpers
 * AI Interview Assessment Platform — Phase 2
 *
 * renderAdminNav(string $activePage)
 *   Renders the top navbar + sidebar.
 *   $activePage: 'dashboard' | 'candidates' | 'interviews' | 'assessments'
 *               | 'reports' | 'settings' | 'documents' | 'doc_analytics'
 *
 * renderAdminFooter()
 *   Closes tags; output Bootstrap + custom JS.
 */

declare(strict_types=1);

function renderAdminNav(string $activePage = 'dashboard'): void
{
    // Initials for avatar
    $name     = $_SESSION['user_name'] ?? 'Admin';
    $initials = initials($name);

    $nav = [
        ['key' => 'dashboard',      'href' => BASE_URL . '/admin/dashboard.php',      'icon' => 'bi-grid-1x2',         'label' => 'Dashboard'],
        ['key' => 'candidates',     'href' => BASE_URL . '/admin/candidates.php',     'icon' => 'bi-people',           'label' => 'Candidates'],
        ['key' => 'questions',      'href' => BASE_URL . '/admin/questions.php',      'icon' => 'bi-patch-question',   'label' => 'Question Bank'],
        ['key' => 'interviews',     'href' => BASE_URL . '/admin/interviews.php',     'icon' => 'bi-camera-video',     'label' => 'Interviews'],
        ['key' => 'attempt_review',  'href' => BASE_URL . '/admin/attempt_review.php', 'icon' => 'bi-shield-check',     'label' => 'Attempt Reviews'],
        ['key' => 'ai_evaluations', 'href' => BASE_URL . '/admin/attempt_review.php', 'icon' => 'bi-stars',            'label' => 'AI Evaluations'],
        ['key' => 'tests',          'href' => BASE_URL . '/admin/tests.php',          'icon' => 'bi-journal-check',    'label' => 'Tests'],
        ['key' => 'documents',      'href' => BASE_URL . '/admin/documents.php',           'icon' => 'bi-file-earmark-text', 'label' => 'Documents'],
        ['key' => 'doc_analytics', 'href' => BASE_URL . '/admin/document_analytics.php', 'icon' => 'bi-shield-check',      'label' => 'Doc Security'],
        ['key' => 'assessments',   'href' => '#',                                         'icon' => 'bi-clipboard-data',    'label' => 'Assessments'],
    ];
    $analytics = [
        ['key' => 'reports',  'href' => '#', 'icon' => 'bi-bar-chart-line',  'label' => 'Reports'],
        ['key' => 'insights', 'href' => '#', 'icon' => 'bi-graph-up-arrow',  'label' => 'Insights'],
    ];
    $system = [
        ['key' => 'settings', 'href' => '#', 'icon' => 'bi-gear', 'label' => 'Settings'],
    ];
    ?>
<!-- ── Navbar ── -->
<header class="app-navbar">
  <a href="<?= BASE_URL ?>/admin/dashboard.php" class="brand-logo">
    Hire<span>IQ</span>
    <span class="ms-2 badge-pill badge-pill--blue" style="font-size:.6875rem;vertical-align:middle;">Admin</span>
  </a>

  <div class="app-navbar__actions">
    <button
      class="btn btn-sm"
      style="border:1px solid var(--color-border);border-radius:var(--radius-md);color:var(--color-text-secondary);font-size:.875rem;padding:.4rem .75rem;"
      title="Notifications"
    >
      <i class="bi bi-bell"></i>
    </button>

    <div class="dropdown">
      <button
        class="d-flex align-items-center gap-2 btn btn-sm"
        style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.35rem .75rem;"
        data-bs-toggle="dropdown"
        aria-expanded="false"
      >
        <div class="avatar" aria-hidden="true"><?= e($initials) ?></div>
        <span style="font-size:.875rem;font-weight:500;color:var(--color-text-primary);">
          <?= e($name) ?>
        </span>
        <i class="bi bi-chevron-down" style="font-size:.7rem;color:var(--color-text-muted);"></i>
      </button>

      <ul class="dropdown-menu dropdown-menu-end shadow-sm border"
          style="border-color:var(--color-border);border-radius:var(--radius-md);min-width:180px;margin-top:.5rem;">
        <li>
          <span class="dropdown-item-text" style="font-size:.8rem;color:var(--color-text-muted);padding:.5rem 1rem .25rem;">
            Signed in as
          </span>
        </li>
        <li>
          <span class="dropdown-item-text fw-600"
                style="font-size:.875rem;padding:.125rem 1rem .5rem;color:var(--color-text-primary);">
            <?= e($name) ?>
          </span>
        </li>
        <li><hr class="dropdown-divider" style="border-color:var(--color-border);margin:.25rem 0;" /></li>
        <li><a class="dropdown-item" href="#" style="font-size:.875rem;"><i class="bi bi-person me-2"></i>Profile</a></li>
        <li><a class="dropdown-item" href="#" style="font-size:.875rem;"><i class="bi bi-gear me-2"></i>Settings</a></li>
        <li><hr class="dropdown-divider" style="border-color:var(--color-border);margin:.25rem 0;" /></li>
        <li>
          <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php" style="font-size:.875rem;">
            <i class="bi bi-box-arrow-right me-2"></i>Sign out
          </a>
        </li>
      </ul>
    </div>
  </div>
</header>

<!-- ── Main layout ── -->
<div class="dashboard-layout">

  <!-- Sidebar -->
  <nav class="sidebar" aria-label="Admin navigation">
    <span class="sidebar__label">Main</span>
    <?php foreach ($nav as $item): ?>
      <a href="<?= $item['href'] ?>"
         class="sidebar__link<?= $activePage === $item['key'] ? ' active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>

    <span class="sidebar__label mt-2">Analytics</span>
    <?php foreach ($analytics as $item): ?>
      <a href="<?= $item['href'] ?>"
         class="sidebar__link<?= $activePage === $item['key'] ? ' active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>

    <span class="sidebar__label mt-2">System</span>
    <?php foreach ($system as $item): ?>
      <a href="<?= $item['href'] ?>"
         class="sidebar__link<?= $activePage === $item['key'] ? ' active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>

    <a href="<?= BASE_URL ?>/logout.php"
       class="sidebar__link mt-auto"
       style="margin-top:auto !important;">
      <i class="bi bi-box-arrow-right"></i> Sign out
    </a>
  </nav>

  <!-- dashboard-layout closes in renderAdminFooter() -->
  <main class="dashboard-main" id="main-content">
<?php
}

/**
 * Close the main + dashboard-layout divs, then render footer JS.
 */
function renderAdminFooter(): void
{
    ?>
  </main><!-- /dashboard-main -->
</div><!-- /dashboard-layout -->
<?php renderFooter(); ?>
<?php
}
