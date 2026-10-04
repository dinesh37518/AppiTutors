<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;

class Phase4PublicWebsiteTest
{
    private string $baseUrl = 'http://127.0.0.1';
    private int $passed = 0;
    private int $failed = 0;

    public function runAll(): void
    {
        echo "=======================================================\n";
        echo " UK TUTORING PLATFORM — PHASE 4 PUBLIC WEBSITE TESTS\n";
        echo "=======================================================\n\n";

        $this->testFunctionalRoutes();
        $this->testBlogEngine();
        $this->testFormsAndValidation();
        $this->testSecurityAndBoundaryControls();
        $this->testAccessibilityAndSEOElements();

        echo "=======================================================\n";
        $total = $this->passed + $this->failed;
        echo " TEST SUMMARY: {$this->passed}/{$total} PASSED (" . ($total > 0 ? round(($this->passed / $total) * 100) : 0) . "%)\n";
        if ($this->failed === 0) {
            echo " STATUS: ALL PHASE 4 PUBLIC WEBSITE CHECKS PASSED!\n";
        } else {
            echo " STATUS: {$this->failed} CHECKS FAILED!\n";
        }
        echo "=======================================================\n";
    }

    private function testFunctionalRoutes(): void
    {
        echo "--- Functional Public Page Routes (Apache HTTP) ---\n";

        $routes = [
            '/' => 'Marketing Homepage',
            '/about.php' => 'About Us & DBS Safeguarding',
            '/subjects.php' => 'UK Curriculum & Subjects',
            '/pricing.php' => 'Pricing & Tutoring Fees',
            '/testimonials.php' => 'Parent & Student Testimonials',
            '/contact.php' => 'Contact Admissions Support',
            '/blog.php' => 'Educational Blog Index',
            '/newsletter.php' => 'Newsletter Subscription Page',
            '/tutors.php' => 'Find a Tutor (Phase 6 Preview)',
            '/login.php' => 'Sign In (Firebase Auth Gateway)',
            '/register.php' => 'Account Registration Selection',
            '/robots.txt' => 'SEO Crawling Directives',
        ];

        foreach ($routes as $path => $label) {
            $url = $this->baseUrl . $path;
            $res = $this->httpGet($url);
            if ($res['status'] === 200 && strlen($res['body']) > 200) {
                $this->pass("Route: {$label} ({$path})", "Status: 200 OK, Body: " . strlen($res['body']) . " bytes");
            } else {
                $this->fail("Route: {$label} ({$path})", "Expected 200 OK, got: {$res['status']}");
            }
        }

        // Test 404 handler on unknown page
        $res404 = $this->httpGet($this->baseUrl . '/non-existent-page-xyz-123');
        if ($res404['status'] === 404 && str_contains($res404['body'], 'Page Not Found')) {
            $this->pass("Error State: 404 Catch-All Handler", "Correctly returned HTTP 404 with styled error page");
        } else {
            $this->fail("Error State: 404 Catch-All Handler", "Expected 404, got {$res404['status']}");
        }
    }

    private function testBlogEngine(): void
    {
        echo "\n--- Blog Engine & Dynamic Detail Reader ---\n";

        // Test Blog Listing has articles
        $resList = $this->httpGet($this->baseUrl . '/blog.php');
        if ($resList['status'] === 200 && str_contains($resList['body'], 'Mastering GCSE Maths Revision')) {
            $this->pass("Blog: Published Posts Rendered in Index", "Found published editorial GCSE Maths article");
        } else {
            $this->fail("Blog: Published Posts Rendered in Index", "Published article not found in HTML");
        }

        // Test Single Post Reader by Slug
        $resDetail = $this->httpGet($this->baseUrl . '/blog-post.php?slug=mastering-gcse-maths-revision');
        if ($resDetail['status'] === 200 && str_contains($resDetail['body'], 'Mastering GCSE Maths Revision')) {
            $this->pass("Blog: Single Article Reader Loaded by Slug", "Correctly displayed full article body");
        } else {
            $this->fail("Blog: Single Article Reader Loaded by Slug", "Expected 200 with article, got {$resDetail['status']}");
        }

        // Test Invalid Slug Returns 404
        $resInvalidSlug = $this->httpGet($this->baseUrl . '/blog-post.php?slug=invalid-non-existent-slug');
        if ($resInvalidSlug['status'] === 404 && str_contains($resInvalidSlug['body'], 'Article Not Found')) {
            $this->pass("Blog: Non-Existent Article Slug Handled", "Correctly returned HTTP 404");
        } else {
            $this->fail("Blog: Non-Existent Article Slug Handled", "Expected 404, got {$resInvalidSlug['status']}");
        }
    }

