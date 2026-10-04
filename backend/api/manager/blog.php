<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\Authorization;
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

    // 1. Strict Manager Authentication & Authority
    $manager = $verifier->authenticateRequest();
    Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
    Authorization::requireActiveStatus($manager);

    if ($method === 'GET') {
        $postId = (int) ($_GET['id'] ?? $_GET['post_id'] ?? 0);

        if ($postId > 0) {
            $post = $blogService->getPostById($postId, $manager);
            Response::success(['post' => $post]);
        }

        $result = $blogService->listPosts($_GET, true, $manager);

        Response::success([
            'posts' => $result['items'],
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ]);

    } elseif ($method === 'POST') {
        $body = Request::getJsonBody();

        $action = strtoupper(trim((string) ($body['action'] ?? 'CREATE')));

        switch ($action) {
            case 'CREATE':
                $post = $blogService->createPost($manager, $body);
                Response::success(['message' => 'Blog post created successfully.', 'post' => $post], 201);
                break;

            case 'UPDATE':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->updatePost($manager, $postId, $body);
                Response::success(['message' => 'Blog post updated successfully.', 'post' => $post]);
                break;

            case 'SUBMIT':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->submitPost($manager, $postId);
                Response::success(['message' => 'Blog post submitted for moderation successfully.', 'post' => $post]);
                break;

            case 'APPROVE':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->approvePost($manager, $postId);
                Response::success(['message' => 'Blog post approved successfully.', 'post' => $post]);
                break;

            case 'REJECT':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
                $post = $blogService->rejectPost($manager, $postId, $reason);
                Response::success(['message' => 'Blog post rejected.', 'post' => $post]);
                break;

            case 'PUBLISH':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->publishPost($manager, $postId);
                Response::success(['message' => 'Blog post published successfully.', 'post' => $post]);
                break;

            case 'ARCHIVE':
            case 'UNPUBLISH':
                $postId = (int) ($body['post_id'] ?? $body['id'] ?? 0);
                if ($postId <= 0) {
                    Response::error('Valid post_id is required.', 'INVALID_PARAMS', 422);
                }
                $post = $blogService->archivePost($manager, $postId);
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
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/manager/blog.php: ' . $e->getMessage());
    Response::error('Blog management action could not be completed.', 'INTERNAL_ERROR', 500);
}
