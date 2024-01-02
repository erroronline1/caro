const cacheName = "2020240101_1638"; // Change value to force update


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
		}).then(()=>{clients.claim();})
	);
});

self.addEventListener("message", (event) => {
	console.log(`Message received: ${event.data}`);
});

// Network-first strategy
self.addEventListener("fetch", event => {
	event.respondWith(caches.open(cacheName).then((cache) => {
		// Go to the network first
		return fetch(event.request).then((fetchedResponse) => {
			cache.put(event.request, fetchedResponse.clone());
			return fetchedResponse;
		}).catch(() => {
			// If the network is unavailable, get
			return cache.match(event.request).then((response) => {
				let cacheResponse = new Response(response.body, {
					status: 203,
					statusText: "OK",
					headers: response.headers,
				});
				return cacheResponse;
			});
		});
	}));
});