    private function testFormsAndValidation(): void
    {
        echo "\n--- Public Forms & Server-Side Validation ---\n";

        // 1. Contact Form Valid Submission
        $contactData = [
            'name' => 'Automated Test Parent',
            'email' => 'test.parent.verification@example.co.uk',
            'type' => 'PARENT_STUDENT',
            'subject' => 'A-Level Chemistry Tutoring Enquiry',
            'message' => 'Requesting an assessment lesson for Year 12 Chemistry.',
            'consent' => '1',
        ];
        $resContact = $this->httpPost($this->baseUrl . '/contact.php', $contactData);
        if ($resContact['status'] === 200 && str_contains($resContact['body'], 'Message Received')) {
            $this->pass("Contact Form: Valid Submission Accepted", "Success confirmation rendered");
        } else {
            $this->fail("Contact Form: Valid Submission Accepted", "Failed to receive success confirmation");
        }

        // 2. Contact Form Invalid Submission (Missing Consent & Bad Email)
        $badContact = [
            'name' => 'Bad User',
            'email' => 'not-an-email',
            'type' => 'GENERAL',
            'subject' => 'Test',
            'message' => 'Test',
        ];
        $resBadContact = $this->httpPost($this->baseUrl . '/contact.php', $badContact);
        if ($resBadContact['status'] === 200 && str_contains($resBadContact['body'], 'Please correct the following errors')) {
            $this->pass("Contact Form: Malformed Input Server-Side Rejection", "Invalid email & missing consent blocked");
        } else {
            $this->fail("Contact Form: Malformed Input Server-Side Rejection", "Failed to reject invalid input");
        }

        // 3. Newsletter Valid Submission
        $newsEmail = 'automated.subscriber.' . time() . '@example.co.uk';
        $newsData = [
            'email' => $newsEmail,
            'consent' => '1',
        ];
        $resNews = $this->httpPost($this->baseUrl . '/newsletter.php', $newsData);
        if ($resNews['status'] === 200 && str_contains($resNews['body'], 'Subscribed Successfully')) {
            $this->pass("Newsletter: Valid Subscription Accepted", "Confirmation banner rendered");
        } else {
            $this->fail("Newsletter: Valid Subscription Accepted", "Failed to subscribe");
        }

        // 4. Verify Database Persistence of Subscriber in Neutral PENDING State
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, status, confirmation_token_hash FROM newsletter_subscribers WHERE email = :email');
        $stmt->execute([':email' => $newsEmail]);
        $row = $stmt->fetch();
        if ($row && $row['status'] === 'PENDING' && strlen($row['confirmation_token_hash'] ?? '') === 64) {
            $this->pass("Newsletter: MySQL Persistence & Token Hashing", "Persisted to newsletter_subscribers in neutral PENDING status (preserving open double-opt-in decision)");
        } else {
            $this->fail("Newsletter: MySQL Persistence & Token Hashing", "Subscriber record not found or status not PENDING (got: " . ($row['status'] ?? 'null') . ")");
        }
    }

