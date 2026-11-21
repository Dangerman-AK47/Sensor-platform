<?php
declare(strict_types=1);

/**
 * Sensor Platform / FileVault / DeviceVault v3
 * Front Controller & Routing Dispatcher
 */

// Error reporting for setup
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Dynamic Auto-discovery of the backend directory
static $resolvedBase = null;
if ($resolvedBase === null) {
    $searchRoots = [
        __DIR__ . '/sensor_app',
        dirname(__DIR__) . '/sensor_app',
        __DIR__,
        dirname(__DIR__),
        dirname(__DIR__) . '/public_html/sensor_app',
        __DIR__ . '/public_html/sensor_app'
    ];

    foreach ($searchRoots as $root) {
        if (file_exists($root . '/src/Controllers/PublicViewController.php')) {
            $resolvedBase = $root;
            break;
        }
    }

    // Deep search across 1st and 2nd level subdirectories
    if ($resolvedBase === null) {
        foreach ([__DIR__, dirname(__DIR__)] as $scanRoot) {
            $dirs = @glob($scanRoot . '/*', GLOB_ONLYDIR) ?: [];
            foreach ($dirs as $d) {
                if (file_exists($d . '/src/Controllers/PublicViewController.php')) {
                    $resolvedBase = $d;
                    break 2;
                }
                $nested = @glob($d . '/*', GLOB_ONLYDIR) ?: [];
                foreach ($nested as $nd) {
                    if (file_exists($nd . '/src/Controllers/PublicViewController.php')) {
                        $resolvedBase = $nd;
                        break 2;
                    }
                }
            }
        }
    }
}

if ($resolvedBase === null) {
    http_response_code(500);
    echo '<div style="font-family:sans-serif; max-width:800px; margin:30px auto; padding:24px; border:2px solid #ef4444; border-radius:8px; background:#fff;">';
    echo '<h2 style="color:#ef4444; margin-top:0;">Folder Inspector Diagnostic</h2>';
    echo '<p>The autoloader searched for <code>src/Controllers/PublicViewController.php</code>. Here is what exists on your server right now:</p>';

    $home = dirname(__DIR__);
    echo "<h3>1. In your home directory (<code>$home</code>):</h3>";
    $homeItems = @scandir($home) ?: [];
    $foundHomeSensor = false;
    foreach ($homeItems as $item) {
        if ($item === '.' || $item === '..') continue;
        if (stripos($item, 'sensor') !== false) {
            $foundHomeSensor = true;
            echo "<b>Found:</b> <code>$home/$item</code><br>";
            if (is_dir("$home/$item")) {
                echo "<pre style='background:#f8fafc; padding:8px; border:1px solid #e2e8f0; border-radius:4px;'>Subfolders: " . htmlspecialchars(print_r(scandir("$home/$item"), true)) . "</pre>";
            }
        }
    }
    if (!$foundHomeSensor) echo "<p style='color:#64748b;'>No folder with 'sensor' in its name found in $home.</p>";

    echo "<h3>2. In public_html (<code>" . __DIR__ . "</code>):</h3>";
    $curItems = @scandir(__DIR__) ?: [];
    $foundCurSensor = false;
    foreach ($curItems as $item) {
        if ($item === '.' || $item === '..') continue;
        if (stripos($item, 'sensor') !== false) {
            $foundCurSensor = true;
            echo "<b>Found:</b> <code>" . __DIR__ . "/$item</code><br>";
            if (is_dir(__DIR__ . "/$item")) {
                echo "<pre style='background:#f8fafc; padding:8px; border:1px solid #e2e8f0; border-radius:4px;'>Subfolders: " . htmlspecialchars(print_r(scandir(__DIR__ . "/$item"), true)) . "</pre>";
            }
        }
    }
    if (!$foundCurSensor) echo "<p style='color:#64748b;'>No folder with 'sensor' in its name found in " . __DIR__ . ".</p>";

    echo '</div>';
    exit;
}

$baseDir = $resolvedBase;

