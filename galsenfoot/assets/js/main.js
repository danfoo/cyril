/* GalsenFoot theme — interactions and effects. Vanilla JS, no dependencies. */
(function () {
	'use strict';
	var doc = document;
	var root = doc.documentElement;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var fx = doc.body.classList.contains('gf-fx') && !reduce;

	function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
	function $all(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }

	/* Light / dark theme */
	var themeBtn = $('[data-gf-theme]');
	if (themeBtn) {
		themeBtn.addEventListener('click', function () {
			var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
			root.setAttribute('data-theme', next);
			try { localStorage.setItem('gf-theme', next); } catch (e) {}
			var meta = $('meta[name="theme-color"]');
			if (meta) { meta.setAttribute('content', next === 'light' ? '#ffffff' : '#0a0e0c'); }
		});
	}

	/* Mobile navigation drawer and search panel */
	var nav = $('#gf-nav');
	var backdrop = $('.gf-backdrop');
	var search = $('#gf-search');
	var lastFocus = null;

	function setNav(open) {
		if (!nav) { return; }
		nav.classList.toggle('is-open', open);
		var head = doc.getElementById('gf-header');
		if (head) { head.classList.remove('is-hidden'); }
		if (backdrop) { backdrop.hidden = !open; }
		doc.body.style.overflow = open ? 'hidden' : '';
		$all('[data-gf-toggle="nav"][aria-expanded]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
		if (open) {
			lastFocus = doc.activeElement;
			var first = $('.gf-nav__head button', nav);
			if (first) { first.focus(); }
		} else if (lastFocus) {
			lastFocus.focus();
			lastFocus = null;
		}
	}
	function setSearch(open) {
		if (!search) { return; }
		search.hidden = !open;
		$all('[data-gf-toggle="search"]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
		if (open) {
			var input = $('input[type="search"]', search);
			if (input) { input.focus(); }
		}
	}
	doc.addEventListener('click', function (e) {
		var toggle = e.target.closest('[data-gf-toggle]');
		if (toggle) {
			if (toggle.getAttribute('data-gf-toggle') === 'nav') {
				setNav(!nav.classList.contains('is-open'));
			} else {
				setSearch(search.hidden);
			}
			return;
		}
		if (nav && nav.classList.contains('is-open') && e.target.closest('.gf-menu a')) { setNav(false); }

		var copy = e.target.closest('[data-gf-copy]');
		if (copy && navigator.clipboard) {
			navigator.clipboard.writeText(copy.getAttribute('data-gf-copy')).then(function () {
				copy.setAttribute('data-tip', (window.GF && GF.copied) || 'OK');
				setTimeout(function () { copy.removeAttribute('data-tip'); }, 1800);
			});
		}
		if (e.target.closest('[data-gf-top]')) {
			window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
		}
	});
	doc.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			if (nav && nav.classList.contains('is-open')) { setNav(false); }
			if (search && !search.hidden) { setSearch(false); }
		}
	});

	/* Header: shrink, hide on scroll down / show on scroll up, reading progress, back to top */
	var header = $('#gf-header');
	var sticky = header && header.classList.contains('is-sticky');
	var bar = $('.gf-progress i');
	var prose = $('[data-gf-progress]');
	var top = $('[data-gf-top]');
	var lastY = window.pageYOffset;
	var ticking = false;

	function onScroll() {
		var y = window.pageYOffset;
		if (header) {
			header.classList.toggle('is-scrolled', y > 24);
			if (sticky) {
				var navOpen = nav && nav.classList.contains('is-open');
				var searching = search && !search.hidden;
				header.classList.toggle('is-hidden', !navOpen && !searching && y > 420 && y > lastY + 4);
				if (y < lastY - 4) { header.classList.remove('is-hidden'); }
			}
		}
		if (bar) {
			var start = 0;
			var end = doc.documentElement.scrollHeight - window.innerHeight;
			if (prose) {
				var rect = prose.getBoundingClientRect();
				start = rect.top + y - 120;
				end = rect.bottom + y - window.innerHeight * 0.6;
			}
			var p = end > start ? Math.min(1, Math.max(0, (y - start) / (end - start))) : 0;
			bar.style.transform = 'scaleX(' + p + ')';
		}
		if (top) { top.hidden = y < 900; }
		lastY = y;
		ticking = false;
	}
	window.addEventListener('scroll', function () {
		if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
	}, { passive: true });
	onScroll();

	/* Reveal on scroll. Only elements below the fold are hidden, and only once this
	   script runs, so content is never invisible without JavaScript or for crawlers. */
	if (fx && 'IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.remove('is-pending');
					io.unobserve(entry.target);
				}
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
		var vh = window.innerHeight;
		$all('.gf-reveal').forEach(function (el) {
			var rect = el.getBoundingClientRect();
			if (rect.top > vh * 0.92) {
				// Stagger siblings that sit on the same row.
				var siblings = el.parentNode ? Array.prototype.indexOf.call(el.parentNode.children, el) : 0;
				el.style.setProperty('--d', Math.min(siblings % 4, 3) * 0.07 + 's');
				el.classList.add('is-pending');
				io.observe(el);
			}
		});
	}

	/* Tabs */
	$all('[data-gf-tabs]').forEach(function (tabs) {
		var buttons = $all('[role="tab"]', tabs);
		function select(btn) {
			buttons.forEach(function (b) {
				var on = b === btn;
				b.setAttribute('aria-selected', on ? 'true' : 'false');
				b.tabIndex = on ? 0 : -1;
				var panel = doc.getElementById(b.getAttribute('aria-controls'));
				if (panel) { panel.hidden = !on; }
			});
		}
		buttons.forEach(function (btn, i) {
			btn.addEventListener('click', function () { select(btn); });
			btn.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
					var next = buttons[(i + (e.key === 'ArrowRight' ? 1 : buttons.length - 1)) % buttons.length];
					select(next);
					next.focus();
				}
			});
		});
	});

	/* Lead story: subtle parallax following the pointer */
	if (fx && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
		$all('.gf-card--overlay.is-lead, .gf-feature > .gf-card--overlay').forEach(function (card) {
			var img = $('.gf-card__media img, .gf-card__media .gf-thumb-fallback', card);
			if (!img) { return; }
			card.addEventListener('pointermove', function (e) {
				var r = card.getBoundingClientRect();
				var x = (e.clientX - r.left) / r.width - 0.5;
				var yy = (e.clientY - r.top) / r.height - 0.5;
				img.style.transform = 'scale(1.08) translate(' + (-x * 14).toFixed(1) + 'px,' + (-yy * 14).toFixed(1) + 'px)';
			});
			card.addEventListener('pointerleave', function () { img.style.transform = ''; });
		});
	}

	/* Score ticker: start on the first upcoming match, drag to scroll with the mouse */
	$all('.gsf-ticker__track').forEach(function (track) {
		var next = $('.gsf-ticker__item.is-scheduled, .gsf-ticker__item.is-live', track);
		if (next) { track.scrollLeft = Math.max(0, next.offsetLeft - track.offsetLeft - track.clientWidth * 0.45); }
		var down = false, startX = 0, startLeft = 0, moved = false;
		track.addEventListener('pointerdown', function (e) {
			if (e.pointerType !== 'mouse') { return; }
			down = true; moved = false; startX = e.clientX; startLeft = track.scrollLeft;
		});
		window.addEventListener('pointermove', function (e) {
			if (!down) { return; }
			var dx = e.clientX - startX;
			if (Math.abs(dx) > 5) { moved = true; }
			track.scrollLeft = startLeft - dx;
		});
		window.addEventListener('pointerup', function () { down = false; });
		track.addEventListener('click', function (e) { if (moved) { e.preventDefault(); moved = false; } }, true);
		track.addEventListener('dragstart', function (e) { e.preventDefault(); });
	});
})();
