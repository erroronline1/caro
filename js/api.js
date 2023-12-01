import {
	_
} from '../libraries/erroronline1.js';
import {
	assemble_helper
} from './assemble.js';


export const api = {
	send: async (method, request, successFn, errorFn = null, payload = {}, form_data = false) => {
		// default disable camera stream
		const scanner=document.querySelector('video');
		if (scanner) scanner.srcObject.getTracks()[0].stop();
		
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
	update_header(string=''){
		document.querySelector('head>h1').innerHTML = string;
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
								document.getElementById('main').innerHTML = '';
								new Compose(data.body);
								if (data.body.component) compose_helper.importComponent(data.body.component);
								}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						break;
					case 'form':
						successFn = function (data) {
							if (data.body) {
								document.getElementById('main').innerHTML = '';
								new Assemble(data.body).initializeSection();
								}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						}
						break;
					case 'form_editor':
						successFn = function (data) {
							if (data.body) {
								document.getElementById('main').innerHTML = '';
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
							if ('status' in data && 'name' in data.status && data.status.name) api.form('get','component', data.status.name);
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
	user: (method, ...request) => {
		/*
		get user/{id|name}
		post user
		put user/{id}
		delete user/{id}
		*/
		request = [...request];
		request.splice(0, 0, 'user');
		let payload,
			successFn = function (data) {
						api.toast(data.status.msg);
						api.user('get', data.status.id);
					};
		switch (method) {
			case 'get':
				successFn = function (data) {
					if (data.body) {
						document.getElementById('main').innerHTML = '';
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
		request.splice(0, 0, 'consumables');
		let payload,
			successFn = function (data) {
						api.toast(data.status.msg);
						api.purchase('get', request[1], data.status.id);
					};
		switch (method) {
			case 'get':
				switch (request[1]) {
					default:
						successFn = function (data) {
							if (data.body) {
								document.getElementById('main').innerHTML = '';
								new Assemble(data.body).initializeSection();
							}
							if ('status' in data && 'msg' in data.status) api.toast(data.status.msg);
						};
				}
				break;
			case 'post':
				payload = _.getInputs('[data-usecase=purchase]', true);
				break;
			case 'put':
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
					window.LANGUAGEFILE = data.body;
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
					if (data.body.form) {
						document.querySelector('body>label').style.backgroundImage = "url(./media/bars.svg)";
						new Assemble(data.body).initializeSection();
						return;
					}
					document.querySelector('body>label').style.backgroundImage = "url('" + data.body.image + "')";
					document.getElementById('openmenu').checked = false;
				}
				break;
			case 'menu':
				successFn = function (data) {
					assemble_helper.userMenu(data.body);
				}
				break;
			default:
				return;
		}
		await api.send(method, request, successFn);
	},

}