/*
 * Shipment form (spec 20): the customer is emailed automatically on every status change (server side, 2026-10-22);
 * this script shows the "move the status back" confirmation for an earlier status (D8) and points the preview link at the chosen
 * status. The server enforces the rules; without JavaScript the administrator ticks the box.
 */
(function () {
    "use strict";

    var form = document.querySelector("[data-shipment-form]");
    if (!form) { return; }
    var status = form.querySelector("[data-ship-status]");
    var back = form.querySelector("[data-ship-back]");
    var preview = form.querySelector("[data-ship-preview]");
    var saved = form.getAttribute("data-saved-status");
    var emailStatuses = form.getAttribute("data-email-statuses").split(" ");
    var order = form.getAttribute("data-status-order").split(" ");

    status.addEventListener("change", function () {
        var value = status.value;
        if (back) {
            var isBack = order.indexOf(value) < order.indexOf(saved);
            back.hidden = !isBack;
            if (!isBack) { back.querySelector("input").checked = false; }
        }
        if (preview && emailStatuses.indexOf(value) !== -1) {
            preview.href = preview.href.replace(/status=[A-Z_]+/, "status=" + value);
        }
    });
}());
