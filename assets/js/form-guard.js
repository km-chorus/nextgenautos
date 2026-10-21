/* Public forms (vehicle page, Contact page): one submission per click. The button shows that the request is being sent,
   and is usable again when the browser restores the page with Back. Without JavaScript the forms post normally. */
(function () {
    'use strict';

    document.querySelectorAll('[data-guard-form]').forEach(function (form) {
        var button = form.querySelector('button[type="submit"]');
        if (!button) { return; }
        var label = button.textContent;
        form.addEventListener('submit', function () {
            window.setTimeout(function () {
                button.disabled = true;
                button.textContent = 'Sending...';
            }, 0);
        });
        window.addEventListener('pageshow', function () {
            button.disabled = false;
            button.textContent = label;
        });
    });
}());
