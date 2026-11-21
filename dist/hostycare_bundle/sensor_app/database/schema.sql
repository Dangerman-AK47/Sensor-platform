-- =====================================================================
-- Sensor Platform / FileVault / DeviceVault v3 - MySQL/MariaDB Schema
-- HostyCare cPanel Compatible (MySQL 5.7+ / 8.0+ / MariaDB 10.3+)
-- All tables prefixed with fv_
-- =====================================================================

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

-- 6. Sensor Config Download Events (Immutable Audit)
CREATE TABLE `fv_sensor_config_download_events` (
    `id` VARCHAR(36) NOT NULL,
    `config_id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `config_version` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_download_events_device` (`device_id`),
    KEY `idx_download_events_config` (`config_id`),
    KEY `idx_download_events_time` (`downloaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Sensor Version Check Events
CREATE TABLE `fv_sensor_version_check_events` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `reported_version` VARCHAR(50) NULL,
    `active_version` VARCHAR(50) NULL,
    `result` ENUM(
        'CURRENT',
        'UPDATE_AVAILABLE',
        'SENSOR_DEACTIVATED',
        'NO_CONFIGURATION_AVAILABLE',
        'INVALID_VERSION',
        'SENSOR_NOT_REGISTERED'
    ) NOT NULL,
    `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_version_checks_device` (`device_id`),
    KEY `idx_version_checks_time` (`checked_at`),
    KEY `idx_version_checks_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Daily Sensor Version Check Metrics
CREATE TABLE `fv_daily_sensor_version_check_metrics` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `metric_date` DATE NOT NULL,
    `result` VARCHAR(50) NOT NULL,
    `check_count` INT NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_daily_metric` (`device_id`, `metric_date`, `result`),
    KEY `idx_metrics_date` (`metric_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Device Status Transition Events
CREATE TABLE `fv_device_status_events` (
    `id` VARCHAR(36) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `previous_status` ENUM('ACTIVE', 'DEACTIVE') NOT NULL,
    `new_status` ENUM('ACTIVE', 'DEACTIVE') NOT NULL,
    `changed_by_admin_id` VARCHAR(36) NULL,
    `reason` TEXT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_events_device` (`device_id`),
    KEY `idx_status_events_time` (`changed_at`),
    CONSTRAINT `fk_status_events_admin` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `fv_admin_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Admin Audit Events
CREATE TABLE `fv_admin_audit_events` (
    `id` VARCHAR(36) NOT NULL,
    `admin_id` VARCHAR(36) NULL,
    `username` VARCHAR(100) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(100) NOT NULL,
    `entity_id` VARCHAR(100) NULL,
    `metadata` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_created_at` (`created_at`),
    KEY `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Storage Blobs (Binary Fallback Storage)
CREATE TABLE `fv_storage_blobs` (
    `storage_key` VARCHAR(500) NOT NULL,
    `data` LONGBLOB NOT NULL,
    `byte_size` BIGINT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`storage_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
