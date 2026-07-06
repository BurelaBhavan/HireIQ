<?php
/**
 * Candidate Data Access
 * AI Interview Assessment Platform — Phase 2
 *
 * All functions use the getDB() PDO singleton.
 * Never interpolate user input — always use prepared statements.
 */

declare(strict_types=1);

// ── Read ───────────────────────────────────────────────────────

/**
 * Return paginated candidate list with optional search + status filter.
 *
 * @return array{rows: array[], total: int}
 */
function getAllCandidates(
    string $search   = '',
    string $status   = '',
    int    $page     = 1,
    int    $perPage  = 20
): array {
    $db     = getDB();
    $offset = ($page - 1) * $perPage;
    $like   = $search !== '' ? '%' . $search . '%' : null;

    // Build WHERE clause fragments.
    // PDO with emulate_prepares=false does NOT allow the same named
    // parameter twice in one statement, so we use :sn and :se for
    // the name vs email search separately.
    $where = ["u.role = 'candidate'"];
    if ($like !== null) {
        $where[] = '(u.full_name LIKE :sn OR u.email LIKE :se)';
    }
    if ($status === 'active') {
        $where[] = 'u.is_active = 1';
    } elseif ($status === 'inactive') {
        $where[] = 'u.is_active = 0';
    }
    $sqlWhere = implode(' AND ', $where);

    // ── Count ─────────────────────────────────────────────────
    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $sqlWhere");
    if ($like !== null) {
        $countStmt->bindValue(':sn', $like);
        $countStmt->bindValue(':se', $like);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    // ── Rows ──────────────────────────────────────────────────
    $rowStmt = $db->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            u.is_active,
            u.created_at,
            u.last_login_at,
            COUNT(s.id) AS interview_count,
            AVG(s.score) AS avg_score
         FROM users u
         LEFT JOIN interview_sessions s
               ON s.candidate_id = u.id AND s.status = 'completed'
         WHERE $sqlWhere
         GROUP BY u.id
         ORDER BY u.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    if ($like !== null) {
        $rowStmt->bindValue(':sn', $like);
        $rowStmt->bindValue(':se', $like);
    }
    $rowStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $rowStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $rowStmt->execute();
    $rows = $rowStmt->fetchAll();

    return ['rows' => $rows, 'total' => $total];
}

/**
 * Fetch a single candidate by ID (candidates only).
 *
 * @return array<string,mixed>|null
 */
function getCandidateById(int $id): ?array
{
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT
            u.id, u.full_name, u.email, u.is_active, u.created_at, u.last_login_at,
            COUNT(s.id)  AS interview_count,
            AVG(s.score) AS avg_score
         FROM users u
         LEFT JOIN interview_sessions s
               ON s.candidate_id = u.id AND s.status = 'completed'
         WHERE u.id = :id AND u.role = 'candidate'
         GROUP BY u.id
         LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Dashboard aggregate stats for candidates.
 *
 * @return array{total: int, active: int, inactive: int, interviewed: int}
 */
function getCandidateStats(): array
{
    $db = getDB();

    $stmt = $db->query(
        "SELECT
            COUNT(*)                                AS total,
            SUM(is_active = 1)                      AS active,
            SUM(is_active = 0)                      AS inactive,
            COUNT(DISTINCT s.candidate_id)          AS interviewed
         FROM users u
         LEFT JOIN interview_sessions s
               ON s.candidate_id = u.id AND s.status = 'completed'
         WHERE u.role = 'candidate'"
    );

    $row = $stmt->fetch();
    return [
        'total'       => (int) $row['total'],
        'active'      => (int) $row['active'],
        'inactive'    => (int) $row['inactive'],
        'interviewed' => (int) $row['interviewed'],
    ];
}

// ── Write ──────────────────────────────────────────────────────

/**
 * Toggle a candidate's is_active flag.
 * Returns the new status (1 = active, 0 = inactive).
 */
function toggleCandidateStatus(int $id): int
{
    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE users
            SET is_active = 1 - is_active
          WHERE id = :id AND role = "candidate"'
    );
    $stmt->execute([':id' => $id]);

    $chk = $db->prepare('SELECT is_active FROM users WHERE id = :id');
    $chk->execute([':id' => $id]);
    return (int) $chk->fetchColumn();
}

/**
 * Permanently delete a candidate account and all related sessions.
 */
function deleteCandidate(int $id): bool
{
    $db   = getDB();
    $stmt = $db->prepare(
        'DELETE FROM users WHERE id = :id AND role = "candidate"'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Update last_login_at for the given user.
 */
function updateLastLogin(int $userId): void
{
    $db   = getDB();
    $stmt = $db->prepare(
        'UPDATE users SET last_login_at = NOW() WHERE id = :id'
    );
    $stmt->execute([':id' => $userId]);
}

/**
 * Fetch the N most recently registered candidates for the activity feed.
 *
 * @return array[]
 */
function getRecentCandidates(int $limit = 5): array
{
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id, full_name, email, created_at
           FROM users
          WHERE role = "candidate"
          ORDER BY created_at DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
