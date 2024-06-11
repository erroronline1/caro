import { assemble_helper, Dialog, Toast } from "./assemble.js";
import { compose_helper } from "./compose.js";

export const api = {
	/**
	 * tracks GET queries and alerts if a changed form is about to get left
	 */
	preventDataloss: {
		// explicitly start this on any eligible successFn
		// on appending queries (e.g. searches) set api.preventDataloss.monitor = false within query routing
		monitor: false,
		event: function (event) {
			// api must define data-loss=prevent for formfields that should be tracked once changed
			if (event.target.dataset.loss !== "prevent") return;
			api.preventDataloss.monitor = true;
		},
		start: function () {
			document.addEventListener("input", api.preventDataloss.event);
			document.addEventListener("textarea", api.preventDataloss.event);
		},
		stop: function () {
			document.removeEventListener("input", api.preventDataloss.event);
			document.removeEventListener("textarea", api.preventDataloss.event);
			api.preventDataloss.monitor = false;
		},
		proceedAnyway: async function (method) {
			if (api.preventDataloss.monitor && method.toUpperCase() === "GET") {
				const options = {};
				options[LANG.GET("general.prevent_dataloss_cancel")] = false;
				options[LANG.GET("general.prevent_dataloss_ok")] = {
					value: true,
					class: "reducedCTA",
				};
				return await new Dialog({
					type: "confirm",
					header: LANG.GET("general.prevent_dataloss"),
					options: options,
				}).then((response) => {
					return response;
				});
			}
			return true;
		},
	},
	/**
	 * handles prepared requests
	 * @param {string} method get|put|post|delete
	 * @param {array} request url parameters to pass to api
	 * @param {function} successFn handle result
	 * @param {function} errorFn handle error
	 * @param {object} payload FormData object|object
	 * @param {boolean} form_data tell _.api how to handle payload
	 * @returns none / results of successFn
	 */
	send: async (method, request, successFn = null, errorFn = null, payload = {}, form_data = false) => {
		if (!(await api.preventDataloss.proceedAnyway(method))) return false;
		api.preventDataloss.stop();
		api.loadindicator(true);
		await _.api(method, "api/api.php/" + request.join("/"), payload, form_data)
			.then(async (data) => {
				document.querySelector("header>div:first-of-type").style.display = data.status === 200 ? "none" : "block";
				if (data.status === 203) new Toast(LANG.GET("general.service_worker_get_cache_fallback"), "info");
				if (data.status === 207) {
					new Toast(LANG.GET("general.service_worker_post_cache_fallback"), "info");
					_serviceWorker.onPostCache();
					return;
				}
				if (successFn) await successFn(data.body);
			})
			.catch((error) => {
				console.trace(error);
				if (errorFn != null) errorFn(error);
				new Toast(error, "error");
			});
		api.loadindicator(false);
	},
	/**
	 * ui feedback on accuring requests that are expected to take longer
	 * @param {any} toggle initiates inidicator, undefined|null|false disables all
	 * @returns none
	 */
	loadindicator: (toggle) => {
		if (toggle) {
			api.loadindicatorTimeout.push(
				setTimeout(() => {
					document.querySelector("body").style.cursor = "wait";
					document.querySelector(".loader").style.display = "block";
					document.querySelector(".loader").style.opacity = "1";
				}, 500)
			); // wait a bit to avoid flash on every request
			return;
		}
		api.loadindicatorTimeout.map((id) => clearTimeout(id));
		api.loadindicatorTimeout = [];
		document.querySelector("body").style.cursor = "initial";
		document.querySelector(".loader").style.opacity = "0";
		setTimeout(() => {
			document.querySelector(".loader").style.display = "none";
		}, 300);
	},
	loadindicatorTimeout: [],
	/**
	 * sets the documents header and is supposed to scroll to top
	 * @param {string} string content
	 */
	update_header: function (string = "") {
		if (string) document.querySelector("header>h1").innerHTML = string;
		window.scrollTo({
			top: 0,
			behavior: "smooth",
		});
	},

	/**
	 * imports serverside defined languagefile
	 * handles user login/logout
	 * loads application menu
	 * loads application landing page
	 * manages manual
	 *
	 * @param {string} method get|post|put|delete
	 * @param {array} request api method, logintoken|manual id
	 * @returns request
	 */
	application: async (method, ...request) => {
		/*
		get application/language
		post application/login
		get application/menu
		get application/start
		*/
		request = [...request];
		request.splice(0, 0, "application");
		let successFn, payload;
		switch (request[1]) {
			case "language":
				successFn = async function (data) {
					window.LANGUAGEFILE = data.body;
				};
				break;
			case "login":
				payload = _.getInputs("[data-usecase=login]", true);

				successFn = async function (data) {
					await api.application("get", "menu");
					if (data.body.form) {
						const body = new Assemble(data.body);
						document.getElementById("main").replaceChildren(body.initializeSection());
						body.processAfterInsertion();
						let signin = LANG.GET("menu.application_signin"),
							greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
						api.update_header(
							LANG.GET("general.welcome_header", {
								":user": greeting,
							})
						);
						return;
					}
					if (data.body.image) {
						const firstLabel = document.querySelector("[data-for=userMenuApplication]>label");
						firstLabel.style.backgroundImage = "url('" + data.body.image + "')";
						firstLabel.style.maskImage = firstLabel.style.webkitMaskImage = "none";
					}
					if (data.body.app_settings) {
						for (const [key, value] of Object.entries(data.body.app_settings)) {
							switch (key) {
								case "forceDesktop":
									if (value) {
										let stylesheet = document.styleSheets[0].cssRules;
										for (let i = 0; i < stylesheet.length; i++) {
											if (stylesheet[i].conditionText === "only screen and (min-width: 64em)")
												stylesheet[i].media.mediaText = "only screen and (min-width: 4em)";
										}
									}
									break;
							}
						}
					}
					api.application("get", "start");
				};
				break;
			case "menu":
				successFn = function (data) {
					assemble_helper.userMenu(data.body);
				};
				break;
			case "start":
				successFn = function (data) {
					let signin = LANG.GET("menu.application_signin"),
						greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
					if (data.user) greeting = " " + data.user;
					api.update_header(
						LANG.GET("general.welcome_header", {
							":user": greeting,
						})
					);
					const body = new Assemble(data.body);
					document.getElementById("main").replaceChildren(body.initializeSection());
					body.processAfterInsertion();
				};
				break;
			case "manual":
				switch (method) {
					case "get":
						successFn = function (data) {
							api.update_header(LANG.GET("menu.application_manual_manager"));
							const body = new Assemble(data.body);
							document.getElementById("main").replaceChildren(body.initializeSection());
							body.processAfterInsertion();
						};
						break;
					case "delete":
						successFn = function (data) {
							new Toast(data.status.msg, data.status.type);
							api.application("get", request[1], data.status.id);
						};
						break;
					default:
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							api.application("get", request[1], data.status.id);
						};
						payload = _.getInputs("[data-usecase=manual]", true);
						break;
				}
				break;
			default:
				return;
		}
		await api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 * displays audit contents
	 *
	 * @param {string} method get|post|put|delete
	 * @param {array} request api method
	 * @returns request
	 */
	audit: (method, ...request) => {
		/*
		get audit/checks/{type}
		get audit/export/{type}
		*/
		request = [...request];
		request.splice(0, 0, "audit");
		let payload,
			successFn = function (data) {
				if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				if (data.body !== undefined) {
					const options = {};
					options[LANG.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						body: data.body,
						options: options,
					});
				}
			},
			title = {
				checks: LANG.GET("menu.audit"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "exportchecks":
					case "exportforms":
					case "exportvendors":
					case "exportregulatory":
					case "exportincorporation":
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
				}
				break;
			case "post":
				payload = _.getInputs("[data-usecase=audit]", true);
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 * planning and editing calendar entries
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	calendar: (method, ...request) => {
		/*
		get calendar/schedule
		get calendar/schedule/{date Y-m-d}/{date Y-m-d} // where first optional date accesses a week or month, second optional the exact specified date
		post calendar/schedule
		put calendar/schedule/{id}
		delete calendar/schedule/{id}
		*/
		request = [...request];
		request.splice(0, 0, "calendar");
		let payload,
			successFn = function (data) {
				if (data.body) {
					api.update_header(title[request[1]]);
					const body = new Assemble(data.body);
					document.getElementById("main").replaceChildren(body.initializeSection());
					body.processAfterInsertion();
					if (request[3] !== undefined) location.hash = "#displayspecificdate";
				}
				if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
			},
			title = {
				schedule: LANG.GET("menu.calendar_scheduling"),
				timesheet: LANG.GET("menu.calendar_timesheet"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "monthlyTimesheets":
						successFn = function (data) {
							if (data.body !== undefined) {
								const options = {};
								options[LANG.GET("general.ok_button")] = false;
								new Dialog({
									type: "input",
									body: data.body,
									options: options,
								});
							}
						};
				}
				break;
			case "post":
				payload = window.calendarFormData; // as prepared by utility.js _client.calendar.createFormData()
				break;
			case "put":
				switch (request[1]) {
					case "complete":
						break;
					default:
						payload = window.calendarFormData; // as prepared by utility.js _client.calendar.createFormData()
				}
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 * loads, executes and manages csv filters
	 *
	 * @param {string} method get|post
	 * @param {array} request api method
	 * @returns request
	 */
	csvfilter: (method, ...request) => {
		/*
		post csvfilter/rule
		get csvfilter/rule

		post csvfilter/filter
		get csvfilter/filter
		*/
		request = [...request];
		request.splice(0, 0, "csvfilter");
		let payload,
			successFn = function (data) {
				if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				if (data.log !== undefined) {
					const dialog = {
						type: "input",
						body: [
							{ type: "text", content: data.log.join("\n") },
							{ type: "links", content: data.links },
						],
					};
					new Dialog(dialog);
				}
			},
			title = {
				rule: LANG.GET("menu.csvfilter_filter_manager"),
				filter: LANG.GET("menu.csvfilter_filter"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.body) {
						api.update_header(title[request[1]]);
						const body = new Assemble(data.body);
						document.getElementById("main").replaceChildren(body.initializeSection());
						body.processAfterInsertion();
					}
					if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				};
				break;
			case "post":
				payload = _.getInputs("[data-usecase=csvfilter]", true);
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 * displays and manages provided files, either administrative managed or open sharepoint
	 *
	 * @param {string} method get|post|delete
	 * @param  {array} request api method, search term / bundle name | requested directory name, requested filename
	 * @returns request
	 */
	file: async (method, ...request) => {
		/*
		get file/filter/{directory}

		get file/files/{directory}

		post file/filemanager
		get file/filemanager/{directory}
		delete file/filemanager/{directory}/{file}

		post file/externalfilemanager
		put file/externalfilemanager/{id}/{int accessible}
		get file/externalfilemanager

		get file/bundle/{bundle}

		post file/bundlemanager
		get file/bundlemanager/{bundle}

		post file/sharepoint
		get file/sharepoint
		*/
		request = [...request];
		request.splice(0, 0, "file");
		let successFn = function (data) {
				if (data.body) {
					api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
					const body = new Assemble(data.body);
					document.getElementById("main").replaceChildren(body.initializeSection());
					body.processAfterInsertion();
				}
				if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				if (data.status !== undefined && data.status.redirect !== undefined) api.file("get", ...data.status.redirect);
			},
			payload,
			title = {
				files: LANG.GET("menu.files_files"),
				bundle: LANG.GET("menu.files_bundles"),
				sharepoint: LANG.GET("menu.files_sharepoint"),
				filemanager: LANG.GET("menu.files_file_manager"),
				bundlemanager: LANG.GET("menu.files_bundle_manager"),
				externalfilemanager: LANG.GET("menu.files_external_file_manager"),
			};

		switch (method) {
			case "get":
				switch (request[1]) {
					case "filter":
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const file of all) {
									if (request[1] === "bundle") {
										file.parentNode.style.display = data.status.data.includes(file.dataset.filtered) ? "block" : "none";
									} else file.style.display = data.status.data.includes(file.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					case "bundlefilter":
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const list of all) {
									if (isNaN(list.dataset.filtered)) continue;
									list.parentNode.style.display = data.status.data.includes(list.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
				}
				break;
			case "post":
				successFn = function (data) {
					if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
					if (data.status !== undefined && data.status.redirect !== undefined) api.file("get", ...data.status.redirect);
				};
				payload = _.getInputs("[data-usecase=file]", true);
				break;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 * form component and form management with creation, editing and approval
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	form: (method, ...request) => {
		/*
		get form elements from database.
		notice only the first requested form will appear. later duplicates will be ignored.

		get form/component_editor/{name|id}
		get form/form_editor/{name|id}

		get form/component/{name}
		post form/component
		delete form/component/{id}

		get form/approval/{id}
		put form/approval/{id}

		get form/form/{name}
		post form/form
		delete form/form/{id}
		*/
		request = [...request];
		request.splice(0, 0, "form");
		let successFn,
			payload,
			title = {
				component_editor: LANG.GET("menu.forms_manage_components"),
				form_editor: LANG.GET("menu.forms_manage_forms"),
				approval: LANG.GET("menu.forms_manage_approval"),
				bundle: LANG.GET("menu.forms_manage_bundles"),
			},
			composedComponent;
		switch (method) {
			case "get":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.body) {
								data.body.content.name = data.name;
								if (data.body.content) compose_helper.importForm([data.body.content]);
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					case "component_editor":
						compose_helper.componentIdentify = 0;
						compose_helper.componentSignature = 0;
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const body = new Compose(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
								if (data.body.component) compose_helper.importComponent(data.body.component);
								// create multipart form for file uploads
								compose_helper.addComponentMultipartFormToMain();
								api.preventDataloss.start();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					case "approval":
					case "bundle":
					case "form":
						api.update_header(title[request[1]]);
						successFn = function (data) {
							api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
							if (data.body) {
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					case "form_editor":
						compose_helper.componentIdentify = 0;
						compose_helper.componentSignature = 0;
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const body = new Compose(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
								if (data.body.components) compose_helper.importForm(data.body.components);
								api.preventDataloss.start();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
				}
				break;
			case "put":
				switch (request[1]) {
					case "approval":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							if (data.status !== undefined && data.status.reload !== undefined) api.form("get", data.status.reload);
						};
						payload = _.getInputs("[data-usecase=approval]", true);
						break;
				}
				break;
			case "post":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							if (data.status !== undefined && data.status.reload !== undefined) api.form("get", data.status.reload);
						};
						composedComponent = compose_helper.composeNewComponent();
						if (!composedComponent) return;
						compose_helper.addComponentStructureToComponentForm(composedComponent);
						payload = _.getInputs("[data-usecase=component_editor_form]", true);
						break;
					case "form":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							if (data.status !== undefined && data.status.reload !== undefined) api.form("get", data.status.reload);
						};
						if (!(payload = compose_helper.composeNewForm())) return;
						break;
					case "bundle":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						payload = _.getInputs("[data-usecase=bundle]", true);
						break;
				}
				break;
			case "delete":
				successFn = function (data) {
					if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
					if (data.status !== undefined && data.status.reload !== undefined) api.form("get", data.status.reload);
				};
				break;
		}
		api.send(method, request, successFn, null, payload, composedComponent || request[1] === "bundle" || method === "put");
	},

	/**
	 * handles internal messenger
	 *
	 * @param {string} method get|post|delete
	 * @param  {array} request api method, filter term / message id / message form data, occasionally query selector
	 * @returns request
	 */
	message: (method, ...request) => {
		/*
		get message/conversation/{conversation_user}
		delete message/conversation/{conversation_user}

		post message/message/{formdata}

		get message/register

		to initiate a new message with (hidden or visible inputs having both the same unique queryselector) prepared recipient and message using _.getinputs(queryselector)
		call by api.message('get', 'message', '{queryselector}') 
		results in get message/message?to=recipient&message=messagetext
		alternatively prepare third parameter as formdata
		*/
		request = [...request];
		request.splice(0, 0, "message");
		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
				if (data.status !== undefined && data.status.redirect) api.message("get", data.status.redirect);
			},
			title = {
				conversation: LANG.GET("menu.message_conversations"),
				register: LANG.GET("menu.message_register"),
			};

		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.body) {
						api.update_header(title[request[1]]);
						const body = new Assemble(data.body);
						document.getElementById("main").replaceChildren(body.initializeSection());
						body.processAfterInsertion();
						if (request[2]) window.scrollTo(0, document.body.scrollHeight);
					}
					if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
					if (request[1] === "inbox" && _serviceWorker.worker)
						_serviceWorker.onMessage({
							unseen: 0,
						});
				};
				break;
			case "post":
				if (2 in request && request[2] && typeof request[2] === "object") {
					//passed formdata
					payload = request[2];
					delete request[2];
				} else payload = _.getInputs("[data-usecase=message]", true);
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 * handles vendor and product management
	 * handles orders
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, id / name / filter term, occasionally subrequest, occasionally message
	 * @returns request
	 */
	purchase: (method, ...request) => {
		/*
		get consumables/vendor/{id|name}
		post consumables/vendor
		put consumables/vendor/{id}

		get consumables/product/{id}
		get consumables/product/{id|name}/search
		post consumables/product
		put consumables/product/{id}
		delete consumables/product/{id}

		post consumables/mdrsamplecheck
		get consumables/mdrsamplecheck
		delete consumables/mdrsamplecheck
		post consumables/incorporation
		get consumables/incorporation
		get consumables/pendingincorporations

		get order/prepared/{unit}
		get order/productsearch/{id|name}
		get order/order/{id}
		post order/order
		put order/order/{id}
		delete order/order/{id}

		get order/approved/
		put order/approved/{id}/{ordered|received|archived|disapproved}/{message}
		delete order/approved/{id}

		get order/filtered/{filter}
		*/
		request = [...request];
		if (["vendor", "product", "mdrsamplecheck", "incorporation", "pendingincorporations"].includes(request[0]))
			request.splice(0, 0, "consumables");
		else request.splice(0, 0, "order");

		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
				if (data.status.type !== "error") api.purchase("get", request[1], data.status.id);
			},
			title = {
				vendor: LANG.GET("menu.purchase_vendor"),
				product: LANG.GET("menu.purchase_product"),
				order: LANG.GET("menu.purchase_order"),
				prepared: LANG.GET("menu.purchase_prepared_orders"),
				approved: LANG.GET("menu.purchase_approved_orders"),
				pendingincorporations: LANG.GET("menu.purchase_incorporated_pending"),
			};
		if (request[2] === LANG.GET("consumables.edit_existing_vendors_new")) request.splice(2, 1);
		switch (method) {
			case "get":
				switch (request[1]) {
					case "productsearch":
						api.preventDataloss.monitor = false;
						successFn = function (data) {
							let list = document.querySelector("hr").previousElementSibling;
							if (list.previousElementSibling) list.remove();
							if (data.body.content) {
								const body = new Assemble(data.body);
								body.initializeSection("hr");
								body.processAfterInsertion();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							api.preventDataloss.monitor = true;
						};
						break;
					case "filter":
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const order of all) {
									order.parentNode.style.display = data.status.data.includes(order.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					case "incorporation":
						successFn = function (data) {
							if (data.body) {
								new Dialog({
									type: "input",
									header: LANG.GET("order.incorporation"),
									body: data.body.content,
									options: data.body.options,
								}).then((response) => {
									if (response) {
										_client.order.performIncorporation(response, data.body.productid);
									}
								});
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					case "mdrsamplecheck":
						successFn = function (data) {
							if (data.body) {
								new Dialog({
									type: "input",
									header: LANG.GET("order.sample_check"),
									body: data.body.content,
									options: data.body.options,
								}).then((response) => {
									if (response) {
										_client.order.performSampleCheck(response, data.body.productid);
									}
								});
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
								if (request[1] === "approved") _client.order.filter();
								api.preventDataloss.start();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
				}
				break;
			case "post":
				switch (request[1]) {
					case "incorporation":
					case "mdrsamplecheck":
						payload = request[3]; // form data object passed by utility.js
						delete request[3];
						successFn = function (data) {
							new Toast(data.status.msg, data.status.type);
						};
						break;
					default:
						payload = _.getInputs("[data-usecase=purchase]", true);
				}
				break;
			case "put":
				if (
					["ordered", "received", "archived", "disapproved", "cancellation", "return", "addinformation"].includes(request[3])
				) {
					if (typeof request[4] === "object") {
						payload = request[4];
						delete request[4];
					}
					successFn = function (data) {
						new Toast(data.status.msg, data.status.type);
					};
				}
				if (request[1] == "prepared") {
					successFn = function (data) {
						new Toast(data.status.msg, data.status.type);
						api.purchase("get", "prepared");
					};
				}
				if (request[1] !== "approved" && !payload) payload = _.getInputs("[data-usecase=purchase]", true); // exclude status updates
				break;
			case "delete":
				switch (request[1]) {
					case "mdrsamplecheck":
						successFn = function (data) {
							new Toast(data.status.msg, data.status.type);
						};
						break;
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 * handles records, displays record lists
	 * imports data from other records
	 *
	 * @param {string} method get|post
	 * @param  {array} request api method, occasional identifier to import from
	 * @returns request
	 */
	record: (method, ...request) => {
		/*
		post record/identifier
		get record/identifier

		get record/forms
		get record/form/{optional identifier}

		get record/formfilter
		get record/identifierfilter

		post record/record

		put record/close/{identifier}

		get record/import
		get record/records
		get records/export
		*/
		request = [...request];
		request.splice(0, 0, "record");
		let payload,
			successFn = function (data) {
				if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				if (data.body !== undefined) {
					const options = {};
					options[LANG.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						body: data.body,
						options: options,
					});
				}
			},
			title = {
				identifier: LANG.GET("menu.record_create_identifier"),
				forms: LANG.GET("menu.record_record"),
				records: LANG.GET("menu.record_summary"),
				record: LANG.GET("menu.record_summary"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "recordfilter":
					case "formfilter":
						api.preventDataloss.monitor = false;
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll("[data-filtered]"),
									exceeding = document.querySelectorAll("[data-filtered_max]");
								for (const element of all) {
									if (data.status.filter === undefined || data.status.filter == "some")
										element.style.display = data.status.data.includes(element.dataset.filtered) ? "block" : "none";
									else
										element.style.display =
											data.status.data.includes(element.dataset.filtered) && ![...exceeding].includes(element)
												? "block"
												: "none";
								}
							}
						};
						break;
					case "import":
						successFn = function (data) {
							if (data.status !== undefined) {
								if (data.status.data !== undefined) {
									let inputs = document.querySelectorAll("input, textarea, select");
									let inputname, groupname, files, a;
									for (const input of inputs) {
										inputname = input.name.replaceAll(" ", "_");
										if (input.type === "file") {
											if (Object.keys(data.status.data).includes(inputname.replace("[]", ""))) {
												files = data.status.data[inputname.replace("[]", "")].split(", ");
												for (const file of files) {
													a = document.createElement("a");
													a.href = file;
													a.target = "_blank";
													a.append(document.createTextNode(file));
													input.parentNode.insertBefore(a, input);
												}
												if (files) input.parentNode.insertBefore(document.createElement("br"), input);
											}
										} else if (input.type === "radio") {
											// nest to avoid overriding values of other radio elements
											input.checked =
												Object.keys(data.status.data).includes(inputname) && data.status.data[inputname] === input.value;
										} else if (input.type === "checkbox") {
											groupname = input.dataset.grouped.replaceAll(" ", "_");
											input.checked =
												Object.keys(data.status.data).includes(groupname) &&
												data.status.data[groupname].split(", ").includes(input.name);
										} else {
											if (Object.keys(data.status.data).includes(inputname)) input.value = data.status.data[inputname];
										}
									}
								}
								if (data.status.msg !== undefined) {
									const options = {};
									options[LANG.GET("record.record_import_ok")] = false;
									options[LANG.GET("record.record_import_clear_identifier")] = {
										value: true,
										class: "reducedCTA",
									};

									new Dialog({
										type: "confirm",
										header: LANG.GET("assemble.compose_merge"),
										options: options,
										body: data.status.msg,
									}).then((response) => {
										if (response) document.querySelector("input[name^=IDENTIFY_BY_]").value = "";
									});
								}
							}
						};
						payload = { IDENTIFY_BY_: request[2] };
						break;
					case "displayonly":
						// for linked forms within forms
						successFn = function (data) {
							if (data.body) {
								const options = {};
								options[LANG.GET("general.ok_button")] = false;
								new Dialog({ type: "input", header: data.title, body: data.body, options: options });
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						request[1] = "form";
						break;
					case "fullexport":
					case "simplifiedexport":
					case "formexport": // sorry. exports a form with records
					case "exportform": // exports the empty form as editable pdf
					case "matchbundles":
						//prevent default successFn
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]] || data.title);
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
								api.preventDataloss.start();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
				}
				break;
			case "post":
				payload = _.getInputs("[data-usecase=record]", true);
				break;
			case "put":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 * manages text templates
	 * displays text template frontend either as body or within a modal
	 *
	 * @param {string} method get|post
	 * @param  {array} request api method, occasional modal destination
	 * @returns request
	 */
	texttemplate: (method, ...request) => {
		/*
		post texttemplate/chunk
		get texttemplate/chunk

		post texttemplate/template
		get texttemplate/template

		get texttemplate/text opens form properly from menu
		get texttemplate/text/modal opens form within modal
		*/
		request = [...request];
		request.splice(0, 0, "texttemplate");
		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
			},
			title = {
				chunk: LANG.GET("menu.texttemplate_chunks"),
				template: LANG.GET("menu.texttemplate_templates"),
				text: LANG.GET("menu.texttemplate_texts"),
			};
		switch (method) {
			case "get":
				switch (request[3]) {
					case "modal":
						successFn = function (data) {
							if (data.body) {
								new Dialog({ type: "input", header: LANG.GET("menu.texttemplate_texts"), body: data.body });
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							if (data.data !== undefined) _client.texttemplate.data = data.data;
							if (data.selected !== undefined && data.selected.length) {
								compose_helper.importTextTemplate(data.selected);
							}
						};
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							if (data.data !== undefined) _client.texttemplate.data = data.data;
							if (data.selected !== undefined && data.selected.length) {
								compose_helper.importTextTemplate(data.selected);
							}
							api.preventDataloss.start();
						};
				}
				break;
			case "post":
				switch (request[1]) {
					case "template":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						if (!(payload = compose_helper.composeNewTextTemplate())) return;
						break;
					default:
						payload = _.getInputs("[data-usecase=texttemplate]", true);
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" && request[1] !== "template");
	},

	/**
	 * displays 2d code form and result passed by get query
	 * displays a generic 2d scanner
	 * displays stl viewer for files managed by filemanager and open sharepoint
	 *
	 * @param {string} method get
	 * @param  {array} request api method, occasionally passed values for 2d codes
	 * @returns request
	 */
	tool: (method, ...request) => {
		/*
		get tool/code
		get tool/code/display?key=value

		get tool/scanner
		
		get tool/stlviewer
		*/
		request = [...request];
		request.splice(0, 0, "tool");
		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
				api.user("get", request[1], data.status.id);
			},
			title = {
				code: LANG.GET("menu.tools_digital_codes"),
				scanner: LANG.GET("menu.tools_scanner"),
				stlviewer: LANG.GET("menu.tools_stl_viewer"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.body) {
						api.update_header(title[request[1]]);
						const body = new Assemble(data.body);
						document.getElementById("main").replaceChildren(body.initializeSection());
						body.processAfterInsertion();
					}
					if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				};
				if (request[3] === "display") {
					payload = _.getInputs("[data-usecase=tool_create_code]");
				}
				break;
			case "post":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 * user manager and display of profile
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	user: (method, ...request) => {
		/*
		get user/profile
		get user/user/{id|name}
		post user/user
		put user/user/{id}
		delete user/user/{id}
		*/
		request = [...request];
		request.splice(0, 0, "user");
		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
				api.user("get", request[1], data.status.id);
			},
			title = {
				profile: LANG.GET("menu.application_user_profile"),
				user: LANG.GET("menu.application_user_manager"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.body) {
						api.update_header(title[request[1]]);
						const body = new Assemble(data.body);
						document.getElementById("main").replaceChildren(body.initializeSection());
						body.processAfterInsertion();
					}
					if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
				};
				break;
			case "post":
				payload = _.getInputs("[data-usecase=user]", true);
				break;
			case "put":
				payload = _.getInputs("[data-usecase=user]", true);
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},
};
