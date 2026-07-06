-- =============================================================
-- AI Interview Assessment Platform
-- Migration: Phase 4 — Secure AI Interview Session Engine
-- Run AFTER migration_phase3.sql
-- Safe to re-run (uses IF NOT EXISTS / DROP IF EXISTS guards)
-- =============================================================

USE `ai_interview_platform`;

-- ─────────────────────────────────────────────────────────────
-- STEP 1 : Drop draft Phase-3 "Future Prep" tables
--          (re-created below with the proper Phase-4 schema)
-- ─────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `ai_evaluations`;
DROP TABLE IF EXISTS `transcripts`;
DROP TABLE IF EXISTS `answers`;
DROP TABLE IF EXISTS `attempts`;

-- ─────────────────────────────────────────────────────────────
-- STEP 2 : Interview Attempts
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `candidate_id` INT UNSIGNED NOT NULL,
  `interview_id` INT UNSIGNED NOT NULL,
  `start_time`   DATETIME     NULL     DEFAULT NULL,
  `end_time`     DATETIME     NULL     DEFAULT NULL,
  `status`       ENUM('not_started','in_progress','completed','expired')
                              NOT NULL DEFAULT 'not_started',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_att_candidate`  (`candidate_id`),
  INDEX `idx_att_interview`  (`interview_id`),
  INDEX `idx_att_status`     (`status`),
  CONSTRAINT `fk_att_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_att_interview`
    FOREIGN KEY (`interview_id`) REFERENCES `interviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 3 : Test Attempts
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `test_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `candidate_id` INT UNSIGNED NOT NULL,
  `test_id`      INT UNSIGNED NOT NULL,
  `start_time`   DATETIME     NULL     DEFAULT NULL,
  `end_time`     DATETIME     NULL     DEFAULT NULL,
  `status`       ENUM('not_started','in_progress','completed','expired')
                              NOT NULL DEFAULT 'not_started',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ta_candidate` (`candidate_id`),
  INDEX `idx_ta_test`      (`test_id`),
  CONSTRAINT `fk_ta_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_ta_test`
    FOREIGN KEY (`test_id`)      REFERENCES `tests`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 4 : Answers  (interview audio answers)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `answers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`    INT UNSIGNED NOT NULL,
  `question_id`   INT UNSIGNED NOT NULL,
  `audio_path`    VARCHAR(255) NULL     DEFAULT NULL
                  COMMENT 'Relative path inside uploads/audio/',
  `response_time` SMALLINT UNSIGNED NULL DEFAULT NULL
                  COMMENT 'Seconds the candidate spent on this question',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ans_attempt_question` (`attempt_id`, `question_id`),
  INDEX `idx_ans_attempt`  (`attempt_id`),
  INDEX `idx_ans_question` (`question_id`),
  CONSTRAINT `fk_ans_attempt`
    FOREIGN KEY (`attempt_id`)  REFERENCES `attempts`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_ans_question`
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 5 : Camera Presence Logs
--           Only stores monitoring stats — NO video saved.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `presence_logs` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`    INT UNSIGNED NOT NULL,
  `timestamp`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `face_detected` TINYINT(1)   NOT NULL DEFAULT 0
                  COMMENT '1 = face present, 0 = face missing',
  PRIMARY KEY (`id`),
  INDEX `idx_pl_attempt` (`attempt_id`),
  CONSTRAINT `fk_pl_attempt`
    FOREIGN KEY (`attempt_id`) REFERENCES `attempts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 6 : Tab Switch Logs
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tab_switch_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`  INT UNSIGNED NOT NULL,
  `timestamp`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `event_type`  ENUM('TAB_HIDDEN','TAB_VISIBLE') NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_tsl_attempt` (`attempt_id`),
  CONSTRAINT `fk_tsl_attempt`
    FOREIGN KEY (`attempt_id`) REFERENCES `attempts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 7 : Fullscreen Logs
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fullscreen_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`  INT UNSIGNED NOT NULL,
  `timestamp`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `event_type`  ENUM('FULLSCREEN_EXIT','FULLSCREEN_ENTER') NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_fsl_attempt` (`attempt_id`),
  CONSTRAINT `fk_fsl_attempt`
    FOREIGN KEY (`attempt_id`) REFERENCES `attempts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 8 : Integrity Events  (warning/flag log)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `integrity_events` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`  INT UNSIGNED NOT NULL,
  `event_type`  VARCHAR(50)  NOT NULL
                COMMENT 'e.g. TAB_SWITCH, FULLSCREEN_EXIT, CAMERA_DISABLED',
  `event_time`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `severity`    ENUM('warning','flag') NOT NULL DEFAULT 'warning',
  PRIMARY KEY (`id`),
  INDEX `idx_ie_attempt` (`attempt_id`),
  CONSTRAINT `fk_ie_attempt`
    FOREIGN KEY (`attempt_id`) REFERENCES `attempts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- STEP 9 : AI Preparation Tables (schema only — no API yet)
-- ─────────────────────────────────────────────────────────────

-- 9a. Transcripts  (populated by Groq Whisper in Phase 5)
CREATE TABLE IF NOT EXISTS `transcripts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `answer_id`       INT UNSIGNED NOT NULL,
  `transcript_text` LONGTEXT     NOT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tr_answer`
    FOREIGN KEY (`answer_id`) REFERENCES `answers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9b. AI Evaluations  (populated by Gemini 2.5 Flash in Phase 6)
CREATE TABLE IF NOT EXISTS `ai_evaluations` (
  `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `answer_id`           INT UNSIGNED   NOT NULL,
  `overall_score`       DECIMAL(5,2)   NULL DEFAULT NULL,
  `technical_score`     DECIMAL(5,2)   NULL DEFAULT NULL,
  `communication_score` DECIMAL(5,2)   NULL DEFAULT NULL,
  `confidence_score`    DECIMAL(5,2)   NULL DEFAULT NULL,
  `strengths`           TEXT           NULL DEFAULT NULL,
  `weaknesses`          TEXT           NULL DEFAULT NULL,
  `summary`             TEXT           NULL DEFAULT NULL,
  `created_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_aie_answer`
    FOREIGN KEY (`answer_id`) REFERENCES `answers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
