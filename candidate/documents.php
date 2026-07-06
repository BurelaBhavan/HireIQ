<?php
/**
 * Candidate Documents
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/documents.php';
require_once __DIR__ . '/../includes/candidate_layout.php';

requireRole('candidate');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $docId = (int) ($_POST['document_id'] ?? 0);
    
    if ($action === 'mark_read' && $docId > 0) {
        markDocumentAsRead($docId, $userId);
        
        // Return JSON if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    redirect(BASE_URL . '/candidate/documents.php');
}

$documents = getCandidateDocuments($userId);

require_once __DIR__ . '/../includes/layout.php';
renderHeader('My Documents', 'dashboard-page');
renderCandidateNav('documents');
?>

<div class="page-header">
  <h1 class="page-header__title">My Documents</h1>
  <p class="page-header__subtitle">Access training materials, guides, and policies provided by the recruitment team.</p>
</div>

<div class="row g-4">
  <?php if (empty($documents)): ?>
    <div class="col-12">
      <div class="empty-state" style="background:var(--color-bg);border-radius:var(--radius-md);">
        <i class="bi bi-file-earmark-text empty-state__icon"></i>
        <p class="empty-state__title">No documents available</p>
        <p class="empty-state__sub">Check back later for newly uploaded materials.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($documents as $doc): ?>
      <?php 
      $ext = strtoupper(pathinfo($doc['file_path'], PATHINFO_EXTENSION)); 
      $icon = match($ext) {
        'PDF' => 'bi-file-earmark-pdf text-danger',
        'DOCX', 'DOC' => 'bi-file-earmark-word text-primary',
        'PPTX', 'PPT' => 'bi-file-earmark-slides text-warning',
        default => 'bi-file-earmark-text text-secondary'
      };
      $isRead = !empty($doc['read_at']);
      ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="content-card h-100 d-flex flex-column" style="padding:1.5rem; position: relative;">
          <?php if (!$isRead): ?>
            <span class="position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle" style="margin-top: .5rem; margin-left: -.5rem;" title="Unread">
              <span class="visually-hidden">New alerts</span>
            </span>
          <?php endif; ?>
          
          <div class="d-flex align-items-start gap-3 mb-3">
            <i class="bi <?= $icon ?>" style="font-size: 2rem;"></i>
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: .25rem; color: var(--color-text-primary);"><?= e($doc['title']) ?></h3>
              <span class="badge bg-light text-dark border"><?= $ext ?></span>
              <span style="font-size: .75rem; color: var(--color-text-muted); margin-left: .5rem;"><?= formatDate($doc['uploaded_at']) ?></span>
            </div>
          </div>
          
          <?php if (!empty($doc['description'])): ?>
            <p style="font-size: .85rem; color: var(--color-text-secondary); flex-grow: 1;">
              <?= nl2br(e($doc['description'])) ?>
            </p>
          <?php else: ?>
            <div style="flex-grow: 1;"></div>
          <?php endif; ?>
          
          <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
            <?php if ($isRead): ?>
              <span class="text-success" style="font-size: .8rem; font-weight: 500;"><i class="bi bi-check-all"></i> Read</span>
            <?php else: ?>
              <span class="text-danger fw-bold" style="font-size: .8rem;">New</span>
            <?php endif; ?>
            
            <a href="<?= BASE_URL . '/document_viewer.php?id=' . (int)$doc['id'] ?>" class="btn btn-sm btn-outline-primary read-doc-btn" data-doc-id="<?= (int)$doc['id'] ?>" data-is-read="<?= $isRead ? '1' : '0' ?>">
              <i class="bi bi-shield-lock"></i> Open Secure Viewer
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const csrfToken = '<?= csrfToken() ?>';
  
  document.querySelectorAll('.read-doc-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      if (this.dataset.isRead === '0') {
        const docId = this.dataset.docId;
        
        // Send AJAX request to mark as read
        fetch('<?= BASE_URL ?>/candidate/documents.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: `action=mark_read&document_id=${docId}&csrf_token=${csrfToken}`
        }).then(response => response.json()).then(data => {
          if (data.success) {
            // Optional: update UI dynamically without reload
            // Or just let them click it and if they refresh it'll be read
          }
        });
        
        // Optimistically update UI
        this.dataset.isRead = '1';
        const card = this.closest('.content-card');
        const badge = card.querySelector('.position-absolute');
        if (badge) badge.remove();
        
        const statusSpan = card.querySelector('.border-top span');
        statusSpan.className = 'text-success';
        statusSpan.style.fontWeight = '500';
        statusSpan.innerHTML = '<i class="bi bi-check-all"></i> Read';
      }
    });
  });
});
</script>

<?php renderCandidateFooter(); ?>
