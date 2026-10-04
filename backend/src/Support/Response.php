<?php

declare(strict_types=1);

namespace App\Support;

class Response
{
    /**
     * Send a standardized JSON response.
     *
     * @param array $payload
     * @param int $statusCode
     * @param array $headers
     * @return void
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            SecurityHeaders::apply();

            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a success response.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $meta
     * @return void
     */
    public static function success(mixed $data = null, int $statusCode = 200, array $meta = []): void
    {
        $payload = [
            'success' => true,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        self::json($payload, $statusCode);
    }

    /**
     * Send a standardized error response.
     *
     * @param string $message
     * @param string $code
     * @param int $statusCode
     * @param array $details
     * @return void
     */
    public static function error(string $message, string $code = 'BAD_REQUEST', int $statusCode = 400, array $details = []): void
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if (!empty($details)) {
            $payload['error']['details'] = $details;
        }

        self::json($payload, $statusCode);
    }
}
