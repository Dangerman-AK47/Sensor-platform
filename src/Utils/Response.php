<?php
declare(strict_types=1);

namespace App\Utils;

class Response
{
    public static function json(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): void
    {
        $payload = ['success' => true, 'message' => $message];
        if ($data !== null) {
            if (is_array($data)) {
                $payload = array_merge($payload, $data);
            } else {
                $payload['data'] = $data;
            }
        }
        self::json($payload, $status);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        $payload = array_merge([
            'success' => false,
            'error' => $message,
            'status' => $status
        ], $extra);
        self::json($payload, $status);
    }

    public static function file(string $filePath, string $filename, string $mimeType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            self::error('File not found', 404);
        }

        $fileSize = filesize($filePath);
        http_response_code(200);
        header("Content-Type: {$mimeType}");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Content-Length: {$fileSize}");
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }
}
