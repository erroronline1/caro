import {
	_
} from '../libraries/erroronline1.js';
import {
	assemble_helper
} from './assemble.js';


export const api = {
	send: (method, request, successFn, errorFn = null, payload = {}, form_data = false) => {
		_.api(method, 'api/api.php/' + request.join('/'), payload, form_data)
			.then(data => {
				successFn(data);
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

		get form/component/{name}
		get form/component_editor/{name}
		get form/form/{name}
		get form/form_editor/{name}

		post form/component
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
							if (data) api.toast('component ' + data.name + ' has been saved');
						}
						if (!(payload = compose_helper.composeNewComponent())) return;
						break;
					case 'form':
							////////////////////////////////////////////
						// TO DO: implement usecases to select from, e.g. in setup.ini? 
						////////////////////////////////////////////
						successFn = function (data) {
							if (data) api.toast('form ' + data.name + ' has been saved');
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
		get user/{id} // type num
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
						api.toast('user could not be saved');
						return;
					}
					api.toast('user ' + data.name + ' has been saved');
					api.user('get', data.id);
				}
				break;
			case 'put':
				payload = _.getInputs('[data-usecase=user]', false);
				successFn = function (data) {
					if (!data) {
						api.toast('user could not be saved');
						return;
					}
					api.toast('user ' + data.name + ' has been saved');
					api.user('get', data.id);
				}
				let image = document.querySelector('[type=file]');
				if (image.files[0]) {
					let reader = new FileReader();
					reader.onloadend = function () {
						payload.photo = reader.result;
						api.send(method, request, successFn, null, payload, false);
						document.getElementById('openmenu').checked = false;
					}
					reader.readAsDataURL(image.files[0]);
					return;
				}
				break;
			case 'delete':
				successFn = function (data) {
					if (data.id) {
						api.toast('user ' + data.name + ' could not be deleted');
						return;
					}
					api.toast('user ' + data.name + ' has been permanently deleted');
					api.user('get', data.id);
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === 'post');
		document.getElementById('openmenu').checked = false;
	},
	application: (method, ...request) => {
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
				successFn = function (data) {
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
		api.send(method, request, successFn);
	},

}