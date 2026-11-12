/*! Écurie Prestige — interactions (sans dépendance). */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function inEditor() {
		return document.body.classList.contains('elementor-editor-active') ||
			!!(window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode());
	}

	function each(list, fn) {
		Array.prototype.forEach.call(list, fn);
	}

	/* ---------- Apparition au défilement + compteurs ---------- */

	function setupReveal(root) {
		var items = root.querySelectorAll('.ep-head, .ep-card:not(.ep-slide), .ep-showcase, .ep-featured__media, .ep-featured__content > *');
		var counters = root.querySelectorAll('[data-ep-count]');

		if (reduceMotion || inEditor() || !('IntersectionObserver' in window)) {
			each(items, function (el) { el.classList.add('is-in'); });
			return;
		}

		root.classList.add('ep-js');

		var io = new IntersectionObserver(function (entries) {
			var batch = 0;
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				entry.target.style.setProperty('--ep-d', batch++);
				entry.target.classList.add('is-in');
				io.unobserve(entry.target);
			});
		}, { rootMargin: '100000px 0px -8% 0px', threshold: 0.08 });
		each(items, function (el) { io.observe(el); });

		if (counters.length) {
			var cio = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) { return; }
					countUp(entry.target);
					cio.unobserve(entry.target);
				});
			}, { threshold: 0.6 });
			each(counters, function (el) { cio.observe(el); });
		}
	}

	function countUp(el) {
		var target = parseInt(el.getAttribute('data-ep-count'), 10);
		if (reduceMotion || !target || el.dataset.epCounted) { return; }
		el.dataset.epCounted = '1';
		var final = el.textContent;
		var start = null;
		var duration = Math.min(1800, 700 + target * 30);
		function frame(now) {
			if (start === null) { start = now; }
			var p = Math.min(1, (now - start) / duration);
			var eased = 1 - Math.pow(1 - p, 4);
			el.textContent = Math.round(target * eased).toLocaleString('fr-FR');
			if (p < 1) { requestAnimationFrame(frame); } else { el.textContent = final; }
		}
		requestAnimationFrame(frame);
	}

	/* ---------- Filtres par discipline ---------- */

	function setupFilters(root) {
		var buttons = root.querySelectorAll('[data-ep-filter]');
		if (!buttons.length) { return; }
		var cards = root.querySelectorAll('.ep-card');
		var track = root.querySelector('.ep-carousel__track');

		each(buttons, function (button) {
			button.addEventListener('click', function () {
				var filter = button.getAttribute('data-ep-filter');
				each(buttons, function (b) {
					var active = b === button;
					b.classList.toggle('is-active', active);
					b.setAttribute('aria-pressed', active ? 'true' : 'false');
				});
				var shown = 0;
				each(cards, function (card) {
					var terms = (card.getAttribute('data-ep-terms') || '').split(' ');
					var match = filter === '*' || terms.indexOf(filter) !== -1;
					card.classList.toggle('is-hidden', !match);
					if (!match) { return; }
					// Rejoue l’apparition en cascade.
					if (root.classList.contains('ep-js')) {
						card.classList.remove('is-in');
						card.style.setProperty('--ep-d', Math.min(shown, 8));
						void card.offsetWidth;
						card.classList.add('is-in');
					}
					shown++;
				});
				if (track) {
					track.scrollLeft = 0;
					track.dispatchEvent(new Event('scroll'));
				}
				root.dispatchEvent(new CustomEvent('ep:filter'));
			});
		});
	}

	/* ---------- Carrousel ---------- */

	function setupCarousel(root) {
		var track = root.querySelector('.ep-carousel__track');
		if (!track) { return; }
		var prev = root.querySelector('[data-ep-scroll="-1"]');
		var next = root.querySelector('[data-ep-scroll="1"]');
		var bar = root.querySelector('.ep-carousel__progress span');
		var ui = root.querySelector('.ep-carousel__ui');

		function step() {
			var card = track.querySelector('.ep-card:not(.is-hidden)');
			if (!card) { return track.clientWidth; }
			var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
			return card.getBoundingClientRect().width + gap;
		}

		function update() {
			var max = track.scrollWidth - track.clientWidth;
			var ratio = track.scrollWidth ? track.clientWidth / track.scrollWidth : 1;
			var progress = max > 0 ? track.scrollLeft / max : 0;
			if (ui) { ui.style.visibility = max > 4 ? '' : 'hidden'; }
			if (bar && bar.parentNode) {
				var width = bar.parentNode.clientWidth;
				bar.style.setProperty('--ep-progress-w', (ratio * 100) + '%');
				bar.style.setProperty('--ep-progress-x', (progress * (width - width * ratio)) + 'px');
			}
			if (prev) { prev.disabled = track.scrollLeft <= 2; }
			if (next) { next.disabled = track.scrollLeft >= max - 2; }
		}

		[prev, next].forEach(function (button) {
			if (!button) { return; }
			button.addEventListener('click', function () {
				var dir = parseInt(button.getAttribute('data-ep-scroll'), 10);
				track.scrollBy({ left: dir * step(), behavior: reduceMotion ? 'auto' : 'smooth' });
			});
		});

		track.addEventListener('scroll', function () { requestAnimationFrame(update); }, { passive: true });
		window.addEventListener('resize', update);
		if ('ResizeObserver' in window) { new ResizeObserver(update).observe(track); }

		// Glisser à la souris.
		var startX = 0, startLeft = 0, dragging = false, moved = false;
		track.addEventListener('pointerdown', function (e) {
			if (e.pointerType !== 'mouse' || e.button !== 0) { return; }
			dragging = true; moved = false; startX = e.clientX; startLeft = track.scrollLeft;
		});
		window.addEventListener('pointermove', function (e) {
			if (!dragging) { return; }
			var dx = e.clientX - startX;
			if (!moved && Math.abs(dx) > 6) { moved = true; track.classList.add('is-dragging'); }
			if (moved) { track.scrollLeft = startLeft - dx; }
		});
		window.addEventListener('pointerup', function () {
			if (!dragging) { return; }
			dragging = false;
			if (moved) {
				// Laisse le clic se terminer avant de réactiver les cartes, puis s’aligne sur la carte la plus proche.
				setTimeout(function () {
					track.classList.remove('is-dragging');
					var s = step();
					track.scrollTo({ left: Math.round(track.scrollLeft / s) * s, behavior: reduceMotion ? 'auto' : 'smooth' });
				}, 0);
			}
		});
		track.addEventListener('click', function (e) {
			if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
		}, true);
		track.addEventListener('dragstart', function (e) { e.preventDefault(); });

		update();
	}

	/* ---------- Carrousel cinéma ---------- */

	function setupShowcase(root) {
		var box = root.querySelector('.ep-showcase');
		if (!box) { return; }
		var stage = box.querySelector('.ep-showcase__stage');
		var ghost = box.querySelector('.ep-showcase__ghost span');
		var countEl = box.querySelector('.ep-showcase__count b');
		var totalEl = box.querySelector('.ep-showcase__count i');
		var bar = box.querySelector('.ep-showcase__bar span');
		var all = Array.prototype.slice.call(stage.querySelectorAll('.ep-slide'));
		var slides = all;
		var active = 0;
		var autoplay = box.hasAttribute('data-ep-autoplay') && !reduceMotion && !inEditor();

		function pad(n) { return (n < 10 ? '0' : '') + n; }

		function restartBar() {
			if (!bar) { return; }
			box.classList.remove('is-playing');
			void bar.offsetWidth;
			if (autoplay && slides.length > 1) { box.classList.add('is-playing'); }
		}

		function layout(dir) {
			var n = slides.length;
			slides.forEach(function (slide, i) {
				var o = i - active;
				if (n > 2) {
					if (o > n / 2) { o -= n; }
					if (o < -n / 2) { o += n; }
				}
				var before = slide._epO;
				// Une carte qui « fait le tour » change de côté sans traverser la scène.
				if (before !== undefined && Math.abs(o - before) > 1.5) {
					slide.classList.add('no-anim');
					slide.style.setProperty('--ep-o', o > 0 ? o + 1 : o - 1);
					void slide.offsetWidth;
					slide.classList.remove('no-anim');
				}
				slide._epO = o;
				slide.style.setProperty('--ep-o', o);
				slide.classList.toggle('is-active', o === 0);
				slide.classList.toggle('is-far', Math.abs(o) > 1);
				var trigger = slide.querySelector('.ep-card__link');
				if (trigger) {
					if (o === 0) { trigger.removeAttribute('tabindex'); } else { trigger.setAttribute('tabindex', '-1'); }
				}
				if (o === 0) { slide.removeAttribute('aria-hidden'); } else { slide.setAttribute('aria-hidden', 'true'); }
			});
			var current = slides[active];
			if (countEl) { countEl.textContent = n ? pad(active + 1) : ''; }
			if (totalEl) { totalEl.textContent = n ? '/ ' + pad(n) : ''; }
			if (ghost && current) {
				var name = current.querySelector('.ep-card__name');
				ghost.style.setProperty('--ep-dir', dir || 1);
				ghost.textContent = name ? name.textContent : '';
				ghost.style.animation = 'none';
				void ghost.offsetWidth;
				ghost.style.animation = '';
			}
			each(box.querySelectorAll('[data-ep-go]'), function (b) { b.disabled = n < 2; });
			restartBar();
		}

		function goTo(index, dir) {
			var n = slides.length;
			if (!n) { return; }
			active = (index + n) % n;
			layout(dir);
		}
		function go(dir) { goTo(active + dir, dir); }

		each(box.querySelectorAll('[data-ep-go]'), function (button) {
			button.addEventListener('click', function () { go(parseInt(button.getAttribute('data-ep-go'), 10)); });
		});

		box.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') { go(-1); e.preventDefault(); }
			if (e.key === 'ArrowRight') { go(1); e.preventDefault(); }
		});

		// Glisser (souris ou doigt).
		var startX = 0, dragging = false, moved = false, dx = 0;
		stage.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse' && e.button !== 0) { return; }
			dragging = true; moved = false; dx = 0; startX = e.clientX;
		});
		window.addEventListener('pointermove', function (e) {
			if (!dragging) { return; }
			dx = e.clientX - startX;
			if (!moved && Math.abs(dx) > 8) { moved = true; box.classList.add('is-dragging'); }
			if (moved) { stage.style.setProperty('--ep-drag', dx + 'px'); }
		});
		function endDrag() {
			if (!dragging) { return; }
			dragging = false;
			box.classList.remove('is-dragging');
			stage.style.removeProperty('--ep-drag');
			if (moved && Math.abs(dx) > 48) { go(dx < 0 ? 1 : -1); }
		}
		window.addEventListener('pointerup', endDrag);
		window.addEventListener('pointercancel', endDrag);
		stage.addEventListener('dragstart', function (e) { e.preventDefault(); });

		// Un clic sur un cheval voisin l’amène au centre au lieu d’ouvrir sa fiche.
		stage.addEventListener('click', function (e) {
			if (moved) { moved = false; e.preventDefault(); e.stopPropagation(); return; }
			var slide = e.target.closest ? e.target.closest('.ep-slide') : null;
			if (slide && !slide.classList.contains('is-active')) {
				e.preventDefault();
				e.stopPropagation();
				var index = slides.indexOf(slide);
				if (index !== -1) { goTo(index, slide._epO < 0 ? -1 : 1); }
			}
		}, true);

		// Défilement automatique, rythmé par la barre de progression.
		if (bar) {
			bar.addEventListener('animationend', function () {
				var dialog = root.querySelector('dialog[open]');
				if (dialog || document.hidden) { restartBar(); } else { go(1); }
			});
		}
		var dialogEl = root.querySelector('.ep-modal');
		if (dialogEl && 'MutationObserver' in window) {
			new MutationObserver(function () { box.classList.toggle('is-paused', dialogEl.hasAttribute('open')); })
				.observe(dialogEl, { attributes: true, attributeFilter: ['open'] });
		}

		root.addEventListener('ep:filter', function () {
			slides = all.filter(function (slide) { return !slide.classList.contains('is-hidden'); });
			all.forEach(function (slide) { slide._epO = undefined; });
			active = 0;
			layout(1);
		});

		box.classList.add('is-ready');
		layout(1);
	}

	/* ---------- Fiche en fenêtre ---------- */

	function setupModal(root) {
		var dialog = root.querySelector('.ep-modal');
		if (!dialog) { return; }
		var body = dialog.querySelector('.ep-modal__body');
		var pos = dialog.querySelector('.ep-modal__pos');
		var prev = dialog.querySelector('[data-ep-prev]');
		var next = dialog.querySelector('[data-ep-next]');
		var current = null;
		var opener = null;

		function list() {
			return Array.prototype.filter.call(root.querySelectorAll('.ep-card'), function (card) {
				return !card.classList.contains('is-hidden') && card.querySelector('template.ep-card__fiche');
			});
		}

		function pad(n) { return (n < 10 ? '0' : '') + n; }

		function show(card) {
			var tpl = card.querySelector('template.ep-card__fiche');
			if (!tpl) { return; }
			current = card;
			body.innerHTML = '';
			body.appendChild(tpl.content.cloneNode(true));
			var title = body.querySelector('.ep-fiche__name');
			if (title && title.id) { dialog.setAttribute('aria-labelledby', title.id); }
			var cards = list();
			var index = cards.indexOf(card);
			var single = cards.length < 2;
			pos.textContent = single ? '' : pad(index + 1) + ' / ' + pad(cards.length);
			pos.hidden = prev.hidden = next.hidden = single;
			each(body.querySelectorAll('[data-ep-count]'), countUp);
			var scroller = body.querySelector('.ep-fiche__content');
			if (scroller) { scroller.scrollTop = 0; }
			body.scrollTop = 0;
		}

		function open(card, trigger) {
			opener = trigger || null;
			show(card);
			if (dialog.open) { return; }
			if (typeof dialog.showModal === 'function') { dialog.showModal(); } else { dialog.setAttribute('open', ''); }
			document.documentElement.classList.add('ep-lock');
		}

		function move(dir) {
			var cards = list();
			if (cards.length < 2) { return; }
			var index = cards.indexOf(current);
			show(cards[(index + dir + cards.length) % cards.length]);
		}

		root.addEventListener('click', function (e) {
			var trigger = e.target.closest ? e.target.closest('[data-ep-open]') : null;
			if (trigger && root.contains(trigger)) {
				var card = trigger.closest('.ep-card');
				if (card) { e.preventDefault(); open(card, trigger); }
				return;
			}
			var thumb = e.target.closest ? e.target.closest('.ep-thumbs__item') : null;
			if (thumb && dialog.contains(thumb)) {
				var main = dialog.querySelector('.ep-fiche__main');
				if (main && main.tagName === 'IMG') {
					main.removeAttribute('srcset');
					main.removeAttribute('sizes');
					main.src = thumb.getAttribute('data-ep-full');
					main.style.animation = 'none';
					void main.offsetWidth;
					main.style.animation = '';
				}
				each(dialog.querySelectorAll('.ep-thumbs__item'), function (t) { t.classList.toggle('is-active', t === thumb); });
			}
		});

		dialog.addEventListener('click', function (e) {
			if (e.target === dialog || (e.target.closest && e.target.closest('[data-ep-close]'))) {
				if (typeof dialog.close === 'function') { dialog.close(); } else { dialog.removeAttribute('open'); onClose(); }
			}
		});
		prev.addEventListener('click', function () { move(-1); });
		next.addEventListener('click', function () { move(1); });
		dialog.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') { move(-1); }
			if (e.key === 'ArrowRight') { move(1); }
		});

		function onClose() {
			document.documentElement.classList.remove('ep-lock');
			body.innerHTML = '';
			if (opener && opener.focus) { opener.focus(); }
		}
		dialog.addEventListener('close', onClose);
	}

	/* ---------- Initialisation ---------- */

	function init(root) {
		if (!root || root.dataset.epReady) { return; }
		root.dataset.epReady = '1';
		setupReveal(root);
		setupFilters(root);
		setupCarousel(root);
		setupShowcase(root);
		setupModal(root);
	}

	function initAll(scope) {
		each((scope || document).querySelectorAll('.ep[data-ep]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initAll(); });
	} else {
		initAll();
	}

	// Elementor : (ré)initialise chaque widget, y compris après un réglage dans l’éditeur.
	function bindElementor() {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks || bindElementor.done) { return; }
		bindElementor.done = true;
		['ep-grid', 'ep-carousel', 'ep-showcase', 'ep-featured'].forEach(function (name) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/' + name + '.default', function ($scope) {
				initAll($scope && $scope[0] ? $scope[0] : document);
			});
		});
	}
	bindElementor();
	window.addEventListener('elementor/frontend/init', bindElementor);
	if (window.jQuery) { window.jQuery(window).on('elementor/frontend/init', bindElementor); }

	window.EcuriePrestige = { init: initAll };
})();
