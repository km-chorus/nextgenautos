/*
 * Part form (spec 16, phase 1): "Add another vehicle" and "Remove" for the compatible-vehicle rows.
 * Without JavaScript the form already offers blank rows, so nothing depends on this script.
 * New rows get the next free index; the server re-numbers the rows it receives, so gaps are harmless.
 */
(function () {
    "use strict";

    var list = document.querySelector("[data-fitment-list]");
    var template = document.querySelector("[data-fitment-template]");
    var add = document.querySelector("[data-fitment-add]");
    if (!list || !template || !add || !("content" in template)) { return; }

    var max = parseInt(list.getAttribute("data-fitment-max"), 10) || 20;
    var next = list.querySelectorAll("[data-fitment-row]").length;

    function rows() { return list.querySelectorAll("[data-fitment-row]"); }

    function sync() {
        var all = rows();
        add.hidden = all.length >= max;
        for (var i = 0; i < all.length; i++) {
            all[i].querySelector("[data-fitment-remove]").hidden = all.length < 2;
        }
    }

    add.addEventListener("click", function () {
        if (rows().length >= max) { return; }
        var html = template.innerHTML.replace(/__N__/g, String(next++));
        var holder = document.createElement("ol");
        holder.innerHTML = html;
        var row = holder.firstElementChild;
        list.appendChild(row);
        sync();
        row.querySelector("select").focus();
    });

    list.addEventListener("click", function (event) {
        var button = event.target.closest && event.target.closest("[data-fitment-remove]");
        if (!button) { return; }
        var row = button.closest("[data-fitment-row]");
        var after = row.nextElementSibling || row.previousElementSibling;
        row.parentNode.removeChild(row);
        sync();
        var focus = after && after.querySelector("select");
        (focus || add).focus();
    });

    sync();
}());

/*
 * Part numbers (spec 27): "Add another number", and Up / Down / Remove per row. The first row is the main number (shown
 * on the website) and is labelled so. Without JavaScript the form offers blank rows and the order typed is kept.
 */
(function () {
    "use strict";

    var list = document.querySelector("[data-number-list]");
    var template = document.querySelector("[data-number-template]");
    var add = document.querySelector("[data-number-add]");
    if (!list || !template || !add || !("content" in template)) { return; }

    var max = parseInt(list.getAttribute("data-number-max"), 10) || 10;
    var next = list.querySelectorAll("[data-number-row]").length;

    function rows() { return list.querySelectorAll("[data-number-row]"); }

    function sync() {
        var all = rows();
        add.hidden = all.length >= max;
        for (var i = 0; i < all.length; i++) {
            var row = all[i];
            row.querySelector("[data-number-tools]").hidden = false;
            row.querySelector("[data-number-up]").disabled = i === 0;
            row.querySelector("[data-number-down]").disabled = i === all.length - 1;
            row.querySelector("[data-number-remove]").hidden = all.length < 2;
            var label = row.querySelector("[data-number-label]");
            label.textContent = i === 0 ? "Main part number" : "Other part number " + i;
        }
    }

    add.addEventListener("click", function () {
        if (rows().length >= max) { return; }
        var holder = document.createElement("ol");
        holder.innerHTML = template.innerHTML.replace(/__N__/g, "new" + String(next++));
        var row = holder.firstElementChild;
        list.appendChild(row);
        sync();
        row.querySelector("input").focus();
    });

    list.addEventListener("click", function (event) {
        var button = event.target.closest && event.target.closest("[data-number-up], [data-number-down], [data-number-remove]");
        if (!button) { return; }
        var row = button.closest("[data-number-row]");
        if (button.hasAttribute("data-number-remove")) {
            var after = row.nextElementSibling || row.previousElementSibling;
            row.parentNode.removeChild(row);
            sync();
            var focus = after && after.querySelector("input");
            (focus || add).focus();
            return;
        }
        if (button.hasAttribute("data-number-up") && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (button.hasAttribute("data-number-down") && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        sync();
        // Keep focus on the same button, or the other one when this one became disabled at the end of the list.
        var keep = button.disabled ? row.querySelector(button.hasAttribute("data-number-up") ? "[data-number-down]" : "[data-number-up]") : button;
        keep.focus();
    });

    sync();
}());
