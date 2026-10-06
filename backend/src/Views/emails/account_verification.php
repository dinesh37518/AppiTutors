<?php
/**
 * Email Template: account_verification
 * Recipient: Student / Parent / Tutor
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Welcome to AppTutors UK, <?= $e($recipient_name ?? 'User') ?>!</h2>

<p>Thank you for creating your account with AppTutors UK. To complete your account registration and ensure safeguarding compliance, please verify your email address.</p>

<div class="info-card">
    <p><strong>Registered Email:</strong> <?= $e($recipient_email ?? '') ?></p>
    <p><strong>Account Role:</strong> <?= $e($account_role ?? 'Student / Parent') ?></p>
    <?php if (!empty($parent_email)): ?>
        <p><strong>Linked Parent/Guardian Email:</strong> <?= $e($parent_email) ?></p>
    <?php endif; ?>
    <p><strong>Status:</strong> <span class="badge badge-pending">PENDING VERIFICATION</span></p>
</div>

<p>Click the link below to confirm your email address and activate your learning portal access:</p>

<p style="text-align: center;">
    <a href="<?= $e($verification_url ?? ($app_url ?? 'http://localhost') . '/login.php') ?>" class="button">Verify Email Address</a>
</p>

<p style="color: #64748b; font-size: 13px;">If you did not create an account on AppTutors UK, please ignore this email.</p>
