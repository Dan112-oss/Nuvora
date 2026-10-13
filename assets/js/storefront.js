/* ==========================================================================
   NOVURA MARKETPLACE - STOREFRONT JAVASCRIPT
   Loaded after app.js on home, shop, category, search and product pages.
   Requires window.Novura (from app.js).
   ========================================================================== */
(function () {
    'use strict';

    var doc = document;

    function qs(selector, context) { return (context || doc).querySelector(selector); }
    function qsa(selector, context) { return Array.prototype.slice.call((context || doc).querySelectorAll(selector)); }
    function pad(n) { return n < 10 ? '0' + n : String(n); }

    /* ---------------------------------------------------------------------
     * Hero slider
     * ------------------------------------------------------------------ */
    function initHero() {
        var hero = qs('[data-hero]');
        if (!hero) { return; }

        var slides = qsa('.hero-slide', hero);
        if (slides.length < 2) { return; }

        var dotsBox = qs('[data-hero-dots]', hero);
        var dots = [];
        var current = 0;
        var timer = null;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        slides.forEach(function (slide, index) {
            if (!dotsBox) { return; }
            var dot = doc.createElement('button');
            dot.type = 'button';
            dot.className = 'hero-dot' + (index === 0 ? ' is-active' : '');
            dot.setAttribute('aria-label', 'Go to slide ' + (index + 1));
            dot.addEventListener('click', function () { show(index); restart(); });
            dotsBox.appendChild(dot);
            dots.push(dot);
        });

        function show(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                var active = i === current;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                slide.tabIndex = active ? 0 : -1;
            });
            dots.forEach(function (dot, i) { dot.classList.toggle('is-active', i === current); });
        }

        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function start() { if (!reduceMotion && !timer) { timer = setInterval(function () { show(current + 1); }, 6000); } }
        function restart() { stop(); start(); }

        var prev = qs('[data-hero-prev]', hero);
        var next = qs('[data-hero-next]', hero);
        if (prev) { prev.addEventListener('click', function () { show(current - 1); restart(); }); }
        if (next) { next.addEventListener('click', function () { show(current + 1); restart(); }); }

        hero.addEventListener('mouseenter', stop);
        hero.addEventListener('mouseleave', start);
        hero.addEventListener('focusin', stop);
        hero.addEventListener('focusout', start);

        // Swipe on touch screens
        var startX = null;
        hero.addEventListener('touchstart', function (event) { startX = event.touches[0].clientX; stop(); }, { passive: true });
        hero.addEventListener('touchend', function (event) {
            if (startX !== null) {
                var delta = event.changedTouches[0].clientX - startX;
                if (Math.abs(delta) > 40) { show(current + (delta < 0 ? 1 : -1)); }
            }
            startX = null;
            start();
        });

        doc.addEventListener('visibilitychange', function () { if (doc.hidden) { stop(); } else { start(); } });

        start();
    }

    /* ---------------------------------------------------------------------
     * Countdown timers: <span data-countdown="2026-10-20T12:00:00+01:00">
     * ------------------------------------------------------------------ */
    function initCountdowns() {
        var timers = qsa('[data-countdown]');
        if (!timers.length) { return; }

        function tick() {
            var now = Date.now();
            var running = false;

            timers.forEach(function (el) {
                var end = Date.parse(el.getAttribute('data-countdown'));
                if (isNaN(end)) { return; }

                var left = Math.floor((end - now) / 1000);
                if (left <= 0) {
                    el.textContent = 'Ended';
                    return;
                }

                running = true;
                var days = Math.floor(left / 86400);
                var hours = Math.floor((left % 86400) / 3600);
                var minutes = Math.floor((left % 3600) / 60);
                var seconds = left % 60;
                el.textContent = (days > 0 ? days + 'd ' : '') + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
            });

            if (!running) { clearInterval(handle); }
        }

        var handle = setInterval(tick, 1000);
        tick();
    }

    /* ---------------------------------------------------------------------
     * Product gallery thumbnails
     * ------------------------------------------------------------------ */
    function initGallery() {
        var gallery = qs('[data-gallery]');
        if (!gallery) { return; }

        var main = qs('[data-gallery-main]', gallery);
        var thumbs = qsa('[data-gallery-thumb]', gallery);

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                if (main) { main.src = thumb.getAttribute('data-src'); }
                thumbs.forEach(function (other) {
                    var active = other === thumb;
                    other.classList.toggle('is-active', active);
                    other.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            });
        });
    }

    /* ---------------------------------------------------------------------
     * Quantity stepper + variant selection (price, stock, max quantity)
     * ------------------------------------------------------------------ */
    function initBuyBox() {
        var priceBox = qs('[data-price-box]');
        var qtyInput = qs('[data-qty-input]');
        if (!qtyInput) { return; }

        var stockText = qs('[data-stock-text]');
        var addButton = qs('[data-add-to-cart]');
        var hardMax = 20;

        function maxQty() { return Math.max(1, parseInt(qtyInput.max, 10) || 1); }

        function clamp() {
            var value = parseInt(qtyInput.value, 10);
            if (isNaN(value) || value < 1) { value = 1; }
            if (value > maxQty()) { value = maxQty(); }
            qtyInput.value = String(value);
        }

        var minus = qs('[data-qty-minus]');
        var plus = qs('[data-qty-plus]');
        if (minus) { minus.addEventListener('click', function () { qtyInput.value = String((parseInt(qtyInput.value, 10) || 1) - 1); clamp(); }); }
        if (plus) { plus.addEventListener('click', function () { qtyInput.value = String((parseInt(qtyInput.value, 10) || 1) + 1); clamp(); }); }
        qtyInput.addEventListener('change', clamp);

        function applyVariant(radio) {
            if (!radio) { return; }

            var stock = parseInt(radio.getAttribute('data-stock'), 10) || 0;
            var adjust = parseFloat(radio.getAttribute('data-adjust')) || 0;

            if (priceBox) {
                var symbol = priceBox.getAttribute('data-symbol') || '';
                var base = parseFloat(priceBox.getAttribute('data-base')) || 0;
                var old = parseFloat(priceBox.getAttribute('data-old')) || 0;
                var currentEl = qs('[data-price-current]', priceBox);
                var oldEl = qs('[data-price-old]', priceBox);

                if (currentEl) { currentEl.textContent = window.Novura.formatMoney(base + adjust, symbol); }
                if (oldEl && old > 0) { oldEl.textContent = window.Novura.formatMoney(old + adjust, symbol); }
            }

            qtyInput.max = String(Math.min(hardMax, Math.max(1, stock)));
            clamp();

            if (stockText) {
                stockText.classList.toggle('is-out', stock <= 0);
                stockText.textContent = stock <= 0 ? 'Out of stock' : (stock <= 5 ? 'Only ' + stock + ' left in stock' : 'In stock');
            }

            if (addButton && stock > 0) { addButton.disabled = false; }
        }

        qsa('input[name="variant_id"]').forEach(function (radio) {
            radio.addEventListener('change', function () { applyVariant(radio); });
        });

        applyVariant(qs('input[name="variant_id"]:checked'));
    }

    /* ---------------------------------------------------------------------
     * Add to cart (talks to api/cart.php, built in the cart step)
     * ------------------------------------------------------------------ */
    function initAddToCart() {
        var button = qs('[data-add-to-cart]');
        if (!button) { return; }

        button.addEventListener('click', function () {
            var qtyInput = qs('[data-qty-input]');
            var variant = qs('input[name="variant_id"]:checked');

            if (button.getAttribute('data-has-variants') === '1' && !variant) {
                window.Novura.toast('Please choose an option first.', 'warning');
                return;
            }

            var payload = {
                action: 'add',
                product_id: parseInt(button.getAttribute('data-product-id'), 10),
                variant_id: variant ? parseInt(variant.value, 10) : null,
                quantity: qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1
            };

            button.disabled = true;
            button.classList.add('is-loading');

            window.Novura.api(button.getAttribute('data-endpoint'), { method: 'POST', data: payload })
                .then(function (response) {
                    window.Novura.toast(response.message || 'Added to your cart.', 'success');
                    if (response.cart_count !== undefined) { window.Novura.setCartCount(response.cart_count); }
                })
                .catch(function (error) {
                    window.Novura.toast(error.message, 'error');
                })
                .then(function () {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    }

    /* ---------------------------------------------------------------------
     * Listing pages: auto-submit sort, mobile filters toggle
     * ------------------------------------------------------------------ */
    function initListing() {
        qsa('[data-autosubmit]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (field.form) { field.form.submit(); }
            });
        });

        var toggle = qs('[data-filters-toggle]');
        var panel = qs('#filters');
        if (toggle && panel) {
            toggle.addEventListener('click', function () {
                var open = panel.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
    }

    /* ---------------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------------ */
    function init() {
        if (!window.Novura) { return; }
        initHero();
        initCountdowns();
        initGallery();
        initBuyBox();
        initAddToCart();
        initListing();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
