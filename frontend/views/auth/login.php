<!-- Authentication Sign In Portal -->
<style>
.login-tabs-wrapper {
    display: flex;
    background: #E2E8F0;
    padding: 0.35rem;
    border-radius: 12px;
    margin-bottom: 1.25rem;
    gap: 0.35rem;
    position: relative;
}

.login-tab-btn {
    flex: 1;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    font-weight: 600;
    text-align: center;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    user-select: none;
    text-decoration: none;
}

.login-tab-btn.tab-active-user {
    background-color: #2563EB !important;
    color: #FFFFFF !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
    font-weight: 700;
}

.login-tab-btn.tab-active-manager {
    background-color: #0F172A !important;
    color: #FFFFFF !important;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.4);
    font-weight: 700;
}

.login-tab-btn.tab-inactive {
    background-color: transparent !important;
    color: #475569 !important;
    box-shadow: none;
    font-weight: 600;
}

.login-tab-btn.tab-inactive:hover {
    background-color: rgba(255, 255, 255, 0.6) !important;
    color: #0F172A !important;
}

.tab-badge {
    font-size: 0.75rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-weight: 700;
}
.tab-badge-active {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
    display: inline-block;
}
.tab-badge-inactive {
    display: none;
}
</style>

<div class="container section">
    <div style="max-width: 480px; margin: 0 auto;">
        <div style="margin-bottom: 2rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Account Access</span>
            <h1 style="margin-bottom: 0.5rem; font-size: 2rem;">Sign In to AppTutors UK</h1>
            <p style="color: var(--color-navy-600); font-size: 0.95rem;">
                Select your account category to access your portal.
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

        <!-- Category Selector: Student / User vs Manager -->
        <?php 
            $activeCat = $loginCategory ?? 'user'; 
            $defaultEmail = ($activeCat === 'manager') ? 'manager@apptutors.co.uk' : ($_POST['email'] ?? '');
            $defaultPass = ($activeCat === 'manager') ? 'Manager2026!' : '';
        ?>
        <div class="login-tabs-wrapper" role="tablist" aria-label="Login Category Selector">
            <a href="/login.php?type=user" 
               id="tab-student-user" 
               role="tab" 
               aria-selected="<?= $activeCat !== 'manager' ? 'true' : 'false' ?>"
               class="login-tab-btn <?= $activeCat !== 'manager' ? 'tab-active-user' : 'tab-inactive' ?>">
                <span>🎓 Student / User</span>
                <span id="badge-student-user" class="tab-badge <?= $activeCat !== 'manager' ? 'tab-badge-active' : 'tab-badge-inactive' ?>">✓ Active</span>
            </a>

            <a href="/login.php?type=manager" 
               id="tab-manager" 
               role="tab" 
               aria-selected="<?= $activeCat === 'manager' ? 'true' : 'false' ?>"
               class="login-tab-btn <?= $activeCat === 'manager' ? 'tab-active-manager' : 'tab-inactive' ?>">
                <span>🛡️ Manager</span>
                <span id="badge-manager" class="tab-badge <?= $activeCat === 'manager' ? 'tab-badge-active' : 'tab-badge-inactive' ?>">✓ Active</span>
            </a>
        </div>

        <!-- Live Selection Status Indicator -->
        <div id="selection-indicator-bar" style="margin-bottom: 1.25rem; padding: 0.65rem 1rem; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; transition: all 0.2s ease; <?= $activeCat === 'manager' ? 'background: #F1F5F9; border: 1.5px solid #0F172A; color: #0F172A;' : 'background: #EFF6FF; border: 1.5px solid #3B82F6; color: #1E40AF;' ?>">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span id="selection-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; <?= $activeCat === 'manager' ? 'background: #0F172A;' : 'background: #2563EB;' ?>"></span>
                <span id="selection-text">Selected Role: <strong style="<?= $activeCat === 'manager' ? 'color: #0F172A;' : 'color: #1E40AF;' ?>"><?= $activeCat === 'manager' ? '🛡️ Manager Portal (Administrative)' : '🎓 Student / Parent / Tutor Portal' ?></strong></span>
            </div>
            <span id="selection-pill" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.2rem 0.5rem; border-radius: 4px; <?= $activeCat === 'manager' ? 'background: #0F172A; color: #ffffff;' : 'background: #2563EB; color: #ffffff;' ?>">
                <?= $activeCat === 'manager' ? 'MANAGER' : 'STUDENT / USER' ?>
            </span>
        </div>

        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-lg);">
            <div id="category-description" style="background: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.825rem; color: var(--color-navy-700); margin-bottom: 1.5rem;">
                <?php if ($activeCat === 'manager'): ?>
                    <strong>Manager Access</strong>: Privileged administrative access for application review, tutor approval, and governance.
                <?php else: ?>
                    <strong>Student / User Access</strong>: For Students, Parents, and Tutors accessing their dashboard and schedules.
                <?php endif; ?>
            </div>

            <!-- Quick Switch Prompt for Manager (when on Student/User tab) -->
            <div id="quick-manager-switch" style="display: <?= $activeCat !== 'manager' ? 'flex' : 'none' ?>; align-items: center; justify-content: space-between; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 6px; padding: 0.65rem 0.85rem; margin-bottom: 1.25rem; font-size: 0.825rem; color: #92400E;">
                <span>Need admin access? Try Manager Portal with demo credentials.</span>
                <a href="/login.php?type=manager" id="quick-manager-link" style="color: #D97706; font-weight: 700; text-decoration: underline;">
                    Switch to Manager &rarr;
                </a>
            </div>

            <!-- Google Sign-In Option (Enabled for Student / User) -->
            <div id="google-signin-container" style="<?= $activeCat === 'manager' ? 'display: none;' : '' ?> margin-bottom: 1.5rem;">
                <form id="google-login-form" action="/login.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="login_category" id="google-category-input" value="<?= htmlspecialchars($activeCat, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="google_id_token" id="google-id-token" value="">

                    <button type="button" id="google-signin-btn" class="btn btn-outline" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 0.75rem 1rem; font-size: 0.95rem; font-weight: 600; border: 1px solid var(--color-navy-200); background: #ffffff; color: var(--color-navy-800); border-radius: var(--radius-sm); cursor: pointer;">
                        <!-- Official Google SVG Icon -->
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink: 0;">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.15z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.24v3.15C3.26 21.36 7.34 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.24C.45 8.16 0 9.98 0 12c0 2.02.45 3.84 1.24 5.42l4.04-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.24 6.58l4.04 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                        <span>Continue with Google</span>
                    </button>
                </form>

                <div style="display: flex; align-items: center; margin: 1.5rem 0 1rem; color: var(--color-navy-400); font-size: 0.85rem;">
                    <div style="flex: 1; height: 1px; background: var(--color-navy-200);"></div>
                    <span style="padding: 0 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500;">or sign in with email</span>
                    <div style="flex: 1; height: 1px; background: var(--color-navy-200);"></div>
                </div>
            </div>

            <!-- Demo Manager Credentials Quick-Access Card -->
            <div id="manager-credentials-box" style="display: <?= $activeCat === 'manager' ? 'block' : 'none' ?>; background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 8px; padding: 1.15rem 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.25rem;" aria-hidden="true">&#128273;</span>
                        <strong style="color: #92400E; font-size: 1rem;">Demo Manager Credentials</strong>
                    </div>
                    <button type="button" id="btn-autofill-demo" class="btn btn-sm" style="background: #D97706; border-color: #D97706; color: #ffffff; padding: 0.4rem 0.85rem; font-size: 0.85rem; cursor: pointer; font-weight: 700; border-radius: 6px;">
                        &#9889; Auto-Fill Demo Credentials
                    </button>
                </div>
                <div style="background: #ffffff; border: 1px solid #FDE68A; border-radius: 6px; padding: 0.85rem; margin-bottom: 0.65rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.25rem 0; border-bottom: 1px dashed #F3F4F6;">
                        <span style="font-size: 0.85rem; color: #6B7280; font-weight: 600;">Email ID:</span>
                        <code style="background: #FEF3C7; color: #92400E; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.95rem;">manager@apptutors.co.uk</code>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.25rem 0; margin-top: 0.35rem;">
                        <span style="font-size: 0.85rem; color: #6B7280; font-weight: 600;">Password:</span>
                        <code style="background: #FEF3C7; color: #92400E; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.95rem;">Manager2026!</code>
                    </div>
                </div>
                <div style="font-size: 0.8rem; color: #B45309; line-height: 1.4;">
                    Click <strong>"&#9889; Auto-Fill Demo Credentials"</strong> to instantly populate the form and access the Manager Governance Portal.
                </div>
            </div>

            <!-- Standard Email / Password Form -->
            <form action="/login.php" method="POST" id="standard-login-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="login_category" id="form-category-input" value="<?= htmlspecialchars($activeCat, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="login-email" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Email Address</label>
                    <input type="email" id="login-email" name="email" class="form-input" required placeholder="user@example.co.uk" value="<?= htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem; transition: background-color 0.3s ease;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <label for="login-password" class="form-label form-label-required" style="font-weight: 600; margin: 0;">Password</label>
                    </div>
                    <input type="password" id="login-password" name="password" class="form-input" required placeholder="••••••••••••" value="<?= htmlspecialchars($defaultPass, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem; transition: background-color 0.3s ease;">
                </div>

                <button type="submit" id="btn-login-submit" class="btn btn-primary btn-lg" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; cursor: pointer;">
                    <?= $activeCat === 'manager' ? 'Sign In as Manager &rarr;' : 'Sign In to Dashboard &rarr;' ?>
                </button>
            </form>

            <div id="register-link-container" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-navy-200); text-align: center; font-size: 0.9rem; color: var(--color-navy-600); <?= $activeCat === 'manager' ? 'display: none;' : '' ?>">
                Don't have an account yet? <a href="/register.php" style="font-weight: 600; color: var(--color-primary-600);">Create an account</a>
            </div>
            <div id="manager-notice-container" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-navy-200); text-align: center; font-size: 0.85rem; color: var(--color-navy-500); <?= $activeCat !== 'manager' ? 'display: none;' : '' ?>">
                Privileged administrative access. Platform oversight, safeguarding verification, and business reporting.
            </div>
        </div>
    </div>
</div>
