<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Utils\Semver;
use PDO;
use RuntimeException;

class CodeFileService
{
    private static bool $tableChecked = false;

    public static function ensureTableExists(): void
    {
        if (self::$tableChecked) return;

        $db = Database::getConnection();
        $db->exec("
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
        ");

        self::$tableChecked = true;
    }

    public static function listCodeFiles(): array
    {
        self::ensureTableExists();
        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT c.*, 
                   u.username as uploader_username, 
                   a.username as activator_username
            FROM fv_global_sensor_code_files c
            LEFT JOIN fv_admin_accounts u ON c.uploaded_by_admin_id = u.id
            LEFT JOIN fv_admin_accounts a ON c.activated_by_admin_id = a.id
            ORDER BY c.created_at DESC
        ');
        return $stmt->fetchAll();
    }

    public static function getActiveCodeFile(): ?array
    {
        self::ensureTableExists();
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM fv_global_sensor_code_files WHERE status = "ACTIVE" LIMIT 1');
        $code = $stmt->fetch();
        return $code ?: null;
    }

    public static function getCodeFileById(string $id): ?array
    {
        self::ensureTableExists();
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM fv_global_sensor_code_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function uploadCodeFile(string $version, array $file, string $adminId): array
    {
        self::ensureTableExists();
        $version = trim($version);
        if (empty($version)) {
            throw new RuntimeException("Code version cannot be empty.");
        }

        $db = Database::getConnection();
        $check = $db->prepare('SELECT id FROM fv_global_sensor_code_files WHERE code_version = :ver LIMIT 1');
        $check->execute(['ver' => $version]);
        if ($check->fetch()) {
            throw new RuntimeException("Code file version '{$version}' already exists.");
        }

        $originalName = basename($file['name']);
        $tmpPath = $file['tmp_name'];
        $fileSize = (int)$file['size'];
        $checksum = hash_file('sha256', $tmpPath);

        // Store file safely
        $storageKey = 'code_files/' . date('Y/m') . '/' . time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $fullPath = dirname(__DIR__, 2) . '/storage/' . $storageKey;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!move_uploaded_file($tmpPath, $fullPath)) {
            if (!copy($tmpPath, $fullPath)) {
                throw new RuntimeException("Failed to save code file to disk.");
            }
        }

        $id = bin2hex(random_bytes(16));
        $stmt = $db->prepare('
            INSERT INTO fv_global_sensor_code_files
            (id, code_version, status, original_file_name, storage_key, mime_type, file_size_bytes, checksum, uploaded_by_admin_id, created_at)
            VALUES (:id, :ver, "INACTIVE", :orig, :key, :mime, :size, :chk, :admin, NOW())
        ');
        $stmt->execute([
            'id' => $id,
            'ver' => $version,
            'orig' => $originalName,
            'key' => $storageKey,
            'mime' => $file['type'] ?: 'application/octet-stream',
            'size' => $fileSize,
            'chk' => $checksum,
            'admin' => $adminId
        ]);

        AuditService::log($adminId, 'UPLOAD_CODE_FILE', 'SENSOR_CODE', $id, [
            'version' => $version,
            'filename' => $originalName
        ]);

        return [
            'id' => $id,
            'version' => $version,
            'checksum' => $checksum
        ];
    }

    public static function activateCodeFile(string $id, string $adminId): array
    {
        self::ensureTableExists();
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM fv_global_sensor_code_files WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $target = $stmt->fetch();

            if (!$target) {
                $db->rollBack();
                throw new RuntimeException('Code file record not found.');
            }

            // Archive currently active code file
            $archive = $db->prepare('UPDATE fv_global_sensor_code_files SET status = "ARCHIVED" WHERE status = "ACTIVE"');
            $archive->execute();

            // Activate new code file
            $activate = $db->prepare('
                UPDATE fv_global_sensor_code_files
                SET status = "ACTIVE",
                    activated_by_admin_id = :admin,
                    activated_at = NOW()
                WHERE id = :id
            ');
            $activate->execute([
                'admin' => $adminId,
                'id' => $id
            ]);

            $db->commit();

            AuditService::log($adminId, 'ACTIVATE_CODE_FILE', 'SENSOR_CODE', $id, [
                'version' => $target['code_version'],
                'filename' => $target['original_file_name']
            ]);

            return [
                'id' => $id,
                'version' => $target['code_version'],
                'activatedAt' => date('c')
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function deleteCodeFile(string $id, string $adminId): bool
    {
        self::ensureTableExists();
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM fv_global_sensor_code_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();

        if (!$target) {
            return false;
        }

        if ($target['status'] === 'ACTIVE') {
            throw new RuntimeException('Cannot delete the currently ACTIVE code file. Activate another code file first.');
        }

        $filePath = dirname(__DIR__, 2) . '/storage/' . $target['storage_key'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $del = $db->prepare('DELETE FROM fv_global_sensor_code_files WHERE id = :id');
        $del->execute(['id' => $id]);

        AuditService::log($adminId, 'DELETE_CODE_FILE', 'SENSOR_CODE', $id, [
            'version' => $target['code_version'],
            'filename' => $target['original_file_name']
        ]);

        return true;
    }

    public static function checkCodeVersion(string $deviceId, string $reportedVersion): array
    {
        $activeCode = self::getActiveCodeFile();
        if (!$activeCode) {
            return [
                'result' => 'NO_CODE_AVAILABLE',
                'isUpdateAvailable' => false,
                'activeVersion' => null,
                'reportedVersion' => $reportedVersion,
                'downloadUrl' => null
            ];
        }

        $activeVersion = $activeCode['code_version'];
        $isUpdate = ($reportedVersion !== $activeVersion);

        return [
            'result' => $isUpdate ? 'UPDATE_AVAILABLE' : 'CURRENT',
            'isUpdateAvailable' => $isUpdate,
            'activeVersion' => $activeVersion,
            'reportedVersion' => $reportedVersion,
            'downloadUrl' => '/api/v1/sensors/code/download?deviceId=' . urlencode($deviceId)
        ];
    }
}
