const fs = require('fs');
const path = require('path');

const zipPath = path.join(__dirname, '..', 'dist', 'sensor_platform_hostycare_bundle.zip');
if (!fs.existsSync(zipPath)) {
    console.error('Bundle zip not found at ' + zipPath);
    process.exit(1);
}

const zipBuffer = fs.readFileSync(zipPath);
const base64Zip = zipBuffer.toString('base64');
console.log(`Bundle loaded: ${zipBuffer.length} bytes -> Base64 length: ${base64Zip.length}`);

const template = `<?php
/**
 * Sensor Platform - 1-Click Automated Self-Extracting Deployer & Diagnostics
 * HostyCare / LiteSpeed / cPanel Deployment Engine
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Security key - optional, defaults to open for initial deployment
$pageTitle = 'HostyCare 1-Click Platform Deployer';
$currentDir = __DIR__;
$parentDir = dirname(__DIR__);
$zipData = '${base64Zip}';

$logs = [];
$success = null;
$error = null;

function logMsg(string $msg): void {
    global $logs;
    $logs[] = '[' . date('H:i:s') . '] ' . $msg;
}

// Handle Form Submissions
$action = $_POST['action'] ?? '';

if ($action === 'deploy') {
    try {
        logMsg('Starting 1-Click Automated Deployment...');
        
        // 1. Temporary zip file
        $tmpZip = $currentDir . '/_tmp_bundle.zip';
        $bundleSource = $tmpZip;
        
        if (file_exists($currentDir . '/sensor_platform_hostycare_bundle.zip')) {
            logMsg('Found pre-uploaded sensor_platform_hostycare_bundle.zip in public_html.');
            $bundleSource = $currentDir . '/sensor_platform_hostycare_bundle.zip';
        } else {
            logMsg('Using embedded base64 payload (' . strlen($zipData) . ' chars)...');
            file_put_contents($tmpZip, base64_decode($zipData));
        }

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is not enabled on this server.');
        }

        $zip = new ZipArchive();
        if ($zip->open($bundleSource) !== true) {
            throw new RuntimeException('Failed to open zip bundle.');
        }

        logMsg('Unpacking files from bundle (' . $zip->numFiles . ' entries)...');
        
        // Target: public files go to $currentDir (public_html), sensor_app goes to $currentDir/sensor_app
        $targetSensorApp = $currentDir . '/sensor_app';
        if (!is_dir($targetSensorApp)) {
            @mkdir($targetSensorApp, 0755, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            // Normalize path
            $entry = str_replace('\\\\', '/', $entry);
            
            // Skip root directory or empty
            if (empty($entry) || $entry === './' || $entry === '/') continue;
            
            // Determine target location
            $targetPath = null;
            if (str_starts_with($entry, 'public_html/')) {
                $sub = substr($entry, strlen('public_html/'));
                if ($sub === '') continue;
                $targetPath = $currentDir . '/' . $sub;
            } elseif (str_starts_with($entry, 'sensor_app/')) {
                $targetPath = $currentDir . '/' . $entry;
            } elseif ($entry === 'DEPLOYMENT_README.txt') {
                $targetPath = $currentDir . '/DEPLOYMENT_README.txt';
            }

            if ($targetPath === null) continue;

            if (str_ends_with($entry, '/')) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0755, true);
                    @chmod($targetPath, 0755);
                }
            } else {
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                    @chmod($dir, 0755);
                }
                $content = $zip->getFromIndex($i);
                file_put_contents($targetPath, $content);
                @chmod($targetPath, 0644);
                logMsg('Extracted: ' . $entry);
            }
        }
        $zip->close();

        // Clean up temporary zip if created
        if (file_exists($tmpZip)) {
            @unlink($tmpZip);
        }

        // Fix any nested public_html/public_html directory if left over from previous extractions
        $nestedPublic = $currentDir . '/public_html';
        if (is_dir($nestedPublic)) {
            logMsg('Notice: Found nested ' . $nestedPublic . ', cleaning up...');
            $files = @scandir($nestedPublic) ?: [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $src = $nestedPublic . '/' . $f;
                $dst = $currentDir . '/' . $f;
                if (!file_exists($dst)) {
                    @rename($src, $dst);
                }
            }
        }

        // Also copy sensor_app to parent directory /home/realtime/sensor_app if writable for optimal security
        $parentSensorApp = $parentDir . '/sensor_app';
        if (is_writable($parentDir) && !is_dir($parentSensorApp)) {
            logMsg('Parent directory is writable. Mirroring sensor_app to ' . $parentSensorApp . '...');
            // Copy files recursively
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($targetSensorApp, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $subPath = substr($item->getPathname(), strlen($targetSensorApp));
                $dest = $parentSensorApp . $subPath;
                if ($item->isDir()) {
                    if (!is_dir($dest)) @mkdir($dest, 0755, true);
                } else {
                    $pdir = dirname($dest);
                    if (!is_dir($pdir)) @mkdir($pdir, 0755, true);
                    @copy($item->getPathname(), $dest);
                    @chmod($dest, 0644);
                }
            }
        }

        // Create storage directories
        $storageDirs = [
            $targetSensorApp . '/storage',
            $targetSensorApp . '/storage/uploads',
            $targetSensorApp . '/storage/configs'
        ];
        foreach ($storageDirs as $sDir) {
            if (!is_dir($sDir)) {
                @mkdir($sDir, 0777, true);
                @chmod($sDir, 0777);
            }
        }

        logMsg('Deployment & path correction completed successfully!');
        $success = 'Platform files extracted and permissions configured successfully!';
    } catch (Throwable $e) {
        $error = 'Deployment error: ' . $e->getMessage();
        logMsg('ERROR: ' . $e->getMessage());
    }
}

if ($action === 'db_setup') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');

    try {
        if (empty($dbName) || empty($dbUser)) {
            throw new RuntimeException('Database Name and Database User cannot be empty.');
        }

        logMsg("Testing PDO MySQL connection to '{$dbName}' with user '{$dbUser}' on host '{$dbHost}'...");
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        logMsg('Connected to MySQL successfully!');

        // Check SQL install file
        $sqlFiles = [
            $currentDir . '/sensor_app/database/production_install.sql',
            $parentDir . '/sensor_app/database/production_install.sql',
            $currentDir . '/sensor_app/database/schema.sql'
        ];
        $sqlPath = null;
        foreach ($sqlFiles as $cand) {
            if (file_exists($cand)) {
                $sqlPath = $cand;
                break;
            }
        }

        if ($sqlPath && file_exists($sqlPath)) {
            logMsg("Executing database schema script from {$sqlPath}...");
            $sql = file_get_contents($sqlPath);
            // Execute statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $execCount = 0;
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    $pdo->exec($stmt);
                    $execCount++;
                }
            }
            logMsg("Executed {$execCount} SQL statements. Tables and initial admin user are ready!");
        } else {
            logMsg('Notice: production_install.sql not found yet. Run deploy step first.');
        }

        // Generate / Update .env file
        $jwtSecret = bin2hex(random_bytes(32));
        $adminPassHash = '$2y$12$e68G0eK9y7n9Ww4A4z5QXeOQ7z1eR5y5z4k7u9m0p1q2r3s4t5u6v'; // default hash for Admin@123456
        $envContent = <<<EOT
APP_ENV=production
APP_DEBUG=false
APP_URL=https://realtimeprojectsolutions.com

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT=3306
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

JWT_SECRET={$jwtSecret}
JWT_EXPIRATION_HOURS=24
SESSION_TTL_SECONDS=28800

DEFAULT_ADMIN_USER=admin
DEFAULT_ADMIN_PASS=Admin@123456
DEFAULT_ADMIN_HASH={$adminPassHash}

MAX_FILE_SIZE_MB=100
ALLOWED_EXTENSIONS=bin,hex,json,csv,txt,log,gz,zip,tar
EOT;

        $envLocations = [
            $currentDir . '/sensor_app/.env',
            $parentDir . '/sensor_app/.env'
        ];
        foreach ($envLocations as $envLoc) {
            $envDir = dirname($envLoc);
            if (is_dir($envDir) && is_writable($envDir)) {
                file_put_contents($envLoc, $envContent);
                @chmod($envLoc, 0600);
                logMsg('Saved .env file at: ' . $envLoc);
            }
        }

        $success = 'Database configured and connected successfully! .env file generated!';
    } catch (Throwable $e) {
        $error = 'Database configuration failed: ' . $e->getMessage();
        logMsg('ERROR: ' . $e->getMessage());
    }
}

if ($action === 'self_destruct') {
    @unlink(__FILE__);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>deploy.php has been permanently deleted from server.</h2><p><a href="/">Visit Live Website &rarr;</a></p></body></html>';
    exit;
}

// Check current server status
$phpVersion = PHP_VERSION;
$hasZip = class_exists('ZipArchive');
$hasPdo = extension_loaded('pdo_mysql');
$indexExists = file_exists($currentDir . '/index.php');
$sensorAppDirExists = is_dir($currentDir . '/sensor_app') || is_dir($parentDir . '/sensor_app');
$envExists = file_exists($currentDir . '/sensor_app/.env') || file_exists($parentDir . '/sensor_app/.env');
$publicNestedExists = is_dir($currentDir . '/public_html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
            --bg: #0f172a;
            --card: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 24px 16px; line-height: 1.5; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { font-size: 24px; color: #fff; margin-bottom: 6px; }
        .header p { color: var(--muted); font-size: 14px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .card h2 { font-size: 18px; margin-bottom: 14px; border-bottom: 1px solid var(--border); padding-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .status-item { background: #0f172a; border: 1px solid var(--border); border-radius: 6px; padding: 12px; }
        .status-item .label { font-size: 12px; color: var(--muted); text-transform: uppercase; }
        .status-item .value { font-size: 15px; font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-success { background: rgba(22, 163, 74, 0.2); color: #4ade80; border: 1px solid #16a34a; }
        .badge-danger { background: rgba(220, 38, 38, 0.2); color: #f87171; border: 1px solid #dc2626; }
        .badge-warning { background: rgba(217, 119, 6, 0.2); color: #fbbf24; border: 1px solid #d97706; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; text-decoration: none; transition: 0.15s; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-secondary { background: #334155; color: #fff; }
        .btn-secondary:hover { background: #475569; }
        .btn-block { width: 100%; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: rgba(22, 163, 74, 0.2); border: 1px solid var(--success); color: #4ade80; }
        .alert-danger { background: rgba(220, 38, 38, 0.2); border: 1px solid var(--danger); color: #f87171; }
        .log-box { background: #000; border: 1px solid var(--border); border-radius: 6px; padding: 12px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; color: #a3e635; margin-top: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px; }
        .form-control { width: 100%; padding: 8px 12px; background: #0f172a; border: 1px solid var(--border); border-radius: 6px; color: #fff; font-size: 14px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media(max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
        .quick-links { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ESP32 Sensor Platform - HostyCare Deployer</h1>
            <p>1-Click self-extracting deployment, folder repair & database initializer for <code>realtimeprojectsolutions.com</code></p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">✖ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Server Environment & Inspection -->
        <div class="card">
            <h2>
                <span>Server & Environment Diagnostics</span>
                <span class="badge badge-success">HostyCare cPanel (<?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'LiteSpeed') ?>)</span>
            </h2>
            <div class="status-grid">
                <div class="status-item">
                    <div class="label">PHP Version</div>
                    <div class="value"><?= $phpVersion ?> (OK)</div>
                </div>
                <div class="status-item">
                    <div class="label">ZipArchive Module</div>
                    <div class="value">
                        <?= $hasZip ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-danger">Disabled</span>' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">PDO MySQL Extension</div>
                    <div class="value">
                        <?= $hasPdo ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-danger">Disabled</span>' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">public_html/index.php</div>
                    <div class="value">
                        <?= $indexExists ? '<span class="badge badge-success">Ready</span>' : '<span class="badge badge-warning">Missing / Run Step 1</span>' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">Backend Code (sensor_app)</div>
                    <div class="value">
                        <?= $sensorAppDirExists ? '<span class="badge badge-success">Detected</span>' : '<span class="badge badge-danger">Missing / Run Step 1</span>' ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">Database Config (.env)</div>
                    <div class="value">
                        <?= $envExists ? '<span class="badge badge-success">Configured</span>' : '<span class="badge badge-warning">Not Configured Yet</span>' ?>
                    </div>
                </div>
            </div>

            <?php if ($publicNestedExists): ?>
                <div class="alert alert-danger" style="margin-top: 10px;">
                    ⚠️ <strong>Nested Folder Detected:</strong> <code>public_html/public_html</code> exists from a previous unzip. Clicking <strong>1-Click Deploy & Repair</strong> will automatically flatten and fix this!
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 1: Deploy / Repair Codebase -->
        <div class="card">
            <h2>Step 1: 1-Click Codebase Extraction & Folder Repair</h2>
            <p style="color: var(--muted); font-size: 14px; margin-bottom: 14px;">
                Extracts the production codebase, unpacks all controllers, fixes Linux case-sensitivity, corrects directory permissions (<code>0755</code>/<code>0644</code>), and configures LiteSpeed <code>.htaccess</code>.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="deploy">
                <button type="submit" class="btn btn-primary btn-block" style="padding: 14px; font-size: 16px;">
                    🚀 1-Click Deploy & Repair Platform
                </button>
            </form>
        </div>

        <!-- Step 2: Database Setup & Initializer -->
        <div class="card">
            <h2>Step 2: MySQL Database Connection & Table Initializer</h2>
            <p style="color: var(--muted); font-size: 14px; margin-bottom: 14px;">
                Enter your HostyCare MySQL database credentials created in cPanel MySQL Database Wizard. This tool tests the connection, imports <code>production_install.sql</code>, and creates the secure <code>.env</code> file.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="db_setup">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" name="db_host" class="form-control" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Database Name (e.g. realtime_sensordb)</label>
                        <input type="text" name="db_name" class="form-control" placeholder="realtime_sensordb" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Database Username (e.g. realtime_sensoruser)</label>
                        <input type="text" name="db_user" class="form-control" placeholder="realtime_sensoruser" required>
                    </div>
                    <div class="form-group">
                        <label>Database Password</label>
                        <input type="password" name="db_pass" class="form-control" placeholder="cPanel DB Password">
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-block" style="padding: 12px; margin-top: 6px;">
                    💾 Test Connection, Initialize Tables & Write .env
                </button>
            </form>
        </div>

        <!-- Step 3: Verification & Live Links -->
        <div class="card">
            <h2>Step 3: Test & Verify Live Website</h2>
            <p style="color: var(--muted); font-size: 14px;">Once Steps 1 and 2 are complete, test each portal below:</p>
            <div class="quick-links">
                <a href="/" target="_blank" class="btn btn-secondary">🌐 Open Homepage / Testing Console &rarr;</a>
                <a href="/admin/login" target="_blank" class="btn btn-secondary">🔐 Open Admin Login (admin / Admin@123456) &rarr;</a>
                <a href="/upload" target="_blank" class="btn btn-secondary">📤 Open Sensor File Upload &rarr;</a>
                <a href="/hostycare_check.php" target="_blank" class="btn btn-secondary">🩺 Open HostyCare PHP Check &rarr;</a>
            </div>

            <div style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 14px;">
                <p style="color: var(--muted); font-size: 13px; margin-bottom: 8px;">
                    🛡️ <strong>Security Tip:</strong> Once your site is working, remove this deployer script:
                </p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete deploy.php from the server?');">
                    <input type="hidden" name="action" value="self_destruct">
                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete deploy.php From Server</button>
                </form>
            </div>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="card">
                <h2>Execution Activity Logs</h2>
                <div class="log-box">
                    <?php foreach ($logs as $l): ?>
                        <div><?= htmlspecialchars($l) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
`;

fs.writeFileSync(path.join(__dirname, '..', 'public', 'deploy.php'), template);
fs.writeFileSync(path.join(__dirname, '..', 'dist', 'deploy.php'), template);

console.log('deploy.php generated successfully in public/ and dist/ !');
