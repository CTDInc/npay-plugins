/**
 * NPay polling client for NukeViet shops checkout.
 *
 * Reads data-* attributes off #npay-checkout and polls the webhook
 * status endpoint until the order is reported as paid, then reloads
 * the page so NukeViet redirects to the confirmation step.
 */
(function () {
    'use strict';

    var root = document.getElementById('npay-checkout');
    if (!root) return;

    var pollUrl  = root.getAttribute('data-poll-url');
    var payId    = root.getAttribute('data-pay-id');
    var statusEl = document.getElementById('npay-status');
    var labelEl  = statusEl ? statusEl.querySelector('.npay-status__label') : null;

    var POLL_INTERVAL_MS = 5000;
    var MAX_POLLS = 360; // ~30 minutes
    var polls = 0;

    function setPaid() {
        if (statusEl) statusEl.classList.add('is-paid');
        if (labelEl) {
            var paidLabel = root.getAttribute('data-label-paid') || 'Payment confirmed!';
            labelEl.textContent = paidLabel;
        }
    }

    function poll() {
        if (!pollUrl || polls++ >= MAX_POLLS) return;

        var req = new XMLHttpRequest();
        req.open('GET', pollUrl + '&_=' + Date.now(), true);
        req.setRequestHeader('Accept', 'application/json');
        req.onreadystatechange = function () {
            if (req.readyState !== 4) return;
            try {
                if (req.status >= 200 && req.status < 300) {
                    var data = JSON.parse(req.responseText || '{}');
                    if (data && data.paid) {
                        setPaid();
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                        return;
                    }
                }
            } catch (e) {
                // swallow parse errors; keep polling
            }
            setTimeout(poll, POLL_INTERVAL_MS);
        };
        req.onerror = function () { setTimeout(poll, POLL_INTERVAL_MS); };
        req.send();
    }

    // Copy-to-clipboard for any [data-copy] target.
    function bindCopy() {
        var buttons = document.querySelectorAll('.npay-btn-copy');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', function (ev) {
                ev.preventDefault();
                var btn = ev.currentTarget;
                var value = btn.getAttribute('data-copy') || '';
                if (!value) return;
                var done = function () {
                    var original = btn.textContent;
                    btn.classList.add('is-copied');
                    btn.textContent = btn.getAttribute('data-copied-label') || 'Copied';
                    setTimeout(function () {
                        btn.classList.remove('is-copied');
                        btn.textContent = original;
                    }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done, function () { fallbackCopy(value, done); });
                } else {
                    fallbackCopy(value, done);
                }
            });
        }
    }

    function fallbackCopy(value, done) {
        var ta = document.createElement('textarea');
        ta.value = value;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        if (done) done();
    }

    bindCopy();
    if (pollUrl && payId) {
        setTimeout(poll, POLL_INTERVAL_MS);
    }
})();
