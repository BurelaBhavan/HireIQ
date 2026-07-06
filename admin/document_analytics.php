<?php
/**
 * Admin — Document Security Analytics
 * AI Interview Assessment Platform — Phase 4.5
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/documents.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

$tab = $_GET['tab'] ?? 'overview';
$selectedDocId = (int) ($_GET['doc_id'] ?? 0);

// Load data
$stats      = getOverallStats();
$docStats   = getDocumentViewStats();
$riskUsers  = getHighRiskUsers(20);
$docLogs    = $selectedDocId > 0 ? getDocumentActivityLogs($selectedDocId) : [];
$selectedDoc = $selectedDocId > 0 ? getDocumentById($selectedDocId) : null;

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Document Security Analytics', 'dashboard-page');
renderAdminNav('doc_analytics');
?>

<style>
/* Analytics page styles */
.analytics-stat-card {
  background: var(--color-bg, #fff);
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: .35rem;
}
.analytics-stat-card__label {
  font-size: .75rem;
  font-weight: 600;
  color: var(--color-text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: .5px;
}
.analytics-stat-card__value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text-primary, #0f172a);
  line-height: 1;
}
.analytics-stat-card__sub {
  font-size: .78rem;
  color: var(--color-text-muted, #64748b);
}
.analytics-stat-card__icon {
  font-size: 1.4rem;
  margin-bottom: .25rem;
}
.risk-badge {
  display: inline-flex;
  align-items: center;
  padding: .25rem .6rem;
  border-radius: 99px;
  font-size: .7rem;
  font-weight: 700;
  gap: .25rem;
}
.risk-critical { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.risk-high     { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
.risk-medium   { background: #fefce8; color: #ca8a04; border: 1px solid #fde68a; }
.risk-low      { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.analytics-tab-btn {
  padding: .45rem 1rem;
  border-radius: 8px;
  border: 1px solid var(--color-border, #e2e8f0);
  background: transparent;
  font-size: .8rem;
  font-weight: 500;
  color: var(--color-text-secondary, #64748b);
  cursor: pointer;
  transition: all .15s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
}
.analytics-tab-btn.active,
.analytics-tab-btn:hover {
  background: var(--color-primary, #2563eb);
  color: #fff;
  border-color: var(--color-primary, #2563eb);
}
.log-table { font-size: .8rem; }
.log-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #64748b; }
.violation-pill {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 22px; height: 22px;
  border-radius: 4px;
  font-size: .7rem; font-weight: 700;
  background: #f1f5f9; color: #475569;
}
.violation-pill.danger { background: #fef2f2; color: #dc2626; }
.violation-pill.warn   { background: #fff7ed; color: #ea580c; }
</style>

<!-- Page header -->
<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">Document Security Analytics</h1>
    <p class="page-header__subtitle">Monitor document access, security violations, and user behaviour.</p>
  </div>
  <button onclick="window.print()" class="btn btn-sm" style="background:var(--color-primary);color:#fff;border:none;border-radius:8px;padding:.5rem 1rem;font-size:.875rem;font-weight:500;" title="Export Report">
    <i class="bi bi-printer me-1"></i> Export Report
  </button>
</div>

<!-- Tab Navigation -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <a href="?tab=overview" class="analytics-tab-btn <?= $tab === 'overview' ? 'active' : '' ?>">
    <i class="bi bi-grid-1x2"></i> Overview
  </a>
  <a href="?tab=documents" class="analytics-tab-btn <?= $tab === 'documents' ? 'active' : '' ?>">
    <i class="bi bi-file-earmark-text"></i> Documents
  </a>
  <a href="?tab=risk" class="analytics-tab-btn <?= $tab === 'risk' ? 'active' : '' ?>">
    <i class="bi bi-person-exclamation"></i> Risk Users
  </a>
  <a href="?tab=violations" class="analytics-tab-btn <?= $tab === 'violations' ? 'active' : '' ?>">
    <i class="bi bi-shield-exclamation"></i> Violation Logs
  </a>
</div>

<!-- ── OVERVIEW TAB ─────────────────────────────────────────── -->
<?php if ($tab === 'overview'): ?>

<div class="row g-3 mb-4">
  <?php
  $statCards = [
    ['label' => 'Total Document Views',   'value' => number_format((int)($stats['total_views'] ?? 0)),        'icon' => 'bi-eye',               'color' => '#2563eb', 'sub' => 'All-time viewing sessions'],
    ['label' => 'Unique Viewers',         'value' => number_format((int)($stats['unique_viewers'] ?? 0)),     'icon' => 'bi-people',            'color' => '#7c3aed', 'sub' => 'Distinct users'],
    ['label' => 'Avg Read Time',          'value' => formatDuration((int)($stats['avg_duration'] ?? 0)),      'icon' => 'bi-clock',             'color' => '#059669', 'sub' => 'Per session'],
    ['label' => 'Total Documents',        'value' => number_format((int)($stats['total_documents'] ?? 0)),    'icon' => 'bi-file-earmark-text', 'color' => '#0891b2', 'sub' => 'Uploaded'],
    ['label' => 'Copy Attempts',          'value' => number_format((int)($stats['total_copy_attempts'] ?? 0)),'icon' => 'bi-clipboard-x',       'color' => '#dc2626', 'sub' => 'Blocked events'],
    ['label' => 'Print Attempts',         'value' => number_format((int)($stats['total_print_attempts'] ?? 0)),'icon' => 'bi-printer',          'color' => '#ea580c', 'sub' => 'Blocked events'],
    ['label' => 'Fullscreen Exits',       'value' => number_format((int)($stats['total_fullscreen_exits'] ?? 0)),'icon' => 'bi-fullscreen-exit','color' => '#f59e0b', 'sub' => 'Policy violations'],
    ['label' => 'Total Violations',       'value' => number_format((int)($stats['total_violations'] ?? 0)),   'icon' => 'bi-shield-exclamation','color' => '#be123c', 'sub' => 'All security events'],
  ];
  ?>
  <?php foreach ($statCards as $card): ?>
    <div class="col-6 col-lg-3">
      <div class="analytics-stat-card">
        <div class="analytics-stat-card__icon" style="color:<?= $card['color'] ?>;">
          <i class="bi <?= $card['icon'] ?>"></i>
        </div>
        <div class="analytics-stat-card__label"><?= $card['label'] ?></div>
        <div class="analytics-stat-card__value" style="color:<?= $card['color'] ?>;"><?= $card['value'] ?></div>
        <div class="analytics-stat-card__sub"><?= $card['sub'] ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Top documents by views -->
<div class="data-table-wrap mb-4">
  <div class="d-flex align-items-center justify-content-between" style="padding:1rem 1.25rem .75rem;">
    <div style="font-weight:600;font-size:.9rem;">Top Documents by Views</div>
    <a href="?tab=documents" class="analytics-tab-btn" style="font-size:.75rem;padding:.3rem .7rem;">View All</a>
  </div>
  <table class="data-table log-table">
    <thead>
      <tr>
        <th>Document</th><th>Type</th><th>Views</th><th>Viewers</th>
        <th>Avg Duration</th><th>Copy Attempts</th><th>Print Attempts</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (array_slice($docStats, 0, 5) as $ds): ?>
        <tr>
          <td><span style="font-weight:600;font-size:.82rem;"><?= e($ds['title']) ?></span></td>
          <td><span class="badge bg-light text-dark border" style="font-size:.7rem;"><?= strtoupper(e($ds['file_type'] ?? '')) ?></span></td>
          <td><?= number_format((int)$ds['total_views']) ?></td>
          <td><?= number_format((int)$ds['unique_viewers']) ?></td>
          <td><?= formatDuration((int)round((float)$ds['avg_duration'])) ?></td>
          <td>
            <span class="violation-pill <?= (int)$ds['total_copy_attempts'] > 0 ? 'danger' : '' ?>">
              <?= (int)$ds['total_copy_attempts'] ?>
            </span>
          </td>
          <td>
            <span class="violation-pill <?= (int)$ds['total_print_attempts'] > 0 ? 'warn' : '' ?>">
              <?= (int)$ds['total_print_attempts'] ?>
            </span>
          </td>
          <td>
            <a href="?tab=violations&doc_id=<?= (int)$ds['id'] ?>" class="action-btn action-btn--primary" title="View Logs">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($docStats)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-graph-up empty-state__icon"></i><p class="empty-state__title">No view data yet</p></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Top risk users preview -->
<div class="data-table-wrap">
  <div class="d-flex align-items-center justify-content-between" style="padding:1rem 1.25rem .75rem;">
    <div style="font-weight:600;font-size:.9rem;">High Risk Users</div>
    <a href="?tab=risk" class="analytics-tab-btn" style="font-size:.75rem;padding:.3rem .7rem;">View All</a>
  </div>
  <table class="data-table log-table">
    <thead>
      <tr><th>User</th><th>Risk Score</th><th>Copy</th><th>Print</th><th>DevTools</th><th>Sessions</th></tr>
    </thead>
    <tbody>
      <?php foreach (array_slice($riskUsers, 0, 5) as $u): ?>
        <?php
          $score = (int)$u['risk_score'];
          $riskClass = $score >= 30 ? 'risk-critical' : ($score >= 15 ? 'risk-high' : ($score >= 5 ? 'risk-medium' : 'risk-low'));
          $riskLabel = $score >= 30 ? 'Critical'      : ($score >= 15 ? 'High'      : ($score >= 5 ? 'Medium'     : 'Low'));
        ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.82rem;"><?= e($u['full_name']) ?></div>
            <div style="font-size:.72rem;color:#64748b;"><?= e($u['email']) ?></div>
          </td>
          <td>
            <span class="risk-badge <?= $riskClass ?>">
              <?= $riskLabel ?> (<?= $score ?>)
            </span>
          </td>
          <td><span class="violation-pill <?= (int)$u['copy_attempts'] > 0 ? 'danger' : '' ?>"><?= (int)$u['copy_attempts'] ?></span></td>
          <td><span class="violation-pill <?= (int)$u['print_attempts'] > 0 ? 'warn' : '' ?>"><?= (int)$u['print_attempts'] ?></span></td>
          <td><span class="violation-pill <?= (int)$u['devtools_count'] > 0 ? 'danger' : '' ?>"><?= (int)$u['devtools_count'] ?></span></td>
          <td><?= (int)$u['total_sessions'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($riskUsers)): ?>
        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-check empty-state__icon"></i><p class="empty-state__title">No violations recorded</p></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ── DOCUMENTS TAB ─────────────────────────────────────────── -->
<?php elseif ($tab === 'documents'): ?>

<div class="data-table-wrap">
  <table class="data-table log-table">
    <thead>
      <tr>
        <th>Document</th><th>Type</th><th>Total Views</th><th>Unique Viewers</th>
        <th>Avg Duration</th><th>Copy</th><th>Print</th><th>FS Exits</th>
        <th>DevTools</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($docStats as $ds): ?>
        <tr>
          <td><span style="font-weight:600;font-size:.82rem;"><?= e($ds['title']) ?></span></td>
          <td><span class="badge bg-light text-dark border" style="font-size:.7rem;"><?= strtoupper(e($ds['file_type'] ?? '?')) ?></span></td>
          <td><?= number_format((int)$ds['total_views']) ?></td>
          <td><?= number_format((int)$ds['unique_viewers']) ?></td>
          <td><?= formatDuration((int)round((float)$ds['avg_duration'])) ?></td>
          <td><span class="violation-pill <?= (int)$ds['total_copy_attempts'] > 0 ? 'danger' : '' ?>"><?= (int)$ds['total_copy_attempts'] ?></span></td>
          <td><span class="violation-pill <?= (int)$ds['total_print_attempts'] > 0 ? 'warn' : '' ?>"><?= (int)$ds['total_print_attempts'] ?></span></td>
          <td><span class="violation-pill <?= (int)$ds['total_fullscreen_exits'] > 0 ? 'warn' : '' ?>"><?= (int)$ds['total_fullscreen_exits'] ?></span></td>
          <td><span class="violation-pill <?= (int)$ds['devtools_count'] > 0 ? 'danger' : '' ?>"><?= (int)$ds['devtools_count'] ?></span></td>
          <td>
            <a href="?tab=violations&doc_id=<?= (int)$ds['id'] ?>" class="action-btn action-btn--primary" title="View Logs">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($docStats)): ?>
        <tr><td colspan="10"><div class="empty-state"><i class="bi bi-file-earmark empty-state__icon"></i><p class="empty-state__title">No documents found</p></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ── RISK USERS TAB ─────────────────────────────────────────── -->
<?php elseif ($tab === 'risk'): ?>

<div class="data-table-wrap">
  <table class="data-table log-table">
    <thead>
      <tr>
        <th>User</th><th>Risk Score</th><th>Copy</th><th>Print</th><th>FS Exits</th>
        <th>Tab Switches</th><th>DevTools</th><th>Screenshots</th><th>Sessions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($riskUsers as $u): ?>
        <?php
          $score = (int)$u['risk_score'];
          $riskClass = $score >= 30 ? 'risk-critical' : ($score >= 15 ? 'risk-high' : ($score >= 5 ? 'risk-medium' : 'risk-low'));
          $riskLabel = $score >= 30 ? 'Critical'      : ($score >= 15 ? 'High'      : ($score >= 5 ? 'Medium'     : 'Low'));
        ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.82rem;"><?= e($u['full_name']) ?></div>
            <div style="font-size:.72rem;color:#64748b;"><?= e($u['email']) ?></div>
          </td>
          <td><span class="risk-badge <?= $riskClass ?>"><?= $riskLabel ?> (<?= $score ?>)</span></td>
          <td><span class="violation-pill <?= (int)$u['copy_attempts'] > 0 ? 'danger' : '' ?>"><?= (int)$u['copy_attempts'] ?></span></td>
          <td><span class="violation-pill <?= (int)$u['print_attempts'] > 0 ? 'warn' : '' ?>"><?= (int)$u['print_attempts'] ?></span></td>
          <td><span class="violation-pill <?= (int)$u['fullscreen_exits'] > 0 ? 'warn' : '' ?>"><?= (int)$u['fullscreen_exits'] ?></span></td>
          <td><?= (int)$u['tab_switches'] ?></td>
          <td><span class="violation-pill <?= (int)$u['devtools_count'] > 0 ? 'danger' : '' ?>"><?= (int)$u['devtools_count'] ?></span></td>
          <td><?= (int)$u['screenshot_events'] ?></td>
          <td><?= (int)$u['total_sessions'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($riskUsers)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="bi bi-person-check empty-state__icon"></i><p class="empty-state__title">No violations recorded</p><p class="empty-state__sub">All users have a clean record.</p></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ── VIOLATIONS TAB ─────────────────────────────────────────── -->
<?php elseif ($tab === 'violations'): ?>

<!-- Document filter -->
<div class="d-flex align-items-center gap-3 mb-3">
  <form method="GET" action="" class="d-flex align-items-center gap-2">
    <input type="hidden" name="tab" value="violations">
    <select name="doc_id" class="form-select form-select-sm" style="max-width:280px;" onchange="this.form.submit()">
      <option value="0">— All Documents —</option>
      <?php foreach ($docStats as $ds): ?>
        <option value="<?= (int)$ds['id'] ?>" <?= $selectedDocId === (int)$ds['id'] ? 'selected' : '' ?>>
          <?= e($ds['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
  </form>
  <?php if ($selectedDoc): ?>
    <span style="font-size:.82rem;color:#64748b;">Showing logs for: <strong><?= e($selectedDoc['title']) ?></strong></span>
  <?php endif; ?>
</div>

<div class="data-table-wrap">
  <table class="data-table log-table">
    <thead>
      <tr>
        <th>User</th><th>IP</th><th>View Start</th><th>Duration</th>
        <th>Copy</th><th>Print</th><th>FS Exits</th><th>Tab Sw.</th>
        <th>DevTools</th><th>Browser</th><th>Device</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($docLogs)): ?>
        <?php foreach ($docLogs as $log): ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.8rem;"><?= e($log['full_name']) ?></div>
              <div style="font-size:.7rem;color:#64748b;"><?= e($log['email']) ?></div>
            </td>
            <td style="font-size:.75rem;font-family:monospace;"><?= e($log['ip_address'] ?? '—') ?></td>
            <td style="font-size:.77rem;"><?= e(date('M j, Y H:i', strtotime($log['view_start']))) ?></td>
            <td style="font-size:.77rem;"><?= $log['duration_seconds'] ? formatDuration((int)$log['duration_seconds']) : '—' ?></td>
            <td><span class="violation-pill <?= (int)$log['copy_attempt_count'] > 0 ? 'danger' : '' ?>"><?= (int)$log['copy_attempt_count'] ?></span></td>
            <td><span class="violation-pill <?= (int)$log['print_attempt_count'] > 0 ? 'warn' : '' ?>"><?= (int)$log['print_attempt_count'] ?></span></td>
            <td><span class="violation-pill <?= (int)$log['fullscreen_exit_count'] > 0 ? 'warn' : '' ?>"><?= (int)$log['fullscreen_exit_count'] ?></span></td>
            <td><?= (int)$log['tab_switch_count'] ?></td>
            <td>
              <?php if ($log['devtools_detected']): ?>
                <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:.68rem;">YES</span>
              <?php else: ?>
                <span style="color:#94a3b8;font-size:.77rem;">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.77rem;"><?= e($log['browser'] ?? '—') ?></td>
            <td style="font-size:.77rem;"><?= e($log['device_type'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php elseif ($selectedDocId > 0): ?>
        <tr><td colspan="11"><div class="empty-state"><i class="bi bi-journal empty-state__icon"></i><p class="empty-state__title">No logs for this document yet</p></div></td></tr>
      <?php else: ?>
        <tr><td colspan="11"><div class="empty-state"><i class="bi bi-funnel empty-state__icon"></i><p class="empty-state__title">Select a document to view logs</p></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

<?php renderAdminFooter(); ?>
