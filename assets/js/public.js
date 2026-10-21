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
