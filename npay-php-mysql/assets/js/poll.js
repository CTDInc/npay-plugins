/* NPay payment page poller */
(function () {
  'use strict';

  var body       = document.body;
  var statusUrl  = body.getAttribute('data-status-url');
  var remaining  = parseInt(body.getAttribute('data-remaining') || '0', 10);
  var statusBox  = document.getElementById('status-box');
  var countdown  = document.getElementById('countdown');
  if (!statusUrl) return;

  var stopped = false;

  // Countdown
  if (countdown && remaining > 0) {
    var tick = function () {
      remaining -= 1;
      if (remaining <= 0) {
        countdown.textContent = '00:00';
        return;
      }
      var m = Math.floor(remaining / 60);
      var s = remaining % 60;
      countdown.textContent =
        (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      setTimeout(tick, 1000);
    };
    setTimeout(tick, 1000);
  }

  // Polling
  var poll = function () {
    if (stopped) return;
    fetch(statusUrl, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.success) return;
        if (j.status === 'paid') {
          stopped = true;
          if (statusBox) {
            statusBox.className = 'npay-card npay-status npay-status-paid';
            statusBox.innerHTML = '✅ Thanh toán thành công! Cảm ơn bạn.';
          }
          setTimeout(function () { window.location.reload(); }, 1500);
        } else if (j.status === 'cancelled') {
          stopped = true;
          if (statusBox) {
            statusBox.className = 'npay-card npay-status npay-status-cancelled';
            statusBox.innerHTML = '❌ Đơn hàng đã bị huỷ.';
          }
        }
      })
      .catch(function () { /* network error — keep trying */ });
  };

  setInterval(poll, 4000);
  poll();
})();
