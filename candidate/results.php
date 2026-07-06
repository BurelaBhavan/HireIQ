<?php
/**
 * Candidate — My Results
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/candidate_layout.php';

requireRole('candidate');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id']);

$db = getDB();
$stmt = $db->prepare("
    SELECT ir.*, i.title as interview_title, i.duration as interview_duration, a.start_time, a.end_time 
    FROM interview_results ir
    JOIN interviews i ON ir.interview_id = i.id
    JOIN attempts a ON ir.attempt_id = a.id
    WHERE ir.candidate_id = ? AND ir.published_at IS NOT NULL
    ORDER BY ir.published_at DESC
");
$stmt->execute([$userId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('My Results', 'dashboard-page');
renderCandidateNav('results');
?>

<div class="page-header">
  <h1 class="page-header__title">My Results</h1>
  <p class="page-header__subtitle">Review selection status and feedback for your completed interview assessments.</p>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <?php if (empty($results)): ?>
      <div class="content-card">
        <div class="empty-state" style="padding: 4rem 2rem;">
          <i class="bi bi-trophy empty-state__icon" style="color: var(--color-text-muted);"></i>
          <p class="empty-state__title">No results published yet</p>
          <p class="empty-state__sub">Once the recruiters evaluate your completed assessments and publish the results, they will appear here.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-4">
        <?php foreach ($results as $res): ?>
          <?php
          $decision = $res['decision'];
          $badgeClass = match($decision) {
              'selected' => 'success',
              'rejected' => 'danger',
              default    => 'secondary'
          };
          $icon = match($decision) {
              'selected' => 'bi-check-circle-fill',
              'rejected' => 'bi-x-circle-fill',
              default    => 'bi-clock-fill'
          };
          ?>
          <div class="content-card" style="padding: 1.5rem; border-left: 4px solid var(--color-<?= $badgeClass ?>);">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 border-bottom pb-2">
              <div>
                <h3 class="mb-1" style="font-size: 1.15rem; font-weight: 700; color: var(--color-text-primary);">
                  <?= e($res['interview_title']) ?>
                </h3>
                <div style="font-size: .8rem; color: var(--color-text-muted);">
                  <i class="bi bi-clock me-1"></i>Duration: <?= $res['interview_duration'] ?> mins &bull; Completed on <?= formatDate($res['end_time']) ?>
                </div>
              </div>
              <div>
                <span class="badge bg-<?= $badgeClass ?>-subtle text-<?= $badgeClass ?>" style="font-size: .9rem; font-weight: 700; border: 1px solid currentColor; padding: .5rem .85rem; border-radius: 8px;">
                  <i class="bi <?= $icon ?> me-1"></i><?= ucfirst($decision) ?>
                </span>
              </div>
            </div>

            <div class="feedback-section" style="background: var(--color-bg-gray); border-radius: 8px; padding: 1.25rem;">
              <h5 class="mb-2" style="font-size: .9rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em;">
                <i class="bi bi-chat-left-text-fill me-1 text-primary"></i> Recruiter Feedback & Remarks
              </h5>
              <p class="mb-0" style="font-size: .925rem; color: var(--color-text-primary); line-height: 1.6; white-space: pre-line;">
                <?= e($res['conclusion']) ?>
              </p>
            </div>
            
            <div class="mt-3 text-end" style="font-size: .75rem; color: var(--color-text-muted);">
              Published on: <?= formatDate($res['published_at']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="content-card mb-4" style="padding: 1.5rem;">
      <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Next Steps</h4>
      <div class="d-flex flex-column gap-3">
        <div class="d-flex gap-3">
          <div style="width: 32px; height: 32px; border-radius: 50%; background: #EFF6FF; display: flex; align-items: center; justify-content: center; color: #3B82F6; flex-shrink: 0;">
            <i class="bi bi-envelope"></i>
          </div>
          <div>
            <h5 class="mb-1" style="font-size: .875rem; font-weight: 600;">Check Your Email</h5>
            <p class="mb-0 text-muted" style="font-size: .775rem;">If you are selected, you will receive an offer letter or details on subsequent rounds via email.</p>
          </div>
        </div>
        
        <div class="d-flex gap-3">
          <div style="width: 32px; height: 32px; border-radius: 50%; background: #F0FDF4; display: flex; align-items: center; justify-content: center; color: #22C55E; flex-shrink: 0;">
            <i class="bi bi-file-earmark-text"></i>
          </div>
          <div>
            <h5 class="mb-1" style="font-size: .875rem; font-weight: 600;">Review Shared Documents</h5>
            <p class="mb-0 text-muted" style="font-size: .775rem;">Recruiters might share preparatory materials or onboarding packets in the Documents section.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php renderCandidateFooter(); ?>
