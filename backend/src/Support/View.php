<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lightweight, secure view renderer for public server-side rendered pages.
 */
class View
{
    /**
     * Render a view within the master header and footer layouts.
     *
     * @param string $template View template name relative to src/Views/ (e.g., 'home')
     * @param array<string, mixed> $data Variables passed to view
     * @param string $title Page title for technical SEO and browser tab
     * @param string $description Meta description for search engines
     */
    public static function render(
        string $template,
        array $data = [],
        string $title = 'AppTutors UK — 1-to-1 Tutoring for Primary, GCSE & A-Level',
        string $description = 'Professional UK tutoring platform connecting students and parents with verified, Enhanced DBS checked tutors across Primary, GCSE, and A-Level curricula.'
    ): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        // Extract variables for view scope
        extract($data, EXTR_SKIP);

        $pageTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $metaDescription = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $canonicalUrl = self::getCanonicalUrl();
        $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $rootDir = dirname(__DIR__, 3);
        $viewsBase = is_dir($rootDir . '/frontend/views') ? ($rootDir . '/frontend/views') : (dirname(__DIR__) . '/Views');

        $headerPath = $viewsBase . '/layouts/header.php';
        $footerPath = $viewsBase . '/layouts/footer.php';

        $candidates = [
            $viewsBase . '/' . $template . '.php',
            $viewsBase . '/public/' . $template . '.php',
            $viewsBase . '/auth/' . $template . '.php',
            $viewsBase . '/student/' . $template . '.php',
            $viewsBase . '/tutor/' . $template . '.php',
            $viewsBase . '/manager/' . $template . '.php',
            $viewsBase . '/components/' . $template . '.php',
            dirname(__DIR__, 2) . '/src/Views/' . $template . '.php',
            dirname(__DIR__) . '/Views/' . $template . '.php',
        ];

        $templateFile = null;
        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                $templateFile = $cand;
                break;
            }
        }

        if ($templateFile === null) {
            throw new \RuntimeException("View template '{$template}' not found.");
        }

        // Render layout
        require $headerPath;
        require $templateFile;
        require $footerPath;
    }

    /**
     * Escape HTML output for XSS protection.
     */
    public static function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate canonical URL for the current request.
     */
    private static function getCanonicalUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        return $scheme . '://' . $host . $path;
    }
}

// Global convenience helper for escaping
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return \App\Support\View::e($value);
    }
}
