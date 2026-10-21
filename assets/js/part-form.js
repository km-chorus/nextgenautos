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
