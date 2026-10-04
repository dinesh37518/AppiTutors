<?php
/**
 * Email Template: booking_cancelled
 * Recipient: Tutor or Student/Parent
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Notice of Lesson Cancellation</h2>

<p>Hello <?= $e($recipient_name ?? 'User') ?>,</p>

<p>A tutoring session scheduled for <strong><?= $e($slot_time ?? 'Scheduled Time') ?></strong> has been cancelled.</p>

<div class="info-card">
    <p><strong>Cancelled by:</strong> <?= $e($cancelled_by ?? 'User') ?></p>
    <?php if (!empty($reason)): ?>
        <p><strong>Reason:</strong> <em>&ldquo;<?= $e($reason) ?>&rdquo;</em></p>
    <?php endif; ?>
    <p><strong>Status:</strong> <span class="badge badge-cancelled">CANCELLED</span></p>
</div>

<p>The availability slot has been released. If you need to rebook or reschedule, please visit the platform:</p>

<p style="text-align: center;">
    <a href="<?= $e($app_url ?? 'http://localhost') ?>" class="button">Visit Platform</a>
</p>
