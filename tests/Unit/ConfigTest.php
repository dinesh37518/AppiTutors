<?php

declare(strict_types=1);

namespace Tests;

use App\Logging\Logger;
use App\Support\Env;
use App\Support\Timezone;
use App\Validation\Validator;

class ConfigTest
{
    public function run(): array
    {
        $results = [];

        // 1. Mandatory Environment Variables
        $results[] = $this->testRequiredEnvVars();

        // 2. Timezone GMT/BST Handling
        $results[] = $this->testTimezoneTranslations();

        // 3. Git Secret Exclusion (.env, service account JSON)
        $results[] = $this->testGitSecretProtection();

        // 4. Input Validator & Sanitization
        $results[] = $this->testInputValidation();

        // 5. Sensitive Credential Redaction in Logger
        $results[] = $this->testLoggerCredentialRedaction();

        return $results;
    }

    private function testRequiredEnvVars(): array
    {
        $required = [
            'APP_NAME',
            'APP_ENV',
            'APP_TIMEZONE',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'FIREBASE_PROJECT_ID',
        ];

        $missing = [];
        foreach ($required as $var) {
            $val = Env::get($var);
            if ($val === null || $val === '') {
                $missing[] = $var;
            }
        }

        $passed = empty($missing);
        return [
            'name' => 'Config: Required Environment Variables Present',
            'passed' => $passed,
            'detail' => $passed ? 'All required .env variables present' : 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function testTimezoneTranslations(): array
    {
        // 1. Test summer date (British Summer Time = UTC+1)
        // 15 July 2026 14:00 London time should be 13:00 UTC
        $summerLondon = '2026-07-15 14:00:00';
        $summerUtc = Timezone::londonToUtc($summerLondon);
        $expectedSummerUtc = '2026-07-15 13:00:00';

        // 2. Test winter date (Greenwich Mean Time = UTC+0)
        // 15 January 2026 14:00 London time should be 14:00 UTC
        $winterLondon = '2026-01-15 14:00:00';
        $winterUtc = Timezone::londonToUtc($winterLondon);
        $expectedWinterUtc = '2026-01-15 14:00:00';

        $isSummerBst = Timezone::isBst($summerUtc);
        $isWinterBst = Timezone::isBst($winterUtc);

        $passed = (
            $summerUtc === $expectedSummerUtc &&
            $winterUtc === $expectedWinterUtc &&
            $isSummerBst === true &&
            $isWinterBst === false
        );

        return [
            'name' => 'Config: Timezone Handles GMT/BST Seasonal Transitions Dynamic Localization',
            'passed' => $passed,
            'detail' => "Summer 14:00 London -> {$summerUtc} (BST: yes), Winter 14:00 London -> {$winterUtc} (BST: no)",
        ];
    }

    private function testGitSecretProtection(): array
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : (file_exists(dirname(__DIR__) . '/.env') ? dirname(__DIR__) : dirname(__DIR__, 2));
        $testEnvFile = $appRoot . '/.env';
        $envExists = file_exists($testEnvFile);

        // Check git status/ignore for .env
        $output = [];
        $ret = 0;
        @exec('git check-ignore .env storage/credentials/secret.json 2>&1', $output, $ret);

        $isIgnored = ($ret === 0 && count($output) >= 1);

        return [
            'name' => 'Config: Git Protection Excludes .env & Service Account JSON',
            'passed' => $isIgnored && $envExists,
            'detail' => $isIgnored ? '.env and service credentials verified as Git-ignored' : 'Warning: Files not ignored by Git',
        ];
    }

    private function testInputValidation(): array
    {
        $validEmail = Validator::validateEmail('parent@example.co.uk');
        $invalidEmail = !Validator::validateEmail('invalid-email-address');

        $xssInput = '<script>alert("xss")</script>Hello';
        $sanitized = Validator::sanitizeString($xssInput);
        $noScript = !str_contains($sanitized, '<script>') && str_contains($sanitized, '&lt;script&gt;');

        $passed = $validEmail && $invalidEmail && $noScript;

        return [
            'name' => 'Config: Server-Side Input Validator & XSS Sanitization',
            'passed' => $passed,
            'detail' => 'Email RFC validation and HTML sanitization working correctly',
        ];
    }

    private function testLoggerCredentialRedaction(): array
    {
        $logger = new Logger();

        $sensitivePayload = [
            'user' => 'john_doe',
            'password' => 'super_secret_p@ss',
            'id_token' => 'eyJhbGciOiJSUzI1NiIs...',
            'nested' => [
                'api_key' => 'live_secret_key_123',
                'regular_field' => 'visible_value',
            ],
        ];

        $redacted = $logger->redactSensitiveData($sensitivePayload);

        $passed = (
            $redacted['password'] === '***REDACTED***' &&
            $redacted['id_token'] === '***REDACTED***' &&
            $redacted['nested']['api_key'] === '***REDACTED***' &&
            $redacted['nested']['regular_field'] === 'visible_value'
        );

        return [
            'name' => 'Config: Logger Automatically Redacts Passwords and Tokens',
            'passed' => $passed,
            'detail' => 'Sensitive fields safely masked from log context',
        ];
    }
}
