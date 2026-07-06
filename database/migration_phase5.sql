-- =============================================================
-- AI Interview Assessment Platform
-- Migration: Phase 5 — AI Interview Evaluation Engine
-- Run AFTER migration_phase4.sql
-- Safe to re-run (uses IF NOT EXISTS / column-exists guards)
-- =============================================================

USE `ai_interview_platform`;

-- ─────────────────────────────────────────────────────────────
-- STEP 1 : Extend transcripts table
--          Add language column (populated by Groq Whisper)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `transcripts`
  ADD COLUMN IF NOT EXISTS `language` VARCHAR(10) NULL DEFAULT NULL
    COMMENT 'ISO 639-1 language code detected by Whisper'
  AFTER `transcript_text`;

-- ─────────────────────────────────────────────────────────────
-- STEP 2 : Extend ai_evaluations table
--          Add model_used and processing_time columns
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `ai_evaluations`
  ADD COLUMN IF NOT EXISTS `model_used`       VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Gemini model used for evaluation'
  AFTER `summary`,
  ADD COLUMN IF NOT EXISTS `processing_time`  SMALLINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'API call duration in milliseconds'
  AFTER `model_used`;

-- ─────────────────────────────────────────────────────────────
-- STEP 3 : evaluation_jobs  (queue/status tracking)
--          Tracks per-answer evaluation jobs for admin dashboard
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `evaluation_jobs` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `answer_id`   INT UNSIGNED  NOT NULL,
  `status`      ENUM('pending','transcribing','evaluating','completed','failed')
                              NOT NULL DEFAULT 'pending',
  `error_msg`   TEXT          NULL DEFAULT NULL,
  `started_at`  DATETIME      NULL DEFAULT NULL,
  `completed_at` DATETIME     NULL DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ej_answer` (`answer_id`),
  INDEX `idx_ej_status`  (`status`),
  CONSTRAINT `fk_ej_answer`
    FOREIGN KEY (`answer_id`) REFERENCES `answers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 4 : Add unique constraint to transcripts.answer_id
--          (one transcript per answer)
-- ─────────────────────────────────────────────────────────────
SET @constraint_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'transcripts'
    AND CONSTRAINT_NAME = 'uq_tr_answer'
);

SET @sql = IF(@constraint_exists = 0,
  'ALTER TABLE `transcripts` ADD UNIQUE KEY `uq_tr_answer` (`answer_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────
-- STEP 5 : Add unique constraint to ai_evaluations.answer_id
--          (one evaluation record per answer)
-- ─────────────────────────────────────────────────────────────
SET @ae_constraint_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'ai_evaluations'
    AND CONSTRAINT_NAME = 'uq_aie_answer'
);

SET @ae_sql = IF(@ae_constraint_exists = 0,
  'ALTER TABLE `ai_evaluations` ADD UNIQUE KEY `uq_aie_answer` (`answer_id`)',
  'SELECT 1'
);
PREPARE ae_stmt FROM @ae_sql;
EXECUTE ae_stmt;
DEALLOCATE PREPARE ae_stmt;
