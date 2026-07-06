<?php
/**
 * Interview Data Access
 * AI Interview Assessment Platform — Phase 2
 */

declare(strict_types=1);

// ── Read ───────────────────────────────────────────────────────

/**
 * Paginated interview list with optional search + status filter.
 *
 * @return array{rows: array[], total: int}
 */
function getAllInterviews(
    string $search  = '',
    string $status  = '',
    int    $page    = 1,
    int    $perPage = 20
): array {
    $db     = getDB();
    $offset = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]          = 'i.title LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $validStatuses = ['draft', 'active', 'archived'];
    if (in_array($status, $validStatuses, true)) {
        $where[]          = 'i.status = :status';
        $params[':status'] = $status;
    }

    $sql = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM interviews i WHERE $sql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $rowStmt = $db->prepare(
        "SELECT
            i.id,
            i.title,
            i.description,
            i.duration,
            i.difficulty,
            i.status,
            i.created_at,
            i.updated_at,
            u.full_name AS created_by_name,
            COUNT(s.id) AS session_count
         FROM interviews i
         JOIN  users u ON u.id = i.created_by
         LEFT JOIN interview_sessions s ON s.interview_id = i.id
         WHERE $sql
         GROUP BY i.id
         ORDER BY i.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) {
        $rowStmt->bindValue($k, $v);
    }
    $rowStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $rowStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $rowStmt->execute();
    $rows = $rowStmt->fetchAll();

    return ['rows' => $rows, 'total' => $total];
}

/**
 * Fetch a single interview by ID.
 *
 * @return array<string,mixed>|null
 */
function getInterviewById(int $id): ?array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT i.*, u.full_name AS created_by_name
           FROM interviews i
           JOIN users u ON u.id = i.created_by
          WHERE i.id = :id
          LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Dashboard aggregate stats for interviews.
 *
 * @return array{total: int, active: int, draft: int, archived: int, completed: int}
 */
function getInterviewStats(): array
{
    $db   = getDB();
    $stmt = $db->query(
        "SELECT
            COUNT(*)                         AS total,
            SUM(status = 'active')           AS active,
            SUM(status = 'draft')            AS draft,
            SUM(status = 'archived')         AS archived,
            (SELECT COUNT(*) FROM interview_sessions WHERE status = 'completed') AS completed
         FROM interviews"
    );
    $row = $stmt->fetch();
    return [
        'total'     => (int) $row['total'],
        'active'    => (int) $row['active'],
        'draft'     => (int) $row['draft'],
        'archived'  => (int) $row['archived'],
        'completed' => (int) $row['completed'],
    ];
}

/**
 * Fetch N most recently created interviews for the activity feed.
 *
 * @return array[]
 */
function getRecentInterviews(int $limit = 5): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT i.id, i.title, i.status, i.difficulty, i.created_at, u.full_name AS created_by_name
           FROM interviews i
           JOIN users u ON u.id = i.created_by
          ORDER BY i.created_at DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Write ──────────────────────────────────────────────────────

/**
 * Create a new interview. Returns the new interview ID.
 */
function createInterview(
    string $title,
    string $description,
    int    $duration,
    string $difficulty,
    string $status,
    int    $createdBy
): int {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO interviews (title, description, duration, difficulty, status, created_by)
         VALUES (:title, :desc, :duration, :difficulty, :status, :created_by)'
    );
    $stmt->execute([
        ':title'      => $title,
        ':desc'       => $description,
        ':duration'   => $duration,
        ':difficulty' => $difficulty,
        ':status'     => $status,
        ':created_by' => $createdBy,
    ]);
    return (int) $db->lastInsertId();
}

/**
 * Update an existing interview. Returns true on success.
 */
function updateInterview(
    int    $id,
    string $title,
    string $description,
    int    $duration,
    string $difficulty,
    string $status
): bool {
    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE interviews
            SET title = :title, description = :desc, duration = :duration,
                difficulty = :difficulty, status = :status
          WHERE id = :id'
    );
    $stmt->execute([
        ':id'         => $id,
        ':title'      => $title,
        ':desc'       => $description,
        ':duration'   => $duration,
        ':difficulty' => $difficulty,
        ':status'     => $status,
    ]);
    return $stmt->rowCount() > 0;
}

/**
 * Change only the status of an interview.
 */
function setInterviewStatus(int $id, string $status): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE interviews SET status = :status WHERE id = :id'
    );
    $stmt->execute([':status' => $status, ':id' => $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Permanently delete an interview (cascades to sessions via FK).
 */
function deleteInterview(int $id): bool
{
    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM interviews WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
