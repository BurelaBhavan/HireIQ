<?php
/**
 * Test Management logic
 */

require_once __DIR__ . '/../config/database.php';

function getAllTests($search = '', $status = '', $page = 1, $perPage = 20) {
    $db = getDB();
    $sql = "SELECT t.*, u.full_name as creator_name,
            (SELECT COUNT(*) FROM test_questions WHERE test_id = t.id) as question_count
            FROM tests t 
            LEFT JOIN users u ON t.created_by = u.id 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (t.title LIKE :search OR t.description LIKE :search)";
        $params['search'] = "%$search%";
    }
    if ($status !== '') {
        $sql .= " AND t.status = :status";
        $params['status'] = $status;
    }

    // Count total
    $countSql = str_replace("SELECT t.*, u.full_name as creator_name,
            (SELECT COUNT(*) FROM test_questions WHERE test_id = t.id) as question_count", "SELECT COUNT(*)", $sql);
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $sql .= " ORDER BY t.created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)(($page - 1) * $perPage);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['rows' => $rows, 'total' => $total];
}

function getTestById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createTest($title, $description, $duration, $status, $userId) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO tests (title, description, duration, status, created_by) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$title, $description, $duration, $status, $userId]);
}

function updateTest($id, $title, $description, $duration, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE tests SET title = ?, description = ?, duration = ?, status = ? WHERE id = ?");
    return $stmt->execute([$title, $description, $duration, $status, $id]);
}

function setTestStatus($id, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE tests SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

function deleteTest($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM tests WHERE id = ?");
    return $stmt->execute([$id]);
}

function getTestStats() {
    $db = getDB();
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft
            FROM tests";
    $stmt = $db->query($sql);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'total'  => (int) ($res['total'] ?? 0),
        'active' => (int) ($res['active'] ?? 0),
        'draft'  => (int) ($res['draft'] ?? 0)
    ];
}
