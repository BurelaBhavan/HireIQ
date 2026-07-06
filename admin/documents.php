<?php
/**
 * Admin — Document Management
 * AI Interview Assessment Platform — Phase 3
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/documents.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireRole('super_admin');

// ── POST handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        
        if (empty($title) || empty($_FILES['document']['name'])) {
            flash('documents', 'Title and file are required.', 'error');
        } else {
            $result = uploadDocument($title, $desc, $_FILES['document'], (int) ($_SESSION['user_id'] ?? 0));
            if ($result['success']) {
                flash('documents', 'Document uploaded successfully.', 'success');
            } else {
                flash('documents', $result['error'], 'error');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['document_id'] ?? 0);
        if ($id > 0 && deleteDocument($id)) {
            flash('documents', 'Document deleted successfully.', 'success');
        } else {
            flash('documents', 'Failed to delete document.', 'error');
        }
    }

    redirect(BASE_URL . '/admin/documents.php');
}

$documents = getAllDocuments();

require_once __DIR__ . '/../includes/layout.php';
renderHeader('Documents', 'dashboard-page');
renderAdminNav('documents');
?>

<div class="page-header d-flex align-items-start justify-content-between">
  <div>
    <h1 class="page-header__title">Document Management</h1>
    <p class="page-header__subtitle">Upload training materials, policies, or guides for candidates.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/admin/document_analytics.php" class="btn btn-sm" style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.875rem;font-weight:500;color:var(--color-text-secondary);">
      <i class="bi bi-shield-check"></i> Security Analytics
    </a>
    <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal" style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);padding:.5rem 1rem;font-size:.875rem;font-weight:500;">
      <i class="bi bi-upload"></i> Upload Document
    </button>
  </div>
</div>

<!-- Flash -->
<?php renderAlert(getFlash('documents')); ?>

<!-- ── Table ── -->
<div class="data-table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Document</th>
        <th>File Type</th>
        <th>Reads</th>
        <th>Uploaded By</th>
        <th>Date</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($documents)): ?>
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <i class="bi bi-file-earmark-text empty-state__icon"></i>
              <p class="empty-state__title">No documents uploaded</p>
              <p class="empty-state__sub">Click "Upload Document" to share materials with candidates.</p>
            </div>
          </td>
        </tr>
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
          ?>
          <tr>
            <td>
              <div class="d-flex align-items-start gap-2">
                <i class="bi <?= $icon ?>" style="font-size: 1.25rem;"></i>
                <div>
                  <div style="font-weight:600;font-size:.875rem;color:var(--color-text-primary);"><?= e($doc['title']) ?></div>
                  <?php if (!empty($doc['description'])): ?>
                    <div style="font-size:.75rem;color:var(--color-text-muted);margin-top:.15rem;max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= e($doc['description']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="badge bg-light text-dark border"><?= $ext ?></span></td>
            <td><span class="badge bg-info-subtle text-info border"><?= (int)$doc['read_count'] ?> views</span></td>
            <td style="font-size:.85rem;color:var(--color-text-secondary);"><?= e($doc['uploader_name']) ?></td>
            <td style="font-size:.8375rem;color:var(--color-text-secondary);"><?= formatDate($doc['uploaded_at']) ?></td>
            <td>
              <div class="d-flex gap-1 justify-content-end">
                <a href="<?= BASE_URL . '/document_viewer.php?id=' . (int)$doc['id'] ?>" target="_blank" class="action-btn action-btn--primary" title="Preview Secure Viewer">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="<?= BASE_URL . '/admin/document_analytics.php?tab=violations&doc_id=' . (int)$doc['id'] ?>" class="action-btn" title="View Activity Logs" style="border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:.35rem .5rem;color:var(--color-text-secondary);">
                  <i class="bi bi-shield-check"></i>
                </a>
                <button class="action-btn action-btn--danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteDocModal" data-doc-id="<?= (int)$doc['id'] ?>" data-doc-title="<?= e($doc['title']) ?>">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ── Upload Modal ── -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="action" value="upload" />
        <div class="modal-body" style="padding:1.5rem;">
          <div class="mb-3">
            <label class="form-label" style="font-weight: 500;">Document Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-weight: 500;">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-weight: 500;">Select File <span class="text-danger">*</span></label>
            <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
            <div class="form-text">Allowed formats: PDF, DOCX, PPT. Max size: 10MB.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm" style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.45rem .875rem;" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm" style="background:var(--color-primary);color:#fff;border-radius:var(--radius-md);padding:.45rem .875rem;font-weight:500;">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Delete Modal ── -->
<div class="modal fade" id="deleteDocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">
        <p style="color:var(--color-text-secondary);font-size:.9rem;">Are you sure you want to permanently delete <strong id="deleteDocTitle"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm" style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.45rem .875rem;" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="document_id" id="deleteDocId" value="" />
          <button type="submit" class="btn btn-sm btn-danger" style="border-radius:var(--radius-md);padding:.45rem .875rem;">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteDocModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('deleteDocId').value = btn.dataset.docId;
  document.getElementById('deleteDocTitle').textContent = btn.dataset.docTitle;
});
</script>

<?php renderAdminFooter(); ?>
