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
                <div>
                    <a href="/tutor-availability.php" class="btn btn-outline btn-sm">
                        Availability Calendar &rarr;
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

                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <!-- Accept / Confirm Form -->
                                <form method="POST" action="/tutor-bookings.php" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="confirm_booking">
                                    <input type="hidden" name="booking_id" value="<?= e((string)$b['id']) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirm-<?= e((string)$b['id']) ?>">
                                        &#10003; Accept & Confirm
                                    </button>
                                </form>

                                <!-- Reject Form -->
                                <form method="POST" action="/tutor-bookings.php" onsubmit="return confirm('Are you sure you want to decline this booking request? The slot will be reopened to other students.');" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="reject_booking">
                                    <input type="hidden" name="booking_id" value="<?= e((string)$b['id']) ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-rose-600); border-color: var(--color-rose-100);" id="btn-reject-<?= e((string)$b['id']) ?>">
                                        Decline
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
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($historyBookings as $b): ?>
                        <div style="padding: 1.25rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                    <strong>Booking #<?= e((string)$b['id']) ?></strong> &mdash; <?= e($b['student_name']) ?>
                                    <?php if ($b['status'] === 'CONFIRMED'): ?>
                                        <span class="badge badge-verified">CONFIRMED</span>
                                    <?php elseif ($b['status'] === 'REJECTED'): ?>
                                        <span class="badge badge-placeholder">DECLINED</span>
                                    <?php elseif ($b['status'] === 'CANCELLED'): ?>
                                        <span class="badge badge-placeholder">CANCELLED</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary"><?= e($b['status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--color-navy-600);">
                                    <?= !empty($b['confirmed_starts_at_utc']) ? 'Session time: ' . e($b['confirmed_starts_at_utc']) : 'Requested time: ' . e($b['proposed_starts_at_utc'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
