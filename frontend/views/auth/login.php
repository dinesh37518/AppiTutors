<!-- Authentication Sign In Portal -->
<div class="container section">
    <div style="max-width: 480px; margin: 0 auto;">
        <div style="margin-bottom: 2.5rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Account Access</span>
            <h1 style="margin-bottom: 0.75rem; font-size: 2rem;">Sign In to AppTutors UK</h1>
            <p style="color: var(--color-navy-600); font-size: 0.95rem;">
                Access your parent, student, tutor, or manager dashboard.
            </p>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 1.5rem;">
                <span style="font-weight: 700;">&#10003; Notice:</span>
                <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-lg);">
            <div style="background: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.8rem; color: var(--color-navy-700); margin-bottom: 1.5rem;">
                <strong>Security Architecture</strong>: Identity authentication is handled by Firebase Authentication. User roles, bookings, and permissions are verified server-side via MySQL.
            </div>

            <form action="/login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="login-email" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Email Address</label>
                    <input type="email" id="login-email" name="email" class="form-input" required placeholder="user@example.co.uk" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <label for="login-password" class="form-label form-label-required" style="font-weight: 600; margin: 0;">Password</label>
                    </div>
                    <input type="password" id="login-password" name="password" class="form-input" required placeholder="••••••••••••" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; cursor: pointer;">Sign In to Dashboard &rarr;</button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-navy-200); text-align: center; font-size: 0.9rem; color: var(--color-navy-600);">
                Don't have an account yet? <a href="/register.php" style="font-weight: 600; color: var(--color-primary-600);">Create an account</a>
            </div>
        </div>
    </div>
</div>
