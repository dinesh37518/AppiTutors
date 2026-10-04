<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'enabled' => filter_var(Env::get('EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'mailer' => Env::get('EMAIL_MAILER', 'log'),
    'host' => Env::get('EMAIL_HOST', '127.0.0.1'),
    'port' => (int) Env::get('EMAIL_PORT', 587),
    'username' => Env::get('EMAIL_USERNAME', ''),
    'password' => Env::get('EMAIL_PASSWORD', ''),
    'encryption' => Env::get('EMAIL_ENCRYPTION', 'tls'),
    'from' => [
        'address' => Env::get('EMAIL_FROM_ADDRESS', 'noreply@tutoringplatform.co.uk'),
        'name' => Env::get('EMAIL_FROM_NAME', 'UK Tutoring Platform'),
    ],
];
