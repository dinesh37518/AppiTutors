<!-- Dedicated Newsletter Unsubscribe Page -->
<div class="container section">
    <div style="max-width: 600px; margin: 0 auto;">
        <div style="margin-bottom: 2rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Email Preferences</span>
            <h1 style="margin-bottom: 0.75rem;">Newsletter Unsubscribe</h1>
            <p style="font-size: 1.05rem; color: var(--color-navy-600);">
                We are sorry to see you go. You can easily manage or cancel your newsletter subscription below.
            </p>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Preferences Updated:</span>
                <span><?= e($successMessage) ?></span>
            </div>
            <div class="card" style="padding: 2rem; text-align: center;">
                <p style="color: var(--color-navy-700); margin-bottom: 1.5rem;">
                    Your email address has been marked as unsubscribed and you will no longer receive educational bulletin updates from AppTutors UK.
                </p>
                <a href="/" class="btn btn-outline">Return to Homepage</a>
            </div>
        <?php elseif (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Notice:</span>
                <span><?= e($errorMessage) ?></span>
            </div>
            <div class="card" style="padding: 2rem;">
                <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Manual Token Entry</h2>
                <p style="color: var(--color-navy-600); font-size: 0.95rem; margin-bottom: 1.5rem;">
                    Please paste the unsubscribe token provided in your email footer below:
                </p>
                <form action="/unsubscribe.php" method="POST">
                    <?= \App\Support\Csrf::renderInput() ?>
                    <div class="form-group">
                        <label for="unsub-token" class="form-label form-label-required">Unsubscribe Token</label>
                        <input type="text" id="unsub-token" name="token" class="form-input" required placeholder="Paste secure token" value="<?= e($token ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Confirm Unsubscribe</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card" style="padding: 2rem;">
                <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Confirm Your Unsubscribe Request</h2>
                <p style="color: var(--color-navy-600); font-size: 0.95rem; margin-bottom: 1.5rem;">
                    Click below to confirm that you wish to opt out of the AppTutors UK educational updates and revision bulletins.
                </p>
                <form action="/unsubscribe.php" method="POST">
                    <?= \App\Support\Csrf::renderInput() ?>
                    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Unsubscribe Now</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
