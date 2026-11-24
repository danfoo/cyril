/* Wedding RSVP Pass – guest book form/wall + wishes carousel */
(function () {
	'use strict';
	var C = window.WRP_GB || {};
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function card(w) {
		var el = document.createElement('article');
		el.className = 'wrp-wish';
		el.innerHTML = '<span class="wrp-wish__quote" aria-hidden="true">“</span><p class="wrp-wish__msg">' + esc(w.message).replace(/\n/g, '<br>') + '</p><footer><strong class="wrp-wish__name">' + esc(w.name) + '</strong>' + (w.relation ? '<span class="wrp-wish__rel">' + esc(w.relation) + '</span>' : '') + '<time>' + esc(w.date) + '</time></footer>';
		return el;
	}
	function hearts(el) {
		if (reduce) { return; }
		var r = el.getBoundingClientRect();
		for (var i = 0; i < 14; i++) {
			var h = document.createElement('span');
			h.textContent = i % 3 ? '♥' : '✦';
			h.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;left:' + (r.left + r.width / 2) + 'px;top:' + (r.top + r.height / 2) + 'px;font-size:' + (12 + Math.random() * 16) + 'px;color:' + ['#e48b98', '#d4a85f', '#f2b8c0'][i % 3] + ';transition:transform 1.2s cubic-bezier(.2,.8,.2,1),opacity 1.2s';
			document.body.appendChild(h);
			(function (h) {
				requestAnimationFrame(function () {
					var a = Math.random() * Math.PI * 2, d = 60 + Math.random() * 140;
					h.style.transform = 'translate(' + Math.cos(a) * d + 'px,' + (Math.sin(a) * d - 60) + 'px) scale(1.2)';
					h.style.opacity = '0';
				});
				setTimeout(function () { h.remove(); }, 1300);
			})(h);
		}
	}

	/* ---------- Guest book ---------- */
	$$('[data-wrp-gb]').forEach(function (gb) {
		var form = $('[data-wrp-gb-form]', gb);
		var wall = $('[data-wrp-gb-wall]', gb);
		var msg = $('[data-wrp-gb-msg]', gb);
		var count = $('[data-wrp-gb-count]', gb);
		var started = Date.now();
		var ta = form.message;
		var updateCount = function () { count.textContent = (1500 - ta.value.length) + ' ' + (C.i18n ? C.i18n.chars : ''); };
		ta.addEventListener('input', updateCount);
		updateCount();

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = $('button[type=submit]', form);
			msg.hidden = true;
			msg.classList.remove('is-error');
			if (!form.name.value.trim() || ta.value.trim().length < 2) {
				(form.name.value.trim() ? ta : form.name).focus();
				return;
			}
			btn.disabled = true;
			fetch(C.api, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'omit',
				body: JSON.stringify({ name: form.name.value, relation: form.relation.value, message: ta.value, website: form.website.value, t: started })
			}).then(function (r) {
				return r.json().then(function (d) { if (!r.ok) { throw new Error(d && d.message ? d.message : C.i18n.error); } return d; });
			}).then(function (d) {
				hearts(btn);
				msg.textContent = C.i18n.thanks + ' ' + (d.status === 'approved' ? C.i18n.published : C.i18n.pending);
				msg.hidden = false;
				if (d.wish) {
					var el = card(d.wish);
					el.classList.add('is-new');
					wall.insertBefore(el, wall.firstChild);
					var empty = $('[data-wrp-gb-empty]', gb);
					if (empty) { empty.hidden = true; }
				}
				ta.value = '';
				updateCount();
				started = Date.now();
			}).catch(function (err) {
				msg.textContent = err.message || C.i18n.error;
				msg.classList.add('is-error');
				msg.hidden = false;
			}).then(function () { btn.disabled = false; });
		});

		var more = $('[data-wrp-gb-more]', gb);
		if (more) {
			var perPage = +gb.getAttribute('data-per-page') || 12;
			more.addEventListener('click', function () {
				more.disabled = true;
				var offset = $$('.wrp-wish', wall).length;
				fetch(C.api + (C.api.indexOf('?') > -1 ? '&' : '?') + 'per_page=' + perPage + '&offset=' + offset)
					.then(function (r) { return r.json(); })
					.then(function (d) {
						(d.items || []).forEach(function (w) { wall.appendChild(card(w)); });
						if ($$('.wrp-wish', wall).length >= d.total) { more.parentNode.hidden = true; }
					}).catch(function () {}).then(function () { more.disabled = false; });
			});
		}
	});

	/* ---------- Carousel ---------- */
	$$('[data-wrp-carousel]').forEach(function (car) {
		var track = $('[data-track]', car);
		if (!track) { return; }
		var cards = $$('.wrp-wish', track);
		var dotsBox = $('[data-dots]', car);
		var step = function () {
			var c = cards[0];
			return c ? c.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || 20) : track.clientWidth;
		};
		var pages = function () { return Math.max(1, Math.round((track.scrollWidth - track.clientWidth) / step()) + 1); };
		var current = function () { return Math.round(track.scrollLeft / step()); };
		var goTo = function (i) {
			var n = pages();
			i = (i + n) % n;
			track.scrollTo({ left: i * step(), behavior: reduce ? 'auto' : 'smooth' });
		};
		var drawDots = function () {
			var n = pages();
			dotsBox.innerHTML = '';
			if (n < 2) { return; }
			for (var i = 0; i < n; i++) {
				var b = document.createElement('button');
				b.type = 'button';
				b.setAttribute('aria-label', 'Vœu ' + (i + 1));
				(function (i) { b.addEventListener('click', function () { goTo(i); restart(); }); })(i);
				dotsBox.appendChild(b);
			}
			sync();
		};
		var sync = function () {
			var i = current();
			$$('button', dotsBox).forEach(function (b, k) { b.classList.toggle('is-active', k === i); });
		};
		var prev = $('[data-prev]', car), next = $('[data-next]', car);
		if (prev) { prev.addEventListener('click', function () { goTo(current() - 1); restart(); }); }
		if (next) { next.addEventListener('click', function () { goTo(current() + 1); restart(); }); }
		var raf;
		track.addEventListener('scroll', function () { cancelAnimationFrame(raf); raf = requestAnimationFrame(sync); }, { passive: true });
		track.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { goTo(current() + 1); restart(); }
			if (e.key === 'ArrowLeft') { goTo(current() - 1); restart(); }
		});

		// Autoplay (paused on hover / touch / when off-screen).
		var timer = null, paused = false, visible = true;
		var tick = function () { if (!paused && visible && !document.hidden) { goTo(current() + 1); } };
		var restart = function () { clearInterval(timer); if (!reduce && pages() > 1) { timer = setInterval(tick, 5500); } };
		car.addEventListener('mouseenter', function () { paused = true; });
		car.addEventListener('mouseleave', function () { paused = false; });
		track.addEventListener('touchstart', function () { paused = true; }, { passive: true });
		track.addEventListener('touchend', function () { setTimeout(function () { paused = false; }, 4000); }, { passive: true });
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) { visible = en[0].isIntersecting; }).observe(car);
		}
		window.addEventListener('resize', function () { drawDots(); });
		drawDots();
		restart();
	});
})();
