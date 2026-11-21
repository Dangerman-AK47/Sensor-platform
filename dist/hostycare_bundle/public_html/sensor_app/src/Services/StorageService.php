<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\Config;
use PDO;
use RuntimeException;

class StorageService
{
    private static function getStorageRoot(): string
    {
        return dirname(__DIR__, 2) . '/storage';
    }

    public static function saveConfigFile(string $storageKey, string $tmpFilePath): string
    {
        $targetDir = self::getStorageRoot() . '/' . dirname($storageKey);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $fullPath = self::getStorageRoot() . '/' . $storageKey;
        if (!copy($tmpFilePath, $fullPath)) {
            throw new RuntimeException("Failed to store configuration file.");
        }

        return $fullPath;
    }

    public static function saveUploadedFile(array $fileInfo, array $device): array
    {
        $maxMb = (int)Config::get('MAX_FILE_SIZE_MB', 100);
        $maxBytes = $maxMb * 1024 * 1024;

        if ($fileInfo['size'] > $maxBytes) {
            throw new RuntimeException("File exceeds maximum allowed size of {$maxMb}MB");
        }

        $origName = basename($fileInfo['name']);
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
        $ext = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION)) ?: 'bin';

        $timestamp = time();
        $randomHex = bin2hex(random_bytes(8));
        $storageKey = 'uploads/' . date('Y/m/d') . '/' . $timestamp . '-' . $randomHex . '.' . $ext;

        $targetDir = self::getStorageRoot() . '/' . dirname($storageKey);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $fullPath = self::getStorageRoot() . '/' . $storageKey;
        if (!move_uploaded_file($fileInfo['tmp_name'], $fullPath)) {
            // Fallback for CLI/testing where move_uploaded_file fails on direct tmp
            if (!copy($fileInfo['tmp_name'], $fullPath)) {
                throw new RuntimeException("Failed to persist uploaded file to storage.");
            }
        }

        $checksum = hash_file('sha256', $fullPath);
        $fileSize = filesize($fullPath);
        $uploadRecordId = 'UPL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $recordPk = bin2hex(random_bytes(16));

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Insert file record
            $stmt = $db->prepare('
                INSERT INTO fv_uploaded_file_records
                (id, upload_record_id, registered_device_id, device_id, original_file_name, storage_key, mime_type, file_extension, file_size_bytes, checksum, upload_status, uploaded_at)
                VALUES (:id, :rec_id, :dev_pk, :dev_id, :orig, :key, :mime, :ext, :size, :chk, "STORED", NOW())
            ');
            $stmt->execute([
                'id' => $recordPk,
                'rec_id' => $uploadRecordId,
                'dev_pk' => $device['id'],
                'dev_id' => $device['device_id'],
                'orig' => $origName,
                'key' => $storageKey,
                'mime' => $fileInfo['type'] ?: 'application/octet-stream',
                'ext' => $ext,
                'size' => $fileSize,
                'chk' => $checksum
            ]);

            // 2. Update device statistics
            $upd = $db->prepare('
                UPDATE fv_registered_devices
                SET upload_count = upload_count + 1,
                    total_storage_bytes = total_storage_bytes + :size,
                    last_upload_at = NOW()
                WHERE id = :id
            ');
            $upd->execute(['size' => $fileSize, 'id' => $device['id']]);

            // 3. Fallback database blob if configured
            if (Config::get('UPLOAD_STORAGE_PROVIDER') === 'database') {
                $blobStmt = $db->prepare('
                    INSERT INTO fv_storage_blobs (id, blob_key, mime_type, byte_size, blob_data, checksum, created_at)
                    VALUES (:id, :key, :mime, :size, :data, :chk, NOW())
                ');
                $blobStmt->execute([
                    'id' => bin2hex(random_bytes(16)),
                    'key' => $storageKey,
                    'mime' => $fileInfo['mimeType'] ?? 'application/octet-stream',
                    'size' => $fileSize,
                    'data' => file_get_contents($fullPath),
                    'chk' => $checksum
                ]);
            }

            $db->commit();

            return [
                'uploadId' => $uploadRecordId,
                'fileName' => $origName,
                'fileSize' => $fileSize,
                'checksum' => $checksum,
                'uploadedAt' => date('c')
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            throw $e;
        }
    }

    public static function listFiles(?string $search = null, int $page = 1, int $perPage = 20): array
    {
        $db = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = '(device_id LIKE :search1 OR original_file_name LIKE :search2 OR upload_record_id LIKE :search3)';
            $searchVal = '%' . trim($search) . '%';
            $params['search1'] = $searchVal;
            $params['search2'] = $searchVal;
            $params['search3'] = $searchVal;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $db->prepare("SELECT COUNT(*) FROM fv_uploaded_file_records {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT id, upload_record_id, device_id, original_file_name, storage_key, mime_type, file_size_bytes, checksum, upload_status, uploaded_at
                FROM fv_uploaded_file_records
                {$whereClause}
                ORDER BY uploaded_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $files = $stmt->fetchAll();

        return [
            'files' => $files,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage)
        ];
    }

    public static function getFile(string $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM fv_uploaded_file_records WHERE id = :id OR upload_record_id = :rec_id LIMIT 1');
        $stmt->execute(['id' => $id, 'rec_id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            return null;
        }

        $fullPath = self::getStorageRoot() . '/' . $record['storage_key'];
        $record['full_path'] = $fullPath;
        $record['exists'] = file_exists($fullPath);

        return $record;
    }

    public static function deleteFile(string $id, string $adminId): bool
    {
        $db = Database::getConnection();
        $file = self::getFile($id);

        if (!$file) {
            return false;
        }

        $db->beginTransaction();

        try {
            // Delete DB record
            $del = $db->prepare('DELETE FROM fv_uploaded_file_records WHERE id = :id');
            $del->execute(['id' => $file['id']]);

            // Decrement device counts
            $upd = $db->prepare('
                UPDATE fv_registered_devices 
                SET upload_count = GREATEST(0, upload_count - 1),
                    total_storage_bytes = GREATEST(0, total_storage_bytes - :size)
                WHERE id = :dev_id
            ');
            $upd->execute(['size' => $file['file_size_bytes'], 'dev_id' => $file['registered_device_id']]);

            // Delete storage file if exists
            if (file_exists($file['full_path'])) {
                unlink($file['full_path']);
            }

            $db->commit();

            AuditService::log($adminId, 'DELETE_FILE', 'FILE', $file['upload_record_id'], [
                'filename' => $file['original_file_name'],
                'device_id' => $file['device_id']
            ]);

            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
