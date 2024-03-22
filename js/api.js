import { assemble_helper, Dialog, Toast } from "./assemble.js";
import { compose_helper } from "./compose.js";

export const api = {
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
	send: async (method, request, successFn = null, errorFn = null, payload = {}, form_data = false) => {
		if (!(await api.preventDataloss.proceedAnyway(method))) return false;
		api.preventDataloss.stop();
		api.loadindicator(true);
		await _.api(method, "api/api.php/" + request.join("/"), payload, form_data)
			.then(async (data) => {
				if (data.status === 203) api.toast(LANG.GET("general.service_worker_get_cache_fallback"));
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
	update_header: function (string = "") {
		document.querySelector("header>h1").innerHTML = string;
		window.scrollTo({
			top: 0,
			behavior: "smooth",
		});
	},
	application: async (method, ...request) => {
		/*
		get application/language
		get application/login
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
				const logintoken = document.querySelector("[data-usecase=login]");
				if (logintoken) {
					request.push(logintoken.value ? logintoken.value : null);
				}
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
						const firstLabel = document.querySelector("nav>div:first-child>label");
						firstLabel.style.backgroundImage = "url('" + data.body.image + "')";
						firstLabel.style.maskImage = firstLabel.style.webkitMaskImage = "none";
					}
					if (data.body.app_settings)
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
	file: async (method, ...request) => {
		/*
		get file/filter/{directory}

		get file/files/{directory}

		post file/filemanager
		get file/filemanager/{directory}
		delete file/filemanager/{directory}/{file}

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
					api.update_header(title[request[1]]);
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
	form: (method, ...request) => {
		/*
		get form elements from database.
		notice only the first requested form will appear. later duplicates will be ignored.

		get form/component_editor/{name}
		get form/form_editor/{name}

		get form/component/{name}
		post form/component

		get form/form/{name}
		post form/form
		*/
		request = [...request];
		request.splice(0, 0, "form");
		let successFn,
			payload,
			title = {
				component_editor: LANG.GET("menu.forms_manage_components"),
				form_editor: LANG.GET("menu.forms_manage_forms"),
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
								api.update_header(title[request[1]]);
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
					case "bundle":
					case "form":
						successFn = function (data) {
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
								api.update_header(title[request[1]]);
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
			case "post":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						composedComponent = compose_helper.composeNewComponent();
						if (!composedComponent) return;
						compose_helper.addComponentStructureToComponentForm(composedComponent);
						payload = _.getInputs("[data-usecase=component_editor_form]", true);
						break;
					case "form":
						successFn = function (data) {
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
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
		}
		api.send(method, request, successFn, null, payload, composedComponent || request[1] === "bundle");
	},
	message: (method, ...request) => {
		/*
		get message/inbox
		get message/sent
		get message/filter/{filter}

		get message/message/ // empty form
		post message/message/{formdata}
		delete message/message/{id}/(inbox|sent)

		// to initiate a new message with (hidden or visible inputs having both the same unique queryselector) prepared recipient and message using _.getinputs(queryselector)
		call by api.message('get', 'message', '{queryselector}') 
		results in get message/message?to=recipient&message=messagetext

		get notification // (returns number of unnotified/unread messages, only used within service-worker)

		*/
		request = [...request];
		request.splice(0, 0, "message");
		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
				if (data.status !== undefined && data.status.redirect) api.message("get", data.status.redirect);
			},
			title = {
				inbox: LANG.GET("menu.message_inbox"),
				sent: LANG.GET("menu.message_sent"),
				message: LANG.GET("menu.message_new"),
			};

		switch (method) {
			case "get":
				switch (request[1]) {
					case "filter":
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const file of all) {
									file.parentNode.style.display = data.status.data.includes(file.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
								api.preventDataloss.start();
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
							if (request[1] === "inbox" && _serviceWorker.worker)
								_serviceWorker.onMessage({
									unseen: 0,
								});
						};
						if (request[2]) payload = _.getInputs(request[2]);
						break;
				}
				break;
			case "post":
				console.log(request);
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
		post consumables/incorporation
		get consumables/incorporation

		get order/prepared
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
		if (["vendor", "product", "mdrsamplecheck", "incorporation"].includes(request[0])) request.splice(0, 0, "consumables");
		else request.splice(0, 0, "order");

		let payload,
			successFn = function (data) {
				new Toast(data.status.msg, data.status.type);
				api.purchase("get", request[1], data.status.id);
			},
			title = {
				vendor: LANG.GET("menu.purchase_vendor"),
				product: LANG.GET("menu.purchase_product"),
				order: LANG.GET("menu.purchase_order"),
				prepared: LANG.GET("menu.purchase_prepared_orders"),
				approved: LANG.GET("menu.purchase_approved_orders"),
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
										orderClient.performIncorporation(response, data.body.productid);
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
										orderClient.performSampleCheck(response, data.body.productid);
									}
								});
							}
							if (data.status !== undefined && data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
						};
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								const body = new Assemble(data.body);
								document.getElementById("main").replaceChildren(body.initializeSection());
								body.processAfterInsertion();
								if (request[1] === "approved") orderClient.filter();
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
				if (["ordered", "received", "archived", "disapproved", "addinformation"].includes(request[3])) {
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
				if (request[1] !== "approved") payload = _.getInputs("[data-usecase=purchase]", true); // exclude status updates
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},
	record: (method, ...request) => {
		/*
		post record/identifier
		get record/identifier

		get record/forms
		get record/form/{optional identifier}

		get record/formfilter
		get record/identifierfilter

		post record/record

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
								if (data.status.msg !== undefined) new Toast(data.status.msg, data.status.type);
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
							}
						};
						payload = { IDENTIFY_BY_: request[2] };
						break;
					case "export":
					case "exportform":
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
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},
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
							if (data.data !== undefined) texttemplateClient.data = data.data;
							if (data.selected !== undefined && data.selected.length) {
								compose_helper.importTextTemplate(data.selected);
							}
						};
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
							if (data.data !== undefined) texttemplateClient.data = data.data;
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
	tool: (method, ...request) => {
		/*
		get tool/code
		get tool/code/display?key=value
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
				//payload = _.getInputs('[data-usecase=user]', true);
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},
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