// Autoloader for App\* namespace
spl_autoload_register(function ($class) use ($baseDir) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    if (str_starts_with($relative, 'Config\\')) {
        $name = substr($relative, 7);
        $candidates = [
            $baseDir . '/config/' . $name . '.php',
            $baseDir . '/config/' . strtolower($name) . '.php',
            $baseDir . '/src/Config/' . $name . '.php',
            $baseDir . '/src/Config/' . strtolower($name) . '.php',
        ];
        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                require_once $cand;
                return;
            }
        }
    } else {
        $relPath = str_replace('\\', '/', $relative);
        $candidates = [
            $baseDir . '/src/' . $relPath . '.php',
            $baseDir . '/' . $relPath . '.php'
        ];
        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                require_once $cand;
                return;
            }
        }
    }
});

use App\Controllers\SensorApiController;
use App\Controllers\AdminApiController;
use App\Controllers\AdminViewController;
use App\Controllers\PublicViewController;
use App\Controllers\PublicApiController;
use App\Utils\Response;

// Parse request URL and Method
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Strip trailing slash except for root
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

// -------------------------------------------------------------
// STATIC ASSETS DISPATCHER (/assets/*)
// -------------------------------------------------------------
if (str_starts_with($path, '/assets/')) {
    $candidates = [
        __DIR__ . $path,
        __DIR__ . str_replace('/', '\\', $path),
        $baseDir . '/public_html' . $path,
        dirname(__DIR__) . '/public_html' . $path,
        dirname(__DIR__) . $path
    ];

    foreach ($candidates as $cand) {
        if (file_exists($cand) && !is_dir($cand)) {
            $ext = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
            $mime = match($ext) {
                'css' => 'text/css; charset=UTF-8',
                'js' => 'application/javascript; charset=UTF-8',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                default => 'application/octet-stream'
            };
            header("Content-Type: $mime");
            header("Cache-Control: public, max-age=604800");
            readfile($cand);
            exit;
        }
    }
}

// -------------------------------------------------------------
// SENSOR API ROUTES (/api/v1/sensors/*)
// -------------------------------------------------------------
if ($path === '/api/v1/sensors/register' && $method === 'POST') {
    SensorApiController::register();
}
if ($path === '/api/v1/sensors/heartbeat' && $method === 'POST') {
    SensorApiController::heartbeat();
}
if ($path === '/api/v1/sensors/version-check' && $method === 'POST') {
    SensorApiController::versionCheck();
}
if ($path === '/api/v1/sensors/configuration/download' && $method === 'GET') {
    SensorApiController::downloadConfiguration();
}
if ($path === '/api/v1/sensors/upload' && $method === 'POST') {
    SensorApiController::upload();
}

// -------------------------------------------------------------
// ADMIN API ROUTES (/api/admin/*)
// -------------------------------------------------------------
if ($path === '/api/admin/login' && $method === 'POST') {
    AdminApiController::login();
}
if ($path === '/api/admin/logout' && $method === 'POST') {
    AdminApiController::logout();
}
if ($path === '/api/admin/session' && $method === 'GET') {
    AdminApiController::session();
}
if ($path === '/api/admin/sensors/stats' && $method === 'GET') {
    AdminApiController::stats();
}
if ($path === '/api/admin/sensors' && $method === 'GET') {
    AdminApiController::listSensors();
}
if (preg_match('#^/api/admin/sensors/([a-zA-Z0-9_\-]+)$#', $path, $matches)) {
    if ($method === 'GET') {
        AdminApiController::getSensor($matches[1]);
    } elseif ($method === 'DELETE') {
        AdminApiController::deleteSensor($matches[1]);
    }
}
if (preg_match('#^/api/admin/sensors/([a-zA-Z0-9_\-]+)/status$#', $path, $matches) && ($method === 'PATCH' || $method === 'POST')) {
    AdminApiController::updateSensorStatus($matches[1]);
}
if (preg_match('#^/api/admin/sensors/([a-zA-Z0-9_\-]+)/token$#', $path, $matches) && $method === 'POST') {
    AdminApiController::regenerateSensorToken($matches[1]);
}
if ($path === '/api/admin/configuration' && $method === 'GET') {
    AdminApiController::listConfigurations();
}
if ($path === '/api/admin/configuration' && $method === 'POST') {
    AdminApiController::uploadConfiguration();
}
if (preg_match('#^/api/admin/configuration/([a-zA-Z0-9_\-]+)/activate$#', $path, $matches) && $method === 'POST') {
    AdminApiController::activateConfiguration($matches[1]);
}
if ($path === '/api/admin/files' && $method === 'GET') {
    AdminApiController::listFiles();
}
if (preg_match('#^/api/admin/files/([a-zA-Z0-9_\-]+)/download$#', $path, $matches) && $method === 'GET') {
    AdminApiController::downloadFile($matches[1]);
}
if (preg_match('#^/api/admin/files/([a-zA-Z0-9_\-]+)$#', $path, $matches) && ($method === 'DELETE' || $method === 'POST')) {
    AdminApiController::deleteFile($matches[1]);
}
if ($path === '/api/admin/history' && $method === 'GET') {
    AdminApiController::listHistory();
}
if (preg_match('#^/api/admin/history/([a-zA-Z0-9_\-]+)/approve$#', $path, $matches) && $method === 'POST') {
    AdminApiController::approveRequest($matches[1]);
}
if (preg_match('#^/api/admin/history/([a-zA-Z0-9_\-]+)/reject$#', $path, $matches) && $method === 'POST') {
    AdminApiController::rejectRequest($matches[1]);
}
if ($path === '/api/admin/version-check-metrics' && $method === 'GET') {
    AdminApiController::versionCheckMetrics();
}
if ($path === '/api/admin/audit' && $method === 'GET') {
    AdminApiController::auditLogs();
}
if ($path === '/api/device-requests' && $method === 'GET') {
    AdminApiController::publicPendingRequests();
}
if ($path === '/api/admin/settings' && $method === 'GET') {
    AdminApiController::getSettings();
}
if ($path === '/api/admin/settings/admin-credentials' && $method === 'POST') {
    AdminApiController::updateAdminCredentials();
}
if ($path === '/api/admin/settings/user-credentials' && $method === 'POST') {
    AdminApiController::updateUserCredentials();
}

