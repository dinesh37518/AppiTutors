<?php

declare(strict_types=1);

$baseUrl = 'http://localhost';

$routes = [
    // Public Website
    'Home Page' => ['path' => '/', 'expect' => ['AppTutors', 'Find a Verified Tutor', 'Enhanced DBS Checked']],
    'Find Tutor Directory' => ['path' => '/tutors.php', 'expect' => ['Find a Verified UK Tutor', 'Enhanced DBS']],
    'About & DBS Safeguarding' => ['path' => '/about.php', 'expect' => ['Safeguarding', 'Enhanced DBS', 'UK Curriculum']],
    'Subjects Directory' => ['path' => '/subjects.php', 'expect' => ['UK Subjects & Curriculum Pathways', 'GCSE', 'A-Level']],
    'Pricing Page & CTA' => ['path' => '/pricing.php', 'expect' => ['Transparent Pricing', 'Tutoring Fees']],
    'Testimonials Page & CTA' => ['path' => '/testimonials.php', 'expect' => ['Parent & Student Testimonials', 'Verified Feedback']],
    'Blog Public Archive' => ['path' => '/blog.php', 'expect' => ['UK Educational Blog', 'Revision Guides']],
    'Contact Manager / WhatsApp' => ['path' => '/contact.php', 'expect' => ['Contact Manager', 'Chat with Manager on WhatsApp', 'wa.me']],
    'Newsletter Registration' => ['path' => '/newsletter.php', 'expect' => ['UK Educational Newsletter', 'Curriculum Updates']],
    'Newsletter Unsubscribe' => ['path' => '/unsubscribe.php', 'expect' => ['Unsubscribe', 'Newsletter']],
    
    // Authentication
    'Login Page (Dual Categories & Google)' => ['path' => '/login.php', 'expect' => ['Student / User', 'Manager', 'Continue with Google', 'google-signin-btn']],
    'Registration Page (Tabs & Parent Email)' => ['path' => '/register.php', 'expect' => ['Student', 'Parent', 'Tutor', 'parent_email']],
    
    // Student / Parent Portal
    'Student Bookings & History' => ['path' => '/student-bookings.php', 'expect' => ['My Bookings', 'Session Requests']],
    'Parent Children Management' => ['path' => '/parent-children.php', 'expect' => ['Children & Dependents', 'Add Child']],
    'Student Profile' => ['path' => '/student-profile.php', 'expect' => ['Student & Parent Profile', 'Parent Email Address']],
    'Book Session Workflow' => ['path' => '/book-session.php', 'expect' => ['Request a 1-to-1 Tutoring Session', 'Booking Protection & Concurrency Safeguard']],
    
    // Tutor Portal
    'Tutor Profile' => ['path' => '/tutor-profile.php', 'expect' => ['Tutor Profile', 'Enhanced DBS Certificate']],
    'Tutor Availability Calendar' => ['path' => '/tutor-availability.php', 'expect' => ['Add Discrete Availability Window', '1-to-Many Slot', 'Maximum Students']],
    'Tutor Bookings & Lesson Notes' => ['path' => '/tutor-bookings.php', 'expect' => ['Lesson Requests & Bookings', 'Meeting Link', 'Decline Reason', 'Lesson Notes']],
    'Tutor Blog Articles' => ['path' => '/tutor-blog.php', 'expect' => ['Tutor Educational Articles', 'Compose New Blog Article', 'Save as Draft Article']],
    
    // Manager Portal
    'Manager Dashboard' => ['path' => '/manager-dashboard.php', 'expect' => ['Platform Management', 'Executive Dashboard', 'AUTHORITY: MANAGER']],
    'Manager Tutor Governance' => ['path' => '/manager-tutors.php', 'expect' => ['Tutor Safeguarding & Approval', 'Certificate missing', 'Fake ID']],
    'Manager Student Accounts' => ['path' => '/manager-students.php', 'expect' => ['Parents Directory', 'AUTHORITY: MANAGER']],
    'Manager Bookings Review' => ['path' => '/manager-bookings.php', 'expect' => ['Lesson Booking Administration', 'AUTHORITY: MANAGER']],
    'Manager Availability Slots' => ['path' => '/manager-availability.php', 'expect' => ['Tutor Availability Schedule', 'AUTHORITY: MANAGER']],
    'Manager Blog Moderation' => ['path' => '/manager-blog.php', 'expect' => ['Blog Editorial', 'AUTHORITY: MANAGER']],
    'Manager Newsletter Management' => ['path' => '/manager-newsletter.php', 'expect' => ['Newsletter Audience', 'Consent Registry', 'AUTHORITY: MANAGER']],
    'Manager Audit Log' => ['path' => '/manager-audit.php', 'expect' => ['Audit Ledger', 'AUTHORITY: MANAGER']],
    'Manager Reporting' => ['path' => '/manager-reports.php', 'expect' => ['Operational Reports', 'AUTHORITY: MANAGER']],
];

echo "========================================================\n";
echo "HTTP ROUTE & UI BUTTON FUNCTIONALITY AUDIT\n";
echo "========================================================\n\n";

$passedRoutes = 0;
$failedRoutes = 0;

foreach ($routes as $label => $info) {
    $url = $baseUrl . $info['path'];
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $content = @file_get_contents($url, false, $ctx);
    $statusLine = $http_response_header[0] ?? 'HTTP/1.0 500 Error';
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = (int) ($match[1] ?? 500);

    $allStringsFound = true;
    $missingString = '';
    if ($statusCode === 200 && $content !== false) {
        foreach ($info['expect'] as $str) {
            if (stripos($content, $str) === false) {
                $allStringsFound = false;
                $missingString = $str;
                break;
            }
        }
    }

    if ($statusCode === 200 && $allStringsFound) {
        $passedRoutes++;
        echo "  [PASS] {$label} ({$info['path']}) -> HTTP 200 OK\n";
    } else {
        $failedRoutes++;
        $reason = ($statusCode !== 200) ? "HTTP {$statusCode}" : "Missing content: '{$missingString}'";
        echo "  [FAIL] {$label} ({$info['path']}) -> {$reason}\n";
    }
}

echo "\n========================================================\n";
echo "AUDIT SUMMARY: {$passedRoutes} / " . count($routes) . " ROUTES VERIFIED\n";
echo "STATUS: " . ($failedRoutes === 0 ? "100% ROUTE AUDIT PASS" : "AUDIT FAILURES DETECTED") . "\n";
echo "========================================================\n";

exit($failedRoutes === 0 ? 0 : 1);
