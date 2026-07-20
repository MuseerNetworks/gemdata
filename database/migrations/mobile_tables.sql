-- ============================================================
-- GemData Mobile App — Required Database Migration
-- Run this in phpMyAdmin on your cPanel production database.
-- FOREIGN KEY constraints removed for broad compatibility.
-- ============================================================

-- Table: mobile_device_tokens
-- Stores bearer tokens for persistent mobile app login sessions (30-day auto-login)
CREATE TABLE IF NOT EXISTS `mobile_device_tokens` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `device_id`  VARCHAR(255)  NOT NULL,
  `token_hash` VARCHAR(64)   NOT NULL,
  `expires_at` DATETIME      NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash`  (`token_hash`),
  UNIQUE KEY `uk_user_device` (`user_id`, `device_id`),
  KEY         `idx_user_id`   (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mobile app bearer token sessions — 30-day persistent login';

-- Table: mobile_push_tokens
-- Stores Firebase / APNs push notification tokens per device
CREATE TABLE IF NOT EXISTS `mobile_push_tokens` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `device_id`  VARCHAR(255)  NOT NULL,
  `push_token` TEXT          NOT NULL,
  `platform`   VARCHAR(50)   NOT NULL COMMENT 'android or ios',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_device` (`user_id`, `device_id`),
  KEY         `idx_user_id`   (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Push notification device registrations';
