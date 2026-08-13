/* ===================================================================
   Room Rental Management System — main.js
   =================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    initMobileNav();
    initStickyNav();
    initFaq();
    initBackToTop();
    initRegisterForm();
    initLoginForm();
    initPasswordToggles();
    initCookieConsent();
    initDarkMode();
});

function initMobileNav() {
    var btn = document.querySelector('.menu-btn');
    var menu = document.querySelector('.mobile-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function () {
        btn.classList.toggle('open');
        menu.classList.toggle('open');
    });

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            btn.classList.remove('open');
            menu.classList.remove('open');
        });
    });
}

function initStickyNav() {
    var header = document.querySelector('.header');
    if (!header) return;
    if (window.scrollY > 40) header.classList.add('scrolled');
    window.addEventListener('scroll', function () {
        header.classList.toggle('scrolled', window.scrollY > 40);
    });
}

function initFaq() {
    var items = document.querySelectorAll('.faq-item');
    if (!items.length) return;
    items.forEach(function (item) {
        var btn = item.querySelector('.faq-btn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var open = item.classList.contains('open');
            items.forEach(function (o) { o.classList.remove('open'); var b = o.querySelector('.faq-btn'); if (b) b.setAttribute('aria-expanded', 'false'); });
            if (!open) { item.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
        });
    });
}

function initBackToTop() {
    var btn = document.getElementById('topBtn');
    if (!btn) return;
    window.addEventListener('scroll', function () { btn.classList.toggle('visible', window.scrollY > 500); });
    btn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
}

function initRegisterForm() {
    var form = document.getElementById('registerForm');
    if (!form) return;
    var password = form.querySelector('#password');
    var confirmPassword = form.querySelector('#confirm_password');
    var citFront = form.querySelector('#citizenship_front');
    var citBack = form.querySelector('#citizenship_back');

    // Citizenship upload tiles — show the chosen file name and mark as selected
    [citFront, citBack].forEach(function (input) {
        if (!input) return;
        input.addEventListener('change', function () {
            var tile = input.closest('.doc-upload');
            var file = input.files && input.files[0];
            if (!tile) return;
            var sub = tile.querySelector('.doc-upload-sub');
            if (file) {
                tile.classList.add('has-file');
                tile.classList.remove('invalid');
                if (sub) sub.textContent = '✓ ' + file.name;
            } else {
                tile.classList.remove('has-file');
                if (sub) sub.textContent = '';
            }
        });
    });

    // Live password strength meter under the password field.
    // Scores the 5 policy criteria (length, lower, upper, number, symbol);
    // a full score of 5 matches exactly what the validator accepts.
    var strengthMeter = form.querySelector('.strength-meter');
    if (password && strengthMeter) {
        var updateStrength = function () {
            var pw = password.value;
            var score = 0;
            if (pw.length >= 8) score++;
            if (/[a-z]/.test(pw)) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/\d/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;

            var level = 0;
            var label = 'Password strength';
            if (pw.length > 0) {
                if (score <= 2) { level = 1; label = 'Password strength: Weak'; }
                else if (score <= 4) { level = 2; label = 'Password strength: Medium'; }
                else { level = 3; label = 'Password strength: Strong'; }
            }
            strengthMeter.setAttribute('data-level', level);

            var bars = strengthMeter.querySelectorAll('.strength-bar');
            bars.forEach(function (bar, i) { bar.classList.toggle('is-filled', i < score); });
            var labelEl = strengthMeter.querySelector('.strength-label');
            if (labelEl) labelEl.textContent = label;
            strengthMeter.classList.add('show');
        };
        password.addEventListener('input', updateStrength);
        password.addEventListener('focus', updateStrength);
    }

    form.addEventListener('submit', function (e) {
        var valid = true;
        var phone = form.querySelector('#phone');

        valid = validateField(form.querySelector('#full_name'), function (v) { return v.trim().length >= 5; }, 'Enter your full name (at least 5 characters).') && valid;
        valid = validateField(form.querySelector('#email'), function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }, 'Enter a valid email address.') && valid;
        valid = validateField(phone, function (v) { return /^9\d{9}$/.test(nepalPhoneDigits(v)); }, 'Enter a valid 10-digit Nepali mobile number (e.g., 9812345678).') && valid;
        valid = validateField(citFront, function (v) { return citizenshipFileValid(citFront); }, 'Upload the front side of your citizenship (JPG, PNG or WebP, under 2MB).') && valid;
        valid = validateField(citBack, function (v) { return citizenshipFileValid(citBack); }, 'Upload the back side of your citizenship (JPG, PNG or WebP, under 2MB).') && valid;
        valid = validateField(password, function (v) { return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(v); }, 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.') && valid;
        valid = validateField(confirmPassword, function (v) { return v === password.value && v.length > 0; }, 'Passwords do not match.') && valid;

        // Normalize to Nepal's +977-XXXXXXXXXX format before submitting
        if (phone && valid) {
            var digits = nepalPhoneDigits(phone.value);
            if (/^\d{10}$/.test(digits)) phone.value = '+977-' + digits;
        }

        var roleChecked = form.querySelector('input[name="role"]:checked');
        var roleError = form.querySelector('.role-error');
        if (!roleChecked) { valid = false; if (roleError) roleError.classList.add('show'); }
        else if (roleError) roleError.classList.remove('show');

        if (!valid) e.preventDefault();
    });

    wireRoleToggle(form);
}

function initLoginForm() {
    var form = document.getElementById('loginForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var valid = true;
        valid = validateField(form.querySelector('#email'), function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }, 'Enter a valid email address.') && valid;
        valid = validateField(form.querySelector('#password'), function (v) { return v.length > 0; }, 'Enter your password.') && valid;

        if (!valid) e.preventDefault();
    });
}

/* Toggle password visibility for any field wrapped in .password-input */
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        var wrap = btn.closest('.password-input');
        if (!wrap) return;
        var input = wrap.querySelector('input');
        if (!input) return;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var eye = btn.querySelector('.icon-eye');
            var eyeOff = btn.querySelector('.icon-eye-off');
            if (eye) eye.style.display = show ? 'none' : '';
            if (eyeOff) eyeOff.style.display = show ? '' : 'none';
        });
    });
}

