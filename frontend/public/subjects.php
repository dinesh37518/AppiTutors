<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\View;

View::render(
    'subjects',
    [],
    'UK Curriculum & Subjects Guide — AppTutors UK',
    'Explore specialist tutoring across UK Primary, Key Stage 3, GCSE (AQA, Edexcel, OCR), and A-Level curricula.'
);
