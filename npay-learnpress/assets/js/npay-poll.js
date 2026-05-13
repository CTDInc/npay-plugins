/* NPay LearnPress payment poller & countdown */
(function ($) {
	'use strict';

	$(function () {
		if (typeof window.NPayLP === 'undefined') {
			return;
		}

		var cfg = window.NPayLP;
		var $status = $('#npay-lp-status');
		var $msg = $status.find('.npay-lp-status-msg');
		var $countdown = $('#npay-lp-countdown .npay-lp-countdown-time');
		var expireMin = parseInt(cfg.expireMinutes || 30, 10);
		var endTime = Date.now() + expireMin * 60 * 1000;
		var pollTimer = null;
		var countdownTimer = null;
		var stopped = false;

		function pad(n) {
			return n < 10 ? '0' + n : '' + n;
		}

		function updateCountdown() {
			var diff = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
			var m = Math.floor(diff / 60);
			var s = diff % 60;
			$countdown.text(pad(m) + ':' + pad(s));
			if (diff <= 0) {
				stop();
				$status.addClass('expired');
				$msg.text(cfg.i18n.expired);
			}
		}

		function poll() {
			$.ajax({
				url: cfg.statusUrl,
				method: 'GET',
				dataType: 'json',
				cache: false
			}).done(function (resp) {
				if (resp && resp.paid) {
					stop();
					$status.addClass('paid');
					$msg.text(cfg.i18n.paid);
					setTimeout(function () {
						if (cfg.redirectOnPaid) {
							window.location.href = cfg.redirectOnPaid;
						} else {
							window.location.reload();
						}
					}, 1500);
				}
			}).fail(function () {
				$msg.text(cfg.i18n.errorChecking);
			});
		}

		function stop() {
			if (stopped) {
				return;
			}
			stopped = true;
			if (pollTimer) {
				clearInterval(pollTimer);
			}
			if (countdownTimer) {
				clearInterval(countdownTimer);
			}
		}

		// Copy-to-clipboard.
		$(document).on('click', '.npay-lp-copy-btn', function () {
			var $btn = $(this);
			var text = $btn.data('target');
			if (!text) {
				return;
			}
			var doFallback = function () {
				var tmp = document.createElement('textarea');
				tmp.value = text;
				document.body.appendChild(tmp);
				tmp.select();
				try {
					document.execCommand('copy');
				} catch (e) {}
				document.body.removeChild(tmp);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).catch(doFallback);
			} else {
				doFallback();
			}
			var origText = $btn.text();
			$btn.addClass('copied').text('✓');
			setTimeout(function () {
				$btn.removeClass('copied').text(origText);
			}, 1200);
		});

		updateCountdown();
		countdownTimer = setInterval(updateCountdown, 1000);
		pollTimer = setInterval(poll, parseInt(cfg.pollInterval || 4000, 10));
		// First poll quickly.
		setTimeout(poll, 1500);
	});
})(window.jQuery);
