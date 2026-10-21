/* Homepage hero carousel (spec 10A). Vanilla JS, no dependencies.
   Crossfade between 2 to 4 slides, rotating every 6 s. Pauses on hover, while focus is in the
   controls, while the tab is hidden, and when the visitor presses Pause. With reduced motion it
   never rotates by itself (the slides can still be changed with the controls).
   Without JavaScript the first slide simply stays visible. */
(function () {
    'use strict';

    var root = document.querySelector('[data-hero]');
    if (!root) { return; }
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-hero-slide]'));
    // Two control groups: the side arrows and the pause button with the dots. Both stay hidden without JavaScript.
    var groups = Array.prototype.slice.call(root.querySelectorAll('[data-hero-controls], [data-hero-controls-extra]'));
    if (slides.length < 2 || groups.length < 2) { return; }
    function showControls(visible) { groups.forEach(function (g) { g.hidden = !visible; }); }

    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-hero-dot]'));
    var toggle = root.querySelector('[data-hero-toggle]');
    var iconPause = root.querySelector('[data-hero-icon-pause]');
    var iconPlay = root.querySelector('[data-hero-icon-play]');
    var region = root.querySelector('[data-hero-slides]');
    var INTERVAL = 6000;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

    var current = 0;
    var timer = null;
    var userPaused = reduce.matches;   // reduced motion: no automatic rotation
    var hovering = false;
    var focusInside = false;

    // Images after the first one are loaded progressively (next slide first), not all at once.
    var loaded = [true];
    function load(index) {
        if (loaded[index]) { return; }
        loaded[index] = true;
        var slide = slides[index];
        var sources = slide.querySelectorAll('source[data-srcset]');
        for (var i = 0; i < sources.length; i++) {
            sources[i].setAttribute('srcset', sources[i].getAttribute('data-srcset'));
            sources[i].removeAttribute('data-srcset');
        }
        var img = slide.querySelector('img[data-src]');
        if (img) { img.setAttribute('src', img.getAttribute('data-src')); img.removeAttribute('data-src'); }
    }
    function loadAll() {
        var index = 1;
        (function next() {
            if (index >= slides.length) { return; }
            load(index++);
            setTimeout(next, 1500);
        }());
    }

    // An image that fails to load is dropped from the rotation (the dark hero panel, headline and
    // buttons stay); with fewer than two working slides the carousel stops and its controls hide.
    var failed = [];
    function usable() { return slides.length - failed.filter(Boolean).length; }
    function step(from, dir) {
        var i = from;
        for (var n = 0; n < slides.length; n++) {
            i = (i + dir + slides.length) % slides.length;
            if (!failed[i]) { return i; }
        }
        return from;
    }
    root.addEventListener('error', function (event) {
        var img = event.target;
        if (!img || img.tagName !== 'IMG' || /^data:/.test(img.getAttribute('src') || '')) { return; }
        var i = slides.indexOf(img.closest('[data-hero-slide]'));
        if (i < 0 || failed[i]) { return; }
        failed[i] = true;
        if (dots[i]) { dots[i].hidden = true; }
        if (usable() < 2) { stop(); showControls(false); }
        if (i === current) { show(step(i, 1)); }
    }, true);

    function show(index) {
        current = (index + slides.length) % slides.length;
        for (var i = 0; i < slides.length; i++) {
            var active = i === current;
            slides[i].classList.toggle('is-active', active);
            if (active) { slides[i].removeAttribute('aria-hidden'); } else { slides[i].setAttribute('aria-hidden', 'true'); }
            if (dots[i]) {
                if (active) { dots[i].setAttribute('aria-current', 'true'); } else { dots[i].removeAttribute('aria-current'); }
            }
        }
        load(current);
        load((current + 1) % slides.length);
    }

    function stop() { if (timer !== null) { clearTimeout(timer); timer = null; } }
    function running() { return usable() > 1 && !userPaused && !hovering && !focusInside && !document.hidden; }
    function schedule() {
        stop();
        if (!running()) { return; }
        timer = setTimeout(function () { show(step(current, 1)); schedule(); }, INTERVAL);
    }

    function syncToggle() {
        toggle.setAttribute('aria-label', userPaused ? 'Start slide rotation' : 'Pause slide rotation');
        iconPause.hidden = userPaused;
        iconPlay.hidden = !userPaused;
        // Announce slide changes politely only when the visitor is in control (not while auto-rotating).
        region.setAttribute('aria-live', userPaused ? 'polite' : 'off');
    }

    showControls(true);
    show(0);
    syncToggle();

    root.querySelector('[data-hero-prev]').addEventListener('click', function () { show(step(current, -1)); schedule(); });
    root.querySelector('[data-hero-next]').addEventListener('click', function () { show(step(current, 1)); schedule(); });
    toggle.addEventListener('click', function () { userPaused = !userPaused; syncToggle(); schedule(); });
    dots.forEach(function (dot, i) { dot.addEventListener('click', function () { if (!failed[i]) { show(i); schedule(); } }); });

    root.addEventListener('mouseenter', function () { hovering = true; stop(); });
    root.addEventListener('mouseleave', function () { hovering = false; schedule(); });
    groups.forEach(function (group) {
        group.addEventListener('focusin', function () { focusInside = true; stop(); });
        group.addEventListener('focusout', function (event) {
            var inside = groups.some(function (g) { return g.contains(event.relatedTarget); });
            if (!inside) { focusInside = false; schedule(); }
        });
    });
    document.addEventListener('visibilitychange', schedule);
    var onReduce = function () { userPaused = reduce.matches; syncToggle(); schedule(); };
    if (reduce.addEventListener) { reduce.addEventListener('change', onReduce); } else if (reduce.addListener) { reduce.addListener(onReduce); }

    if (document.readyState === 'complete') { loadAll(); } else { window.addEventListener('load', loadAll); }
    schedule();
}());
