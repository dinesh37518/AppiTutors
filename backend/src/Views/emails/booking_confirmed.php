<?php
/**
 * Email Template: booking_confirmed
 * Recipient: Student / Parent
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Good news, <?= $e($recipient_name ?? 'Student') ?>!</h2>

<p>Your tutoring session has been confirmed by your tutor.</p>

<div class="info-card">
    <p><strong>Tutor:</strong> <?= $e($tutor_name ?? 'Assigned Tutor') ?></p>
    <p><strong>Lesson Time (UK):</strong> <?= $e($slot_time ?? 'Scheduled Time') ?></p>
    <?php if (!empty($child_name)): ?>
        <p><strong>Student:</strong> <?= $e($child_name) ?></p>
    <?php endif; ?>
    <p><strong>Status:</strong> <span class="badge badge-confirmed">CONFIRMED</span></p>
</div>

<p>You can view your confirmed booking and prepare for your upcoming session here:</p>

<p style="text-align: center;">
    <a href="<?= $e($app_url ?? 'http://localhost') ?>/student-bookings.php" class="button">View My Bookings</a>
</p>

<p style="color: #64748b; font-size: 13px;">If you need to make any changes or have questions, please access your bookings dashboard.</p>
