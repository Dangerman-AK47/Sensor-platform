<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DeviceService;
use App\Services\ConfigService;
use App\Services\StorageService;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Utils\Response;
use Exception;

class AdminApiController
{
    private static function requireAdmin(): array
    {
        $admin = AuthService::getAuthenticatedAdmin();
        if (!$admin) {
            Response::error('Unauthorized: Valid admin session required', 401);
        }
        return $admin;
    }

    public static function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            Response::error('Invalid credentials', 401);
        }

        $admin = AuthService::authenticateAdmin($username, $password);
        if (!$admin) {
            Response::error('Invalid credentials', 401);
        }

        AuditService::log($admin['id'], 'LOGIN', 'ADMIN_ACCOUNT', $admin['id']);

        Response::success([
            'admin' => $admin,
            'redirect' => '/admin/dashboard'
        ], 'Authenticated successfully');
    }

    public static function logout(): void
    {
        $admin = AuthService::getAuthenticatedAdmin();
        if ($admin) {
            AuditService::log($admin['id'], 'LOGOUT', 'ADMIN_ACCOUNT', $admin['id']);
        }
        AuthService::logout();
        Response::success(null, 'Logged out successfully');
    }

    public static function session(): void
    {
        $admin = self::requireAdmin();
        Response::success(['admin' => $admin]);
    }

    public static function stats(): void
    {
        self::requireAdmin();
        $stats = DeviceService::getStats();
        Response::success($stats);
    }

    public static function listSensors(): void
    {
        self::requireAdmin();
        $search = $_GET['search'] ?? null;
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['perPage'] ?? 20)));

        $data = DeviceService::listDevices($search, $status, $page, $perPage);
        Response::success($data);
    }

    public static function getSensor(string $id): void
    {
        self::requireAdmin();
        $device = DeviceService::getDevice($id);
        if (!$device) {
            Response::error('Device not found', 404);
        }
        Response::success(['device' => $device]);
    }

    public static function updateSensorStatus(string $id): void
    {
        $admin = self::requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $newStatus = trim($input['status'] ?? '');
        $reason = $input['reason'] ?? null;

        try {
            $updated = DeviceService::updateStatus($id, $newStatus, $admin['id'], $reason);
            if (!$updated) {
                Response::error('Device not found or status already current', 404);
            }
            Response::success(null, "Sensor status updated to {$newStatus}");
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function regenerateSensorToken(string $id): void
    {
        $admin = self::requireAdmin();
        try {
            $result = DeviceService::regenerateToken($id, $admin['id']);
            Response::success($result, 'New bearer token generated. Save it now; it will not be displayed again.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function deleteSensor(string $id): void
    {
        $admin = self::requireAdmin();
        try {
            $deleted = DeviceService::deleteDevice($id, $admin['id']);
            if (!$deleted) {
                Response::error('Device not found', 404);
            }
            Response::success(null, 'Sensor device and all associated telemetry records permanently deleted');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function listConfigurations(): void
    {
        self::requireAdmin();
        $configs = ConfigService::listConfigs();
        Response::success(['configurations' => $configs]);
    }

    public static function uploadConfiguration(): void
    {
        $admin = self::requireAdmin();
        $version = trim($_POST['version'] ?? '');

        if (empty($version)) {
            Response::error('Version string is required', 400);
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Configuration file upload missing or invalid', 400);
        }

        try {
            $result = ConfigService::uploadConfig($version, $_FILES['file'], $admin['id']);
            Response::success($result, 'Configuration uploaded successfully', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function activateConfiguration(string $id): void
    {
        $admin = self::requireAdmin();
        try {
            $config = ConfigService::activateConfig($id, $admin['id']);
            Response::success(['config' => $config], "Configuration {$config['config_version']} activated successfully");
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function listFiles(): void
    {
        self::requireAdmin();
        $search = $_GET['search'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['perPage'] ?? 20)));

        $data = StorageService::listFiles($search, $page, $perPage);
        Response::success($data);
    }

    public static function downloadFile(string $id): void
    {
        self::requireAdmin();
        $file = StorageService::getFile($id);
        if (!$file || !$file['exists']) {
            Response::error('File record or physical file missing', 404);
        }

        Response::file($file['full_path'], $file['original_file_name'], $file['mime_type']);
    }

    public static function deleteFile(string $id): void
    {
        $admin = self::requireAdmin();
        try {
            $deleted = StorageService::deleteFile($id, $admin['id']);
            if (!$deleted) {
                Response::error('File not found', 404);
            }
            Response::success(null, 'File deleted successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function listHistory(): void
    {
        self::requireAdmin();
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['perPage'] ?? 20)));

        $data = DeviceService::listRequests($status, $page, $perPage);
        Response::success($data);
    }

    public static function approveRequest(string $id): void
    {
        $admin = self::requireAdmin();
        try {
            $result = DeviceService::approveRequest($id, $admin['id']);
            Response::success($result, 'Registration request approved. Provide the bearer token to device now.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function rejectRequest(string $id): void
    {
        $admin = self::requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $reason = trim($input['reason'] ?? 'Rejected by administrator');

        try {
            $rejected = DeviceService::rejectRequest($id, $admin['id'], $reason);
            if (!$rejected) {
                Response::error('Request not found or not in pending state', 404);
            }
            Response::success(null, 'Registration request rejected');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function versionCheckMetrics(): void
    {
        self::requireAdmin();
        $metrics = AuditService::getVersionCheckMetrics();
        Response::success(['metrics' => $metrics]);
    }

    public static function auditLogs(): void
    {
        self::requireAdmin();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['perPage'] ?? 25)));

        $data = AuditService::listAuditLogs($page, $perPage);
        Response::success($data);
    }

    public static function publicPendingRequests(): void
    {
        $data = DeviceService::listRequests('PENDING', 1, 20);
        Response::success($data);
    }

    public static function getSettings(): void
    {
        self::requireAdmin();
        $settings = SettingsService::getSettings();
        Response::success($settings);
    }

    public static function updateAdminCredentials(): void
    {
        $admin = self::requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username)) {
            Response::error('Admin username cannot be empty', 400);
        }

        try {
            $res = SettingsService::updateAdminCredentials(
                $admin['id'],
                $username,
                !empty($password) ? $password : null
            );
            Response::success($res, 'Admin credentials updated successfully. Use these new credentials for next login.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function updateUserCredentials(): void
    {
        $admin = self::requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username)) {
            Response::error('User username cannot be empty', 400);
        }

        try {
            $res = SettingsService::updatePortalUserCredentials(
                $username,
                !empty($password) ? $password : null,
                $admin['id']
            );
            Response::success($res, 'User credentials updated successfully. Portal login will now require this new password.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
