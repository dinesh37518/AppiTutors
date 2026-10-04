<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;
use App\Logging\Logger;
use App\Support\Timezone;
use App\Support\View;

$posts = [];

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("
        SELECT 
            bp.id, bp.title, bp.slug, bp.excerpt, bp.body, bp.status, bp.published_at,
            u.display_name as author_name
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_user_id = u.id
        WHERE bp.status = 'PUBLISHED'
        ORDER BY bp.published_at DESC
        LIMIT 20
    ");
    $rawPosts = $stmt->fetchAll();

    foreach ($rawPosts as $row) {
        if (!empty($row['published_at'])) {
            $row['published_date_london'] = Timezone::utcToLondon($row['published_at'], 'j F Y');
        } else {
            $row['published_date_london'] = 'Recently Published';
        }
        $posts[] = $row;
    }
} catch (\Throwable $e) {
    Logger::error('Failed to load published blog posts', ['error' => $e->getMessage()]);
    $posts = [];
}

View::render(
    'blog',
    ['posts' => $posts],
    'Educational Blog & Revision Advice — AppTutors UK',
    'Practical study techniques, exam board revision guides, and subject masterclasses written by verified UK tutors.'
);
