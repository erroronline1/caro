/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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

// import relevant functions and set global scope

// main modules
import { api } from "./api.js";
window.api = api;
window.toasttimeout = {};
import { _serviceWorker, _client } from "./utility.js";
window._serviceWorker = _serviceWorker;
window._client = _client;

// necessary import due to calling from inline events prerendered by backend api
import { compose_helper } from "./compose.js";
window.compose_helper = compose_helper;

// during development, can be deleted during production, doesn't mess up the application though
import { rendertest, screenshot } from "../unittests/unittests.js";
window.rendertest = rendertest;
window.screenshot = screenshot;

// INITIALIZE APPLICATION

// serviceWorker
window.addEventListener("load", async () => {
	await _serviceWorker.register();
});

// scroll indicator event listener for vertical distance
window.addEventListener("scroll", function () {
	const percentScrolled = (window.scrollY / (document.body.clientHeight - window.innerHeight + 100)) * 100;
	document.querySelector("header>div:last-of-type").style.width = percentScrolled + "%";
});

// menu clearing event listener
window.addEventListener("pointerup", _client.application.clearMenu);

// add useragent to html tag to apply specific css attributes
if (navigator.userAgent.toLowerCase().includes("safari")) document.documentElement.setAttribute("data-useragent", "safari");
if (navigator.userAgent.toLowerCase().includes("chrome")) document.documentElement.removeAttribute("data-useragent");

// initial api requests
await api.application("get", "language");
api.application("post", "login"); //"start");
