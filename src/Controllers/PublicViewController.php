<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Utils\View;

class PublicViewController
{
    public static function root(): void
    {
        AuthService::ensurePortalUserTable();
        $isAuth = AuthService::isPortalUserAuthenticated();
        $user = AuthService::getAuthenticatedPortalUser();

        View::render('public.home', [
            'title' => 'Sensor Platform Testing & Ingestion Console',
            'isAuth' => $isAuth,
            'portalUser' => $user
        ], 'public');
    }

    public static function upload(): void
    {
        AuthService::ensurePortalUserTable();
        $isAuth = AuthService::isPortalUserAuthenticated();
        $user = AuthService::getAuthenticatedPortalUser();

        View::render('public.upload', [
            'title' => 'Sensor Data Upload Portal',
            'isAuth' => $isAuth,
            'portalUser' => $user
        ], 'public');
    }
}
