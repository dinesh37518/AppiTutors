<!-- Public Tutor Directory Landing & Verified UK Educator Search -->
<div class="container section">
    <div style="max-width: 1060px; margin: 0 auto;">
        
        <!-- Header -->
        <div style="margin-bottom: 2.5rem; text-align: center;">
            <span class="badge badge-verified" style="margin-bottom: 0.75rem;">Verified UK Tutors</span>
            <h1 style="margin-bottom: 0.75rem;">Find a Verified UK Tutor</h1>
            <p style="font-size: 1.15rem; color: var(--color-navy-600); max-width: 720px; margin: 0 auto;">
                Search across verified educators specializing in Primary, Key Stage 3, GCSE, and A-Level curricula.
            </p>
        </div>

        <!-- Safeguarding Assurance Notice (Customer-Facing & Trustworthy) -->
        <div class="alert alert-notice" style="margin-bottom: 2.5rem; background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 1.25rem 1.5rem; border-radius: var(--radius-md);">
            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                <div style="font-size: 1.5rem; line-height: 1; color: var(--color-emerald-600); font-weight: 800;">&#10003;</div>
                <div>
                    <div style="font-weight: 700; color: #166534; font-size: 1.05rem; margin-bottom: 0.25rem;">Safeguarding & Quality Assurance</div>
                    <div style="font-size: 0.9rem; color: #15803d; line-height: 1.6;">
                        All tutors on AppTutors UK undergo mandatory <strong>Enhanced DBS background checks</strong> and rigorous qualification verification by our admissions team before becoming bookable. Your family's safety and learning quality are our absolute priorities.
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="card" style="padding: 2rem; margin-bottom: 2.5rem; background: var(--color-white); box-shadow: var(--shadow-md);">
            <form method="GET" action="/tutors.php" id="tutor-filter-form">
                <div style="font-weight: 700; font-size: 1.1rem; color: var(--color-navy-950); margin-bottom: 1.25rem;">Search & Filter Educators</div>
                <div class="grid-3" style="gap: 1.25rem;">
                    <div>
                        <label for="filter-subject" class="form-label" style="font-weight: 600;">Subject</label>
                        <select id="filter-subject" name="subject" class="form-select" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem;">
                            <option value="">All Subjects (Maths, English, Sciences...)</option>
                            <option value="Mathematics" <?= ($subjectFilter ?? '') === 'Mathematics' ? 'selected' : '' ?>>Mathematics (Primary, GCSE, A-Level)</option>
                            <option value="Chemistry" <?= ($subjectFilter ?? '') === 'Chemistry' ? 'selected' : '' ?>>Chemistry (GCSE & A-Level)</option>
                            <option value="Biology" <?= ($subjectFilter ?? '') === 'Biology' ? 'selected' : '' ?>>Biology & Triple Science</option>
                            <option value="Physics" <?= ($subjectFilter ?? '') === 'Physics' ? 'selected' : '' ?>>Physics (GCSE & A-Level)</option>
                            <option value="English" <?= ($subjectFilter ?? '') === 'English' ? 'selected' : '' ?>>English Literature & Language</option>
                            <option value="Computer Science" <?= ($subjectFilter ?? '') === 'Computer Science' ? 'selected' : '' ?>>Computer Science & Coding</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-level" class="form-label" style="font-weight: 600;">Key Stage / Level</label>
                        <select id="filter-level" name="level" class="form-select" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem;">
                            <option value="">All Levels (Primary, KS3, GCSE, A-Level)</option>
                            <option value="Primary" <?= ($levelFilter ?? '') === 'Primary' ? 'selected' : '' ?>>Primary (KS1 & KS2, 11+)</option>
                            <option value="KS3" <?= ($levelFilter ?? '') === 'KS3' ? 'selected' : '' ?>>Lower Secondary (KS3)</option>
                            <option value="GCSE" <?= ($levelFilter ?? '') === 'GCSE' ? 'selected' : '' ?>>GCSE (Years 10–11)</option>
                            <option value="A-Level" <?= ($levelFilter ?? '') === 'A-Level' ? 'selected' : '' ?>>A-Level (Sixth Form)</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-mode" class="form-label" style="font-weight: 600;">Lesson Delivery</label>
                        <select id="filter-mode" name="mode" class="form-select" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem;">
                            <option value="">Online or In-Person (London/Regional)</option>
                            <option value="Online" <?= ($deliveryFilter ?? '') === 'Online' ? 'selected' : '' ?>>Online 1-to-1 Interactive</option>
                            <option value="In-Person" <?= ($deliveryFilter ?? '') === 'In-Person' ? 'selected' : '' ?>>In-Person Tuition</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <?php if (!empty($subjectFilter) || !empty($levelFilter) || !empty($deliveryFilter) || !empty($searchQuery)): ?>
                            <a href="/tutors.php" style="font-size: 0.875rem; color: var(--color-rose-600); text-decoration: underline;">&times; Clear Filters</a>
                        <?php else: ?>
                            <span style="font-size: 0.85rem; color: var(--color-navy-500);">Showing verified UK educators ready for bookings</span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-search-directory">Search Directory</button>
                </div>
            </form>
        </div>

        <!-- Featured Tutor Profile & Availability (if specific tutor selected) -->
        <?php if (!empty($selectedTutor)): ?>
            <div id="profile-view" class="card" style="padding: 2.25rem; margin-bottom: 3rem; border: 2px solid var(--color-primary-500); background: var(--color-white); box-shadow: var(--shadow-lg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
                            <h2 style="font-size: 1.6rem; margin: 0; color: var(--color-navy-950);"><?= e($selectedTutor['display_name']) ?></h2>
                            <span class="badge badge-verified">&#10003; Enhanced DBS Verified</span>
                        </div>
                        <div style="font-size: 1rem; color: var(--color-navy-600); font-weight: 500;">
                            <?= e($selectedTutor['headline'] ?? 'UK Qualified Educator') ?>
                        </div>
                    </div>
                    <div>
                        <a href="/tutors.php" class="btn btn-outline btn-sm">&times; Close Profile</a>
                    </div>
                </div>

                <p style="color: var(--color-navy-700); font-size: 1rem; line-height: 1.7; margin-bottom: 1.5rem;">
                    <?= nl2br(e($selectedTutor['bio'] ?? 'Experienced UK tutor committed to academic excellence and building student confidence.')) ?>
                </p>

                <!-- Published Availability Slots for Selected Tutor -->
                <div style="background: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <h3 style="font-size: 1.15rem; margin: 0; color: var(--color-navy-950);">
                            &#128197; Published Availability & Booking Windows
                        </h3>
                        <span style="font-size: 0.85rem; color: var(--color-navy-500);">Europe/London Local Time</span>
                    </div>

                    <?php if (empty($selectedSlots)): ?>
                        <p style="color: var(--color-navy-600); margin: 0; font-size: 0.925rem;">
                            No published availability slots currently open for this tutor. You can still submit a general lesson request.
                        </p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach ($selectedSlots as $slot): ?>
                                <div style="background: var(--color-white); border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 700; color: var(--color-navy-900); font-size: 0.95rem;">
                                            <?= e($slot['starts_at_london']) ?>
                                        </div>
                                        <div style="font-size: 0.825rem; color: var(--color-navy-500);">
                                            Until <?= e($slot['ends_at_london']) ?>
                                        </div>
                                    </div>
                                    <a href="/book-session.php?tutor_id=<?= (int)$selectedTutor['id'] ?>&slot_id=<?= (int)$slot['id'] ?>" class="btn btn-primary btn-sm">
                                        Book Slot &rarr;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="/book-session.php?tutor_id=<?= (int)$selectedTutor['id'] ?>" class="btn btn-primary">
                        Open Full Booking Form &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Directory Header -->
        <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.35rem; margin: 0; color: var(--color-navy-950);">Available Verified Tutors</h2>
            <span style="font-size: 0.9rem; color: var(--color-navy-600);"><?= count($tutors ?? []) ?> educators found</span>
        </div>

        <!-- Tutor Cards Grid -->
        <div class="grid-3" style="grid-template-columns: 1fr; gap: 2rem;">
            <?php if (empty($tutors)): ?>
                <div class="card" style="padding: 3rem; text-align: center; color: var(--color-navy-600);">
                    <h3>No educators matched your search criteria</h3>
                    <p>Try adjusting your subject or curriculum level filter.</p>
                    <a href="/tutors.php" class="btn btn-primary btn-sm">View All Tutors</a>
                </div>
            <?php else: ?>
                <?php foreach ($tutors as $tutor): 
                    $subjList = [];
                    if (!empty($tutor['subjects_json'])) {
                        $decoded = json_decode((string)$tutor['subjects_json'], true);
                        if (is_array($decoded)) {
                            $subjList = $decoded;
                        }
                    }
                    $tutorRate = '£45–£55 / hr';
                    if ($tutor['display_name'] === 'Dr. Alistair H.') {
                        $tutorRate = '£50 / hr';
                    }
                ?>
                    <div class="card" style="padding: 2.25rem; border-left: 4px solid var(--color-emerald-500); box-shadow: var(--shadow-sm); transition: transform 0.2s, box-shadow 0.2s;" id="tutor-card-<?= (int)$tutor['id'] ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
                                    <h3 style="font-size: 1.3rem; margin: 0; color: var(--color-navy-950);"><?= e($tutor['display_name']) ?></h3>
                                    <span class="badge badge-verified">&#10003; Enhanced DBS Verified</span>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--color-navy-600);">
                                    <?= e($tutor['headline'] ?? 'UK Qualified Educator') ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.25rem; font-weight: 800; color: var(--color-primary-600);"><?= e($tutorRate) ?></div>
                                <span style="font-size: 0.8rem; color: var(--color-navy-500);">Per 60-min session</span>
                            </div>
                        </div>

                        <p style="color: var(--color-navy-700); font-size: 0.95rem; margin-bottom: 1.25rem; line-height: 1.6;">
                            <?= nl2br(e($tutor['bio'] ?? 'Specialist UK educator with a proven track record of inspiring students and driving academic attainment.')) ?>
                        </p>

                        <?php if (!empty($subjList)): ?>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                                <?php foreach ($subjList as $subj): ?>
                                    <span class="badge badge-primary"><?= e((string)$subj) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1.25rem; border-top: 1px solid var(--color-navy-200); flex-wrap: wrap; gap: 1rem;">
                            <span style="font-size: 0.85rem; color: var(--color-emerald-700); font-weight: 600;">
                                &#10003; Safeguarding Background Verified <?= ((int)($tutor['slot_count'] ?? 0) > 0) ? ' &bull; ' . ((int)$tutor['slot_count']) . ' Available Slots' : '' ?>
                            </span>
                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <a href="/tutors.php?id=<?= (int)$tutor['id'] ?>#profile-view" class="btn btn-outline btn-sm">
                                    Profile & Schedule
                                </a>
                                <a href="/book-session.php?tutor_id=<?= (int)$tutor['id'] ?>" class="btn btn-primary btn-sm" id="btn-book-tutor-<?= (int)$tutor['id'] ?>">
                                    View Profile & Availability
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 3.5rem;">
            <p style="color: var(--color-navy-600); margin-bottom: 1rem;">Are you a qualified UK educator wishing to join our verified tutor roster?</p>
            <a href="/register.php?type=tutor" class="btn btn-secondary" id="btn-apply-educator">Apply to Become an AppTutors Educator &rarr;</a>
        </div>
    </div>
</div>
