<!-- Dedicated Newsletter Subscription Page -->
<div class="container section">
    <div style="max-width: 680px; margin: 0 auto;">
        <div style="margin-bottom: 2.5rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Curriculum Updates & Revision</span>
            <h1 style="margin-bottom: 1rem;">UK Educational Newsletter</h1>
            <p style="font-size: 1.15rem; color: var(--color-navy-600);">
                Receive authoritative guidance on key UK exam dates, syllabus updates, revision planning templates, and educator insights.
            </p>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Subscribed Successfully:</span>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 2.5rem; box-shadow: var(--shadow-lg);">
            <h2 style="font-size: 1.35rem; margin-bottom: 1rem;">Join Over 500+ UK Parents & Students</h2>
            <p style="color: var(--color-navy-600); font-size: 0.95rem; margin-bottom: 1.5rem;">
                We publish twice monthly during the academic term, featuring practical study advice without marketing spam. Unsubscribe at any time with a single click.
            </p>

            <form action="/newsletter.php" method="POST" class="js-newsletter-form" novalidate>
                <div class="form-group">
                    <label for="newsletter-email" class="form-label form-label-required">Email Address</label>
                    <input type="email" id="newsletter-email" name="email" class="form-input" required maxlength="255" placeholder="parent@example.co.uk" value="<?= e($submittedEmail ?? '') ?>">
                    <div class="form-hint">We respect your inbox. Your email is securely hashed and protected.</div>
                </div>

                <div class="form-group">
                    <label class="form-checkbox-label">
                        <input type="checkbox" name="consent" value="1" required class="form-checkbox" <?= (!empty($submittedConsent)) ? 'checked' : '' ?>>
                        <span>I consent to receiving educational updates, exam tips, and revision guides from AppTutors UK (implemented as an explicit-consent engineering control; pending client/legal approval of authoritative policy).</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Subscribe to Free Revision Guides &rarr;</button>
                <div class="js-newsletter-status" style="display: none; margin-top: 1rem;"></div>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-navy-200);">
                <div class="alert alert-notice" style="margin: 0;">
                    <div>
                        <span class="badge badge-open-decision" style="margin-bottom: 0.35rem;">OPEN CLIENT DECISION</span>
                        <div style="font-weight: 700; font-size: 0.85rem; margin-bottom: 0.25rem;">Double Opt-In Email Workflow</div>
                        <div style="font-size: 0.8rem; color: var(--color-navy-700);">
                            The client marketing and legal team will decide whether new subscribers require a confirmation email token verification before moving to ACTIVE status.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
