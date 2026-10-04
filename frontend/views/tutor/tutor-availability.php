<!-- Tutor Availability Management Interface -->
<div class="container section">
    <div style="max-width: 900px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Tutor Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Availability Schedule</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Teaching Availability Calendar</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Define your available lesson windows in UK London time (Europe/London GMT/BST).
                    </p>
                </div>
                <div>
                    <a href="/tutor-profile.php" class="btn btn-secondary btn-sm">&larr; Back to Profile</a>
                </div>
            </div>
        </div>

        <!-- Alerts -->
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

        <!-- Safeguarding Gate Check Banner -->
        <?php if (empty($isBookable)): ?>
            <div class="alert alert-notice" style="margin-bottom: 2rem;">
                <div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                        <span class="badge badge-open-decision">SAFEGUARDING PUBLISHING GATE</span>
                        <strong style="color: var(--color-navy-950);">Slot Publishing Restricted</strong>
                    </div>
                    <p style="color: var(--color-navy-700); font-size: 0.925rem; margin: 0;">
                        In accordance with platform safeguarding rules (DISC-017 / DISC-028), tutors cannot publish live availability slots until their teaching profile is approved by a manager and Enhanced DBS credentials are fully verified.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create Availability Slot Form -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="font-size: 1.35rem; margin: 0;">Add Discrete Availability Window</h2>
                <span class="badge badge-primary">Timezone: Europe/London</span>
            </div>
            <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 1.5rem;">
                Times are selected in UK local time and automatically converted to UTC for database persistence. Overlapping slots are strictly rejected by the server-side concurrency algorithm.
            </p>

            <form action="/tutor-availability.php" method="POST">
                <input type="hidden" name="form_action" value="create_slot">

                <div class="grid-3" style="gap: 1.25rem;">
                    <div class="form-group">
                        <label for="slot_date" class="form-label form-label-required">Date (UK)</label>
                        <input type="date" id="slot_date" name="slot_date" class="form-input" required value="<?= e($defaultDate ?? date('Y-m-d', strtotime('+1 day'))) ?>">
                    </div>

                    <div class="form-group">
                        <label for="slot_start" class="form-label form-label-required">Start Time (London)</label>
                        <input type="time" id="slot_start" name="slot_start" class="form-input" required value="16:00">
                    </div>

                    <div class="form-group">
                        <label for="slot_end" class="form-label form-label-required">End Time (London)</label>
                        <input type="time" id="slot_end" name="slot_end" class="form-input" required value="17:00">
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <div class="form-hint" style="margin: 0;">
                        Adjacent slots (e.g. 16:00–17:00 and 17:00–18:00) are permitted without conflict.
                    </div>
                    <button type="submit" class="btn btn-primary" <?= empty($isBookable) ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : '' ?>>
                        Add Availability Slot &rarr;
                    </button>
                </div>
            </form>
        </div>

        <!-- Current Availability Slots Table -->
        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.35rem; margin: 0;">Scheduled Availability Slots</h2>
                <span style="font-size: 0.85rem; color: var(--color-navy-500);"><?= count($slots ?? []) ?> slots configured</span>
            </div>

            <?php if (empty($slots)): ?>
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--color-navy-500); background: var(--color-navy-50); border-radius: var(--radius-sm);">
                    <p style="margin-bottom: 0.5rem; font-size: 1.05rem; font-weight: 600; color: var(--color-navy-700);">No availability slots scheduled yet.</p>
                    <p style="font-size: 0.9rem; margin: 0;">Add teaching slots using the form above once your tutor credentials have been verified.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); text-align: left; font-size: 0.875rem; color: var(--color-navy-600);">
                                <th style="padding: 0.75rem 0.5rem;">UK Local Window (London)</th>
                                <th style="padding: 0.75rem 0.5rem;">UTC Database Timestamp</th>
                                <th style="padding: 0.75rem 0.5rem;">Season</th>
                                <th style="padding: 0.75rem 0.5rem;">Status</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slots as $slot): ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-200); font-size: 0.9rem;">
                                    <td style="padding: 0.85rem 0.5rem; font-weight: 600; color: var(--color-navy-900);">
                                        <?= e($slot['starts_at_london']) ?> &ndash; <?= e(substr($slot['ends_at_london'], -5)) ?>
                                    </td>
                                    <td style="padding: 0.85rem 0.5rem; color: var(--color-navy-600); font-family: monospace; font-size: 0.85rem;">
                                        <?= e($slot['starts_at_utc']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 0.5rem;">
                                        <span class="badge <?= !empty($slot['is_bst']) ? 'badge-primary' : 'badge-placeholder' ?>" style="font-size: 0.75rem;">
                                            <?= !empty($slot['is_bst']) ? 'BST (UTC+1)' : 'GMT (UTC+0)' ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 0.5rem;">
                                        <span class="badge badge-verified" style="font-size: 0.75rem;">
                                            <?= e($slot['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 0.5rem; text-align: right;">
                                        <form action="/tutor-availability.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this availability slot?');">
                                            <input type="hidden" name="form_action" value="delete_slot">
                                            <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--color-rose-600); border-color: var(--color-rose-600); padding: 0.25rem 0.65rem; font-size: 0.8rem;">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--color-navy-200); font-size: 0.85rem; color: var(--color-navy-600);">
                <strong>Open Client Decision (DISC-023):</strong> Recurring weekly availability generation remains an open client business decision. In Phase 5, slots are managed discretely with transaction-isolated overlap validation.
            </div>
        </div>

    </div>
</div>
