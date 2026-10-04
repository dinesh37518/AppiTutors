<?php
/**
 * Manager Administration Dashboard View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Administration</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Executive Dashboard</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Platform Management &amp; Governance</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Centralized oversight for UK tutoring operations, safeguarding verification, and lifecycle management.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'dashboard';
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

        <!-- KPI Summary Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
            
            <!-- Tutors Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-primary-600);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Educator Pipeline
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                    <?= $e($kpis['tutors']['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong style="color: var(--color-amber-600);"><?= $e($kpis['tutors']['pending_approvals'] ?? 0) ?></strong> pending approval</span>
                    <span><strong style="color: var(--color-emerald-700);"><?= $e($kpis['tutors']['bookable_active'] ?? 0) ?></strong> bookable &amp; active</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-tutors.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Review Tutors &rsaquo;</a>
                </div>
            </div>

            <!-- Safeguarding Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-emerald-600);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Enhanced DBS Status
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-emerald-700); margin-bottom: 0.5rem;">
                    <?= $e($kpis['dbs']['verified'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong style="color: var(--color-emerald-700);"><?= $e($kpis['dbs']['verified'] ?? 0) ?></strong> verified credentials</span>
                    <span><strong style="color: var(--color-amber-600);"><?= $e($kpis['dbs']['submitted'] ?? 0) ?></strong> awaiting verification</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-tutors.php?status=PENDING" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Verify Credentials &rsaquo;</a>
                </div>
            </div>

            <!-- Bookings Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-primary-500);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Bookings &amp; Lessons
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                    <?= $e($kpis['bookings']['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong style="color: var(--color-amber-600);"><?= $e($kpis['bookings']['pending'] ?? 0) ?></strong> pending tutor reply</span>
                    <span><strong style="color: var(--color-emerald-700);"><?= $e($kpis['bookings']['confirmed'] ?? 0) ?></strong> confirmed bookings</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-bookings.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Manage Bookings &rsaquo;</a>
                </div>
            </div>

            <!-- Students & Parents Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-navy-700);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Clients &amp; Students
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                    <?= $e($kpis['students_parents']['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong><?= $e($kpis['students_parents']['total_children'] ?? 0) ?></strong> active children / dependents</span>
                    <span>Registered UK guardians</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-students.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">View Clients &rsaquo;</a>
                </div>
            </div>

            <!-- Availability Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-navy-500);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Slot Capacity
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                    <?= $e($kpis['availability']['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong style="color: var(--color-emerald-700);"><?= $e($kpis['availability']['published'] ?? 0) ?></strong> published / open</span>
                    <span><strong style="color: var(--color-primary-600);"><?= $e($kpis['availability']['booked'] ?? 0) ?></strong> reserved / booked</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-availability.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">View Slots &rsaquo;</a>
                </div>
            </div>

            <!-- Blog Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-primary-700);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Blog &amp; Editorial
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                    <?= $e($kpis['blog']['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong style="color: var(--color-emerald-700);"><?= $e($kpis['blog']['published'] ?? 0) ?></strong> published articles</span>
                    <span><strong style="color: var(--color-amber-600);"><?= $e($kpis['blog']['draft'] ?? 0) ?></strong> drafts in progress</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-blog.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Manage Blog &rsaquo;</a>
                </div>
            </div>

            <!-- Newsletter Subscribers Card -->
            <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-navy-600);">
                <div style="font-size: 0.85rem; color: var(--color-navy-500); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
                    Newsletter Audience
                </div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                    <?= $e($kpis['newsletter']['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--color-navy-600); display: flex; flex-direction: column; gap: 0.25rem;">
                    <span><strong style="color: var(--color-amber-600);"><?= $e($kpis['newsletter']['pending'] ?? 0) ?></strong> pending opt-in verification</span>
                    <span><strong style="color: var(--color-emerald-700);"><?= $e($kpis['newsletter']['active'] ?? 0) ?></strong> active subscribers</span>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/manager-newsletter.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">View Subscribers &rsaquo;</a>
                </div>
            </div>

        </div>

        <!-- Recent Audit Trail Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-size: 1.25rem; margin: 0;">Recent Platform Audit Events</h2>
                    <p style="color: var(--color-navy-500); font-size: 0.875rem; margin: 0;">Audit records protected by server-side authorization and controlled application access.</p>
                </div>
                <a href="/manager-audit.php" class="btn btn-secondary btn-sm">Full Audit Ledger &rsaquo;</a>
            </div>

            <?php if (empty($kpis['recent_activity'])): ?>
                <p style="color: var(--color-navy-500); font-size: 0.9rem; margin: 0;">No audit events recorded yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); color: var(--color-navy-700);">
                                <th style="padding: 0.6rem 0.5rem;">Event / Action</th>
                                <th style="padding: 0.6rem 0.5rem;">Target Entity</th>
                                <th style="padding: 0.6rem 0.5rem;">Initiating Actor</th>
                                <th style="padding: 0.6rem 0.5rem;">Timestamp (UTC)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kpis['recent_activity'] as $log): ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100);">
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <code style="background: var(--color-navy-100); padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.85rem;">
                                            <?= $e($log['action']) ?>
                                        </code>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-700);">
                                        <?= $e($log['entity_type']) ?> #<?= $e($log['entity_id'] ?? 'N/A') ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <?= $e($log['actor_name'] ?? 'System') ?> 
                                        <span style="font-size: 0.8rem; color: var(--color-navy-400);">(<?= $e($log['actor_role'] ?? 'SYSTEM') ?>)</span>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--color-navy-500); font-size: 0.85rem;">
                                        <?= $e($log['created_at']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Governance Footer Info -->
        <div style="display: flex; justify-content: space-between; align-items: center; color: var(--color-navy-400); font-size: 0.8rem; flex-wrap: wrap; gap: 0.5rem;">
            <span>Platform Standard: Europe/London Display • UTC Database Authority</span>
            <span>Server Timestamp: <?= $e($kpis['timestamp_london'] ?? '') ?> UK</span>
        </div>

    </div>
</div>
