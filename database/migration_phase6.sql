-- =============================================================
-- AI Interview Assessment Platform
-- Migration: Phase 6 — Interview-Specific Questions & Result Publication
-- =============================================================

USE `ai_interview_platform`;

-- 1. Add question_source and interview_id_ref to questions table
ALTER TABLE `questions`
  ADD COLUMN IF NOT EXISTS `question_source` ENUM('bank', 'interview') NOT NULL DEFAULT 'bank' AFTER `category`,
  ADD COLUMN IF NOT EXISTS `interview_id_ref` INT UNSIGNED NULL DEFAULT NULL AFTER `question_source`;

-- Add foreign key constraint if it doesn't exist
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'questions'
    AND CONSTRAINT_NAME = 'fk_q_interview_ref'
);

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `questions` ADD CONSTRAINT `fk_q_interview_ref` FOREIGN KEY (`interview_id_ref`) REFERENCES `interviews` (`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Create interview_results table
CREATE TABLE IF NOT EXISTS `interview_results` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`   INT UNSIGNED NOT NULL,
  `candidate_id` INT UNSIGNED NOT NULL,
  `interview_id` INT UNSIGNED NOT NULL,
  `decision`     ENUM('selected','rejected','pending') NOT NULL DEFAULT 'pending',
  `conclusion`   TEXT NULL,
  `published_at` DATETIME NULL DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ir_attempt` (`attempt_id`),
  CONSTRAINT `fk_ir_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ir_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ir_interview` FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
