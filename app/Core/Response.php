<?php

namespace App\Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $message = 'Error', int $statusCode = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        self::json($response, $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }

    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }

    public static function sse(callable $callback): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $callback(function (string $event, ?string $data = null, ?string $id = null) {
            if ($id !== null) {
                echo "id: {$id}\n";
            }
            if ($event !== 'message') {
                echo "event: {$event}\n";
            }
            echo "data: " . json_encode(['content' => $data], JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        });

        echo "event: done\n";
        echo "data: " . json_encode(['content' => '[DONE]']) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
