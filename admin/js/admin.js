(function () {
	'use strict';

	function copyField(text, btn) {
		if (!navigator.clipboard || !text) {
			return;
		}

		navigator.clipboard.writeText(text).then(function () {
			var orig = btn.textContent;
			var copied =
				(typeof oliImportSyncTrp !== 'undefined' && oliImportSyncTrp.copied) ||
				btn.getAttribute('data-copied') ||
				'Copied!';
			btn.textContent = copied;
			btn.classList.add('is-copied');
			setTimeout(function () {
				btn.textContent = orig;
				btn.classList.remove('is-copied');
			}, 1200);
		});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.oli-import-sync-trp-copy');
		if (btn && btn.dataset.v) {
			e.preventDefault();
			copyField(btn.dataset.v, btn);
		}
	});

	var notice = document.querySelector('.oli-import-sync-trp-donate-notice');
	if (notice && typeof oliImportSyncTrp !== 'undefined') {
		notice.addEventListener('click', function (e) {
			if (!e.target.classList.contains('notice-dismiss')) {
				return;
			}
			var body = new FormData();
			body.append('action', 'oli_import_sync_trp_dismiss_donate');
			body.append('nonce', oliImportSyncTrp.donateNonce || '');
			if (window.fetch) {
				fetch(oliImportSyncTrp.ajaxUrl, {
					method: 'POST',
					body: body,
					credentials: 'same-origin',
				});
			}
		});
	}

	var clearBtn = document.querySelector('.oli-import-sync-trp-clear-log');
	if (clearBtn) {
		clearBtn.addEventListener('click', function (e) {
			var msg =
				typeof oliImportSyncTrp !== 'undefined' && oliImportSyncTrp.clearLogConfirm
					? oliImportSyncTrp.clearLogConfirm
					: '';
			if (msg && !window.confirm(msg)) {
				e.preventDefault();
			}
		});
	}
})();
