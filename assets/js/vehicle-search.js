/* Vehicle search form (homepage hero and Vehicles page). Vanilla JS, no dependencies, no requests.
   The form works without JavaScript: every model is listed, grouped by make. This only narrows the
   Model list to the chosen Make (the groups are already in the page) and leaves unused filters out
   of the address. */
(function () {
    'use strict';

    var forms = document.querySelectorAll('[data-vsearch]');
    Array.prototype.forEach.call(forms, function (form) {
        var brand = form.querySelector('[data-vsearch-brand]');
        var model = form.querySelector('[data-vsearch-model]');
        if (!brand || !model) { return; }
        var groups = Array.prototype.slice.call(model.querySelectorAll('optgroup'));

        function sync(keepSelection) {
            var slug = brand.value;
            groups.forEach(function (group) {
                var show = slug === '' || group.getAttribute('data-brand') === slug;
                group.hidden = !show;
                group.disabled = !show;
            });
            var current = model.options[model.selectedIndex];
            if (!keepSelection || (current && current.parentNode.tagName === 'OPTGROUP' && current.parentNode.disabled)) {
                model.value = '';
            }
        }

        brand.addEventListener('change', function () { sync(false); });
        sync(true);

        // Keep the address tidy: filters left on "Any" are not sent (without JavaScript they are sent empty, which is harmless).
        form.addEventListener('submit', function () {
            var empty = Array.prototype.filter.call(form.querySelectorAll('select, input[type="search"], input[type="text"]'), function (el) { return el.value === '' && !el.disabled; });
            empty.forEach(function (el) { el.disabled = true; });
            setTimeout(function () { empty.forEach(function (el) { el.disabled = false; }); }, 0);
        });
    });
}());
