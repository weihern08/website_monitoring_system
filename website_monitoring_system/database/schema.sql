-- Website Monitoring System (UptimeGuard)
-- Import this file in phpMyAdmin if you skip the web installer.

CREATE DATABASE IF NOT EXISTS `website_monitoring`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `website_monitoring`;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `websites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `interval_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
  `timeout_seconds` INT UNSIGNED NOT NULL DEFAULT 10,
  `slow_threshold_ms` INT UNSIGNED NOT NULL DEFAULT 3000,
  `status` ENUM('up','down','unknown') NOT NULL DEFAULT 'unknown',
  `is_slow` TINYINT(1) NOT NULL DEFAULT 0,
  `is_paused` TINYINT(1) NOT NULL DEFAULT 0,
  `response_time` INT UNSIGNED DEFAULT NULL,
  `http_code` INT DEFAULT NULL,
  `last_error` VARCHAR(255) DEFAULT NULL,
  `last_checked` DATETIME DEFAULT NULL,
  `status_since` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_checked` (`last_checked`),
  KEY `idx_paused` (`is_paused`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `website_id` INT UNSIGNED NOT NULL,
  `status` ENUM('up','down') NOT NULL,
  `is_slow` TINYINT(1) NOT NULL DEFAULT 0,
  `response_time` INT UNSIGNED DEFAULT NULL,
  `http_code` INT DEFAULT NULL,
  `error_message` VARCHAR(255) DEFAULT NULL,
  `checked_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_website_checked` (`website_id`, `checked_at`),
  KEY `idx_status` (`status`),
  KEY `idx_checked_at` (`checked_at`),
  CONSTRAINT `fk_logs_website`
    FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alerts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `website_id` INT UNSIGNED NOT NULL,
  `alert_type` ENUM('down','recovery','slow') NOT NULL,
  `status` ENUM('up','down') NOT NULL,
  `response_time` INT UNSIGNED DEFAULT NULL,
  `message` TEXT NOT NULL,
  `sent` TINYINT(1) NOT NULL DEFAULT 0,
  `sent_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_website_sent` (`website_id`, `sent_at`),
  KEY `idx_type` (`alert_type`),
  CONSTRAINT `fk_alerts_website`
    FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('telegram_bot_token', ''),
  ('telegram_chat_id', ''),
  ('telegram_enabled', '0'),
  ('slow_threshold_ms', '3000'),
  ('request_timeout', '10'),
  ('cron_secret', ''),
  ('log_retention_days', '90'),
  ('status_page_title', 'UptimeGuard Status')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
