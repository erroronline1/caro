const cacheName = "2020240107_0101"; // Change value to force update
importScripts("./libraries/erroronline1.js");
var database = _.idb;
database.database = {
	name: "caro",
	table: "cached_post_put_delete",
};

addEventListener("message", async (message) => {
	client = message.source;
	//do something with message.data
	switch (message.data) {
		case "getnotifications":
			let count = await fetch("api/api.php/message/notification/", {
				method: "GET",
				cache: "no-cache",
				body: null,
			}).then(
				async (response) => {
					if (response.statusText === "OK")
						return {
							status: response.status,
							body: await response.json(),
						};
					else return undefined;
				},
				() => {
					return undefined;
				}
			);
			if (count)
				client.postMessage({
					unnotified: count.body.unnotified,
					unseen: count.body.unseen,
				});
			break;
	}
});

self.addEventListener("install", (event) => {
	// Kick out the old service worker
	self.skipWaiting();
	event.waitUntil(caches.open(cacheName));
});

self.addEventListener("activate", (event) => {
	// Delete any non-current cache
	event.waitUntil(
		caches
			.keys()
			.then((keys) => {
				Promise.all(
					keys.map((key) => {
						if (![cacheName].includes(key)) {
							return caches.delete(key);
						}
					})
				);
			})
			.then(() => {
				clients.claim();
			})
	);
});

// Network-first strategy
self.addEventListener("fetch", (event) => {
	let cacheResponse = new Response(
		JSON.stringify(
			{
				status: {
					msg: "data unavailable due to unconnected network",
				},
			},
			null,
			2
		),
		{
			status: 503,
			statusText: "OK",
			headers: {
				"Content-Type": "application/json",
			},
		}
	);
	event.respondWith(
		caches.open(cacheName).then(async (cache) => {
			// Go to the network first
			const clonedRequest = await event.request.clone();
			return fetch(event.request)
				.then(async (fetchedResponse) => {
					if (event.request.method === "GET")
						cache.put(event.request, fetchedResponse.clone());
					//sync-event still marked as experimental as of 01/2024 so sync has to be performed on successful fetch request
					const cachedRequests = await database.all();
					let successfullyRequested = [];
					for (const [key, entry] of Object.entries(cachedRequests)) {
						await fetch(entry.url, {
							method: entry.method,
							headers: {
								"Content-Type": entry.type,
							},
							body: entry.request,
						})
							.then(() => {
								successfullyRequested.push(parseInt(key, 10));
							})
							.catch((error) => {
								console.log(error);
							});
					}
					if (successfullyRequested.length)
						await database.delete(successfullyRequested);
					return fetchedResponse;
				})
				.catch(async () => {
					// If the network is unavailable, get cached get requests or store post, put, delete
					if (event.request.method === "GET")
						return cache.match(event.request).then(
							(response) => {
								cacheResponse = new Response(response.body, {
									status: 203,
									statusText: "OK",
									headers: response.headers,
								});
								return cacheResponse;
							},
							(error) => {
								return cacheResponse;
							}
						);
					// since get-requests should have been handled until here, do something with the others
					let data = {
						method: clonedRequest.method,
						url: clonedRequest.url,
						type: clonedRequest.headers.get("content-type"),
						request: await clonedRequest.blob(),
					};
					await database.add(data).then(
						() => {
							cacheResponse = new Response(
								JSON.stringify(
									{
										status: {
											msg: "request will be synced on next network access",
										},
									},
									null,
									2
								),
								{
									status: 207,
									statusText: "OK",
									headers: {
										"Content-Type": "application/json",
									},
								}
							);
							return cacheResponse;
						},
						(error) => {
							console.log(error);
							return cacheResponse;
						}
					);
					return cacheResponse;
				});
		})
	);
});
