<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;
use App\Services\BookingService;
use App\Support\View;

$db = Database::getConnection();
$bookingService = new BookingService($db);

$subjectFilter = trim((string)($_GET['subject'] ?? ''));
$levelFilter = trim((string)($_GET['level'] ?? ''));
$deliveryFilter = trim((string)($_GET['mode'] ?? ''));
$searchQuery = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
$selectedTutorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "
    SELECT u.id, u.display_name, tp.headline, tp.bio, tp.qualifications, tp.subjects_json, tp.curriculum_json,
           (SELECT COUNT(*) FROM availability_slots s WHERE s.tutor_user_id = u.id AND s.status = 'PUBLISHED' AND s.starts_at_utc >= NOW()) as slot_count
    FROM users u
    JOIN tutor_profiles tp ON u.id = tp.user_id
    WHERE u.status = 'ACTIVE' 
      AND tp.approval_status = 'APPROVED' 
      AND tp.dbs_status = 'VERIFIED'
";
$params = [];

if (!empty($searchQuery)) {
    $sql .= " AND (u.display_name LIKE :q OR tp.headline LIKE :q OR tp.bio LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

if (!empty($subjectFilter) && !str_starts_with($subjectFilter, 'All')) {
    $sql .= " AND (tp.subjects_json LIKE :subj OR tp.headline LIKE :subj)";
    $params[':subj'] = "%{$subjectFilter}%";
}

if (!empty($levelFilter) && !str_starts_with($levelFilter, 'All')) {
    $sql .= " AND (tp.headline LIKE :lvl OR tp.subjects_json LIKE :lvl OR tp.curriculum_json LIKE :lvl)";
    $params[':lvl'] = "%{$levelFilter}%";
}

// Order Dr. Alistair H. first, then tutors with published slots
$sql .= " ORDER BY (u.display_name = 'Dr. Alistair H.') DESC, slot_count DESC, u.id ASC LIMIT 12";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tutors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If selected tutor requested, load details and slots
$selectedTutor = null;
$selectedSlots = [];
if ($selectedTutorId > 0) {
    foreach ($tutors as $t) {
        if ((int)$t['id'] === $selectedTutorId) {
            $selectedTutor = $t;
            break;
        }
    }
    if ($selectedTutor === null) {
        $stmtSel = $db->prepare("SELECT u.id, u.display_name, tp.headline, tp.bio, tp.qualifications, tp.subjects_json FROM users u JOIN tutor_profiles tp ON u.id = tp.user_id WHERE u.id = :id AND tp.approval_status = 'APPROVED' AND tp.dbs_status = 'VERIFIED'");
        $stmtSel->execute([':id' => $selectedTutorId]);
        $selectedTutor = $stmtSel->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($selectedTutor !== null) {
        $selectedSlots = $bookingService->getAvailableSlotsForTutor($selectedTutorId);
    }
}

View::render(
    'tutors',
    [
        'tutors' => $tutors,
        'selectedTutor' => $selectedTutor,
        'selectedSlots' => $selectedSlots,
        'selectedTutorId' => $selectedTutorId,
        'subjectFilter' => $subjectFilter,
        'levelFilter' => $levelFilter,
        'deliveryFilter' => $deliveryFilter,
        'searchQuery' => $searchQuery,
    ],
    'Find a Verified UK Tutor — AppTutors UK',
    'Search our directory of Enhanced DBS checked, verified UK tutors across Primary, KS3, GCSE, and A-Level.'
);
