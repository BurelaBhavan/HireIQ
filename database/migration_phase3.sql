-- =============================================================
-- AI Interview Assessment Platform
-- Migration: Phase 3 — Question Bank, Tests, Notifications, Documents
-- =============================================================

USE `ai_interview_platform`;

-- ── 1. Question Bank ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `questions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_text`   TEXT NOT NULL,
  `expected_topics` TEXT NULL COMMENT 'Keywords for AI evaluation',
  `difficulty`      ENUM('Easy', 'Medium', 'Hard') NOT NULL DEFAULT 'Medium',
  `category`        VARCHAR(100) NOT NULL DEFAULT 'General',
  `created_by`      INT UNSIGNED NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_q_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Interview Builder ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `interview_questions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id`    INT UNSIGNED NOT NULL,
  `question_id`     INT UNSIGNED NOT NULL,
  `difficulty`      ENUM('Easy', 'Medium', 'Hard') NOT NULL DEFAULT 'Medium',
  `sequence_order`  INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_iq_interview` FOREIGN KEY (`interview_id`) REFERENCES `interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_iq_question` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Test Management ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `duration`    SMALLINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Duration in minutes',
  `status`      ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tests_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_questions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`       INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `difficulty`    ENUM('Easy', 'Medium', 'Hard') NOT NULL DEFAULT 'Medium',
  `category`      VARCHAR(100) NULL,
  `marks`         DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tq_test` FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Invitations ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `interview_invitations` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id`    INT UNSIGNED NOT NULL,
  `candidate_id`    INT UNSIGNED NOT NULL,
  `invited_by`      INT UNSIGNED NOT NULL,
  `status`          ENUM('Pending', 'Accepted', 'Declined', 'Expired') NOT NULL DEFAULT 'Pending',
  `invitation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_date`   DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ii_interview` FOREIGN KEY (`interview_id`) REFERENCES `interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ii_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ii_invited_by` FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_invitations` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`         INT UNSIGNED NOT NULL,
  `candidate_id`    INT UNSIGNED NOT NULL,
  `invited_by`      INT UNSIGNED NOT NULL,
  `status`          ENUM('Pending', 'Accepted', 'Declined', 'Expired') NOT NULL DEFAULT 'Pending',
  `invitation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_date`   DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ti_test` FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ti_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ti_invited_by` FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Notifications ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `message`     TEXT NOT NULL,
  `type`        ENUM('Interview', 'Test', 'Assessment', 'Document', 'Announcement', 'Reminder') NOT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Documents ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `file_path`   VARCHAR(255) NOT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_doc_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_reads` (
  `document_id`   INT UNSIGNED NOT NULL,
  `candidate_id`  INT UNSIGNED NOT NULL,
  `read_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`, `candidate_id`),
  CONSTRAINT `fk_dr_doc` FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Future Prep (Schemas only) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `attempts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`  INT UNSIGNED NOT NULL,
  `test_id`     INT UNSIGNED NULL,
  `started_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `score`       DECIMAL(5,2) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `answers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`    INT UNSIGNED NOT NULL,
  `question_id`   INT UNSIGNED NULL,
  `test_question_id` INT UNSIGNED NULL,
  `answer_text`   TEXT NULL,
  `video_url`     VARCHAR(255) NULL,
  `audio_url`     VARCHAR(255) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ans_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transcripts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `answer_id`   INT UNSIGNED NOT NULL,
  `transcript`  TEXT NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_trans_answer` FOREIGN KEY (`answer_id`) REFERENCES `answers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_evaluations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `answer_id`   INT UNSIGNED NOT NULL,
  `score`       DECIMAL(5,2) NOT NULL,
  `feedback`    TEXT NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ai_answer` FOREIGN KEY (`answer_id`) REFERENCES `answers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
