(function () {
    'use strict';
    function init(block) {
        var form = document.getElementById('product_addtocart_form');
        var slot = block.closest('[data-wgmulti-delivery-slot]');
        if (!form || !window.fetch) { if (slot) { slot.hidden = true; } return; }
        var sequence = 0, timer, expiry;
        function getSignature() {
            return Array.prototype.map.call(form.querySelectorAll('[name="qty"], [name^="super_attribute["]'), function (el) { return el.name + '=' + el.value; }).join('&');
        }
        var signature = getSignature();
        function refresh() {
            var request = ++sequence;
            block.hidden = true;
            if (slot) { slot.hidden = false; }
            var data = new URLSearchParams();
            data.set('product', block.dataset.product);
            var qty = form.querySelector('[name="qty"]');
            data.set('qty', qty ? qty.value : '1');
            form.querySelectorAll('[name^="super_attribute["]').forEach(function (field) {
                if (!field.disabled && (!/^(checkbox|radio)$/.test(field.type) || field.checked)) { data.set(field.name, field.value); }
            });
            fetch(block.dataset.url, {method: 'POST', credentials: 'omit', cache: 'no-store',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: data.toString()})
                .then(function (response) { if (!response.ok) { throw new Error('Unavailable'); } return response.json(); })
                .then(function (result) {
                    if (request !== sequence) { return; }
                    if (result.status !== 'estimate' && result.status !== 'choose_options') {
                        if (slot) { slot.hidden = true; }
                        return;
                    }
                    block.querySelector('.wgmulti-delivery__label').textContent = result.label || '';
                    block.querySelector('.wgmulti-delivery__detail').textContent = result.detail || '';
                    var notice = block.querySelector('.wgmulti-delivery__notice');
                    if (notice) { notice.hidden = result.status !== 'estimate'; }
                    block.hidden = false;
                    clearTimeout(expiry);
                    if (result.refresh_after) {
                        expiry = setTimeout(function () { if (!document.hidden) { schedule(); } }, Math.max(1, Math.min(300, result.refresh_after)) * 1000);
                    }
                }).catch(function () { if (request === sequence) { block.hidden = true; if (slot) { slot.hidden = true; } } });
        }
        function schedule() {
            signature = getSignature();
            ++sequence;
            block.hidden = true;
            if (slot) { slot.hidden = false; }
            clearTimeout(timer);
            clearTimeout(expiry);
            timer = setTimeout(refresh, 180);
        }
        function changed(event) {
            if (event.target.matches('[name="qty"], [name^="super_attribute["]')) { schedule(); }
        }
        form.addEventListener('change', changed);
        form.addEventListener('input', changed);
        // Porto quantity buttons and some configurable widgets update values without native input events.
        setInterval(function () {
            if (document.hidden) { return; }
            var next = getSignature();
            if (next !== signature) { signature = next; schedule(); }
        }, 700);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { schedule(); } });
        window.addEventListener('pageshow', schedule);
        refresh();
    }
    function ready() { document.querySelectorAll('[data-wgmulti-delivery]').forEach(init); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
}());
