<?php
/**
 * Manager Booking Administration View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Administration</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Booking Administration</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Lesson Booking Administration</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Global visibility into session requests, status histories, and scheduled 1-to-1 lessons.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'bookings';
        include __DIR__ . '/manager-nav.php'; 
        ?>

        <!-- Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#10003; Success:</span>
                <span><?= $e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= $e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Controls -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
            <form method="GET" action="/manager-bookings.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 220px;">
                    <label for="search" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-navy-700); margin-bottom: 0.25rem;">Search Parties</label>
                    <input type="text" id="search" name="search" value="<?= $e($search ?? '') ?>" placeholder="Search student, tutor, or child name..." class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-navy-200); border-radius: 6px;">
                </div>

                <div style="min-width: 160px;">
                    <label for="status" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-navy-700); margin-bottom: 0.25rem;">Lifecycle Status</label>
                    <select id="status" name="status" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-navy-200); border-radius: 6px;">
                        <option value="">All Statuses</option>
                        <option value="PENDING" <?= ($statusFilter === 'PENDING') ? 'selected' : '' ?>>PENDING</option>
                        <option value="CONFIRMED" <?= ($statusFilter === 'CONFIRMED') ? 'selected' : '' ?>>CONFIRMED</option>
                        <option value="REJECTED" <?= ($statusFilter === 'REJECTED') ? 'selected' : '' ?>>REJECTED</option>
                        <option value="CANCELLED" <?= ($statusFilter === 'CANCELLED') ? 'selected' : '' ?>>CANCELLED</option>
                        <option value="COMPLETED" <?= ($statusFilter === 'COMPLETED') ? 'selected' : '' ?>>COMPLETED</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary btn-sm">Filter Bookings</button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="/manager-bookings.php" class="btn btn-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Bookings Table Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Recorded Sessions (<?= $e($pagination['total'] ?? 0) ?>)</h2>
            </div>

            <?php if (empty($bookings)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--color-navy-500);">
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No bookings found matching current filters.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); color: var(--color-navy-700);">
                                <th style="padding: 0.75rem 0.5rem;">ID</th>
                                <th style="padding: 0.75rem 0.5rem;">Student / Parent</th>
                                <th style="padding: 0.75rem 0.5rem;">Student (Child)</th>
                                <th style="padding: 0.75rem 0.5rem;">Assigned Tutor</th>
                                <th style="padding: 0.75rem 0.5rem;">Scheduled Lesson (UK)</th>
                                <th style="padding: 0.75rem 0.5rem;">Status</th>
                                <th style="padding: 0.75rem 0.5rem;">Booked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $b): 
                                $statusBadgeClass = match($b['status']) {
                                    'CONFIRMED' => 'badge-verified',
                                    'PENDING' => 'badge-amber',
                                    'REJECTED' => 'badge-rejected',
                                    'CANCELLED', 'SYSTEM_CANCELLED' => 'badge-suspended',
                                    default => 'badge-primary',
                                };
                            ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100);">
                                    <td style="padding: 0.75rem 0.5rem; font-weight: 600;">
                                        #<?= $e($b['id']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div><strong><?= $e($b['student_name']) ?></strong></div>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500);"><?= $e($b['student_email']) ?></div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-700);">
                                        <?= $e($b['child_name'] ?: 'Direct Student') ?>
                                        <?php if (!empty($b['child_school_year'])): ?>
                                            <span style="font-size: 0.8rem; color: var(--color-navy-400);">(<?= $e($b['child_school_year']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div><strong><?= $e($b['tutor_name']) ?></strong></div>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500);"><?= $e($b['tutor_email']) ?></div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <?= $e($b['lesson_time_london']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span class="badge <?= $statusBadgeClass ?>"><?= $e($b['status']) ?></span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-500); font-size: 0.85rem;">
                                        <?= $e($b['created_at']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
                    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;">
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <a href="/manager-bookings.php?page=<?= $p ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>"
                               class="btn <?= ($pagination['page'] === $p) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>
