/* Wedding RSVP Pass – scanner service worker (served by WordPress, placeholders replaced in PHP). */
'use strict';

var CACHE = 'wrp-scanner-__WRP_CACHE_VERSION__';
var ASSETS = __WRP_ASSETS__;
var SHELLS = __WRP_SHELLS__; // [app, scanner]

function shellFor(url) {
	var path = new URL(url).pathname;
	for (var i = SHELLS.length - 1; i >= 0; i--) {
		if (path.indexOf(new URL(SHELLS[i]).pathname) === 0) { return SHELLS[i]; }
	}
	return SHELLS[0];
}

self.addEventListener('install', function (event) {
	event.waitUntil(
		caches.open(CACHE).then(function (cache) {
			// Cache one by one so a single failure does not break the install.
			return Promise.all(ASSETS.map(function (url) {
				return fetch(url, { cache: 'reload', credentials: 'same-origin' }).then(function (res) {
					if (res.ok) { return cache.put(url, res); }
				}).catch(function () {});
			}));
		}).then(function () { return self.skipWaiting(); })
	);
});

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (k) {
				return k.indexOf('wrp-scanner-') === 0 && k !== CACHE;
			}).map(function (k) { return caches.delete(k); }));
		}).then(function () { return self.clients.claim(); })
	);
});

function timeout(ms, promise) {
	return new Promise(function (resolve, reject) {
		var t = setTimeout(function () { reject(new Error('timeout')); }, ms);
		promise.then(function (r) { clearTimeout(t); resolve(r); }, function (e) { clearTimeout(t); reject(e); });
	});
}

self.addEventListener('fetch', function (event) {
	var req = event.request;
	if (req.method !== 'GET') { return; }
	var url = new URL(req.url);

	// Never cache the API / AJAX: the app handles offline mode itself.
	if (url.pathname.indexOf('/wp-json/') > -1 || url.searchParams.has('rest_route') || url.pathname.indexOf('admin-ajax.php') > -1) { return; }

	// App shell: network first (fresh names/assets), fall back to cache when offline.
	if (req.mode === 'navigate') {
		var shell = shellFor(req.url);
		event.respondWith(
			timeout(4000, fetch(req)).then(function (res) {
				if (res && res.ok) {
					var copy = res.clone();
					caches.open(CACHE).then(function (c) { c.put(shell, copy); });
				}
				return res;
			}).catch(function () {
				return caches.match(shell, { ignoreSearch: true }).then(function (r) {
					return r || new Response('<h1>Hors connexion</h1><p>Ouvrez l’application une première fois avec du réseau.</p>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
				});
			})
		);
		return;
	}

	// Static assets: cache first.
	event.respondWith(
		caches.match(req).then(function (hit) {
			if (hit) { return hit; }
			return fetch(req).then(function (res) {
				if (res && res.ok && url.origin === self.location.origin) {
					var copy = res.clone();
					caches.open(CACHE).then(function (c) { c.put(req, copy); });
				}
				return res;
			});
		})
	);
});
