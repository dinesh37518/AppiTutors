<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\BlogService;
use App\Services\Exceptions\ValidationException;
use App\Support\Request;
use App\Support\Response;

$logger = new Logger();
$blogService = new BlogService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    if ($method === 'GET') {
        // Optional user authentication for personalized/role-aware visibility
        $currentUser = null;
        try {
            $currentUser = $verifier->authenticateRequest();
        } catch (\Throwable $e) {
            $currentUser = null; // Unauthenticated public access allowed for published posts
        }

        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        if ($slug !== '') {
            $post = $blogService->getPostBySlug($slug, $currentUser);
            Response::success(['post' => $post]);
        }

        $postId = (int) ($_GET['id'] ?? $_GET['post_id'] ?? 0);
        if ($postId > 0) {
            $post = $blogService->getPostById($postId, $currentUser);
            Response::success(['post' => $post]);
        }

        $result = $blogService->listPosts($currentUser, $_GET);
        Response::success([
            'posts' => $result['items'],
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ]);

    } elseif ($method === 'POST') {
        // Author actions require authenticated active tutor or manager
        $user = $verifier->authenticateRequest();

        $body = Request::getJsonBody();
        $action = strtoupper(trim((string) ($body['action'] ?? 'CREATE')));

        switch ($action) {
            case 'CREATE':
                $post = $blogService->createPost($user, $body);
                Response::success(['message' => 'Blog post created successfully.', 'post' => $post], 201);
                break;

            case 'UPDATE':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->updatePost($user, $postId, $body);
                Response::success(['message' => 'Blog post updated successfully.', 'post' => $post]);
                break;

            case 'SUBMIT':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->submitPost($user, $postId);
                Response::success(['message' => 'Blog post submitted for moderation successfully.', 'post' => $post]);
                break;

            case 'APPROVE':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->approvePost($user, $postId);
                Response::success(['message' => 'Blog post approved successfully.', 'post' => $post]);
                break;

            case 'REJECT':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
                $post = $blogService->rejectPost($user, $postId, $reason);
                Response::success(['message' => 'Blog post rejected.', 'post' => $post]);
                break;

            case 'PUBLISH':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->publishPost($user, $postId);
                Response::success(['message' => 'Blog post published successfully.', 'post' => $post]);
                break;

            case 'ARCHIVE':
            case 'UNPUBLISH':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->archivePost($user, $postId);
                Response::success(['message' => 'Blog post archived successfully.', 'post' => $post]);
                break;

            default:
                Response::error("Unknown action '{$action}'. Allowed actions: CREATE, UPDATE, SUBMIT, APPROVE, REJECT, PUBLISH, ARCHIVE.", 'INVALID_ACTION', 422);
        }

    } else {
        Response::error('Method not allowed. Use GET or POST.', 'METHOD_NOT_ALLOWED', 405);
    }

} catch (InvalidTokenException | AuthenticationException $e) {
    Response::error($e->getMessage(), 'UNAUTHENTICATED', 401);
} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (\Throwable $e) {
    $logger->error('Unexpected error in /api/blog.php: ' . $e->getMessage());
    Response::error('Blog request could not be completed.', 'INTERNAL_ERROR', 500);
}
