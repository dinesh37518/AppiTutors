<!-- Hero Section -->
<section class="hero" aria-labelledby="hero-heading">
    <div class="container hero-grid">
        <div class="hero-content">
            <div class="hero-tagline">
                <span class="badge badge-verified" style="font-size: 0.75rem;">100% Vetted</span>
                <span>Enhanced DBS Checked UK Tutors</span>
            </div>
            <h1 id="hero-heading" class="hero-title">
                Expert 1-to-1 UK Tutoring for Primary, GCSE & A-Level
            </h1>
            <p class="hero-subtitle">
                Connecting students and parents with subject specialists across England, Wales, and Northern Ireland. Structured, curriculum-aligned tutoring designed to build confidence and exam success.
            </p>
            <div class="hero-actions">
                <a href="/tutors.php" class="btn btn-primary btn-lg">Find a Verified Tutor</a>
                <a href="/register.php?type=tutor" class="btn btn-secondary btn-lg" id="btn-hero-apply-tutor">&#9997; Apply as a Tutor</a>
                <a href="/about.php" class="btn btn-outline btn-lg">Our Safeguarding Standard</a>
            </div>
            <div class="hero-trust-bar">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: var(--color-emerald-600); font-weight: 800;">&#10003;</span>
                    <span>AQA, Edexcel, OCR Aligned</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: var(--color-emerald-600); font-weight: 800;">&#10003;</span>
                    <span>Manager Vetted Credentials</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: var(--color-emerald-600); font-weight: 800;">&#10003;</span>
                    <span>Europe/London Timings</span>
                </div>
            </div>
        </div>

        <!-- Hero Feature Card -->
        <div class="hero-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <span class="badge badge-primary">Curriculum Focus</span>
                <span class="badge badge-verified">Tailored Learning</span>
            </div>
            <h2 style="font-size: 1.35rem; margin-bottom: 0.75rem;">Structured Academic Support</h2>
            <p style="font-size: 0.95rem; color: var(--color-navy-600); margin-bottom: 1.25rem;">
                Every child learns differently. Our verified tutors construct individualized study trajectories tailored to national curriculum benchmarks and targeted grade improvements.
            </p>
            <div style="background-color: var(--color-navy-50); border: 1px solid var(--color-navy-200); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                <div style="font-weight: 700; font-size: 0.9rem; color: var(--color-navy-950); margin-bottom: 0.5rem;">UK Key Stage Coverage</div>
                <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: var(--color-navy-700);">
                    <li><strong>Primary (KS1 & KS2)</strong>: Literacy, Numeracy & 11+ Foundations</li>
                    <li><strong>Secondary (KS3)</strong>: Core STEM & Humanities Preparation</li>
                    <li><strong>GCSE (KS4)</strong>: Exam Board Technique & Past Papers</li>
                    <li><strong>A-Level (KS5)</strong>: Deep Subject Specialisms & UCAS Support</li>
                </ul>
            </div>
            <a href="/subjects.php" class="btn btn-secondary" style="width: 100%;">Explore Curriculum Breakdown &rarr;</a>
        </div>
    </div>
</section>

