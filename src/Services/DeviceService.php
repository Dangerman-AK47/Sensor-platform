<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Utils\DeviceIdValidator;
use PDO;
use RuntimeException;

class DeviceService
{
    public static function getStats(): array
    {
        $db = Database::getConnection();

        // 1. Device counts
        $devices = $db->query('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = "ACTIVE" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "DEACTIVE" THEN 1 ELSE 0 END) as deactive
            FROM fv_registered_devices
        ')->fetch();

        // 2. Request counts
        $requests = $db->query('
            SELECT 
                SUM(CASE WHEN status = "PENDING" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ("APPROVED", "AUTO_APPROVED") THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status IN ("REJECTED", "CANCELLED") THEN 1 ELSE 0 END) as rejected
            FROM fv_device_registration_requests
        ')->fetch();

        // 3. Upload & Storage metrics
        $files = $db->query('
            SELECT 
                COUNT(*) as total_files,
                COALESCE(SUM(file_size_bytes), 0) as total_bytes,
                SUM(CASE WHEN uploaded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as uploads_24h
            FROM fv_uploaded_file_records
        ')->fetch();

        // 4. Recent Uploads (limit 5)
        $recentUploads = $db->query('
            SELECT upload_record_id, device_id, original_file_name, file_size_bytes, uploaded_at
            FROM fv_uploaded_file_records
            ORDER BY uploaded_at DESC
            LIMIT 5
        ')->fetchAll();

        // 5. Recent Requests (limit 5)
        $recentRequests = $db->query('
            SELECT id, device_id, status, submitted_at
            FROM fv_device_registration_requests
            ORDER BY submitted_at DESC
            LIMIT 5
        ')->fetchAll();

        // 6. 7-Day Upload Activity
        $activity7d = $db->query('
            SELECT 
                DATE(uploaded_at) as upload_date,
                DAYNAME(uploaded_at) as day_name,
                COUNT(*) as count
            FROM fv_uploaded_file_records
            WHERE uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(uploaded_at), DAYNAME(uploaded_at)
            ORDER BY upload_date ASC
        ')->fetchAll();

        return [
            'totalSensors' => (int)($devices['total'] ?? 0),
            'activeSensors' => (int)($devices['active'] ?? 0),
            'deactiveSensors' => (int)($devices['deactive'] ?? 0),
            'pendingRequests' => (int)($requests['pending'] ?? 0),
            'approvedRequests' => (int)($requests['approved'] ?? 0),
            'rejectedRequests' => (int)($requests['rejected'] ?? 0),
            'totalFiles' => (int)($files['total_files'] ?? 0),
            'totalStorageBytes' => (int)($files['total_bytes'] ?? 0),
            'uploadsLast24h' => (int)($files['uploads_24h'] ?? 0),
            'recentUploads' => $recentUploads,
            'recentRequests' => $recentRequests,
            'activity7d' => $activity7d
        ];
    }

    public static function listDevices(?string $search = null, ?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $db = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = '(device_id LIKE :search1 OR hardware_model LIKE :search2)';
            $searchVal = '%' . trim($search) . '%';
            $params['search1'] = $searchVal;
            $params['search2'] = $searchVal;
        }

        if (!empty($status) && in_array(strtoupper($status), ['ACTIVE', 'DEACTIVE'], true)) {
            $where[] = 'status = :status';
            $params['status'] = strtoupper($status);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM fv_registered_devices {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT id, device_id, status, registered_at, first_seen_at, last_seen_at, 
                       sensor_version, firmware_version, hardware_model, upload_count, 
                       total_storage_bytes, last_upload_at
                FROM fv_registered_devices 
                {$whereClause}
                ORDER BY registered_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll();

        return [
            'devices' => $devices,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage)
        ];
    }

    public static function getDevice(string $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM fv_registered_devices WHERE id = :id OR device_id = :device_id LIMIT 1');
        $stmt->execute(['id' => $id, 'device_id' => strtoupper($id)]);
        $device = $stmt->fetch();

        if (!$device) {
            return null;
        }

        // Fetch recent uploads
        $uploadsStmt = $db->prepare('
            SELECT upload_record_id, original_file_name, file_size_bytes, mime_type, uploaded_at, checksum
            FROM fv_uploaded_file_records
            WHERE registered_device_id = :device_id
            ORDER BY uploaded_at DESC
            LIMIT 10
        ');
        $uploadsStmt->execute(['device_id' => $device['id']]);
        $device['recent_uploads'] = $uploadsStmt->fetchAll();

        return $device;
    }

    public static function updateStatus(string $id, string $newStatus, string $adminId, ?string $reason = null): bool
    {
        $newStatus = strtoupper($newStatus);
        if (!in_array($newStatus, ['ACTIVE', 'DEACTIVE'], true)) {
            throw new RuntimeException("Invalid status: {$newStatus}");
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT status, device_id FROM fv_registered_devices WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $device = $stmt->fetch();

            if (!$device) {
                $db->rollBack();
                return false;
            }

            $prevStatus = $device['status'];
            if ($prevStatus === $newStatus) {
                $db->rollBack();
                return true;
            }

            // Update status
            $update = $db->prepare('UPDATE fv_registered_devices SET status = :status WHERE id = :id');
            $update->execute(['status' => $newStatus, 'id' => $id]);

            // Log status transition event
            $eventStmt = $db->prepare('
                INSERT INTO fv_device_status_events 
                (id, device_id, previous_status, new_status, changed_by_admin_id, reason, changed_at)
                VALUES (:id, :device_id, :prev, :new, :admin_id, :reason, NOW())
            ');
            $eventStmt->execute([
                'id' => bin2hex(random_bytes(16)),
                'device_id' => $device['device_id'],
                'prev' => $prevStatus,
                'new' => $newStatus,
                'admin_id' => $adminId,
                'reason' => $reason
            ]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function regenerateToken(string $id, string $adminId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT device_id FROM fv_registered_devices WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $device = $stmt->fetch();

        if (!$device) {
            throw new RuntimeException("Device not found.");
        }

        $tokenData = AuthService::generateSensorToken();
        $update = $db->prepare('UPDATE fv_registered_devices SET token_hash = :hash WHERE id = :id');
        $update->execute(['hash' => $tokenData['tokenHash'], 'id' => $id]);

        AuditService::log($adminId, 'REGENERATE_TOKEN', 'DEVICE', $device['device_id'], [
            'device_id' => $device['device_id']
        ]);

        return [
            'deviceId' => $device['device_id'],
            'rawToken' => $tokenData['rawToken']
        ];
    }

    public static function recordHeartbeat(string $deviceIdOrPk, ?string $sensorVersion = null, ?string $firmwareVersion = null, ?string $hardwareModel = null, ?string $metadata = null): void
    {
        try {
            $db = Database::getConnection();
            $fields = ['`last_seen_at` = NOW()'];
            $params = [
                'target_id' => $deviceIdOrPk,
                'target_dev_id' => $deviceIdOrPk
            ];

            if (!empty($sensorVersion) && $sensorVersion !== '0.0.0') {
                $fields[] = '`sensor_version` = :sensor_version';
                $params['sensor_version'] = $sensorVersion;
            }
            if (!empty($firmwareVersion)) {
                $fields[] = '`firmware_version` = :firmware_version';
                $params['firmware_version'] = $firmwareVersion;
            }
            if (!empty($hardwareModel)) {
                $fields[] = '`hardware_model` = :hardware_model';
                $params['hardware_model'] = $hardwareModel;
            }
            if (!empty($metadata)) {
                $fields[] = '`metadata` = :metadata';
                $params['metadata'] = $metadata;
            }

            $sql = 'UPDATE `fv_registered_devices` SET ' . implode(', ', $fields) . ' WHERE `id` = :target_id OR `device_id` = :target_dev_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } catch (\Throwable $e) {
            error_log("Failed to record heartbeat: " . $e->getMessage());
        }
    }

    public static function updateSensorVersion(string $deviceIdOrPk, string $version): void
    {
        $version = trim($version);
        if (empty($version) || $version === '0.0.0') {
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('UPDATE `fv_registered_devices` SET `sensor_version` = :ver, `last_seen_at` = NOW() WHERE `id` = :target_id OR `device_id` = :target_dev_id');
            $stmt->execute([
                'ver' => $version,
                'target_id' => $deviceIdOrPk,
                'target_dev_id' => $deviceIdOrPk
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to update sensor version: " . $e->getMessage());
        }
    }

    public static function submitRegistration(array $payload, ?string $ip = null): array
    {
        $deviceId = DeviceIdValidator::normalize($payload['deviceId'] ?? null);
        if ($deviceId === null) {
            throw new RuntimeException('Invalid deviceId format.');
        }

        $db = Database::getConnection();

        // Check if device already registered
        $check = $db->prepare('SELECT id, status FROM fv_registered_devices WHERE device_id = :id LIMIT 1');
        $check->execute(['id' => $deviceId]);
        $existing = $check->fetch();

        if ($existing && $existing['status'] === 'ACTIVE') {
            return [
                'status' => 'CONFLICT',
                'message' => 'Device is already registered and active'
            ];
        }

        $db->beginTransaction();
        try {
            $requestId = bin2hex(random_bytes(16));
            $tokenData = AuthService::generateSensorToken();
            $meta = [
                'ip' => $ip,
                'sensorVersion' => $payload['sensorVersion'] ?? '1.0.0',
                'firmwareVersion' => $payload['firmwareVersion'] ?? '2.1.0',
                'hardwareModel' => $payload['hardwareModel'] ?? 'ESP32-S3',
                'metadata' => $payload['metadata'] ?? null
            ];

            // Auto-approved request entry in registration history
            $stmt = $db->prepare('
                INSERT INTO fv_device_registration_requests
                (id, device_id, status, submitted_at, reviewed_at, requester_metadata, pending_device_key)
                VALUES (:id, :device_id, "AUTO_APPROVED", NOW(), NOW(), :meta, :key)
            ');
            $stmt->execute([
                'id' => $requestId,
                'device_id' => $deviceId,
                'meta' => json_encode($meta),
                'key' => bin2hex(random_bytes(16))
            ]);

            // Upsert device immediately as ACTIVE
            $devStmt = $db->prepare('
                INSERT INTO fv_registered_devices
                (id, device_id, status, registered_at, token_hash, sensor_version, firmware_version, hardware_model, metadata)
                VALUES (:id, :device_id, "ACTIVE", NOW(), :token_hash, :sensor_ver, :fw_ver, :hw, :meta)
                ON DUPLICATE KEY UPDATE
                status = "ACTIVE",
                token_hash = VALUES(token_hash),
                sensor_version = VALUES(sensor_version),
                firmware_version = VALUES(firmware_version),
                hardware_model = VALUES(hardware_model),
                metadata = VALUES(metadata)
            ');
            $devicePk = bin2hex(random_bytes(16));
            $devStmt->execute([
                'id' => $devicePk,
                'device_id' => $deviceId,
                'token_hash' => $tokenData['tokenHash'],
                'sensor_ver' => $meta['sensorVersion'],
                'fw_ver' => $meta['firmwareVersion'],
                'hw' => $meta['hardwareModel'],
                'meta' => json_encode($meta['metadata'])
            ]);

            $db->commit();

            AuditService::log('system', 'AUTO_APPROVE_DEVICE', 'DEVICE', $deviceId, [
                'device_id' => $deviceId,
                'requestId' => $requestId
            ], $ip);

            return [
                'status' => 'AUTO_APPROVED',
                'deviceId' => $deviceId,
                'token' => $tokenData['rawToken'],
                'requestId' => $requestId,
                'message' => 'Sensor automatically approved! Store your Bearer token securely.'
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function approveRequest(string $requestId, string $adminId): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM fv_device_registration_requests WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch();

            if (!$request || $request['status'] !== 'PENDING') {
                $db->rollBack();
                throw new RuntimeException('Registration request not found or not pending.');
            }

            $deviceId = $request['device_id'];
            $meta = json_decode($request['requester_metadata'] ?? '{}', true) ?: [];

            // Generate sensor token
            $tokenData = AuthService::generateSensorToken();

            // Upsert device
            $devStmt = $db->prepare('
                INSERT INTO fv_registered_devices
                (id, device_id, status, registered_at, token_hash, sensor_version, firmware_version, hardware_model, metadata)
                VALUES (:id, :device_id, "ACTIVE", NOW(), :token_hash, :sensor_ver, :fw_ver, :hw, :meta)
                ON DUPLICATE KEY UPDATE
                status = "ACTIVE",
                token_hash = VALUES(token_hash),
                sensor_version = VALUES(sensor_version),
                firmware_version = VALUES(firmware_version),
                hardware_model = VALUES(hardware_model)
            ');
            $devicePk = bin2hex(random_bytes(16));
            $devStmt->execute([
                'id' => $devicePk,
                'device_id' => $deviceId,
                'token_hash' => $tokenData['tokenHash'],
                'sensor_ver' => $meta['sensorVersion'] ?? null,
                'fw_ver' => $meta['firmwareVersion'] ?? null,
                'hw' => $meta['hardwareModel'] ?? null,
                'meta' => json_encode($meta['metadata'] ?? null)
            ]);

            // Update request
            $updReq = $db->prepare('
                UPDATE fv_device_registration_requests
                SET status = "APPROVED", reviewed_at = NOW(), reviewed_by_admin_id = :admin_id
                WHERE id = :id
            ');
            $updReq->execute(['admin_id' => $adminId, 'id' => $requestId]);

            $db->commit();

            AuditService::log($adminId, 'APPROVE_DEVICE', 'DEVICE_REQUEST', $requestId, [
                'device_id' => $deviceId
            ]);

            return [
                'deviceId' => $deviceId,
                'rawToken' => $tokenData['rawToken'],
                'status' => 'APPROVED'
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function rejectRequest(string $requestId, string $adminId, string $reason): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            UPDATE fv_device_registration_requests
            SET status = "REJECTED", reviewed_at = NOW(), reviewed_by_admin_id = :admin_id, review_reason = :reason
            WHERE id = :id AND status = "PENDING"
        ');
        $stmt->execute([
            'admin_id' => $adminId,
            'reason' => trim($reason),
            'id' => $requestId
        ]);

        if ($stmt->rowCount() > 0) {
            AuditService::log($adminId, 'REJECT_DEVICE', 'DEVICE_REQUEST', $requestId, [
                'reason' => $reason
            ]);
            return true;
        }
        return false;
    }

    public static function listRequests(?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $db = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($status)) {
            $where[] = 'r.status = :status';
            $params['status'] = strtoupper($status);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $db->prepare("SELECT COUNT(*) FROM fv_device_registration_requests r {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT r.*, a.username as reviewer_username
                FROM fv_device_registration_requests r
                LEFT JOIN fv_admin_accounts a ON r.reviewed_by_admin_id = a.id
                {$whereClause}
                ORDER BY r.submitted_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        return [
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage)
        ];
    }

    public static function deleteDevice(string $idOrDeviceId, string $adminId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, device_id FROM fv_registered_devices WHERE id = :id OR device_id = :dev_id LIMIT 1');
        $stmt->execute(['id' => $idOrDeviceId, 'dev_id' => $idOrDeviceId]);
        $device = $stmt->fetch();

        if (!$device) {
            return false;
        }

        $db->beginTransaction();
        try {
            $deviceId = $device['device_id'];
            $devPk = $device['id'];

            // 1. Delete associated uploaded files from disk & DB
            try {
                $filesStmt = $db->prepare('SELECT storage_key FROM fv_uploaded_file_records WHERE registered_device_id = :pk OR device_id = :did');
                $filesStmt->execute(['pk' => $devPk, 'did' => $deviceId]);
                $files = $filesStmt->fetchAll();
                foreach ($files as $f) {
                    if (!empty($f['storage_key'])) {
                        StorageService::deleteFile($f['storage_key']);
                    }
                }
                $delFiles = $db->prepare('DELETE FROM fv_uploaded_file_records WHERE registered_device_id = :pk OR device_id = :did');
                $delFiles->execute(['pk' => $devPk, 'did' => $deviceId]);
            } catch (\Throwable $e) {
                error_log("deleteDevice warning (uploaded files): " . $e->getMessage());
            }

            // 2. Delete config download audits
            try {
                $delDl = $db->prepare('DELETE FROM fv_sensor_config_download_audits WHERE device_id = :did');
                $delDl->execute(['did' => $deviceId]);
            } catch (\Throwable $e) {
                try {
                    $delDl2 = $db->prepare('DELETE FROM fv_sensor_config_download_events WHERE device_id = :did');
                    $delDl2->execute(['did' => $deviceId]);
                } catch (\Throwable $e2) {}
            }

            // 3. Delete version check events
            try {
                $delEv = $db->prepare('DELETE FROM fv_sensor_version_check_events WHERE device_id = :did');
                $delEv->execute(['did' => $deviceId]);
            } catch (\Throwable $e) {}

            // 4. Delete daily metrics
            try {
                $delMetrics = $db->prepare('DELETE FROM fv_daily_sensor_version_check_metrics WHERE device_id = :did');
                $delMetrics->execute(['did' => $deviceId]);
            } catch (\Throwable $e) {}
            try {
                $delMetrics2 = $db->prepare('DELETE FROM fv_daily_sensor_version_metrics WHERE device_id = :did');
                $delMetrics2->execute(['did' => $deviceId]);
            } catch (\Throwable $e) {}

            // 5. Delete device status events
            try {
                $delEvents = $db->prepare('DELETE FROM fv_device_status_events WHERE device_id = :did');
                $delEvents->execute(['did' => $deviceId]);
            } catch (\Throwable $e) {}

            // 6. Delete registration requests
            try {
                $delRequests = $db->prepare('DELETE FROM fv_device_registration_requests WHERE device_id = :did');
                $delRequests->execute(['did' => $deviceId]);
            } catch (\Throwable $e) {}

            // 7. Delete device itself
            $delDev = $db->prepare('DELETE FROM fv_registered_devices WHERE id = :pk OR device_id = :did');
            $delDev->execute(['pk' => $devPk, 'did' => $deviceId]);

            $db->commit();

            AuditService::log($adminId, 'DELETE_DEVICE', 'DEVICE', $deviceId, [
                'device_id' => $deviceId,
                'id' => $devPk
            ]);

            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
