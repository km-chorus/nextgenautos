/* Vehicle cards (Vehicles page): Previous / Next buttons on the photograph. The extra photographs are only downloaded the first
   time a card is used (no requests on page load). Without this file the card shows its first photograph and the buttons stay hidden. */
(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('[data-gallery]'), function (figure) {
        var slides = Array.prototype.slice.call(figure.querySelectorAll('.vehicle-card__media img'));
        var prev = figure.querySelector('[data-gallery-prev]');
        var next = figure.querySelector('[data-gallery-next]');
        var count = figure.querySelector('[data-gallery-count]');
        if (slides.length < 2 || !prev || !next) { return; }
        var index = 0;

        prev.hidden = false;
        next.hidden = false;
        if (count) { count.hidden = false; }

        function show(to) {
            index = (to + slides.length) % slides.length;
            slides.forEach(function (img, i) {
                if (i === index && !img.getAttribute('src')) { img.setAttribute('src', img.getAttribute('data-src')); }
                img.hidden = i !== index;
            });
            if (count) { count.textContent = (index + 1) + ' / ' + slides.length; }
        }

        prev.addEventListener('click', function () { show(index - 1); });
        next.addEventListener('click', function () { show(index + 1); });
    });
}());
