<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'AppTutors UK — 1-to-1 Tutoring for Primary, GCSE & A-Level' ?></title>
    <meta name="description" content="<?= $metaDescription ?? 'Professional UK tutoring platform connecting students and parents with verified, Enhanced DBS checked tutors.' ?>">
    <link rel="canonical" href="<?= $canonicalUrl ?? 'http://localhost/' ?>">
    
    <!-- OpenGraph / Social Metadata -->
    <meta property="og:title" content="<?= $pageTitle ?? 'AppTutors UK' ?>">
    <meta property="og:description" content="<?= $metaDescription ?? 'Verified 1-to-1 UK Tutoring' ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_GB">
    <meta property="og:url" content="<?= $canonicalUrl ?? 'http://localhost/' ?>">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <!-- WCAG 2.2 AA Keyboard Accessibility Skip Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Top Trust & Announcement Bar -->
    <aside class="site-banner" aria-label="Announcement">
        <span>UK Curriculum Specialists • All Tutors Enhanced DBS Checked • Dedicated 1-to-1 Learning</span>
    </aside>

<?php
$isLoggedIn = !empty($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'STUDENT_PARENT';
$portalLink = ($userRole === 'MANAGER') ? '/manager-dashboard.php' : (($userRole === 'TUTOR') ? '/tutor-profile.php' : '/student-profile.php');
$userName = $_SESSION['user_name'] ?? 'User';
?>
    <!-- Main Navigation Header -->
    <header class="site-header" role="banner">
        <div class="container header-inner">
            <a href="/" class="brand-link" aria-label="AppTutors UK Homepage">
                <span class="brand-icon" aria-hidden="true">AT</span>
                <span>AppTutors<span style="color: var(--color-primary-600);">UK</span></span>
            </a>

            <!-- Desktop Navigation -->
            <nav class="nav-desktop" role="navigation" aria-label="Main Navigation">
                <ul class="nav-links">
                    <li><a href="/" class="nav-link <?= ($currentUri === '/' || $currentUri === '/index.php') ? 'active' : '' ?>">Home</a></li>
                    <li><a href="/tutors.php" class="nav-link <?= str_starts_with($currentUri, '/tutors') ? 'active' : '' ?>">Find a Tutor</a></li>
                    <li><a href="/about.php" class="nav-link <?= str_starts_with($currentUri, '/about') ? 'active' : '' ?>">About & DBS</a></li>
                    <li><a href="/subjects.php" class="nav-link <?= str_starts_with($currentUri, '/subjects') ? 'active' : '' ?>">Subjects</a></li>
                    <li><a href="/pricing.php" class="nav-link <?= str_starts_with($currentUri, '/pricing') ? 'active' : '' ?>">Pricing</a></li>
                    <li><a href="/testimonials.php" class="nav-link <?= str_starts_with($currentUri, '/testimonials') ? 'active' : '' ?>">Testimonials</a></li>
                    <li><a href="/blog.php" class="nav-link <?= str_starts_with($currentUri, '/blog') ? 'active' : '' ?>">Blog</a></li>
                    <li><a href="/contact.php" class="nav-link <?= str_starts_with($currentUri, '/contact') ? 'active' : '' ?>">Contact</a></li>
                </ul>
            </nav>

            <!-- Header Actions -->
            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $portalLink ?>" class="btn btn-outline btn-sm">My Portal</a>
                    <a href="/logout.php" class="btn btn-primary btn-sm">Sign Out</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-outline btn-sm">Sign In</a>
                    <a href="/register.php" class="btn btn-primary btn-sm">Get Started</a>
                <?php endif; ?>
                <button id="menu-toggle" class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Toggle navigation menu">
                    <span aria-hidden="true">&#9776;</span>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <nav id="mobile-nav" class="mobile-nav" role="navigation" aria-label="Mobile Navigation">
            <ul class="mobile-nav-links">
                <li><a href="/" class="mobile-nav-link <?= ($currentUri === '/' || $currentUri === '/index.php') ? 'active' : '' ?>">Home</a></li>
                <li><a href="/tutors.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/tutors') ? 'active' : '' ?>">Find a Tutor</a></li>
                <li><a href="/about.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/about') ? 'active' : '' ?>">About & DBS Safeguarding</a></li>
                <li><a href="/subjects.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/subjects') ? 'active' : '' ?>">Curriculum & Subjects</a></li>
                <li><a href="/pricing.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/pricing') ? 'active' : '' ?>">Pricing & Fee Structure</a></li>
                <li><a href="/testimonials.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/testimonials') ? 'active' : '' ?>">Client Testimonials</a></li>
                <li><a href="/blog.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/blog') ? 'active' : '' ?>">Educational Blog & Revision</a></li>
                <li><a href="/contact.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/contact') ? 'active' : '' ?>">Contact Us</a></li>
                <li><a href="/newsletter.php" class="mobile-nav-link <?= str_starts_with($currentUri, '/newsletter') ? 'active' : '' ?>">Newsletter Subscription</a></li>
            </ul>
            <div style="display: flex; gap: 0.75rem; flex-direction: column;">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $portalLink ?>" class="btn btn-outline" style="width: 100%;">My Portal (<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>)</a>
                    <a href="/logout.php" class="btn btn-primary" style="width: 100%;">Sign Out</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-outline" style="width: 100%;">Sign In</a>
                    <a href="/register.php" class="btn btn-primary" style="width: 100%;">Create Account / Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Main Content Container targeted by Skip Link -->
    <main id="main-content" tabindex="-1">
