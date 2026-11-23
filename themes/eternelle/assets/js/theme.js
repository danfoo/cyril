/* Éternelle – wedding theme interactions */
(function () {
	'use strict';

	var C = window.ETERNELLE || {};
	var doc = document.documentElement;
	doc.classList.remove('no-js');
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

	/* ------------------------------------------------------------------
	 * Music
	 * ---------------------------------------------------------------- */
	var audio = $('[data-audio]');
	var musicBtn = $('[data-music]');
	function setMusic(on) {
		if (!audio) { return; }
		if (on) {
			audio.volume = 0;
			var p = audio.play();
			if (p && p.catch) { p.catch(function () { musicBtn.setAttribute('aria-pressed', 'false'); }); }
			var v = 0;
			var fade = setInterval(function () { v = Math.min(0.6, v + 0.05); audio.volume = v; if (v >= 0.6) { clearInterval(fade); } }, 120);
		} else {
			audio.pause();
		}
		musicBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
	}
	if (musicBtn) {
		musicBtn.addEventListener('click', function () { setMusic(audio.paused); });
	}

	/* ------------------------------------------------------------------
	 * Envelope intro
	 * ---------------------------------------------------------------- */
	var env = $('[data-envelope]');
	if (env) {
		var seen = false;
		try { seen = sessionStorage.getItem('et_env_opened') === '1'; } catch (e) {}
		if (seen || reduce) {
			env.remove();
		} else {
			document.body.classList.add('et-locked', 'et-intro');
			var finish = function (delay) {
				try { sessionStorage.setItem('et_env_opened', '1'); } catch (e) {}
				setTimeout(function () {
					env.classList.add('is-gone');
					document.body.classList.remove('et-locked', 'et-intro');
					burst(window.innerWidth / 2, window.innerHeight / 2, 40);
					setTimeout(function () { env.remove(); }, 1100);
				}, delay);
			};
			$('[data-envelope-open]', env).addEventListener('click', function () {
				env.classList.add('is-open');
				setMusic(true);
				finish(2300);
			});
			$('[data-envelope-skip]', env).addEventListener('click', function () { finish(0); });
		}
	}

	/* ------------------------------------------------------------------
	 * Header & navigation
	 * ---------------------------------------------------------------- */
	var header = $('[data-header]');
	var burger = $('[data-burger]');
	var nav = $('[data-nav]');
	function onScroll() {
		if (header) { header.classList.toggle('is-scrolled', window.scrollY > 40); }
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
	if (burger && nav) {
		burger.addEventListener('click', function () {
			var open = burger.getAttribute('aria-expanded') !== 'true';
			burger.setAttribute('aria-expanded', open ? 'true' : 'false');
			nav.classList.toggle('is-open', open);
			header.classList.toggle('menu-open', open);
			document.body.classList.toggle('et-locked', open);
		});
		nav.addEventListener('click', function (e) {
			if (e.target.closest('a')) {
				burger.setAttribute('aria-expanded', 'false');
				nav.classList.remove('is-open');
				header.classList.remove('menu-open');
				document.body.classList.remove('et-locked');
			}
		});
	}

	// Highlight the section in view.
	var links = $$('.et-menu a[href*="#"]');
	if (links.length && 'IntersectionObserver' in window) {
		var map = {};
		links.forEach(function (a) {
			var id = a.getAttribute('href').split('#')[1];
			if (id) { map[id] = a.parentNode; }
		});
		var spy = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting && map[en.target.id]) {
					Object.keys(map).forEach(function (k) { map[k].classList.remove('is-current'); });
					map[en.target.id].classList.add('is-current');
				}
			});
		}, { rootMargin: '-45% 0px -50% 0px' });
		Object.keys(map).forEach(function (id) { var s = document.getElementById(id); if (s) { spy.observe(s); } });
	}

	/* ------------------------------------------------------------------
	 * Countdown
	 * ---------------------------------------------------------------- */
	var cd = $('[data-countdown]');
	if (cd && C.date) {
		var els = { d: $('[data-cd=d]', cd), h: $('[data-cd=h]', cd), m: $('[data-cd=m]', cd), s: $('[data-cd=s]', cd) };
		var prev = {};
		var tickCd = function () {
			var diff = C.date - Date.now();
			if (diff <= 0) {
				cd.classList.add('is-done');
				cd.textContent = (C.i18n && C.i18n.married) || '♥';
				return false;
			}
			var v = {
				d: Math.floor(diff / 864e5),
				h: Math.floor(diff / 36e5) % 24,
				m: Math.floor(diff / 6e4) % 60,
				s: Math.floor(diff / 1e3) % 60
			};
			Object.keys(v).forEach(function (k) {
				var txt = k === 'd' ? String(v[k]) : ('0' + v[k]).slice(-2);
				if (prev[k] !== txt) {
					els[k].textContent = txt;
					if (prev[k] !== undefined && !reduce) {
						els[k].classList.remove('tick');
						void els[k].offsetWidth;
						els[k].classList.add('tick');
					}
					prev[k] = txt;
				}
			});
			return true;
		};
		if (tickCd()) {
			var cdTimer = setInterval(function () { if (!tickCd()) { clearInterval(cdTimer); } }, 1000);
		}
	}

	/* ------------------------------------------------------------------
	 * Reveal on scroll
	 * ---------------------------------------------------------------- */
	var revealEls = $$('[data-reveal]');
	if ('IntersectionObserver' in window && !reduce) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					en.target.classList.add('is-visible');
					var section = en.target.closest('.et-section');
					if (section) { section.classList.add('is-visible'); }
					io.unobserve(en.target);
				}
			});
		}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
		revealEls.forEach(function (el) { io.observe(el); });
	} else {
		revealEls.forEach(function (el) { el.classList.add('is-visible'); });
		$$('.et-section').forEach(function (s) { s.classList.add('is-visible'); });
	}

	/* ------------------------------------------------------------------
	 * Hero FX: rose petals, golden dust, bokeh
	 * ---------------------------------------------------------------- */
	var canvas = $('[data-fx]');
	var hero = $('[data-hero]');
	if (canvas && hero && !reduce && (C.petals || C.sparkles)) {
		var ctx = canvas.getContext('2d');
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var W = 0, H = 0, petals = [], dust = [], bokeh = [];
		var mouse = { x: 0, y: 0, tx: 0, ty: 0 };
		var small = window.innerWidth < 700;
		var petalColors = [['#ffffff', '#f6c1c8'], ['#fff6f6', '#eea9b3'], ['#fffaf2', '#f3d2b4'], ['#ffffff', '#f9d9dd']];

		var resize = function () {
			W = hero.clientWidth; H = hero.clientHeight;
			canvas.width = W * dpr; canvas.height = H * dpr;
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		};
		var rnd = function (a, b) { return a + Math.random() * (b - a); };
		var newPetal = function (initial) {
			var c = petalColors[Math.floor(Math.random() * petalColors.length)];
			return { x: rnd(-50, W + 50), y: initial ? rnd(-H, H) : rnd(-80, -20), s: rnd(8, 17), vy: rnd(0.5, 1.4), vx: rnd(-0.4, 0.5), r: rnd(0, 6.28), vr: rnd(-0.025, 0.025), sw: rnd(0, 6.28), fl: rnd(0, 6.28), vf: rnd(0.02, 0.05), c1: c[0], c2: c[1], a: rnd(0.65, 0.95), z: rnd(0.6, 1.3) };
		};
		var newDust = function () {
			return { x: rnd(0, W), y: rnd(0, H), r: rnd(0.6, 2.2), vy: rnd(-0.35, -0.08), vx: rnd(-0.15, 0.15), t: rnd(0, 6.28), vt: rnd(0.02, 0.06) };
		};
		var newBokeh = function () {
			return { x: rnd(0, W), y: rnd(0, H), r: rnd(20, 90), a: rnd(0.04, 0.12), vx: rnd(-0.15, 0.15), vy: rnd(-0.12, 0.12), z: rnd(0.2, 1) };
		};
		resize();
		if (C.petals) { for (var i = 0; i < (small ? 16 : 34); i++) { petals.push(newPetal(true)); } }
		if (C.sparkles) {
			for (var j = 0; j < (small ? 50 : 110); j++) { dust.push(newDust()); }
			for (var k = 0; k < (small ? 6 : 12); k++) { bokeh.push(newBokeh()); }
		}
		window.addEventListener('resize', resize);
		hero.addEventListener('pointermove', function (e) {
			mouse.tx = (e.clientX / W - 0.5) * 30;
			mouse.ty = (e.clientY / H - 0.5) * 30;
		});

		var drawPetal = function (p) {
			ctx.save();
			ctx.translate(p.x, p.y);
			ctx.rotate(p.r);
			ctx.scale(p.z, p.z * (0.35 + Math.abs(Math.cos(p.fl)) * 0.65));
			ctx.globalAlpha = p.a;
			var g = ctx.createLinearGradient(-p.s, -p.s, p.s, p.s);
			g.addColorStop(0, p.c1);
			g.addColorStop(1, p.c2);
			ctx.fillStyle = g;
			ctx.beginPath();
			ctx.moveTo(0, -p.s);
			ctx.bezierCurveTo(p.s * 1.1, -p.s * 0.9, p.s * 0.9, p.s * 0.7, 0, p.s);
			ctx.bezierCurveTo(-p.s * 0.9, p.s * 0.7, -p.s * 1.1, -p.s * 0.9, 0, -p.s);
			ctx.fill();
			// Vein.
			ctx.globalAlpha = p.a * 0.25;
			ctx.strokeStyle = p.c2;
			ctx.lineWidth = 0.6;
			ctx.beginPath();
			ctx.moveTo(0, -p.s * 0.7);
			ctx.quadraticCurveTo(p.s * 0.15, 0, 0, p.s * 0.8);
			ctx.stroke();
			ctx.restore();
		};

		var visible = true;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) { visible = en[0].isIntersecting; if (visible) { requestAnimationFrame(frame); } }).observe(hero);
		}
		var frame = function () {
			if (!visible || document.hidden) { return; }
			mouse.x += (mouse.tx - mouse.x) * 0.05;
			mouse.y += (mouse.ty - mouse.y) * 0.05;
			ctx.clearRect(0, 0, W, H);

			// Bokeh.
			ctx.globalCompositeOperation = 'lighter';
			bokeh.forEach(function (b) {
				b.x += b.vx; b.y += b.vy;
				if (b.x < -100) { b.x = W + 100; } if (b.x > W + 100) { b.x = -100; }
				if (b.y < -100) { b.y = H + 100; } if (b.y > H + 100) { b.y = -100; }
				var x = b.x + mouse.x * b.z, y = b.y + mouse.y * b.z;
				var g = ctx.createRadialGradient(x, y, 0, x, y, b.r);
				g.addColorStop(0, 'rgba(255,226,180,' + b.a + ')');
				g.addColorStop(0.7, 'rgba(255,210,170,' + (b.a * 0.5) + ')');
				g.addColorStop(1, 'rgba(255,200,160,0)');
				ctx.fillStyle = g;
				ctx.beginPath(); ctx.arc(x, y, b.r, 0, 6.283); ctx.fill();
			});
			// Golden dust.
			dust.forEach(function (d) {
				d.t += d.vt; d.y += d.vy; d.x += d.vx;
				if (d.y < -5) { d.y = H + 5; d.x = rnd(0, W); }
				var tw = 0.35 + Math.abs(Math.sin(d.t)) * 0.65;
				ctx.fillStyle = 'rgba(255,225,160,' + (tw * 0.9) + ')';
				ctx.beginPath(); ctx.arc(d.x + mouse.x * 0.4, d.y + mouse.y * 0.4, d.r * tw, 0, 6.283); ctx.fill();
				if (d.r > 1.8 && tw > 0.9) {
					ctx.strokeStyle = 'rgba(255,240,210,' + (tw * 0.6) + ')';
					ctx.lineWidth = 0.6;
					ctx.beginPath();
					ctx.moveTo(d.x - 6, d.y); ctx.lineTo(d.x + 6, d.y);
					ctx.moveTo(d.x, d.y - 6); ctx.lineTo(d.x, d.y + 6);
					ctx.stroke();
				}
			});
			ctx.globalCompositeOperation = 'source-over';
			// Petals.
			for (var i = 0; i < petals.length; i++) {
				var p = petals[i];
				p.sw += 0.015; p.fl += p.vf;
				p.y += p.vy * p.z; p.x += (p.vx + Math.sin(p.sw) * 0.7) * p.z + mouse.x * 0.002; p.r += p.vr;
				if (p.y > H + 40 || p.x < -80 || p.x > W + 80) { petals[i] = newPetal(false); }
				drawPetal(p);
			}
			requestAnimationFrame(frame);
		};
		document.addEventListener('visibilitychange', function () { if (!document.hidden) { requestAnimationFrame(frame); } });
		requestAnimationFrame(frame);
	}

	// Parallax for the hero photo.
	var px = $('[data-parallax]');
	if (px && !reduce) {
		var ticking = false;
		window.addEventListener('scroll', function () {
			if (ticking) { return; }
			ticking = true;
			requestAnimationFrame(function () {
				var y = Math.min(window.scrollY, window.innerHeight * 1.2);
				px.style.translate = '0 ' + (y * parseFloat(px.getAttribute('data-parallax'))) + 'px';
				ticking = false;
			});
		}, { passive: true });
	}

	/* ------------------------------------------------------------------
	 * Gallery lightbox
	 * ---------------------------------------------------------------- */
	var gallery = $('[data-gallery]');
	var lb = $('[data-lightbox]');
	if (gallery && lb) {
		var items = $$('a', gallery);
		var img = $('[data-lb-img]', lb);
		var idx = 0;
		var lastFocus = null;
		var showImg = function (i) {
			idx = (i + items.length) % items.length;
			img.src = items[idx].getAttribute('href');
			var alt = $('img', items[idx]);
			img.alt = alt ? alt.alt : '';
			img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';
		};
		var open = function (i) {
			lastFocus = document.activeElement;
			showImg(i);
			lb.hidden = false;
			document.body.classList.add('et-locked');
			$('[data-lb-close]', lb).focus();
		};
		var close = function () {
			lb.hidden = true;
			document.body.classList.remove('et-locked');
			if (lastFocus) { lastFocus.focus(); }
		};
		gallery.addEventListener('click', function (e) {
			var a = e.target.closest('a');
			if (!a) { return; }
			e.preventDefault();
			open(+a.getAttribute('data-index'));
		});
		$('[data-lb-close]', lb).addEventListener('click', close);
		$('[data-lb-prev]', lb).addEventListener('click', function () { showImg(idx - 1); });
		$('[data-lb-next]', lb).addEventListener('click', function () { showImg(idx + 1); });
		lb.addEventListener('click', function (e) { if (e.target === lb) { close(); } });
		document.addEventListener('keydown', function (e) {
			if (lb.hidden) { return; }
			if (e.key === 'Escape') { close(); }
			if (e.key === 'ArrowLeft') { showImg(idx - 1); }
			if (e.key === 'ArrowRight') { showImg(idx + 1); }
		});
		var sx = 0;
		lb.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
		lb.addEventListener('touchend', function (e) {
			var dx = e.changedTouches[0].clientX - sx;
			if (Math.abs(dx) > 50) { showImg(idx + (dx < 0 ? 1 : -1)); }
		});
	}

	/* ------------------------------------------------------------------
	 * Little magic: hearts on click, golden sparkles following the cursor
	 * ---------------------------------------------------------------- */
	function burst(x, y, n) {
		if (reduce) { return; }
		var glyphs = ['♥', '♡', '✦'];
		for (var i = 0; i < n; i++) {
			var h = document.createElement('span');
			h.className = 'et-pop-heart';
			h.textContent = glyphs[i % glyphs.length];
			var ang = Math.random() * Math.PI * 2;
			var dist = 40 + Math.random() * (n > 10 ? 220 : 70);
			h.style.left = x + 'px';
			h.style.top = y + 'px';
			h.style.fontSize = (10 + Math.random() * 14) + 'px';
			h.style.color = ['#e48b98', '#d4a85f', '#f2b8c0'][i % 3];
			h.style.setProperty('--x', Math.cos(ang) * dist + 'px');
			h.style.setProperty('--y', (Math.sin(ang) * dist - 40) + 'px');
			h.style.setProperty('--r', (Math.random() * 120 - 60) + 'deg');
			document.body.appendChild(h);
			setTimeout(h.remove.bind(h), 1100);
		}
	}
	if (C.hearts && !reduce) {
		document.addEventListener('click', function (e) {
			if (e.target.closest('a,button,input,textarea,select,label,[data-lightbox]')) { return; }
			burst(e.clientX, e.clientY, 7);
		});
	}
	if (C.cursor && finePointer && !reduce) {
		var last = 0;
		document.addEventListener('pointermove', function (e) {
			var now = performance.now();
			if (now - last < 45) { return; }
			last = now;
			var s = document.createElement('span');
			s.className = 'et-sparkle';
			s.style.left = (e.clientX - 3) + 'px';
			s.style.top = (e.clientY - 3) + 'px';
			s.style.setProperty('--x', (Math.random() * 30 - 15) + 'px');
			s.style.setProperty('--y', (Math.random() * 20 + 10) + 'px');
			document.body.appendChild(s);
			setTimeout(s.remove.bind(s), 950);
		}, { passive: true });
	}
})();
