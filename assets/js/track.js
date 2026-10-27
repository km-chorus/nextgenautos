/* Track Vehicle (spec 20): a "Copy" button beside each container number. Without JavaScript (or without clipboard
   access) the button stays hidden and the number can still be selected and copied by hand. */
(function () {
    'use strict';

    if (!navigator.clipboard || !window.isSecureContext) { return; }
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        var text = button.parentNode.querySelector('[data-copy-text]');
        if (!text) { return; }
        button.hidden = false;
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(text.textContent.trim()).then(function () {
                button.firstChild.textContent = 'Copied';
                setTimeout(function () { button.firstChild.textContent = 'Copy'; }, 2000);
            });
        });
    });
}());
