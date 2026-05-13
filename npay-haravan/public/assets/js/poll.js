(function () {
    var cfg = window.NPAY_CONFIG || {};
    if (!cfg.statusUrl) return;

    var box = document.getElementById('npay-status');
    if (box && box.dataset.status === 'paid') return;

    var interval = Math.max(2000, cfg.pollMs || 4000);

    function setPaid(data) {
        if (!box) return;
        box.classList.remove('pending');
        box.classList.add('paid');
        box.textContent = '✔ Đã nhận thanh toán. Cảm ơn quý khách!';
        box.dataset.status = 'paid';
    }

    function poll() {
        fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.status === 'paid') {
                    setPaid(data);
                } else {
                    setTimeout(poll, interval);
                }
            })
            .catch(function () { setTimeout(poll, interval); });
    }

    setTimeout(poll, interval);
})();
