<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Support\Env;
use App\Support\Response;
use App\Support\Timezone;
use App\Support\View;

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Front Controller Router
match (true) {
    // 1. Marketing Homepage
    $requestUri === '/' || $requestUri === '/index.php' => (function () {
        View::render(
            'home',
            [],
            'AppTutors UK — 1-to-1 Tutoring for Primary, GCSE & A-Level',
            'Professional UK tutoring platform connecting students and parents with verified, Enhanced DBS checked tutors across Primary, GCSE, and A-Level curricula.'
        );
    })(),

    // 2. Public Informational Pages
    $requestUri === '/about' || $requestUri === '/about.php' => (function () {
        require __DIR__ . '/about.php';
    })(),

    $requestUri === '/subjects' || $requestUri === '/subjects.php' => (function () {
        require __DIR__ . '/subjects.php';
    })(),

    $requestUri === '/pricing' || $requestUri === '/pricing.php' => (function () {
        require __DIR__ . '/pricing.php';
    })(),

    $requestUri === '/testimonials' || $requestUri === '/testimonials.php' => (function () {
        require __DIR__ . '/testimonials.php';
    })(),

    $requestUri === '/contact' || $requestUri === '/contact.php' => (function () {
        require __DIR__ . '/contact.php';
    })(),

    $requestUri === '/blog' || $requestUri === '/blog.php' => (function () {
        require __DIR__ . '/blog.php';
    })(),

    str_starts_with($requestUri, '/blog-post') => (function () {
        require __DIR__ . '/blog-post.php';
    })(),

    $requestUri === '/newsletter' || $requestUri === '/newsletter.php' => (function () {
        require __DIR__ . '/newsletter.php';
    })(),

    $requestUri === '/tutors' || $requestUri === '/tutors.php' => (function () {
        require __DIR__ . '/tutors.php';
    })(),

    $requestUri === '/login' || $requestUri === '/login.php' => (function () {
        require __DIR__ . '/login.php';
    })(),

    $requestUri === '/register' || $requestUri === '/register.php' => (function () {
        require __DIR__ . '/register.php';
    })(),

    // 3. API Endpoints (Preserving Phase 3 Architecture)
    str_starts_with($requestUri, '/api/health') => (function () {
        require dirname(__DIR__) . '/api/health.php';
    })(),

    str_starts_with($requestUri, '/api/auth') => (function () {
        require dirname(__DIR__) . '/api/auth.php';
    })(),

    str_starts_with($requestUri, '/api/register/student') => (function () {
        require dirname(__DIR__) . '/api/register/student.php';
    })(),

    str_starts_with($requestUri, '/api/register/tutor') => (function () {
        require dirname(__DIR__) . '/api/register/tutor.php';
    })(),

    // 4. 404 Catch-All
    default => (function () use ($requestUri) {
        if (str_starts_with($requestUri, '/api/')) {
            Response::error("API endpoint '{$requestUri}' not found on this platform.", 'NOT_FOUND', 404);
            return;
        }
        http_response_code(404);
        View::render('404', [], 'Page Not Found — AppTutors UK', 'The requested page could not be found.');
    })(),
};
