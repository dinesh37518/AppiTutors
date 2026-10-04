<?php
/**
 * Email Template: tutor_rejected
 * Recipient: Tutor Applicant
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Update on Your UK Tutoring Platform Application</h2>

<p>Hello <?= $e($recipient_name ?? 'Applicant') ?>,</p>

<p>Thank you for your interest in joining the UK Tutoring Platform. Following a compliance and safeguarding review, we regret to inform you that we are unable to approve your tutor application at this time.</p>

<?php if (!empty($reason)): ?>
<div class="info-card">
    <p><strong>Review feedback:</strong> <em>&ldquo;<?= $e($reason) ?>&rdquo;</em></p>
</div>
<?php endif; ?>

<p>If you believe this decision was made in error or if your credentials have changed, you may contact our compliance team for further information.</p>
