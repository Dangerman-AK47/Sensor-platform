<?php
declare(strict_types=1);

/**
 * Sensor Platform - Application Configuration Loader
 */

namespace App\Config;

class Config
{
    private static array $settings = [];
    private static bool $loaded = false;

    public static function load(?string $envFile = null): void
    {
        if (self::$loaded) {
            return;
        }

        if ($envFile !== null) {
            $envPath = $envFile;
        } else {
            $candidates = [
                dirname(__DIR__) . '/.env',
                dirname(__DIR__, 2) . '/sensor_app/.env',
                dirname(__DIR__) . '/sensor_app/.env',
                dirname(__DIR__, 2) . '/.env',
                '/home/realtime/sensor_app/.env',
                '/home/realtime/public_html/sensor_app/.env',
                '/home/realtime/public_html/.env',
                '/home/realtime/.env'
            ];
            $envPath = dirname(__DIR__) . '/.env';
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $envPath = $cand;
                    break;
                }
            }
        }
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val);
                    // Strip surrounding quotes
                    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                        $val = substr($val, 1, -1);
                    }
                    self::$settings[$key] = $val;
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $val;
                    }
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$settings[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
