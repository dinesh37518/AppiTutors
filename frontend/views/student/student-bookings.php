<!-- Student/Parent Bookings List Interface -->
<div class="container section">
    <div style="max-width: 960px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav aria-label="Breadcrumb" style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Family Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">My Bookings</span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">My Bookings & Lesson Requests</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Review your requested and confirmed 1-to-1 tutoring sessions.
                    </p>
                </div>
                <div>
                    <a href="/book-session.php" class="btn btn-primary btn-sm" id="btn-book-session">
                        + Book a New Session
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

        <!-- Bookings List Card -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
                <h2 style="font-size: 1.35rem; color: var(--color-navy-950); margin: 0;">
                    Session Requests (<?= count($bookings ?? []) ?>)
                </h2>
                <span class="badge badge-primary">Account: <?= e($currentUser->displayName ?? 'Student/Parent') ?></span>
            </div>

            <?php if (empty($bookings)): ?>
                <div style="text-align: center; padding: 3rem 1.5rem; background-color: var(--color-navy-50); border: 2px dashed var(--color-navy-200); border-radius: var(--radius-md);">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--color-navy-400);" aria-hidden="true">&#128197;</div>
                    <h3 style="font-size: 1.15rem; color: var(--color-navy-900); margin-bottom: 0.5rem;">No Bookings Found</h3>
                    <p style="color: var(--color-navy-600); font-size: 0.925rem; max-width: 440px; margin: 0 auto 1.5rem;">
                        You have not submitted any lesson requests yet. Browse our verified UK tutors and reserve your first availability slot.
                    </p>
                    <a href="/book-session.php" class="btn btn-primary btn-sm">+ Book Your First Session</a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($bookings as $b): ?>
                        <div class="card" style="padding: 1.5rem; border: 1px solid var(--color-navy-200); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem;">
                            <div style="flex: 1; min-width: 260px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                                    <span style="font-weight: 700; font-size: 1.15rem; color: var(--color-navy-950);">
                                        Booking #<?= e((string)$b['id']) ?> with <?= e($b['tutor_name']) ?>
                                    </span>
                                    <?php if ($b['status'] === 'CONFIRMED'): ?>
                                        <span class="badge badge-verified">CONFIRMED</span>
                                    <?php elseif ($b['status'] === 'PENDING'): ?>
                                        <span class="badge badge-primary">PENDING TUTOR REVIEW</span>
                                    <?php elseif ($b['status'] === 'REJECTED'): ?>
                                        <span class="badge badge-placeholder">DECLINED BY TUTOR</span>
                                    <?php elseif ($b['status'] === 'CANCELLED'): ?>
                                        <span class="badge badge-placeholder">CANCELLED</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary"><?= e($b['status']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.9rem; color: var(--color-navy-600); margin-bottom: 0.75rem;">
                                    <div>
                                        <strong>Participant:</strong> <?= !empty($b['child_name']) ? e($b['child_name']) . ' (' . e($b['child_school_year'] ?? 'Dependent') . ')' : 'Self (Account Holder)' ?>
                                    </div>
                                    <div>
                                        <strong>Proposed Time (UTC):</strong> <?= e($b['proposed_starts_at_utc'] ?? '') ?>
                                    </div>
                                    <?php if (!empty($b['confirmed_starts_at_utc'])): ?>
                                        <div>
                                            <strong>Confirmed Time:</strong> <?= e($b['confirmed_starts_at_utc']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($b['inquiry_notes'])): ?>
                                    <div style="font-size: 0.875rem; background-color: var(--color-navy-50); padding: 0.65rem 0.85rem; border-radius: var(--radius-sm); color: var(--color-navy-700); border-left: 3px solid var(--color-navy-300);">
                                        <strong>Lesson Notes:</strong> <?= e($b['inquiry_notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($b['status'] === 'PENDING'): ?>
                                <div>
                                    <form method="POST" action="/student-bookings.php" onsubmit="return confirm('Are you sure you want to cancel this pending booking request?');" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="cancel_booking">
                                        <input type="hidden" name="booking_id" value="<?= e((string)$b['id']) ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-rose-600); border-color: var(--color-rose-100);">
                                            Cancel Request
                                        </button>
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
