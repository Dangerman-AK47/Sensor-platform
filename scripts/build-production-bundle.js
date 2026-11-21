/**
 * Automated Production Packaging Tool for HostyCare cPanel Deployment
 * Builds clean, production-ready directory structure and generates ZIP bundle.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.join(ROOT_DIR, 'dist');
const PKG_DIR = path.join(DIST_DIR, 'hostycare_bundle');

console.log('====================================================');
console.log('📦 Building HostyCare Production Deployment Bundle');
console.log('====================================================\n');

// Clean dist directory
if (fs.existsSync(DIST_DIR)) {
    fs.rmSync(DIST_DIR, { recursive: true, force: true });
}
fs.mkdirSync(PKG_DIR, { recursive: true });

// Helper to copy recursively
function copyDir(src, dest, ignore = []) {
    if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
    const entries = fs.readdirSync(src, { withFileTypes: true });

    for (const entry of entries) {
        if (ignore.includes(entry.name)) continue;
        const srcPath = path.join(src, entry.name);
        const destPath = path.join(dest, entry.name);

        if (entry.isDirectory()) {
            copyDir(srcPath, destPath, ignore);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    }
}

// 1. Structure: Option 1 - Recommended cPanel Split Structure
// public_html -> goes directly into cPanel public_html/
// sensor_app  -> goes into cPanel home directory /home/username/sensor_app/
const appDir = path.join(PKG_DIR, 'sensor_app');
const webDir = path.join(PKG_DIR, 'public_html');

fs.mkdirSync(appDir, { recursive: true });
fs.mkdirSync(webDir, { recursive: true });

console.log('1. Copying backend modules into sensor_app/...');
copyDir(path.join(ROOT_DIR, 'config'), path.join(appDir, 'config'));
copyDir(path.join(ROOT_DIR, 'src'), path.join(appDir, 'src'));
copyDir(path.join(ROOT_DIR, 'views'), path.join(appDir, 'views'));
copyDir(path.join(ROOT_DIR, 'database'), path.join(appDir, 'database'));

// Empty clean storage directory with subfolders
const storageTarget = path.join(appDir, 'storage');
fs.mkdirSync(path.join(storageTarget, 'uploads'), { recursive: true });
fs.mkdirSync(path.join(storageTarget, 'configs', '2026', '10'), { recursive: true });

// Copy baseline sensor config
const baselineCfg = {
    version: '1.1.0',
    telemetry_interval_sec: 30,
    sampling_rate_hz: 10,
    features: { sleep_mode: false, high_precision: true }
};
fs.writeFileSync(path.join(storageTarget, 'configs', '2026', '10', 'sensor_config_v1.1.0.json'), JSON.stringify(baselineCfg, null, 2));

// Copy storage/.htaccess
if (fs.existsSync(path.join(ROOT_DIR, 'storage', '.htaccess'))) {
    fs.copyFileSync(path.join(ROOT_DIR, 'storage', '.htaccess'), path.join(storageTarget, '.htaccess'));
}

// Copy .env template
fs.copyFileSync(path.join(ROOT_DIR, '.env.production.template'), path.join(appDir, '.env.production.template'));

console.log('2. Copying web assets and front-controller into public_html/...');
copyDir(path.join(ROOT_DIR, 'public', 'assets'), path.join(webDir, 'assets'));
fs.copyFileSync(path.join(ROOT_DIR, 'public', '.htaccess'), path.join(webDir, '.htaccess'));

// Adapt public_html/index.php to detect split or single root automatically
const indexPhpSource = fs.readFileSync(path.join(ROOT_DIR, 'public', 'index.php'), 'utf8');

// Replace $baseDir = dirname(__DIR__); with flexible locator
const flexibleLocator = `
    // Detect whether backend is in split directory (../sensor_app) or same directory (..)
    if (file_exists(dirname(__DIR__) . '/sensor_app/src')) {
        $baseDir = dirname(__DIR__) . '/sensor_app';
    } else {
        $baseDir = dirname(__DIR__);
    }
`;

const adaptedIndex = indexPhpSource.replace(
    /\$baseDir = dirname\(__DIR__\);/,
    flexibleLocator.trim()
);
fs.writeFileSync(path.join(webDir, 'index.php'), adaptedIndex);

// Add quick diagnostic health-check page in public_html for cPanel testing
const healthCheckScript = `<?php
header('Content-Type: application/json');
$checks = [
    'php_version' => PHP_VERSION,
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];
$allOk = $checks['pdo_mysql'] && $checks['json'] && $checks['mbstring'] && $checks['openssl'];
echo json_encode([
    'status' => $allOk ? 'READY_FOR_SENSOR_PLATFORM' : 'MISSING_EXTENSIONS',
    'checks' => $checks
], JSON_PRETTY_PRINT);
`;
fs.writeFileSync(path.join(webDir, 'hostycare_check.php'), healthCheckScript);

// Add quick installation README in the bundle
const bundleReadme = `# HostyCare ₹239/month Business Plan Deployment Bundle

This bundle contains the production files for your ESP32 Sensor Platform.

## Option A: Recommended Split Deployment (Highest Security)
1. In cPanel File Manager:
   - Upload the \`sensor_app\` folder to your cPanel HOME directory:
     /home/YOUR_CPANEL_USER/sensor_app
   - Rename \`sensor_app/.env.production.template\` to \`sensor_app/.env\` and enter your cPanel MySQL details.
2. Upload the contents of \`public_html\` directly into:
   - /home/YOUR_CPANEL_USER/public_html/
3. Import \`sensor_app/database/production_install.sql\` into your database via phpMyAdmin.

## Option B: Single public_html Deployment (Simplest)
If you prefer everything in public_html:
1. Upload both \`sensor_app\` and the contents of \`public_html\` into:
   /home/YOUR_CPANEL_USER/public_html/
2. The index.php automatically detects and binds to \`sensor_app\`!
3. The .htaccess files inside \`sensor_app/storage\`, \`sensor_app/config\`, and \`sensor_app/database\` automatically block unauthorized web access.

Visit https://yourdomain.com/hostycare_check.php to verify server environment.
Delete \`hostycare_check.php\` after verification.
`;
fs.writeFileSync(path.join(PKG_DIR, 'DEPLOYMENT_README.txt'), bundleReadme);

console.log('3. Creating production ZIP bundle using bsdtar (POSIX forward-slashes)...');
const zipOutput = path.join(DIST_DIR, 'sensor_platform_hostycare_bundle.zip');

try {
    const tarCmd = `tar.exe -a -c -f "${zipOutput}" -C "${PKG_DIR}" .`;
    execSync(tarCmd, { stdio: 'inherit' });
    console.log(`\n✅ Production bundle created successfully at:\n   ${zipOutput}`);
} catch (e) {
    console.warn('Tar creation failed:', e.message);
}

console.log('\nBundle contents:');
console.log(' - dist/hostycare_bundle/public_html/ (web root)');
console.log(' - dist/hostycare_bundle/sensor_app/  (private backend)');
console.log(' - dist/sensor_platform_hostycare_bundle.zip (ready for 1-click upload)');
