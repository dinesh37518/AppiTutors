<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\View;

View::render(
    'tutors',
    [],
    'Find a Verified UK Tutor — AppTutors UK',
    'Search our directory of Enhanced DBS checked, verified UK tutors across Primary, KS3, GCSE, and A-Level.'
);
