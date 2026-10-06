<!-- Manager Tutor Governance Portal -->
<div class="container section">
    <div style="max-width: 1100px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Administration</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Tutor Approval & Governance</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Tutor Safeguarding & Approval Portal</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Managerial review queue for tutor registrations, DBS document verification, and platform bookability governance.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <span class="badge badge-manager">AUTHORITY: MANAGER</span>
                </div>
            </div>
        </div>

        <!-- Shared Manager Navigation -->
        <?php 
        $activeTab = 'tutors';
        include __DIR__ . '/manager-nav.php'; 
        ?>

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

        <!-- Safeguarding Governance Notice -->
        <div class="alert alert-notice" style="margin-bottom: 2.5rem;">
            <div>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.35rem;">
                    <strong style="color: var(--color-navy-950);">Audited Safeguarding Boundary</strong>
                    <span class="badge badge-open-decision">ENGINEERING CONTROLS</span>
                </div>
                <p style="color: var(--color-navy-700); font-size: 0.925rem; margin: 0;">
                    All approval, rejection, suspension, and DBS credential reviews are immutable events recorded in the server-side audit log. Tutors cannot self-approve, and prospective educators remain strictly non-bookable and hidden from public queries until manager approval is granted and DBS status is marked VERIFIED.
                </p>
            </div>
        </div>

        <!-- Filter / Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-navy-200); padding-bottom: 0.5rem; flex-wrap: wrap;">
            <?php
            $currentFilter = $filter ?? 'ALL';
            $filters = [
                'ALL' => 'All Tutors',
                'PENDING' => 'Pending Review',
                'APPROVED' => 'Approved',
                'SUSPENDED' => 'Suspended',
                'REJECTED' => 'Rejected'
            ];
            foreach ($filters as $key => $label):
                $active = ($currentFilter === $key);
            ?>
                <a href="/manager-tutors.php?status=<?= urlencode($key) ?>" 
                   class="btn <?= $active ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
                   style="<?= $active ? '' : 'background: transparent; border-color: transparent;' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Tutors Table Card -->
        <div class="card" style="padding: 1.5rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Tutor Review Pipeline (<?= count($tutors ?? []) ?>)</h2>
            </div>

            <?php if (empty($tutors)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--color-navy-500);">
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No tutors found matching the selected filter.</p>
                    <p style="font-size: 0.9rem;">Check other status filters or wait for prospective tutors to submit onboarding profiles.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-navy-200); color: var(--color-navy-700);">
                                <th style="padding: 0.75rem 0.5rem;">Tutor</th>
                                <th style="padding: 0.75rem 0.5rem;">Contact</th>
                                <th style="padding: 0.75rem 0.5rem;">Profile / Rate</th>
                                <th style="padding: 0.75rem 0.5rem;">DBS Status</th>
                                <th style="padding: 0.75rem 0.5rem;">Approval Status</th>
                                <th style="padding: 0.75rem 0.5rem;">Bookable?</th>
                                <th style="padding: 0.75rem 0.5rem; text-align: right;">Governance Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tutors as $t): 
                                $isBookable = (!empty($t['is_bookable']));
                                $dbsStatus = $t['dbs_status'] ?? 'NOT_SUBMITTED';
                                $approvalStatus = $t['approval_status'] ?? 'PENDING';
                                $userStatus = $t['status'] ?? 'PENDING';
                            ?>
                                <tr style="border-bottom: 1px solid var(--color-navy-100); vertical-align: top;">
                                    <td style="padding: 1rem 0.5rem;">
                                        <strong><?= e(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                                            ID: <?= (int)$t['id'] ?> | Joined: <?= e(substr($t['created_at'] ?? '', 0, 10)) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; margin-top: 0.25rem;">
                                            Account: <span class="badge <?= $userStatus === 'ACTIVE' ? 'badge-verified' : 'badge-primary' ?>"><?= e($userStatus) ?></span>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 0.5rem;">
                                        <div><?= e($t['email'] ?? '') ?></div>
                                        <div style="font-size: 0.8rem; color: var(--color-navy-500);"><?= e($t['phone'] ?? 'No phone') ?></div>
                                    </td>
                                    <td style="padding: 1rem 0.5rem;">
                                        <div><?= e($t['headline'] ?? 'No headline') ?></div>
                                        <div style="font-size: 0.825rem; color: var(--color-navy-600); margin-top: 0.25rem;">
                                            Rate: <strong><?= !empty($t['hourly_rate']) ? '£' . number_format((float)$t['hourly_rate'], 2) . '/hr' : 'Not set' ?></strong>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 0.5rem;">
                                        <span class="badge <?= $dbsStatus === 'VERIFIED' ? 'badge-verified' : ($dbsStatus === 'REJECTED' ? 'badge-suspended' : 'badge-open-decision') ?>">
                                            <?= e($dbsStatus) ?>
                                        </span>
                                        <?php if (!empty($t['dbs_certificate_number'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                                                Cert: <?= e($t['dbs_certificate_number']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($t['dbs_file_path'])): ?>
                                            <div style="margin-top: 0.5rem;">
                                                <a href="/api/dbs/document.php?tutor_id=<?= (int)$t['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                    View Doc &rarr;
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem 0.5rem;">
                                        <span class="badge <?= $approvalStatus === 'APPROVED' ? 'badge-verified' : ($approvalStatus === 'SUSPENDED' ? 'badge-suspended' : ($approvalStatus === 'REJECTED' ? 'badge-rejected' : 'badge-primary')) ?>">
                                            <?= e($approvalStatus) ?>
                                        </span>
                                        <?php if (!empty($t['approved_at'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--color-navy-500); margin-top: 0.25rem;">
                                                Approved: <?= e(substr($t['approved_at'], 0, 10)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem 0.5rem;">
                                        <?php if ($isBookable): ?>
                                            <span class="badge badge-verified" style="font-size: 0.75rem;">LIVE</span>
                                        <?php else: ?>
                                            <span class="badge badge-open-decision" style="font-size: 0.75rem;">BLOCKED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem 0.5rem; text-align: right; position: relative;">
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end;">
                                            <!-- Approval Workflow Form -->
                                            <form method="POST" action="/manager-tutors.php" style="display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end; align-items: center;">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
                                                <input type="hidden" name="tutor_id" value="<?= (int)$t['id'] ?>">

                                                <?php if ($approvalStatus === 'PENDING'): ?>
                                                    <button type="submit" name="action" value="APPROVE" class="btn btn-primary btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;" id="btn-approve-tutor-<?= (int)$t['id'] ?>">
                                                        Approve
                                                    </button>
                                                    
                                                    <details style="display: inline-block; text-align: left;">
                                                        <summary class="btn btn-danger btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.65rem; cursor: pointer; list-style: none;" id="btn-reject-menu-<?= (int)$t['id'] ?>">
                                                            Reject &dtrif;
                                                        </summary>
                                                        <div style="position: absolute; right: 0.5rem; margin-top: 0.25rem; background: #fff; border: 1px solid var(--color-navy-300); box-shadow: var(--shadow-lg); padding: 0.75rem; border-radius: var(--radius-sm); width: 250px; z-index: 100; text-align: left;">
                                                            <div style="font-weight: 700; font-size: 0.8rem; color: #9b2c2c; margin-bottom: 0.35rem;">Select Rejection Reason:</div>
                                                            <select name="reason_preset" style="width: 100%; font-size: 0.8rem; padding: 0.35rem; margin-bottom: 0.35rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm);">
                                                                <option value="Certificate missing">Certificate missing</option>
                                                                <option value="Fake ID">Fake ID</option>
                                                                <option value="Incomplete background check">Incomplete background check</option>
                                                                <option value="Other">Other manager reason</option>
                                                            </select>
                                                            <input type="text" name="reason_custom" placeholder="Additional details..." style="width: 100%; box-sizing: border-box; font-size: 0.8rem; padding: 0.35rem; margin-bottom: 0.5rem; border: 1px solid var(--color-navy-300); border-radius: var(--radius-sm);">
                                                            <button type="submit" name="action" value="REJECT" class="btn btn-danger btn-sm" style="width: 100%; font-size: 0.8rem; padding: 0.35rem;" id="btn-confirm-reject-<?= (int)$t['id'] ?>" onclick="return confirm('Confirm rejection of this tutor application?');">
                                                                Confirm Rejection
                                                            </button>
                                                        </div>
                                                    </details>
                                                <?php elseif ($approvalStatus === 'APPROVED'): ?>
                                                    <button type="submit" name="action" value="SUSPEND" class="btn btn-warning btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;" onclick="return confirm('Suspend this approved tutor?');">
                                                        Suspend
                                                    </button>
                                                <?php elseif ($approvalStatus === 'SUSPENDED'): ?>
                                                    <button type="submit" name="action" value="REINSTATE" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                                        Reinstate
                                                    </button>
                                                <?php elseif ($approvalStatus === 'REJECTED'): ?>
                                                    <button type="submit" name="action" value="APPROVE" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                                        Re-evaluate
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- DBS Workflow Decision -->
                                            <?php if ($dbsStatus === 'SUBMITTED'): ?>
                                                <form method="POST" action="/manager-tutors.php" style="display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end; margin-top: 0.25rem;">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
                                                    <input type="hidden" name="tutor_id" value="<?= (int)$t['id'] ?>">
                                                    <input type="hidden" name="workflow" value="DBS">
                                                    
                                                    <button type="submit" name="dbs_action" value="VERIFY" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; background-color: var(--color-emerald-600); color: white;">
                                                        Verify DBS
                                                    </button>
                                                    <button type="submit" name="dbs_action" value="REJECT" class="btn btn-danger btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" onclick="return confirm('Reject DBS certificate?');">
                                                        Reject DBS
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
