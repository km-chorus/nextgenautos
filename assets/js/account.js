/* Customer sign-in and registration (spec 13): show/hide password, a live length hint and a busy state on submit.
   Progressive enhancement only: the forms work without this file, and the server validates everything again. */
(function () {
    'use strict';

    var toggles = document.querySelectorAll('[data-password-toggle]');
    for (var i = 0; i < toggles.length; i++) {
        (function (button) {
            var input = document.getElementById(button.getAttribute('aria-controls'));
            if (!input) { return; }
            button.hidden = false;   // without JavaScript there is no toggle to confuse anyone
            button.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.textContent = show ? 'Hide' : 'Show';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        })(toggles[i]);
    }

    var hint = document.querySelector('[data-password-hint]');
    var newPassword = hint ? document.getElementById('password') : null;
    if (hint && newPassword) {
        var original = hint.textContent;
        hint.setAttribute('aria-live', 'polite');
        newPassword.addEventListener('input', function () {
            var length = newPassword.value.length;
            hint.textContent = length === 0 ? original
                : length < 12 ? (12 - length) + ' more character' + (12 - length === 1 ? '' : 's') + ' needed.'
                : 'Long enough.';
        });
    }

    var forms = document.querySelectorAll('[data-auth-form]');
    for (var f = 0; f < forms.length; f++) {
        (function (form) {
            var submit = form.querySelector('[data-auth-submit]');
            if (!submit) { return; }
            form.addEventListener('submit', function (event) {
                if (submit.classList.contains('is-busy')) { event.preventDefault(); return; }   // no double submit
                submit.classList.add('is-busy');
                submit.setAttribute('aria-disabled', 'true');
                submit.textContent = (submit.getAttribute('data-busy-label') || 'Please wait') + '...';
            });
        })(forms[f]);
    }

    // The browser's Back button restores the page from its cache with the busy state still set: undo it.
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) { return; }
        var busy = document.querySelectorAll('[data-auth-submit].is-busy');
        for (var b = 0; b < busy.length; b++) { window.location.reload(); break; }
    });
})();
