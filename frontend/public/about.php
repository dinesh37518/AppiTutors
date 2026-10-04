<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\View;

View::render(
    'about',
    [],
    'About Us & DBS Safeguarding Standards — AppTutors UK',
    'Learn about our rigorous UK educator vetting process, mandatory Enhanced DBS background checks, and educational safeguarding architecture.'
);
