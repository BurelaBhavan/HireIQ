<?php
/**
 * Notifications & Invitations Management
 */

require_once __DIR__ . '/../config/database.php';

// ── Notifications ─────────────────────────────────────────────

function createNotification($userId, $title, $message, $type) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $title, $message, $type]);
}

function getUserNotifications($userId, $unreadOnly = false) {
    $db = getDB();
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unreadOnly) {
        $sql .= " AND is_read = 0";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationRead($id, $userId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$id, $userId]);
}

function markAllNotificationsRead($userId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    return $stmt->execute([$userId]);
}

function getUnreadNotificationCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

// ── Invitations ───────────────────────────────────────────────

function createInterviewInvitation($interviewId, $candidateId, $adminId) {
    $db = getDB();
    
    // Check if pending invitation already exists
    $stmt = $db->prepare("SELECT id FROM interview_invitations WHERE interview_id = ? AND candidate_id = ? AND status IN ('Pending', 'Accepted')");
    $stmt->execute([$interviewId, $candidateId]);
    if ($stmt->fetch()) {
        return false; // Already invited/accepted
    }
    
    $stmt = $db->prepare("INSERT INTO interview_invitations (interview_id, candidate_id, invited_by) VALUES (?, ?, ?)");
    $success = $stmt->execute([$interviewId, $candidateId, $adminId]);
    
    if ($success) {
        // Create Notification
        $stmt2 = $db->prepare("SELECT title FROM interviews WHERE id = ?");
        $stmt2->execute([$interviewId]);
        $title = $stmt2->fetchColumn();
        
        createNotification($candidateId, "New Interview Assignment", "You have been assigned to the interview: $title. Please check your pending invitations.", 'Interview');
    }
    
    return $success;
}

function createTestInvitation($testId, $candidateId, $adminId) {
    $db = getDB();
    
    // Check if pending invitation already exists
    $stmt = $db->prepare("SELECT id FROM test_invitations WHERE test_id = ? AND candidate_id = ? AND status IN ('Pending', 'Accepted')");
    $stmt->execute([$testId, $candidateId]);
    if ($stmt->fetch()) {
        return false; // Already invited/accepted
    }
    
    $stmt = $db->prepare("INSERT INTO test_invitations (test_id, candidate_id, invited_by) VALUES (?, ?, ?)");
    $success = $stmt->execute([$testId, $candidateId, $adminId]);
    
    if ($success) {
        // Create Notification
        $stmt2 = $db->prepare("SELECT title FROM tests WHERE id = ?");
        $stmt2->execute([$testId]);
        $title = $stmt2->fetchColumn();
        
        createNotification($candidateId, "New Test Assignment", "You have been assigned to the test: $title. Please check your assigned tests.", 'Test');
    }
    
    return $success;
}

function updateInterviewInvitationStatus($invitationId, $candidateId, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE interview_invitations SET status = ?, response_date = NOW() WHERE id = ? AND candidate_id = ?");
    return $stmt->execute([$status, $invitationId, $candidateId]);
}

function updateTestInvitationStatus($invitationId, $candidateId, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE test_invitations SET status = ?, response_date = NOW() WHERE id = ? AND candidate_id = ?");
    return $stmt->execute([$status, $invitationId, $candidateId]);
}

function getCandidateInterviewInvitations($candidateId, $status = null) {
    $db = getDB();
    $sql = "SELECT ii.*, i.title, i.duration, i.description 
            FROM interview_invitations ii 
            JOIN interviews i ON ii.interview_id = i.id 
            WHERE ii.candidate_id = ?";
    $params = [$candidateId];
    if ($status) {
        $sql .= " AND ii.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY ii.invitation_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCandidateTestInvitations($candidateId, $status = null) {
    $db = getDB();
    $sql = "SELECT ti.*, t.title, t.duration, t.description 
            FROM test_invitations ti 
            JOIN tests t ON ti.test_id = t.id 
            WHERE ti.candidate_id = ?";
    $params = [$candidateId];
    if ($status) {
        $sql .= " AND ti.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY ti.invitation_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
