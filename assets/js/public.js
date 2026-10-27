/* Public site behaviour (spec 09): the mobile menu. Vanilla JS, no dependencies.
   Without JavaScript the navigation is shown inline by public-nojs.css. */
(function () {
    'use strict';

    var nav = document.querySelector('[data-site-nav]');
    var toggle = document.querySelector('[data-nav-toggle]');
    var close = document.querySelector('[data-nav-close]');
    if (!nav || !toggle || !close) { return; }

    var wide = window.matchMedia('(min-width: 62em)');
    var outside = document.querySelectorAll('main, footer, .skip-link, .site-header__inner > .site-logo, [data-nav-toggle]');

    function setOutside(inert) {
        for (var i = 0; i < outside.length; i++) {
            if (inert) { outside[i].setAttribute('inert', ''); } else { outside[i].removeAttribute('inert'); }
        }
    }

    function isOpen() { return nav.classList.contains('is-open'); }

    function open() {
        nav.removeAttribute('inert');
        nav.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('menu-open');
        setOutside(true);
        close.focus();
    }

    function shut(returnFocus) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
        setOutside(false);
        if (!wide.matches) { nav.setAttribute('inert', ''); }
        if (returnFocus) { toggle.focus(); }
    }

    function focusable() {
        return Array.prototype.slice.call(nav.querySelectorAll('a[href], button:not([disabled])'));
    }

    // On small screens the closed menu must not be reachable by keyboard or screen reader.
    function sync() {
        if (wide.matches) {
            nav.removeAttribute('inert');
            if (isOpen()) { shut(false); }
        } else if (!isOpen()) {
            nav.setAttribute('inert', '');
        }
    }

    toggle.addEventListener('click', open);
    close.addEventListener('click', function () { shut(true); });

    nav.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('a')) { shut(false); }
    });

    document.addEventListener('keydown', function (event) {
        if (!isOpen()) { return; }
        if (event.key === 'Escape') {
            shut(true);
            return;
        }
        if (event.key === 'Tab') {
            var items = focusable();
            if (!items.length) { return; }
            var first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    if (wide.addEventListener) { wide.addEventListener('change', sync); } else if (wide.addListener) { wide.addListener(sync); }
    sync();
}());

/* Cookie settings (spec 18): "Cookie settings" opens the dialog in place of the /cookies/ page.
   Saving is a normal form POST, so without JavaScript (or without <dialog> support) the links simply open /cookies/. */
(function () {
    'use strict';

    var dialog = document.querySelector('[data-cookie-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') { return; }
    var opener = null;

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('[data-cookie-settings]');
        if (link) {
            event.preventDefault();
            opener = link;
            dialog.showModal();
            var first = dialog.querySelector('input[type="checkbox"]');
            if (first) { first.focus(); }
            return;
        }
        if (event.target.closest && event.target.closest('[data-cookie-close]')) { dialog.close(); }
    });

    // A click on the backdrop (outside the form) closes the dialog, like Escape does natively.
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) { dialog.close(); }
    });

    dialog.addEventListener('close', function () {
        if (opener && document.contains(opener)) { opener.focus(); }
    });
}());

