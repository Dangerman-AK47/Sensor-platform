<?php
/**
 * Sensor Platform - Admin Password Initializer / Reset Tool
 * Run once to reset the admin password, then delete this file.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Locate base directory
$candidates = [
    __DIR__ . '/sensor_app',
    dirname(__DIR__) . '/sensor_app',
    __DIR__,
    dirname(__DIR__)
];

$baseDir = null;
foreach ($candidates as $cand) {
    if (file_exists($cand . '/config/database.php')) {
        $baseDir = $cand;
        break;
    }
}

if (!$baseDir) {
    die("Error: Could not locate sensor_app directory on server.");
}

// Load config and database
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/config/database.php';

use App\Config\Config;
use App\Config\Database;

Config::load();

$actionResult = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = trim($_POST['password'] ?? 'admin123');
    if (strlen($newPass) < 4) {
        $error = "Password must be at least 4 characters.";
    } else {
        try {
            $pdo = Database::getConnection();
            $hash = password_hash($newPass, PASSWORD_DEFAULT);

            // Ensure table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `fv_admin_accounts` (
                    `id` VARCHAR(36) NOT NULL,
                    `username` VARCHAR(50) NOT NULL,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `role` ENUM('SUPER_ADMIN', 'OPERATOR', 'VIEWER') NOT NULL DEFAULT 'SUPER_ADMIN',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `last_login_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_admin_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Insert or Update Admin
            $stmt = $pdo->prepare("
                INSERT INTO `fv_admin_accounts` (`id`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`)
                VALUES ('admin-root-0001', 'admin', :hash, 'SUPER_ADMIN', 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `password_hash` = :hash2, `is_active` = 1
            ");
            $stmt->execute(['hash' => $hash, 'hash2' => $hash]);

            $actionResult = "Admin password has been successfully set to: <strong>" . htmlspecialchars($newPass) . "</strong>";
        } catch (Throwable $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset — FileVault</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 30px; max-width: 440px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.4); text-align: center; }
        h1 { font-size: 20px; margin-bottom: 8px; color: #fff; }
        p { color: #94a3b8; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        .alert { padding: 12px 16px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: left; }
        .alert-success { background: rgba(22, 163, 74, 0.2); border: 1px solid #16a34a; color: #4ade80; }
        .alert-danger { background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626; color: #f87171; }
        .form-group { text-align: left; margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff; font-size: 15px; box-sizing: border-box; }
        .btn { display: inline-block; width: 100%; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; text-decoration: none; box-sizing: border-box; transition: 0.15s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #16a34a; color: #fff; margin-top: 10px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; background: #334155; font-size: 12px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size: 36px; margin-bottom: 12px;">🛡️</div>
        <h1>Reset Admin Password</h1>
        <p>Set a new password for the <code>admin</code> super-administrator account.</p>

        <?php if ($actionResult): ?>
            <div class="alert alert-success">
                <?= $actionResult ?>
            </div>
            <a href="/admin/login" class="btn btn-success">Go to Admin Login &rarr;</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="admin" readonly style="opacity: 0.7; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="text" name="password" value="admin123" required autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary">
                    Set Admin Password Now
                </button>
            </form>
        <?php endif; ?>

        <div style="margin-top: 24px; font-size: 12px; color: #64748b;">
            After logging in, delete <code>reset_admin.php</code> from <code>public_html</code>.
        </div>
    </div>
</body>
</html>
