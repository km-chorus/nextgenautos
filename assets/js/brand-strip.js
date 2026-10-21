/* Browse by Brand (spec 10C): arrow buttons for the horizontal strip. Vanilla JS, no dependencies.
   The strip scrolls natively without this (swipe, trackpad, keyboard); this only adds the arrows and
   disables each at the end it has reached. */
(function () {
    'use strict';

    var root = document.querySelector('[data-brand-strip]');
    if (!root) { return; }
    var viewport = root.querySelector('[data-brand-viewport]');
    var prev = root.querySelector('[data-brand-prev]');
    var next = root.querySelector('[data-brand-next]');
    if (!viewport || !prev || !next) { return; }

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var ticking = false;

    function update() {
        ticking = false;
        var max = viewport.scrollWidth - viewport.clientWidth;
        var scrollable = max > 1;
        prev.hidden = next.hidden = !scrollable;
        prev.disabled = viewport.scrollLeft <= 1;
        next.disabled = viewport.scrollLeft >= max - 1;
    }
    function queue() { if (!ticking) { ticking = true; window.requestAnimationFrame(update); } }

    // Scroll by most of a screenful of brands (never less than two brand widths' worth feels too short)
    function scrollBy(direction) {
        var step = Math.max(viewport.clientWidth * 0.8, 240);
        viewport.scrollBy({ left: direction * step, behavior: reduce.matches ? 'auto' : 'smooth' });
    }

    prev.addEventListener('click', function () { scrollBy(-1); });
    next.addEventListener('click', function () { scrollBy(1); });
    viewport.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    if (window.ResizeObserver) { new ResizeObserver(queue).observe(viewport); }
    update();
}());
