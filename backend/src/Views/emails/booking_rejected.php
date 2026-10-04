<?php
/**
 * Email Template: booking_rejected
 * Recipient: Student / Parent
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Hello <?= $e($recipient_name ?? 'Student') ?>,</h2>

<p>Your lesson request with <strong><?= $e($tutor_name ?? 'the requested tutor') ?></strong> for <strong><?= $e($slot_time ?? 'the selected time') ?></strong> could not be accepted at this time.</p>

<?php if (!empty($reason)): ?>
<div class="info-card">
    <p><strong>Reason provided:</strong> <em>&ldquo;<?= $e($reason) ?>&rdquo;</em></p>
</div>
<?php endif; ?>

<p>We invite you to explore other approved, DBS-verified tutors available in your subject area:</p>

<p style="text-align: center;">
    <a href="<?= $e($app_url ?? 'http://localhost') ?>/tutors.php" class="button">Find Alternative Tutors</a>
</p>

<p style="color: #64748b; font-size: 13px;">Our admissions and support team is available if you need assistance finding the right match for your academic goals.</p>
