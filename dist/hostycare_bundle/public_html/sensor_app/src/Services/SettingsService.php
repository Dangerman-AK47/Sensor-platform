<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use RuntimeException;
use PDO;

class SettingsService
{
    public static function getSettings(): array
    {
        AuthService::ensurePortalUserTable();
        $db = Database::getConnection();

        // Get primary admin
        $adminStmt = $db->query("SELECT id, username, role, is_active, last_login_at FROM fv_admin_accounts WHERE role = 'SUPER_ADMIN' ORDER BY created_at ASC LIMIT 1");
        $admin = $adminStmt->fetch();
        if (!$admin) {
            $adminStmt = $db->query("SELECT id, username, role, is_active, last_login_at FROM fv_admin_accounts ORDER BY created_at ASC LIMIT 1");
            $admin = $adminStmt->fetch();
        }

        // Get primary portal user
        $userStmt = $db->query("SELECT id, username, role, is_active, last_login_at, updated_at FROM fv_portal_users ORDER BY created_at ASC LIMIT 1");
        $user = $userStmt->fetch();

        return [
            'admin' => [
                'id' => $admin['id'] ?? 'admin-root-0001',
                'username' => $admin['username'] ?? 'admin',
                'role' => $admin['role'] ?? 'SUPER_ADMIN',
                'lastLogin' => $admin['last_login_at'] ?? null
            ],
            'portalUser' => [
                'id' => $user['id'] ?? 'user-portal-0001',
                'username' => $user['username'] ?? 'user',
                'role' => $user['role'] ?? 'USER',
                'updatedAt' => $user['updated_at'] ?? null
            ]
        ];
    }

    public static function updateAdminCredentials(string $adminId, string $newUsername, ?string $newPassword = null): array
    {
        $newUsername = trim($newUsername);
        if (strlen($newUsername) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $newUsername)) {
            throw new RuntimeException("Invalid admin username. Must be at least 3 alphanumeric characters.");
        }

        $db = Database::getConnection();

        // Check if username taken by another admin
        $check = $db->prepare("SELECT id FROM fv_admin_accounts WHERE username = :u AND id != :id LIMIT 1");
        $check->execute(['u' => $newUsername, 'id' => $adminId]);
        if ($check->fetch()) {
            throw new RuntimeException("Username '{$newUsername}' is already taken by another administrator.");
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 4) {
                throw new RuntimeException("Admin password must be at least 4 characters.");
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE fv_admin_accounts SET username = :u, password_hash = :p, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['u' => $newUsername, 'p' => $hash, 'id' => $adminId]);
        } else {
            $stmt = $db->prepare("UPDATE fv_admin_accounts SET username = :u, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['u' => $newUsername, 'id' => $adminId]);
        }

        // Update active session username if matching
        AuthService::startSession();
        if (!empty($_SESSION['admin_id']) && $_SESSION['admin_id'] === $adminId) {
            $_SESSION['username'] = $newUsername;
        }

        AuditService::log($adminId, 'UPDATE_ADMIN_CREDENTIALS', 'ADMIN_ACCOUNT', $adminId, [
            'username' => $newUsername,
            'password_updated' => !empty($newPassword)
        ]);

        return [
            'id' => $adminId,
            'username' => $newUsername,
            'passwordUpdated' => !empty($newPassword)
        ];
    }

    public static function updatePortalUserCredentials(string $newUsername, ?string $newPassword = null, ?string $adminId = null): array
    {
        AuthService::ensurePortalUserTable();
        $newUsername = trim($newUsername);
        if (strlen($newUsername) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $newUsername)) {
            throw new RuntimeException("Invalid user username. Must be at least 3 alphanumeric characters.");
        }

        $db = Database::getConnection();

        // Get primary portal user id
        $stmt = $db->query("SELECT id FROM fv_portal_users ORDER BY created_at ASC LIMIT 1");
        $userId = $stmt->fetchColumn() ?: 'user-portal-0001';

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 4) {
                throw new RuntimeException("User password must be at least 4 characters.");
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $db->prepare("
                INSERT INTO fv_portal_users (id, username, password_hash, role, is_active, created_at, updated_at)
                VALUES (:id, :u, :p, 'USER', 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE username = :u2, password_hash = :p2, is_active = 1, updated_at = NOW()
            ");
            $upd->execute([
                'id' => $userId,
                'u' => $newUsername,
                'p' => $hash,
                'u2' => $newUsername,
                'p2' => $hash
            ]);
        } else {
            $upd = $db->prepare("
                UPDATE fv_portal_users SET username = :u, updated_at = NOW() WHERE id = :id
            ");
            $upd->execute(['u' => $newUsername, 'id' => $userId]);
        }

        // Invalidate or update active portal user session if currently logged in
        AuthService::startSession();
        if (!empty($_SESSION['portal_user_id']) && $_SESSION['portal_user_id'] === $userId) {
            $_SESSION['portal_username'] = $newUsername;
        }

        AuditService::log($adminId, 'UPDATE_USER_CREDENTIALS', 'PORTAL_USER', $userId, [
            'username' => $newUsername,
            'password_updated' => !empty($newPassword)
        ]);

        return [
            'id' => $userId,
            'username' => $newUsername,
            'passwordUpdated' => !empty($newPassword)
        ];
    }
}
