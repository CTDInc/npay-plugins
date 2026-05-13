(function () {
    "use strict";
    var cfg = window.NPAY_SAPO || {};
    var statusEl   = document.getElementById("npay-status");
    var statusText = statusEl ? statusEl.querySelector(".text") : null;
    var cdEl       = document.getElementById("npay-countdown");
    var deadline   = Date.now() + (cfg.ttl || 900) * 1000;
    var stopped    = false;

    function pad(n) { return n < 10 ? "0" + n : "" + n; }
    function fmt(ms) {
        if (ms < 0) ms = 0;
        var s = Math.floor(ms / 1000);
        return pad(Math.floor(s / 60)) + ":" + pad(s % 60);
    }

    function tick() {
        if (stopped) return;
        var rem = deadline - Date.now();
        if (cdEl) cdEl.textContent = fmt(rem);
        if (rem <= 0) {
            stopped = true;
            if (statusEl) {
                statusEl.setAttribute("data-status", "expired");
                if (statusText) statusText.textContent = "Phiên thanh toán đã hết hạn.";
            }
            return;
        }
        setTimeout(tick, 500);
    }

    function poll() {
        if (stopped || !cfg.statusUrl) return;
        fetch(cfg.statusUrl, { cache: "no-store" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;
                if (data.status === "paid") {
                    stopped = true;
                    if (statusEl) {
                        statusEl.setAttribute("data-status", "paid");
                        if (statusText) statusText.textContent = "Đã nhận thanh toán. Cảm ơn quý khách!";
                    }
                } else if (data.status === "cancelled" || data.status === "expired") {
                    stopped = true;
                    if (statusEl) {
                        statusEl.setAttribute("data-status", "expired");
                        if (statusText) statusText.textContent = "Đơn hàng đã bị huỷ hoặc hết hạn.";
                    }
                }
            })
            .catch(function () { /* ignore network errors */ })
            .finally(function () {
                if (!stopped) setTimeout(poll, 4000);
            });
    }

    tick();
    poll();
})();
