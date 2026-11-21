<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DeviceService;
use App\Services\ConfigService;
use App\Services\StorageService;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Utils\View;

class AdminViewController
{
    private static function requireAuth(): array
    {
        $admin = AuthService::getAuthenticatedAdmin();
        if (!$admin) {
            header('Location: /admin/login');
            exit;
        }
        return $admin;
    }

    public static function login(): void
    {
        $admin = AuthService::getAuthenticatedAdmin();
        if ($admin) {
            header('Location: /admin/dashboard');
            exit;
        }
        View::render('admin.login', ['title' => 'Admin Login'], null);
    }

    public static function dashboard(): void
    {
        $admin = self::requireAuth();
        $stats = DeviceService::getStats();

        View::render('admin.dashboard', [
            'title' => 'Dashboard Overview',
            'admin' => $admin,
            'activeNav' => 'dashboard',
            'stats' => $stats
        ]);
    }

    public static function sensors(): void
    {
        $admin = self::requireAuth();
        $search = $_GET['search'] ?? null;
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $data = DeviceService::listDevices($search, $status, $page, 20);

        View::render('admin.sensors', [
            'title' => 'Sensors',
            'admin' => $admin,
            'activeNav' => 'sensors',
            'data' => $data,
            'search' => $search,
            'status' => $status
        ]);
    }

    public static function sensorDetail(string $id): void
    {
        $admin = self::requireAuth();
        $device = DeviceService::getDevice($id);

        if (!$device) {
            header('Location: /admin/sensors');
            exit;
        }

        View::render('admin.sensor-detail', [
            'title' => "Sensor {$device['device_id']}",
            'admin' => $admin,
            'activeNav' => 'sensors',
            'device' => $device
        ]);
    }

    public static function configs(): void
    {
        $admin = self::requireAuth();
        $configs = ConfigService::listConfigs();
        $activeConfig = ConfigService::getActiveConfig();

        View::render('admin.configs', [
            'title' => 'Configuration Settings',
            'admin' => $admin,
            'activeNav' => 'configs',
            'configs' => $configs,
            'activeConfig' => $activeConfig
        ]);
    }

    public static function codeFiles(): void
    {
        $admin = self::requireAuth();
        $codeFiles = \App\Services\CodeFileService::listCodeFiles();
        $activeCode = \App\Services\CodeFileService::getActiveCodeFile();

        View::render('admin.code-files', [
            'title' => 'Code Files',
            'admin' => $admin,
            'activeNav' => 'code_files',
            'codeFiles' => $codeFiles,
            'activeCode' => $activeCode
        ]);
    }

    public static function history(): void
    {
        $admin = self::requireAuth();
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $data = DeviceService::listRequests($status, $page, 20);

        View::render('admin.history', [
            'title' => 'Registration History',
            'admin' => $admin,
            'activeNav' => 'history',
            'data' => $data,
            'status' => $status
        ]);
    }

    public static function files(): void
    {
        $admin = self::requireAuth();
        $search = $_GET['search'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $data = StorageService::listFiles($search, $page, 20);

        View::render('admin.files', [
            'title' => 'Uploaded Files',
            'admin' => $admin,
            'activeNav' => 'files',
            'data' => $data,
            'search' => $search
        ]);
    }

    public static function metrics(): void
    {
        $admin = self::requireAuth();
        $metrics = AuditService::getVersionCheckMetrics();

        View::render('admin.metrics', [
            'title' => 'Version Check Metrics',
            'admin' => $admin,
            'activeNav' => 'metrics',
            'metrics' => $metrics
        ]);
    }

    public static function audit(): void
    {
        $admin = self::requireAuth();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = AuditService::listAuditLogs($page, 25);

        View::render('admin.audit', [
            'title' => 'Audit Logs',
            'admin' => $admin,
            'activeNav' => 'audit',
            'data' => $data
        ]);
    }

    public static function settings(): void
    {
        $admin = self::requireAuth();
        $settings = SettingsService::getSettings();

        View::render('admin.settings', [
            'title' => 'Settings',
            'admin' => $admin,
            'activeNav' => 'settings',
            'settings' => $settings
        ]);
    }
}
