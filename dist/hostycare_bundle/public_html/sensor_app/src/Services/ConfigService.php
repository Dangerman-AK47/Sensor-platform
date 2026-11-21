<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Utils\Semver;
use PDO;
use RuntimeException;

class ConfigService
{
    public static function listConfigs(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT c.*, 
                   u.username as uploader_username, 
                   a.username as activator_username
            FROM fv_global_sensor_configurations c
            LEFT JOIN fv_admin_accounts u ON c.uploaded_by_admin_id = u.id
            LEFT JOIN fv_admin_accounts a ON c.activated_by_admin_id = a.id
            ORDER BY c.created_at DESC
        ');
        return $stmt->fetchAll();
    }

    public static function getActiveConfig(): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM fv_global_sensor_configurations WHERE status = "ACTIVE" LIMIT 1');
        $cfg = $stmt->fetch();
        return $cfg ?: null;
    }

    public static function uploadConfig(string $version, array $file, string $adminId): array
    {
        $version = trim($version);
        if (!Semver::isValid($version)) {
            throw new RuntimeException("Version '{$version}' does not adhere to strict semver (MAJOR.MINOR.PATCH).");
        }

        $db = Database::getConnection();
        $check = $db->prepare('SELECT id FROM fv_global_sensor_configurations WHERE config_version = :ver LIMIT 1');
        $check->execute(['ver' => $version]);
        if ($check->fetch()) {
            throw new RuntimeException("Configuration version '{$version}' already exists.");
        }

        $originalName = basename($file['name']);
        $tmpPath = $file['tmp_name'];
        $fileSize = (int)$file['size'];
        $checksum = hash_file('sha256', $tmpPath);

        // Store file
        $storageKey = 'configs/' . date('Y/m') . '/' . time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $fullPath = StorageService::saveConfigFile($storageKey, $tmpPath);

        $id = bin2hex(random_bytes(16));
        $stmt = $db->prepare('
            INSERT INTO fv_global_sensor_configurations
            (id, config_version, status, original_file_name, storage_key, mime_type, file_size_bytes, checksum, uploaded_by_admin_id, created_at)
            VALUES (:id, :ver, "INACTIVE", :orig, :key, :mime, :size, :chk, :admin, NOW())
        ');
        $stmt->execute([
            'id' => $id,
            'ver' => $version,
            'orig' => $originalName,
            'key' => $storageKey,
            'mime' => $file['type'] ?: 'application/json',
            'size' => $fileSize,
            'chk' => $checksum,
            'admin' => $adminId
        ]);

        AuditService::log($adminId, 'UPLOAD_CONFIG', 'GLOBAL_CONFIG', $id, [
            'version' => $version,
            'filename' => $originalName
        ]);

        return [
            'id' => $id,
            'version' => $version,
            'checksum' => $checksum
        ];
    }

    public static function activateConfig(string $id, string $adminId): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM fv_global_sensor_configurations WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $target = $stmt->fetch();

            if (!$target) {
                $db->rollBack();
                throw new RuntimeException('Configuration record not found.');
            }

            if ($target['status'] === 'ACTIVE') {
                $db->rollBack();
                return $target;
            }

            if ($target['status'] === 'ARCHIVED') {
                $db->rollBack();
                throw new RuntimeException('Archived configurations cannot be reactivated.');
            }

            // Get current active config
            $activeStmt = $db->query('SELECT * FROM fv_global_sensor_configurations WHERE status = "ACTIVE" LIMIT 1 FOR UPDATE');
            $active = $activeStmt->fetch();

            if ($active) {
                if (Semver::compare($target['config_version'], $active['config_version']) <= 0) {
                    $db->rollBack();
                    throw new RuntimeException("New version ({$target['config_version']}) must be strictly greater than active version ({$active['config_version']}).");
                }

                // Archive active config
                $archiveStmt = $db->prepare('UPDATE fv_global_sensor_configurations SET status = "ARCHIVED" WHERE id = :id');
                $archiveStmt->execute(['id' => $active['id']]);
            }

            // Activate new config
            $activateStmt = $db->prepare('
                UPDATE fv_global_sensor_configurations
                SET status = "ACTIVE", activated_at = NOW(), activated_by_admin_id = :admin_id
                WHERE id = :id
            ');
            $activateStmt->execute(['admin_id' => $adminId, 'id' => $id]);

            $db->commit();

            AuditService::log($adminId, 'ACTIVATE_CONFIG', 'GLOBAL_CONFIG', $id, [
                'new_version' => $target['config_version'],
                'previous_version' => $active['config_version'] ?? null
            ]);

            return $target;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function checkVersion(string $deviceId, string $reportedVersion): array
    {
        $db = Database::getConnection();
        $active = self::getActiveConfig();

        if (!Semver::isValid($reportedVersion)) {
            $result = 'INVALID_VERSION';
            self::logVersionCheck($deviceId, $reportedVersion, null, $result);
            return [
                'result' => $result,
                'message' => 'Reported version does not match semver specification'
            ];
        }

        if (!$active) {
            $result = 'NO_CONFIGURATION_AVAILABLE';
            self::logVersionCheck($deviceId, $reportedVersion, null, $result);
            return [
                'result' => $result,
                'message' => 'No active global configuration deployed'
            ];
        }

        $activeVersion = $active['config_version'];
        $cmp = Semver::compare($reportedVersion, $activeVersion);

        if ($cmp === 0) {
            $result = 'CURRENT';
        } elseif ($cmp < 0) {
            $result = 'UPDATE_AVAILABLE';
        } else {
            // Reported version is newer than active config
            $result = 'CURRENT';
        }

        try {
            self::logVersionCheck($deviceId, $reportedVersion, $activeVersion, $result);
        } catch (\Throwable $e) {
            error_log("Failed to log version check event: " . $e->getMessage());
        }

        return [
            'result' => $result,
            'activeVersion' => $activeVersion,
            'reportedVersion' => $reportedVersion,
            'downloadUrl' => $result === 'UPDATE_AVAILABLE' ? '/api/v1/sensors/configuration/download?deviceId=' . urlencode($deviceId) : null
        ];
    }

    private static function logVersionCheck(string $deviceId, ?string $reported, ?string $active, string $result): void
    {
        try {
            $db = Database::getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // 1. Immutable event
            $stmt = $db->prepare('
                INSERT INTO fv_sensor_version_check_events 
                (id, device_id, reported_version, result, ip_address, checked_at)
                VALUES (:id, :device, :rep, :res, :ip, NOW())
            ');
            $stmt->execute([
                'id' => bin2hex(random_bytes(16)),
                'device' => $deviceId,
                'rep' => $reported ?? '0.0.0',
                'res' => in_array($result, ['CURRENT', 'UPDATE_AVAILABLE', 'NO_CONFIGURATION_AVAILABLE', 'SENSOR_DEACTIVATED']) ? $result : 'CURRENT',
                'ip' => $ip
            ]);

            // 2. Daily aggregate metric
            $metricId = bin2hex(random_bytes(16));
            try {
                $upsert = $db->prepare('
                    INSERT INTO fv_daily_sensor_version_check_metrics
                    (id, device_id, metric_date, result, total_checks)
                    VALUES (:id, :device, CURDATE(), :res, 1)
                    ON DUPLICATE KEY UPDATE total_checks = total_checks + 1
                ');
                $upsert->execute([
                    'id' => $metricId,
                    'device' => $deviceId,
                    'res' => in_array($result, ['CURRENT', 'UPDATE_AVAILABLE', 'NO_CONFIGURATION_AVAILABLE', 'SENSOR_DEACTIVATED']) ? $result : 'CURRENT'
                ]);
            } catch (\Throwable $e) {
                try {
                    $upsert = $db->prepare('
                        INSERT INTO fv_daily_sensor_version_check_metrics
                        (id, device_id, metric_date, result, check_count)
                        VALUES (:id, :device, CURDATE(), :res, 1)
                        ON DUPLICATE KEY UPDATE check_count = check_count + 1
                    ');
                    $upsert->execute([
                        'id' => $metricId,
                        'device' => $deviceId,
                        'res' => in_array($result, ['CURRENT', 'UPDATE_AVAILABLE', 'NO_CONFIGURATION_AVAILABLE', 'SENSOR_DEACTIVATED']) ? $result : 'CURRENT'
                    ]);
                } catch (\Throwable $e2) {
                    error_log("Failed to log daily version metric: " . $e2->getMessage());
                }
            }
        } catch (\Throwable $ex) {
            error_log("Failed to log version check event: " . $ex->getMessage());
        }
    }
}
