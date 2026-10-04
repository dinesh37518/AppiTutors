<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\View;

View::render(
    'pricing',
    [],
    'Pricing & Tutoring Fee Structure — AppTutors UK',
    'Transparent UK tutoring fee structure across Primary, GCSE, and A-Level. No registration fees or hidden subscription charges.'
);
