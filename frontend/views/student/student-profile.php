<!-- Student & Parent Profile Management Interface -->
<div class="container section">
    <div style="max-width: 860px; margin: 0 auto;">
        
        <!-- Header & Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav aria-label="Breadcrumb" style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem; color: var(--color-navy-500); margin-bottom: 0.75rem;">
                <a href="/">Home</a> &rsaquo; <span>Family Portal</span> &rsaquo; <span style="color: var(--color-navy-800); font-weight: 600;">Profile</span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">Student & Parent Profile</h1>
                    <p style="color: var(--color-navy-600); font-size: 1.05rem;">
                        Manage your account details and contact preferences for UK 1-to-1 tutoring.
                    </p>
                </div>
                <div>
                    <a href="/parent-children.php" class="btn btn-secondary btn-sm" id="btn-manage-children">
                        Manage Children & Dependents &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Success & Error Alerts -->
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

        <!-- Account Authority & Status Badge Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--color-primary-500);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="font-weight: 700; font-size: 1.05rem; color: var(--color-navy-950); margin-bottom: 0.25rem;">
                        Application Role & Identity Authority
                    </div>
                    <p style="color: var(--color-navy-600); font-size: 0.9rem; margin-bottom: 0;">
                        Identity authenticated via Firebase Authentication. Platform role strictly derived from MySQL platform authority.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <span class="badge badge-primary">Role: <?= e($profile['role'] ?? 'STUDENT_PARENT') ?></span>
                    <span class="badge badge-verified">Status: <?= e($profile['status'] ?? 'ACTIVE') ?></span>
                </div>
            </div>
        </div>

        <!-- Section 1: Profile Information Form -->
        <div class="card" style="padding: 2.25rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-md);">
            <h2 style="font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--color-navy-950);">
                Personal & Contact Details
            </h2>
            <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 1.75rem;">
                Keep your contact details up to date for session communication and geographic tutor matching.
            </p>

            <form method="POST" action="/student-profile.php" novalidate id="student-profile-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="display_name" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Full Name / Display Name <span style="color: var(--color-rose-600);">*</span>
                    </label>
                    <input type="text"
                           id="display_name"
                           name="display_name"
                           class="form-control"
                           required
                           maxlength="150"
                           value="<?= e($profile['display_name'] ?? '') ?>"
                           style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                           aria-describedby="display_name_hint">
                    <small id="display_name_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                        Your name as it appears to verified tutors on the platform.
                    </small>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="email" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Email Address (Identity Authority)
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control"
                           readonly
                           disabled
                           value="<?= e($profile['email'] ?? '') ?>"
                           style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); background-color: var(--color-navy-50); color: var(--color-navy-600); font-size: 1rem;"
                           aria-describedby="email_hint">
                    <small id="email_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                        Primary email managed by Firebase Authentication credentials. Protected from client modification.
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label for="phone" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            Contact Telephone
                        </label>
                        <input type="tel"
                               id="phone"
                               name="phone"
                               class="form-control"
                               maxlength="40"
                               placeholder="e.g. 07700 900123"
                               value="<?= e($profile['phone'] ?? '') ?>"
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem;"
                               aria-describedby="phone_hint">
                        <small id="phone_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            UK mobile or landline for booking and urgent communications.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="postcode" class="form-label" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                            UK Postcode
                        </label>
                        <input type="text"
                               id="postcode"
                               name="postcode"
                               class="form-control"
                               maxlength="20"
                               placeholder="e.g. SW1A 1AA"
                               value="<?= e($profile['postcode'] ?? '') ?>"
                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-navy-200); border-radius: var(--radius-sm); font-size: 1rem; text-transform: uppercase;"
                               aria-describedby="postcode_hint">
                        <small id="postcode_hint" style="color: var(--color-navy-500); font-size: 0.825rem; display: block; margin-top: 0.25rem;">
                            Used to suggest local in-person tutors if requested.
                        </small>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" id="btn-save-profile">
                        Save Profile Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Section 2: Family & Dependent Overview Card -->
        <div class="card" style="padding: 2rem; margin-bottom: 2rem; background-color: var(--color-navy-50); border: 1px solid var(--color-navy-200);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.15rem; color: var(--color-navy-950); margin-bottom: 0.35rem;">
                        Children & Dependents Management
                    </h3>
                    <p style="color: var(--color-navy-600); font-size: 0.925rem; margin-bottom: 0;">
                        Are you managing learning for one or more school-aged children? Register your children's Key Stage, year group, and curriculum focus.
                    </p>
                </div>
                <div>
                    <a href="/parent-children.php" class="btn btn-outline btn-sm">
                        Go to Children Management &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Open Decision Callout -->
        <div class="card" style="padding: 1.5rem; background-color: var(--color-white); border-left: 4px solid var(--color-amber-500);">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.25rem; line-height: 1;" aria-hidden="true">&#9432;</span>
                <div>
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-navy-950); margin-bottom: 0.25rem;">
                        Architectural Note: Multi-Guardian Access (Open Decision)
                    </h4>
                    <p style="color: var(--color-navy-600); font-size: 0.875rem; margin-bottom: 0; line-height: 1.5;">
                        The current database schema establishes a 1-to-many relationship between a single parent account and multiple children. Multi-guardian / joint parental access models remain an open client decision (DISC-032 / Master Document Section 4.3) and have not been preemptively assumed.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>
