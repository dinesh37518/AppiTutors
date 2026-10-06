<?php
/**
 * Email Template: newsletter_blog
 * Recipient: Newsletter Subscriber
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">New Educational Article: <?= $e($post_title ?? 'Latest Insights') ?></h2>

<p>Hello,</p>

<p>A new educational revision guide and article has just been published on AppTutors UK:</p>

<div class="info-card">
    <h3 style="margin-top: 0; margin-bottom: 8px; color: #1e3a8a; font-size: 16px;"><?= $e($post_title ?? '') ?></h3>
    <?php if (!empty($post_excerpt)): ?>
        <p style="color: #475569; font-size: 14px; margin-bottom: 12px;"><?= $e($post_excerpt) ?></p>
    <?php endif; ?>
    <p style="margin: 0;">
        <a href="<?= $e($post_url ?? 'http://localhost') ?>" class="button">Read Full Article</a>
    </p>
</div>

<p style="color: #64748b; font-size: 12px; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
    You are receiving this email because you subscribed to AppTutors educational updates.
    <?php if (!empty($unsubscribe_url)): ?>
        <br><a href="<?= $e($unsubscribe_url) ?>" style="color: #64748b; text-decoration: underline;">Unsubscribe from newsletter</a>
    <?php endif; ?>
</p>
