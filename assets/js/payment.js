(function ($) {
	'use strict';

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				alert('Address copied');
			});
		} else {
			var $tmp = $('<textarea>').val(text).appendTo('body').select();
			document.execCommand('copy');
			$tmp.remove();
			alert('Address copied');
		}
	}

	$(document).on('click', '.acw-copy, .cfp-copy', function (e) {
		e.preventDefault();
		copyText($(this).data('copy') || $('#acw-address, #cfp-address').first().text());
	});

	function startCountdown() {
		var $box = $('.acw-payment-box, .cfp-payment-box').first();
		if (!$box.length) return;

		var expires = parseInt($box.data('expires'), 10);
		var $el = $('#acw-countdown, #cfp-countdown').first();

		function tick() {
			var left = expires - Math.floor(Date.now() / 1000);
			if (left <= 0) {
				$el.text('Expired');
				$('#acw-status, #cfp-status').first().text('Quote expired. Please place a new order.');
				return;
			}
			var m = Math.floor(left / 60);
			var s = left % 60;
			$el.text(m + ':' + (s < 10 ? '0' : '') + s);
			setTimeout(tick, 1000);
		}
		tick();
	}

	function poll() {
		var $box = $('.acw-payment-box, .cfp-payment-box').first();
		if (!$box.length) return;

		var orderId = $box.data('order');
		$.post(wc_checkout_params.ajax_url || '/wp-admin/admin-ajax.php', {
			action: 'acw_check_payment',
			order_id: orderId
		}).done(function (res) {
			if (res.success && res.data.paid) {
				$('#acw-status, #cfp-status').first().text('Payment received! Redirecting…');
				setTimeout(function () {
					window.location.reload();
				}, 1500);
			}
		});
	}

	$(function () {
		startCountdown();
		if ($('.acw-payment-box, .cfp-payment-box').length) {
			setInterval(poll, 20000);
			poll();
		}
	});
})(jQuery);
