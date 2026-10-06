/**
 * UK Tutoring Platform — Production Frontend Script
 * WCAG 2.2 AA accessible navigation and progressive form enhancements
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Accessible Mobile Navigation Menu
    const menuToggle = document.getElementById('menu-toggle');
    const mobileNav = document.getElementById('mobile-nav');

    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', function () {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', !isExpanded);
            mobileNav.classList.toggle('is-open');

            if (!isExpanded) {
                // Focus first link in mobile nav
                const firstLink = mobileNav.querySelector('a');
                if (firstLink) firstLink.focus();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) {
                menuToggle.setAttribute('aria-expanded', 'false');
                mobileNav.classList.remove('is-open');
                menuToggle.focus();
            }
        });
    }

    // 2. Progressive Newsletter Form Submission
    const newsletterForms = document.querySelectorAll('.js-newsletter-form');
    newsletterForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const emailInput = form.querySelector('input[type="email"]');
            const consentCheckbox = form.querySelector('input[type="checkbox"]');
            const statusEl = form.querySelector('.js-newsletter-status');

            if (!emailInput || !consentCheckbox) return;

            if (!consentCheckbox.checked) {
                e.preventDefault();
                if (statusEl) {
                    statusEl.textContent = 'Please confirm your consent to receive educational updates.';
                    statusEl.className = 'js-newsletter-status alert alert-error';
                    statusEl.setAttribute('role', 'alert');
                }
                consentCheckbox.focus();
            }
        });
    });

    // 3. Login Category Tabs & Demo Manager Auto-Fill (CSP Compliant)
    const tabUser = document.getElementById('tab-student-user');
    const tabManager = document.getElementById('tab-manager');
    const autoFillBtn = document.getElementById('btn-autofill-demo');
    const quickMgrLink = document.getElementById('quick-manager-link');
    const emailInput = document.getElementById('login-email');
    const passInput = document.getElementById('login-password');
    const formCatInput = document.getElementById('form-category-input');
    const googleCatInput = document.getElementById('google-category-input');
    const desc = document.getElementById('category-description');
    const googleContainer = document.getElementById('google-signin-container');
    const mgrCredsBox = document.getElementById('manager-credentials-box');
    const submitBtn = document.getElementById('btn-login-submit');
    const regLink = document.getElementById('register-link-container');
    const mgrNotice = document.getElementById('manager-notice-container');
    const indicatorBar = document.getElementById('selection-indicator-bar');
    const indicatorDot = document.getElementById('selection-dot');
    const indicatorText = document.getElementById('selection-text');
    const indicatorPill = document.getElementById('selection-pill');
    const quickMgrSwitch = document.getElementById('quick-manager-switch');
    const badgeUser = document.getElementById('badge-student-user');
    const badgeManager = document.getElementById('badge-manager');

    function selectCategory(cat, updateHistory = true) {
        if (formCatInput) formCatInput.value = cat;
        if (googleCatInput) googleCatInput.value = cat;

        if (cat === 'manager') {
            if (tabUser) {
                tabUser.className = 'login-tab-btn tab-inactive';
                tabUser.setAttribute('aria-selected', 'false');
            }
            if (badgeUser) { badgeUser.className = 'tab-badge tab-badge-inactive'; }

            if (tabManager) {
                tabManager.className = 'login-tab-btn tab-active-manager';
                tabManager.setAttribute('aria-selected', 'true');
            }
            if (badgeManager) { badgeManager.className = 'tab-badge tab-badge-active'; }

            if (indicatorBar) {
                indicatorBar.style.background = '#F1F5F9';
                indicatorBar.style.border = '1.5px solid #0F172A';
                indicatorBar.style.color = '#0F172A';
            }
            if (indicatorDot) { indicatorDot.style.background = '#0F172A'; }
            if (indicatorText) { indicatorText.innerHTML = 'Selected Role: <strong style="color: #0F172A;">🛡️ Manager Portal (Administrative)</strong>'; }
            if (indicatorPill) {
                indicatorPill.style.background = '#0F172A';
                indicatorPill.style.color = '#FFFFFF';
                indicatorPill.innerText = 'MANAGER';
            }

            if (desc) desc.innerHTML = '<strong>Manager Access</strong>: Privileged administrative access for application review, tutor approval, and governance.';
            if (googleContainer) googleContainer.style.display = 'none';
            if (mgrCredsBox) mgrCredsBox.style.display = 'block';
            if (quickMgrSwitch) quickMgrSwitch.style.display = 'none';
            if (submitBtn) submitBtn.innerHTML = 'Sign In as Manager &rarr;';
            if (regLink) regLink.style.display = 'none';
            if (mgrNotice) mgrNotice.style.display = 'block';

            if (emailInput && (!emailInput.value || emailInput.value === 'user@example.co.uk')) {
                emailInput.value = 'manager@apptutors.co.uk';
            }
            if (passInput && !passInput.value) {
                passInput.value = 'Manager2026!';
            }

            if (updateHistory && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '?type=manager');
            }
        } else {
            if (tabUser) {
                tabUser.className = 'login-tab-btn tab-active-user';
                tabUser.setAttribute('aria-selected', 'true');
            }
            if (badgeUser) { badgeUser.className = 'tab-badge tab-badge-active'; }

            if (tabManager) {
                tabManager.className = 'login-tab-btn tab-inactive';
                tabManager.setAttribute('aria-selected', 'false');
            }
            if (badgeManager) { badgeManager.className = 'tab-badge tab-badge-inactive'; }

            if (indicatorBar) {
                indicatorBar.style.background = '#EFF6FF';
                indicatorBar.style.border = '1.5px solid #3B82F6';
                indicatorBar.style.color = '#1E40AF';
            }
            if (indicatorDot) { indicatorDot.style.background = '#2563EB'; }
            if (indicatorText) { indicatorText.innerHTML = 'Selected Role: <strong style="color: #1E40AF;">🎓 Student / Parent / Tutor Portal</strong>'; }
            if (indicatorPill) {
                indicatorPill.style.background = '#2563EB';
                indicatorPill.style.color = '#FFFFFF';
                indicatorPill.innerText = 'STUDENT / USER';
            }

            if (desc) desc.innerHTML = '<strong>Student / User Access</strong>: For Students, Parents, and Tutors accessing their dashboard and schedules.';
            if (googleContainer) googleContainer.style.display = 'block';
            if (mgrCredsBox) mgrCredsBox.style.display = 'none';
            if (quickMgrSwitch) quickMgrSwitch.style.display = 'flex';
            if (submitBtn) submitBtn.innerHTML = 'Sign In to Dashboard &rarr;';
            if (regLink) regLink.style.display = 'block';
            if (mgrNotice) mgrNotice.style.display = 'none';

            if (emailInput && emailInput.value === 'manager@apptutors.co.uk') {
                emailInput.value = '';
            }
            if (passInput && passInput.value === 'Manager2026!') {
                passInput.value = '';
            }

            if (updateHistory && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '?type=user');
            }
        }
    }

    if (tabUser) {
        tabUser.addEventListener('click', function (e) {
            e.preventDefault();
            selectCategory('user');
        });
    }

    if (tabManager) {
        tabManager.addEventListener('click', function (e) {
            e.preventDefault();
            selectCategory('manager');
        });
    }

    if (quickMgrLink) {
        quickMgrLink.addEventListener('click', function (e) {
            e.preventDefault();
            selectCategory('manager');
        });
    }

    if (autoFillBtn) {
        autoFillBtn.addEventListener('click', function (e) {
            e.preventDefault();
            selectCategory('manager');
            if (emailInput) {
                emailInput.value = 'manager@apptutors.co.uk';
                emailInput.style.backgroundColor = '#FEF3C7';
                setTimeout(function () { emailInput.style.backgroundColor = ''; }, 600);
            }
            if (passInput) {
                passInput.value = 'Manager2026!';
                passInput.style.backgroundColor = '#FEF3C7';
                setTimeout(function () { passInput.style.backgroundColor = ''; }, 600);
            }
        });
    }

    // Google Sign-In button handler
    const googleBtn = document.getElementById('google-signin-btn');
    if (googleBtn) {
        googleBtn.addEventListener('click', function () {
            const promptToken = prompt('Google Sign-In via Firebase Authentication.\nEnter Firebase Google ID Token (or test token) to verify:', '');
            if (promptToken) {
                const idInput = document.getElementById('google-id-token');
                const gForm = document.getElementById('google-login-form');
                if (idInput && gForm) {
                    idInput.value = promptToken;
                    gForm.submit();
                }
            }
        });
    }
});
