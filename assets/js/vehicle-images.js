/**
 * Vehicle images page: upload form enhancement (spec 07_spec_vehicle_image.md).
 *
 * Progressive enhancement only. Without JavaScript the form still posts the chosen files
 * natively. With it:
 *   - the selection is checked for obvious problems (type, size, count) so the administrator
 *     hears about them before waiting for an upload,
 *   - large photographs are shrunk in the browser with browser-image-compression (self-hosted,
 *     no web worker because the admin Content-Security-Policy does not allow blob workers),
 *   - a preview of every file that will be sent is shown.
 *
 * NONE of this is a security control. The server re-validates the real content, size and
 * dimensions of every file and re-encodes it. If compression fails or is unavailable, the
 * original file is sent unchanged.
 *
 * Every file name is inserted with textContent (never as HTML).
 */
(function () {
    "use strict";

    var ALLOWED = ["image/jpeg", "image/png", "image/webp"];
    var COMPRESS_ABOVE = 1.5 * 1024 * 1024;   // bytes: smaller files are sent as they are

    function formatSize(bytes) {
        if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1) + " MB"; }
        return Math.max(1, Math.round(bytes / 1024)) + " KB";
    }

    function readAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = function () { reject(reader.error); };
            reader.readAsDataURL(file);
        });
    }

    function init() {
        var form = document.querySelector("[data-image-upload]");
        if (!form) { return; }

        var input = form.querySelector('input[type="file"]');
        var list = form.querySelector("[data-upload-previews]");
        var status = form.querySelector("[data-upload-status]");
        var button = form.querySelector("[data-submit-button]");
        var maxFiles = parseInt(form.getAttribute("data-max-files"), 10) || 10;
        var maxBytes = parseInt(form.getAttribute("data-max-bytes"), 10) || 10485760;
        var maxTotal = parseInt(form.getAttribute("data-max-total"), 10) || 0;   // PHP post_max_size in bytes (0 = unknown / unlimited)
        var overTotal = false;
        var preparing = false;
        var run = 0;   // a newer selection cancels the display of an older one

        function setBusy(busy) {
            preparing = busy;
            if (!button) { return; }
            button.disabled = busy;
            if (busy) { button.setAttribute("aria-busy", "true"); } else { button.removeAttribute("aria-busy"); }
        }

        function addItem(file) {
            var item = document.createElement("li");
            item.className = "upload-preview";
            var thumb = document.createElement("span");
            thumb.className = "upload-preview__thumb";
            var text = document.createElement("span");
            text.className = "upload-preview__text";
            var name = document.createElement("span");
            name.className = "upload-preview__name";
            name.textContent = file.name;
            var note = document.createElement("span");
            note.className = "upload-preview__note";
            note.textContent = "Preparing";
            text.appendChild(name);
            text.appendChild(note);
            item.appendChild(thumb);
            item.appendChild(text);
            list.appendChild(item);
            return { item: item, thumb: thumb, note: note };
        }

        function fail(ui, message) {
            ui.item.classList.add("upload-preview--rejected");
            ui.note.textContent = "Will not be uploaded: " + message;
        }

        input.addEventListener("change", function () {
            var current = ++run;
            var files = Array.prototype.slice.call(input.files || []);
            list.textContent = "";
            status.textContent = "";
            overTotal = false;
            if (files.length === 0) { setBusy(false); return; }

            if (files.length > maxFiles) {
                input.value = "";
                status.textContent = "Select at most " + maxFiles + " images at a time.";
                setBusy(false);
                return;
            }

            setBusy(true);
            status.textContent = "Preparing " + files.length + (files.length === 1 ? " image" : " images") + "...";

            var tasks = files.map(function (file) {
                var ui = addItem(file);
                if (ALLOWED.indexOf(file.type) === -1) {
                    fail(ui, "this format is not supported (use JPEG, PNG or WebP)");
                    return Promise.resolve(null);
                }

                var work = Promise.resolve(file);
                if (file.size > COMPRESS_ABOVE && typeof window.imageCompression === "function") {
                    work = window.imageCompression(file, {
                        maxSizeMB: 3,
                        maxWidthOrHeight: 2400,
                        useWebWorker: false,
                        initialQuality: 0.85,
                        preserveExif: false
                    }).then(function (blob) {
                        // Keep the original if compression did not help.
                        if (!blob || blob.size >= file.size) { return file; }
                        return new File([blob], file.name, { type: blob.type || file.type, lastModified: file.lastModified });
                    }).catch(function () { return file; });
                }

                return work.then(function (ready) {
                    if (ready.size > maxBytes) {
                        fail(ui, "it is larger than " + formatSize(maxBytes));
                        return null;
                    }
                    ui.note.textContent = ready === file
                        ? formatSize(ready.size) + ", ready"
                        : "Reduced from " + formatSize(file.size) + " to " + formatSize(ready.size) + ", ready";
                    return readAsDataUrl(ready).then(function (url) {
                        var img = document.createElement("img");
                        img.src = url;
                        img.alt = "";
                        img.width = 72;
                        img.height = 54;
                        ui.thumb.appendChild(img);
                        return ready;
                    }).catch(function () { return ready; });
                });
            });

            Promise.all(tasks).then(function (results) {
                if (current !== run) { return; }
                var accepted = results.filter(Boolean);
                var changed = accepted.length !== files.length || accepted.some(function (f, i) { return f !== files[i]; });

                if (changed && typeof DataTransfer === "function") {
                    try {
                        var transfer = new DataTransfer();
                        accepted.forEach(function (f) { transfer.items.add(f); });
                        input.files = transfer.files;
                    } catch (error) { /* keep the original selection; the server judges every file */ }
                }

                var rejected = files.length - accepted.length;
                var total = accepted.reduce(function (sum, f) { return sum + f.size; }, 0);
                // The whole request (all files plus form fields) must fit in PHP's post_max_size or PHP discards it.
                maxTotal = parseInt(form.getAttribute("data-max-total"), 10) || 0;   // read when needed so a server-side change shows up without reloading the script
                if (maxTotal > 0 && total > maxTotal - 262144) {
                    overTotal = true;
                    status.textContent = "These images add up to " + formatSize(total) + ", more than the server accepts in one upload (" + formatSize(maxTotal) + "). Choose fewer images.";
                    setBusy(false);
                    if (button) { button.disabled = true; }
                    return;
                }
                if (accepted.length === 0) {
                    status.textContent = "None of the selected files can be uploaded. Choose JPEG, PNG or WebP images up to " + formatSize(maxBytes) + ".";
                    input.value = "";
                } else {
                    status.textContent = accepted.length + (accepted.length === 1 ? " image is" : " images are") + " ready to upload."
                        + (rejected > 0 ? " " + rejected + (rejected === 1 ? " file was" : " files were") + " left out." : "");
                }
                setBusy(false);
            });
        });

        // Do not let a half-prepared selection be sent.
        form.addEventListener("submit", function (event) {
            if (preparing || overTotal) { event.preventDefault(); event.stopImmediatePropagation(); }
        }, true);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
