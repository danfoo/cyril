/* Wedding RSVP Pass – RSVP page & invitation card */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var css = getComputedStyle(document.documentElement);
	var ACCENT = (css.getPropertyValue('--wrp-accent') || '#b8925a').trim();
	var DARK = (css.getPropertyValue('--wrp-dark') || '#3b2f2a').trim();
	var BLUSH = (css.getPropertyValue('--wrp-blush') || '#f4e1dc').trim();

	/* ------------------------------------------------------------------
	 * Falling petals
	 * ---------------------------------------------------------------- */
	function petals(canvas, count) {
		if (!canvas || reduceMotion) { return; }
		var ctx = canvas.getContext('2d');
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var W, H, items = [];
		var colors = ['#f6c6cb', '#f2b5bb', '#fbe1e3', '#efd3b5', '#ffffff'];
		function resize() {
			W = canvas.clientWidth; H = canvas.clientHeight;
			canvas.width = W * dpr; canvas.height = H * dpr;
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		}
		function make(initial) {
			return {
				x: Math.random() * W,
				y: initial ? Math.random() * H : -30,
				s: 7 + Math.random() * 9,
				vy: 0.4 + Math.random() * 0.9,
				vx: -0.3 + Math.random() * 0.6,
				r: Math.random() * Math.PI * 2,
				vr: -0.02 + Math.random() * 0.04,
				sw: Math.random() * Math.PI * 2,
				flip: Math.random() * Math.PI,
				c: colors[Math.floor(Math.random() * colors.length)],
				a: 0.55 + Math.random() * 0.4
			};
		}
		resize();
		for (var i = 0; i < count; i++) { items.push(make(true)); }
		window.addEventListener('resize', resize);
		var running = true;
		document.addEventListener('visibilitychange', function () { running = !document.hidden; if (running) { requestAnimationFrame(tick); } });
		function draw(p) {
			ctx.save();
			ctx.translate(p.x, p.y);
			ctx.rotate(p.r);
			ctx.scale(1, Math.abs(Math.cos(p.flip)) * 0.7 + 0.3);
			ctx.globalAlpha = p.a;
			var g = ctx.createLinearGradient(-p.s, -p.s, p.s, p.s);
			g.addColorStop(0, '#ffffff');
			g.addColorStop(1, p.c);
			ctx.fillStyle = g;
			ctx.beginPath();
			ctx.moveTo(0, -p.s);
			ctx.bezierCurveTo(p.s, -p.s * 0.8, p.s * 0.9, p.s * 0.6, 0, p.s);
			ctx.bezierCurveTo(-p.s * 0.9, p.s * 0.6, -p.s, -p.s * 0.8, 0, -p.s);
			ctx.fill();
			ctx.restore();
		}
		function tick() {
			if (!running) { return; }
			ctx.clearRect(0, 0, W, H);
			for (var i = 0; i < items.length; i++) {
				var p = items[i];
				p.sw += 0.02; p.flip += 0.03;
				p.y += p.vy; p.x += p.vx + Math.sin(p.sw) * 0.6; p.r += p.vr;
				if (p.y > H + 30 || p.x < -40 || p.x > W + 40) { items[i] = make(false); }
				draw(p);
			}
			requestAnimationFrame(tick);
		}
		requestAnimationFrame(tick);
	}

	/* ------------------------------------------------------------------
	 * Confetti / hearts burst
	 * ---------------------------------------------------------------- */
	function confetti() {
		if (reduceMotion) { return; }
		var c = document.createElement('canvas');
		c.className = 'wrp-confetti';
		document.body.appendChild(c);
		var ctx = c.getContext('2d');
		var W = c.width = window.innerWidth, H = c.height = window.innerHeight;
		var parts = [];
		var palette = [ACCENT, BLUSH, '#e8a3a8', '#ffffff', '#d4b483', DARK];
		for (var i = 0; i < 160; i++) {
			parts.push({
				x: W / 2 + (Math.random() - 0.5) * 120, y: H * 0.35,
				vx: (Math.random() - 0.5) * 16, vy: -Math.random() * 15 - 4,
				s: 5 + Math.random() * 7, r: Math.random() * 6, vr: (Math.random() - 0.5) * 0.3,
				c: palette[i % palette.length], heart: Math.random() < 0.3, life: 0
			});
		}
		function heart(s) {
			ctx.beginPath();
			ctx.moveTo(0, s * 0.3);
			ctx.bezierCurveTo(-s, -s * 0.4, -s * 0.4, -s, 0, -s * 0.4);
			ctx.bezierCurveTo(s * 0.4, -s, s, -s * 0.4, 0, s * 0.3);
			ctx.fill();
		}
		var frames = 0;
		(function loop() {
			ctx.clearRect(0, 0, W, H);
			parts.forEach(function (p) {
				p.vy += 0.35; p.vx *= 0.985; p.x += p.vx; p.y += p.vy; p.r += p.vr;
				ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c;
				if (p.heart) { heart(p.s * 1.4); } else { ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); }
				ctx.restore();
			});
			if (++frames < 220) { requestAnimationFrame(loop); } else { c.remove(); }
		})();
	}

	petals(document.querySelector('.wrp-petals'), window.innerWidth < 600 ? 14 : 26);

	/* ------------------------------------------------------------------
	 * RSVP form
	 * ---------------------------------------------------------------- */
	var form = document.querySelector('[data-wrp-form]');
	if (form) {
		var yes = form.querySelector('[data-wrp-yes]');
		var submit = form.querySelector('[data-wrp-submit]');
		var seatsInput = form.querySelector('input[name=wrp_seats][type=number]');

		var sync = function () {
			var checked = form.querySelector('input[name=wrp_answer]:checked');
			submit.disabled = !checked;
			if (yes) { yes.hidden = !(checked && checked.value === 'yes'); }
			if (seatsInput) {
				var n = Math.max(1, Math.min(+seatsInput.max, parseInt(seatsInput.value, 10) || 1));
				seatsInput.value = n;
				form.querySelectorAll('[data-wrp-companions] .wrp-field').forEach(function (f) {
					var show = +f.getAttribute('data-index') < n - 1;
					f.hidden = !show;
					f.querySelector('input').disabled = !show;
				});
			}
		};
		form.addEventListener('change', sync);
		form.addEventListener('input', function (e) { if (e.target === seatsInput) { sync(); } });
		form.querySelectorAll('[data-step]').forEach(function (b) {
			b.addEventListener('click', function () {
				seatsInput.value = (parseInt(seatsInput.value, 10) || 1) + (+b.getAttribute('data-step'));
				sync();
			});
		});
		form.querySelectorAll('input[name=wrp_answer]').forEach(function (r) {
			r.addEventListener('change', function () {
				if (r.value === 'yes' && r.checked && !reduceMotion) { miniHearts(r.closest('.wrp-choice')); }
			});
		});
		form.addEventListener('submit', function () {
			submit.classList.add('is-loading');
			setTimeout(function () { submit.disabled = true; }, 0);
		});
		document.querySelectorAll('[data-wrp-edit]').forEach(function (b) {
			b.addEventListener('click', function () {
				form.hidden = false;
				b.closest('.wrp-state').hidden = true;
				form.scrollIntoView({ behavior: 'smooth', block: 'start' });
				sync();
			});
		});
		sync();
	}

	function miniHearts(el) {
		var rect = el.getBoundingClientRect();
		for (var i = 0; i < 8; i++) {
			var h = document.createElement('span');
			h.textContent = '♥';
			h.style.cssText = 'position:fixed;z-index:50;pointer-events:none;color:' + (i % 2 ? ACCENT : '#e8a3a8') + ';font-size:' + (12 + Math.random() * 14) + 'px;left:' + (rect.left + rect.width / 2) + 'px;top:' + (rect.top + rect.height / 2) + 'px;transition:transform 1.1s cubic-bezier(.2,.8,.2,1),opacity 1.1s';
			document.body.appendChild(h);
			(function (h) {
				requestAnimationFrame(function () {
					h.style.transform = 'translate(' + ((Math.random() - 0.5) * 160) + 'px,' + (-60 - Math.random() * 90) + 'px) scale(1.3)';
					h.style.opacity = '0';
				});
				setTimeout(function () { h.remove(); }, 1200);
			})(h);
		}
	}

	/* ------------------------------------------------------------------
	 * Invitation card
	 * ---------------------------------------------------------------- */
	var card = window.WRP_CARD;
	if (!card) { return; }

	function makeQR(text) {
		var qr = window.qrcode(0, 'Q');
		qr.addData(text);
		qr.make();
		return qr;
	}

	var qrbox = document.querySelector('[data-wrp-qr]');
	var qr = null;
	function initQR() {
		if (typeof window.qrcode !== 'function') { return; }
		qr = makeQR(card.qr);
		if (qrbox) {
			qrbox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
			var path = qrbox.querySelector('path');
			if (path) { path.setAttribute('fill', DARK); }
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initQR);
	} else {
		initQR();
	}

	if (document.body.classList.contains('is-welcome')) {
		setTimeout(confetti, 500);
	}

	// Subtle 3D tilt.
	var invite = document.getElementById('wrp-invite');
	if (invite && !reduceMotion && window.matchMedia('(hover: hover)').matches) {
		invite.addEventListener('pointermove', function (e) {
			var r = invite.getBoundingClientRect();
			var x = (e.clientX - r.left) / r.width - 0.5;
			var y = (e.clientY - r.top) / r.height - 0.5;
			invite.style.transform = 'rotateY(' + (x * 8) + 'deg) rotateX(' + (-y * 8) + 'deg)';
		});
		invite.addEventListener('pointerleave', function () { invite.style.transform = ''; });
	}

	/* ---------- Calendar (.ics) ---------- */
	var icsBtn = document.querySelector('[data-wrp-ics]');
	if (icsBtn) {
		icsBtn.addEventListener('click', function () {
			var esc = function (s) { return String(s || '').replace(/[\\;,]/g, '\\$&').replace(/\n/g, '\\n'); };
			var ics = [
				'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Wedding RSVP Pass//FR', 'BEGIN:VEVENT',
				'UID:' + card.qr.replace(/\W/g, '') + '@wedding',
				'DTSTAMP:' + new Date().toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z',
				'DTSTART:' + card.start, 'DTEND:' + card.end,
				'SUMMARY:' + esc(card.labels.calTitle),
				'LOCATION:' + esc([card.venue, card.address].filter(Boolean).join(', ')),
				'DESCRIPTION:' + esc(card.guest + ' – ' + card.code),
				'BEGIN:VALARM', 'TRIGGER:-P1D', 'ACTION:DISPLAY', 'DESCRIPTION:' + esc(card.labels.calTitle), 'END:VALARM',
				'END:VEVENT', 'END:VCALENDAR'
			].join('\r\n');
			download(new Blob([ics], { type: 'text/calendar;charset=utf-8' }), 'mariage.ics');
		});
	}

	function download(blob, name) {
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = name;
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
	}

	/* ---------- Card as PNG (drawn on canvas) ---------- */
	var dlBtn = document.querySelector('[data-wrp-download]');
	if (dlBtn) {
		dlBtn.addEventListener('click', function () {
			dlBtn.disabled = true;
			var fontsReady = document.fonts && document.fonts.load ? Promise.all([
				document.fonts.load('80px "Great Vibes"'),
				document.fonts.load('italic 30px "Cormorant Garamond"'),
				document.fonts.load('600 30px "Cormorant Garamond"'),
				document.fonts.load('500 20px Montserrat')
			]).catch(function () {}) : Promise.resolve();
			fontsReady.then(function () {
				var c = drawCard();
				c.toBlob(function (blob) {
					download(blob, (card.file || 'invitation') + '.png');
					dlBtn.disabled = false;
				}, 'image/png');
			});
		});
	}

	function drawCard() {
		var W = 1000, H = 1480;
		var c = document.createElement('canvas');
		c.width = W; c.height = H;
		var x = c.getContext('2d');
		var cx = W / 2;
		var serif = '"Cormorant Garamond", Georgia, serif';
		var script = '"Great Vibes", "Segoe Script", cursive';
		var sans = 'Montserrat, Arial, sans-serif';

		// Background.
		x.fillStyle = '#fffdf9';
		x.fillRect(0, 0, W, H);
		var g = x.createRadialGradient(cx, 0, 10, cx, 0, 760);
		g.addColorStop(0, hexA(card.blush, 0.9));
		g.addColorStop(1, hexA(card.blush, 0));
		x.fillStyle = g;
		x.fillRect(0, 0, W, H);

		// Frames.
		x.strokeStyle = card.accent;
		x.lineWidth = 2;
		x.strokeRect(26, 26, W - 52, H - 52);
		x.lineWidth = 1;
		x.globalAlpha = 0.55;
		x.strokeRect(38, 38, W - 76, H - 76);
		x.globalAlpha = 1;
		corner(x, 26, 26, 1, 1); corner(x, W - 26, 26, -1, 1); corner(x, 26, H - 26, 1, -1); corner(x, W - 26, H - 26, -1, -1);

		x.textAlign = 'center';
		x.textBaseline = 'alphabetic';

		var y = 150;
		x.fillStyle = card.accent;
		spaced(x, 'INVITATION', cx, y, '500 20px ' + sans, 10);

		y += 120;
		x.fillStyle = card.dark;
		x.font = '400 112px ' + script;
		var namesLine = card.a + '  &  ' + card.b;
		fitFont(x, namesLine, W - 160, 112, script, '400');
		x.fillText(namesLine, cx, y);

		y += 60;
		x.fillStyle = '#6f5f55';
		y = wrap(x, card.inviteLine, cx, y, W - 260, 36, 'italic 400 30px ' + serif);

		y += 20;
		ornament(x, cx, y);
		y += 70;
		x.fillStyle = card.dark;
		x.font = '600 44px ' + serif;
		fitFont(x, card.date.toUpperCase(), W - 180, 44, serif, '600');
		x.fillText(card.date.toUpperCase(), cx, y);
		y += 46;
		x.font = '400 32px ' + serif;
		x.fillText('à ' + card.time, cx, y);
		y += 46;
		x.font = 'italic 400 32px ' + serif;
		x.fillText(card.venue, cx, y);
		if (card.address) {
			y += 34;
			x.fillStyle = '#8a7a70';
			x.font = '400 20px ' + sans;
			x.fillText(card.address, cx, y);
		}

		// Guest block.
		y += 60;
		x.strokeStyle = hexA(card.accent, 0.5);
		x.lineWidth = 1.5;
		line(x, cx - 300, y, cx + 300, y);
		y += 40;
		x.fillStyle = card.accent;
		spaced(x, card.labels.nominative.toUpperCase(), cx, y, '500 17px ' + sans, 7);
		y += 66;
		x.fillStyle = card.dark;
		x.font = '400 64px ' + script;
		fitFont(x, card.guest, W - 220, 64, script, '400');
		x.fillText(card.guest, cx, y);
		if (card.companions && card.companions.length) {
			y += 40;
			x.font = 'italic 400 28px ' + serif;
			var comp = '& ' + card.companions.join(', ');
			fitFont(x, comp, W - 220, 28, serif, 'italic 400');
			x.fillText(comp, cx, y);
		}
		y += 38;
		x.fillStyle = '#6f5f55';
		x.font = '400 20px ' + sans;
		var seats = card.labels.seats.replace('%d', card.seats) + (card.table ? '  ·  ' + card.labels.table + ' : ' + card.table : '');
		x.fillText(seats, cx, y);
		y += 30;
		line(x, cx - 300, y, cx + 300, y);

		// QR.
		// QR block centred in the space left above the quote.
		var bottom = card.quote ? H - 110 : H - 70;
		var size = Math.max(200, Math.min(330, bottom - y - 130));
		var qy = y + Math.max(30, (bottom - y - size - 70) / 2);
		x.fillStyle = '#ffffff';
		roundRect(x, cx - size / 2 - 16, qy - 16, size + 32, size + 32, 16);
		x.fill();
		x.strokeStyle = hexA(card.accent, 0.6);
		x.lineWidth = 1.5;
		x.stroke();
		var q = qr || makeQR(card.qr);
		var n = q.getModuleCount();
		var cell = size / n;
		x.fillStyle = card.dark;
		for (var r = 0; r < n; r++) {
			for (var col = 0; col < n; col++) {
				if (q.isDark(r, col)) {
					x.fillRect(Math.floor(cx - size / 2 + col * cell), Math.floor(qy + r * cell), Math.ceil(cell), Math.ceil(cell));
				}
			}
		}
		x.fillStyle = card.dark;
		spaced(x, card.code, cx, qy + size + 56, '700 26px "Courier New", monospace', 6);

		if (card.quote) {
			x.fillStyle = '#8a7a70';
			x.font = 'italic 400 24px ' + serif;
			x.fillText('« ' + card.quote + ' »', cx, H - 70);
		}
		return c;
	}

	function hexA(hex, a) {
		var h = String(hex).replace('#', '');
		if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
		var n = parseInt(h, 16);
		return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
	}
	function line(x, x1, y1, x2, y2) { x.beginPath(); x.moveTo(x1, y1); x.lineTo(x2, y2); x.stroke(); }
	function roundRect(x, X, Y, w, h, r) {
		x.beginPath();
		x.moveTo(X + r, Y); x.arcTo(X + w, Y, X + w, Y + h, r); x.arcTo(X + w, Y + h, X, Y + h, r);
		x.arcTo(X, Y + h, X, Y, r); x.arcTo(X, Y, X + w, Y, r); x.closePath();
	}
	function spaced(x, text, cx, y, font, spacing) {
		x.font = font;
		var chars = String(text).split('');
		var widths = chars.map(function (ch) { return x.measureText(ch).width; });
		var total = widths.reduce(function (a, b) { return a + b; }, 0) + spacing * (chars.length - 1);
		var px = cx - total / 2;
		var align = x.textAlign;
		x.textAlign = 'left';
		chars.forEach(function (ch, i) { x.fillText(ch, px, y); px += widths[i] + spacing; });
		x.textAlign = align;
	}
	function fitFont(x, text, maxW, size, family, weight) {
		var s = size;
		x.font = weight + ' ' + s + 'px ' + family;
		while (x.measureText(text).width > maxW && s > 12) {
			s -= 2;
			x.font = weight + ' ' + s + 'px ' + family;
		}
	}
	function wrap(x, text, cx, y, maxW, lh, font) {
		x.font = font;
		var words = String(text || '').split(/\s+/), lineTxt = '';
		for (var i = 0; i < words.length; i++) {
			var test = lineTxt ? lineTxt + ' ' + words[i] : words[i];
			if (x.measureText(test).width > maxW && lineTxt) {
				x.fillText(lineTxt, cx, y); y += lh; lineTxt = words[i];
			} else { lineTxt = test; }
		}
		if (lineTxt) { x.fillText(lineTxt, cx, y); }
		return y;
	}
	function ornament(x, cx, y) {
		x.save();
		x.strokeStyle = card.accent;
		x.fillStyle = card.accent;
		x.lineWidth = 1.5;
		line(x, cx - 170, y, cx - 40, y);
		line(x, cx + 40, y, cx + 170, y);
		x.beginPath(); x.arc(cx - 34, y, 3, 0, Math.PI * 2); x.fill();
		x.beginPath(); x.arc(cx + 34, y, 3, 0, Math.PI * 2); x.fill();
		x.translate(cx, y + 2);
		x.beginPath();
		x.moveTo(0, 10);
		x.bezierCurveTo(-22, -4, -12, -22, 0, -10);
		x.bezierCurveTo(12, -22, 22, -4, 0, 10);
		x.fill();
		x.restore();
	}
	function corner(x, X, Y, sx, sy) {
		x.save();
		x.translate(X, Y);
		x.scale(sx, sy);
		x.strokeStyle = card.accent;
		x.lineWidth = 1.5;
		x.beginPath();
		x.moveTo(30, 30); x.bezierCurveTo(50, 8, 80, 10, 92, 24); x.bezierCurveTo(74, 30, 52, 36, 30, 30);
		x.moveTo(30, 30); x.bezierCurveTo(8, 50, 10, 80, 24, 92); x.bezierCurveTo(30, 74, 36, 52, 30, 30);
		x.stroke();
		x.fillStyle = card.accent;
		x.beginPath(); x.arc(30, 30, 4, 0, Math.PI * 2); x.fill();
		x.restore();
	}
})();
