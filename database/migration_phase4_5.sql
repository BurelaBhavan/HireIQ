-- =============================================================
-- AI Interview Assessment Platform
-- Migration: Phase 4.5 — Enterprise Secure Document Viewer
-- Run this after migration_phase3.sql
-- =============================================================

USE `ai_interview_platform`;

-- ── 1. Extend documents table ──────────────────────────────────
ALTER TABLE `documents`
  ADD COLUMN IF NOT EXISTS `file_size`      BIGINT UNSIGNED    NULL     COMMENT 'File size in bytes'         AFTER `file_path`,
  ADD COLUMN IF NOT EXISTS `file_type`      VARCHAR(20)        NULL     COMMENT 'pdf | docx | pptx | etc'    AFTER `file_size`,
  ADD COLUMN IF NOT EXISTS `is_restricted`  TINYINT(1)         NOT NULL DEFAULT 1 COMMENT '1=secure viewer only' AFTER `file_type`,
  ADD COLUMN IF NOT EXISTS `original_name`  VARCHAR(255)       NULL     COMMENT 'Original filename'           AFTER `is_restricted`;

-- ── 2. Document view tokens ────────────────────────────────────
-- Short-lived HMAC tokens generated per view session.
-- A token is consumed (single-use for streaming requests).
CREATE TABLE IF NOT EXISTS `document_view_tokens` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `token`       VARCHAR(128)   NOT NULL UNIQUE,
  `document_id` INT UNSIGNED   NOT NULL,
  `user_id`     INT UNSIGNED   NOT NULL,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME       NOT NULL,
  `used`        TINYINT(1)     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_dvt_token`      (`token`),
  INDEX `idx_dvt_expires`    (`expires_at`),
  INDEX `idx_dvt_user_doc`   (`user_id`, `document_id`),
  CONSTRAINT `fk_dvt_document` FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dvt_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Document activity logs ──────────────────────────────────
-- One row per viewing session (open → close).
CREATE TABLE IF NOT EXISTS `document_activity_logs` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`           INT UNSIGNED NOT NULL,
  `user_id`               INT UNSIGNED NOT NULL,
  `session_token`         VARCHAR(128) NULL      COMMENT 'Links to document_view_tokens',
  `view_start`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `view_end`              DATETIME     NULL,
  `duration_seconds`      INT UNSIGNED NULL,
  -- Violation counters (updated via AJAX heartbeats)
  `tab_switch_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `fullscreen_exit_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `copy_attempt_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `print_attempt_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `right_click_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `devtools_detected`     TINYINT(1)        NOT NULL DEFAULT 0,
  `screenshot_suspicion`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- Client info
  `ip_address`            VARCHAR(45)  NULL,
  `user_agent`            TEXT         NULL,
  `device_type`           VARCHAR(50)  NULL,
  `browser`               VARCHAR(100) NULL,
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_dal_document`   (`document_id`),
  INDEX `idx_dal_user`       (`user_id`),
  INDEX `idx_dal_view_start` (`view_start`),
  CONSTRAINT `fk_dal_document` FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dal_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Document violation events ──────────────────────────────
-- Granular per-event log for every security violation.
CREATE TABLE IF NOT EXISTS `document_violations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_id`      INT UNSIGNED NOT NULL COMMENT 'FK to document_activity_logs',
  `document_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `event_type`  ENUM(
    'right_click','copy_attempt','print_attempt','keyboard_shortcut',
    'tab_switch','fullscreen_exit','devtools_open','screenshot_suspicion',
    'screen_share_detected','drag_attempt','selection_attempt',
    'session_expired','heartbeat_failed'
  ) NOT NULL,
  `event_detail` VARCHAR(255) NULL COMMENT 'e.g. which key combination',
  `ip_address`   VARCHAR(45)  NULL,
  `occurred_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_dv_log`      (`log_id`),
  INDEX `idx_dv_document` (`document_id`),
  INDEX `idx_dv_user`     (`user_id`),
  INDEX `idx_dv_type`     (`event_type`),
  CONSTRAINT `fk_dv_log`      FOREIGN KEY (`log_id`)      REFERENCES `document_activity_logs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dv_document` FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`)              ON DELETE CASCADE,
  CONSTRAINT `fk_dv_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)                  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Cleanup: remove expired tokens (event scheduler) ───────
-- Optional: enable MySQL event scheduler to auto-clean tokens
-- SET GLOBAL event_scheduler = ON;
-- CREATE EVENT IF NOT EXISTS `clean_expired_tokens`
--   ON SCHEDULE EVERY 1 HOUR
--   DO DELETE FROM `document_view_tokens` WHERE `expires_at` < NOW();
