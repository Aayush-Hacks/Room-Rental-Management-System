/* =====================================================================
   Room Rental System — dashboard.js
   Vanilla JS for the logged-in dashboard area.
   Handles: mobile sidebar toggle, user dropdown, flash messages, dark mode.
   ===================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    initDashMobileToggle();
    initFlashDismiss();
});

/* ---------------------------------------------------------------------
   Dashboard mobile menu toggle
   --------------------------------------------------------------------- */
function initDashMobileToggle() {
    var toggle = document.getElementById('dashMobileToggle');
    var menu = document.getElementById('dashMobileMenu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
        var isOpen = menu.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}

/* ---------------------------------------------------------------------
   Auto-dismiss flash messages after 6 seconds
   --------------------------------------------------------------------- */
function initFlashDismiss() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (el) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.3s ease';
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 300);
        }, 6000);
    });
}

