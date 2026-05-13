(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Copy-to-clipboard
        document.querySelectorAll('[data-copy]').forEach(function (el) {
            el.addEventListener('click', function () {
                var text = el.getAttribute('data-copy') || el.textContent;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function () {
                        var original = el.textContent;
                        el.textContent = '✓ Copied';
                        setTimeout(function () { el.textContent = original; }, 1200);
                    });
                }
            });
        });

        // Status polling
        var statusEl = document.getElementById('npay-status');
        if (!statusEl) return;

        var url = statusEl.dataset.statusUrl;
        var interval = parseInt(statusEl.dataset.interval || '5000', 10);
        if (!url) return;

        var textEl = statusEl.querySelector('.npay-status-text');

        function checkStatus() {
            fetch(url, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.completed) {
                        statusEl.classList.add('is-completed');
                        if (textEl) {
                            textEl.textContent = statusEl.dataset.completedText
                                || 'Thanh toán thành công!';
                        }
                        clearInterval(timer);
                        setTimeout(function () {
                            window.location.reload();
                        }, 2000);
                    }
                })
                .catch(function () { /* silent */ });
        }

        var timer = setInterval(checkStatus, interval);
        checkStatus();
    });
})();
