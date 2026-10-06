<!-- Account Registration & Educator Application Portal -->
<div class="container section">
    <div style="max-width: <?= ($accountType === 'tutor') ? '760px' : '640px' ?>; margin: 0 auto;">
        <div style="margin-bottom: 2.5rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">
                <?= ($accountType === 'tutor') ? 'Educator Application' : 'Join AppTutors UK' ?>
            </span>
            <h1 style="margin-bottom: 0.75rem; font-size: 2.2rem;">
                <?= ($accountType === 'tutor') ? 'Apply to Become an Educator' : 'Create Your Account' ?>
            </h1>
            <p style="font-size: 1.05rem; color: var(--color-navy-600); max-width: 560px; margin: 0 auto;">
                <?= ($accountType === 'tutor') 
                    ? 'Join our verified roster of professional UK educators. Provide your academic qualifications and Enhanced DBS certificate to obtain tutor access.' 
                    : 'Connect with verified, Enhanced DBS-checked UK educators across Primary, GCSE, and A-Level curricula.' ?>
            </p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($currentUser) && $accountType === 'tutor'): ?>
            <div class="alert alert-notice" style="margin-bottom: 1.5rem; background: var(--color-navy-50); border: 1px solid var(--color-navy-200); color: var(--color-navy-800); padding: 0.85rem 1rem; border-radius: var(--radius-sm); font-size: 0.875rem;">
                <strong>Logged in as:</strong> <?= htmlspecialchars($currentUser['display_name']) ?> (<?= htmlspecialchars($currentUser['email']) ?>). Complete your educator credentials below to activate tutor access.
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-lg); background: var(--color-white);">
            
            <?php if ($accountType === 'tutor'): ?>
                <!-- Dedicated Tutor Application Header (Student/Parent Tabs Hidden) -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 2px solid var(--color-navy-100);">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.35rem;" aria-hidden="true">&#9997;</span>
                        <div>
                            <strong style="font-size: 1.05rem; color: var(--color-navy-950); display: block;">Official UK Tutor Application</strong>
                            <span style="font-size: 0.8rem; color: var(--color-navy-600);">Qualifications, safeguarding certificates & teaching profile</span>
                        </div>
                    </div>
                    <span class="badge badge-verified">Tutor Onboarding</span>
                </div>
            <?php else: ?>
                <!-- Account Type Switcher Tabs for Student and Parent Registration -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1.75rem; border-bottom: 2px solid var(--color-navy-100); padding-bottom: 0.75rem;">
                    <a href="/register.php?type=student" 
                       id="tab-reg-student"
                       class="btn <?= ($accountType === 'student') ? 'btn-primary' : 'btn-outline' ?> btn-sm"
                       style="flex: 1; text-align: center; text-decoration: none;">
                        &#127891; Student
                    </a>
                    <a href="/register.php?type=parent" 
                       id="tab-reg-parent"
                       class="btn <?= ($accountType === 'parent') ? 'btn-primary' : 'btn-outline' ?> btn-sm"
                       style="flex: 1; text-align: center; text-decoration: none;">
                        &#128106; Parent
                    </a>
                    <a href="/register.php?type=tutor" 
                       id="tab-reg-tutor"
                       class="btn <?= ($accountType === 'tutor') ? 'btn-primary' : 'btn-outline' ?> btn-sm"
                       style="flex: 1; text-align: center; text-decoration: none;">
                        &#9997; Tutor
                    </a>
                </div>
            <?php endif; ?>

            <form action="/register.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="account_type" value="<?= htmlspecialchars($accountType ?? 'student', ENT_QUOTES, 'UTF-8') ?>">

                <!-- Role Explanation Callout -->
                <?php if ($accountType === 'tutor'): ?>
                    <div style="background: var(--color-emerald-50); border: 1px solid var(--color-emerald-500); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.85rem; color: var(--color-emerald-800); margin-bottom: 1.5rem;">
                        <strong>Tutor Access Grant</strong>: Submit your academic qualifications and Enhanced DBS certificate below to receive instant access to your Tutor Portal and manage your availability schedule.
                    </div>
                <?php elseif ($accountType === 'parent'): ?>
                    <div style="background: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.825rem; color: var(--color-navy-700); margin-bottom: 1.5rem;">
                        <strong>Parent Account</strong>: Register to browse approved UK tutors, register dependent children, and book 1-to-1 or group lessons.
                    </div>
                <?php else: ?>
                    <div style="background: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.825rem; color: var(--color-navy-700); margin-bottom: 1.5rem;">
                        <strong>Student Account</strong>: Register to access revision schedules and join live tutorial sessions. For students under 18, a linked parent/guardian email is required for safeguarding communication.
                    </div>
                <?php endif; ?>

                <?php
                    $defaultName = $_POST['display_name'] ?? ($currentUser['display_name'] ?? '');
                    $defaultEmail = $_POST['email'] ?? ($currentUser['email'] ?? '');
                ?>

                <!-- Section: Personal & Account Details -->
                <div class="grid-2" style="gap: 1.25rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label for="reg-name" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            <?= ($accountType === 'tutor') ? 'Full Name & Academic Title' : (($accountType === 'parent') ? 'Parent / Guardian Full Name' : 'Student Full Name') ?>
                        </label>
                        <input type="text" id="reg-name" name="display_name" class="form-input" required 
                               placeholder="<?= ($accountType === 'tutor') ? 'e.g. Dr. Eleanor Vance' : 'e.g. Sarah Jenkins' ?>"
                               value="<?= htmlspecialchars($defaultName, ENT_QUOTES, 'UTF-8') ?>" 
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                    </div>

                    <div class="form-group">
                        <label for="reg-email" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Email Address</label>
                        <input type="email" id="reg-email" name="email" class="form-input" required 
                               placeholder="name@example.co.uk" 
                               value="<?= htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8') ?>" 
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                    </div>
                </div>

                <!-- Parent Email Field (Required for Student Registration) -->
                <?php if ($accountType === 'student'): ?>
                    <div class="form-group" style="margin-bottom: 1.25rem; background: #f8fafc; padding: 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm);">
                        <label for="reg-parent-email" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--color-navy-900);">
                            Parent Email Address
                        </label>
                        <input type="email" id="reg-parent-email" name="parent_email" class="form-input" required
                               placeholder="parent.guardian@example.co.uk" 
                               value="<?= htmlspecialchars($_POST['parent_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm); font-size: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--color-navy-600); margin-top: 0.35rem;">
                            <strong>UK Safeguarding Requirement</strong>: Booking confirmations, class links, and lesson notes will be communicated to this verified parent address.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="reg-password" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Account Password</label>
                    <input type="password" id="reg-password" name="password" class="form-input" required minlength="6" 
                           placeholder="At least 6 characters" 
                           style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                </div>

                <?php if ($accountType === 'tutor'): ?>
                    <!-- Section: Tutor Qualifications & Credentials -->
                    <div style="border-top: 1px solid var(--color-navy-200); padding-top: 1.5rem; margin-top: 1.5rem; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.15rem; color: var(--color-navy-900); margin-bottom: 0.5rem;">Academic Qualifications & Specialisms</h3>
                        <p style="font-size: 0.85rem; color: var(--color-navy-600); margin-bottom: 1.25rem;">
                            Specify your degrees, universities, and pedagogical qualifications for learner matching.
                        </p>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="reg-qualifications" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                                Academic Degrees & Teaching Qualifications
                            </label>
                            <textarea id="reg-qualifications" name="qualifications" class="form-textarea" rows="3" required
                                      placeholder="e.g. BSc (Hons) Mathematics (First Class) — University of Warwick; PGCE Secondary Mathematics with QTS"
                                      style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem; font-family: inherit;"><?= htmlspecialchars($_POST['qualifications'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div style="font-size: 0.8rem; color: var(--color-navy-600); margin-top: 0.35rem;">
                                Include your degrees, issuing universities, and any professional teaching credentials (PGCE, QTS).
                            </div>
                        </div>

                        <div class="grid-2" style="gap: 1.25rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label for="reg-headline" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Teaching Headline</label>
                                <input type="text" id="reg-headline" name="headline" class="form-input" required maxlength="255"
                                       placeholder="e.g. Oxford Graduate — GCSE & A-Level Maths Specialist"
                                       value="<?= htmlspecialchars($_POST['headline'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                            </div>
                            <div class="form-group">
                                <label for="reg-rate" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">Hourly Rate (&pound; GBP)</label>
                                <input type="number" id="reg-rate" name="hourly_rate" class="form-input" min="20" max="250" step="5" required
                                       placeholder="45"
                                       value="<?= htmlspecialchars($_POST['hourly_rate'] ?? '45', ENT_QUOTES, 'UTF-8') ?>" 
                                       style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;">
                            </div>
                        </div>

                        <!-- Subjects Taught Selection -->
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Subject Specialisms (Select all you teach)</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.5rem; margin-bottom: 0.75rem;">
                                <?php
                                    $availableSubjects = [
                                        'GCSE Mathematics', 'A-Level Mathematics', 'Further Mathematics',
                                        'Physics (GCSE & A-Level)', 'Chemistry (GCSE & A-Level)', 'Biology (GCSE & A-Level)',
                                        'English Literature', 'English Language', 'Computer Science & Python', '11+ / Entrance'
                                    ];
                                    $selectedSubjects = $_POST['subjects'] ?? ['GCSE Mathematics', 'A-Level Mathematics'];
                                ?>
                                <?php foreach ($availableSubjects as $subj): ?>
                                    <label style="display: flex; align-items: center; gap: 0.45rem; font-size: 0.85rem; color: var(--color-navy-800); background: var(--color-navy-50); padding: 0.45rem 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--color-navy-200); cursor: pointer;">
                                        <input type="checkbox" name="subjects[]" value="<?= htmlspecialchars($subj, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($subj, (array)$selectedSubjects, true) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($subj, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="custom_subjects" class="form-input" placeholder="Other subjects (comma-separated, e.g. History, Economics)" value="<?= htmlspecialchars($_POST['custom_subjects'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.9rem;">
                        </div>

                        <!-- Educator Bio -->
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="reg-bio" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                                Educator Bio & Teaching Methodology
                            </label>
                            <textarea id="reg-bio" name="bio" class="form-textarea" rows="4" required
                                      placeholder="Detail your tutoring experience, approach to building student confidence, exam board familiarity (AQA, Edexcel, OCR), and lesson structuring methodology..."
                                      style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem; font-family: inherit;"><?= htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>

                    <!-- Section: Enhanced DBS Certificate & Safeguarding Credentials -->
                    <div style="background: #f0fdf4; border: 1.5px solid var(--color-emerald-500); border-radius: var(--radius-sm); padding: 1.35rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                            <span class="badge badge-verified">Safeguarding Mandatory</span>
                            <strong style="color: var(--color-emerald-800); font-size: 1rem;">Enhanced DBS Safeguarding Certificate</strong>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--color-emerald-700); margin-bottom: 1rem;">
                            To ensure child safeguarding across UK schools and households, tutors must provide their Enhanced DBS certificate credentials.
                        </p>

                        <div class="grid-2" style="gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="reg-dbs-number" class="form-label form-label-required" style="font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--color-navy-900);">
                                    Enhanced DBS Certificate Number
                                </label>
                                <input type="text" id="reg-dbs-number" name="dbs_certificate_number" class="form-input" required maxlength="50"
                                       placeholder="e.g. 001234567890"
                                       value="<?= htmlspecialchars($_POST['dbs_certificate_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--color-emerald-600); border-radius: var(--radius-sm); font-size: 1rem; background: #fff;">
                                <div style="font-size: 0.775rem; color: var(--color-emerald-800); margin-top: 0.25rem;">
                                    Standard 12-digit UK DBS disclosure number.
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="reg-dbs-date" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--color-navy-900);">
                                    Certificate Issue Date
                                </label>
                                <input type="date" id="reg-dbs-date" name="dbs_issue_date" class="form-input"
                                       value="<?= htmlspecialchars($_POST['dbs_issue_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                                       style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-emerald-500); border-radius: var(--radius-sm); font-size: 1rem; background: #fff;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 0.25rem;">
                            <label for="reg-dbs-file" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--color-navy-900);">
                                Upload Certificate Document / Scan (PDF, PNG, JPG — Max 5MB)
                            </label>
                            <input type="file" id="reg-dbs-file" name="dbs_file" class="form-input" accept=".pdf,image/png,image/jpeg"
                                   style="width: 100%; padding: 0.55rem; border: 1px solid var(--color-emerald-500); border-radius: var(--radius-sm); font-size: 0.9rem; background: #fff;">
                            <div style="font-size: 0.775rem; color: var(--color-emerald-800); margin-top: 0.35rem;">
                                Scans are stored in encrypted private server storage with zero public URL exposure.
                            </div>
                        </div>
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
                    <label class="form-checkbox-label" style="font-size: 0.85rem; color: var(--color-navy-700); display: flex; gap: 0.5rem; align-items: flex-start; cursor: pointer;">
                        <input type="checkbox" required name="terms" value="1" checked class="form-checkbox" style="margin-top: 0.2rem;">
                        <span>I accept the AppTutors UK Terms of Service, Safeguarding Standards, and Educator Code of Conduct.</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" id="btn-submit-registration" style="width: 100%; padding: 0.9rem; font-size: 1.05rem; font-weight: 700; cursor: pointer;">
                    <?= ($accountType === 'tutor') ? 'Submit Tutor Application & Access Portal &rarr;' : 'Create Account & Access Portal &rarr;' ?>
                </button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-navy-200); text-align: center; font-size: 0.9rem; color: var(--color-navy-600);">
                <?php if ($accountType === 'tutor'): ?>
                    Looking to book tutoring lessons instead? <a href="/register.php?type=student" style="font-weight: 600; color: var(--color-primary-600);">Register as a Student or Parent</a>
                <?php else: ?>
                    Already have an account? <a href="/login.php" style="font-weight: 600; color: var(--color-primary-600);">Sign in here</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
