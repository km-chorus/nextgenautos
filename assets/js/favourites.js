/* Save-to-favourites buttons (spec 22): switch in place without reloading. Without JavaScript the form posts and the
   page comes back. A guest (no token in the form) is sent to sign in by the server, so the form is left to submit. */
(function () {
    'use strict';

    if (!window.fetch || !window.FormData) { return; }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches || !form.matches('[data-fav]') || !form.querySelector('input[name="csrf_token"]')) { return; }
        event.preventDefault();
        var button = form.querySelector('[data-fav-button]');
        if (button.disabled) { return; }
        button.disabled = true;

        // getAttribute: the form has a field named "action", which hides the form's own .action property (fixed 2026-10-23)
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json().then(function (data) { return { status: response.status, data: data }; }); })
            .then(function (res) {
                if (res.status === 401 && res.data.login) { window.location.href = res.data.login; return; }
                if (res.status !== 200) { throw new Error('not saved'); }
                var saved = res.data.favourite === true;
                // The same vehicle can appear twice on a page (for example a card and its detail): update every button for it.
                var slug = form.querySelector('input[name="vehicle"]').value;
                document.querySelectorAll('[data-fav]').forEach(function (f) {
                    if (f.querySelector('input[name="vehicle"]').value !== slug) { return; }
                    var b = f.querySelector('[data-fav-button]');
                    b.setAttribute('aria-pressed', saved ? 'true' : 'false');
                    b.setAttribute('aria-label', b.getAttribute(saved ? 'data-label-remove' : 'data-label-add'));
                    f.querySelector('[data-fav-action]').value = saved ? 'remove' : 'add';
                    var t = f.querySelector('[data-fav-text]');
                    if (t) { t.textContent = saved ? 'Saved' : 'Save'; }
                });
                if (window.publicToast) { window.publicToast('success', saved ? 'Saved to your favourites.' : 'Removed from your favourites.'); }
            })
            .catch(function () {
                if (window.publicToast) { window.publicToast('error', 'Your favourites could not be updated. Please try again.'); } else { form.submit(); }
            })
            .then(function () { button.disabled = false; });
    });
}());
