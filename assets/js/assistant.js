/* Site assistant (spec 19): the chat window. Every answer comes from /assistant/ (JSON built on the server from the
   site's real data) and is inserted with textContent only. Links are accepted only for the site itself, tel:, mailto:
   and https:. Nothing is stored: closing the page forgets the conversation. Vanilla JS, no dependencies. */
(function () {
    'use strict';

    var root = document.querySelector('[data-assistant]');
    if (!root || !window.fetch) { return; }

    var panel = root.querySelector('[data-assistant-panel]');
    var toggle = root.querySelector('[data-assistant-toggle]');
    var closeBtn = root.querySelector('[data-assistant-close]');
    var log = root.querySelector('[data-assistant-log]');
    var form = root.querySelector('[data-assistant-form]');
    var input = root.querySelector('[data-assistant-input]');
    var send = root.querySelector('[data-assistant-send]');
    var busy = false;
    var started = false;

    root.hidden = false;

    // Keep the button above the cookie banner while the banner is shown.
    var banner = document.querySelector('[data-cookie-banner]');
    function offset() {
        var h = banner && document.contains(banner) ? banner.getBoundingClientRect().height : 0;
        root.style.setProperty('--assistant-offset', Math.round(h) + 'px');
    }
    offset();
    window.addEventListener('resize', offset);

    function isOpen() { return !panel.hidden; }

    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close the site assistant');
        root.classList.add('is-open');
        if (!started) { started = true; ask({ intent: 'start' }, null); }
        input.focus();
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open the site assistant');
        root.classList.remove('is-open');
        toggle.focus();
    }

    toggle.addEventListener('click', function () { if (isOpen()) { close(); } else { open(); } });
    closeBtn.addEventListener('click', close);
    root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) { event.preventDefault(); close(); }
    });

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = String(text); }
        return node;
    }

    function safeHref(href) {
        if (typeof href !== 'string') { return null; }
        if (/^\/(?!\/)/.test(href) || /^(tel|mailto):/i.test(href) || /^https:\/\//i.test(href)) { return href; }
        return null;
    }

    function scrollDown() { log.scrollTop = log.scrollHeight; }

    function addUser(text) {
        var row = el('div', 'assistant__msg assistant__msg--user');
        row.appendChild(el('p', 'assistant__bubble', text));
        log.appendChild(row);
        scrollDown();
    }

    function removeOldChips() {
        log.querySelectorAll('.assistant__chips').forEach(function (c) { c.parentNode.removeChild(c); });
    }

    function addBot(data) {
        removeOldChips();
        (data.messages || []).forEach(function (m) {
            var row = el('div', 'assistant__msg assistant__msg--bot');
            var bubble = el('div', 'assistant__bubble');
            bubble.appendChild(el('p', 'assistant__text', m.text));
            if (m.lines && m.lines.length) {
                var list = el(m.numbered ? 'ol' : 'ul', 'assistant__lines');
                m.lines.forEach(function (line) { list.appendChild(el('li', null, line)); });
                bubble.appendChild(list);
            }
            if (m.items && m.items.length) {
                var items = el('ul', 'assistant__items');
                m.items.forEach(function (item) {
                    var li = el('li');
                    var href = safeHref(item.href);
                    var box = el(href ? 'a' : 'div', 'assistant__item');
                    if (href) { box.href = href; }
                    var head = el('span', 'assistant__item-head');
                    head.appendChild(el('span', 'assistant__item-title', item.title));
                    if (item.price) { head.appendChild(el('span', 'assistant__item-price', item.price)); }
                    box.appendChild(head);
                    if (item.meta) { box.appendChild(el('span', 'assistant__item-meta', item.meta)); }
                    li.appendChild(box);
                    items.appendChild(li);
                });
                bubble.appendChild(items);
            }
            if (m.links && m.links.length) {
                var links = el('div', 'assistant__links');
                m.links.forEach(function (link) {
                    var href = safeHref(link.href);
                    if (!href) { return; }
                    var a = el('a', 'assistant__link', link.label);
                    a.href = href;
                    if (link.external) {
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.appendChild(el('span', 'sr-only', ' (opens in a new tab)'));
                    }
                    links.appendChild(a);
                });
                bubble.appendChild(links);
            }
            row.appendChild(bubble);
            log.appendChild(row);
        });
        if (data.chips && data.chips.length) {
            var chips = el('div', 'assistant__chips');
            chips.setAttribute('role', 'group');
            chips.setAttribute('aria-label', 'Suggested questions');
            data.chips.forEach(function (chip) {
                var b = el('button', 'assistant__chip', chip.label);
                b.type = 'button';
                b.setAttribute('data-intent', chip.id);
                chips.appendChild(b);
            });
            log.appendChild(chips);
        }
        scrollDown();
    }

    function setBusy(state) {
        busy = state;
        send.disabled = state;
        log.setAttribute('aria-busy', state ? 'true' : 'false');
        var typing = log.querySelector('.assistant__typing');
        if (state && !typing) {
            typing = el('div', 'assistant__msg assistant__msg--bot assistant__typing');
            var dots = el('p', 'assistant__bubble');
            dots.setAttribute('aria-label', 'The assistant is answering');
            dots.appendChild(el('span', null, ''));
            dots.appendChild(el('span', null, ''));
            dots.appendChild(el('span', null, ''));
            typing.appendChild(dots);
            log.appendChild(typing);
            scrollDown();
        } else if (!state && typing) {
            typing.parentNode.removeChild(typing);
        }
    }

    function ask(params, shownText) {
        if (busy) { return; }
        if (shownText) { addUser(shownText); }
        setBusy(true);
        var query = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        var controller = window.AbortController ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, 10000) : null;
        fetch('/assistant/?' + query, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller ? controller.signal : undefined })
            .then(function (response) {
                if (!response.ok) { throw new Error('status ' + response.status); }
                return response.json();
            })
            .then(function (data) { setBusy(false); addBot(data); })
            .catch(function () {
                setBusy(false);
                addBot({ messages: [{ text: "Sorry, I can't answer right now. Please try again, or contact us directly.", links: [{ label: 'Contact us', href: '/contact/', external: false }] }], chips: [] });
            })
            .then(function () { if (timer) { clearTimeout(timer); } });
    }

    log.addEventListener('click', function (event) {
        var chip = event.target.closest && event.target.closest('[data-intent]');
        if (chip) { ask({ intent: chip.getAttribute('data-intent') }, chip.textContent); input.focus(); }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = input.value.replace(/\s+/g, ' ').trim();
        if (!text || busy) { return; }
        input.value = '';
        ask({ q: text.slice(0, 200) }, text.slice(0, 200));
    });
}());
