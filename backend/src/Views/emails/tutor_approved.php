<?php
/**
 * Email Template: tutor_approved
 * Recipient: Approved Tutor
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Congratulations, <?= $e($recipient_name ?? 'Tutor') ?>!</h2>

<p>Your tutor profile has been fully approved, and your Enhanced DBS safeguarding credentials have been verified by platform management.</p>

<div class="info-card">
    <p><strong>Account Status:</strong> <span class="badge badge-confirmed">APPROVED &amp; ACTIVE</span></p>
    <p><strong>DBS Status:</strong> <span class="badge badge-confirmed">VERIFIED</span></p>
    <p>You are now eligible to publish availability slots and receive lesson booking requests from students and parents across the UK.</p>
</div>

<p>To begin teaching, set up your weekly schedule on your availability calendar:</p>

<p style="text-align: center;">
    <a href="<?= $e($app_url ?? 'http://localhost') ?>/tutor-availability.php" class="button">Set Availability Slots</a>
</p>

<p style="color: #64748b; font-size: 13px;">Welcome to the UK Tutoring Platform community!</p>
