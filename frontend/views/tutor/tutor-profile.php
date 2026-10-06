<!-- Tutor Profile Management Interface -->
<div class="container section">
    <div style="max-width: 900px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Tutor Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Profile & Safeguarding</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: gap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Tutor Profile & Onboarding</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Manage your teaching profile, qualifications, and DBS safeguarding credentials.
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="/tutor-availability.php" class="btn btn-secondary btn-sm">Availability Schedule</a>
                    <a href="/tutor-bookings.php" class="btn btn-secondary btn-sm">Lesson Requests</a>
                    <a href="/tutor-blog.php" class="btn btn-secondary btn-sm">Blog Articles</a>
                </div>
            </div>
        </div>

        <!-- Success & Error Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Success:</span>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Safeguarding Bookability Gate Banner -->
        <div class="card" style="padding: 1.75rem; margin-bottom: 2rem; border-left: 4px solid <?= (!empty($profile['is_bookable'])) ? 'var(--color-emerald-600)' : 'var(--color-amber-500)' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-weight: 700; font-size: 1.1rem; color: var(--color-navy-950);">
                            Safeguarding & Bookability Gate
                        </span>
                        <?php if (!empty($profile['is_bookable'])): ?>
                            <span class="badge badge-verified">LIVE & BOOKABLE</span>
                        <?php else: ?>
                            <span class="badge badge-open-decision">VERIFICATION REQUIRED</span>
                        <?php endif; ?>
                    </div>
                    <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 0.75rem;">
                        Under the platform's mandatory safeguarding architecture, prospective tutors remain non-bookable and excluded from public directory queries until manager approval is granted and DBS credentials are fully verified.
                    </p>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem;">
                        <div>
                            <strong>Approval Status:</strong>
                            <span class="badge <?= ($profile['approval_status'] ?? '') === 'APPROVED' ? 'badge-verified' : 'badge-primary' ?>">
                                <?= e($profile['approval_status'] ?? 'PENDING') ?>
                            </span>
                        </div>
                        <div>
                            <strong>DBS Status:</strong>
                            <span class="badge <?= ($profile['dbs_status'] ?? '') === 'VERIFIED' ? 'badge-verified' : (($profile['dbs_status'] ?? '') === 'SUBMITTED' ? 'badge-open-decision' : 'badge-placeholder') ?>">
                                <?= e($profile['dbs_status'] ?? 'NOT_SUBMITTED') ?>
                            </span>
                        </div>
                        <div>
                            <strong>Account State:</strong>
                            <span class="badge <?= ($profile['status'] ?? '') === 'ACTIVE' ? 'badge-verified' : 'badge-primary' ?>">
                                <?= e($profile['status'] ?? 'PENDING') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 1: Tutor Profile Information Form -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <h2 style="font-size: 1.35rem; margin-bottom: 0.5rem;">1. Teaching Profile Details</h2>
            <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 1.75rem;">
                This information will appear on your public educator profile once approved by platform admissions managers.
            </p>

            <form action="/tutor-profile.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="form_action" value="update_profile">
                
                <div class="form-group">
                    <label for="headline" class="form-label form-label-required">Professional Headline</label>
                    <input type="text" id="headline" name="headline" class="form-input" maxlength="255" required placeholder="e.g. Cambridge Graduate & Experienced GCSE / A-Level Mathematics Specialist" value="<?= e($profile['headline'] ?? '') ?>">
                    <div class="form-hint">A concise summary of your academic background and tutoring specialisms (max 255 characters).</div>
                </div>

                <div class="form-group">
                    <label for="bio" class="form-label form-label-required">Educator Biography & Teaching Approach</label>
                    <textarea id="bio" name="bio" class="form-textarea" rows="6" required placeholder="Detail your pedagogical approach, experience with major UK exam boards (AQA, Edexcel, OCR), and lesson structuring methodology..."><?= e($profile['bio'] ?? '') ?></textarea>
                </div>

                <div class="grid-2" style="gap: 1.5rem;">
                    <div class="form-group">
                        <label for="subjects" class="form-label form-label-required">Primary Subjects Taught</label>
                        <input type="text" id="subjects" name="subjects" class="form-input" required placeholder="e.g. Mathematics, Further Maths, Physics" value="<?= e(is_array($profile['subjects'] ?? null) ? implode(', ', $profile['subjects']) : '') ?>">
                        <div class="form-hint">Comma-separated list of subjects you offer.</div>
                    </div>

                    <div class="form-group">
                        <label for="curriculum" class="form-label form-label-required">Key Stages & Curricula</label>
                        <input type="text" id="curriculum" name="curriculum" class="form-input" required placeholder="e.g. KS3, GCSE (KS4), A-Level (KS5)" value="<?= e(is_array($profile['curriculum'] ?? null) ? implode(', ', $profile['curriculum']) : '') ?>">
                        <div class="form-hint">e.g. Primary, 11+, KS3, GCSE, A-Level.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="qualifications" class="form-label form-label-required">Academic Degrees & Teaching Certifications</label>
                    <textarea id="qualifications" name="qualifications" class="form-textarea" rows="3" required placeholder="e.g. BSc (Hons) Mathematics (First Class), University of Warwick; PGCE Secondary Mathematics"><?= e($profile['qualifications'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Save Profile Details &rarr;</button>
            </form>
        </div>

        <!-- Section 2: Enhanced DBS Safeguarding Documentation -->
        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                <span class="badge badge-verified">Safeguarding Mandatory</span>
            </div>
            <h2 style="font-size: 1.35rem; margin-bottom: 0.5rem;">2. Enhanced DBS Verification Submission</h2>
            <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 1.5rem;">
                To safeguard child learners, all UK tutors must provide a verifiable Enhanced DBS certificate. Certificate scans are stored in encrypted private storage outside the web root.
            </p>

            <form action="/tutor-profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="form_action" value="submit_dbs">

                <div class="grid-2" style="gap: 1.5rem;">
                    <div class="form-group">
                        <label for="certificate_number" class="form-label form-label-required">DBS Certificate Number</label>
                        <input type="text" id="certificate_number" name="certificate_number" class="form-input" required maxlength="50" placeholder="e.g. 001234567890" value="<?= e($dbsMeta['certificate_number'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="issue_date" class="form-label form-label-required">Certificate Issue Date</label>
                        <input type="date" id="issue_date" name="issue_date" class="form-input" required value="<?= e($dbsMeta['issue_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="dbs_document" class="form-label">Upload Certificate Scan (PDF, PNG, JPG — Max 5MB)</label>
                    <input type="file" id="dbs_document" name="dbs_document" class="form-input" accept=".pdf,image/png,image/jpeg">
                    <?php if (!empty($dbsMeta['stored_filename'])): ?>
                        <div style="font-size: 0.825rem; color: var(--color-emerald-700); margin-top: 0.45rem; font-weight: 600;">
                            &#10003; Document on file: <?= e($dbsMeta['original_filename'] ?? 'dbs_document.pdf') ?> (Submitted: <?= e($dbsMeta['submitted_at_utc'] ?? '') ?> UTC)
                        </div>
                    <?php else: ?>
                        <div class="form-hint">Stored strictly in private server storage with zero public URL exposure. Physical evidence retention duration is an open client decision (DISC-020).</div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-emerald" style="margin-top: 0.5rem;">Submit DBS for Manager Verification &rarr;</button>
            </form>
        </div>

    </div>
</div>
