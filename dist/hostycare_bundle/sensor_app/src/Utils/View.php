<?php
declare(strict_types=1);

namespace App\Utils;

class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'admin'): void
    {
        $candidateRoots = [
            dirname(__DIR__, 2) . '/views',
            dirname(__DIR__) . '/views',
            dirname(__DIR__, 3) . '/sensor_app/views',
            dirname(__DIR__, 2) . '/sensor_app/views',
            '/home/realtime/public_html/sensor_app/views',
            '/home/realtime/sensor_app/views'
        ];

        $baseDir = dirname(__DIR__, 2) . '/views';
        foreach ($candidateRoots as $root) {
            if (is_dir($root)) {
                $baseDir = $root;
                break;
            }
        }

        $templatePath = $baseDir . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($templatePath)) {
            http_response_code(500);
            echo "View template not found: {$template} (searched in {$baseDir})";
            exit;
        }

        extract($data);

        // Capture template output
        ob_start();
        require $templatePath;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutPath = $baseDir . '/layouts/' . $layout . '.php';
            if (file_exists($layoutPath)) {
                require $layoutPath;
                exit;
            }
        }

        echo $content;
        exit;
    }
}
