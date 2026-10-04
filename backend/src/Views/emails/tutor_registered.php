<?php
/**
 * Email Template: tutor_registered
 * Recipient: Tutor Applicant
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Welcome to the UK Tutoring Platform, <?= $e($recipient_name ?? 'Applicant') ?>!</h2>

<p>Thank you for submitting your tutor application. Your profile has been received and is currently under review by our admissions and compliance team.</p>

<div class="info-card">
    <p><strong>Account Status:</strong> <span class="badge badge-pending">PENDING VERIFICATION</span></p>
    <p><strong>Next Steps:</strong></p>
    <ul style="margin: 6px 0; padding-left: 20px;">
        <li>Submit your Enhanced DBS certificate documentation for safeguarding verification.</li>
        <li>Complete your subject qualifications and biography details.</li>
        <li>Our managerial team will review your application within 2–3 business days.</li>
    </ul>
</div>

<p>You can access your tutor profile and upload your safeguarding credentials at any time:</p>

<p style="text-align: center;">
    <a href="<?= $e($app_url ?? 'http://localhost') ?>/tutor-profile.php" class="button">Access Tutor Profile</a>
</p>

<p style="color: #64748b; font-size: 13px;">In compliance with UK safeguarding standards, availability slots cannot be published until Enhanced DBS verification is confirmed.</p>
