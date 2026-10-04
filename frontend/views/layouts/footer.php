    </main>

    <!-- Master Site Footer -->
    <footer class="site-footer" role="contentinfo">
        <div class="container">
            <div class="footer-grid">
                <!-- Col 1: Platform Overview & Safeguarding -->
                <div class="footer-brand">
                    <h3>AppTutors UK</h3>
                    <p style="color: var(--color-navy-400); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.25rem;">
                        Dedicated UK tutoring platform connecting students and parents with verified, Enhanced DBS checked educators across Primary, GCSE, and A-Level curricula.
                    </p>
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(5, 150, 105, 0.15); border: 1px solid var(--color-emerald-600); padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; color: var(--color-emerald-500);">
                        <span aria-hidden="true">&#10003;</span>
                        <span>Enhanced DBS Safeguarding Verified</span>
                    </div>
                </div>

                <!-- Col 2: Curricula & Levels -->
                <div class="footer-col">
                    <h4>UK Curricula</h4>
                    <ul class="footer-links">
                        <li><a href="/subjects.php#primary">Primary (KS1 & KS2)</a></li>
                        <li><a href="/subjects.php#ks3">Lower Secondary (KS3)</a></li>
                        <li><a href="/subjects.php#gcse">GCSE Preparation (KS4)</a></li>
                        <li><a href="/subjects.php#alevel">A-Level Specialists (KS5)</a></li>
                        <li><a href="/subjects.php#entrance">11+ & Grammar Entrance</a></li>
                        <li><a href="/tutors.php">Browse All Tutors</a></li>
                    </ul>
                </div>

                <!-- Col 3: Company & Information -->
                <div class="footer-col">
                    <h4>Information</h4>
                    <ul class="footer-links">
                        <li><a href="/about.php">About Us & DBS Verification</a></li>
                        <li><a href="/pricing.php">Pricing & Fee Structure</a></li>
                        <li><a href="/testimonials.php">Testimonials & Case Studies</a></li>
                        <li><a href="/blog.php">Revision Blog & Resources</a></li>
                        <li><a href="/contact.php">Contact Admissions Support</a></li>
                        <li><a href="/book-session.php">Book a Session</a></li>
                        <li><a href="/student-bookings.php">My Bookings</a></li>
                        <li><a href="/student-profile.php">Student & Parent Portal</a></li>
                        <li><a href="/parent-children.php">Children & Dependents</a></li>
                        <li><a href="/tutor-profile.php">Tutor Portal</a></li>
                        <li><a href="/tutor-bookings.php">Tutor Requests</a></li>
                        <li><a href="/manager-tutors.php">Manager Review</a></li>
                    </ul>
                </div>

                <!-- Col 4: Newsletter & Governance -->
                <div class="footer-col">
                    <h4>Educational Updates</h4>
                    <p style="color: var(--color-navy-400); font-size: 0.875rem; margin-bottom: 1rem;">
                        Subscribe to receive UK exam board updates, key dates, and revision study guides.
                    </p>
                    <form action="/newsletter.php" method="POST" class="js-newsletter-form" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div>
                            <label for="footer-newsletter-email" class="sr-only">Your email address</label>
                            <input type="email" id="footer-newsletter-email" name="email" class="form-input" placeholder="parent@example.co.uk" required style="padding: 0.6rem 0.85rem; font-size: 0.9rem;">
                        </div>
                        <div>
                            <label class="form-checkbox-label" style="font-size: 0.775rem; color: var(--color-navy-400);">
                                <input type="checkbox" name="consent" value="1" required class="form-checkbox">
                                <span>I consent to educational emails (explicit-consent control; policy pending client/legal approval).</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Subscribe to Guides</button>
                        <div class="js-newsletter-status" style="display: none;"></div>
                    </form>

                    <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem;">
                        <span class="badge badge-placeholder" style="align-self: flex-start;">CLIENT APPROVAL REQUIRED</span>
                        <a href="/about.php#safeguarding" style="color: var(--color-navy-500); text-decoration: none;">Safeguarding Policy [Placeholder]</a>
                        <a href="/about.php#terms" style="color: var(--color-navy-500); text-decoration: none;">Terms of Service [Placeholder]</a>
                        <a href="/about.php#privacy" style="color: var(--color-navy-500); text-decoration: none;">Privacy Notice [Placeholder]</a>
                    </div>
                </div>
            </div>

            <!-- Bottom Disclaimer & Timezone Note -->
            <div class="footer-bottom">
                <div>
                    <span>&copy; 2026 AppTutors UK. All rights reserved. Platform authority strictly governed by server-side MySQL.</span>
                </div>
                <div>
                    <span>All lesson bookings and schedules operate in Europe/London (GMT/BST).</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Production JavaScript -->
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
