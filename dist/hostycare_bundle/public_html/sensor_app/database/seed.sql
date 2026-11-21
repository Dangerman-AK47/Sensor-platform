-- =====================================================================
-- Sensor Platform / FileVault / DeviceVault v3 - Seed Data
-- Default development admin:
--   Username: admin
--   Password: admin123
--   Bcrypt hash ($2y$12$...):
--   $2y$12$L7R2Q5R5u6d2KkQW9f9u0O7o4C4c4A4A4A4A4A4A4A4A4A4A4A4A4 -> generated via standard bcrypt cost 12
-- =====================================================================

-- 1. Default Admin Account (password: password)
INSERT INTO `fv_admin_accounts` (`id`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`, `last_login_at`)
VALUES (
    'admin-root-0001',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'SUPER_ADMIN',
    1,
    NOW(),
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- 1b. Default Portal User (Username: user, Password: password)
INSERT INTO `fv_portal_users` (`id`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`, `last_login_at`)
VALUES (
    'user-portal-0001',
    'user',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'USER',
    1,
    NOW(),
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- 2. Registered Devices (Sample Active & Deactive devices)
-- SENSOR-001 (Token: a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef)
INSERT INTO `fv_registered_devices` 
(`id`, `device_id`, `status`, `registered_at`, `token_hash`, `first_seen_at`, `last_seen_at`, `sensor_version`, `firmware_version`, `hardware_model`, `metadata`, `upload_count`, `total_storage_bytes`, `last_upload_at`)
VALUES 
(
    'dev-001',
    'SENSOR-001',
    'ACTIVE',
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    '$2y$12$X5kY7z8A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0U1V2W3',
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    NOW(),
    '1.0.0',
    '2.1.0',
    'ESP32-S3',
    JSON_OBJECT('location', 'Lab Bench A', 'sensor_type', 'BME680'),
    12,
    4194304,
    DATE_SUB(NOW(), INTERVAL 1 HOUR)
),
(
    'dev-002',
    'SENSOR-002',
    'ACTIVE',
    DATE_SUB(NOW(), INTERVAL 5 DAY),
    '$2y$12$X5kY7z8A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0U1V2W3',
    DATE_SUB(NOW(), INTERVAL 5 DAY),
    DATE_SUB(NOW(), INTERVAL 10 MINUTE),
    '1.0.0',
    '2.1.0',
    'ESP32-WROOM-32D',
    JSON_OBJECT('location', 'Roof Weather Station', 'sensor_type', 'DHT22'),
    8,
    2097152,
    DATE_SUB(NOW(), INTERVAL 2 HOUR)
),
(
    'dev-003',
    'SENSOR-003',
    'DEACTIVE',
    DATE_SUB(NOW(), INTERVAL 14 DAY),
    '$2y$12$X5kY7z8A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0U1V2W3',
    DATE_SUB(NOW(), INTERVAL 14 DAY),
    DATE_SUB(NOW(), INTERVAL 3 DAY),
    '0.9.1',
    '1.8.0',
    'ESP32-DevKitC',
    JSON_OBJECT('location', 'Warehouse Bay 3', 'decommission_reason', 'Battery failure'),
    3,
    524288,
    DATE_SUB(NOW(), INTERVAL 4 DAY)
);

-- 3. Device Registration Requests
INSERT INTO `fv_device_registration_requests`
(`id`, `device_id`, `status`, `submitted_at`, `reviewed_at`, `review_reason`, `reviewed_by_admin_id`, `requester_metadata`, `pending_device_key`)
VALUES
(
    'req-001',
    'SENSOR-004',
    'PENDING',
    DATE_SUB(NOW(), INTERVAL 35 MINUTE),
    NULL,
    NULL,
    NULL,
    JSON_OBJECT('ip', '192.168.1.155', 'hardware', 'ESP32-C3', 'firmware', '3.0.0'),
    'key-sensor-004-pending'
),
(
    'req-002',
    'SENSOR-001',
    'APPROVED',
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    'Device authorized for Lab testing',
    'admin-root-0001',
    JSON_OBJECT('ip', '192.168.1.101', 'hardware', 'ESP32-S3'),
    'key-sensor-001-approved'
),
(
    'req-003',
    'UNKNOWN-DEV-99',
    'REJECTED',
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    'Unrecognized MAC address and hardware identifier',
    'admin-root-0001',
    JSON_OBJECT('ip', '10.0.0.99', 'hardware', 'Generic-ESP8266'),
    'key-unknown-rejected'
);

-- 4. Global Sensor Configurations
INSERT INTO `fv_global_sensor_configurations`
(`id`, `config_version`, `status`, `original_file_name`, `storage_key`, `mime_type`, `file_size_bytes`, `checksum`, `uploaded_by_admin_id`, `activated_by_admin_id`, `activated_at`, `created_at`)
VALUES
(
    'cfg-001',
    '1.0.0',
    'ARCHIVED',
    'sensor_config_v1.0.0.json',
    'configs/2026/09/sensor_config_v1.0.0.json',
    'application/json',
    1024,
    'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    'admin-root-0001',
    'admin-root-0001',
    DATE_SUB(NOW(), INTERVAL 14 DAY),
    DATE_SUB(NOW(), INTERVAL 14 DAY)
),
(
    'cfg-002',
    '1.1.0',
    'ACTIVE',
    'sensor_config_v1.1.0.json',
    'configs/2026/10/sensor_config_v1.1.0.json',
    'application/json',
    2048,
    '7d793037a0760186574b0282f2f435e7b1e50774690f69799f4eb6192de6974a',
    'admin-root-0001',
    'admin-root-0001',
    DATE_SUB(NOW(), INTERVAL 3 DAY),
    DATE_SUB(NOW(), INTERVAL 3 DAY)
),
(
    'cfg-003',
    '1.2.0',
    'INACTIVE',
    'sensor_config_v1.2.0_beta.json',
    'configs/2026/10/sensor_config_v1.2.0_beta.json',
    'application/json',
    2560,
    '9f83c60517b4a024026bb9e9100d979f59935f4ebb0633b0981199bed52d507f',
    'admin-root-0001',
    NULL,
    NULL,
    DATE_SUB(NOW(), INTERVAL 6 HOUR)
);

-- 5. Sample Uploaded File Records
INSERT INTO `fv_uploaded_file_records`
(`id`, `upload_record_id`, `sequence_number`, `registered_device_id`, `device_id`, `original_file_name`, `storage_key`, `mime_type`, `file_extension`, `file_size_bytes`, `checksum`, `upload_status`, `uploaded_at`)
VALUES
(
    'rec-001',
    'UPL-20261017-0001',
    1,
    'dev-001',
    'SENSOR-001',
    'telemetry_20261017_0900.csv',
    'uploads/2026/10/17/telemetry_20261017_0900.csv',
    'text/csv',
    'csv',
    262144,
    '2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824',
    'STORED',
    DATE_SUB(NOW(), INTERVAL 3 HOUR)
),
(
    'rec-002',
    'UPL-20261017-0002',
    2,
    'dev-002',
    'SENSOR-002',
    'weather_readings_hourly.json',
    'uploads/2026/10/17/weather_readings_hourly.json',
    'application/json',
    'json',
    131072,
    '486ea46224d1bb4fb680f34f7c9ad96a8f24ec88be73ea8e5a6c65260e9cb8a7',
    'STORED',
    DATE_SUB(NOW(), INTERVAL 2 HOUR)
);

-- 6. Sample Daily Version Check Metrics
INSERT INTO `fv_daily_sensor_version_check_metrics`
(`id`, `device_id`, `metric_date`, `result`, `check_count`)
VALUES
('met-001', 'SENSOR-001', CURDATE(), 'CURRENT', 48),
('met-002', 'SENSOR-002', CURDATE(), 'UPDATE_AVAILABLE', 24),
('met-003', 'SENSOR-003', CURDATE(), 'SENSOR_DEACTIVATED', 6);

-- 7. Sample Admin Audit Events
INSERT INTO `fv_admin_audit_events`
(`id`, `admin_id`, `username`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `user_agent`, `created_at`)
VALUES
('aud-001', 'admin-root-0001', 'admin', 'APPROVE_DEVICE', 'DEVICE_REQUEST', 'req-002', JSON_OBJECT('device_id', 'SENSOR-001'), '127.0.0.1', 'Mozilla/5.0 Chrome/120', DATE_SUB(NOW(), INTERVAL 7 DAY)),
('aud-002', 'admin-root-0001', 'admin', 'ACTIVATE_CONFIG', 'GLOBAL_CONFIG', 'cfg-002', JSON_OBJECT('version', '1.1.0', 'previous', '1.0.0'), '127.0.0.1', 'Mozilla/5.0 Chrome/120', DATE_SUB(NOW(), INTERVAL 3 DAY));