    private function testSecurityAndBoundaryControls(): void
    {
        echo "\n--- Security & Boundary Protection (No Leakage) ---\n";

        // 1. .env Must Not Be Web-Accessible (403 Forbidden or 404 Not Found)
        $resEnv = $this->httpGet($this->baseUrl . '/.env');
        if ($resEnv['status'] === 404 || $resEnv['status'] === 403) {
            $this->pass("Security: .env Blocked from Web Access", "Access denied with HTTP {$resEnv['status']}");
        } else {
            $this->fail("Security: .env Blocked from Web Access", "Got unexpected status: {$resEnv['status']}");
        }

        // 2. Storage / Credentials Must Not Be Accessible
        $resStorage = $this->httpGet($this->baseUrl . '/storage/credentials/firebase-service-account.json');
        if ($resStorage['status'] === 404) {
            $this->pass("Security: storage/credentials Blocked from Web Access", "Returned HTTP 404 Not Found");
        } else {
            $this->fail("Security: storage/credentials Blocked from Web Access", "Got unexpected status: {$resStorage['status']}");
        }

        // 3. .htaccess Must Return 403 Forbidden
        $resHtaccess = $this->httpGet($this->baseUrl . '/.htaccess');
        if ($resHtaccess['status'] === 403) {
            $this->pass("Security: .htaccess Strictly Protected", "Returned HTTP 403 Forbidden");
        } else {
            $this->fail("Security: .htaccess Strictly Protected", "Got unexpected status: {$resHtaccess['status']}");
        }

        // 4. Protected API /api/auth.php Must Remain Protected
        $resApi = $this->httpGet($this->baseUrl . '/api/auth.php');
        if ($resApi['status'] === 401) {
            $this->pass("Security: Protected API Boundary Intact", "/api/auth.php correctly returns 401 Unauthorized");
        } else {
            $this->fail("Security: Protected API Boundary Intact", "Expected 401, got {$resApi['status']}");
        }
    }

    private function testAccessibilityAndSEOElements(): void
    {
        echo "\n--- Accessibility (WCAG 2.2 AA) & SEO Foundations ---\n";

        $resHome = $this->httpGet($this->baseUrl . '/');
        $html = $resHome['body'];

        // 1. Skip Link
        if (str_contains($html, 'class="skip-link"') && str_contains($html, 'href="#main-content"')) {
            $this->pass("Accessibility: Skip to Main Content Link", "Found visible focusable skip link");
        } else {
            $this->fail("Accessibility: Skip to Main Content Link", "Skip link missing");
        }

        // 2. Main Content Landmark
        if (str_contains($html, '<main id="main-content"')) {
            $this->pass("Accessibility: Main Semantic Landmark", "Found <main id=\"main-content\">");
        } else {
            $this->fail("Accessibility: Main Semantic Landmark", "Main landmark missing");
        }

        // 3. Heading Hierarchy (Single H1)
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1Matches);
        $h1Count = count($h1Matches[0] ?? []);
        if ($h1Count === 1) {
            $this->pass("Accessibility & SEO: Single <h1> Hierarchy", "Exactly 1 <h1> tag on homepage");
        } else {
            $this->fail("Accessibility & SEO: Single <h1> Hierarchy", "Found {$h1Count} <h1> tags");
        }

        // 4. Mobile Menu ARIA Attributes
        if (str_contains($html, 'aria-expanded="false"') && str_contains($html, 'aria-controls="mobile-nav"')) {
            $this->pass("Accessibility: Mobile Navigation ARIA Controls", "Found aria-expanded and aria-controls attributes");
        } else {
            $this->fail("Accessibility: Mobile Navigation ARIA Controls", "ARIA attributes missing from mobile nav toggle");
        }

        // 5. SEO Meta Description and Canonical Tags
        if (str_contains($html, '<meta name="description"') && str_contains($html, '<link rel="canonical"')) {
            $this->pass("SEO: Meta Description and Canonical Link", "Meta tags correctly configured");
        } else {
            $this->fail("SEO: Meta Description and Canonical Link", "SEO meta tags missing");
        }

        // 6. CSS Focus-Visible State
        $resCss = $this->httpGet($this->baseUrl . '/assets/css/app.css');
        if (str_contains($resCss['body'], ':focus-visible') && str_contains($resCss['body'], 'outline: 3px solid')) {
            $this->pass("Accessibility: WCAG 2.2 AA Focus Indicators", "Defined 3px outline focus-visible state in CSS");
        } else {
            $this->fail("Accessibility: WCAG 2.2 AA Focus Indicators", "Focus-visible styles not found in app.css");
        }
    }

    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    }

    private function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    }

    private function pass(string $name, string $detail): void
    {
        $this->passed++;
        printf("[ PASS ] %-55s\n         Detail: %s\n", $name, $detail);
    }

    private function fail(string $name, string $detail): void
    {
        $this->failed++;
        printf("[ FAIL ] %-55s\n         Detail: %s\n", $name, $detail);
    }
}

// Execute if run directly
if (php_sapi_name() === 'cli') {
    (new Phase4PublicWebsiteTest())->runAll();
}
