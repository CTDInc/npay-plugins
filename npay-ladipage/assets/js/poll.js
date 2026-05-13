/* npay-ladipage — poll order status from qr-page.php */
(function () {
    'use strict';

    var cfg = window.NPAY_CONFIG || {};
    if (!cfg.statusUrl) return;

    var card     = document.getElementById('qrCard');
    var box      = document.getElementById('statusBox');
    var cd       = document.getElementById('countdown');
    var initial  = card && card.dataset.status ? card.dataset.status : 'pending';
    var pollMs   = cfg.pollMs || 4000;
    var stopped  = false;

    function setStatus(status, paidAt) {
        if (!box) return;
        box.className = 'status status-' + status;
        if (status === 'paid') {
            box.textContent = '✅ Đã thanh toán' + (paidAt ? ' lúc ' + paidAt : '') + '. Cảm ơn quý khách!';
        } else if (status === 'expired') {
            box.textContent = '⌛ Đơn đã hết hạn. Vui lòng tạo đơn mới.';
        } else if (status === 'cancelled') {
            box.textContent = '✖ Đơn đã bị huỷ.';
        } else {
            box.textContent = '⏳ Đang chờ thanh toán…';
        }
    }

    function poll() {
        if (stopped) return;
        fetch(cfg.statusUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.ok) return;
                setStatus(j.status, j.paid_at);
                if (j.status === 'paid' || j.status === 'cancelled') {
                    stopped = true;
                    if (j.status === 'paid') {
                        // small visual cue: green border
                        if (card) card.style.boxShadow = '0 0 0 3px #22c55e';
                    }
                    return;
                }
                if (j.status === 'expired') {
                    stopped = true;
                    return;
                }
                setTimeout(poll, pollMs);
            })
            .catch(function () {
                setTimeout(poll, pollMs * 2);
            });
    }

    function tickCountdown() {
        if (!cd) return;
        var remaining = parseInt(cd.dataset.remaining || '0', 10);
        function step() {
            if (remaining <= 0) {
                cd.textContent = '00:00';
                return;
            }
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            cd.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            remaining -= 1;
            setTimeout(step, 1000);
        }
        step();
    }

    // Kick off.
    if (initial === 'pending') {
        tickCountdown();
        setTimeout(poll, 1500);
    } else {
        setStatus(initial);
    }
})();
