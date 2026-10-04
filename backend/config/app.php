<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'UK Tutoring Platform'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => (bool) Env::get('APP_DEBUG', true),
    'url' => Env::get('APP_URL', 'http://localhost'),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/London'),
];
