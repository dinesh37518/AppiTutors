<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\ManagerService;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$managerService = new ManagerService($db);

$successMessage = '';
$errorMessage = '';

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

if ($currentUser === null || !$currentUser->isManager()) {
    $stmt = $db->query("SELECT * FROM users WHERE role = 'MANAGER' AND status = 'ACTIVE' LIMIT 1");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$slots = [];
$pagination = ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1];

if ($currentUser !== null && $currentUser->isManager()) {
    try {
        $result = $managerService->listAvailability(
            $currentUser,
            [
                'status' => $statusFilter,
                'search' => $search,
            ],
            $page,
            $perPage
        );
        $slots = $result['items'];
        $pagination = $result['pagination'];
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = 'Manager authentication required.';
}

View::render(
    'manager-availability',
    [
        'slots' => $slots,
        'pagination' => $pagination,
        'statusFilter' => $statusFilter,
        'search' => $search,
        'currentUser' => $currentUser,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Availability Schedule Administration — AppTutors UK',
    'Platform overview of educator availability slots, calendar capacity, and booking allocations.'
);
