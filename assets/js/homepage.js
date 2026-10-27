/* Homepage hero background (spec 10A; controls removed at the owner's request, 2026-10-22). Vanilla JS, no dependencies.
   Crossfade between 2 to 4 photos, every 6 s. There are no buttons: the photos are a background, the headline and search
   never change. Rotation pauses while the pointer is over the hero, while focus is inside it (for example in the search
   form), and while the tab is hidden. With reduced motion it never rotates. Without JavaScript the first photo stays. */
(function () {
    'use strict';

    var root = document.querySelector('[data-hero]');
    if (!root) { return; }
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-hero-slide]'));
    if (slides.length < 2) { return; }

    var INTERVAL = 6000;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

    var current = 0;
    var timer = null;
    var hovering = false;
    var focusInside = false;

    // Photos after the first one are loaded progressively (next photo first), not all at once.
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

    // A photo that fails to load is dropped from the rotation; with fewer than two working photos the rotation stops.
    var failed = [];
    function usable() { return slides.length - failed.filter(Boolean).length; }
    function step(from) {
        var i = from;
        for (var n = 0; n < slides.length; n++) {
            i = (i + 1) % slides.length;
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
        if (usable() < 2) { stop(); }
        if (i === current) { show(step(i)); }
    }, true);

    function show(index) {
        current = (index + slides.length) % slides.length;
        for (var i = 0; i < slides.length; i++) {
            var active = i === current;
            slides[i].classList.toggle('is-active', active);
            if (active) { slides[i].removeAttribute('aria-hidden'); } else { slides[i].setAttribute('aria-hidden', 'true'); }
        }
        load(current);
        load((current + 1) % slides.length);
    }

    function stop() { if (timer !== null) { clearTimeout(timer); timer = null; } }
    function running() { return usable() > 1 && !reduce.matches && !hovering && !focusInside && !document.hidden; }
    function schedule() {
        stop();
        if (!running()) { return; }
        timer = setTimeout(function () { show(step(current)); schedule(); }, INTERVAL);
    }

    show(0);
    root.addEventListener('mouseenter', function () { hovering = true; stop(); });
    root.addEventListener('mouseleave', function () { hovering = false; schedule(); });
    root.addEventListener('focusin', function () { focusInside = true; stop(); });
    root.addEventListener('focusout', function (event) {
        if (!root.contains(event.relatedTarget)) { focusInside = false; schedule(); }
    });
    document.addEventListener('visibilitychange', schedule);
    if (reduce.addEventListener) { reduce.addEventListener('change', schedule); } else if (reduce.addListener) { reduce.addListener(schedule); }

    if (document.readyState === 'complete') { loadAll(); } else { window.addEventListener('load', loadAll); }
    schedule();
}());
