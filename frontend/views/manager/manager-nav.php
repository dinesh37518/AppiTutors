<?php
/**
 * Shared Manager Administration Navigation Bar
 * Included at the top of each manager view.
 */
$activeTab = $activeTab ?? 'dashboard';
$tabs = [
    'dashboard' => ['url' => '/manager-dashboard.php', 'label' => 'Dashboard Overview'],
    'tutors' => ['url' => '/manager-tutors.php', 'label' => 'Tutors & DBS'],
    'students' => ['url' => '/manager-students.php', 'label' => 'Students & Parents'],
    'bookings' => ['url' => '/manager-bookings.php', 'label' => 'Bookings'],
    'availability' => ['url' => '/manager-availability.php', 'label' => 'Availability'],
    'blog' => ['url' => '/manager-blog.php', 'label' => 'Blog'],
    'newsletter' => ['url' => '/manager-newsletter.php', 'label' => 'Newsletter'],
    'audit' => ['url' => '/manager-audit.php', 'label' => 'Audit Ledger'],
    'reports' => ['url' => '/manager-reports.php', 'label' => 'Reports'],
];
?>
<nav aria-label="Manager Administration Sections" style="margin-bottom: 2rem; border-bottom: 2px solid var(--color-navy-200); padding-bottom: 0.25rem;">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <?php foreach ($tabs as $key => $tab): 
            $isActive = ($activeTab === $key);
        ?>
            <a href="<?= htmlspecialchars($tab['url'], ENT_QUOTES, 'UTF-8') ?>" 
               class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
               style="<?= $isActive ? 'font-weight: 600;' : 'background: transparent; border-color: transparent; color: var(--color-navy-700);' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
