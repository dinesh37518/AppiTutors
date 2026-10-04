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

$reports = [];
if ($currentUser !== null && $currentUser->isManager()) {
    try {
        $reports = $managerService->getReports($currentUser);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = 'Manager authentication required.';
}

View::render(
    'manager-reports',
    [
        'reports' => $reports,
        'currentUser' => $currentUser,
        'errorMessage' => $errorMessage,
    ],
    'Operational Reports & Compliance — AppTutors UK',
    'Factual platform reporting on tutor approvals, DBS safeguarding rates, and capacity allocation.'
);
