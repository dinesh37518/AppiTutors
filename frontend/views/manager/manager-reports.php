<?php
/**
 * Manager Operational Reports View
 */
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container section">
    <div style="max-width: 1150px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Administration</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Operational Reports</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Operational Reports &amp; Compliance Summaries</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Factual operational metrics, safeguarding adherence rates, and platform capacity distribution.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'reports';
        include __DIR__ . '/manager-nav.php'; 
        ?>

        <!-- Alerts -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
                <span style="font-weight: 700;">&#9888; Error:</span>
                <span><?= $e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Safeguarding Governance Notice -->
        <div class="alert alert-notice" style="margin-bottom: 2rem;">
            <div>
                <strong>Operational Scope Boundary:</strong>
                <p style="font-size: 0.9rem; margin: 0.25rem 0 0 0;">
                    These summaries reflect verified server-side database records. In accordance with the project specification, financial and payout reporting is excluded pending the commercial payment provider decision.
                </p>
            </div>
        </div>

        <!-- Reports Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
            
            <!-- Tutor Pipeline Report -->
            <div class="card" style="padding: 1.5rem;">
                <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem; color: var(--color-navy-900);">
                    Educator Approval Distribution
                </h2>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php 
                    $tutorsTotal = $reports['tutors']['total'] ?? 0;
                    $tutorDist = $reports['tutors']['distribution'] ?? [];
                    foreach (['APPROVED', 'PENDING', 'SUSPENDED', 'REJECTED'] as $status):
                        $count = (int) ($tutorDist[$status] ?? 0);
                        $pct = $tutorsTotal > 0 ? round(($count / $tutorsTotal) * 100, 1) : 0;
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <span><?= $e($status) ?></span>
                                <span><strong><?= $e($count) ?></strong> (<?= $e($pct) ?>%)</span>
                            </div>
                            <div style="background: var(--color-navy-100); height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: <?= $status === 'APPROVED' ? 'var(--color-emerald-600)' : ($status === 'PENDING' ? 'var(--color-amber-500)' : 'var(--color-rose-600)') ?>; width: <?= $pct ?>%; height: 100%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- DBS Compliance Report -->
            <div class="card" style="padding: 1.5rem;">
                <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem; color: var(--color-navy-900);">
                    DBS Safeguarding Compliance Rate
                </h2>
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--color-emerald-700);">
                        <?= $e($reports['dbs']['compliance_rate_percent'] ?? 0) ?>%
                    </div>
                    <p style="font-size: 0.85rem; color: var(--color-navy-500); margin: 0;">Verified Enhanced DBS ratio across all registered tutors</p>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                    <?php 
                    $dbsDist = $reports['dbs']['distribution'] ?? [];
                    foreach ($dbsDist as $st => $ct):
                    ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid var(--color-navy-100);">
                            <span><?= $e($st) ?></span>
                            <strong><?= $e($ct) ?> tutors</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Booking Lifecycle Report -->
            <div class="card" style="padding: 1.5rem;">
                <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem; color: var(--color-navy-900);">
                    Booking Lifecycle Distribution
                </h2>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                    <?php 
                    $bookingDist = $reports['bookings']['distribution'] ?? [];
                    $totalBookings = $reports['bookings']['total'] ?? 0;
                    if (empty($bookingDist)):
                    ?>
                        <p style="color: var(--color-navy-500);">No bookings recorded yet.</p>
                    <?php else: 
                        foreach ($bookingDist as $bStatus => $bCount):
                            $bPct = $totalBookings > 0 ? round(($bCount / $totalBookings) * 100, 1) : 0;
                    ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid var(--color-navy-100);">
                            <span><?= $e($bStatus) ?></span>
                            <span><strong><?= $e($bCount) ?></strong> (<?= $e($bPct) ?>%)</span>
                        </div>
                    <?php 
                        endforeach; 
                    endif;
                    ?>
                </div>
            </div>

            <!-- Availability Capacity Report -->
            <div class="card" style="padding: 1.5rem;">
                <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem; color: var(--color-navy-900);">
                    Platform Teaching Capacity
                </h2>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                    <?php 
                    $slotDist = $reports['availability']['distribution'] ?? [];
                    foreach (['PUBLISHED', 'BOOKED', 'BLOCKED'] as $slSt):
                        $slCount = (int) ($slotDist[$slSt] ?? 0);
                    ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid var(--color-navy-100);">
                            <span><?= $e($slSt) ?></span>
                            <strong><?= $e($slCount) ?> slots</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <div style="text-align: right; color: var(--color-navy-400); font-size: 0.8rem;">
            Report generated at: <?= $e($reports['generated_at_utc'] ?? '') ?> UTC
        </div>

    </div>
</div>
