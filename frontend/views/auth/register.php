<!-- Account Registration Portal -->
<div class="container section">
    <div style="max-width: 640px; margin: 0 auto;">
        <div style="margin-bottom: 2.5rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Join AppTutors UK</span>
            <h1 style="margin-bottom: 0.75rem; font-size: 2.2rem;">Create Your Account</h1>
            <p style="font-size: 1.05rem; color: var(--color-navy-600); max-width: 520px; margin: 0 auto;">
                Connect with verified, Enhanced DBS-checked UK educators across Primary, GCSE, and A-Level curricula.
            </p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-lg);">
            
            <!-- Account Type Switcher Tabs -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.75rem; border-bottom: 2px solid var(--color-navy-100); padding-bottom: 0.75rem;">
                <a href="/register.php?type=student" 
                   class="btn <?= ($accountType !== 'tutor') ? 'btn-primary' : 'btn-outline' ?> btn-sm"
                   style="flex: 1; text-align: center; text-decoration: none;">
                    &#128106; Parent / Student
                </a>
                <a href="/register.php?type=tutor" 
                   class="btn <?= ($accountType === 'tutor') ? 'btn-primary' : 'btn-outline' ?> btn-sm"
                   style="flex: 1; text-align: center; text-decoration: none;">
                    &#127891; Professional Tutor
                </a>
            </div>

            <form action="/register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="account_type" value="<?= htmlspecialchars($accountType ?? 'student', ENT_QUOTES, 'UTF-8') ?>">

                <!-- Role Explanation Callout -->
                <?php if ($accountType === 'tutor'): ?>
                    <div style="background: var(--color-emerald-50); border: 1px solid var(--color-emerald-500); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.825rem; color: var(--color-emerald-800); margin-bottom: 1.5rem;">
                        <strong>Tutor Safeguarding Notice</strong>: New tutor profiles begin in <code>PENDING</code> status. You will be able to complete your qualifications, upload DBS credentials, and publish calendar slots once verified.
                    </div>
                <?php else: ?>
                    <div style="background: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.825rem; color: var(--color-navy-700); margin-bottom: 1.5rem;">
                        <strong>Immediate Family Access</strong>: Register to browse approved UK tutors, register dependent children, and request 1-to-1 lessons.
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="reg-name" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        <?= ($accountType === 'tutor') ? 'Full Name & Academic Title' : 'Your Full Name (Parent or Student)' ?>
                    </label>
                    <input type="text" id="reg-name" name="display_name" class="form-input" required 
                           placeholder="<?= ($accountType === 'tutor') ? 'e.g. Dr. Eleanor Vance' : 'e.g. Sarah Jenkins' ?>"
                           value="<?= htmlspecialchars($_POST['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="reg-email" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Email Address</label>
                    <input type="email" id="reg-email" name="email" class="form-input" required 
                           placeholder="name@example.co.uk" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="reg-password" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Password</label>
                    <input type="password" id="reg-password" name="password" class="form-input" required minlength="6" 
                           placeholder="At least 6 characters" 
                           style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                </div>

                <?php if ($accountType === 'tutor'): ?>
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label for="reg-headline" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Teaching Specialism Headline</label>
                        <input type="text" id="reg-headline" name="headline" class="form-input" 
                               placeholder="e.g. Oxford Graduate — GCSE & A-Level Maths & Physics Specialist"
                               value="<?= htmlspecialchars($_POST['headline'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="reg-rate" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Hourly Rate (&pound; GBP)</label>
                        <input type="number" id="reg-rate" name="hourly_rate" class="form-input" min="20" max="250" step="5"
                               placeholder="45"
                               value="<?= htmlspecialchars($_POST['hourly_rate'] ?? '45', ENT_QUOTES, 'UTF-8') ?>" 
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <label for="reg-phone" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Phone (Optional)</label>
                            <input type="tel" id="reg-phone" name="phone" class="form-input" placeholder="07123456789"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                        </div>
                        <div>
                            <label for="reg-postcode" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">UK Postcode (Optional)</label>
                            <input type="text" id="reg-postcode" name="postcode" class="form-input" placeholder="e.g. SW1A 1AA"
                                   value="<?= htmlspecialchars($_POST['postcode'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-checkbox-label" style="font-size: 0.85rem; color: var(--color-navy-700); display: flex; gap: 0.5rem; align-items: flex-start;">
                        <input type="checkbox" required name="terms" value="1" checked class="form-checkbox" style="margin-top: 0.2rem;">
                        <span>I accept the AppTutors UK Terms of Service, Safeguarding Standards, and Privacy Notice.</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; cursor: pointer;">
                    <?= ($accountType === 'tutor') ? 'Submit Tutor Application &rarr;' : 'Create Account & Access Portal &rarr;' ?>
                </button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-navy-200); text-align: center; font-size: 0.9rem; color: var(--color-navy-600);">
                Already have an account? <a href="/login.php" style="font-weight: 600; color: var(--color-primary-600);">Sign in here</a>
            </div>
        </div>
    </div>
</div>
