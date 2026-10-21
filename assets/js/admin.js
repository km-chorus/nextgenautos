/**
 * Admin UI behaviour (spec: Context/specs/02_spec_admin.md).
 * Vanilla JS, no dependencies, UI behaviour only:
 *   - mobile off-canvas navigation
 *   - password show/hide
 *   - dismissible alerts
 *   - login form client-side checks and double-submit protection (authentication
 *     itself is entirely server-side)
 *   - Add Brand form: slug follows the name until edited
 *   - button loading-state demo
 *
 * Loaded in <head> without defer so the "js" class is set before first paint
 * (the off-canvas navigation styles only apply when JS is available).
 */
(function () {
    "use strict";

    document.documentElement.classList.add("js");

    function ready(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    /* ---------- Mobile navigation ---------------------------------------- */

    function initNavigation() {
        var shell = document.querySelector("[data-admin-shell]");
        var sidebar = document.getElementById("admin-sidebar");
        var toggles = document.querySelectorAll("[data-nav-toggle]");
        var closers = document.querySelectorAll("[data-nav-close]");
        if (!shell || !sidebar || toggles.length === 0) { return; }

        var desktop = window.matchMedia("(min-width: 1024px)");
        var lastFocus = null;
        // Everything that must be unreachable while the menu is open.
        var background = document.querySelectorAll("[data-nav-background]");

        function setOpen(open) {
            shell.classList.toggle("nav-open", open);
            document.documentElement.classList.toggle("nav-lock", open);
            toggles.forEach(function (btn) { btn.setAttribute("aria-expanded", String(open)); });
            background.forEach(function (el) {
                if (open) { el.setAttribute("inert", ""); } else { el.removeAttribute("inert"); }
            });

            if (open) {
                lastFocus = document.activeElement;
                var first = sidebar.querySelector("[data-nav-close]") || sidebar.querySelector("a[href]");
                if (first) { first.focus(); }
            } else if (lastFocus && typeof lastFocus.focus === "function") {
                lastFocus.focus();
                lastFocus = null;
            }
        }

        toggles.forEach(function (btn) {
            btn.addEventListener("click", function () {
                setOpen(!shell.classList.contains("nav-open"));
            });
        });
        closers.forEach(function (el) {
            el.addEventListener("click", function () { setOpen(false); });
        });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && shell.classList.contains("nav-open")) {
                setOpen(false);
            }
        });
        // Leaving the mobile layout while open must reset everything.
        var onChange = function () {
            if (desktop.matches && shell.classList.contains("nav-open")) {
                lastFocus = null;
                setOpen(false);
            }
        };
        if (desktop.addEventListener) { desktop.addEventListener("change", onChange); }
        else if (desktop.addListener) { desktop.addListener(onChange); }
    }

    /* ---------- Password show / hide ------------------------------------- */

    function initPasswordToggles() {
        document.querySelectorAll("[data-password-toggle]").forEach(function (btn) {
            var input = document.getElementById(btn.getAttribute("aria-controls"));
            if (!input) { return; }
            btn.hidden = false; // hidden in markup so it is not shown without JS
            btn.addEventListener("click", function () {
                var show = input.type === "password";
                input.type = show ? "text" : "password";
                btn.setAttribute("aria-pressed", String(show));
                btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
                var use = btn.querySelector("use");
                if (use) {
                    var href = use.getAttribute("href") || "";
                    use.setAttribute("href", href.replace(/#i-.*$/, show ? "#i-eye-off" : "#i-eye"));
                }
                input.focus();
            });
        });
    }

    /* ---------- Dismissible alerts --------------------------------------- */

    function initAlerts() {
        document.addEventListener("click", function (event) {
            var btn = event.target.closest("[data-dismiss-alert]");
            if (!btn) { return; }
            var alert = btn.closest(".alert");
            if (!alert) { return; }
            var next = document.querySelector("[data-focus-after-dismiss]");
            alert.remove();
            if (next) { next.focus(); }
        });
    }

    /* Links for features that do not exist yet must not navigate. */
    function initUnavailableLinks() {
        document.addEventListener("click", function (event) {
            var link = event.target.closest('a[aria-disabled="true"]');
            if (link) { event.preventDefault(); }
        });
    }

    /* ---------- Login form: client-side checks (the server validates again) ---- */

    function initLoginForm() {
        var form = document.querySelector("[data-login-form]");
        if (!form) { return; }

        var email = form.querySelector("#email");
        var password = form.querySelector("#password");
        var button = form.querySelector("[data-submit-button]");
        var notice = document.querySelector("[data-login-notice]");

        function setError(input, message) {
            var error = document.getElementById(input.id + "-error");
            if (!error) { return; }
            var text = error.querySelector("[data-error-text]");
            if (message) {
                text.textContent = message;
                error.hidden = false;
                input.setAttribute("aria-invalid", "true");
            } else {
                error.hidden = true;
                input.removeAttribute("aria-invalid");
            }
        }

        // Clear an error as soon as the user edits the field.
        [email, password].forEach(function (input) {
            input.addEventListener("input", function () { setError(input, ""); });
        });

        // Browsers may restore this page from the back/forward cache with the button still busy.
        window.addEventListener("pageshow", function () {
            form.removeAttribute("data-submitting");
            if (button) {
                button.classList.remove("is-loading");
                button.removeAttribute("aria-busy");
            }
        });

        // Move attention to the server's message (wrong credentials, throttling, ...).
        if (notice) { notice.focus(); }

        form.addEventListener("submit", function (event) {
            if (form.hasAttribute("data-submitting")) {
                event.preventDefault(); // already sending: ignore double submits
                return;
            }

            var emailValue = email.value.trim();
            var firstInvalid = null;

            if (emailValue === "") {
                setError(email, "Enter your email address.");
                firstInvalid = email;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
                setError(email, "Enter a valid email address, for example name@example.com.");
                firstInvalid = email;
            } else {
                setError(email, "");
            }

            if (password.value === "") {
                setError(password, "Enter your password.");
                if (!firstInvalid) { firstInvalid = password; }
            } else {
                setError(password, "");
            }

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }

            // Valid: let the browser submit to the server (which validates everything again).
            form.setAttribute("data-submitting", "");
            if (button) {
                button.classList.add("is-loading");
                button.setAttribute("aria-busy", "true");
            }
        });
    }

    /* ---------- Password strength feedback (advice only; the server enforces the rules) ---- */

    function initPasswordStrength() {
        document.querySelectorAll("[data-password-strength]").forEach(function (input) {
            var form = input.closest("form");
            var label = form && form.querySelector("[data-strength-label]");
            if (!label) { return; }

            input.addEventListener("input", function () {
                var value = input.value;
                if (value === "") {
                    label.textContent = "At least 12 characters.";
                    label.removeAttribute("data-level");
                    return;
                }
                var classes = 0;
                [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/].forEach(function (re) { if (re.test(value)) { classes += 1; } });
                var level;
                if (value.length < 12) { level = "weak"; }
                else if (value.length >= 20 || (value.length >= 16 && classes >= 3)) { level = "strong"; }
                else if (value.length >= 16 || classes >= 3) { level = "good"; }
                else { level = "fair"; }

                var names = { weak: "Weak", fair: "Fair", good: "Good", strong: "Strong" };
                label.textContent = "Password strength: " + names[level] + (level === "weak" ? " (use at least 12 characters)" : "");
                label.setAttribute("data-level", level);
            });
        });
    }

    /* ---------- Double-submit protection for the other forms ----------------------------- */

    function initSingleSubmit() {
        document.querySelectorAll("form[data-single-submit]").forEach(function (form) {
            var button = form.querySelector("[data-submit-button]");
            window.addEventListener("pageshow", function () {
                form.removeAttribute("data-submitting");
                if (button) { button.classList.remove("is-loading"); button.removeAttribute("aria-busy"); }
            });
            form.addEventListener("submit", function (event) {
                if (form.hasAttribute("data-submitting")) { event.preventDefault(); return; }
                form.setAttribute("data-submitting", "");
                if (button) { button.classList.add("is-loading"); button.setAttribute("aria-busy", "true"); }
            });
        });
    }

    /* Move attention to a server message (errors, confirmations) on pages without the login form. */
    function initNoticeFocus() {
        if (document.querySelector("[data-login-form]")) { return; }
        var notice = document.querySelector("[data-login-notice]");
        if (notice) { notice.focus(); }
    }

    /* ---------- Add Brand: the slug follows the name until the user edits it -------------- */

    function initSlugForm() {
        var form = document.querySelector("[data-slug-form]");
        if (!form) { return; }
        var source = form.querySelector("[data-slug-source]");
        var target = form.querySelector("[data-slug-target]");
        if (!source || !target) { return; }

        function slugify(text) {
            return text.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 100);
        }

        // Follow only while the slug is empty or still equals what the name would produce.
        var following = target.value === "" || target.value === slugify(source.value);
        source.addEventListener("input", function () {
            if (following) { target.value = slugify(source.value); }
        });
        target.addEventListener("input", function () {
            following = target.value === "" || target.value === slugify(source.value);
        });
    }

    /* ---------- Loading-state demo (component library page) -------------- */

    function initLoadingDemo() {
        document.querySelectorAll("[data-demo-loading]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                if (btn.classList.contains("is-loading")) { return; }
                var label = btn.textContent;
                btn.classList.add("is-loading");
                btn.setAttribute("aria-busy", "true");
                btn.setAttribute("aria-disabled", "true");
                btn.textContent = "Saving";
                window.setTimeout(function () {
                    btn.classList.remove("is-loading");
                    btn.removeAttribute("aria-busy");
                    btn.removeAttribute("aria-disabled");
                    btn.textContent = label;
                }, 1800);
            });
        });
    }

    ready(function () {
        initNavigation();
        initPasswordToggles();
        initAlerts();
        initUnavailableLinks();
        initLoginForm();
        initPasswordStrength();
        initSingleSubmit();
        initNoticeFocus();
        initSlugForm();
        initLoadingDemo();
    });
})();
