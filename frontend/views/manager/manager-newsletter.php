<?php
/**
 * Manager Newsletter Audience Administration View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$statuses = [
    'ALL' => 'All Statuses',
    'PENDING' => 'Pending Opt-In Verification',
    'ACTIVE' => 'Active Subscribers',
    'UNSUBSCRIBED' => 'Unsubscribed',
    'SUPPRESSED' => 'Suppressed',
];
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <a href="/manager-dashboard.php">Administration</a> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Newsletter Audience</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Newsletter Audience &amp; Consent Registry</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Operational oversight of subscriber consent timestamps, verification state, and list hygiene.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'newsletter';
        include __DIR__ . '/manager-nav.php'; 
        ?>

        <!-- Open Decision Callout Banner -->
        <div class="alert alert-notice" style="margin-bottom: 2rem;">
            <div>
                <span class="badge badge-open-decision" style="margin-bottom: 0.35rem;">OPEN CLIENT DECISION</span>
                <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.35rem;">Newsletter Double Opt-In Verification &amp; Commercial Scope</div>
                <div style="font-size: 0.875rem; color: var(--color-navy-700); line-height: 1.5;">
                    The client commercial and legal policy regarding mandatory double opt-in email verification remains an open business decision. In accordance with platform governance, all new public sign-ups are persisted in neutral <strong>PENDING</strong> status with <code>confirmed_at = NULL</code>. No commercial bulk delivery provider or automated marketing engine is active in this phase.
                </div>
            </div>
        </div>

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

        <!-- Audience Statistics Summary Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
            
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-navy-700);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Total Audience
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950);">
                    <?= $e($stats['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                    All registered email records
                </div>
            </div>

            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-amber-600);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Pending Opt-In
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-amber-600);">
                    <?= $e($stats['pending'] ?? 0) ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                    Awaiting double opt-in confirmation
                </div>
            </div>

            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-emerald-600);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Active Subscribers
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-emerald-700);">
                    <?= $e($stats['active'] ?? 0) ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                    Confirmed audience members
                </div>
            </div>

            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-navy-400);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Unsubscribed
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-600);">
                    <?= $e($stats['unsubscribed'] ?? 0) ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                    Opted-out subscribers
                </div>
            </div>

        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
            <form action="/manager-newsletter.php" method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label for="newsletter-search" class="visually-hidden">Search subscribers</label>
                    <input type="text" id="newsletter-search" name="search" class="form-input" 
                           placeholder="Search by email..." 
                           value="<?= $e($search) ?>">
                </div>

                <div>
                    <label for="newsletter-status-filter" class="visually-hidden">Filter by status</label>
                    <select id="newsletter-status-filter" name="status" class="form-input" onchange="this.form.submit()">
                        <?php foreach ($statuses as $val => $lbl): ?>
                            <option value="<?= $e($val) ?>" <?= ($filterStatus === $val) ? 'selected' : '' ?>>
                                <?= $e($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <?php if ($filterStatus !== 'ALL' || !empty($search)): ?>
                    <a href="/manager-newsletter.php" class="btn btn-secondary btn-sm" style="color: var(--color-navy-600);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Subscribers Table -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Subscriber Registry</h2>
                <span style="font-size: 0.875rem; color: var(--color-navy-500);">
                    Showing <?= count($subscribers) ?> of <?= $e($pagination['total'] ?? 0) ?> subscribers
                </span>
            </div>

            <?php if (empty($subscribers)): ?>
                <div style="padding: 2.5rem; text-align: center; color: var(--color-navy-500);">
                    <p style="font-size: 1.05rem; margin-bottom: 0.5rem;">No subscribers found matching current filters.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;" role="table" aria-label="Newsletter subscribers list">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); text-align: left; font-size: 0.85rem; color: var(--color-navy-600); text-transform: uppercase;">
                                <th style="padding: 0.75rem 0.5rem;">ID</th>
                                <th style="padding: 0.75rem 0.5rem;">Subscriber Email</th>
                                <th style="padding: 0.75rem 0.5rem;">Consent Given</th>
                                <th style="padding: 0.75rem 0.5rem;">Confirmed At</th>
                                <th style="padding: 0.75rem 0.5rem;">Status</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Administrative Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $s): 
                                $statusBadgeClass = match($s['status']) {
                                    'ACTIVE' => 'badge-verified',
                                    'PENDING' => 'badge-pending',
                                    'UNSUBSCRIBED' => 'badge-cancelled',
                                    'SUPPRESSED' => 'badge-rejected',
                                    default => 'badge-secondary'
                                };
                            ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100); font-size: 0.95rem;">
                                    <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: var(--color-navy-700);">
                                        #<?= $e($s['id']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: var(--color-navy-950);">
                                        <?= $e($s['email']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: var(--color-navy-600);">
                                        <?= $s['consent_at'] ? $e($s['consent_at']) : '<span style="color: var(--color-navy-400);">&mdash;</span>' ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: var(--color-navy-600);">
                                        <?= $s['confirmed_at'] ? $e($s['confirmed_at']) : '<span style="color: var(--color-navy-400);">&mdash;</span>' ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <span class="badge <?= $statusBadgeClass ?>">
                                            <?= $e($s['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                        <form action="/manager-newsletter.php" method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center; justify-content: flex-end;">
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                            <input type="hidden" name="subscriber_id" value="<?= $e($s['id']) ?>">
                                            
                                            <?php if ($s['status'] !== 'ACTIVE'): ?>
                                                <button type="submit" name="status" value="ACTIVE" class="btn btn-secondary btn-sm" title="Mark Active">
                                                    Activate
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($s['status'] !== 'SUPPRESSED'): ?>
                                                <button type="submit" name="status" value="SUPPRESSED" class="btn btn-secondary btn-sm" style="color: var(--color-red-600);" title="Suppress Subscriber">
                                                    Suppress
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($s['status'] !== 'UNSUBSCRIBED'): ?>
                                                <button type="submit" name="status" value="UNSUBSCRIBED" class="btn btn-secondary btn-sm" title="Unsubscribe">
                                                    Unsubscribe
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-navy-200);">
                        <div style="font-size: 0.875rem; color: var(--color-navy-600);">
                            Page <?= $e($pagination['page']) ?> of <?= $e($pagination['total_pages']) ?>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($pagination['page'] > 1): ?>
                                <a href="/manager-newsletter.php?page=<?= $pagination['page'] - 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                            <?php endif; ?>
                            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                                <a href="/manager-newsletter.php?page=<?= $pagination['page'] + 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>
