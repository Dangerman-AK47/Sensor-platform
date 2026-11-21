<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DeviceService;
use App\Services\ConfigService;
use App\Services\StorageService;
use App\Services\AuditService;
use App\Utils\Response;
use App\Utils\DeviceIdValidator;
use Exception;

class SensorApiController
{
    private static function getAuthHeader(): ?string
    {
        return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    }

    private static function getDeviceIdHeader(): ?string
    {
        return $_SERVER['HTTP_X_SENSOR_ID'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
    }

    private static function requireSensorAuth(): array
    {
        $authHeader = self::getAuthHeader();
        // Fallback to query param for file download GET requests
        if (empty($authHeader) && !empty($_GET['token'])) {
            $authHeader = 'Bearer ' . trim($_GET['token']);
        }

        $deviceIdHeader = self::getDeviceIdHeader() ?? ($_GET['deviceId'] ?? null);
        $device = AuthService::authenticateSensor($authHeader, $deviceIdHeader);

        if (!$device) {
            Response::error('Unauthorized: Invalid or missing sensor Bearer token', 401);
        }

        if ($device['status'] !== 'ACTIVE') {
            Response::error('Forbidden: Sensor is deactivated', 403, [
                'result' => 'SENSOR_DEACTIVATED'
            ]);
        }

        return $device;
    }

    public static function register(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Invalid JSON payload', 400);
        }

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $result = DeviceService::submitRegistration($input, $ip);

            if ($result['status'] === 'CONFLICT') {
                Response::error($result['message'], 409);
            }

            Response::json($result, 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public static function heartbeat(): void
    {
        $device = self::requireSensorAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $sensorVersion = !empty($input['sensorVersion']) ? trim((string)$input['sensorVersion']) : null;
        $firmwareVersion = !empty($input['firmwareVersion']) ? trim((string)$input['firmwareVersion']) : null;
        $hardwareModel = !empty($input['hardwareModel']) ? trim((string)$input['hardwareModel']) : null;
        $metadata = !empty($input['metadata']) && is_array($input['metadata']) ? json_encode($input['metadata']) : null;

        DeviceService::recordHeartbeat($device['id'], $sensorVersion, $firmwareVersion, $hardwareModel, $metadata);

        Response::success([
            'ok' => true,
            'deviceId' => $device['device_id'],
            'sensorStatus' => $device['status'],
            'serverTime' => date('c'),
            'timestamp' => date('c')
        ]);
    }

    public static function versionCheck(): void
    {
        $device = self::requireSensorAuth();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || (empty($input['currentVersion']) && empty($input['currentConfigVersion']))) {
            Response::error('Missing currentVersion in request body', 400);
        }

        $reported = trim((string)($input['currentVersion'] ?? $input['currentConfigVersion'] ?? ''));
        if (!empty($reported) && $reported !== '0.0.0') {
            DeviceService::updateSensorVersion($device['id'], $reported);
        }

        $result = ConfigService::checkVersion($device['device_id'], $reported ?: '0.0.0');

        Response::json($result);
    }

    public static function downloadConfiguration(): void
    {
        $device = self::requireSensorAuth();
        $activeConfig = ConfigService::getActiveConfig();

        if (!$activeConfig) {
            Response::error('No active configuration available for download', 404);
        }

        $filePath = dirname(__DIR__, 2) . '/storage/' . $activeConfig['storage_key'];
        if (!file_exists($filePath)) {
            Response::error('Active configuration storage file is missing', 500);
        }

        // Immediately update sensor_version in database upon configuration download!
        DeviceService::updateSensorVersion($device['id'], $activeConfig['config_version']);

        // Log immutable download audit record
        AuditService::logConfigDownload($activeConfig['id'], $device['device_id'], $activeConfig['config_version']);

        header("X-Config-Version: {$activeConfig['config_version']}");
        header("Access-Control-Expose-Headers: X-Config-Version");

        Response::file($filePath, $activeConfig['original_file_name'], $activeConfig['mime_type']);
    }

    public static function upload(): void
    {
        $device = self::requireSensorAuth();

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['file']['error'] ?? 'No file provided';
            Response::error("File upload failed or missing (code: {$err})", 400);
        }

        // Update sensor version if provided in header
        $sensorVersion = $_SERVER['HTTP_X_SENSOR_VERSION'] ?? null;
        if (!empty($sensorVersion) && $sensorVersion !== '0.0.0') {
            DeviceService::updateSensorVersion($device['id'], trim($sensorVersion));
        }

        try {
            $result = StorageService::saveUploadedFile($_FILES['file'], $device);
            Response::json(array_merge(['success' => true], $result), 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