/* Toasts (2026-10-22): every form's feedback (welcome, "too many attempts", confirmations, errors) is one toast at a time,
   bottom left, with a progress bar that shows when it closes. It closes when the bar runs out (CSS animation, paused while
   the pointer or focus is on it), with its close button, or with Escape. With reduced motion there is no moving bar: a
   timer closes it instead (also paused on hover/focus). The server prints a toast in the footer; scripts show one with
   window.publicToast(type, text). Without JavaScript the server's toast simply stays at the bottom of the page. */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ICONS = {
        success: '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'
    };
    var svg = function (paths) { return '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' + paths + '</svg>'; };
    var current = null;

    function wire(toast) {
        var bar = toast.querySelector('[data-toast-progress]');
        var ms = parseInt(toast.getAttribute('data-toast-ms'), 10) || 6000;
        var timer = null;
        var started = 0;
        var left = ms;
        function hide() {
            window.clearTimeout(timer);
            toast.classList.add('is-leaving');
            window.setTimeout(function () { toast.hidden = true; if (toast.getAttribute('data-toast-js') !== null) { toast.remove(); } }, reduce ? 0 : 250);
            if (current === toast) { current = null; }
        }
        function pause() { if (timer !== null) { window.clearTimeout(timer); timer = null; left -= Date.now() - started; } }
        function resume() { if (timer === null && !toast.hidden) { started = Date.now(); timer = window.setTimeout(hide, Math.max(left, 1000)); } }

        current = toast;
        toast.classList.add('is-js');
        toast.querySelector('[data-toast-close]').addEventListener('click', hide);
        toast.hideToast = hide;
        if (reduce || !bar) {
            toast.addEventListener('mouseenter', pause);
            toast.addEventListener('mouseleave', resume);
            toast.addEventListener('focusin', pause);
            toast.addEventListener('focusout', resume);
            resume();
        } else {
            bar.addEventListener('animationend', hide);   // pausing is pure CSS (:hover / :focus-within)
        }
    }

    /* Shows a toast built in the browser (type: 'success' | 'error' | 'info'); replaces the one on screen. */
    window.publicToast = function (type, text) {
        if (!text) { return; }
        type = ICONS[type] ? type : 'info';
        if (current && current.hideToast) { current.hideToast(); }
        var ms = type === 'error' ? 8000 : 6000;
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        toast.setAttribute('data-toast', '');
        toast.setAttribute('data-toast-js', '');
        toast.setAttribute('data-toast-ms', String(ms));
        toast.innerHTML = '<span class="toast__icon" aria-hidden="true">' + svg(ICONS[type]) + '</span><p class="toast__text"></p>'
            + '<button class="toast__close" type="button" data-toast-close aria-label="Close message">' + svg('<path d="M6 6l12 12M18 6L6 18"/>') + '</button>'
            + '<span class="toast__progress toast__progress--' + ms + '" aria-hidden="true" data-toast-progress></span>';
        toast.querySelector('.toast__text').textContent = text;   // text only: never interpreted as markup
        document.body.appendChild(toast);
        wire(toast);
    };

    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && current && current.hideToast) { current.hideToast(); } });
    var fromServer = document.querySelector('[data-toast]');
    if (fromServer) { wire(fromServer); }
}());

/* Menu dropdowns (2026-10-26): the arrow button next to "Vehicles" opens and closes its list (aria-expanded); Escape and a
   click elsewhere close it. Hover and keyboard focus also open it on desktop (CSS). */
(function () {
    'use strict';

    var drops = Array.prototype.slice.call(document.querySelectorAll('[data-nav-dropdown]'));
    if (drops.length === 0) { return; }
    function set(drop, open) {
        drop.classList.toggle('is-open', open);
        if (open) { drop.classList.remove('is-dismissed'); }
        drop.querySelector('[data-nav-sub-toggle]').setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    drops.forEach(function (drop) {
        var toggle = drop.querySelector('[data-nav-sub-toggle]');
        drop.addEventListener('mouseleave', function () { drop.classList.remove('is-dismissed'); });
        drop.addEventListener('focusin', function (event) { if (!drop.contains(event.relatedTarget)) { drop.classList.remove('is-dismissed'); } });   // tabbing in from outside opens it again
        drop.addEventListener('focusout', function (event) { if (!drop.contains(event.relatedTarget)) { drop.classList.remove('is-dismissed'); set(drop, false); } });
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var open = !drop.classList.contains('is-open');
            drops.forEach(function (d) { set(d, false); });
            set(drop, open);
        });
    });
    document.addEventListener('click', function (event) {
        drops.forEach(function (d) { if (!d.contains(event.target)) { set(d, false); } });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        drops.forEach(function (d) {
            if (d.classList.contains('is-open') || d.contains(document.activeElement)) {
                set(d, false);
                d.classList.add('is-dismissed');   // stays closed although focus is still inside (on the arrow button)
                if (d.contains(document.activeElement)) { d.querySelector('[data-nav-sub-toggle]').focus(); }
            }
        });
    });
}());
