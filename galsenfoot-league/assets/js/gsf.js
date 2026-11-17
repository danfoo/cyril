/* GalsenFoot League — countdowns, animated counters, live score refresh. No dependencies. */
(function () {
	'use strict';
	var cfg = window.GSF || { rest: '', i18n: { d: 'j', h: 'h', m: 'min', s: 's' } };
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function pad(n) { return n < 10 ? '0' + n : '' + n; }

	/* Countdown to kick-off */
	function initCountdowns(root) {
		var nodes = (root || document).querySelectorAll('[data-gsf-countdown]:not([data-gsf-ready])');
		Array.prototype.forEach.call(nodes, function (el) {
			el.setAttribute('data-gsf-ready', '1');
			var target = parseInt(el.getAttribute('data-gsf-countdown'), 10) * 1000;
			var parts = ['d', 'h', 'm', 's'].map(function (key) {
				var box = document.createElement('span');
				var value = document.createTextNode('00');
				var label = document.createElement('small');
				label.textContent = cfg.i18n[key];
				box.appendChild(value);
				box.appendChild(label);
				el.appendChild(box);
				return value;
			});
			function tick() {
				var left = Math.max(0, Math.floor((target - Date.now()) / 1000));
				parts[0].nodeValue = pad(Math.floor(left / 86400));
				parts[1].nodeValue = pad(Math.floor(left % 86400 / 3600));
				parts[2].nodeValue = pad(Math.floor(left % 3600 / 60));
				parts[3].nodeValue = pad(left % 60);
				if (left <= 0) { clearInterval(timer); }
			}
			var timer = setInterval(tick, 1000);
			tick();
			el.removeAttribute('aria-hidden');
		});
	}

	/* Numbers counting up when they enter the viewport */
	function initCounters(root) {
		var nodes = (root || document).querySelectorAll('[data-gsf-count]:not([data-gsf-ready])');
		if (!nodes.length) { return; }
		function run(el) {
			var end = parseInt(el.getAttribute('data-gsf-count'), 10) || 0;
			if (reduce || end < 2) { return; }
			var start = null;
			var duration = Math.min(1400, 500 + end * 40);
			function frame(now) {
				if (!start) { start = now; }
				var p = Math.min(1, (now - start) / duration);
				el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
				if (p < 1) { requestAnimationFrame(frame); }
			}
			el.textContent = '0';
			requestAnimationFrame(frame);
		}
		if (!('IntersectionObserver' in window)) { return; }
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { io.unobserve(entry.target); run(entry.target); }
			});
		}, { threshold: 0.6 });
		Array.prototype.forEach.call(nodes, function (el) {
			el.setAttribute('data-gsf-ready', '1');
			io.observe(el);
		});
	}

	/* Refresh the scores of live matches every 45 s */
	function initLive() {
		if (!cfg.rest || !document.querySelector('[data-gsf-live]') || !window.fetch) { return; }
		function ids() {
			var seen = {};
			Array.prototype.forEach.call(document.querySelectorAll('[data-gsf-live] [data-match], [data-gsf-live][data-match-hero]'), function (el) {
				seen[el.getAttribute('data-match') || el.getAttribute('data-match-hero')] = 1;
			});
			return Object.keys(seen);
		}
		function refresh() {
			if (document.hidden) { return; }
			var list = ids();
			if (!list.length) { return; }
			fetch(cfg.rest + 'matches?ids=' + list.join(','), { credentials: 'omit' })
				.then(function (r) { return r.ok ? r.json() : []; })
				.then(function (matches) {
					matches.forEach(function (m) {
						Array.prototype.forEach.call(document.querySelectorAll('.gsf-match[data-match="' + m.id + '"] .gsf-match__center, [data-match-hero="' + m.id + '"] .gsf-hero__center'), function (center) {
							if (center.getAttribute('data-html') !== m.centerHtml) {
								// centerHtml is rendered and escaped server-side by the plugin.
								center.innerHTML = m.centerHtml;
								center.setAttribute('data-html', m.centerHtml);
							}
						});
						Array.prototype.forEach.call(document.querySelectorAll('.gsf-ticker a[data-match="' + m.id + '"]'), function (a) {
							var scores = a.querySelectorAll('.gsf-ticker__row b');
							if (scores.length === 2 && m.home.score !== null) {
								scores[0].textContent = m.home.score;
								scores[1].textContent = m.away.score;
							}
						});
					});
				})
				.catch(function () {});
		}
		setInterval(refresh, 45000);
	}

	/* Match centre tabs: sliding indicator, keyboard arrows, swipe on touch screens */
	function initMatchCenters(root) {
		var nodes = (root || document).querySelectorAll('[data-gsf-mc]:not([data-gsf-ready])');
		Array.prototype.forEach.call(nodes, function (mc) {
			mc.setAttribute('data-gsf-ready', '1');
			var tabs = Array.prototype.slice.call(mc.querySelectorAll('[role="tab"]'));
			var ink = mc.querySelector('.gsf-mc__ink');
			var body = mc.querySelector('.gsf-mc__body');
			var current = 0;
			tabs.forEach(function (t, i) { if (t.getAttribute('aria-selected') === 'true') { current = i; } });

			function moveInk() {
				var t = tabs[current];
				if (!ink || !t) { return; }
				ink.style.width = t.offsetWidth + 'px';
				ink.style.transform = 'translateX(' + t.offsetLeft + 'px)';
			}
			function select(i, focus) {
				if (i < 0 || i >= tabs.length || i === current) { return; }
				var dir = i > current ? 'next' : 'prev';
				tabs.forEach(function (t, j) {
					var on = j === i;
					t.setAttribute('aria-selected', on ? 'true' : 'false');
					t.tabIndex = on ? 0 : -1;
					var panel = document.getElementById(t.getAttribute('aria-controls'));
					if (panel) {
						panel.hidden = !on;
						if (on && !reduce) {
							panel.classList.remove('is-in-next', 'is-in-prev');
							void panel.offsetWidth;
							panel.classList.add('is-in-' + dir);
						}
						if (on) { initCountdowns(panel); initCounters(panel); }
					}
				});
				current = i;
				moveInk();
				if (focus) { tabs[i].focus(); }
				// Keep the tab bar in view when a long list is replaced by a short one on phones.
				var top = mc.getBoundingClientRect().top;
				if (top < 0) { window.scrollBy({ top: top - 80, behavior: reduce ? 'auto' : 'smooth' }); }
			}
			tabs.forEach(function (t, i) {
				t.addEventListener('click', function () { select(i); });
				t.addEventListener('keydown', function (e) {
					var k = e.key;
					if (k === 'ArrowRight' || k === 'ArrowLeft' || k === 'Home' || k === 'End') {
						e.preventDefault();
						var n = k === 'Home' ? 0 : k === 'End' ? tabs.length - 1 : (current + (k === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
						select(n, true);
					}
				});
			});

			// Swipe left / right on the content to change tab (horizontal gestures only).
			var sx = 0, sy = 0, tracking = false;
			body.addEventListener('touchstart', function (e) {
				if (e.touches.length !== 1 || e.target.closest('.gsf-ticker__track, .gsf-table-wrap')) { tracking = false; return; }
				tracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
			}, { passive: true });
			body.addEventListener('touchend', function (e) {
				if (!tracking) { return; }
				tracking = false;
				var dx = e.changedTouches[0].clientX - sx;
				var dy = e.changedTouches[0].clientY - sy;
				if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.6) { select(current + (dx < 0 ? 1 : -1)); }
			}, { passive: true });

			mc.classList.add('is-ready');
			moveInk();
			window.addEventListener('resize', moveInk);
			if (document.fonts && document.fonts.ready) { document.fonts.ready.then(moveInk); }
		});
	}

	function init(root) { initMatchCenters(root); initCountdowns(root); initCounters(root); }
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); initLive(); });
	} else {
		init(); initLive();
	}
	// Elementor editor: widgets are re-rendered dynamically.
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && elementorFrontend.hooks) {
			elementorFrontend.hooks.addAction('frontend/element_ready/widget', function ($scope) {
				init($scope && $scope[0] ? $scope[0] : document);
			});
		}
	});
})();