<!-- Section: Book a Tutoring Session & Live Availability -->
<section class="section" id="booking-session-section" style="background: var(--color-navy-50); border-top: 1px solid var(--color-navy-200); border-bottom: 1px solid var(--color-navy-200); padding: 3.5rem 0;">
    <div class="container">
        
        <?php if (!empty($currentUser)): ?>
            <!-- User Bookings Summary (Matches student-bookings.php) -->
            <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md); background: var(--color-white);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                            <h2 style="font-size: 1.45rem; color: var(--color-navy-950); margin: 0;">My Bookings & Lesson Requests</h2>
                            <span class="badge badge-primary">Session Requests (<?= count($bookings ?? []) ?>)</span>
                        </div>
                        <p style="color: var(--color-navy-600); font-size: 0.95rem; margin: 0.35rem 0 0 0;">
                            Review your requested and confirmed 1-to-1 tutoring sessions.
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <span class="badge badge-verified">Account: <?= e($currentUser->displayName ?? 'Student/Parent') ?></span>
                        <a href="/book-session.php" class="btn btn-primary btn-sm" id="btn-book-session-home">
                            + Book a New Session
                        </a>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div style="text-align: center; padding: 2.5rem 1.5rem; background-color: var(--color-navy-50); border: 2px dashed var(--color-navy-200); border-radius: var(--radius-md);">
                        <div style="font-size: 2.5rem; margin-bottom: 0.75rem; color: var(--color-navy-400);" aria-hidden="true">&#128197;</div>
                        <h3 style="font-size: 1.15rem; color: var(--color-navy-900); margin-bottom: 0.5rem;">No Bookings Found</h3>
                        <p style="color: var(--color-navy-600); font-size: 0.925rem; max-width: 440px; margin: 0 auto 1.25rem;">
                            You have not submitted any lesson requests yet. Browse our verified UK tutors below and reserve your first availability slot.
                        </p>
                        <a href="/book-session.php" class="btn btn-primary btn-sm">+ Book Your First Session</a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach (array_slice($bookings, 0, 3) as $b): ?>
                            <div style="padding: 1.25rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); background: var(--color-white); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                        <strong>Booking #<?= e((string)$b['id']) ?> with <?= e($b['tutor_name']) ?></strong>
                                        <span class="badge <?= ($b['status'] === 'CONFIRMED') ? 'badge-verified' : 'badge-primary' ?>"><?= e($b['status']) ?></span>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--color-navy-600);">
                                        <strong>Time:</strong> <?= e($b['proposed_starts_at_utc'] ?? '') ?> UTC
                                        <?php if (!empty($b['child_name'])): ?>
                                            &bull; <strong>Participant:</strong> <?= e($b['child_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <a href="/student-bookings.php" class="btn btn-outline btn-sm">View Full Details &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($bookings) > 3): ?>
                            <div style="text-align: right; margin-top: 0.5rem;">
                                <a href="/student-bookings.php" style="font-size: 0.9rem; font-weight: 600;">View all <?= count($bookings) ?> bookings &rarr;</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Quick 1-to-1 Booking Reservation Session Widget -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge badge-verified" style="margin-bottom: 0.5rem;">Live Booking Windows</span>
                <h2 style="font-size: 1.65rem; color: var(--color-navy-950); margin: 0;">
                    Book a 1-to-1 Tutoring Session
                </h2>
                <p style="color: var(--color-navy-600); font-size: 1.05rem; margin: 0.5rem 0 0 0;">
                    Choose an approved UK educator and reserve their published availability window in seconds.
                </p>
            </div>
            <div>
                <a href="/book-session.php" class="btn btn-primary">
                    + Book a New Session &rarr;
                </a>
            </div>
        </div>

        <div class="grid-2" style="gap: 2rem;">
            <!-- Left Card: Quick Booking Picker -->
            <div class="card" style="padding: 2rem; box-shadow: var(--shadow-md); background: var(--color-white);">
                <h3 style="font-size: 1.2rem; color: var(--color-navy-950); margin-bottom: 1.25rem;">
                    &#128197; Quick Session Reservation
                </h3>
                <form method="GET" action="/book-session.php" id="home-quick-book-form">
                    <div style="margin-bottom: 1.25rem;">
                        <label for="home-tutor-select" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            1. Select Verified UK Tutor
                        </label>
                        <select id="home-tutor-select" name="tutor_id" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem;" onchange="window.location.href='/?tutor_id=' + this.value + '#booking-session-section'">
                            <?php foreach ($tutors as $tutor): ?>
                                <option value="<?= (int)$tutor['id'] ?>" <?= ($selectedTutorId === (int)$tutor['id']) ? 'selected' : '' ?>>
                                    <?= e($tutor['display_name']) ?> &mdash; <?= e($tutor['headline'] ?? 'UK Educator') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label for="home-slot-select" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            2. Select Published Availability Slot
                        </label>
                        <?php if (empty($availableSlots)): ?>
                            <div style="padding: 0.85rem 1rem; background: var(--color-navy-50); border: 1px dashed var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.9rem; color: var(--color-navy-600);">
                                No published slots open for this educator right now. Click below to proceed to booking calendar.
                            </div>
                        <?php else: ?>
                            <select id="home-slot-select" name="slot_id" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 0.95rem;">
                                <?php foreach ($availableSlots as $slot): ?>
                                    <option value="<?= (int)$slot['id'] ?>">
                                        <?= e($slot['starts_at_london']) ?> to <?= e($slot['ends_at_london']) ?> (UK Time)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 0.75rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            Continue to Reserve Session &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Card: Featured Educator Highlight -->
            <?php 
                $activeTutorHighlight = null;
                foreach ($tutors as $t) {
                    if ((int)$t['id'] === $selectedTutorId) {
                        $activeTutorHighlight = $t;
                        break;
                    }
                }
                if ($activeTutorHighlight === null && !empty($tutors)) {
                    $activeTutorHighlight = $tutors[0];
                }
            ?>
            <div class="card" style="padding: 2rem; box-shadow: var(--shadow-md); background: var(--color-white); border-left: 4px solid var(--color-emerald-500); display: flex; flex-direction: column; justify-content: space-between;">
                <?php if ($activeTutorHighlight !== null): ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; color: var(--color-navy-950); margin: 0 0 0.25rem 0;">
                                    <?= e($activeTutorHighlight['display_name']) ?>
                                </h3>
                                <span class="badge badge-verified">&#10003; Enhanced DBS Verified</span>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 1.15rem; font-weight: 800; color: var(--color-primary-600);">&pound;50 / hr</span>
                            </div>
                        </div>

                        <p style="font-size: 0.95rem; color: var(--color-navy-700); line-height: 1.6; margin-bottom: 1.25rem;">
                            <?= e($activeTutorHighlight['headline'] ?? 'Specialist UK educator with validated credentials and background verification.') ?>
                        </p>

                        <div style="background: var(--color-navy-50); border-radius: var(--radius-sm); padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: var(--color-navy-900); margin-bottom: 0.25rem;">
                                &#128197; Open Scheduling
                            </div>
                            <div style="font-size: 0.875rem; color: var(--color-emerald-700); font-weight: 600;">
                                <?= count($availableSlots) ?> availability slots published for this tutor
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem;">
                        <a href="/tutors.php?id=<?= (int)$activeTutorHighlight['id'] ?>" class="btn btn-outline" style="flex: 1; text-align: center;">
                            View Profile & Bio
                        </a>
                        <a href="/book-session.php?tutor_id=<?= (int)$activeTutorHighlight['id'] ?>" class="btn btn-primary" style="flex: 1; text-align: center;">
                            Book This Tutor
                        </a>
                    </div>
                <?php else: ?>
                    <div>
                        <h3>Browse Verified UK Educators</h3>
                        <p>Search over 400 verified tutors across Primary, KS3, GCSE, and A-Level.</p>
                    </div>
                    <a href="/tutors.php" class="btn btn-primary">Open Tutor Directory</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<!-- Section 1: How It Works -->
<section class="section" aria-labelledby="how-it-works-heading">
    <div class="container">
        <div style="text-align: center; max-width: 720px; margin: 0 auto 3.5rem;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Clear & Transparent</span>
            <h2 id="how-it-works-heading" style="margin-bottom: 1rem;">How AppTutors Works for Families</h2>
            <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                A straightforward, safeguarding-first pathway from tutor discovery to completed lesson reviews.
            </p>
        </div>

        <div class="steps-grid">
            <!-- Step 1 -->
            <div class="step-card">
                <div class="step-number" aria-hidden="true">1</div>
                <div class="card-icon" style="margin-top: 0.5rem;" aria-hidden="true">&#128269;</div>
                <h3 class="card-title">1. Discover Verified Tutors</h3>
                <p class="card-body">
                    Filter by Key Stage, subject specialism, and exam board. Every tutor profile displays verified academic qualifications and an active Enhanced DBS certificate status.
                </p>
                <div style="font-size: 0.85rem; color: var(--color-emerald-700); font-weight: 600;">
                    &#10003; Strict Manager Verification Gate
                </div>
            </div>

            <!-- Step 2 -->
            <div class="step-card">
                <div class="step-number" aria-hidden="true">2</div>
                <div class="card-icon" style="margin-top: 0.5rem;" aria-hidden="true">&#128197;</div>
                <h3 class="card-title">2. Request Lesson Inquiries</h3>
                <p class="card-body">
                    Select open calendar slots published directly by the tutor. Include specific learning goals, target exam boards, or topic areas requiring attention.
                </p>
                <div style="font-size: 0.85rem; color: var(--color-primary-600); font-weight: 600;">
                    &#10003; Transparent Lesson Lifecycle
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step-card">
                <div class="step-number" aria-hidden="true">3</div>
                <div class="card-icon" style="margin-top: 0.5rem;" aria-hidden="true">&#127891;</div>
                <h3 class="card-title">3. Learn & Track Progress</h3>
                <p class="card-body">
                    Engage in focused 1-to-1 lessons. Following each completed session, tutors document structured lesson notes and homework guidance directly on your dashboard.
                </p>
                <div style="font-size: 0.85rem; color: var(--color-amber-600); font-weight: 600;">
                    &#10003; Detailed Feedback & Milestones
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 2: Safeguarding Commitment Banner -->
<section class="container" aria-labelledby="safeguarding-heading">
    <div class="safeguard-banner">
        <div class="safeguard-grid">
            <div>
                <span class="badge badge-verified" style="margin-bottom: 1rem;">Safeguarding First</span>
                <h2 id="safeguarding-heading" style="margin-bottom: 1rem; color: var(--color-white);">
                    Uncompromising UK Safeguarding & DBS Verification
                </h2>
                <p style="color: var(--color-navy-100); font-size: 1.05rem; line-height: 1.6;">
                    Child safety is our foremost non-negotiable priority. No tutor can publish availability, accept bookings, or appear in search results without completing comprehensive background verification.
                </p>
                <ul class="safeguard-points">
                    <li class="safeguard-point">
                        <span class="safeguard-check">&#10003;</span>
                        <span><strong>Enhanced DBS Checking</strong>: Certificate authenticity and issue dates verified by platform managers.</span>
                    </li>
                    <li class="safeguard-point">
                        <span class="safeguard-check">&#10003;</span>
                        <span><strong>Proof of Identity & Qualifications</strong>: Verification of university degrees and teaching credentials.</span>
                    </li>
                    <li class="safeguard-point">
                        <span class="safeguard-check">&#10003;</span>
                        <span><strong>Server-Side Access Control</strong>: High-security storage for sensitive verification evidence outside the public web root.</span>
                    </li>
                </ul>
            </div>
            <div style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: var(--radius-md); padding: 2rem; -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);">
                <h3 style="color: var(--color-white); font-size: 1.25rem; margin-bottom: 1rem;">Our Safeguarding Policy</h3>
                <p style="color: var(--color-navy-100); font-size: 0.9rem; margin-bottom: 1.5rem;">
                    Platform managers actively oversee tutor registrations and lesson feedback. Read our complete safeguarding architecture and verification workflows.
                </p>
                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 0.85rem; color: var(--color-navy-100); line-height: 1.5;">
                        Platform managers actively oversee tutor registrations, background verification, and lesson safeguarding protocols.
                    </div>
                </div>
                <a href="/about.php#safeguarding" class="btn btn-emerald" style="width: 100%;">Read Safeguarding Framework &rarr;</a>
            </div>
        </div>
    </div>
</section>

<!-- Section 3: UK Curricula & Key Stages -->
<section class="section section-alt" aria-labelledby="stages-heading">
    <div class="container">
        <div style="text-align: center; max-width: 720px; margin: 0 auto 3.5rem;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Academic Coverage</span>
            <h2 id="stages-heading" style="margin-bottom: 1rem;">Specialist Tutoring Across Every Stage</h2>
            <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                Targeted academic support aligned with UK national curriculum standards and major examining bodies.
            </p>
        </div>

        <div class="grid-4">
            <!-- Primary -->
            <div class="card">
                <div class="card-icon" aria-hidden="true">&#127793;</div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span class="badge badge-primary">Ages 5–11</span>
                </div>
                <h3 class="card-title">Primary School</h3>
                <p class="card-body">
                    Key Stages 1 & 2 covering core English literacy, Maths mastery, foundational science, phonics, and selective 11+ grammar entrance preparation.
                </p>
                <a href="/subjects.php#primary" style="font-weight: 600; font-size: 0.9rem;">View Primary Subjects &rarr;</a>
            </div>

            <!-- Lower Secondary -->
            <div class="card">
                <div class="card-icon" aria-hidden="true">&#128218;</div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span class="badge badge-primary">Ages 11–14</span>
                </div>
                <h3 class="card-title">Lower Secondary (KS3)</h3>
                <p class="card-body">
                    Years 7–9 consolidation bridging the gap to GCSEs. Deepening understanding in STEM, English comprehension, history, and modern languages.
                </p>
                <a href="/subjects.php#ks3" style="font-weight: 600; font-size: 0.9rem;">View KS3 Subjects &rarr;</a>
            </div>

            <!-- GCSE -->
            <div class="card">
                <div class="card-icon" aria-hidden="true">&#9878;</div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span class="badge badge-primary">Ages 14–16</span>
                </div>
                <h3 class="card-title">GCSE Examinations</h3>
                <p class="card-body">
                    Targeted preparation for AQA, Edexcel, and OCR exams. Mastering mark schemes, exam time management, and structured essay/problem-solving technique.
                </p>
                <a href="/subjects.php#gcse" style="font-weight: 600; font-size: 0.9rem;">View GCSE Subjects &rarr;</a>
            </div>

            <!-- A-Level -->
            <div class="card">
                <div class="card-icon" aria-hidden="true">&#127979;</div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span class="badge badge-primary">Ages 16–18</span>
                </div>
                <h3 class="card-title">A-Level & Further</h3>
                <p class="card-body">
                    Advanced single-subject mastery for Sixth Form students preparing for university entrance, UCAS personal statements, and competitive degree programs.
                </p>
                <a href="/subjects.php#alevel" style="font-weight: 600; font-size: 0.9rem;">View A-Level Subjects &rarr;</a>
            </div>
        </div>
    </div>
</section>

<!-- Section 4: Transparent Pricing & Governance -->
<section class="section" aria-labelledby="pricing-preview-heading">
    <div class="container">
        <div style="text-align: center; max-width: 720px; margin: 0 auto 3rem;">
            <span class="badge badge-verified" style="margin-bottom: 0.75rem;">Fair & Transparent Rates</span>
            <h2 id="pricing-preview-heading" style="margin-bottom: 1rem;">Transparent Tutoring Fees</h2>
            <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                Indicative fee ranges vary based on subject difficulty, key stage, and individual tutor qualifications. Direct inquiry-first scheduling with no hidden platform fees.
            </p>
        </div>

        <div class="table-responsive" style="max-width: 860px; margin: 0 auto 2rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Curriculum Level</th>
                        <th scope="col">Subject Scope</th>
                        <th scope="col">Indicative Range (Hourly)</th>
                        <th scope="col">Delivery Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Primary (KS1 & KS2)</strong></td>
                        <td>Maths, English, Reading, 11+</td>
                        <td>&pound;25 &ndash; &pound;40 / hr</td>
                        <td>Online or In-Person</td>
                    </tr>
                    <tr>
                        <td><strong>Lower Secondary (KS3)</strong></td>
                        <td>STEM, English, Humanities, Languages</td>
                        <td>&pound;30 &ndash; &pound;45 / hr</td>
                        <td>Online or In-Person</td>
                    </tr>
                    <tr>
                        <td><strong>GCSE (KS4)</strong></td>
                        <td>All Subjects & Exam Boards</td>
                        <td>&pound;35 &ndash; &pound;55 / hr</td>
                        <td>Online or In-Person</td>
                    </tr>
                    <tr>
                        <td><strong>A-Level (KS5)</strong></td>
                        <td>Specialist Sciences, Maths, Humanities</td>
                        <td>&pound;45 &ndash; &pound;70 / hr</td>
                        <td>Online or In-Person</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="text-align: center;">
            <a href="/pricing.php" class="btn btn-outline">Read Full Pricing & Policy Details &rarr;</a>
        </div>
    </div>
</section>

<!-- Section: Become an AppTutors Educator / Apply as a Tutor -->
<section class="section" id="apply-as-tutor-section" style="background: linear-gradient(135deg, var(--color-navy-950) 0%, var(--color-navy-900) 100%); color: var(--color-white); padding: 4.5rem 0;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 2.5rem;">
            <div style="max-width: 650px;">
                <span class="badge badge-verified" style="margin-bottom: 0.85rem; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7;">
                    Teach with AppTutors UK
                </span>
                <h2 style="font-size: 2.1rem; color: var(--color-white); margin-bottom: 0.85rem; line-height: 1.25;">
                    Are You a Qualified UK Educator? Apply as a Tutor
                </h2>
                <p style="color: var(--color-navy-200); font-size: 1.1rem; line-height: 1.6; margin-bottom: 1.5rem;">
                    Join our vetted roster of passionate subject specialists. Teach Primary, KS3, GCSE, or A-Level students across England, Wales, and Northern Ireland with full control over your calendar and fees.
                </p>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap; font-size: 0.95rem; color: var(--color-navy-300);">
                    <div><span style="color: var(--color-emerald-400); font-weight: 700;">&#10003;</span> Set your own hourly rate (&pound;25–&pound;70+/hr)</div>
                    <div><span style="color: var(--color-emerald-400); font-weight: 700;">&#10003;</span> Publish flexible Europe/London slots</div>
                    <div><span style="color: var(--color-emerald-400); font-weight: 700;">&#10003;</span> Transparent manager safeguarding review</div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.85rem; min-width: 260px; text-align: center;">
                <a href="/register.php?type=tutor" class="btn btn-primary btn-lg" id="btn-apply-tutor-banner" style="font-size: 1.15rem; padding: 1rem 2rem;">
                    Apply as a Tutor &rarr;
                </a>
                <span style="font-size: 0.85rem; color: var(--color-navy-400);">Enhanced DBS checking required before publishing slots</span>
            </div>
        </div>
    </div>
</section>

<!-- Section 5: Call to Action -->
<section class="section section-navy" aria-labelledby="cta-heading">
    <div class="container" style="text-align: center; max-width: 760px;">
        <span class="badge badge-verified" style="margin-bottom: 1rem;">Start Your Learning Journey</span>
        <h2 id="cta-heading" style="color: var(--color-white); margin-bottom: 1.25rem;">
            Ready to Connect with a Subject Specialist?
        </h2>
        <p style="color: var(--color-navy-200); font-size: 1.15rem; margin-bottom: 2.25rem;">
            Explore approved tutor profiles, check verified DBS credentials, and request lesson times that suit your family's weekly routine.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="/tutors.php" class="btn btn-primary btn-lg">Browse Verified Tutors</a>
            <a href="/register.php?type=tutor" class="btn btn-secondary btn-lg" id="btn-cta-apply-tutor">&#9997; Apply as a Tutor</a>
            <a href="/register.php" class="btn btn-outline btn-lg" style="color: var(--color-white); border-color: rgba(255, 255, 255, 0.3);">Register as Parent or Student</a>
        </div>
    </div>
</section>
