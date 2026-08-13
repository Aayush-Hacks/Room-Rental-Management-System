/**
 * cookie-consent.js
 * -----------------------------------------------------------------
 * Vanilla JS cookie consent banner. Shows a GDPR-style notice
 * on first visit and remembers the user's choice for 90 days.
 * Dynamically creates the banner HTML so no markup changes needed.
 * -----------------------------------------------------------------
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'rr_cookie_consent';
    var EXPIRY_DAYS = 90;

    // Check if user already made a choice
    function getCookie(name) {
        var match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return match ? decodeURIComponent(match[2]) : null;
    }

    function setCookie(name, value, days) {
        var expires = new Date();
        expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + '=' + encodeURIComponent(value) +
            ';expires=' + expires.toUTCString() +
            ';path=/;SameSite=Lax';
    }

    // Don't show if already consented
    if (getCookie(COOKIE_NAME) === 'accepted' || getCookie(COOKIE_NAME) === 'rejected') {
        return;
    }

    // Build banner
    var banner = document.createElement('div');
    banner.id = 'cookieBanner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Cookie consent');
    banner.innerHTML =
        '<div class="cookie-content">' +
            '<div class="cookie-text">' +
                '<strong>We use cookies</strong>' +
                '<p>This site uses essential cookies to improve your experience and keep you logged in. By continuing, you accept our use of cookies.</p>' +
            '</div>' +
            '<div class="cookie-actions">' +
                '<button class="btn btn-ghost" id="cookieReject" style="padding:8px 16px; font-size:0.85rem;">Reject</button>' +
                '<button class="btn btn-primary" id="cookieAccept" style="padding:8px 16px; font-size:0.85rem;">Accept all</button>' +
            '</div>' +
        '</div>';

    document.body.appendChild(banner);

    // Reveal with a short delay for smooth animation
    requestAnimationFrame(function () {
        banner.classList.add('is-visible');
    });

    // Handle accept
    document.getElementById('cookieAccept').addEventListener('click', function () {
        setCookie(COOKIE_NAME, 'accepted', EXPIRY_DAYS);
        banner.classList.remove('is-visible');
        banner.classList.add('is-dismissing');
        setTimeout(function () {
            if (banner.parentNode) banner.parentNode.removeChild(banner);
        }, 400);
    });

    // Handle reject
    document.getElementById('cookieReject').addEventListener('click', function () {
        setCookie(COOKIE_NAME, 'rejected', EXPIRY_DAYS);
        banner.classList.remove('is-visible');
        banner.classList.add('is-dismissing');
        setTimeout(function () {
            if (banner.parentNode) banner.parentNode.removeChild(banner);
        }, 400);
    });
})();
