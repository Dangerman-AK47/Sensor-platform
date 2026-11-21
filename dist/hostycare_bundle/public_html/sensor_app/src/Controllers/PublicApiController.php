<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Utils\Response;

class PublicApiController
{
    public static function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            Response::error('Username and password are required', 400);
        }

        $user = AuthService::authenticatePortalUser($username, $password);
        if (!$user) {
            Response::error('Invalid username or password', 401);
        }

        Response::success([
            'user' => $user,
            'message' => 'Portal authenticated successfully'
        ], 'Unlocked successfully');
    }

    public static function logout(): void
    {
        AuthService::logoutPortalUser();
        Response::success(null, 'Signed out of portal successfully');
    }

    public static function session(): void
    {
        $user = AuthService::getAuthenticatedPortalUser();
        if ($user) {
            Response::success([
                'authenticated' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username']
                ]
            ]);
        } else {
            Response::success([
                'authenticated' => false
            ]);
        }
    }
}
