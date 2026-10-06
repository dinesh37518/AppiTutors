<!-- Tutor Bookings Management Interface -->
<div class="container section">
    <div style="max-width: 960px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav aria-label="Breadcrumb" style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Tutor Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Lesson Requests</span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Lesson Requests & Bookings</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Manage student lesson inquiries, accept new sessions, and review your tutoring commitments.
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="/tutor-availability.php" class="btn btn-outline btn-sm">
                        Availability Calendar
                    </a>
                    <a href="/tutor-blog.php" class="btn btn-outline btn-sm">
                        Blog Articles
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

        <!-- Pending Requests Section -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md); border-top: 4px solid var(--color-primary-500);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 style="font-size: 1.35rem; color: var(--color-navy-950); margin: 0 0 0.25rem 0;">
                        Pending Lesson Requests (<?= count(array_filter($bookings ?? [], fn($b) => $b['status'] === 'PENDING')) ?>)
                    </h2>
                    <p style="color: var(--color-navy-600); font-size: 0.925rem; margin: 0;">
                        Requests awaiting your confirmation or rejection.
                    </p>
                </div>
                <span class="badge badge-primary">Tutor: <?= e($currentUser->displayName ?? 'Tutor') ?></span>
            </div>

            <?php
            $pendingBookings = array_filter($bookings ?? [], fn($b) => $b['status'] === 'PENDING');
            ?>

            <?php if (empty($pendingBookings)): ?>
                <div style="text-align: center; padding: 2.5rem 1.5rem; background-color: var(--color-navy-50); border: 1px dashed var(--color-navy-200); border-radius: var(--radius-md);">
                    <div style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--color-navy-400);" aria-hidden="true">&#9989;</div>
                    <h3 style="font-size: 1.1rem; color: var(--color-navy-900); margin-bottom: 0.25rem;">All Caught Up!</h3>
                    <p style="color: var(--color-navy-600); font-size: 0.9rem; max-width: 440px; margin: 0 auto;">
                        You have no pending booking requests awaiting action.
                    </p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($pendingBookings as $b): ?>
                        <div class="card" style="padding: 1.5rem; border: 1px solid var(--color-navy-200); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem;">
                            <div style="flex: 1; min-width: 260px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 700; font-size: 1.15rem; color: var(--color-navy-950);">
                                        Request #<?= e((string)$b['id']) ?> from <?= e($b['student_name']) ?>
                                    </span>
                                    <span class="badge badge-primary">PENDING ACTION</span>
                                </div>

                                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.9rem; color: var(--color-navy-600); margin-bottom: 0.75rem;">
                                    <div>
                                        <strong>Student / Child:</strong> <?= !empty($b['child_name']) ? e($b['child_name']) . ' (' . e($b['child_school_year'] ?? 'Student') . ')' : 'Adult Student' ?>
                                    </div>
                                    <div>
                                        <strong>Requested Time (UTC):</strong> <?= e($b['proposed_starts_at_utc'] ?? '') ?>
                                    </div>
                                </div>

                                <?php if (!empty($b['inquiry_notes'])): ?>
                                    <div style="font-size: 0.875rem; background-color: var(--color-navy-50); padding: 0.65rem 0.85rem; border-radius: var(--radius-sm); color: var(--color-navy-700); border-left: 3px solid var(--color-primary-400);">
                                        <strong>Learning Objectives:</strong> <?= e($b['inquiry_notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="min-width: 280px; display: flex; flex-direction: column; gap: 0.75rem;">
                                <!-- Accept / Confirm Form -->
                                <form method="POST" action="/tutor-bookings.php" style="margin: 0; background: var(--color-navy-50); padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--color-navy-200);">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="confirm_booking">
                                    <input type="hidden" name="booking_id" value="<?= e((string)$b['id']) ?>">
                                    <div style="margin-bottom: 0.5rem;">
                                        <label for="meeting-link-<?= e((string)$b['id']) ?>" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-navy-700); margin-bottom: 0.25rem;">
                                            Meeting Link (Google Meet / Zoom):
                                        </label>
                                        <input type="url" id="meeting-link-<?= e((string)$b['id']) ?>" name="meeting_link" placeholder="https://meet.google.com/..." style="width: 100%; box-sizing: border-box; font-size: 0.8rem; padding: 0.35rem 0.5rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm);">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirm-<?= e((string)$b['id']) ?>" style="width: 100%;">
                                        &#10003; Accept &amp; Confirm
                                    </button>
                                </form>

                                <!-- Reject Form -->
                                <form method="POST" action="/tutor-bookings.php" onsubmit="return confirm('Are you sure you want to decline this booking request? The slot will be reopened to other students.');" style="margin: 0; background: #fff5f5; padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid #fed7d7;">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="reject_booking">
                                    <input type="hidden" name="booking_id" value="<?= e((string)$b['id']) ?>">
                                    <div style="margin-bottom: 0.5rem;">
                                        <label for="reject-reason-<?= e((string)$b['id']) ?>" style="display: block; font-size: 0.75rem; font-weight: 600; color: #9b2c2c; margin-bottom: 0.25rem;">
                                            Decline Reason:
                                        </label>
                                        <input type="text" id="reject-reason-<?= e((string)$b['id']) ?>" name="reason" placeholder="e.g. Schedule conflict, Subject mismatch" required style="width: 100%; box-sizing: border-box; font-size: 0.8rem; padding: 0.35rem 0.5rem; border: 1px solid #feb2b2; border-radius: var(--radius-sm);">
                                    </div>
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-rose-600); border-color: var(--color-rose-300); width: 100%;" id="btn-reject-<?= e((string)$b['id']) ?>">
                                        Decline Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Confirmed & History Section -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <h2 style="font-size: 1.35rem; color: var(--color-navy-950); margin-bottom: 1.5rem;">
                All Bookings & History
            </h2>

            <?php
            $historyBookings = array_filter($bookings ?? [], fn($b) => $b['status'] !== 'PENDING');
            ?>

            <?php if (empty($historyBookings)): ?>
                <p style="color: var(--color-navy-600); font-size: 0.925rem; margin: 0;">
                    No past or confirmed bookings on record yet.
                </p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($historyBookings as $b): ?>
                        <div style="padding: 1.25rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                        <strong>Booking #<?= e((string)$b['id']) ?></strong> &mdash; <?= e($b['student_name']) ?>
                                        <?php if ($b['status'] === 'CONFIRMED'): ?>
                                            <span class="badge badge-verified">CONFIRMED</span>
                                        <?php elseif ($b['status'] === 'REJECTED'): ?>
                                            <span class="badge badge-placeholder">DECLINED</span>
                                        <?php elseif ($b['status'] === 'CANCELLED' || $b['status'] === 'SYSTEM_CANCELLED'): ?>
                                            <span class="badge badge-placeholder"><?= e($b['status']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-primary"><?= e($b['status']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--color-navy-600);">
                                        <?= !empty($b['confirmed_starts_at_utc']) ? 'Session time: ' . e($b['confirmed_starts_at_utc']) : 'Requested time: ' . e($b['proposed_starts_at_utc'] ?? '') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Meeting Link display for confirmed bookings -->
                            <?php if (!empty($b['meeting_link'])): ?>
                                <div style="font-size: 0.875rem; background: var(--color-navy-50); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border-left: 3px solid var(--color-primary-500); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                    <span><strong>Attendance Meeting Link:</strong> <a href="<?= e($b['meeting_link']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary-600);"><?= e($b['meeting_link']) ?></a></span>
                                    <a href="<?= e($b['meeting_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                        Open Classroom &rarr;
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Lesson Notes Component (for Confirmed / Completed Bookings) -->
                            <?php if ($b['status'] === 'CONFIRMED' || $b['status'] === 'COMPLETED'): ?>
                                <div style="margin-top: 0.5rem; padding-top: 0.75rem; border-top: 1px dashed var(--color-navy-200);">
                                    <div style="font-size: 0.875rem; font-weight: 700; color: var(--color-navy-900); margin-bottom: 0.5rem;">
                                        Lesson Notes & Student Progress
                                    </div>

                                    <?php if (!empty($bookingNotes[$b['id']])): ?>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem;">
                                            <?php foreach ($bookingNotes[$b['id']] as $note): ?>
                                                <div style="font-size: 0.85rem; background: #fff; border: 1px solid var(--color-navy-200); padding: 0.65rem; border-radius: var(--radius-sm);">
                                                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--color-navy-500); margin-bottom: 0.25rem;">
                                                        <span>Logged: <?= e(substr($note['created_at'] ?? '', 0, 16)) ?></span>
                                                        <span class="badge <?= $note['visibility'] === 'PARENT_VISIBLE' ? 'badge-verified' : 'badge-primary' ?>" style="font-size: 0.7rem;">
                                                            <?= $note['visibility'] === 'PARENT_VISIBLE' ? 'Parent & Student Visible' : 'Internal Only' ?>
                                                        </span>
                                                    </div>
                                                    <div style="color: var(--color-navy-800); white-space: pre-wrap;"><?= e($note['content']) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Add New Note Form -->
                                    <form method="POST" action="/tutor-bookings.php" style="margin: 0; background: var(--color-navy-50); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-navy-200);">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="save_notes">
                                        <input type="hidden" name="booking_id" value="<?= e((string)$b['id']) ?>">
                                        <textarea name="notes" rows="2" placeholder="Record session feedback, topics covered, or homework..." required style="width: 100%; box-sizing: border-box; font-size: 0.85rem; padding: 0.5rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm); margin-bottom: 0.5rem;"></textarea>
                                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                                            <label style="font-size: 0.8rem; color: var(--color-navy-700);">
                                                Visibility:
                                                <select name="visibility" style="font-size: 0.8rem; padding: 0.25rem 0.5rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm);">
                                                    <option value="PARENT_VISIBLE">Parent &amp; Student Visible</option>
                                                    <option value="INTERNAL">Tutor Internal Only</option>
                                                </select>
                                            </label>
                                            <button type="submit" class="btn btn-primary btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                                                Save Lesson Notes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
