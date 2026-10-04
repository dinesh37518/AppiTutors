<?php
/**
 * Manager Students & Parents Administration View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Administration</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Student &amp; Parent Administration</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Students &amp; Parents Directory</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Managerial directory of registered UK guardians, student profiles, and family child associations.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'students';
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

        <!-- Search Controls -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
            <form method="GET" action="/manager-students.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 250px;">
                    <label for="search" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-navy-700); margin-bottom: 0.25rem;">Search Accounts</label>
                    <input type="text" id="search" name="search" value="<?= $e($search ?? '') ?>" placeholder="Search name or email address..." class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-navy-200); border-radius: 6px;">
                </div>

                <div>
                    <button type="submit" class="btn btn-primary btn-sm">Search Clients</button>
                    <?php if (!empty($search)): ?>
                        <a href="/manager-students.php" class="btn btn-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Students Table Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Registered Clients (<?= $e($pagination['total'] ?? 0) ?>)</h2>
            </div>

            <?php if (empty($students)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--color-navy-500);">
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No student/parent accounts found.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); color: var(--color-navy-700);">
                                <th style="padding: 0.75rem 0.5rem;">Client / Guardian</th>
                                <th style="padding: 0.75rem 0.5rem;">Contact</th>
                                <th style="padding: 0.75rem 0.5rem;">Postcode</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: center;">Children</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: center;">Bookings</th>
                                <th style="padding: 0.75rem 0.5rem;">Status</th>
                                <th style="padding: 0.75rem 0.5rem;">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): 
                                $statusBadge = ($s['status'] === 'ACTIVE') ? 'badge-verified' : 'badge-amber';
                            ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100);">
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <strong><?= $e($s['display_name']) ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500);">ID #<?= $e($s['id']) ?></div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div><?= $e($s['email']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500);"><?= $e($s['phone'] ?: 'No phone provided') ?></div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-700);">
                                        <?= $e($s['postcode'] ?: 'Not specified') ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                        <span class="badge badge-primary"><?= $e($s['active_children']) ?> dependents</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: center; font-weight: 600;">
                                        <?= $e($s['total_bookings']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span class="badge <?= $statusBadge ?>"><?= $e($s['status']) ?></span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-500); font-size: 0.85rem;">
                                        <?= $e(substr((string)$s['created_at'], 0, 10)) ?>
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
                            <a href="/manager-students.php?page=<?= $p ?>&search=<?= urlencode($search) ?>"
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
