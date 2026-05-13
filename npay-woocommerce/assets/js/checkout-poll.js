/**
 * NPay checkout polling - detects payment without page refresh.
 */
(function ($) {
    'use strict';

    if (typeof window.NPayCheckout === 'undefined' || !window.NPayCheckout.orderId) {
        return;
    }

    var cfg = window.NPayCheckout;
    var $status = $('.npay-status');
    var $countdown = $('.npay-countdown[data-expires]');
    var timer = null;
    var pollHandle = null;

    function poll() {
        $.post(cfg.ajaxUrl, {
            action: 'npay_check_order_status',
            order_id: cfg.orderId,
            nonce: cfg.nonce
        }).done(function (resp) {
            if (resp && resp.success && resp.data) {
                if (resp.data.is_paid || !resp.data.needs_pay) {
                    if ($status.length) {
                        $status.removeClass('pending').addClass('paid').text(cfg.i18n.paid);
                    }
                    stop();
                    setTimeout(function () {
                        window.location.reload();
                    }, 1200);
                }
            }
        });
    }

    function stop() {
        if (pollHandle) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function tickCountdown() {
        if (!$countdown.length) {
            return;
        }
        var expires = parseInt($countdown.attr('data-expires'), 10);
        if (!expires) {
            return;
        }
        var diff = expires - Math.floor(Date.now() / 1000);
        if (diff <= 0) {
            $countdown.text($countdown.attr('data-expired-text') || 'Expired');
            stop();
            return;
        }
        var mins = Math.floor(diff / 60);
        var secs = diff % 60;
        var label = $countdown.attr('data-label') || 'Time remaining';
        $countdown.text(label + ': ' + (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs);
    }

    $(document).on('click', '.npay-copy-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var value = $btn.attr('data-copy') || '';
        if (!value) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value);
        } else {
            var $tmp = $('<textarea>').val(value).appendTo('body').select();
            try { document.execCommand('copy'); } catch (err) {}
            $tmp.remove();
        }
        var original = $btn.text();
        $btn.text('✓');
        setTimeout(function () { $btn.text(original); }, 1200);
    });

    $(function () {
        pollHandle = setInterval(poll, cfg.interval || 5000);
        if ($countdown.length) {
            tickCountdown();
            timer = setInterval(tickCountdown, 1000);
        }
    });
})(jQuery);
