-- =============================================================
-- AI Interview Assessment Platform
-- Database Schema - Phase 1: Authentication System
-- =============================================================

CREATE DATABASE IF NOT EXISTS `ai_interview_platform`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `ai_interview_platform`;

-- -------------------------------------------------------------
-- Table: users
-- Stores all platform users (admins + candidates)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(150)      NOT NULL,
  `email`         VARCHAR(255)      NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)      NOT NULL,
  `role`          ENUM('super_admin','candidate') NOT NULL DEFAULT 'candidate',
  `is_active`     TINYINT(1)        NOT NULL DEFAULT 1,
  `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: password_resets
-- Token-based password reset workflow
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255) NOT NULL,
  `token`      VARCHAR(64)  NOT NULL UNIQUE,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pr_email` (`email`),
  INDEX `idx_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Seed: Default Super Admin
-- Password: password  (bcrypt cost-12 hash)
-- IMPORTANT: Change this password after first login
-- -------------------------------------------------------------
INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`) VALUES
(
  'Super Administrator',
  'admin@aiplatform.com',
  '$2y$12$OZXKAjAJsnqOGJSPvAOQN.m8uh2oC904uemhl2ukFGp5WGrVdTD.C',  -- password: password (replace in prod)
  'super_admin'
);
