/* Latest Parts & Accessories (spec 16 phase 1): "Browse by type" / "Browse by brand" as two tabs, and instant filtering
   of the part cards already on the page. Without JavaScript both groups are shown as plain lists of links that the server
   answers with the matching parts, so nothing here is required. Vanilla JS, no dependencies. */
(function () {
    'use strict';

    var root = document.querySelector('[data-parts]');
    var tablist = root && root.querySelector('[data-parts-tablist]');
    var grid = root && root.querySelector('[data-parts-grid]');
    if (!root || !tablist || !grid) { return; }

    var shown = parseInt(root.getAttribute('data-parts-shown'), 10) || 8;
    var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[data-parts-tab]'));
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.part-card'));
    var status = root.querySelector('[data-parts-status]');
    var none = root.querySelector('[data-parts-none]');
    // Opened from a filter link (?parts_type= / ?parts_make=): the page holds only those parts, so every choice loads its own page.
    var serverFiltered = root.hasAttribute('data-parts-active');

    function panel(name) { return root.querySelector('[data-parts-panel="' + name + '"]'); }

    // Tabs (WAI-ARIA tabs pattern: arrow keys move between tabs, the panel follows).
    tablist.setAttribute('role', 'tablist');
    tablist.setAttribute('aria-label', 'Browse parts');
    tablist.hidden = false;
    tabs.forEach(function (tab) {
        var name = tab.getAttribute('data-parts-tab');
        var p = panel(name);
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', p.id);
        p.setAttribute('role', 'tabpanel');
        p.setAttribute('aria-labelledby', tab.id);
        var title = p.querySelector('[data-parts-panel-title]');
        if (title) { title.classList.add('sr-only'); }
    });

    function select(name, focus) {
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-parts-tab') === name;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            panel(tab.getAttribute('data-parts-tab')).hidden = !on;
            if (on && focus) { tab.focus(); }
        });
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () {
            if (tab.getAttribute('aria-selected') === 'true') { return; }
            select(tab.getAttribute('data-parts-tab'), false);
            var all = panel(tab.getAttribute('data-parts-tab')).querySelector('[data-parts-value=""]');
            if (all && !serverFiltered) { apply(all, false); }   // a new tab starts from "All"
        });
        tab.addEventListener('keydown', function (event) {
            var next = null;
            if (event.key === 'ArrowRight') { next = tabs[(i + 1) % tabs.length]; }
            if (event.key === 'ArrowLeft') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
            if (event.key === 'Home') { next = tabs[0]; }
            if (event.key === 'End') { next = tabs[tabs.length - 1]; }
            if (next) { event.preventDefault(); next.click(); next.focus(); }
        });
    });

    function matches(card, kind, value) {
        if (value === '') { return true; }
        if (kind === 'type') { return card.getAttribute('data-type') === value; }
        return (' ' + card.getAttribute('data-makes') + ' ').indexOf(' ' + value + ' ') !== -1;
    }

    // Shows the first `shown` cards that match. Returns false when none of the cards on the page match, so the caller can
    // let the link load the server's answer (the page holds only the newest parts).
    function apply(chip, announce) {
        var kind = chip.getAttribute('data-parts-filter');
        var value = chip.getAttribute('data-parts-value');
        var count = 0;
        cards.forEach(function (card) {
            var ok = matches(card, kind, value) && count < shown;
            if (ok) { count++; }
            card.hidden = !ok;
        });
        if (count === 0 && value !== '') { return false; }
        root.querySelectorAll('[data-parts-filter]').forEach(function (c) { c.removeAttribute('aria-current'); });
        chip.setAttribute('aria-current', 'true');
        if (none) { none.hidden = count > 0; }
        if (announce && status) {
            status.textContent = value === '' ? 'Showing the latest parts.' : 'Showing ' + count + (count === 1 ? ' part' : ' parts') + ' for ' + chip.textContent + '.';
        }
        return true;
    }

    root.addEventListener('click', function (event) {
        var chip = event.target.closest && event.target.closest('[data-parts-filter]');
        if (!chip || serverFiltered) { return; }
        if (apply(chip, true)) {
            event.preventDefault();
            if (window.history && window.history.replaceState) { window.history.replaceState(null, '', chip.getAttribute('href')); }
        }
    });

    select(root.getAttribute('data-parts-active') || 'type', false);
}());
