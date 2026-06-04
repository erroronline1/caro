/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

// import relevant functions and set global scope

// main modules
import { api } from "./api.js";
window.api = api;
window.toasttimeout = {};
import { _serviceWorker, _client } from "./utility.js";
window._serviceWorker = _serviceWorker;
window._client = _client;
// observe changes to the canvas, updating masonry
import { Masonry } from "./assemble.js";
window.Masonry = new Masonry();
// necessary global import due to calling from inline events prerendered by backend api
import { Composer } from "./compose.js";
window.Composer = new Composer();

// during development, can be deleted during production, doesn't mess up the application though
import { rendertest, screenshot, request_param,jsMarkdown } from "../unittests/unittests.js";
window.rendertest = rendertest;
window.screenshot = screenshot;
window.request_param = request_param;
window.jsMarkdown = jsMarkdown;

// INITIALIZE APPLICATION

// scroll indicator event listener for vertical distance
window.addEventListener("scroll", function () {
	const percentScrolled = (window.scrollY / (document.body.clientHeight - window.innerHeight + 100)) * 100;
	document.querySelector("header>div:last-of-type").style.width = percentScrolled + "%";
});

// menu clearing event listener
window.addEventListener("pointerup", _client.application.clearMenu);

// hide fullscreen toggle if loaded as pwa
if (navigator.standalone || window.matchMedia('(display-mode: standalone)').matches) {
	document.querySelector("body > header > div:nth-of-type(1)").style.display = 'none';
}

// add useragent to html tag to apply specific css attributes
if (navigator.userAgent.toLowerCase().includes("safari")) document.documentElement.setAttribute("data-useragent", "safari");
if (navigator.userAgent.toLowerCase().includes("chrome")) document.documentElement.removeAttribute("data-useragent");

// initial api requests
api.application("get", null, "start");
