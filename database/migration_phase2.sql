-- =============================================================
-- AI Interview Assessment Platform
-- Migration: Phase 2 — Candidate + Interview Management
-- Run this against the existing ai_interview_platform database.
-- Safe to re-run (uses IF NOT EXISTS / IF EXISTS guards).
-- =============================================================

USE `ai_interview_platform`;

-- ── 1. Add last_login_at to users ────────────────────────────
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL DEFAULT NULL AFTER `is_active`;

-- ── 2. Create interviews table ────────────────────────────────
CREATE TABLE IF NOT EXISTS `interviews` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255)  NOT NULL,
  `description` TEXT          NULL,
  `duration`    SMALLINT UNSIGNED NOT NULL DEFAULT 30  COMMENT 'Duration in minutes',
  `difficulty`  ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'medium',
  `status`      ENUM('draft','active','archived')      NOT NULL DEFAULT 'draft',
  `created_by`  INT UNSIGNED  NOT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_iv_status`     (`status`),
  INDEX `idx_iv_created_by` (`created_by`),
  CONSTRAINT `fk_iv_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Create interview_sessions table (Phase 3 prep) ─────────
CREATE TABLE IF NOT EXISTS `interview_sessions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id`  INT UNSIGNED NOT NULL,
  `candidate_id`  INT UNSIGNED NOT NULL,
  `status`        ENUM('pending','in_progress','completed','abandoned') NOT NULL DEFAULT 'pending',
  `score`         DECIMAL(5,2) NULL DEFAULT NULL  COMMENT 'AI-computed score 0-100',
  `started_at`    DATETIME     NULL DEFAULT NULL,
  `completed_at`  DATETIME     NULL DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_is_interview`  (`interview_id`),
  INDEX `idx_is_candidate`  (`candidate_id`),
  CONSTRAINT `fk_is_interview`
    FOREIGN KEY (`interview_id`) REFERENCES `interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_is_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
