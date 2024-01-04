const cacheName = "2020240101_1638"; // Change value to force update

importScripts('./libraries/erroronline1.js');

self.addEventListener("install", event => {
	// Kick out the old service worker
	self.skipWaiting();
	event.waitUntil(
		caches.open(cacheName)
	);
});

self.addEventListener("activate", event => {
	// Delete any non-current cache
	event.waitUntil(
		caches.keys().then(keys => {
			Promise.all(
				keys.map(key => {
					if (![cacheName].includes(key)) {
						return caches.delete(key);
					}
				})
			)
		}).then(() => {
			_.indexedDB.setup('caro', 'cached_post_put_delete')
			clients.claim();
		})
	);
});

self.addEventListener("message", (event) => {
	console.log(`Message received: ${event.data}`);
});

// Network-first strategy
self.addEventListener("fetch", event => {
	let cacheResponse = new Response(JSON.stringify({
		status: {
			msg: 'data unavailable due to unconnected network'
		}
	}, null, 2), {
		status: 503,
		statusText: "OK",
		headers: {
			'Content-Type': 'application/json'
		}
	});
	event.respondWith(caches.open(cacheName).then(async (cache) => {
		// Go to the network first
		return fetch(event.request).then((fetchedResponse) => {
			if (event.request.method === 'GET') cache.put(event.request, fetchedResponse.clone());
			return fetchedResponse;
		}).catch(async () => {
			// If the network is unavailable, get cached get requests or store post, put, delete
			if (event.request.method === 'GET') return cache.match(event.request).then((response) => {
				cacheResponse = new Response(response.body, {
					status: 203,
					statusText: "OK",
					headers: response.headers
				});
				return cacheResponse;
			}, (error) => {
				return cacheResponse
			});
			console.log('request: ', event.request);
			//return;
			_.indexedDB.add('cached_post_put_delete', event.request).then(
				() => {
					cacheResponse = new Response(JSON.stringify({
						status: {
							msg: 'request will be synced on next network access'
						}
					}, null, 2), {
						status: 203,
						statusText: "OK",
						headers: {
							'Content-Type': 'application/json'
						}
					});
					return cacheResponse;
				});
			return cacheResponse;
		});
	}));
});