const cacheName = "2020240101_1638"; // Change value to force update
let syncLock = false;
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
			_.indexedDB.setup('caro', 'cached_post_put_delete');
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
		const clonedRequest = await event.request.clone();
		return fetch(event.request).then(async (fetchedResponse) => {
			if (event.request.method === 'GET') cache.put(event.request, fetchedResponse.clone());
			//sync-event still marked as experimental as of 01/2024 so sync has to be performed on successful fetch request
			if (!syncLock) {
				syncLock = true;
				const cachedRequests = await _.indexedDB.keys('cached_post_put_delete');
				let successfullyRequested = [];
				for (const [key, entry] of Object.entries(cachedRequests)) {
					await fetch(entry.url, {
							method: entry.method,
							headers: {
								'Content-Type': entry.type
							},
							body: entry.request
						}).then(() => {
							successfullyRequested.push(key)
						})
						.catch(error => {
							console.log(error)
						});
				}
				console.log(await _.indexedDB.delete('cached_post_put_delete', successfullyRequested));
				syncLock = false;
			}
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
			// since get-requests should have been handled until here, do something with the others
			await _.indexedDB.add('cached_post_put_delete', {
				'method': clonedRequest.method,
				'url': clonedRequest.url,
				'type': clonedRequest.headers.get('content-type'),
				'request': await clonedRequest.blob()
			}).then(
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
				}).catch((error) => {
				return cacheResponse;
			});
			return cacheResponse;
		});
	}));
});