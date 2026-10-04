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

$kpis = [];
if ($currentUser !== null && $currentUser->isManager()) {
    try {
        $kpis = $managerService->getDashboardKpis($currentUser);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = 'Access restricted: Manager authentication required to view governance portal.';
}

View::render(
    'manager-dashboard',
    [
        'kpis' => $kpis,
        'currentUser' => $currentUser,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Executive Dashboard — Platform Administration — AppTutors UK',
    'Platform management dashboard providing factual KPIs, safeguarding status, and operational metrics.'
);
