<?php
declare(strict_types=1);

namespace App\Utils;

class DeviceIdValidator
{
    /**
     * Validates and normalizes Device ID:
     * - Must be a non-empty string between 2 and 100 characters.
     * - Only alphanumeric characters, dashes, and underscores allowed.
     * - Always returns UPPERCASE string.
     */
    public static function normalize(?string $deviceId): ?string
    {
        if ($deviceId === null) {
            return null;
        }

        $trimmed = strtoupper(trim($deviceId));
        if (strlen($trimmed) < 2 || strlen($trimmed) > 100) {
            return null;
        }

        if (!preg_match('/^[A-Z0-9_\-]+$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }
}
