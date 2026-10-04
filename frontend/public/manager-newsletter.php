<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\NewsletterService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$newsletterService = new NewsletterService($db);

$successMessage = '';
$errorMessage = '';

// Determine active manager context
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUser = null;

if ($currentUserId !== null) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $currentUserId]);
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Fallback: If no session user, look up manager user in DB for governance portal view
if ($currentUser === null || !$currentUser->isManager()) {
    $stmt = $db->query("SELECT * FROM users WHERE role = 'MANAGER' AND status = 'ACTIVE' LIMIT 1");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Manager Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null && $currentUser->isManager()) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh and try again.';
    } else {
        $subscriberId = (int) ($_POST['subscriber_id'] ?? 0);
        $newStatus = strtoupper(trim((string) ($_POST['status'] ?? '')));

        try {
            if ($subscriberId <= 0) {
                $errorMessage = 'Valid subscriber ID is required.';
            } else {
                $sub = $newsletterService->updateSubscriberStatus($currentUser, $subscriberId, $newStatus);
                $successMessage = "Subscriber #{$subscriberId} status updated to {$sub['status']}.";
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// Fetch subscribers according to filter if manager context available
$filterStatus = $_GET['status'] ?? 'ALL';
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['search'] ?? ''));

$subscribersResult = ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1]];
$stats = ['total' => 0, 'pending' => 0, 'active' => 0, 'unsubscribed' => 0, 'suppressed' => 0];

if ($currentUser !== null && $currentUser->isManager()) {
    try {
        $filters = [
            'status' => $filterStatus,
            'page' => $page,
            'per_page' => 20,
            'search' => $search,
        ];
        $subscribersResult = $newsletterService->listSubscribers($currentUser, $filters);
        $stats = $newsletterService->getSubscriberStats($currentUser);
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = 'Access restricted: Manager authentication required to view newsletter administration.';
}

$csrfToken = Csrf::generateToken();

View::render(
    'manager-newsletter',
    [
        'subscribers' => $subscribersResult['items'],
        'pagination' => $subscribersResult['pagination'],
        'stats' => $stats,
        'filterStatus' => $filterStatus,
        'search' => $search,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Newsletter Audience Administration — Platform Administration — AppTutors UK',
    'Audience registry and consent tracking for educational newsletters with explicit preservation of open double-opt-in scope.'
);
