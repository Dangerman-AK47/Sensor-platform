<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

class AuditService
{
    public static function log(?string $adminId, string $action, string $entityType, ?string $entityId = null, ?array $metadata = null): void
    {
        try {
            $db = Database::getConnection();
            $username = 'SYSTEM';

            if (!empty($adminId)) {
                $stmt = $db->prepare('SELECT username FROM fv_admin_accounts WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $adminId]);
                $username = $stmt->fetchColumn() ?: 'admin';
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser', 0, 255);

            $metaArr = $metadata ?? [];
            if (!isset($metaArr['user_agent'])) {
                $metaArr['user_agent'] = $ua;
            }

            // Primary insert matching production_install.sql schema (without user_agent column)
            try {
                $ins = $db->prepare('
                    INSERT INTO fv_admin_audit_events
                    (id, admin_id, username, action, entity_type, entity_id, metadata, ip_address, created_at)
                    VALUES (:id, :admin_id, :user, :action, :type, :ent_id, :meta, :ip, NOW())
                ');
                $ins->execute([
                    'id' => bin2hex(random_bytes(16)),
                    'admin_id' => $adminId,
                    'user' => $username,
                    'action' => $action,
                    'type' => $entityType,
                    'ent_id' => $entityId,
                    'meta' => json_encode($metaArr),
                    'ip' => $ip
                ]);
            } catch (\Throwable $e) {
                // Fallback in case schema with user_agent column is present
                $ins = $db->prepare('
                    INSERT INTO fv_admin_audit_events
                    (id, admin_id, username, action, entity_type, entity_id, metadata, ip_address, user_agent, created_at)
                    VALUES (:id, :admin_id, :user, :action, :type, :ent_id, :meta, :ip, :ua, NOW())
                ');
                $ins->execute([
                    'id' => bin2hex(random_bytes(16)),
                    'admin_id' => $adminId,
                    'user' => $username,
                    'action' => $action,
                    'type' => $entityType,
                    'ent_id' => $entityId,
                    'meta' => json_encode($metaArr),
                    'ip' => $ip,
                    'ua' => $ua
                ]);
            }
        } catch (\Throwable $e) {
            error_log("Failed to write audit event: " . $e->getMessage());
        }
    }

    public static function listAuditLogs(int $page = 1, int $perPage = 25): array
    {
        $db = Database::getConnection();
        $total = (int)$db->query('SELECT COUNT(*) FROM fv_admin_audit_events')->fetchColumn();

        if ($total === 0) {
            // Seed initial baseline audit event if table is currently empty
            self::log(null, 'SYSTEM_INIT', 'PLATFORM', 'PROD-INSTANCE', [
                'event' => 'FileVault & ESP32 Sensor Platform production instance online',
                'host' => $_SERVER['HTTP_HOST'] ?? 'realtimeprojectsolutions.com'
            ]);
            $total = (int)$db->query('SELECT COUNT(*) FROM fv_admin_audit_events')->fetchColumn();
        }

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $db->prepare("
            SELECT * FROM fv_admin_audit_events
            ORDER BY created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute();
        $logs = $stmt->fetchAll();

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage)
        ];
    }

    public static function logConfigDownload(string $configId, string $deviceId, string $version): void
    {
        try {
            $db = Database::getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $db->prepare('
                INSERT INTO fv_sensor_config_download_events
                (id, config_id, device_id, config_version, ip_address, downloaded_at)
                VALUES (:id, :cfg, :dev, :ver, :ip, NOW())
            ');
            $stmt->execute([
                'id' => bin2hex(random_bytes(16)),
                'cfg' => $configId,
                'dev' => $deviceId,
                'ver' => $version,
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log config download event: " . $e->getMessage());
        }
    }

    public static function getVersionCheckMetrics(): array
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->query('
                SELECT device_id, result, SUM(total_checks) as total_checks, MAX(metric_date) as last_date
                FROM fv_daily_sensor_version_check_metrics
                GROUP BY device_id, result
                ORDER BY last_date DESC, total_checks DESC
            ');
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            try {
                $stmt = $db->query('
                    SELECT device_id, result, SUM(check_count) as total_checks, MAX(metric_date) as last_date
                    FROM fv_daily_sensor_version_check_metrics
                    GROUP BY device_id, result
                    ORDER BY last_date DESC, total_checks DESC
                ');
                return $stmt->fetchAll();
            } catch (\Throwable $e2) {
                return [];
            }
        }
    }
}
