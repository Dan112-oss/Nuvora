/* ==========================================================================
   NOVURA MARKETPLACE - GLOBAL JAVASCRIPT
   Exposes window.Novura = { api, toast, setCartCount, formatMoney }
   ========================================================================== */
(function () {
    'use strict';

    var doc = document;

    function qs(selector, context) { return (context || doc).querySelector(selector); }
    function qsa(selector, context) { return Array.prototype.slice.call((context || doc).querySelectorAll(selector)); }

    /* ---------------------------------------------------------------------
     * API helper: JSON requests with CSRF header, timeout and clean errors.
     *
     *   Novura.api('/api/cart.php', { method: 'POST', data: { id: 5 } })
     *       .then(function (res) { ... })
     *       .catch(function (err) { Novura.toast(err.message, 'error'); });
     * ------------------------------------------------------------------ */
    function csrfToken() {
        var meta = qs('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function api(url, options) {
        options = options || {};

        var method = String(options.method || 'GET').toUpperCase();
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var body;

        if (method !== 'GET' && method !== 'HEAD') {
            headers['X-CSRF-Token'] = csrfToken();
            if (options.data !== undefined) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(options.data);
            }
        } else if (options.data && typeof options.data === 'object') {
            var query = new URLSearchParams(options.data).toString();
            if (query) { url += (url.indexOf('?') === -1 ? '?' : '&') + query; }
        }

        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, options.timeout || 20000) : null;

        return fetch(url, {
            method: method,
            headers: headers,
            body: body,
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok || data.success === false) {
                    var error = new Error(data.message || 'Something went wrong. Please try again.');
                    error.status = response.status;
                    error.data = data;
                    throw error;
                }
                return data;
            });
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                throw new Error('The request took too long. Please try again.');
            }
            if (error instanceof TypeError) {
                throw new Error('Network problem. Check your connection and try again.');
            }
            throw error;
        }).then(function (data) {
            if (timer) { clearTimeout(timer); }
            return data;
        }, function (error) {
            if (timer) { clearTimeout(timer); }
            throw error;
        });
    }

    /* ---------------------------------------------------------------------
     * Toasts
     * ------------------------------------------------------------------ */
    function toast(message, type, duration) {
        var stack = qs('.toast-stack');
        if (!stack) {
            stack = doc.createElement('div');
            stack.className = 'toast-stack';
            stack.setAttribute('role', 'status');
            stack.setAttribute('aria-live', 'polite');
            doc.body.appendChild(stack);
        }

        var item = doc.createElement('div');
        item.className = 'toast toast-' + (type || 'info');
        item.textContent = String(message);
        stack.appendChild(item);

        setTimeout(function () {
            if (item.parentNode) { item.parentNode.removeChild(item); }
        }, duration || 4000);
    }

    /* ---------------------------------------------------------------------
     * Small utilities
     * ------------------------------------------------------------------ */
    function setCartCount(count) {
        var total = Math.max(0, parseInt(count, 10) || 0);
        qsa('[data-cart-count]').forEach(function (badge) {
            badge.textContent = String(total);
            badge.hidden = total === 0;
        });
    }

    function formatMoney(amount, symbol) {
        var value = Number(amount) || 0;
        var hasDecimals = Math.abs(value - Math.round(value)) >= 0.005;
        return (symbol === undefined ? '\u20A6' : symbol) + value.toLocaleString('en-NG', {
            minimumFractionDigits: hasDecimals ? 2 : 0,
            maximumFractionDigits: 2
        });
    }

    /* ---------------------------------------------------------------------
     * Dropdowns: <div data-dropdown><button data-dropdown-toggle>...</button><div class="dropdown-menu">
     * ------------------------------------------------------------------ */
    function closeDropdowns(except) {
        qsa('[data-dropdown].open').forEach(function (dropdown) {
            if (dropdown === except) { return; }
            dropdown.classList.remove('open');
            var toggle = qs('[data-dropdown-toggle]', dropdown);
            if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
        });
    }

    function initDropdowns() {
        doc.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-dropdown-toggle]');

            if (toggle) {
                var dropdown = toggle.closest('[data-dropdown]');
                var willOpen = !dropdown.classList.contains('open');
                closeDropdowns(dropdown);
                dropdown.classList.toggle('open', willOpen);
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                return;
            }

            if (!event.target.closest('[data-dropdown]')) { closeDropdowns(); }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeDropdowns(); closeNav(); }
        });
    }

    /* ---------------------------------------------------------------------
     * Mobile category menu
     * ------------------------------------------------------------------ */
    function closeNav() {
        var nav = qs('#category-nav');
        var button = qs('[data-nav-toggle]');
        if (nav) { nav.classList.remove('is-open'); }
        if (button) { button.setAttribute('aria-expanded', 'false'); }
    }

    function initNavToggle() {
        var button = qs('[data-nav-toggle]');
        var nav = qs('#category-nav');
        if (!button || !nav) { return; }

        button.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ---------------------------------------------------------------------
     * Flash alerts: dismiss button + auto-hide for success messages
     * ------------------------------------------------------------------ */
    function initFlash() {
        doc.addEventListener('click', function (event) {
            var button = event.target.closest('[data-dismiss]');
            if (!button) { return; }
            var alert = button.closest('.alert');
            if (alert && alert.parentNode) { alert.parentNode.removeChild(alert); }
        });

        qsa('.alert-success').forEach(function (alert) {
            setTimeout(function () {
                if (alert.parentNode) { alert.parentNode.removeChild(alert); }
            }, 6000);
        });
    }

    /* ---------------------------------------------------------------------
     * Password show/hide: <button data-toggle-password> inside .input-group
     * ------------------------------------------------------------------ */
    function initPasswordToggle() {
        doc.addEventListener('click', function (event) {
            var button = event.target.closest('[data-toggle-password]');
            if (!button) { return; }

            var input = qs('input', button.closest('.input-group'));
            if (!input) { return; }

            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    }

    /* ---------------------------------------------------------------------
     * Prevent double submits: <form data-once>
     * ------------------------------------------------------------------ */
    function initSubmitGuard() {
        qsa('form[data-once]').forEach(function (form) {
            form.addEventListener('submit', function () {
                qsa('button[type="submit"]', form).forEach(function (button) {
                    button.disabled = true;
                    button.classList.add('is-loading');
                });
            });
        });

        // Back/forward cache can restore a page with disabled buttons.
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) { return; }
            qsa('form[data-once] button[type="submit"]').forEach(function (button) {
                button.disabled = false;
                button.classList.remove('is-loading');
            });
        });
    }

    /* ---------------------------------------------------------------------
     * Register page: show store fields only for sellers
     * ------------------------------------------------------------------ */
    function initAccountType() {
        var radios = qsa('input[name="account_type"]');
        if (!radios.length) { return; }

        function sync() {
            var checked = qs('input[name="account_type"]:checked');
            var isVendor = !!checked && checked.value === 'vendor';

            qsa('[data-vendor-only]').forEach(function (block) {
                block.hidden = !isVendor;
                qsa('input, select, textarea', block).forEach(function (field) {
                    field.disabled = !isVendor;
                    field.required = isVendor && field.hasAttribute('data-required-vendor');
                });
            });
        }

        radios.forEach(function (radio) { radio.addEventListener('change', sync); });
        sync();
    }

    /* ---------------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------------ */
    function init() {
        initDropdowns();
        initNavToggle();
        initFlash();
        initPasswordToggle();
        initSubmitGuard();
        initAccountType();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.Novura = {
        api: api,
        toast: toast,
        setCartCount: setCartCount,
        formatMoney: formatMoney
    };
})();
