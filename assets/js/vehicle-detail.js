/* Vehicle detail page gallery (spec 14, redesigned 2026-10-21): Exterior / Interior tabs, thumbnails, previous / next,
   arrow keys and swipe, always within the current tab. Progressive enhancement only: without JavaScript the first photo
   is shown and every thumbnail opens its photo. The forms use form-guard.js. No inline script (the CSP forbids it). */
(function () {
    'use strict';

    var gallery = document.querySelector('[data-vd-gallery]');
    if (!gallery) { return; }
    var slides = Array.prototype.slice.call(gallery.querySelectorAll('[data-vd-slide]'));
    if (slides.length === 0) { return; }

    var tabsBox = gallery.querySelector('[data-vd-tabs]');
    var tabs = tabsBox ? Array.prototype.slice.call(tabsBox.querySelectorAll('[data-vd-tab]')) : [];
    var countText = gallery.querySelector('[data-vd-count-text]');
    var labels = { exterior: 'Exterior', interior: 'Interior', damage: 'Damage' };
    var type = gallery.getAttribute('data-vd-first') || 'exterior';
    var pos = 0;   // position within the current type

    function ofType() { return slides.filter(function (s) { return s.getAttribute('data-vd-type') === type; }); }
    function panel(t) { return gallery.querySelector('[data-vd-panel="' + t + '"]'); }

    function show(newPos) {
        var list = ofType();
        if (list.length === 0) { return; }
        pos = (newPos + list.length) % list.length;
        var active = list[pos];
        var index = slides.indexOf(active);
        slides.forEach(function (s) { s.classList.toggle('is-active', s === active); });
        var img = active.querySelector('img');
        if (img && img.loading === 'lazy') { img.loading = 'eager'; }
        gallery.querySelectorAll('[data-vd-thumb]').forEach(function (thumb) {
            if (parseInt(thumb.getAttribute('data-vd-thumb'), 10) === index) {
                thumb.setAttribute('aria-current', 'true');
                if (thumb.scrollIntoView) { thumb.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
            } else {
                thumb.removeAttribute('aria-current');
            }
        });
        if (countText) { countText.textContent = labels[type] + ' · ' + (pos + 1) + '/' + list.length; }
    }

    function selectType(t, focus) {
        type = t;
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-vd-tab') === t;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            var p = panel(tab.getAttribute('data-vd-tab'));
            if (p) { p.hidden = !on; }
            if (on && focus) { tab.focus(); }
        });
        show(0);
    }

    // Tabs (WAI-ARIA pattern: arrow keys move between the two tabs).
    if (tabsBox && tabs.length > 1) {
        tabsBox.setAttribute('role', 'tablist');
        tabsBox.setAttribute('aria-label', 'Photo type');
        tabsBox.hidden = false;
        tabs.forEach(function (tab, i) {
            var p = panel(tab.getAttribute('data-vd-tab'));
            tab.setAttribute('role', 'tab');
            if (p) {
                tab.setAttribute('aria-controls', p.id);
                p.setAttribute('role', 'tabpanel');
                p.setAttribute('aria-labelledby', tab.id);
                var title = p.querySelector('[data-vd-panel-title]');
                if (title) { title.classList.add('sr-only'); }
            }
            tab.addEventListener('click', function () { selectType(tab.getAttribute('data-vd-tab'), false); });
            tab.addEventListener('keydown', function (event) {
                var next = null;
                if (event.key === 'ArrowRight') { next = tabs[(i + 1) % tabs.length]; }
                if (event.key === 'ArrowLeft') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
                if (next) { event.preventDefault(); selectType(next.getAttribute('data-vd-tab'), true); }
            });
        });
    }

    // Thumbnails switch the photo in place instead of opening it.
    gallery.querySelectorAll('[data-vd-thumb]').forEach(function (thumb) {
        thumb.addEventListener('click', function (event) {
            event.preventDefault();
            var index = parseInt(thumb.getAttribute('data-vd-thumb'), 10) || 0;
            show(ofType().indexOf(slides[index]));
        });
    });

    var prev = gallery.querySelector('[data-vd-prev]');
    var next = gallery.querySelector('[data-vd-next]');
    if (prev && next) {
        prev.hidden = false;
        next.hidden = false;
        prev.addEventListener('click', function () { show(pos - 1); });
        next.addEventListener('click', function () { show(pos + 1); });
    }
    gallery.addEventListener('keydown', function (event) {
        if (event.target.closest && event.target.closest('[data-vd-tab]')) { return; }   // arrows on a tab move between tabs
        if (event.key === 'ArrowLeft') { show(pos - 1); }
        if (event.key === 'ArrowRight') { show(pos + 1); }
    });

    // Swipe on touch screens
    var startX = null;
    gallery.addEventListener('touchstart', function (event) { startX = event.touches[0].clientX; }, { passive: true });
    gallery.addEventListener('touchend', function (event) {
        if (startX === null) { return; }
        var dx = event.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) { show(pos + (dx < 0 ? 1 : -1)); }
        startX = null;
    });

    if (tabs.length > 1) { selectType(type, false); } else { show(0); }
}());
