/* Wedding RSVP Pass – admin interactions */
(function () {
	'use strict';
	var cfg = window.WRP_ADMIN || {};

	function toast(msg) {
		var t = document.createElement('div');
		t.className = 'wrp-toast';
		t.textContent = msg;
		document.body.appendChild(t);
		requestAnimationFrame(function () { t.classList.add('is-on'); });
		setTimeout(function () { t.classList.remove('is-on'); setTimeout(function () { t.remove(); }, 400); }, 1800);
	}

	function post(action, data) {
		var body = new URLSearchParams(Object.assign({ action: action, nonce: cfg.nonce }, data || {}));
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); });
	}

	function copy(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		ta.remove();
		return Promise.resolve();
	}

	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-wrp-copy],[data-wrp-mark],[data-wrp-confirm]');
		if (!el) { return; }

		var confirmType = el.getAttribute('data-wrp-confirm');
		if (confirmType) {
			var msg = confirmType === 'del' ? cfg.confirmDel : confirmType === 'regen' ? cfg.confirmRegen : '?';
			if (!window.confirm(msg)) { e.preventDefault(); return; }
		}

		var link = el.getAttribute('data-wrp-copy');
		if (link) {
			e.preventDefault();
			copy(link).then(function () { toast(cfg.copied); });
		}

		var id = el.getAttribute('data-wrp-mark');
		if (id) {
			// Sending through WhatsApp / SMS / copied link → the guest is now "invited".
			post('wrp_mark_invited', { id: id }).then(function (res) {
				if (res && res.success) {
					el.classList.add('is-done');
					var row = el.closest('tr');
					var badge = row && row.querySelector('.wrp-badge--pending');
					if (badge) {
						badge.className = 'wrp-badge wrp-badge--invited';
						badge.textContent = 'Invité·e – en attente';
					}
				}
			});
		}
	});

	// Select all.
	var all = document.getElementById('wrp-check-all');
	if (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; });
		});
	}
	var bulk = document.getElementById('wrp-bulk-form');
	if (bulk) {
		bulk.addEventListener('submit', function (e) {
			var act = bulk.querySelector('[name=bulk_action]').value;
			if (!bulk.querySelector('input[name="ids[]"]:checked') || !act) { e.preventDefault(); return; }
			if (act === 'delete' && !window.confirm(cfg.confirmBulkDel)) { e.preventDefault(); }
		});
	}

	// QR codes.
	function renderQR(el) {
		if (typeof window.qrcode !== 'function') { return; }
		var qr = window.qrcode(0, 'M');
		qr.addData(el.getAttribute('data-wrp-qr'));
		qr.make();
		el.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
	}
	document.querySelectorAll('[data-wrp-qr]').forEach(function (el) {
		var details = el.closest('details');
		if (details && !details.open) {
			details.addEventListener('toggle', function once() { renderQR(el); details.removeEventListener('toggle', once); });
		} else {
			renderQR(el);
		}
	});

	// Live arrivals on the wedding-day page.
	var list = document.querySelector('[data-wrp-arrivals]');
	if (list) {
		var seen = {};
		var first = true;
		var refresh = function () {
			post('wrp_live_stats').then(function (res) {
				if (!res || !res.success) { return; }
				var s = res.data.stats;
				var inEl = document.querySelector('[data-live=in]');
				var exEl = document.querySelector('[data-live=expected]');
				if (inEl) { inEl.textContent = s.seats_checked_in; }
				if (exEl) { exEl.textContent = s.seats_confirmed; }
				var ring = document.querySelector('.wrp-ring__fg');
				if (ring) { ring.style.setProperty('--p', s.seats_confirmed ? Math.round(s.seats_checked_in / s.seats_confirmed * 100) : 0); }
				list.innerHTML = '';
				if (!res.data.arrivals.length) {
					var li0 = document.createElement('li');
					li0.className = 'wrp-muted';
					li0.textContent = 'Aucune arrivée synchronisée pour le moment.';
					list.appendChild(li0);
				}
				res.data.arrivals.forEach(function (a) {
					var key = a.name + a.time;
					var li = document.createElement('li');
					if (!first && !seen[key]) { li.className = 'is-new'; }
					seen[key] = 1;
					var b = document.createElement('b');
					b.textContent = a.name + (a.seats > 1 ? ' (' + a.seats + ')' : '');
					var em = document.createElement('em');
					em.textContent = (a.table ? a.table + ' · ' : '') + a.time;
					li.appendChild(b);
					li.appendChild(em);
					list.appendChild(li);
				});
				first = false;
			});
		};
		refresh();
		setInterval(refresh, 15000);
	}
})();
