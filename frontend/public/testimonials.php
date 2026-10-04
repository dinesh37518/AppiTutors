<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\View;

View::render(
    'testimonials',
    [],
    'Verified Parent & Student Testimonials — AppTutors UK',
    'Real case studies and verified feedback from UK families following completed lesson lifecycles.'
);
