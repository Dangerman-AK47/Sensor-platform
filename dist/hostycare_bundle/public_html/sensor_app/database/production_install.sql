-- =====================================================================
-- Sensor Platform / FileVault / DeviceVault v3
-- Production Database Installation Script for HostyCare cPanel MySQL
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `fv_storage_blobs`;
DROP TABLE IF EXISTS `fv_admin_audit_events`;
DROP TABLE IF EXISTS `fv_device_status_events`;
DROP TABLE IF EXISTS `fv_daily_sensor_version_check_metrics`;
DROP TABLE IF EXISTS `fv_sensor_version_check_events`;
DROP TABLE IF EXISTS `fv_sensor_config_download_events`;
DROP TABLE IF EXISTS `fv_uploaded_file_records`;
DROP TABLE IF EXISTS `fv_global_sensor_configurations`;
DROP TABLE IF EXISTS `fv_device_registration_requests`;
DROP TABLE IF EXISTS `fv_registered_devices`;
DROP TABLE IF EXISTS `fv_admin_accounts`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Admin Accounts
CREATE TABLE `fv_admin_accounts` (
    `id` VARCHAR(36) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('ADMIN', 'SUPER_ADMIN') NOT NULL DEFAULT 'ADMIN',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1b. Portal Users (User credentials for homepage unlock modal)
CREATE TABLE `fv_portal_users` (
    `id` VARCHAR(36) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'USER',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_portal_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Registered Devices
CREATE TABLE `fv_registered_devices` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `status` ENUM('ACTIVE', 'DEACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `token_hash` VARCHAR(255) NULL,
    `first_seen_at` DATETIME NULL,
    `last_seen_at` DATETIME NULL,
    `sensor_version` VARCHAR(50) NULL,
    `firmware_version` VARCHAR(50) NULL,
    `hardware_model` VARCHAR(100) NULL,
    `metadata` JSON NULL,
    `upload_count` INT NOT NULL DEFAULT 0,
    `total_storage_bytes` BIGINT NOT NULL DEFAULT 0,
    `last_upload_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_registered_device_id` (`device_id`),
    KEY `idx_devices_status` (`status`),
    KEY `idx_devices_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Device Registration Requests
CREATE TABLE `fv_device_registration_requests` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'AUTO_APPROVED') NOT NULL DEFAULT 'PENDING',
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at` DATETIME NULL,
    `review_reason` TEXT NULL,
    `reviewed_by_admin_id` VARCHAR(36) NULL,
    `requester_metadata` JSON NULL,
    `pending_device_key` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pending_device_key` (`pending_device_key`),
    KEY `idx_requests_device_id` (`device_id`),
    KEY `idx_requests_status` (`status`),
    CONSTRAINT `fk_requests_admin` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `fv_admin_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Global Sensor Configurations
CREATE TABLE `fv_global_sensor_configurations` (
    `id` VARCHAR(36) NOT NULL,
    `config_version` VARCHAR(50) NOT NULL,
    `status` ENUM('INACTIVE', 'ACTIVE', 'ARCHIVED') NOT NULL DEFAULT 'INACTIVE',
    `original_file_name` VARCHAR(255) NOT NULL,
    `storage_key` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL DEFAULT 'application/json',
    `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
    `checksum` VARCHAR(64) NULL,
    `uploaded_by_admin_id` VARCHAR(36) NULL,
    `activated_by_admin_id` VARCHAR(36) NULL,
    `activated_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_config_version` (`config_version`),
    KEY `idx_configs_status` (`status`),
    CONSTRAINT `fk_configs_uploader` FOREIGN KEY (`uploaded_by_admin_id`) REFERENCES `fv_admin_accounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_configs_activator` FOREIGN KEY (`activated_by_admin_id`) REFERENCES `fv_admin_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4b. Global Sensor Code Files
CREATE TABLE IF NOT EXISTS `fv_global_sensor_code_files` (
    `id` VARCHAR(36) NOT NULL,
    `code_version` VARCHAR(50) NOT NULL,
    `status` ENUM('INACTIVE', 'ACTIVE', 'ARCHIVED') NOT NULL DEFAULT 'INACTIVE',
    `original_file_name` VARCHAR(255) NOT NULL,
    `storage_key` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
    `checksum` VARCHAR(64) NULL,
    `uploaded_by_admin_id` VARCHAR(36) NULL,
    `activated_by_admin_id` VARCHAR(36) NULL,
    `activated_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code_version` (`code_version`),
    KEY `idx_code_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Uploaded File Records
CREATE TABLE `fv_uploaded_file_records` (
    `id` VARCHAR(36) NOT NULL,
    `upload_record_id` VARCHAR(64) NOT NULL,
    `sequence_number` BIGINT NOT NULL AUTO_INCREMENT,
    `registered_device_id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `storage_key` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_extension` VARCHAR(20) NOT NULL,
    `file_size_bytes` BIGINT NOT NULL,
    `checksum` VARCHAR(64) NULL,
    `upload_status` ENUM('PENDING', 'STORED', 'FAILED') NOT NULL DEFAULT 'STORED',
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sequence_number` (`sequence_number`),
    UNIQUE KEY `uk_upload_record_id` (`upload_record_id`),
    KEY `idx_uploads_device_fk` (`registered_device_id`),
    KEY `idx_uploads_device_id` (`device_id`),
    KEY `idx_uploads_uploaded_at` (`uploaded_at`),
    KEY `idx_uploads_status` (`upload_status`),
    CONSTRAINT `fk_uploads_device` FOREIGN KEY (`registered_device_id`) REFERENCES `fv_registered_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Sensor Config Download Events
CREATE TABLE `fv_sensor_config_download_events` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `config_id` VARCHAR(36) NOT NULL,
    `downloaded_version` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cfg_dl_device` (`device_id`),
    KEY `idx_cfg_dl_config` (`config_id`),
    CONSTRAINT `fk_cfg_dl_config` FOREIGN KEY (`config_id`) REFERENCES `fv_global_sensor_configurations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Sensor Version Check Events
CREATE TABLE `fv_sensor_version_check_events` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `reported_version` VARCHAR(50) NOT NULL,
    `result` ENUM('CURRENT', 'UPDATE_AVAILABLE', 'NO_CONFIGURATION_AVAILABLE', 'SENSOR_DEACTIVATED') NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ver_chk_device` (`device_id`),
    KEY `idx_ver_chk_checked_at` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Daily Sensor Version Check Metrics
CREATE TABLE `fv_daily_sensor_version_check_metrics` (
    `id` VARCHAR(36) NOT NULL,
    `metric_date` DATE NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `result` ENUM('CURRENT', 'UPDATE_AVAILABLE', 'NO_CONFIGURATION_AVAILABLE', 'SENSOR_DEACTIVATED') NOT NULL,
    `total_checks` INT NOT NULL DEFAULT 1,
    `last_checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_daily_metric` (`metric_date`, `device_id`, `result`),
    KEY `idx_metric_date` (`metric_date`),
    KEY `idx_metric_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Device Status Events
CREATE TABLE `fv_device_status_events` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `previous_status` ENUM('PENDING', 'ACTIVE', 'DEACTIVE', 'AUTO_APPROVED') NOT NULL,
    `new_status` ENUM('PENDING', 'ACTIVE', 'DEACTIVE', 'AUTO_APPROVED') NOT NULL,
    `changed_by_admin_id` VARCHAR(36) NULL,
    `reason` TEXT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_events_device` (`device_id`),
    KEY `idx_status_events_changed_at` (`changed_at`),
    CONSTRAINT `fk_status_events_admin` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `fv_admin_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Admin Audit Events
CREATE TABLE `fv_admin_audit_events` (
    `id` VARCHAR(36) NOT NULL,
    `admin_id` VARCHAR(36) NULL,
    `username` VARCHAR(100) NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(100) NOT NULL,
    `entity_id` VARCHAR(100) NULL,
    `metadata` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_admin` (`admin_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_created_at` (`created_at`),
    CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `fv_admin_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Storage Blobs (Optional Database Blob Storage Fallback)
CREATE TABLE `fv_storage_blobs` (
    `id` VARCHAR(36) NOT NULL,
    `blob_key` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    `byte_size` BIGINT NOT NULL,
    `blob_data` LONGBLOB NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_blob_key` (`blob_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- INITIAL PRODUCTION SEED
-- =====================================================================

-- Default Super Admin (Username: admin, Password: password)
INSERT INTO `fv_admin_accounts` 
(`id`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`, `last_login_at`)
VALUES 
(
    'admin-root-0001',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'SUPER_ADMIN',
    1,
    NOW(),
    NOW(),
    NULL
) ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Default Portal User (Username: user, Password: password)
INSERT INTO `fv_portal_users`
(`id`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`, `last_login_at`)
VALUES
(
    'user-portal-0001',
    'user',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'USER',
    1,
    NOW(),
    NOW(),
    NULL
) ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Baseline Active Configuration v1.1.0
INSERT INTO `fv_global_sensor_configurations`
(`id`, `config_version`, `status`, `original_file_name`, `storage_key`, `mime_type`, `file_size_bytes`, `checksum`, `uploaded_by_admin_id`, `activated_by_admin_id`, `activated_at`, `created_at`)
VALUES
(
    'cfg-prod-001',
    '1.1.0',
    'ACTIVE',
    'sensor_config_v1.1.0.json',
    'configs/2026/10/sensor_config_v1.1.0.json',
    'application/json',
    182,
    '486ea46224d1bb4fb680f34f7c9ad96a8f24ec88be73ea8e5a6c65260e9cb8a7',
    'admin-root-0001',
    'admin-root-0001',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE `status` = 'ACTIVE';
