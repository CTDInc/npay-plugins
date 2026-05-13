(function () {
  'use strict';

  var card = document.querySelector('.card[data-ref]');
  if (!card) return;
  var refCode = card.getAttribute('data-ref');
  var statusEl = document.getElementById('status-text');
  var statusBox = document.getElementById('status-box');
  var countdownEl = document.getElementById('countdown');
  var seconds = 15;

  function setStatus(s) {
    if (!s) return;
    if (statusEl) statusEl.textContent = s;
    if (statusBox) {
      statusBox.className = 'status status-' + s;
    }
  }

  function poll() {
    fetch('/status/' + encodeURIComponent(refCode), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || d.error) return;
        setStatus(d.status);
        if (d.status === 'paid') {
          if (countdownEl) countdownEl.parentElement.style.display = 'none';
          window.clearInterval(timer);
        }
      })
      .catch(function () { /* ignore */ });
  }

  function tick() {
    seconds--;
    if (seconds <= 0) {
      seconds = 15;
      poll();
    }
    if (countdownEl) countdownEl.textContent = seconds;
  }

  var timer = window.setInterval(tick, 1000);
  poll();
})();
