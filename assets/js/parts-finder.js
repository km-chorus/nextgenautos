/* "Find parts for your vehicle" (spec 27): when a make is chosen, the model list is filled with that make's models (from
   the form's data-models, which the server built from the compatible vehicles entered in the admin). Empty fields are
   left out of the address on submit. Without JavaScript the form still works: choose a make, press Show parts, and the
   page comes back with that make's models. The server validates every value again. Vanilla JS, no dependencies. */
(function () {
    'use strict';

    var form = document.querySelector('[data-parts-finder]');
    if (!form) { return; }
    var make = form.querySelector('[data-finder-make]');
    var model = form.querySelector('[data-finder-model]');
    var models = {};
    try { models = JSON.parse(form.getAttribute('data-models') || '{}') || {}; } catch (e) { models = {}; }

    function option(value, text) {
        var o = document.createElement('option');
        o.value = value;
        o.textContent = text;   // text only: model names are never parsed as HTML
        return o;
    }

    // Search only (no compatible vehicles entered yet): no make / model fields.
    if (make && model) make.addEventListener('change', function () {
        var list = Object.prototype.hasOwnProperty.call(models, make.value) ? models[make.value] : [];
        while (model.firstChild) { model.removeChild(model.firstChild); }
        model.appendChild(option('', make.value === '' ? 'Choose a make first' : 'All models'));
        for (var i = 0; i < list.length; i++) { model.appendChild(option(list[i], list[i])); }
        model.disabled = make.value === '';
    });

    form.addEventListener('submit', function () {
        Array.prototype.forEach.call(form.elements, function (el) {
            if (el.name && (el.tagName === 'SELECT' || el.tagName === 'INPUT') && el.value.trim() === '') { el.disabled = true; }
        });
    });
    // Back/forward cache: re-enable what the submit handler disabled.
    window.addEventListener('pageshow', function () {
        Array.prototype.forEach.call(form.elements, function (el) {
            if (el !== model || (make && make.value !== '')) { el.disabled = false; }
        });
    });
}());
