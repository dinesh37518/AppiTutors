<!-- Contact & Admissions Support Page -->
<div class="container section">
    <div style="max-width: 900px; margin: 0 auto;">
        <div style="margin-bottom: 3.5rem; text-align: center;">
            <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Get in Touch</span>
            <h1 style="margin-bottom: 1rem;">Contact AppTutors UK Admissions</h1>
            <p style="font-size: 1.15rem; color: var(--color-navy-600); max-width: 680px; margin: 0 auto;">
                Have questions regarding tutor matching, curriculum specifications, or our safeguarding standards? Our UK support team is here to assist.
            </p>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" style="margin-bottom: 2.5rem;">
                <span style="font-weight: 700;">&#10003; Message Received:</span>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 2.5rem;">
                <div>
                    <div style="font-weight: 700; margin-bottom: 0.35rem;">Please correct the following errors:</div>
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid-3" style="grid-template-columns: 1.1fr 0.9fr; gap: 3rem; align-items: flex-start;">
            <!-- Contact Form -->
            <div class="card" style="padding: 2.25rem;">
                <h2 style="font-size: 1.35rem; margin-bottom: 1.25rem;">Send an Inquiry</h2>

                <form action="/contact.php" method="POST" novalidate>
                    <div class="form-group">
                        <label for="contact-name" class="form-label form-label-required">Full Name</label>
                        <input type="text" id="contact-name" name="name" class="form-input" required maxlength="120" value="<?= e($formData['name'] ?? '') ?>" placeholder="e.g. Sarah Jenkins">
                    </div>

                    <div class="form-group">
                        <label for="contact-email" class="form-label form-label-required">Email Address</label>
                        <input type="email" id="contact-email" name="email" class="form-input" required maxlength="255" value="<?= e($formData['email'] ?? '') ?>" placeholder="parent@example.co.uk">
                        <div class="form-hint">We will reply to this address within 1 business day.</div>
                    </div>

                    <div class="form-group">
                        <label for="contact-type" class="form-label form-label-required">Enquiry Type</label>
                        <select id="contact-type" name="type" class="form-select" required>
                            <option value="">-- Please Select --</option>
                            <option value="PARENT_STUDENT" <?= (($formData['type'] ?? '') === 'PARENT_STUDENT') ? 'selected' : '' ?>>Parent / Student Tutoring Request</option>
                            <option value="TUTOR_APPLICATION" <?= (($formData['type'] ?? '') === 'TUTOR_APPLICATION') ? 'selected' : '' ?>>Prospective Tutor Onboarding</option>
                            <option value="SAFEGUARDING" <?= (($formData['type'] ?? '') === 'SAFEGUARDING') ? 'selected' : '' ?>>Safeguarding & DBS Query</option>
                            <option value="GENERAL" <?= (($formData['type'] ?? '') === 'GENERAL') ? 'selected' : '' ?>>General Platform Inquiry</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="contact-subject" class="form-label form-label-required">Subject / Key Stage</label>
                        <input type="text" id="contact-subject" name="subject" class="form-input" required maxlength="150" value="<?= e($formData['subject'] ?? '') ?>" placeholder="e.g. GCSE Higher Maths (Edexcel)">
                    </div>

                    <div class="form-group">
                        <label for="contact-message" class="form-label form-label-required">Message</label>
                        <textarea id="contact-message" name="message" class="form-textarea" required rows="5" placeholder="Please describe your tutoring requirements, current grade levels, or specific queries..."><?= e($formData['message'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-checkbox-label">
                            <input type="checkbox" name="consent" value="1" required class="form-checkbox" <?= (!empty($formData['consent'])) ? 'checked' : '' ?>>
                            <span>I consent to having AppTutors UK store and process my submitted inquiry details to formulate a response (implemented as an explicit-consent engineering control; pending client/legal approval of the authoritative legal policy).</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Submit Inquiry &rarr;</button>
                </form>
            </div>

            <!-- Support Channels & Hours -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="card" style="padding: 1.75rem;">
                    <div class="card-icon" aria-hidden="true">&#128338;</div>
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Operating Hours</h3>
                    <p style="font-size: 0.9rem; color: var(--color-navy-600); margin-bottom: 0.75rem;">
                        Our admissions advisory and safeguarding team operates during standard UK business hours:
                    </p>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: var(--color-navy-700);">
                        <li><strong>Monday &ndash; Friday</strong>: 09:00 &ndash; 18:00</li>
                        <li><strong>Saturday</strong>: 10:00 &ndash; 14:00</li>
                        <li><strong>Sunday & Bank Holidays</strong>: Closed</li>
                    </ul>
                    <div style="font-size: 0.8rem; color: var(--color-navy-500); margin-top: 0.75rem;">
                        All times scheduled in <strong>Europe/London (GMT/BST)</strong>.
                    </div>
                </div>

                <div class="card" style="padding: 1.75rem;">
                    <div class="card-icon card-icon-emerald" aria-hidden="true">&#128737;</div>
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Safeguarding Support</h3>
                    <p style="font-size: 0.9rem; color: var(--color-navy-600); margin-bottom: 0.75rem;">
                        Direct line to the designated platform safeguarding officer for child protection questions or tutor certificate checks:
                    </p>
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-navy-950);">
                        Email: <code>safeguarding@apptutors.co.uk</code>
                    </div>
                </div>

                <!-- Contact Manager WhatsApp Option -->
                <div class="card" style="padding: 1.75rem; border: 1px solid var(--color-emerald-500); background: #f0fdf4;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.5rem;" aria-hidden="true">&#128172;</span>
                        <h3 style="font-size: 1.15rem; margin: 0; color: #166534;">Contact Manager</h3>
                    </div>
                    <p style="font-size: 0.9rem; color: #15803d; margin-bottom: 1rem; line-height: 1.5;">
                        Connect directly with the platform admissions manager via WhatsApp for urgent enquiries, tutor onboarding questions, or booking assistance.
                    </p>
                    <?php
                    $waNumber = preg_replace('/[^0-9]/', '', $managerWhatsApp ?? '447700900000');
                    $waUrl = 'https://wa.me/' . $waNumber . '?text=' . urlencode('Hello AppTutors Manager, I have an inquiry regarding UK tutoring services.');
                    ?>
                    <a href="<?= htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8') ?>" 
                       id="btn-contact-manager-whatsapp"
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="btn" 
                       style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; background: #25D366; color: #ffffff; font-weight: 600; padding: 0.75rem 1rem; border-radius: var(--radius-sm); text-decoration: none; border: none; text-align: center;">
                        <span>&#128172;</span>
                        <span>Chat with Manager on WhatsApp</span>
                    </a>
                </div>

                <div class="card" style="padding: 1.75rem; background: var(--color-navy-50);">
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Transactional Email</h3>
                    <p style="font-size: 0.875rem; color: var(--color-navy-600); margin-bottom: 0.5rem;">
                        Automated confirmations and lesson updates are dispatched via our server-side <code>EmailService</code> abstraction.
                    </p>
                    <span class="badge badge-open-decision">EMAILJS & LOG TRANSLATION</span>
                </div>
            </div>
        </div>
    </div>
</div>
