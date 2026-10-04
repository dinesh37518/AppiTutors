<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SecurityHeaders — Centralized HTTP Security and Privacy Headers Enforcer.
 *
 * Implements strict defensive headers aligned with OWASP Secure Headers guidance:
 * - Content-Security-Policy (CSP) tailored to application assets
 * - X-Content-Type-Options: nosniff
 * - X-Frame-Options: SAMEORIGIN
 * - Referrer-Policy: strict-origin-when-cross-origin
 * - Permissions-Policy
 * - Strict-Transport-Security (emitted conditionally when HTTPS is active)
 */
class SecurityHeaders
{
    public const CSP_POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none';";
    public const PERMISSIONS_POLICY = "geolocation=(), camera=(), microphone=(), payment=()";
    public const REFERRER_POLICY = "strict-origin-when-cross-origin";
    public const FRAME_OPTIONS = "SAMEORIGIN";
    public const CONTENT_TYPE_OPTIONS = "nosniff";

    /**
     * Apply security headers to the current HTTP response if headers have not already been sent.
     *
     * @param bool $forceHttpsHsts If true, emits HSTS regardless of local HTTPS detection (used in tests or prod)
     * @return array<string, string> Map of applied header names and values
     */
    public static function apply(bool $forceHttpsHsts = false): array
    {
        $headers = [
            'X-Content-Type-Options' => self::CONTENT_TYPE_OPTIONS,
            'X-Frame-Options' => self::FRAME_OPTIONS,
            'Referrer-Policy' => self::REFERRER_POLICY,
            'Permissions-Policy' => self::PERMISSIONS_POLICY,
            'Content-Security-Policy' => self::CSP_POLICY,
        ];

        // HSTS is strictly emitted in production or when HTTPS is active.
        // Plain HTTP local development does not emit HSTS (per Phase 11 specification).
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || $forceHttpsHsts;

        if ($isHttps) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (!headers_sent()) {
            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        return $headers;
    }

    /**
     * Determine whether the current request is over HTTPS.
     */
    public static function isHttps(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }
}
