<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\View;

http_response_code(404);

View::render(
    '404',
    [],
    'Page Not Found — AppTutors UK',
    'The requested page could not be found on this platform.'
);