/* Show the landlord note on register when the Landlord section is
   selected, and hide it (plus any server-side is-visible state) otherwise. */
function wireRoleToggle(form) {
    var roleInputs = form.querySelectorAll('input[name="role"]');
    var landlordNote = form.querySelector('.form-note');
    roleInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            if (landlordNote) {
                landlordNote.classList.remove('is-visible');
                landlordNote.classList.toggle('show', input.value === 'landlord' && input.checked);
            }
        });
    });
}

/* A citizenship file is valid when present, an allowed image type, and under 2MB. */
function citizenshipFileValid(input) {
    var f = input && input.files && input.files[0];
    if (!f) return false;
    return ['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) !== -1 && f.size <= 2 * 1024 * 1024;
}

/* Extract the local 10-digit part of a Nepali phone number, accepting
   both the bare 98XXXXXXXX form and the full +977-XXXXXXXXXX form. */
function nepalPhoneDigits(value) {
    var d = (value || '').replace(/\D/g, '');
    if (d.length === 13 && d.indexOf('977') === 0) d = d.slice(3);
    return d;
}

function validateField(input, testFn, message) {
    if (!input) return true;
    var wrapper = input.closest('.form-group');
    var errorEl = wrapper ? wrapper.querySelector('.form-error') : null;
    var ok = testFn(input.value);
    input.classList.toggle('invalid', !ok);
    // Highlight the whole +977 prefixed wrapper when invalid
    if (wrapper) {
        var phoneWrap = wrapper.querySelector('.phone-input');
        if (phoneWrap) phoneWrap.classList.toggle('invalid', !ok);
    }
    if (errorEl) { errorEl.textContent = message; errorEl.classList.toggle('show', !ok); }
    return ok;
}

/* ---------------------------------------------------------------------
   Dark mode toggle — persists preference in localStorage
   --------------------------------------------------------------------- */
function initDarkMode() {
    var toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    // On page load, apply the saved preference
    var saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        toggle.textContent = '☀️';
    }

    toggle.addEventListener('click', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            toggle.textContent = '🌙';
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            toggle.textContent = '☀️';
        }
    });
}

function initCookieConsent() {
    var banner = document.getElementById('cookieBanner');
    var acceptBtn = document.getElementById('cookieAccept');
    if (!banner || !acceptBtn) return;
    if (localStorage.getItem('cookieConsent') === 'accepted') return;
    setTimeout(function () { banner.classList.add('show'); }, 600);
    acceptBtn.addEventListener('click', function () {
        localStorage.setItem('cookieConsent', 'accepted');
        banner.classList.add('hiding');
        setTimeout(function () { banner.classList.remove('show', 'hiding'); }, 400);
    });
}

