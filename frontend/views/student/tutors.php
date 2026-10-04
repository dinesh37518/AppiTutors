<!-- Public Tutor Directory Landing & Safeguarding Gate Preview -->
<div class="container section">
    <div style="max-width: 1000px; margin: 0 auto;">
        <div style="margin-bottom: 3.5rem; text-align: center;">
            <span class="badge badge-verified" style="margin-bottom: 0.75rem;">Phase 6 Directory Preview</span>
            <h1 style="margin-bottom: 1rem;">Find a Verified UK Tutor</h1>
            <p style="font-size: 1.15rem; color: var(--color-navy-600); max-width: 720px; margin: 0 auto;">
                Search across verified educators specializing in Primary, Key Stage 3, GCSE, and A-Level curricula.
            </p>
        </div>

        <!-- Strict Safeguarding Gate Advisory -->
        <div class="alert alert-notice" style="margin-bottom: 3rem;">
            <div>
                <span class="badge badge-primary" style="margin-bottom: 0.5rem;">DISC-028 Safeguarding Gate</span>
                <div style="font-weight: 700; margin-bottom: 0.25rem;">Safeguarding Enforcement Notice</div>
                <div style="font-size: 0.875rem; line-height: 1.6;">
                    The live tutor directory and interactive calendar slots are scheduled for release in <strong>Phase 5 (Tutor Workflow)</strong> and <strong>Phase 6 (Directory & Booking)</strong>. In strict compliance with our non-negotiable security baseline, tutors only become bookable and indexed after:
                    <br>&bull; <code>tutor_profiles.approval_status = 'APPROVED'</code> (Explicit manager sign-off)
                    <br>&bull; <code>tutor_profiles.dbs_status = 'VERIFIED'</code> (Validated Enhanced DBS certificate)
                </div>
            </div>
        </div>

        <!-- Search & Filter Controls (UI Representation) -->
        <div class="card" style="padding: 2rem; margin-bottom: 3rem; background: var(--color-white); box-shadow: var(--shadow-md);">
            <div style="font-weight: 700; font-size: 1.1rem; color: var(--color-navy-950); margin-bottom: 1rem;">Search Filters</div>
            <div class="grid-3" style="gap: 1.25rem;">
                <div>
                    <label for="filter-subject" class="form-label">Subject</label>
                    <select id="filter-subject" class="form-select" disabled>
                        <option>All Subjects (Maths, English, Sciences...)</option>
                    </select>
                </div>
                <div>
                    <label for="filter-level" class="form-label">Key Stage / Level</label>
                    <select id="filter-level" class="form-select" disabled>
                        <option>All Levels (Primary, KS3, GCSE, A-Level)</option>
                    </select>
                </div>
                <div>
                    <label for="filter-mode" class="form-label">Lesson Delivery</label>
                    <select id="filter-mode" class="form-select" disabled>
                        <option>Online or In-Person (London/Regional)</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <span style="font-size: 0.85rem; color: var(--color-navy-500);">Interactive live database querying activates in Phase 6.</span>
                <button type="button" class="btn btn-primary" disabled style="opacity: 0.75; cursor: not-allowed;">Search Directory (Phase 6)</button>
            </div>
        </div>

        <!-- Card Layout Demonstration -->
        <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.35rem;">Verified Tutor Profile Architecture</h2>
            <span class="badge badge-placeholder">CLIENT APPROVAL REQUIRED</span>
        </div>

        <div class="grid-3" style="grid-template-columns: 1fr; gap: 2rem;">
            <!-- Example Verified Card -->
            <div class="card" style="padding: 2.25rem; border-left: 4px solid var(--color-emerald-500);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                            <h3 style="font-size: 1.3rem; margin: 0;">Dr. Alistair H.</h3>
                            <span class="badge badge-verified">&#10003; Enhanced DBS Verified</span>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--color-navy-600);">
                            MSc, PhD Biochemistry (Imperial College London) • 8+ Years Tutoring
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.25rem; font-weight: 800; color: var(--color-primary-600);">&pound;50 / hr</div>
                        <span class="badge badge-placeholder">CLIENT REVIEW</span>
                    </div>
                </div>

                <p style="color: var(--color-navy-700); font-size: 0.95rem; margin-bottom: 1.25rem; line-height: 1.6;">
                    Specialist A-Level Chemistry and GCSE Triple Science educator. Proven methodology focused on past paper mark scheme mastery and breaking down organic reaction mechanisms into intuitive principles.
                </p>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                    <span class="badge badge-primary">A-Level Chemistry (AQA / OCR)</span>
                    <span class="badge badge-primary">GCSE Biology & Physics</span>
                    <span class="badge badge-primary">Oxbridge Medicine Prep</span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1.25rem; border-top: 1px solid var(--color-navy-200);">
                    <span style="font-size: 0.85rem; color: var(--color-emerald-700); font-weight: 600;">
                        &#10003; Safeguarding Background Verified
                    </span>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="opacity: 0.75; cursor: not-allowed;">
                        View Profile & Availability (Phase 6)
                    </button>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 3.5rem;">
            <p style="color: var(--color-navy-600); margin-bottom: 1rem;">Are you a qualified UK educator wishing to join our verified tutor roster?</p>
            <a href="/register.php" class="btn btn-secondary">Apply to Become an AppTutors Educator &rarr;</a>
        </div>
    </div>
</div>
