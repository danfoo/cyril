/* Wedding RSVP Pass – « Espace mariés » (PWA) */
(function () {
	'use strict';

	var CFG = window.WRP_APP || {};
	var KEY = 'wrp_app_' + (CFG.siteId || 'x') + '_';
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
	var app = $('#app');

	var S = {
		session: null,
		guests: [],
		stats: null,
		list: 'all',
		filter: 'all',
		search: '',
		view: 'home',
		online: navigator.onLine,
		team: []
	};

	/* ------------------------------------------------------------------
	 * Utils
	 * ---------------------------------------------------------------- */
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function store(k, v) { try { localStorage.setItem(KEY + k, JSON.stringify(v)); } catch (e) {} }
	function load(k) { try { return JSON.parse(localStorage.getItem(KEY + k)); } catch (e) { return null; } }
	function initials(name) {
		var p = String(name || '?').trim().split(/\s+/);
		return ((p[0] || '?')[0] + (p[1] ? p[1][0] : '')).toUpperCase();
	}
	function plural(n, one, many) { return n + ' ' + (n > 1 ? many : one); }
	function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	var toastT;
	function toast(msg) {
		var t = $('[data-toast]');
		t.textContent = msg;
		t.classList.add('is-on');
		clearTimeout(toastT);
		toastT = setTimeout(function () { t.classList.remove('is-on'); }, 2600);
	}
	function copy(text) {
		if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(text); }
		var ta = document.createElement('textarea');
		ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
		document.body.appendChild(ta); ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		ta.remove();
		return Promise.resolve();
	}
	function show(screen) { app.setAttribute('data-screen', screen); window.scrollTo(0, 0); }

	/* ------------------------------------------------------------------
	 * Network
	 * ---------------------------------------------------------------- */
	function ajax(action, data) {
		var body = new URLSearchParams(Object.assign({ action: 'wrp_app_' + action }, data || {}));
		return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: body, cache: 'no-store' })
			.then(function (r) { return r.json().catch(function () { return { success: false }; }); });
	}

	function rest(path, method, body, retried) {
		var opts = {
			method: method || 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'X-WP-Nonce': S.session ? S.session.restNonce : '', 'Accept': 'application/json' }
		};
		if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
		var url = CFG.rest + path;
		return fetch(url, opts).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (data) {
				if (r.ok) { return data; }
				// Expired nonce / session → refresh once.
				if (!retried && (r.status === 401 || r.status === 403) && data && /nonce|not_logged|forbidden_context|rest_forbidden/.test(data.code || '')) {
					return refreshSession().then(function (s) {
						if (!s.loggedIn) { show('login'); throw new Error('session'); }
						return rest(path, method, body, true);
					});
				}
				var err = new Error((data && data.message) || ('Erreur ' + r.status));
				err.status = r.status;
				throw err;
			});
		});
	}

	function refreshSession() {
		return ajax('session').then(function (res) {
			S.session = res && res.success ? res.data : { loggedIn: false };
			if (S.session.loggedIn) {
				store('session', S.session);
				if (S.session.scannerKey) { shareScannerKey(S.session); }
			}
			return S.session;
		});
	}

	/**
	 * The scanner (same origin) stores its key in IndexedDB; give it the key
	 * so it is configured automatically on this phone.
	 */
	function shareScannerKey(sess) {
		try {
			localStorage.setItem('wrp_autokey_' + CFG.siteId, JSON.stringify({ key: sess.scannerKey, name: sess.user.name }));
		} catch (e) {}
	}

	/* ------------------------------------------------------------------
	 * Install (PWA)
	 * ---------------------------------------------------------------- */
	var deferredPrompt = null;
	var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
	var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferredPrompt = e;
		$$('[data-install]').forEach(function (b) { b.hidden = false; });
	});
	window.addEventListener('appinstalled', function () {
		deferredPrompt = null;
		$$('[data-install]').forEach(function (b) { b.hidden = true; });
		toast('Application installée ♥');
	});
	$$('[data-install]').forEach(function (b) {
		b.addEventListener('click', function () {
			if (deferredPrompt) {
				deferredPrompt.prompt();
				deferredPrompt.userChoice.finally(function () { deferredPrompt = null; });
			} else if (isIOS) {
				iosHelp();
			}
		});
	});
	if (isIOS && !isStandalone) {
		$$('[data-ios-help], .screen--login [data-install]').forEach(function (b) { b.hidden = false; });
	}
	var iosBtn = $('[data-ios-help]');
	if (iosBtn) { iosBtn.addEventListener('click', iosHelp); }
	function iosHelp() {
		openSheet('<h2>Installer sur iPhone</h2><ol class="ios-steps"><li>Ouvrez cette page dans <b>Safari</b>.</li><li>Touchez le bouton <b>Partager</b> (carré avec une flèche).</li><li>Choisissez <b>« Sur l’écran d’accueil »</b>.</li><li>Touchez <b>Ajouter</b> : l’icône ♥ apparaît sur votre écran.</li></ol><button type="button" class="btn" data-sheet-close>J’ai compris</button>');
	}

	if ('serviceWorker' in navigator && CFG.sw) {
		navigator.serviceWorker.register(CFG.sw, { scope: CFG.scope }).catch(function () {});
	}

	/* ------------------------------------------------------------------
	 * Login
	 * ---------------------------------------------------------------- */
	var loginForm = $('[data-login]');
	loginForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var btn = loginForm.querySelector('[type=submit]');
		var err = $('[data-login-error]');
		err.hidden = true;
		if (!navigator.onLine) { err.textContent = 'Connexion internet nécessaire pour se connecter.'; err.hidden = false; return; }
		btn.classList.add('is-loading');
		ajax('login', { login: loginForm.login.value.trim(), password: loginForm.password.value }).then(function (res) {
			if (!res || !res.success) {
				throw new Error(res && res.data && res.data.message ? res.data.message : 'Connexion impossible.');
			}
			return refreshSession();
		}).then(function (sess) {
			if (!sess.loggedIn) { throw new Error('Les cookies semblent bloqués sur ce navigateur.'); }
			loginForm.password.value = '';
			route();
		}).catch(function (e2) {
			err.textContent = e2.message;
			err.hidden = false;
		}).then(function () { btn.classList.remove('is-loading'); });
	});
	$('[data-pw-toggle]').addEventListener('click', function () {
		var i = loginForm.password;
		i.type = i.type === 'password' ? 'text' : 'password';
	});

	$('[data-logout]').addEventListener('click', function () {
		if (!window.confirm('Se déconnecter de l’espace des mariés ?')) { return; }
		ajax('logout', { nonce: S.session ? S.session.restNonce : '' }).finally(function () {
			try {
				Object.keys(localStorage).forEach(function (k) { if (k.indexOf(KEY) === 0) { localStorage.removeItem(k); } });
			} catch (e) {}
			S.session = null; S.guests = [];
			show('login');
		});
	});

	/* ------------------------------------------------------------------
	 * Routing
	 * ---------------------------------------------------------------- */
	function route() {
		var s = S.session;
		if (!s || !s.loggedIn) { show('login'); return; }
		// Welcome team: straight to the scanner (already configured).
		if (!s.can.manage) { location.replace(CFG.scannerUrl); return; }
		setupMain();
		show('main');
		go(S.view);
		loadGuests(true);
	}

	function go(view) {
		S.view = view;
		$$('[data-view]').forEach(function (v) { v.hidden = v.getAttribute('data-view') !== view; });
		$$('[data-tabbar] [data-go]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-go') === view); });
		if (view === 'team') { loadTeam(); }
		if (view === 'guests') { renderGuests(); }
		window.scrollTo(0, 0);
	}
	document.addEventListener('click', function (e) {
		var g = e.target.closest('[data-go]');
		if (g) { go(g.getAttribute('data-go')); }
		var a = e.target.closest('[data-action]');
		if (a) {
			var act = a.getAttribute('data-action');
			if (act === 'add') { guestForm(null); }
			if (act === 'pending') { S.filter = 'invited'; go('guests'); }
			if (act === 'add-member') { memberForm(); }
		}
		if (e.target.closest('[data-sheet-close]')) { closeSheet(); }
	});

	function setupMain() {
		var s = S.session;
		$('[data-names]').textContent = s.wedding.names;
		$('[data-subtitle]').textContent = s.wedding.date + (s.wedding.venue ? ' · ' + s.wedding.venue : '');
		$$('[data-initials]').forEach(function (el) { el.textContent = s.user.initials; });
		$('[data-user-name]').textContent = s.user.name;
		$('[data-user-role]').textContent = s.can.all ? 'Accès à toutes les listes' : 'Votre liste d’invités';
		$('[data-hello-kicker]').textContent = 'Bonjour ' + s.user.first;
		var left = s.wedding.timestamp - Date.now();
		var days = Math.floor(left / 864e5);
		$('[data-hello]').textContent = days >= 2 ? 'Plus que ' + days + ' jours' : days === 1 ? 'Plus qu’un jour !' : left > 0 ? 'C’est le grand jour ♥' : 'Vive les mariés ♥';
		var adm = $('[data-admin-link]');
		adm.href = s.adminUrl;
		adm.hidden = !s.can.admin;
		$$('[data-scanner-link]').forEach(function (l) { l.hidden = !s.can.scan; });
		$('[data-tabbar] [data-go="team"]').hidden = !s.can.team;
		renderLists();
		tickCountdown();
	}

	var cdTimer;
	function tickCountdown() {
		clearInterval(cdTimer);
		var el = $('[data-countdown]');
		var ts = S.session.wedding.timestamp;
		var draw = function () {
			var d = Math.max(0, ts - Date.now());
			var v = [[Math.floor(d / 864e5), 'jours'], [Math.floor(d / 36e5) % 24, 'heures'], [Math.floor(d / 6e4) % 60, 'min'], [Math.floor(d / 1e3) % 60, 'sec']];
			el.innerHTML = v.map(function (x) { return '<div><b>' + x[0] + '</b><span>' + x[1] + '</span></div>'; }).join('');
		};
		draw();
		cdTimer = setInterval(draw, 1000);
	}

	/* ------------------------------------------------------------------
	 * Guests
	 * ---------------------------------------------------------------- */
	function loadGuests(fromCache) {
		// The offline copy is only used to display something instantly (first load / list switch).
		if (fromCache) {
			var cached = load('guests_' + S.list);
			if (cached) { S.guests = cached.guests; S.stats = cached.stats; renderAll(); }
		}
		if (!navigator.onLine) { setOffline(true); return Promise.resolve(); }
		return rest('guests?list=' + encodeURIComponent(S.list)).then(function (data) {
			S.guests = data.guests;
			S.stats = data.stats;
			store('guests_' + S.list, { guests: data.guests, stats: data.stats });
			setOffline(false);
			renderAll();
		}).catch(function (e) {
			if (e.message !== 'session') { setOffline(!navigator.onLine); if (navigator.onLine) { toast(e.message); } }
		});
	}

	function setOffline(off) {
		S.online = !off;
		$('[data-offline]').hidden = !off;
	}
	window.addEventListener('online', function () { if (S.session && S.session.loggedIn) { refreshSession().then(function () { loadGuests(); }); } });
	window.addEventListener('offline', function () { setOffline(true); });

	function renderAll() { renderHome(); renderGuests(); }

	function renderLists() {
		var box = $('[data-lists]');
		var lists = S.session.lists || [];
		box.hidden = lists.length < 2;
		box.innerHTML = lists.map(function (l) {
			return '<button type="button" class="chip' + (l.key === S.list ? ' is-active' : '') + '" data-list="' + esc(l.key) + '">' + (l.key === 'all' ? 'Toutes les listes' : 'Liste de ' + esc(l.label)) + '</button>';
		}).join('');
	}
	$('[data-lists]').addEventListener('click', function (e) {
		var b = e.target.closest('[data-list]');
		if (!b) { return; }
		S.list = b.getAttribute('data-list');
		renderLists();
		S.guests = [];
		loadGuests(true);
	});

	var FILTERS = [
		['all', 'Tous'], ['pending', 'À inviter'], ['invited', 'Sans réponse'],
		['confirmed', 'Présents'], ['declined', 'Absents'], ['arrived', 'Arrivés']
	];
	function matches(g, f) {
		if (f === 'all') { return true; }
		if (f === 'arrived') { return !!g.checkedInAt; }
		return g.status === f;
	}
	function renderFilters() {
		$('[data-filters]').innerHTML = FILTERS.map(function (f) {
			var n = S.guests.filter(function (g) { return matches(g, f[0]); }).length;
			return '<button type="button" class="chip' + (f[0] === S.filter ? ' is-active' : '') + '" data-filter="' + f[0] + '">' + f[1] + '<b>' + n + '</b></button>';
		}).join('');
	}
	$('[data-filters]').addEventListener('click', function (e) {
		var b = e.target.closest('[data-filter]');
		if (!b) { return; }
		S.filter = b.getAttribute('data-filter');
		renderGuests();
	});
	$('[data-search]').addEventListener('input', function (e) { S.search = e.target.value; renderGuests(); });

	function guestRow(g) {
		var sub = [plural(g.status === 'confirmed' ? g.seatsConfirmed : g.seatsAllowed, 'place', 'places'), g.group, g.table, S.list === 'all' && S.session.can.all ? g.owner : ''].filter(Boolean).join(' · ');
		var pill = g.checkedInAt ? '<span class="pill pill--in">✓ Arrivé·e</span>' : '<span class="pill pill--' + g.status + '">' + esc(g.statusLabel) + '</span>';
		return '<li><button type="button" class="row" data-guest="' + g.id + '"><span class="row__av is-' + g.status + '">' + esc(initials(g.name)) + '</span><span class="row__main"><b>' + esc(g.name) + '</b><small>' + esc(sub) + '</small></span>' + pill + '</button></li>';
	}

	function renderGuests() {
		if (!S.session) { return; }
		renderFilters();
		var q = norm(S.search.trim());
		var list = S.guests.filter(function (g) {
			if (!matches(g, S.filter)) { return false; }
			if (!q) { return true; }
			return norm([g.name, g.email, g.phone, g.group, g.table, (g.companions || []).join(' ')].join(' ')).indexOf(q) > -1;
		});
		$('[data-count]').textContent = plural(list.length, 'invitation', 'invitations');
		$('[data-guests]').innerHTML = list.length ? list.map(guestRow).join('') : '<li class="empty">' + (S.guests.length ? 'Aucun invité ne correspond.' : 'Aucun invité pour le moment.<br>Touchez ＋ pour ajouter le premier.') + '</li>';
	}

	function renderHome() {
		var s = S.stats;
		if (!s) { return; }
		var waiting = s.invited + s.pending;
		$('[data-stats]').innerHTML =
			'<button type="button" class="card card--ok" data-card="confirmed"><strong>' + s.seats_confirmed + '</strong><span>Personnes présentes</span><small>' + plural(s.confirmed, 'réponse positive', 'réponses positives') + '</small></button>' +
			'<button type="button" class="card card--wait" data-card="invited"><strong>' + waiting + '</strong><span>En attente</span><small>' + plural(s.pending, 'pas encore invité', 'pas encore invités') + '</small></button>' +
			'<button type="button" class="card card--no" data-card="declined"><strong>' + s.declined + '</strong><span>Absents</span><small>ont décliné</small></button>' +
			'<button type="button" class="card" data-card="all"><strong>' + s.invitations + '</strong><span>Invitations</span><small>' + plural(s.seats_invited, 'place proposée', 'places proposées') + '</small></button>';
		var rate = s.invitations ? Math.round((s.confirmed + s.declined) / s.invitations * 100) : 0;
		$('[data-progress]').style.width = rate + '%';
		$('[data-progress-label]').textContent = rate + ' % des invités ont répondu' + (s.seats_checked_in ? ' · ' + s.seats_checked_in + ' arrivés' : '');
		var latest = S.guests.filter(function (g) { return g.respondedAt; }).sort(function (a, b) { return a.respondedAt < b.respondedAt ? 1 : -1; }).slice(0, 6);
		$('[data-latest]').innerHTML = latest.length ? latest.map(function (g) {
			var msg = g.message ? '<em>« ' + esc(g.message.length > 90 ? g.message.slice(0, 90) + '…' : g.message) + ' »</em>' : '';
			return '<li><button type="button" class="row" data-guest="' + g.id + '"><span class="row__av is-' + g.status + '">' + esc(initials(g.name)) + '</span><span class="row__main"><b>' + esc(g.name) + '</b><small>' + (g.status === 'confirmed' ? 'Présent·e · ' + plural(g.seatsConfirmed, 'personne', 'personnes') : 'Ne pourra pas venir') + '</small>' + msg + '</span></button></li>';
		}).join('') : '<li class="empty">Pas encore de réponse. Envoyez vos invitations depuis l’onglet Invités ♥</li>';
	}
	$('[data-stats]').addEventListener('click', function (e) {
		var c = e.target.closest('[data-card]');
		if (c) { S.filter = c.getAttribute('data-card'); go('guests'); }
	});
	document.addEventListener('click', function (e) {
		var r = e.target.closest('[data-guest]');
		if (r) { guestDetail(+r.getAttribute('data-guest')); }
	});

	function findGuest(id) { return S.guests.filter(function (g) { return g.id === id; })[0]; }
	function upsert(g) {
		var i = S.guests.findIndex(function (x) { return x.id === g.id; });
		if (i > -1) { S.guests[i] = g; } else { S.guests.push(g); S.guests.sort(function (a, b) { return norm(a.lastName + a.firstName) < norm(b.lastName + b.firstName) ? -1 : 1; }); }
	}
	function afterChange() {
		// Stats are recomputed server-side; refresh quietly.
		renderAll();
		loadGuests();
	}

	/* ---------- Detail ---------- */
	function guestDetail(id) {
		var g = findGuest(id);
		if (!g) { return; }
		var rows = [
			['E-mail', g.email], ['Téléphone', g.phone],
			['Places', g.status === 'confirmed' ? g.seatsConfirmed + ' / ' + g.seatsAllowed : g.seatsAllowed],
			['Accompagnants', (g.companions || []).join(', ')], ['Groupe', g.group], ['Table', g.table],
			['Régime', g.dietary], ['Liste', S.session.can.all ? g.owner : ''],
			['Code du carton', g.code], ['Arrivé·e', g.checkedInAt ? g.checkedInAt.slice(11, 16) : ''], ['Notes', g.notes]
		].filter(function (r) { return r[1] !== '' && r[1] != null; });
		var pill = g.checkedInAt ? '<span class="pill pill--in">✓ Arrivé·e</span>' : '<span class="pill pill--' + g.status + '">' + esc(g.statusLabel) + '</span>';
		var html = '<h2>' + esc(g.name) + '</h2>' + pill +
			'<ul class="info">' + rows.map(function (r) { return '<li><span>' + r[0] + '</span><b>' + esc(r[1]) + '</b></li>'; }).join('') + '</ul>' +
			(g.message ? '<p class="quote">« ' + esc(g.message) + ' »</p>' : '') +
			'<div class="actions">' +
			(g.links.whatsapp ? '<a class="btn btn--wa" data-send href="' + esc(g.links.whatsapp) + '" target="_blank" rel="noopener">WhatsApp</a>' : '') +
			(g.links.sms ? '<a class="btn btn--ghost" data-send href="' + esc(g.links.sms) + '">SMS</a>' : '') +
			(g.email ? '<button type="button" class="btn btn--ghost" data-email>' + (g.code ? 'Renvoyer le carton' : 'Envoyer par e-mail') + '</button>' : '') +
			(navigator.share ? '<button type="button" class="btn btn--ghost" data-share>Partager…</button>' : '') +
			'<button type="button" class="btn btn--ghost" data-copy>Copier le lien</button>' +
			(g.links.card ? '<a class="btn btn--gold" href="' + esc(g.links.card) + '" target="_blank" rel="noopener">Voir le carton</a>' : '') +
			'<button type="button" class="btn btn--wide" data-edit>Modifier</button>' +
			(g.checkedInAt ? '<button type="button" class="btn btn--ghost btn--wide" data-reset>Annuler son passage</button>' : '') +
			'<button type="button" class="btn btn--danger btn--wide" data-delete>Supprimer</button>' +
			'</div>';
		var body = openSheet(html);

		var markInvited = function () {
			if (g.status !== 'pending' || !S.online) { return; }
			rest('guests/' + g.id + '/invited', 'POST').then(function (d) { upsert(d.guest); afterChange(); }).catch(function () {});
		};
		$$('[data-send]', body).forEach(function (a) { a.addEventListener('click', markInvited); });
		var on = function (sel, fn) { var el = $(sel, body); if (el) { el.addEventListener('click', fn); } };
		on('[data-copy]', function () { copy(g.links.rsvp).then(function () { toast('Lien copié !'); markInvited(); }); });
		on('[data-share]', function () {
			navigator.share({ title: S.session.wedding.names, text: g.links.text }).then(markInvited).catch(function () {});
		});
		on('[data-email]', function (e) {
			var b = e.currentTarget;
			b.classList.add('is-loading');
			rest('guests/' + g.id + '/email', 'POST').then(function (d) { upsert(d.guest); afterChange(); toast('E-mail envoyé à ' + g.firstName + ' ✉'); })
				.catch(function (err) { toast(err.message); }).then(function () { b.classList.remove('is-loading'); });
		});
		on('[data-edit]', function () { guestForm(g); });
		on('[data-reset]', function () {
			if (!window.confirm('Annuler le passage de ' + g.name + ' ?')) { return; }
			rest('guests/' + g.id + '/reset', 'POST').then(function (d) { upsert(d.guest); afterChange(); closeSheet(); toast('Passage annulé'); }).catch(function (err) { toast(err.message); });
		});
		on('[data-delete]', function () {
			if (!window.confirm('Supprimer définitivement ' + g.name + ' ?')) { return; }
			rest('guests/' + g.id, 'DELETE').then(function () {
				S.guests = S.guests.filter(function (x) { return x.id !== g.id; });
				closeSheet(); afterChange(); toast('Invité supprimé');
			}).catch(function (err) { toast(err.message); });
		});
	}

	/* ---------- Form ---------- */
	function field(label, name, value, type, extra) {
		return '<label><span>' + label + '</span><input name="' + name + '" type="' + (type || 'text') + '" value="' + esc(value == null ? '' : value) + '" ' + (extra || '') + '></label>';
	}
	function guestForm(g) {
		if (!S.online) { toast('Connexion internet nécessaire pour modifier la liste.'); return; }
		var isNew = !g;
		g = g || { seatsAllowed: 1, status: 'pending', ownerId: S.session.user.id };
		var owners = S.session.owners || [];
		var groups = Array.from(new Set(S.guests.map(function (x) { return x.group; }).filter(Boolean)));
		var html = '<h2>' + (isNew ? 'Nouvel invité' : 'Modifier ' + esc(g.firstName)) + '</h2><form class="form" data-guest-form novalidate>' +
			'<div class="two">' + field('Prénom *', 'firstName', g.firstName, 'text', 'required autocomplete="off"') + field('Nom', 'lastName', g.lastName, 'text', 'autocomplete="off"') + '</div>' +
			field('Téléphone (WhatsApp / SMS)', 'phone', g.phone, 'tel', 'placeholder="+33 6 12 34 56 78"') +
			field('E-mail', 'email', g.email, 'email', 'autocapitalize="none"') +
			'<div class="two">' + field('Places (invité inclus)', 'seatsAllowed', g.seatsAllowed, 'number', 'min="1" max="20" inputmode="numeric"') + field('Table', 'table', g.table) + '</div>' +
			field('Groupe (famille, amis…)', 'group', g.group, 'text', 'list="wrp-groups"') +
			'<datalist id="wrp-groups">' + groups.map(function (x) { return '<option value="' + esc(x) + '">'; }).join('') + '</datalist>' +
			(owners.length > 1 ? '<label><span>Liste</span><select name="ownerId">' + owners.map(function (o) { return '<option value="' + o.id + '"' + (o.id === g.ownerId ? ' selected' : '') + '>Liste de ' + esc(o.label) + '</option>'; }).join('') + '</select></label>' : '') +
			(!isNew ? '<div class="two"><label><span>Réponse</span><select name="status">' +
				[['pending', 'À inviter'], ['invited', 'Invité·e – en attente'], ['confirmed', 'Présent·e'], ['declined', 'Absent·e']].map(function (s) { return '<option value="' + s[0] + '"' + (s[0] === g.status ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') +
				'</select></label>' + field('Personnes confirmées', 'seatsConfirmed', g.seatsConfirmed, 'number', 'min="0" max="20" inputmode="numeric"') + '</div>' : '') +
			field('Régime / allergies', 'dietary', g.dietary) +
			'<label><span>Notes privées</span><textarea name="notes" rows="2">' + esc(g.notes || '') + '</textarea></label>' +
			'<p class="form__error" data-form-error hidden></p>' +
			'<button type="submit" class="btn btn--gold">' + (isNew ? 'Ajouter l’invité' : 'Enregistrer') + '</button>' +
			(isNew ? '<button type="submit" class="btn btn--ghost" data-again>Ajouter et en saisir un autre</button>' : '') +
			'</form>';
		var body = openSheet(html);
		var form = $('[data-guest-form]', body);
		var again = false;
		var againBtn = $('[data-again]', form);
		if (againBtn) { againBtn.addEventListener('click', function () { again = true; }); }
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = $('[data-form-error]', form);
			var data = {};
			new FormData(form).forEach(function (v, k) { data[k] = v; });
			if (!String(data.firstName || '').trim()) { err.textContent = 'Le prénom est obligatoire.'; err.hidden = false; again = false; return; }
			if (data.ownerId) { data.ownerId = +data.ownerId; }
			var btns = $$('button[type=submit]', form);
			btns.forEach(function (b) { b.classList.add('is-loading'); });
			rest(isNew ? 'guests' : 'guests/' + g.id, 'POST', data).then(function (d) {
				upsert(d.guest);
				afterChange();
				toast(isNew ? d.guest.firstName + ' a été ajouté·e ♥' : 'Modifications enregistrées');
				if (isNew && again) { guestForm(null); } else { guestDetail(d.guest.id); }
			}).catch(function (e2) {
				err.textContent = e2.message; err.hidden = false;
			}).then(function () { again = false; btns.forEach(function (b) { b.classList.remove('is-loading'); }); });
		});
		setTimeout(function () { var f = form.firstName; if (f && isNew) { f.focus(); } }, 350);
	}

	/* ------------------------------------------------------------------
	 * Team (welcome / scan accounts)
	 * ---------------------------------------------------------------- */
	function loadTeam() {
		if (!S.online) { return; }
		rest('team').then(function (d) { S.team = d.team; renderTeam(); }).catch(function (e) { toast(e.message); });
	}
	function renderTeam() {
		var ul = $('[data-team]');
		ul.innerHTML = S.team.length ? S.team.map(function (m) {
			return '<li><div class="row"><span class="row__av is-confirmed">' + esc(initials(m.name)) + '</span><span class="row__main"><b>' + esc(m.name) + '</b><small>Identifiant : ' + esc(m.login) + '</small></span><button type="button" class="btn btn--danger btn--small" data-del-member="' + m.id + '">Retirer</button></div></li>';
		}).join('') : '<li class="empty">Aucune personne pour le moment.</li>';
	}
	$('[data-team]').addEventListener('click', function (e) {
		var b = e.target.closest('[data-del-member]');
		if (!b) { return; }
		var id = +b.getAttribute('data-del-member');
		var m = S.team.filter(function (x) { return x.id === id; })[0];
		if (!window.confirm('Retirer l’accès de ' + (m ? m.name : '') + ' ?')) { return; }
		rest('team/' + id, 'DELETE').then(function () { toast('Accès retiré'); loadTeam(); }).catch(function (err) { toast(err.message); });
	});
	function memberForm() {
		if (!S.online) { toast('Connexion internet nécessaire.'); return; }
		var body = openSheet('<h2>Ajouter une personne</h2><p class="muted">Elle pourra uniquement scanner les cartons à l’entrée.</p><form class="form" data-member-form novalidate>' +
			field('Prénom et nom *', 'name', '', 'text', 'required autocomplete="off"') +
			field('E-mail (optionnel)', 'email', '', 'email', 'autocapitalize="none"') +
			'<p class="form__error" data-form-error hidden></p><button type="submit" class="btn btn--gold">Créer son accès</button></form>');
		var form = $('[data-member-form]', body);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = $('[data-form-error]', form);
			var btn = $('button[type=submit]', form);
			btn.classList.add('is-loading');
			rest('team', 'POST', { name: form.name.value.trim(), email: form.email.value.trim() }).then(function (d) {
				loadTeam();
				var b2 = openSheet('<h2>Accès créé ♥</h2><p class="muted">Transmettez ces informations à ' + esc(d.member.name) + '. Le mot de passe n’est affiché qu’une seule fois.</p>' +
					'<div class="secret">Adresse<code>' + esc(CFG.appUrl) + '</code>Identifiant<code>' + esc(d.member.login) + '</code>Mot de passe<code>' + esc(d.password) + '</code></div>' +
					'<div class="actions">' + (navigator.share ? '<button type="button" class="btn btn--wa btn--wide" data-share-member>Envoyer (WhatsApp, SMS…)</button>' : '') +
					'<button type="button" class="btn btn--ghost btn--wide" data-copy-member>Copier le message</button><button type="button" class="btn btn--wide" data-sheet-close>Terminé</button></div>');
				var sh = $('[data-share-member]', b2);
				if (sh) { sh.addEventListener('click', function () { navigator.share({ text: d.message }).catch(function () {}); }); }
				$('[data-copy-member]', b2).addEventListener('click', function () { copy(d.message).then(function () { toast('Message copié'); }); });
			}).catch(function (e2) {
				err.textContent = e2.message; err.hidden = false;
			}).then(function () { btn.classList.remove('is-loading'); });
		});
	}

	/* ------------------------------------------------------------------
	 * Sheet
	 * ---------------------------------------------------------------- */
	function openSheet(html) {
		var sheet = $('[data-sheet]');
		var body = $('[data-sheet-body]');
		body.innerHTML = html;
		sheet.hidden = false;
		document.body.style.overflow = 'hidden';
		$('.sheet__panel', sheet).scrollTop = 0;
		return body;
	}
	function closeSheet() {
		$('[data-sheet]').hidden = true;
		document.body.style.overflow = '';
	}
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSheet(); } });

	/* ------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------- */
	if (!navigator.onLine) {
		S.session = load('session');
		if (S.session && S.session.loggedIn) { route(); } else { show('login'); }
	} else {
		refreshSession().then(route).catch(function () {
			S.session = load('session');
			if (S.session && S.session.loggedIn) { route(); } else { show('login'); }
		});
	}
	setInterval(function () { if (S.session && S.session.loggedIn && navigator.onLine && !document.hidden) { refreshSession(); } }, 10 * 60 * 1000);

	window.WRPApp = { state: S, go: go, reload: loadGuests };
})();
