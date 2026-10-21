/* Vehicles page (spec 11): the filter panel becomes a drawer on small screens, and the sort control applies as soon as it
   changes. Vanilla JS, no dependencies, no requests. Without this file the page still works: the filters sit above the results
   and the Sort button submits the form. */
(function () {
    'use strict';

    var root = document.querySelector('[data-inventory]');
    if (!root) { return; }
    var panel = root.querySelector('[data-inv-panel]');
    var openBtn = root.querySelector('[data-inv-open]');
    var closeBtn = root.querySelector('[data-inv-close]');
    var backdrop = root.querySelector('[data-inv-backdrop]');
    var sort = root.querySelector('[data-inv-sort]');
    var wide = window.matchMedia('(min-width: 64em)');
    var lastFocus = null;

    document.documentElement.classList.add('is-js');

    if (sort) {
        sort.querySelector('select').addEventListener('change', function () { sort.submit(); });
    }
    if (!panel || !openBtn) { return; }

    openBtn.hidden = wide.matches;

    function focusables() {
        return Array.prototype.filter.call(panel.querySelectorAll('a[href], button, input, select'), function (el) { return !el.disabled && el.offsetParent !== null; });
    }
    function open() {
        lastFocus = document.activeElement;
        panel.classList.add('is-open');
        backdrop.hidden = false;
        document.body.classList.add('inv-open');
        openBtn.setAttribute('aria-expanded', 'true');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        var first = focusables()[0];
        if (first) { first.focus(); }
    }
    function close() {
        panel.classList.remove('is-open');
        backdrop.hidden = true;
        document.body.classList.remove('inv-open');
        openBtn.setAttribute('aria-expanded', 'false');
        panel.removeAttribute('role');
        panel.removeAttribute('aria-modal');
        if (lastFocus && typeof lastFocus.focus === 'function' && !wide.matches) { lastFocus.focus(); }
    }

    openBtn.addEventListener('click', open);
    if (closeBtn) { closeBtn.addEventListener('click', close); }
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (!panel.classList.contains('is-open')) { return; }
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'Tab') {
            var items = focusables();
            if (!items.length) { return; }
            var first = items[0];
            var last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
    var onResize = function () {
        openBtn.hidden = wide.matches;
        if (wide.matches && panel.classList.contains('is-open')) { close(); }
    };
    if (wide.addEventListener) { wide.addEventListener('change', onResize); } else { wide.addListener(onResize); }
}());
