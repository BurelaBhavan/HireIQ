<?php
/**
 * api/acknowledge.php — Document Confidentiality Acknowledgment
 * Stores user consent before document viewing.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/documents.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

header('Content-Type: application/json');
header('Cache-Control: no-store');

$userId = (int) $_SESSION['user_id'];
$docId  = (int) ($_POST['doc_id'] ?? 0);
if ($docId <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid doc_id']); exit; }

$doc = getDocumentById($docId);
if (!$doc) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

try {
    $db = getDB();
    // Only insert once per session per doc
    $exists = $db->prepare("SELECT id FROM document_acknowledgments WHERE document_id=? AND user_id=? LIMIT 1");
    $exists->execute([$docId, $userId]);
    if (!$exists->fetchColumn()) {
        $db->prepare("INSERT INTO document_acknowledgments (document_id,user_id,ip_address,user_agent) VALUES (?,?,?,?)")
           ->execute([$docId, $userId, $ip, $ua]);
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
}
