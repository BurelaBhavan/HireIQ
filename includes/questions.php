<?php
/**
 * Question Bank Management functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function getQuestions($filters = []) {
    $db = getDB();
    $source = $filters['question_source'] ?? 'bank';

    $sql = "SELECT q.*, u.full_name as creator_name, i.title as interview_title 
            FROM questions q 
            LEFT JOIN users u ON q.created_by = u.id 
            LEFT JOIN interviews i ON q.interview_id_ref = i.id
            WHERE 1=1";
    $params = [];

    if ($source !== 'all') {
        $sql .= " AND q.question_source = :question_source";
        $params['question_source'] = $source;
    }

    if (!empty($filters['interview_id_ref'])) {
        $sql .= " AND q.interview_id_ref = :interview_id_ref";
        $params['interview_id_ref'] = (int) $filters['interview_id_ref'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (q.question_text LIKE :search OR q.expected_topics LIKE :search OR q.category LIKE :search)";
        $params['search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['difficulty'])) {
        $sql .= " AND q.difficulty = :difficulty";
        $params['difficulty'] = $filters['difficulty'];
    }

    if (!empty($filters['category'])) {
        $sql .= " AND q.category = :category";
        $params['category'] = $filters['category'];
    }

    $sql .= " ORDER BY q.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQuestionById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createQuestion($data) {
    $db = getDB();
    $source = $data['question_source'] ?? 'bank';
    $interviewIdRef = isset($data['interview_id_ref']) && $data['interview_id_ref'] > 0 ? (int)$data['interview_id_ref'] : null;

    $stmt = $db->prepare("INSERT INTO questions (question_text, expected_topics, difficulty, category, created_by, question_source, interview_id_ref) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $success = $stmt->execute([
        $data['question_text'],
        $data['expected_topics'],
        $data['difficulty'],
        $data['category'],
        $data['created_by'],
        $source,
        $interviewIdRef
    ]);
    return $success ? (int)$db->lastInsertId() : 0;
}

function updateQuestion($id, $data) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE questions SET question_text = ?, expected_topics = ?, difficulty = ?, category = ? WHERE id = ?");
    return $stmt->execute([
        $data['question_text'],
        $data['expected_topics'],
        $data['difficulty'],
        $data['category'],
        $id
    ]);
}

function deleteQuestion($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
    return $stmt->execute([$id]);
}

function getUniqueCategories() {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT category FROM questions WHERE category IS NOT NULL AND category != '' ORDER BY category");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
