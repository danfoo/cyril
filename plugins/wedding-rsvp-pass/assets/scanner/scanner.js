/* Wedding RSVP Pass – offline scanner app
 *
 * - The guest list (hashed pass codes) is stored in IndexedDB.
 * - Scans are validated locally, stored in a queue, and pushed to the
 *   server when the network is available (idempotent via UUID).
 * - Uses the native BarcodeDetector when available, jsQR otherwise.
 */
(function () {
	'use strict';

	var CFG = window.WRP_SCANNER || {};
	var PREFIX = 'wrp_' + (CFG.siteId || 'x') + '_';
	var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
	var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
	var app = $('#app');

	/* ==================================================================
	 * Storage (IndexedDB with localStorage fallback)
	 * ================================================================ */
	var Store = (function () {
		var dbp = null;
		function open() {
			if (dbp) { return dbp; }
			dbp = new Promise(function (resolve, reject) {
				if (!('indexedDB' in window)) { reject(new Error('no idb')); return; }
				var req = indexedDB.open('wrp-scanner-' + (CFG.siteId || 'x'), 1);
				req.onupgradeneeded = function () {
					var db = req.result;
					if (!db.objectStoreNames.contains('kv')) { db.createObjectStore('kv'); }
				};
				req.onsuccess = function () { resolve(req.result); };
				req.onerror = function () { reject(req.error); };
			});
			return dbp;
		}
		function tx(mode, fn) {
			return open().then(function (db) {
				return new Promise(function (resolve, reject) {
					var t = db.transaction('kv', mode);
					var st = t.objectStore('kv');
					var r = fn(st);
					t.oncomplete = function () { resolve(r && 'result' in r ? r.result : undefined); };
					t.onerror = function () { reject(t.error); };
					t.onabort = function () { reject(t.error); };
				});
			});
		}
		return {
			get: function (k) {
				return tx('readonly', function (st) { return st.get(k); }).catch(function () {
					try { return JSON.parse(localStorage.getItem(PREFIX + k)); } catch (e) { return null; }
				});
			},
			set: function (k, v) {
				return tx('readwrite', function (st) { st.put(v, k); }).catch(function () {
					try { localStorage.setItem(PREFIX + k, JSON.stringify(v)); } catch (e) {}
				});
			},
			clear: function () {
				return tx('readwrite', function (st) { st.clear(); }).catch(function () {}).then(function () {
					Object.keys(localStorage).forEach(function (k) { if (k.indexOf(PREFIX) === 0) { localStorage.removeItem(k); } });
				});
			}
		};
	})();

	/* ==================================================================
	 * Utils
	 * ================================================================ */
	function sha256(ascii) {
		// Public-domain compact SHA-256 (ASCII input) – works without HTTPS.
		function rr(v, a) { return (v >>> a) | (v << (32 - a)); }
		var maxWord = Math.pow(2, 32), i, j, result = '';
		var words = [], bitLen = ascii.length * 8;
		var hash = [], k = [], pc = 0, isComp = {};
		for (var cand = 2; pc < 64; cand++) {
			if (!isComp[cand]) {
				for (i = 0; i < 313; i += cand) { isComp[i] = cand; }
				hash[pc] = (Math.pow(cand, 0.5) * maxWord) | 0;
				k[pc++] = (Math.pow(cand, 1 / 3) * maxWord) | 0;
			}
		}
		ascii += '\x80';
		while (ascii.length % 64 - 56) { ascii += '\x00'; }
		for (i = 0; i < ascii.length; i++) {
			j = ascii.charCodeAt(i);
			if (j >> 8) { return ''; }
			words[i >> 2] |= j << ((3 - i) % 4) * 8;
		}
		words[words.length] = ((bitLen / maxWord) | 0);
		words[words.length] = bitLen;
		for (j = 0; j < words.length;) {
			var w = words.slice(j, j += 16);
			var old = hash;
			hash = hash.slice(0, 8);
			for (i = 0; i < 64; i++) {
				var w15 = w[i - 15], w2 = w[i - 2], a = hash[0], e = hash[4];
				var t1 = hash[7] + (rr(e, 6) ^ rr(e, 11) ^ rr(e, 25)) + ((e & hash[5]) ^ ((~e) & hash[6])) + k[i] +
					(w[i] = (i < 16) ? w[i] : (w[i - 16] + (rr(w15, 7) ^ rr(w15, 18) ^ (w15 >>> 3)) + w[i - 7] + (rr(w2, 17) ^ rr(w2, 19) ^ (w2 >>> 10))) | 0);
				var t2 = (rr(a, 2) ^ rr(a, 13) ^ rr(a, 22)) + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
				hash = [(t1 + t2) | 0].concat(hash);
				hash[4] = (hash[4] + t1) | 0;
			}
			for (i = 0; i < 8; i++) { hash[i] = (hash[i] + old[i]) | 0; }
		}
		for (i = 0; i < 8; i++) {
			for (j = 3; j + 1; j--) {
				var b = (hash[i] >> (j * 8)) & 255;
				result += ((b < 16) ? 0 : '') + b.toString(16);
			}
		}
		return result;
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
		var b = new Uint8Array(16);
		(window.crypto || window.msCrypto).getRandomValues(b);
		b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
		var h = Array.prototype.map.call(b, function (x) { return (x + 256).toString(16).slice(1); }).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}

	function normalizeCode(raw) {
		var s = String(raw || '').trim().toUpperCase();
		if (s.indexOf('WRP:') === 0) { s = s.slice(4); }
		return s.replace(/[^A-Z0-9]/g, '');
	}
	function normalizeKey(raw) {
		var s = String(raw || '').trim().toUpperCase();
		if (s.indexOf('WRPKEY:') === 0) { s = s.slice(7); }
		s = s.replace(/[^A-Z0-9]/g, '');
		return s.length === 16 ? s.match(/.{4}/g).join('-') : s;
	}
	function timeOf(iso) {
		if (!iso) { return ''; }
		var d = new Date(iso);
		return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function plural(n, one, many) { return n + ' ' + (n > 1 ? many : one); }

	var toastTimer;
	function toast(msg) {
		var t = $('[data-toast]');
		t.textContent = msg;
		t.classList.add('is-on');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { t.classList.remove('is-on'); }, 2600);
	}

	/* ---------- Sound & haptics ---------- */
	var audio = null;
	function unlockAudio() {
		if (!audio) {
			try { audio = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { audio = null; }
		}
		if (audio && audio.state === 'suspended') { audio.resume(); }
	}
	function tone(freqs, dur, type) {
		if (!audio) { return; }
		var now = audio.currentTime;
		freqs.forEach(function (f, i) {
			var o = audio.createOscillator(), g = audio.createGain();
			o.type = type || 'sine';
			o.frequency.value = f;
			g.gain.setValueAtTime(0.0001, now + i * 0.09);
			g.gain.exponentialRampToValueAtTime(0.25, now + i * 0.09 + 0.02);
			g.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.09 + dur);
			o.connect(g); g.connect(audio.destination);
			o.start(now + i * 0.09); o.stop(now + i * 0.09 + dur + 0.05);
		});
	}
	function feedback(kind) {
		if (kind === 'ok') { tone([784, 988, 1319], 0.35); vibrate([60, 40, 60]); }
		else if (kind === 'warn') { tone([440, 440], 0.25, 'triangle'); vibrate([200, 80, 200]); }
		else { tone([220], 0.5, 'square'); vibrate([400]); }
	}
	function vibrate(p) { if (navigator.vibrate) { try { navigator.vibrate(p); } catch (e) {} } }

	/* ==================================================================
	 * State
	 * ================================================================ */
	var S = {
		key: '', deviceId: '', deviceName: '',
		list: null,          // { generatedAt, wedding, stats, guests: [] }
		byHash: {}, byId: {},
		scans: [],           // local check-ins
		lastSync: null,
		syncing: false
	};

	function indexList() {
		S.byHash = {}; S.byId = {};
		if (!S.list) { return; }
		S.list.guests.forEach(function (g) { S.byHash[g.h] = g; S.byId[g.id] = g; });
	}

	function localScanFor(guestId) {
		for (var i = S.scans.length - 1; i >= 0; i--) {
			if (S.scans[i].guestId === guestId && S.scans[i].result !== 'unknown') { return S.scans[i]; }
		}
		return null;
	}

	/** Previous check-in of a guest (server or local), or null. */
	function checkInfo(g) {
		var local = localScanFor(g.id);
		if (local) { return { at: local.scannedAt, here: true }; }
		if (g.checkedAt) { return { at: g.checkedAt, here: g.checkedBy === S.deviceId }; }
		return null;
	}

	function save() {
		return Promise.all([
			Store.set('scans', S.scans),
			Store.set('lastSync', S.lastSync)
		]);
	}

	/* ==================================================================
	 * Network
	 * ================================================================ */
	function api(name, opts) {
		opts = opts || {};
		var ctrl = window.AbortController ? new AbortController() : null;
		var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 15000) : null;
		var headers = { 'X-WRP-Key': opts.key || S.key, 'Accept': 'application/json' };
		if (opts.body) { headers['Content-Type'] = 'application/json'; }
		var url = CFG.endpoints[name];
		url += (url.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now();
		return fetch(url, {
			method: opts.body ? 'POST' : 'GET',
			headers: headers,
			body: opts.body ? JSON.stringify(opts.body) : undefined,
			credentials: 'omit',
			cache: 'no-store',
			signal: ctrl ? ctrl.signal : undefined
		}).then(function (r) {
			if (timer) { clearTimeout(timer); }
			return r.json().catch(function () { return {}; }).then(function (data) {
				if (!r.ok) {
					var err = new Error(data && data.message ? data.message : 'HTTP ' + r.status);
					err.status = r.status;
					throw err;
				}
				return data;
			});
		});
	}

	var syncSoonTimer;
	function syncSoon() {
		clearTimeout(syncSoonTimer);
		syncSoonTimer = setTimeout(function () { sync(false); }, 1500);
	}

	function sync(manual) {
		if (S.syncing || !S.key) { return Promise.resolve(); }
		if (!navigator.onLine && !manual) { return Promise.resolve(); }
		S.syncing = true;
		$('[data-sync]').classList.add('is-spinning');

		var pending = S.scans.filter(function (s) { return !s.synced; });
		var push = pending.length ? api('checkins', {
			body: {
				deviceId: S.deviceId,
				deviceName: S.deviceName,
				checkins: pending.map(function (s) {
					return { uuid: s.uuid, guestId: s.guestId, seats: s.seats, scannedAt: s.scannedAt };
				})
			}
		}).then(function (res) {
			var byUuid = {};
			(res.results || []).forEach(function (r) { byUuid[r.uuid] = r.result; });
			pending.forEach(function (s) {
				if (byUuid[s.uuid]) { s.synced = true; s.serverResult = byUuid[s.uuid]; }
			});
			var dups = pending.filter(function (s) { return s.serverResult === 'duplicate'; }).length;
			if (dups) { toast(plural(dups, 'doublon détecté', 'doublons détectés') + ' avec un autre téléphone'); }
		}) : Promise.resolve();

		return push.then(function () {
			return api('guests');
		}).then(function (list) {
			if (list.build && CFG.build && list.build !== CFG.build) { $('[data-update]').hidden = false; }
			S.list = list;
			indexList();
			S.lastSync = new Date().toISOString();
			return Promise.all([Store.set('list', list), save()]);
		}).then(function () {
			if (manual) { toast('Liste synchronisée ✓ (' + plural(S.list.guests.length, 'carton', 'cartons') + ')'); }
		}).catch(function (e) {
			if (e && e.status === 401) {
				toast('Clé du scanner refusée : reconfigurez ce téléphone.');
			} else if (manual) {
				toast('Pas de réseau : les validations seront envoyées plus tard.');
			}
			return save();
		}).then(function () {
			S.syncing = false;
			$('[data-sync]').classList.remove('is-spinning');
			render();
		});
	}

	/* ==================================================================
	 * Rendering
	 * ================================================================ */
	function show(screen) { app.setAttribute('data-screen', screen); }

	function render() {
		var online = navigator.onLine;
		var net = $('[data-net]');
		net.classList.toggle('is-off', !online);
		$('[data-net-label]').textContent = online ? 'En ligne' : 'Hors connexion – scan actif';

		var pending = S.scans.filter(function (s) { return !s.synced; }).length;
		var badge = $('[data-pending]');
		badge.hidden = !pending;
		badge.textContent = pending;

		if (S.list && S.list.wedding) { $('[data-names]').textContent = S.list.wedding.names; }

		var expected = 0, arrived = 0, cards = 0;
		if (S.list) {
			S.list.guests.forEach(function (g) {
				if (g.status !== 'confirmed') { return; }
				expected += g.seats;
				if (checkInfo(g)) { arrived += g.seats; cards++; }
			});
		}
		$('[data-arrived]').textContent = arrived;
		$('[data-expected]').textContent = expected;
		$('[data-arrived-cards]').textContent = cards;
		var pct = expected ? Math.min(1, arrived / expected) : 0;
		$('[data-ring]').style.strokeDashoffset = String(326.7 - 326.7 * pct);

		var ls = $('[data-last-sync]');
		if (!S.list) {
			ls.textContent = 'Liste non téléchargée : touchez ⟳ avec du réseau.';
		} else {
			var d = S.lastSync ? new Date(S.lastSync) : null;
			ls.textContent = 'Liste à jour : ' + (d ? d.toLocaleDateString() + ' ' + timeOf(S.lastSync) : '—') + (pending ? ' · ' + plural(pending, 'validation à envoyer', 'validations à envoyer') : '');
		}
		$('[data-device-info]').textContent = 'Téléphone : ' + (S.deviceName || 'sans nom') + ' · v' + (CFG.version || '');

		renderHistory();
		if (!$('[data-panel=search]').hidden) { renderSearch(); }
	}

	function renderHistory() {
		var ul = $('[data-history]');
		var items = S.scans.slice().reverse().slice(0, 300);
		if (!items.length) {
			ul.innerHTML = '<li class="empty">Aucun passage validé sur ce téléphone.</li>';
			return;
		}
		ul.innerHTML = items.map(function (s) {
			var g = S.byId[s.guestId];
			var name = g ? g.name : (s.name || 'Invité #' + s.guestId);
			var dot = s.serverResult === 'duplicate' ? 'warn' : s.synced ? 'ok' : '';
			var status = s.serverResult === 'duplicate' ? '⚠ doublon' : s.synced ? '✓ envoyé' : '⏳ en attente';
			return '<li><span class="dot ' + dot + '"></span><span class="who"><b>' + esc(name) + '</b><small>' + plural(s.seats, 'personne', 'personnes') + (g && g.table ? ' · ' + esc(g.table) : '') + '</small></span><span class="right">' + timeOf(s.scannedAt) + '<br>' + status + '</span></li>';
		}).join('');
	}

	function renderSearch() {
		var q = $('[data-search]').value.trim().toLowerCase();
		var ul = $('[data-search-results]');
		if (!S.list) { ul.innerHTML = '<li class="empty">Synchronisez d’abord la liste.</li>'; return; }
		var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
		var nq = norm(q);
		var res = S.list.guests.filter(function (g) {
			if (!nq) { return true; }
			return norm(g.name).indexOf(nq) > -1 || norm((g.companions || []).join(' ')).indexOf(nq) > -1 || norm(g.table).indexOf(nq) > -1 || norm(g.code4) === nq;
		}).slice(0, 80);
		if (!res.length) { ul.innerHTML = '<li class="empty">Aucun résultat.</li>'; return; }
		ul.innerHTML = res.map(function (g) {
			var info = checkInfo(g);
			var dot = g.status !== 'confirmed' ? 'err' : info ? 'ok' : '';
			var right = g.status !== 'confirmed' ? 'non confirmé' : info ? '✓ ' + timeOf(info.at) : plural(g.seats, 'pers.', 'pers.');
			return '<li><button type="button" data-guest="' + g.id + '"><span class="dot ' + dot + '"></span><span class="who"><b>' + esc(g.name) + '</b><small>' + esc([g.companions && g.companions.length ? '+ ' + g.companions.join(', ') : '', g.table, g.side].filter(Boolean).join(' · ')) + '</small></span><span class="right">' + right + '</span></button></li>';
		}).join('');
	}

	/* ==================================================================
	 * Result overlay & validation
	 * ================================================================ */
	var resultTimer = null;
	var pendingConfirm = null;
	var scanPaused = false;

	function showResult(kind, o) {
		var el = $('[data-result]');
		el.className = 'result result--' + kind;
		$('[data-r-icon]').textContent = { ok: '✓', warn: '!', err: '✕', info: '♥' }[kind];
		$('[data-r-title]').textContent = o.title || '';
		$('[data-r-name]').textContent = o.name || '';
		$('[data-r-comp]').textContent = o.comp || '';
		$('[data-r-seats]').textContent = o.seats || '';
		$('[data-r-table]').textContent = o.table || '';
		$('[data-r-note]').textContent = o.note || '';
		var confirmBtn = $('[data-r-confirm]');
		confirmBtn.hidden = !o.onConfirm;
		pendingConfirm = o.onConfirm || null;
		el.hidden = false;
		scanPaused = true;

		var timer = $('[data-r-timer]');
		timer.classList.remove('is-running');
		clearTimeout(resultTimer);
		if (o.autoClose) {
			void timer.offsetWidth;
			timer.style.animationDuration = o.autoClose + 'ms';
			timer.classList.add('is-running');
			resultTimer = setTimeout(closeResult, o.autoClose);
		}
	}

	function closeResult() {
		clearTimeout(resultTimer);
		$('[data-result]').hidden = true;
		pendingConfirm = null;
		setTimeout(function () { scanPaused = false; }, 350);
	}

	function guestDetails(g) {
		return {
			name: g.name,
			comp: g.companions && g.companions.length ? '& ' + g.companions.join(', ') : '',
			seats: plural(g.seats || 1, 'personne', 'personnes'),
			table: g.table ? 'Table : ' + g.table : ''
		};
	}

	function record(g) {
		var s = { uuid: uuid(), guestId: g.id, name: g.name, seats: g.seats || 1, scannedAt: new Date().toISOString(), synced: false };
		S.scans.push(s);
		save();
		render();
		syncSoon();
		return s;
	}

	function welcome(g) {
		record(g);
		var d = guestDetails(g);
		d.title = 'Bienvenue !';
		d.note = g.dietary ? '🍽 ' + g.dietary : '';
		d.autoClose = 4000;
		feedback('ok');
		showResult('ok', d);
	}

	function handleGuest(g, manual) {
		var d = guestDetails(g);
		if (g.status !== 'confirmed') {
			d.title = g.status === 'declined' ? 'A décliné l’invitation' : 'Présence non confirmée';
			d.note = 'Ce carton n’est plus valide. Vérifiez avec les mariés.';
			feedback('err');
			showResult('err', d);
			return;
		}
		var prev = checkInfo(g);
		if (prev) {
			d.title = 'Déjà validé';
			d.note = 'Entrée enregistrée à ' + timeOf(prev.at) + (prev.here ? ' sur ce téléphone.' : ' sur un autre téléphone.');
			feedback('warn');
			showResult('warn', d);
			return;
		}
		if (manual) {
			d.title = 'Vérifiez l’identité';
			d.onConfirm = function () { welcome(g); };
			showResult('info', d);
			return;
		}
		welcome(g);
	}

	function handleCode(raw, source) {
		var txt = String(raw || '').trim();
		if (/^WRPKEY:/i.test(txt)) {
			toast('QR d’activation : ce téléphone est déjà configuré.');
			return;
		}
		var code = normalizeCode(txt);
		if (!/^[A-Z0-9]{8}$/.test(code)) {
			feedback('err');
			showResult('err', { title: 'QR code non reconnu', name: '', note: 'Ce code n’est pas un carton d’invitation de ce mariage.' });
			return;
		}
		if (!S.list) {
			feedback('err');
			showResult('err', { title: 'Liste absente', note: 'Synchronisez le téléphone avec du réseau avant de scanner.' });
			return;
		}
		var g = S.byHash[sha256('wrp|' + code)];
		if (!g) {
			feedback('err');
			showResult('err', { title: 'Carton inconnu', name: code.match(/.{4}/g).join(' '), note: 'Absent de la liste téléchargée. Si l’invité vient de confirmer, synchronisez (⟳) puis réessayez.' });
			return;
		}
		handleGuest(g, source === 'manual');
	}

	/* ==================================================================
	 * Camera & QR detection
	 * ================================================================ */
	var Cam = {
		stream: null, detector: null, wantOn: false, lastText: '', lastAt: 0, busy: false, mode: 'guest',
		video: $('[data-video]'), canvas: $('[data-canvas]'), viewport: $('[data-viewport]'),
		start: function () {
			var self = this;
			unlockAudio();
			if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
				$('[data-cam-error]').textContent = 'Caméra indisponible (le site doit être en HTTPS). Utilisez la saisie manuelle.';
				return Promise.resolve(false);
			}
			self.wantOn = true;
			return navigator.mediaDevices.getUserMedia({
				video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
				audio: false
			}).then(function (stream) {
				self.stream = stream;
				self.video.srcObject = stream;
				return self.video.play();
			}).then(function () {
				self.viewport.classList.add('is-live');
				$('[data-cam-error]').textContent = '';
				if ('BarcodeDetector' in window && !self.detector) {
					return window.BarcodeDetector.getSupportedFormats().then(function (f) {
						if (f.indexOf('qr_code') > -1) { self.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); }
					}).catch(function () {});
				}
			}).then(function () {
				self.keepAwake();
				self.loop();
				return true;
			}).catch(function (e) {
				self.wantOn = false;
				$('[data-cam-error]').textContent = 'Accès caméra refusé (' + (e && e.name ? e.name : 'erreur') + '). Autorisez la caméra dans les réglages du navigateur.';
				return false;
			});
		},
		stop: function () {
			if (this.stream) { this.stream.getTracks().forEach(function (t) { t.stop(); }); }
			this.stream = null;
			this.viewport.classList.remove('is-live');
			if (this.wakeLock) { try { this.wakeLock.release(); } catch (e) {} this.wakeLock = null; }
		},
		keepAwake: function () {
			var self = this;
			if ('wakeLock' in navigator) {
				navigator.wakeLock.request('screen').then(function (l) { self.wakeLock = l; }).catch(function () {});
			}
		},
		loop: function () {
			var self = this;
			if (!self.stream) { return; }
			setTimeout(function () {
				if (!self.stream) { return; }
				if (scanPaused || self.busy || self.video.readyState < 2) { self.loop(); return; }
				self.busy = true;
				self.detect().then(function (text) {
					self.busy = false;
					if (text) {
						var now = Date.now();
						if (text === self.lastText && now - self.lastAt < 3500) { self.loop(); return; }
						self.lastText = text; self.lastAt = now;
						self.onText(text);
					}
					self.loop();
				}).catch(function () { self.busy = false; self.loop(); });
			}, 110);
		},
		detect: function () {
			var v = this.video;
			if (this.detector) {
				return this.detector.detect(v).then(function (codes) { return codes && codes[0] ? codes[0].rawValue : null; });
			}
			if (typeof window.jsQR !== 'function') { return Promise.resolve(null); }
			var vw = v.videoWidth, vh = v.videoHeight;
			if (!vw || !vh) { return Promise.resolve(null); }
			// Center square crop, downscaled for speed.
			var side = Math.min(vw, vh), size = Math.min(640, side);
			var c = this.canvas;
			if (c.width !== size) { c.width = size; c.height = size; }
			var ctx = this.ctx || (this.ctx = c.getContext('2d', { willReadFrequently: true }));
			ctx.drawImage(v, (vw - side) / 2, (vh - side) / 2, side, side, 0, 0, size, size);
			var img = ctx.getImageData(0, 0, size, size);
			var res = window.jsQR(img.data, size, size, { inversionAttempts: 'dontInvert' });
			return Promise.resolve(res ? res.data : null);
		},
		onText: function (text) {
			if (this.mode === 'key') { onSetupKeyScanned(text); return; }
			handleCode(text, 'camera');
		}
	};

	document.addEventListener('visibilitychange', function () {
		if (document.hidden) {
			if (Cam.stream) { Cam.stop(); Cam.wantOn = true; }
		} else if (Cam.wantOn && app.getAttribute('data-screen') === 'main' && !$('[data-panel=scan]').hidden) {
			Cam.start();
		}
	});

	/* ==================================================================
	 * Setup
	 * ================================================================ */
	var setupForm = $('[data-setup-form]');
	var setupHome = Cam.viewport.parentNode;
	var setupHomeNext = Cam.viewport.nextSibling;

	function setupError(msg) {
		var el = $('[data-setup-error]');
		el.textContent = msg || '';
		el.hidden = !msg;
	}

	function activate(key, deviceName) {
		var btn = setupForm.querySelector('[type=submit]');
		btn.disabled = true;
		setupError('');
		return api('hello', { key: key }).then(function () {
			S.key = key;
			S.deviceName = deviceName || S.deviceName || '';
			S.deviceId = S.deviceId || uuid();
			return Promise.all([Store.set('key', S.key), Store.set('deviceId', S.deviceId), Store.set('deviceName', S.deviceName)]);
		}).then(function () {
			if (navigator.storage && navigator.storage.persist) { navigator.storage.persist().catch(function () {}); }
			show('main');
			return sync(true);
		}).catch(function (e) {
			setupError(e && e.status === 401 ? 'Clé invalide. Vérifiez-la dans l’administration.' : e && e.status === 429 ? 'Trop de tentatives, patientez quelques minutes.' : 'Connexion impossible. L’activation nécessite du réseau.');
		}).then(function () { btn.disabled = false; });
	}

	setupForm.addEventListener('submit', function (e) {
		e.preventDefault();
		unlockAudio();
		var key = normalizeKey(setupForm.key.value);
		if (key.replace(/-/g, '').length < 8) { setupError('Saisissez la clé complète.'); return; }
		stopKeyScan();
		activate(key, setupForm.device.value.trim());
	});

	function stopKeyScan() {
		if (Cam.mode !== 'key') { return; }
		Cam.stop();
		Cam.mode = 'guest';
		Cam.wantOn = false;
		setupHome.insertBefore(Cam.viewport, setupHomeNext);
	}

	$('[data-scan-key]').addEventListener('click', function () {
		if (Cam.mode === 'key') { stopKeyScan(); return; }
		Cam.mode = 'key';
		setupForm.insertBefore(Cam.viewport, setupForm.firstChild);
		Cam.start();
	});

	function onSetupKeyScanned(text) {
		if (!/^WRPKEY:/i.test(text)) { toast('Ce n’est pas le QR d’activation.'); return; }
		var key = normalizeKey(text);
		setupForm.key.value = key;
		vibrate([60]);
		stopKeyScan();
		activate(key, setupForm.device.value.trim());
	}

	/* ==================================================================
	 * Main UI events
	 * ================================================================ */
	$$('[data-tab]').forEach(function (b) {
		b.addEventListener('click', function () {
			var tab = b.getAttribute('data-tab');
			$$('[data-tab]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
			$$('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== tab; });
			if (tab === 'scan') {
				if (Cam.wantOn && !Cam.stream) { Cam.start(); }
			} else {
				if (Cam.stream) { Cam.stop(); Cam.wantOn = true; }
				if (tab === 'search') { renderSearch(); $('[data-search]').focus(); }
				if (tab === 'history') { renderHistory(); }
			}
		});
	});

	$('[data-cam-start]').addEventListener('click', function () { Cam.start(); });
	$('[data-sync]').addEventListener('click', function () { unlockAudio(); sync(true); });
	$('[data-search]').addEventListener('input', renderSearch);
	$('[data-search-results]').addEventListener('click', function (e) {
		var b = e.target.closest('[data-guest]');
		if (b) { unlockAudio(); handleGuest(S.byId[+b.getAttribute('data-guest')], true); }
	});
	$('[data-manual]').addEventListener('submit', function (e) {
		e.preventDefault();
		unlockAudio();
		var input = e.target.code;
		var v = input.value;
		input.value = '';
		input.blur();
		if (v.trim()) { handleCode(v, 'manual-code'); }
	});
	$('[data-r-close]').addEventListener('click', closeResult);
	$('[data-r-confirm]').addEventListener('click', function () {
		var fn = pendingConfirm;
		pendingConfirm = null;
		if (fn) { fn(); }
	});
	$('[data-result]').addEventListener('click', function (e) { if (e.target === e.currentTarget) { closeResult(); } });
	$('[data-logout]').addEventListener('click', function () {
		var pending = S.scans.filter(function (s) { return !s.synced; }).length;
		var msg = pending ? 'Attention : ' + pending + ' validation(s) pas encore envoyée(s) seront perdues. Déconnecter quand même ?' : 'Déconnecter ce téléphone et effacer la liste locale ?';
		if (!window.confirm(msg)) { return; }
		Cam.stop();
		Store.clear().then(function () { location.reload(); });
	});

	window.addEventListener('online', function () { render(); sync(false); });
	document.addEventListener('visibilitychange', function () { if (!document.hidden && S.key && navigator.onLine) { sync(false); } });

	// Update to the latest version (validations waiting to be sent are kept in the phone).
	$('[data-update]').addEventListener('click', function () {
		var clear = window.caches ? caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (k) { return k.indexOf('wrp-') === 0; }).map(function (k) { return caches.delete(k); }));
		}) : Promise.resolve();
		var upd = navigator.serviceWorker ? navigator.serviceWorker.getRegistration(CFG.scope).then(function (r) { return r && r.update(); }).catch(function () {}) : Promise.resolve();
		Promise.all([clear, upd]).catch(function () {}).then(function () { location.replace(location.pathname + '?v=' + Date.now().toString(36)); });
	});
	window.addEventListener('offline', render);
	setInterval(function () { if (navigator.onLine) { sync(false); } }, 45000);

	/* ==================================================================
	 * Boot
	 * ================================================================ */
	if ('serviceWorker' in navigator && CFG.sw) {
		navigator.serviceWorker.register(CFG.sw, { scope: CFG.scope }).catch(function (e) {
			if (window.console) { console.warn('Service worker non enregistré', e); }
		});
	}

	Promise.all([
		Store.get('key'), Store.get('deviceId'), Store.get('deviceName'),
		Store.get('list'), Store.get('scans'), Store.get('lastSync')
	]).then(function (v) {
		S.key = v[0] || '';
		S.deviceId = v[1] || '';
		S.deviceName = v[2] || '';
		S.list = v[3] || null;
		S.scans = Array.isArray(v[4]) ? v[4] : [];
		S.lastSync = v[5] || null;
		indexList();
		if (S.key) {
			show('main');
			render();
			sync(false);
		} else {
			show('setup');
			autoConfigure();
		}
		showBackLink();
	});

	/**
	 * Logged in through the « Espace mariés »? Configure the scanner automatically
	 * (no key to type).
	 */
	function autoConfigure() {
		var cached = null;
		try { cached = JSON.parse(localStorage.getItem('wrp_autokey_' + CFG.siteId)); } catch (e) {}
		var useKey = function (k, name) {
			if (!k) { return false; }
			setupForm.key.value = k;
			if (name && !setupForm.device.value) { setupForm.device.value = name; }
			activate(normalizeKey(k), name || '');
			return true;
		};
		if (!navigator.onLine || !CFG.ajax) {
			// Offline: trust the key handed over by the app; the list will download at the first sync.
			if (cached && cached.key) {
				S.key = normalizeKey(cached.key);
				S.deviceName = cached.name || '';
				S.deviceId = S.deviceId || uuid();
				Promise.all([Store.set('key', S.key), Store.set('deviceId', S.deviceId), Store.set('deviceName', S.deviceName)]);
				show('main');
				render();
			}
			return;
		}
		fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'wrp_app_session' }) })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				var d = res && res.success ? res.data : null;
				if (d && d.loggedIn && d.scannerKey) { useKey(d.scannerKey, d.user.name); }
				else if (cached) { useKey(cached.key, cached.name); }
			}).catch(function () { if (cached) { useKey(cached.key, cached.name); } });
	}

	function showBackLink() {
		var back = $('[data-back]');
		if (!back) { return; }
		var sess = null;
		try { sess = JSON.parse(localStorage.getItem('wrp_app_' + CFG.siteId + '_session')); } catch (e) {}
		back.hidden = !(sess && sess.loggedIn && sess.can && sess.can.manage);
	}

	// Expose for debugging / tests.
	window.WRPScanner = { state: S, handleCode: handleCode, sync: sync, sha256: sha256 };
})();
