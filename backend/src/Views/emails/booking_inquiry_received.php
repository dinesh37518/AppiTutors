<?php
/**
 * Email Template: booking_inquiry_received
 * Recipient: Assigned Tutor (or Student confirmation)
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Hello <?= $e($recipient_name ?? 'Tutor') ?>,</h2>

<p>You have received a new lesson request on the UK Tutoring Platform.</p>

<div class="info-card">
    <p><strong>Lesson Time (UK):</strong> <?= $e($slot_time ?? 'To be scheduled') ?></p>
    <p><strong>Student / Parent:</strong> <?= $e($student_name ?? 'Prospective Student') ?></p>
    <?php if (!empty($child_name)): ?>
        <p><strong>Student (Child):</strong> <?= $e($child_name) ?> (<?= $e($child_school_year ?? '') ?>)</p>
    <?php endif; ?>
    <?php if (!empty($notes)): ?>
        <p><strong>Inquiry Notes:</strong> <em>&ldquo;<?= $e($notes) ?>&rdquo;</em></p>
    <?php endif; ?>
    <p><strong>Current Status:</strong> <span class="badge badge-pending">PENDING CONFIRMATION</span></p>
</div>

<p>Please review and confirm or decline this session request at your earliest convenience:</p>

<p style="text-align: center;">
    <a href="<?= $e($app_url ?? 'http://localhost') ?>/tutor-bookings.php" class="button">Review Lesson Request</a>
</p>

<p style="color: #64748b; font-size: 13px;">If you are unable to fulfill this request, please decline it promptly so the student can arrange alternative tutoring.</p>
