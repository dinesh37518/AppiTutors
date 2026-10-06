<!-- Student/Parent Booking Workflow Interface -->
<div class="container section">
    <div style="max-width: 860px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav aria-label="Breadcrumb" style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Family Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Book a Session</span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Request a 1-to-1 Tutoring Session</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Select an approved UK educator and reserve a published availability slot for yourself or your child.
                    </p>
                </div>
                <div>
                    <a href="/student-bookings.php" class="btn btn-outline btn-sm">
                        View My Bookings &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Alert -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Success:</span>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Alert -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Concurrency & Booking Lifecycle Safeguards Notice -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--color-primary-500); background-color: var(--color-navy-50);">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.25rem; line-height: 1;" aria-hidden="true">&#9432;</span>
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.25rem;">
                        Booking Protection & Concurrency Safeguard
                    </h2>
                    <p style="color: var(--color-navy-600); font-size: 0.9rem; margin-bottom: 0; line-height: 1.5;">
                        Sessions are reserved via atomic database row locking to prevent double-booking. Newly submitted requests begin in <strong style="color: var(--color-primary-600);">PENDING</strong> status awaiting tutor confirmation. Tutors only accept bookings after mandatory Enhanced DBS verification.
                    </p>
                </div>
            </div>
        </div>

        <!-- Booking Form Card -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <form method="POST" action="/book-session.php" novalidate id="booking-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="create_booking">

                <!-- Step 1: Select Tutor -->
                <div class="form-group" style="margin-bottom: 1.75rem;">
                    <label for="tutor_user_id" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Select Verified Tutor <span style="color: var(--color-rose-600);">*</span>
                    </label>
                    <select id="tutor_user_id" 
                            name="tutor_user_id" 
                            class="form-control" 
                            required
                            onchange="window.location.href='/book-session.php?tutor_id=' + this.value"
                            style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                            aria-describedby="tutor_hint">
                        <option value="">-- Choose an Approved Tutor --</option>
                        <?php foreach ($tutors as $tutor): ?>
                            <option value="<?= e((string)$tutor['id']) ?>" <?= ($selectedTutorId === (int)$tutor['id']) ? 'selected' : '' ?>>
                                <?= e($tutor['display_name']) ?> &mdash; <?= e($tutor['headline'] ?? 'UK Qualified Educator') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="tutor_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                        Only tutors with manager approval and verified Enhanced DBS safeguarding appear here.
                    </small>
                </div>

                <!-- Step 2: Select Availability Slot -->
                <div class="form-group" style="margin-bottom: 1.75rem;">
                    <label for="slot_id" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Select Published Time Window <span style="color: var(--color-rose-600);">*</span>
                    </label>
                    <?php if (empty($availableSlots)): ?>
                        <div style="padding: 1rem; background-color: var(--color-navy-50); border: 1px dashed var(--color-navy-200); border-radius: var(--radius-sm); color: var(--color-navy-600); font-size: 0.925rem;">
                            <?php if ($selectedTutorId > 0): ?>
                                No published availability slots currently open for this tutor. Please select another tutor or check back later.
                            <?php else: ?>
                                Please select a tutor above to display their published calendar slots.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <select id="slot_id" 
                                name="slot_id" 
                                class="form-control" 
                                required
                                style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                                aria-describedby="slot_hint">
                            <option value="">-- Select Available Time Slot --</option>
                            <?php foreach ($availableSlots as $slot): ?>
                                <option value="<?= e((string)$slot['id']) ?>">
                                    [<?= (!empty($slot['is_group']) ? 'Group: Max ' . ((int)($slot['max_students'] ?? 1)) . ' Students' : '1-to-1') ?>] <?= e($slot['starts_at_london']) ?> to <?= e($slot['ends_at_london']) ?> (UK Time)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="slot_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Displayed in UK Local Time (Europe/London). Concurrency-locked upon submission.
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Step 3: Select Student or Child -->
                <div class="form-group" style="margin-bottom: 1.75rem;">
                    <label for="child_id" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Participant / Student Selection
                    </label>
                    <select id="child_id" 
                            name="child_id" 
                            class="form-control"
                            style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                            aria-describedby="child_hint">
                        <option value="">Myself (Account Holder / Adult Student)</option>
                        <?php if (!empty($children)): ?>
                            <optgroup label="My Registered Dependents">
                                <?php foreach ($children as $child): ?>
                                    <option value="<?= e((string)$child['id']) ?>">
                                        <?= e($child['first_name']) ?> <?= e($child['last_name'] ?? '') ?> (<?= e($child['school_year'] ?? 'Student') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <small id="child_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                        Choose whether the lesson is for yourself or one of your registered dependents. You can add more children in <a href="/parent-children.php">Children Management</a>.
                    </small>
                </div>

                <!-- Step 4: Inquiry Notes -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="inquiry_notes" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Lesson Objectives & Notes for the Tutor
                    </label>
                    <textarea id="inquiry_notes" 
                              name="inquiry_notes" 
                              rows="4" 
                              class="form-control"
                              maxlength="2000"
                              placeholder="e.g. Preparing for upcoming GCSE Physics paper 1; struggling with electromagnetism formulas."
                              style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem; font-family: inherit;"
                              aria-describedby="notes_hint"></textarea>
                    <small id="notes_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                        Maximum 2,000 characters. Sanitized server-side against HTML and script injection.
                    </small>
                </div>

                <!-- Action Button -->
                <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center;">
                    <a href="/student-bookings.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" 
                            class="btn btn-primary" 
                            id="btn-submit-booking"
                            <?= (empty($availableSlots) || $selectedTutorId <= 0) ? 'disabled' : '' ?>>
                        Submit Booking Request
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