// -------------------------------------------------------------
// USER PORTAL AUTH ROUTES (/api/user/*)
// -------------------------------------------------------------
if ($path === '/api/user/login' && $method === 'POST') {
    PublicApiController::login();
}
if ($path === '/api/user/logout' && $method === 'POST') {
    PublicApiController::logout();
}
if ($path === '/api/user/session' && $method === 'GET') {
    PublicApiController::session();
}

// -------------------------------------------------------------
// WEB VIEW ROUTES
// -------------------------------------------------------------
if ($path === '/' && $method === 'GET') {
    PublicViewController::root();
}
if ($path === '/upload' && $method === 'GET') {
    PublicViewController::upload();
}
if ($path === '/admin' && $method === 'GET') {
    header('Location: /admin/dashboard');
    exit;
}
if ($path === '/admin/login' && $method === 'GET') {
    AdminViewController::login();
}
if ($path === '/admin/dashboard' && $method === 'GET') {
    AdminViewController::dashboard();
}
if ($path === '/admin/sensors' && $method === 'GET') {
    AdminViewController::sensors();
}
if (preg_match('#^/admin/sensors/([a-zA-Z0-9_\-]+)$#', $path, $matches) && $method === 'GET') {
    AdminViewController::sensorDetail($matches[1]);
}
if ($path === '/admin/configs' && $method === 'GET') {
    AdminViewController::configs();
}
if ($path === '/admin/history' && $method === 'GET') {
    AdminViewController::history();
}
if ($path === '/admin/files' && $method === 'GET') {
    AdminViewController::files();
}
if ($path === '/admin/version-check-metrics' && $method === 'GET') {
    AdminViewController::metrics();
}
if ($path === '/admin/audit' && $method === 'GET') {
    AdminViewController::audit();
}
if ($path === '/admin/settings' && $method === 'GET') {
    AdminViewController::settings();
}

// 404 Not Found
if (str_starts_with($path, '/api/')) {
    Response::error("API endpoint not found: {$method} {$path}", 404);
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title><link rel="stylesheet" href="/assets/css/styles.css"></head><body class="bg-slate-50 min-h-screen flex items-center justify-center font-sans"><div class="max-w-md p-8 bg-white border border-slate-200 rounded-lg shadow-card text-center"><h1 class="text-2xl font-bold text-slate-900 mb-2">404 - Page Not Found</h1><p class="text-slate-500 mb-6">The requested path does not exist on this server.</p><a href="/admin/dashboard" class="btn btn-primary">Return to Dashboard</a></div></body></html>';
    exit;
}
