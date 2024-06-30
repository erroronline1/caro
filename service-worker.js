/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

const cacheName = "20240629_0101"; // Change value to force update
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
			let notifs = await fetch("api/api.php/notification/notifs/", {
				method: "GET",
				cache: "no-cache",
				body: null,
			}).then(
				async (response) => {
					return {
						status: response.status,
						body: await response.json(),
					};
				},
				() => {
					return undefined;
				}
			);
			client.postMessage(notifs.body);
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
					if (event.request.method === "GET") cache.put(event.request, fetchedResponse.clone());
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
							.then((response) => {
								// add cached request to deletion if response is successful, not if authorization fails
								if (response.status === 200) successfullyRequested.push(parseInt(key, 10));
							})
							.catch((error) => {
								console.log(error);
							});
					}
					if (successfullyRequested.length) await database.delete(successfullyRequested);
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
