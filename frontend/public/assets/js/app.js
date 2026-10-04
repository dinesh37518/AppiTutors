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
});
