<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Exceptions\ValidationException;

/**
 * Request — Centralized HTTP Request Parsing, Validation, and Client Resolution.
 *
 * Implements defensive HTTP handling:
 * - Content-Type inspection
 * - Malformed JSON rejection (HTTP 400)
 * - Safe fallback to form-encoded input
 * - Bearer token extraction
 * - Client IP resolution
 */
class Request
{
    /**
     * Parse and validate request input (JSON or form-urlencoded).
     *
     * @param bool $required Whether a non-empty body is required
     * @return array
     * @throws ValidationException If JSON payload is syntactically invalid
     */
    public static function getJsonBody(bool $required = false): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            if ($required && empty($_POST)) {
                throw new ValidationException('Request body cannot be empty.', 'EMPTY_REQUEST_BODY', 400);
            }
            return $_POST ?? [];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $trimmed = trim($raw);
        $isJson = str_contains(strtolower($contentType), 'application/json')
            || str_starts_with($trimmed, '{')
            || str_starts_with($trimmed, '[');

        if ($isJson) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException(
                    'Malformed JSON payload: ' . json_last_error_msg(),
                    'MALFORMED_JSON',
                    400
                );
            }
            return is_array($decoded) ? $decoded : [];
        }

        return !empty($_POST) ? $_POST : (json_decode($raw, true) ?? []);
    }

    /**
     * Get the current HTTP request method.
     */
    public static function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Extract Bearer token from HTTP Authorization header.
     */
    public static function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($header) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            $token = trim($matches[1]);
            return $token !== '' ? $token : null;
        }

        return null;
    }

    /**
     * Resolve client IP address with optional test override header.
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_TEST_IP'])) {
            return trim((string) $_SERVER['HTTP_X_TEST_IP']);
        }

        return trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    }
}
