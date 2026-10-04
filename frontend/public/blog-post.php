<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;
use App\Logging\Logger;
use App\Support\Timezone;
use App\Support\View;

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '') {
    header('Location: /blog.php');
    exit;
}

$post = null;

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            bp.id, bp.title, bp.slug, bp.excerpt, bp.body, bp.status, bp.published_at,
            u.display_name as author_name
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_user_id = u.id
        WHERE bp.slug = :slug AND bp.status = 'PUBLISHED'
        LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch();

    if ($post) {
        if (!empty($post['published_at'])) {
            $post['published_date_london'] = Timezone::utcToLondon($post['published_at'], 'j F Y');
        } else {
            $post['published_date_london'] = 'Recently Published';
        }
    }
} catch (\Throwable $e) {
    Logger::error('Failed to query blog post by slug', ['slug' => $slug, 'error' => $e->getMessage()]);
    $post = null;
}

if (!$post) {
    http_response_code(404);
    View::render(
        '404',
        [],
        'Article Not Found — AppTutors UK',
        'The requested revision guide or blog article does not exist or has been removed.'
    );
    exit;
}

View::render(
    'blog-post',
    ['post' => $post],
    $post['title'] . ' — AppTutors UK Revision Guide',
    $post['excerpt'] ?? 'UK educational study guide from AppTutors verified educators.'
);
