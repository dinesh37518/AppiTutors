<!-- Parent/Guardian Child Management Interface -->
<div class="container section">
    <div style="max-width: 960px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav aria-label="Breadcrumb" style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Family Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Children & Dependents</span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Children & Dependents</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Manage educational profiles and UK curriculum tracks for your children.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="/student-profile.php" class="btn btn-outline btn-sm">
                        &larr; My Profile
                    </a>
                    <a href="#add-child-card" class="btn btn-primary btn-sm">
                        + Add Child
                    </a>
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

        <!-- Phase Boundary Notice -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--color-primary-500); background-color: var(--color-navy-50);">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.25rem; line-height: 1;" aria-hidden="true">&#9432;</span>
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.25rem;">
                        Educational Profile Onboarding (Phase 6)
                    </h2>
                    <p style="color: var(--color-navy-600); font-size: 0.9rem; margin-bottom: 0; line-height: 1.5;">
                        Establishing child profiles links educational stage and curriculum focus to your family account. To preserve strict architectural boundaries, lesson booking, availability matching, and tutor assignment are reserved for the Phase 7 Booking Engine.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 1: Children List -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 style="font-size: 1.35rem; color: var(--color-navy-950); margin-bottom: 0.25rem;">
                        Registered Children (<?= count($children ?? []) ?>)
                    </h2>
                    <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 0;">
                        Dependents currently associated with your parental account.
                    </p>
                </div>
                <span class="badge badge-primary">Parent ID: <?= e((string)($currentUser->id ?? '')) ?></span>
            </div>

            <?php if (empty($children)): ?>
                <div style="text-align: center; padding: 3rem 1.5rem; background-color: var(--color-navy-50); border: 2px dashed var(--color-navy-200); border-radius: var(--radius-md);">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--color-navy-400);" aria-hidden="true">&#128106;</div>
                    <h3 style="font-size: 1.15rem; color: var(--color-navy-900); margin-bottom: 0.5rem;">No Children Added Yet</h3>
                    <p style="color: var(--color-navy-600); font-size: 0.925rem; max-width: 440px; margin: 0 auto 1.5rem;">
                        Add your child's details to configure their Key Stage and curriculum for 1-to-1 tutoring.
                    </p>
                    <a href="#add-child-card" class="btn btn-primary btn-sm">+ Add First Child</a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($children as $child): ?>
                        <div class="card" style="padding: 1.5rem; border: 1px solid var(--color-navy-200); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
                                    <h3 style="font-size: 1.2rem; color: var(--color-navy-950); margin: 0;">
                                        <?= e($child['first_name']) ?> <?= e($child['last_name'] ?? '') ?>
                                    </h3>
                                    <span class="badge badge-verified">Active</span>
                                </div>
                                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.9rem; color: var(--color-navy-600);">
                                    <div>
                                        <strong>School Year:</strong> <?= e($child['school_year'] ?? 'Not specified') ?>
                                    </div>
                                    <div>
                                        <strong>Curriculum:</strong> <?= e($child['curriculum'] ?? 'UK National') ?>
                                    </div>
                                    <div>
                                        <strong>Date of Birth:</strong> <?= !empty($child['date_of_birth']) ? e($child['date_of_birth']) : 'Protected' ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <!-- Delete / Deactivate Form -->
                                <form method="POST" action="/parent-children.php" onsubmit="return confirm('Are you sure you want to remove this child profile?');" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete_child">
                                    <input type="hidden" name="child_id" value="<?= e((string) $child['id']) ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-rose-600); border-color: var(--color-rose-100);" aria-label="Remove <?= e($child['first_name']) ?>'s profile">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section 2: Add New Child Form -->
        <div class="card" id="add-child-card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <h2 style="font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--color-navy-950);">
                Add a New Child Profile
            </h2>
            <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 1.75rem;">
                Multiple children can be registered under a single parent account. All information is secured in strict accordance with UK child data privacy safeguards.
            </p>

            <form method="POST" action="/parent-children.php" novalidate id="add-child-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="add_child">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label for="first_name" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            Child's First Name <span style="color: var(--color-rose-600);">*</span>
                        </label>
                        <input type="text"
                               id="first_name"
                               name="first_name"
                               class="form-control"
                               required
                               maxlength="100"
                               placeholder="e.g. Oliver"
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                               aria-describedby="first_name_hint">
                        <small id="first_name_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Required. Maximum 100 characters.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            Child's Last Name
                        </label>
                        <input type="text"
                               id="last_name"
                               name="last_name"
                               class="form-control"
                               maxlength="100"
                               placeholder="e.g. Smith"
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                               aria-describedby="last_name_hint">
                        <small id="last_name_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Optional. Defaults to family surname if omitted.
                        </small>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label for="date_of_birth" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            Date of Birth
                        </label>
                        <input type="date"
                               id="date_of_birth"
                               name="date_of_birth"
                               class="form-control"
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                               aria-describedby="dob_hint">
                        <small id="dob_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Format: YYYY-MM-DD. Optional. Cannot be in the future.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="school_year" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            School Academic Year
                        </label>
                        <select id="school_year"
                                name="school_year"
                                class="form-control"
                                style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                                aria-describedby="school_year_hint">
                            <option value="">-- Select Academic Year --</option>
                            <option value="Reception">Early Years (Reception)</option>
                            <option value="Year 1">Year 1 (KS1)</option>
                            <option value="Year 2">Year 2 (KS1 / SATs)</option>
                            <option value="Year 3">Year 3 (KS2)</option>
                            <option value="Year 4">Year 4 (KS2)</option>
                            <option value="Year 5">Year 5 (11+ Prep)</option>
                            <option value="Year 6">Year 6 (KS2 SATs / 11+)</option>
                            <option value="Year 7">Year 7 (KS3 Transition)</option>
                            <option value="Year 8">Year 8 (KS3)</option>
                            <option value="Year 9">Year 9 (KS3 / GCSE Foundation)</option>
                            <option value="Year 10">Year 10 (GCSE Year 1)</option>
                            <option value="Year 11">Year 11 (GCSE Exam Year)</option>
                            <option value="Year 12">Year 12 (A-Level / AS / IB)</option>
                            <option value="Year 13">Year 13 (A-Level Final Exam)</option>
                        </select>
                        <small id="school_year_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Current academic year group in the UK curriculum.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="curriculum" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            Curriculum Focus
                        </label>
                        <select id="curriculum"
                                name="curriculum"
                                class="form-control"
                                style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                                aria-describedby="curriculum_hint">
                            <option value="">-- Select Curriculum Track --</option>
                            <option value="National Curriculum">UK National Curriculum (General)</option>
                            <option value="GCSE / IGCSE">GCSE / IGCSE (Edexcel, AQA, OCR)</option>
                            <option value="A-Level">A-Level (Linear, OCR, Edexcel, AQA)</option>
                            <option value="11+ / Grammar School">11+ Grammar School Entrance (GL / CEM)</option>
                            <option value="International Baccalaureate">International Baccalaureate (IB)</option>
                            <option value="Scottish Highers">Scottish National 5 / Highers</option>
                        </select>
                        <small id="curriculum_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Primary syllabus or exam board standard.
                        </small>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" id="btn-add-child">
                        + Add Child Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Open Decision & Child Safeguarding Information -->
        <div class="card" style="padding: 1.5rem; background-color: var(--color-white); border-left: 4px solid var(--color-amber-500);">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.25rem; line-height: 1;" aria-hidden="true">&#9432;</span>
                <div>
                    <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.25rem;">
                        Safeguarding & Multi-Guardian Relationship Notice
                    </h3>
                    <p style="color: var(--color-navy-600); font-size: 0.875rem; margin-bottom: 0; line-height: 1.5;">
                        In accordance with the approved database schema, child profiles are linked to a single registering parent account (<code style="font-size: 0.85rem; background: var(--color-navy-100); padding: 0.1rem 0.3rem; border-radius: 3px;">parent_user_id</code>). Joint custody and multi-guardian access remain an open business decision (DISC-032). Child records are never accessible to unrelated parents or unverified tutors.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>
