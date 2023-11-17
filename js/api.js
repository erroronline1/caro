import {
	_
} from '../libraries/erroronline1.js';
import {
	assemble_helper
} from './assemble.js';


export const api = {
	send: async (method, request, successFn, errorFn = null, payload = {}, form_data = false) => {
		await _.api(method, 'api/api.php/' + request.join('/'), payload, form_data)
			.then(async data => {
				await successFn(data);
			})
			.catch((error) => {
				console.trace(error);
				if (errorFn != null) errorFn(error);
				else api.toast(error);
			});
	},
	toast: function (msg) {
		const toast = document.querySelector('dialog');
		if (typeof msg !== 'undefined') {
			toast.innerHTML = msg;
			toast.show();
			window.setTimeout(api.toast, 3000);
		} else toast.close();
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
		let successFn, payload;

		switch (method) {
			case 'get':
				switch (request[1]) {
					case 'component':
						successFn = function (data) {
							data.content.name = data.name;
							if (data.content) compose_helper.importForm(data.content);
						}
						break;
					case 'component_editor':
						successFn = function (data) {
							document.getElementById('main').innerHTML = '';
							new Compose(data);
							if (data.component) compose_helper.importComponent(data.component);
						}
						break;
					case 'form':
						successFn = function (data) {
							document.getElementById('main').innerHTML = '';
							new Assemble(data).initializeSection();
						}
						break;
					case 'form_editor':
						successFn = function (data) {
							document.getElementById('main').innerHTML = '';
							new Compose(data);
							if (data.component) compose_helper.importForm(data.component);
						}
						break;
				}
				break;
			case 'post':
				switch (request[1]) {
					case 'component':
						successFn = function (data) {
							if (data) api.toast(LANG.GET('assemble.api_component_saved', {
								':name': data.name
							}));
						}
						if (!(payload = compose_helper.composeNewComponent())) return;
						break;
					case 'form':
						////////////////////////////////////////////
						// TO DO: implement usecases to select from, e.g. in setup.ini? 
						////////////////////////////////////////////
						successFn = function (data) {
							if (data) api.toast(LANG.GET('assemble.api_form_saved', {
								':name': data.name
							}));
						}
						if (!(payload = compose_helper.composeNewForm())) return;
						break;
				}
				break;
		}
		api.send(method, request, successFn, null, payload);
		document.getElementById('openmenu').checked = false;
	},
	user: (method, ...request) => {
		/*
		get user/{id|name}
		post user
		put user/{id}
		delete user/{id}
		*/
		request = [...request];
		request.splice(0, 0, 'user');
		let successFn, payload;
		switch (method) {
			case 'get':
				successFn = function (data) {
					document.getElementById('main').innerHTML = '';
					new Assemble(data).initializeSection();
				};
				break;
			case 'post':
				payload = _.getInputs('[data-usecase=user]', true);
				successFn = function (data) {
					if (!data) {
						api.toast(LANG.GET('user.api_user_not_saved'));
						return;
					}
					api.toast(LANG.GET('user.api_user_saved', {
						':name': data.name
					}));
					api.user('get', data.id);
				}
				break;
			case 'put':
				payload = _.getInputs('[data-usecase=user]', true);
				successFn = function (data) {
					if (!data) {
						api.toast(LANG.GET('user.api_user_not_saved'));
						return;
					}
					api.toast(LANG.GET('user.api_user_saved', {
						':name': data.name
					}));
					api.user('get', data.id);
				}
				break;
			case 'delete':
				successFn = function (data) {
					if (data.id) {
						api.toast(LANG.GET('user.api_user_not_deleted', {
							':name': data.name
						}));
						return;
					}
					api.toast(LANG.GET('user.api_user_deleted', {
						':name': data.name
					}));
					api.user('get', data.id);
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, (method === 'post' || method === 'put'));
		document.getElementById('openmenu').checked = false;
	},
	purchase: (method, ...request) => {
		/*
		get purchase/distributor/{id|name}
		post purchase/distributor
		put purchase/distributor/{id}

		get purchase/product/{id}
		get purchase/product/{id|name}/search
		post purchase/product
		put purchase/product/{id}
		delete purchase/product/{id}

		get purchase/order/{id}
		post purchase/order
		put purchase/order/{id}
		delete purchase/order/{id}
		*/
		request = [...request];
		request.splice(0, 0, 'purchase');
		let successFn, payload;
		switch (method) {
			case 'get':
				switch (request[1]) {
					default:
						successFn = function (data) {
							document.getElementById('main').innerHTML = '';
							new Assemble(data).initializeSection();
						};
				}
				break;
			case 'post':
				payload = _.getInputs('[data-usecase=purchase]', true);
				switch (request[1]) {
					case 'distributor':
						successFn = function (data) {
							if (!data) {
								api.toast(LANG.GET('purchase.api_distributor_not_saved', {
									':name': data.name
								}));
								return;
							}
							api.toast(LANG.GET('purchase.api_distributor_saved', {
								':name': data.name
							}));
							api.purchase('get', request[1], data.id);
						}
						break;
						case 'product':
							successFn = function (data) {
								if (!data) {
									api.toast(LANG.GET('purchase.api_product_not_saved', {
										':name': data.name
									}));
									return;
								}
								api.toast(LANG.GET('purchase.api_product_saved', {
									':name': data.name
								}));
								api.purchase('get', request[1], data.id);
							}
							break;
					}
				break;
			case 'put':
				switch (request[1]) {
					case 'distributor':
						payload = _.getInputs('[data-usecase=purchase]', true);
						successFn = function (data) {
							if (!data) {
								api.toast(LANG.GET('purchase.api_distributor_not_saved'));
								return;
							}
							api.toast(LANG.GET('purchase.api_distributor_saved', {
								':name': data.name
							}));
							api.purchase('get', request[1], data.id);
						}
						break;
						case 'product':
							payload = _.getInputs('[data-usecase=purchase]', true);
							successFn = function (data) {
								if (!data) {
									api.toast(LANG.GET('purchase.api_product_not_saved'));
									return;
								}
								api.toast(LANG.GET('purchase.api_product_saved', {
									':name': data.name
								}));
								api.purchase('get', request[1], data.id);
							}
							break;
					}
				break;
			case 'delete':
				switch (request[1]) {
					case 'product':
						successFn = function (data) {
							if (data.id) {
								api.toast(LANG.GET('purchase.api_product_not_deleted', {
									':name': data.name
								}));
								return;
							}
							api.toast(LANG.GET('purchase.api_product_deleted', {
								':name': data.name
							}));
							api.purchase('get', request[1], data.id);
						}
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, (method === 'post' || method === 'put'));
		document.getElementById('openmenu').checked = false;
	},
	application: async (method, ...request) => {
		/*
		get application/user
		get application/menu
		get application/language
		*/
		request = [...request];
		request.splice(0, 0, 'application');
		let successFn;
		switch (request[1]) {
			case 'language':
				successFn = async function (data) {
					window.LANGUAGEFILE = data;
				}
				break;
			case 'login':
				const logintoken = document.querySelector('[data-usecase=login]');
				if (logintoken) {
					request.push((logintoken.value ? logintoken.value : null));
				}
				successFn = function (data) {
					document.getElementById('main').innerHTML = '';
					api.application('get', 'menu');
					if (data.form) {
						document.querySelector('body>label').style.backgroundImage = "url(./media/bars.svg)";
						new Assemble(data).initializeSection();
						return;
					}
					document.querySelector('body>label').style.backgroundImage = "url('" + data.image + "')";
					document.getElementById('openmenu').checked = false;
				}
				break;
			case 'menu':
				successFn = function (data) {
					assemble_helper.userMenu(data);
				}
				break;
			default:
				return;
		}
		await api.send(method, request, successFn);
	},

}