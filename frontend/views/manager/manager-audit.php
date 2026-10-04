<?php
/**
 * Manager Audit Ledger View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Administration</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Security &amp; Compliance Audit Ledger</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Security &amp; Compliance Audit Ledger</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Immutable server-side record of authentication events, onboarding decisions, and lifecycle transitions.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'audit';
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
            <form method="GET" action="/manager-audit.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 220px;">
                    <label for="search" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-navy-700); margin-bottom: 0.25rem;">Search Metadata / Action</label>
                    <input type="text" id="search" name="search" value="<?= $e($search ?? '') ?>" placeholder="Search action name or actor..." class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-navy-200); border-radius: 6px;">
                </div>

                <div style="min-width: 160px;">
                    <label for="action" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-navy-700); margin-bottom: 0.25rem;">Action Type</label>
                    <input type="text" id="action" name="action" value="<?= $e($actionFilter ?? '') ?>" placeholder="e.g. MANAGER_APPROVE" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-navy-200); border-radius: 6px;">
                </div>

                <div>
                    <button type="submit" class="btn btn-primary btn-sm">Filter Audit Logs</button>
                    <?php if (!empty($search) || !empty($actionFilter)): ?>
                        <a href="/manager-audit.php" class="btn btn-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Audit Table Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Recorded Audit Events (<?= $e($pagination['total'] ?? 0) ?>)</h2>
                <span style="font-size: 0.85rem; color: var(--color-navy-500);">All personal identifiers and credentials strictly masked</span>
            </div>

            <?php if (empty($logs)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--color-navy-500);">
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No audit events found matching current criteria.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); color: var(--color-navy-700);">
                                <th style="padding: 0.75rem 0.5rem;">Log ID</th>
                                <th style="padding: 0.75rem 0.5rem;">Action Code</th>
                                <th style="padding: 0.75rem 0.5rem;">Target Entity</th>
                                <th style="padding: 0.75rem 0.5rem;">Actor</th>
                                <th style="padding: 0.75rem 0.5rem;">Redacted Metadata</th>
                                <th style="padding: 0.75rem 0.5rem;">Timestamp (UTC)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100);">
                                    <td style="padding: 0.75rem 0.5rem; font-weight: 600;">
                                        #<?= $e($l['id']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <code style="background: var(--color-navy-100); padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600; color: var(--color-navy-950);">
                                            <?= $e($l['action']) ?>
                                        </code>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-700);">
                                        <?= $e($l['entity_type']) ?> #<?= $e($l['entity_id'] ?: 'N/A') ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <div><strong><?= $e($l['actor_name']) ?></strong></div>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500);"><?= $e($l['actor_role']) ?></div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; font-size: 0.8rem; font-family: monospace; max-width: 320px; word-break: break-all;">
                                        <?= $e(json_encode($l['metadata'], JSON_UNESCAPED_SLASHES)) ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-500); font-size: 0.85rem;">
                                        <?= $e($l['created_at']) ?>
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
                            <a href="/manager-audit.php?page=<?= $p ?>&action=<?= urlencode($actionFilter) ?>&search=<?= urlencode($search) ?>"
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
