/* Wedding RSVP Pass – scanner service worker (served by WordPress, placeholders replaced in PHP). */
'use strict';

var CACHE = 'wrp-scanner-__WRP_CACHE_VERSION__';
var ASSETS = __WRP_ASSETS__;
var APP_URL = __WRP_SCOPE_URL__;

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

	// Never cache the API: the app handles offline mode itself.
	if (url.pathname.indexOf('/wp-json/') > -1 || url.searchParams.has('rest_route')) { return; }

	// App shell: network first (fresh names/assets), fall back to cache when offline.
	if (req.mode === 'navigate') {
		event.respondWith(
			timeout(4000, fetch(req)).then(function (res) {
				if (res && res.ok) {
					var copy = res.clone();
					caches.open(CACHE).then(function (c) { c.put(APP_URL, copy); });
				}
				return res;
			}).catch(function () {
				return caches.match(APP_URL, { ignoreSearch: true }).then(function (r) {
					return r || new Response('<h1>Hors connexion</h1><p>Ouvrez le scanner une première fois avec du réseau.</p>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
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
