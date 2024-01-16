import {
	assemble_helper
} from './assemble.js';

export const api = {
	preventDataloss: {
		// explicitly start this on any eligible successFn
		// on appending queries (e.g. searches) set api.preventDataloss.monitor = false within query routing
		monitor: false,
		event: function (event) {
			// api must define data-loss=prevent for formfields that should be tracked
			if (event.target.dataset.loss !== 'prevent') return;
			api.preventDataloss.monitor = true;
		},
		start: function () {
			document.addEventListener('input', api.preventDataloss.event);
		},
		stop: function () {
			document.removeEventListener('input', api.preventDataloss.event);
			api.preventDataloss.monitor = false;
		},
		proceedAnyway: function (method) {
			if (api.preventDataloss.monitor && method.toUpperCase() === 'GET')
				return confirm(LANG.GET('general.prevent_dataloss'))
			return true;
		}
	},
	send: async (method, request, successFn = null, errorFn = null, payload = {}, form_data = false) => {
		// default disable camera stream
		const scanner = document.querySelector('video');
		if (scanner) scanner.srcObject.getTracks()[0].stop();
		if (!api.preventDataloss.proceedAnyway(method)) return false;
		api.preventDataloss.stop()
		api.loadindicator(true);
		await _.api(method, 'api/api.php/' + request.join('/'), payload, form_data)
			.then(async data => {
				if (data.status === 203) api.toast(LANG.GET('general.service_worker_get_cache_fallback'));
				if (data.status === 207) {
					api.toast(LANG.GET('general.service_worker_post_cache_fallback'));
					_serviceWorker.onPostCache();
					return;
				}
				if (successFn) await successFn(data.body);
			})
			.catch((error) => {
				console.trace(error);
				if (errorFn != null) errorFn(error);
				else api.toast(error);
			});
		api.loadindicator(false);
	},
	loadindicator: (toggle) => {
		if (toggle) {
			document.querySelector('body').style.cursor = 'wait';
			document.querySelector('.loader').style.display = 'block';
			document.querySelector('.loader').style.opacity = '1';
			return;
		}
		document.querySelector('body').style.cursor = 'initial';
		document.querySelector('.loader').style.opacity = '0';
		setTimeout(() => {
			document.querySelector('.loader').style.display = 'none'
		}, 300);

	},
	toast: function (msg) {
		const toast = document.querySelector('dialog');
		if (typeof msg !== 'undefined') {
			toast.innerHTML = msg;
			toast.show();
			window.setTimeout(api.toast, 3000);
		} else toast.close();
	},
	update_header: function (string = '') {
		document.querySelector('header>h1').innerHTML = string;
		window.scrollTo({
			top: 0,
			behavior: 'smooth'
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
		request.splice(0, 0, 'application');
		let successFn, payload;
		switch (request[1]) {
			case 'language':
				successFn = async function (data) {
					window.LANGUAGEFILE = data.body;
				}
				break;
			case 'login':
				const logintoken = document.querySelector('[data-usecase=login]');
				if (logintoken) {
					request.push((logintoken.value ? logintoken.value : null));
				}
				successFn = function (data) {
					document.getElementById('main').replaceChildren();
					api.application('get', 'menu');
					if (data.body.form) {
						document.querySelector('body>label').style.backgroundImage = "url(./media/bars.svg)";
						new Assemble(data.body).initializeSection();
						let signin = LANG.GET('menu.application_signin'),
							greeting = ', ' + signin.charAt(0).toLowerCase() + signin.slice(1);
						if (data.user) greeting = ' ' + data.user;
						api.update_header(LANG.GET('general.welcome_header', {
							':user': greeting
						}));
						return;
					}
					if (data.body.image) document.querySelector('body>label').style.backgroundImage = "url('" + data.body.image + "')";
					api.application('get', 'start');
					document.getElementById('openmenu').checked = false;
				}
				break;
			case 'menu':
				successFn = function (data) {
					assemble_helper.userMenu(data.body);
				}
				break;
			case 'start':
				successFn = function (data) {
					let signin = LANG.GET('menu.application_signin'),
						greeting = ', ' + signin.charAt(0).toLowerCase() + signin.slice(1);
					if (data.user) greeting = ' ' + data.user;
					api.update_header(LANG.GET('general.welcome_header', {
						':user': greeting
					}));
					document.getElementById('main').replaceChildren();
					new Assemble(data.body).initializeSection();
					document.getElementById('openmenu').checked = false;
				}
				break;
			case 'manual':
				switch (method) {
					case 'get':
						successFn = function (data) {
							api.update_header(LANG.GET('menu.application_manual_manager'));
							document.getElementById('main').replaceChildren();
							new Assemble(data.body).initializeSection();
							document.getElementById('openmenu').checked = false;
						}
						break;
					case 'delete':
						successFn = function (data) {
							api.toast(data.status.msg);
							api.application('get', request[1], data.status.id);
						}
						break;
					default:
						successFn = function (data) {
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
							api.application('get', request[1], data.status.id);
						};
						payload = _.getInputs('[data-usecase=manual]', true);
						break;
				}
				break;
			default:
				return;
		}
		await api.send(method, request, successFn, null, payload, (method === 'post' || method === 'put'));
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
		request.splice(0, 0, 'file');
		let successFn = function (data) {
				if (data.body) {
					api.update_header(title[request[1]]);
					document.getElementById('main').replaceChildren();
					new Assemble(data.body).initializeSection();
				}
				if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
				if ('status' in data && 'redirect' in data.status) api.file('get', ...data.status.redirect);
			},
			payload,
			title = {
				'files': LANG.GET('menu.files_files'),
				'bundle': LANG.GET('menu.files_bundles'),
				'sharepoint': LANG.GET('menu.files_sharepoint'),
				'filemanager': LANG.GET('menu.files_file_manager'),
				'bundlemanager': LANG.GET('menu.files_bundle_manager')
			};

		switch (method) {
			case 'get':
				switch (request[1]) {
					case 'filter':
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll('[data-filtered]');
								for (const file of all) {
									file.parentNode.style.display = data.status.data.includes(file.dataset.filtered) ? 'block' : 'none';
								}
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
						break;
					case 'bundlefilter':
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll('[data-filtered]');
								for (const list of all) {
									list.parentNode.style.display = data.status.data.includes(parseInt(list.dataset.filtered, 10)) ? 'block' : 'none';
								}
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
						break;
				}
				break;
			case 'post':
				successFn = function (data) {
					if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
					if ('status' in data && 'redirect' in data.status) api.file('get', ...data.status.redirect);
				};
				payload = _.getInputs('[data-usecase=file]', true);
				break;
		}
		api.send(method, request, successFn, null, payload, method === 'post');
		document.getElementById('openmenu').checked = false;
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
		request.splice(0, 0, 'form');
		let successFn, payload,
			title = {
				'component_editor': LANG.GET('menu.forms_manage_components'),
				'form_editor': LANG.GET('menu.forms_manage_forms')
			};

		switch (method) {
			case 'get':
				switch (request[1]) {
					case 'component':
						successFn = function (data) {
							if (data.body) {
								data.body.content.name = data.name;
								if (data.body.content) compose_helper.importForm(data.body.content);
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						break;
					case 'component_editor':
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								document.getElementById('main').replaceChildren();
								new Compose(data.body);
								if (data.body.component) compose_helper.importComponent(data.body.component);
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						break;
					case 'form':
						successFn = function (data) {
							if (data.body) {
								document.getElementById('main').replaceChildren();
								new Assemble(data.body).initializeSection();
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						break;
					case 'form_editor':
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								document.getElementById('main').replaceChildren();
								new Compose(data.body);
								if (data.body.component) compose_helper.importForm(data.body.component);
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						break;
				}
				break;
			case 'post':
				switch (request[1]) {
					case 'component':
						successFn = function (data) {
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
							if ('status' in data && 'name' in data.status && data.status.name) api.form('get', 'component', data.status.name);
						}
						if (!(payload = compose_helper.composeNewComponent())) return;
						break;
					case 'form':
						////////////////////////////////////////////
						// TO DO: implement usecases to select from, e.g. in setup.ini? 
						////////////////////////////////////////////
						successFn = function (data) {
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						if (!(payload = compose_helper.composeNewForm())) return;
						break;
				}
				break;
		}
		api.send(method, request, successFn, null, payload);
		document.getElementById('openmenu').checked = false;
	},
	message: (method, ...request) => {
		/*
		get inbox
		get sent
		get filter/{filter}

		get message/ // empty form
		get message/{queryselector} // to initiate a new message with (hidden or visible) recipient and prepared message _.getinputs(queryselector)
		post message
		delete message/{id}/(inbox|sent)

		get notification // (returns number of unnotified/unread messages, only used within service-worker)

		*/
		request = [...request];
		request.splice(0, 0, 'message');
		let payload,
			successFn = function (data) {
				api.toast(data.status.msg);
				if ('redirect' in data.status && data.status.redirect)
					api.message('get', data.status.redirect);
			},
			title = {
				'inbox': LANG.GET('menu.message_inbox'),
				'sent': LANG.GET('menu.message_sent'),
				'message': LANG.GET('menu.message_new')
			};

		switch (method) {
			case 'get':
				switch (request[1]) {
					case 'filter':
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll('[data-filtered]');
								for (const file of all) {
									file.parentNode.style.display = data.status.data.includes(file.dataset.filtered) ? 'block' : 'none';
								}
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								document.getElementById('main').replaceChildren();
								new Assemble(data.body).initializeSection();
								api.preventDataloss.start();
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
							if (request[1] === 'inbox' && _serviceWorker.worker) _serviceWorker.onMessage({
								unseen: 0
							});
						};
						if (request[2]) payload = _.getInputs(request[2])
						break;
				}
				break;
			case 'post':
				payload = _.getInputs('[data-usecase=message]', true);
				break;
			case 'delete':
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === 'post');
		document.getElementById('openmenu').checked = false;
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

		get order/prepared
		get order/productsearch/{id|name}
		get order/order/{id}
		post order/order
		put order/order/{id}
		delete order/order/{id}

		get order/approved/
		put order/approved/{id}/{ordered|received|archived|disapproved}
		delete order/approved/{id}

		get order/filtered/{filter}
		*/
		request = [...request];
		if (['vendor', 'product'].includes(request[0]))
			request.splice(0, 0, 'consumables');
		else
			request.splice(0, 0, 'order');

		let payload,
			successFn = function (data) {
				api.toast(data.status.msg);
				api.purchase('get', request[1], data.status.id);
			},
			title = {
				'vendor': LANG.GET('menu.purchase_vendor'),
				'product': LANG.GET('menu.purchase_product'),
				'order': LANG.GET('menu.purchase_order'),
				'prepared': LANG.GET('menu.purchase_prepared_orders'),
				'approved': LANG.GET('menu.purchase_approved_orders')
			};
		if (request[2] === LANG.GET('consumables.edit_existing_vendors_new')) request.splice(2, 1);
		switch (method) {
			case 'get':
				switch (request[1]) {
					case 'productsearch':
						api.preventDataloss.monitor = false;
						successFn = function (data) {
							if (data.body) {
								let list = document.querySelector('[data-type=links]');
								if (list) list.remove();
								new Assemble(data.body).initializeSection('hr');
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
						break;
					case 'filter':
						successFn = function (data) {
							if (data.status) {
								const all = document.querySelectorAll('[data-filtered]');
								for (const order of all) {
									order.parentNode.style.display = data.status.data.includes(order.dataset.filtered) ? 'block' : 'none';
								}
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
						break;
					default:
						successFn = function (data) {
							if (data.body) {
								api.update_header(title[request[1]]);
								document.getElementById('main').replaceChildren();
								new Assemble(data.body).initializeSection();
								if (request[1] === 'approved') orderClient.filter();
								api.preventDataloss.start();
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
				}
				break;
			case 'post':
				payload = _.getInputs('[data-usecase=purchase]', true);
				break;
			case 'put':
				if (['ordered', 'received', 'archived', 'disapproved'].includes(request[3])) {
					successFn = function (data) {
						api.toast(data.status.msg);
					};
					break;
				}
				payload = _.getInputs('[data-usecase=purchase]', true);
				break;
			case 'delete':
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, (method === 'post' || method === 'put'));
		document.getElementById('openmenu').checked = false;
	},
	tool: (method, ...request) => {
		/*
		get tool/code
		get tool/code/display?key=value
		*/
		request = [...request];
		request.splice(0, 0, 'tool');
		let payload,
			successFn = function (data) {
				api.toast(data.status.msg);
				api.user('get', request[1], data.status.id);
			},
			title = {
				'code': LANG.GET('menu.tools_digital_codes'),
				'scanner': LANG.GET('menu.tools_scanner'),
				'stlviewer': LANG.GET('menu.tool_stl_viewer')
			};;
		switch (method) {
			case 'get':
				successFn = function (data) {
					if (data.body) {
						api.update_header(title[request[1]]);
						document.getElementById('main').replaceChildren();
						new Assemble(data.body).initializeSection();
					}
					if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
				};
				if (request[3] === 'display') {
					payload = _.getInputs('[data-usecase=tool_create_code]');
					console.log(payload);
				}
				break;
			case 'post':
				//payload = _.getInputs('[data-usecase=user]', true);
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === 'post');
		document.getElementById('openmenu').checked = false;
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
		request.splice(0, 0, 'user');
		let payload,
			successFn = function (data) {
				api.toast(data.status.msg);
				api.user('get', request[1], data.status.id);
			},
			title = {
				'profile': LANG.GET('menu.application_user_profile'),
				'user': LANG.GET('menu.application_user_manager')
			};;
		switch (method) {
			case 'get':
				successFn = function (data) {
					if (data.body) {
						api.update_header(title[request[1]]);
						document.getElementById('main').replaceChildren();
						new Assemble(data.body).initializeSection();
					}
					if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
				};
				break;
			case 'post':
				payload = _.getInputs('[data-usecase=user]', true);
				break;
			case 'put':
				payload = _.getInputs('[data-usecase=user]', true);
				break;
			case 'delete':
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, (method === 'post' || method === 'put'));
		document.getElementById('openmenu').checked = false;
	},
}