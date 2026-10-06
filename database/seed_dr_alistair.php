<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;

$db = Database::getConnection();

// Check if Dr. Alistair H. already exists
$stmt = $db->prepare("SELECT id FROM users WHERE display_name = 'Dr. Alistair H.' LIMIT 1");
$stmt->execute();
$existingId = $stmt->fetchColumn();

if (!$existingId) {
    // Insert user
    $stmtUser = $db->prepare("
        INSERT INTO users (email, display_name, role, status, firebase_uid, created_at, updated_at)
        VALUES (:email, :name, 'TUTOR', 'ACTIVE', :uid, NOW(), NOW())
    ");
    $stmtUser->execute([
        ':email' => 'alistair.h@apptutors.co.uk',
        ':name' => 'Dr. Alistair H.',
        ':uid' => 'tutor_dr_alistair_h_' . time(),
    ]);
    $tutorId = (int) $db->lastInsertId();
    echo "Created user Dr. Alistair H. with ID: {$tutorId}\n";
} else {
    $tutorId = (int) $existingId;
    echo "Found existing Dr. Alistair H. with ID: {$tutorId}\n";
}

// Upsert tutor profile
$stmtProf = $db->prepare("
    INSERT INTO tutor_profiles (user_id, headline, bio, qualifications, subjects_json, dbs_status, approval_status, approved_at, created_at, updated_at)
    VALUES (:uid, :headline, :bio, :quals, :subj, 'VERIFIED', 'APPROVED', NOW(), NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        headline = VALUES(headline),
        bio = VALUES(bio),
        qualifications = VALUES(qualifications),
        subjects_json = VALUES(subjects_json),
        dbs_status = 'VERIFIED',
        approval_status = 'APPROVED',
        updated_at = NOW()
");
$stmtProf->execute([
    ':uid' => $tutorId,
    ':headline' => 'MSc, PhD Biochemistry (Imperial College London) • 8+ Years Tutoring',
    ':bio' => 'Specialist A-Level Chemistry and GCSE Triple Science educator. Proven methodology focused on past paper mark scheme mastery and breaking down organic reaction mechanisms into intuitive principles.',
    ':quals' => 'MSc, PhD Biochemistry (Imperial College London)',
    ':subj' => json_encode(['A-Level Chemistry (AQA / OCR)', 'GCSE Biology & Physics', 'Oxbridge Medicine Prep']),
]);
echo "Updated tutor profile for ID {$tutorId}\n";

// Add some upcoming availability slots for Dr. Alistair H.
$stmtSlots = $db->prepare("SELECT COUNT(*) FROM availability_slots WHERE tutor_user_id = :uid AND starts_at_utc > NOW()");
$stmtSlots->execute([':uid' => $tutorId]);
$slotCount = (int) $stmtSlots->fetchColumn();

if ($slotCount < 3) {
    $now = new DateTime('next Monday 10:00:00', new DateTimeZone('Europe/London'));
    for ($i = 0; $i < 4; $i++) {
        $slotStart = clone $now;
        $slotStart->modify("+{$i} days");
        $slotEnd = clone $slotStart;
        $slotEnd->modify('+1 hour');

        $utcStart = clone $slotStart;
        $utcStart->setTimezone(new DateTimeZone('UTC'));
        $utcEnd = clone $slotEnd;
        $utcEnd->setTimezone(new DateTimeZone('UTC'));

        $stmtAdd = $db->prepare("
            INSERT INTO availability_slots (tutor_user_id, starts_at_utc, ends_at_utc, status, max_students, created_at, updated_at)
            VALUES (:uid, :starts, :ends, 'PUBLISHED', 1, NOW(), NOW())
        ");
        $stmtAdd->execute([
            ':uid' => $tutorId,
            ':starts' => $utcStart->format('Y-m-d H:i:s'),
            ':ends' => $utcEnd->format('Y-m-d H:i:s'),
        ]);
    }
    echo "Created 4 published availability slots for Dr. Alistair H.\n";
} else {
    echo "Dr. Alistair H. already has {$slotCount} upcoming slots.\n";
}
