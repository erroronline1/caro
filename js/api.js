import {
	_
} from '../libraries/erroronline1.js';
import { assemble_helper } from './assemble.js';

export const api = {
	send: (method, payload, successFn, errorFn = null, form_data = false) => {
		_.api(method, 'api/api.php', payload, form_data)
			.then(data => {
				successFn(data);
			})
			.catch((error) => {
				if (errorFn != null) errorFn(error);
				else api.toast(error);
			});
	},
	toast: function (msg) {
		const toast=document.querySelector('dialog');
		if (typeof msg !== 'undefined') {
			toast.innerHTML = msg;
			toast.show();
			window.setTimeout(api.toast, 3000);
		} else toast.close();
},

	form: (request, name = null, ...forms) => {
		/*
		get form elements from database.
		notice only the first requested form will appear. later duplicates will be ignored.
		*/
		const methods = {
			'get': ['form_get', 'form_components_edit', 'form_edit', 'form_components_add'],
			'post': ['form_components_save', 'form_save']
		};
		let successFn = function (data) {
				document.getElementById('main').innerHTML = '';
				new Assemble(data).initializeSection();
			},
			method,
			payload = {
				'request': request,
				'name': name,
				'content': [...forms].join(',')
			};
		for (let key in methods) {
			if (methods[key].includes(request)) {
				method = key;
				break;
			}
		}
		if (request === 'form_components_edit') successFn = function (data) {
			document.getElementById('main').innerHTML = '';
			new Compose(data);
			if (data.component) compose_helper.importComponent(data.component);
		}
		if (request === 'form_components_save') {
			successFn = function (data) {
				if (data) api.toast('component ' + data.name + ' has been saved');
			}
			if (!(payload = compose_helper.composeNewComponent())) return;
			payload.request = 'form_components_save';
		}
		if (request === 'form_components_add') successFn = function (data) {
			data.content.name = data.name;
			compose_helper.importForm(data.content);
		}
		if (request === 'form_edit') successFn = function (data) {
			document.getElementById('main').innerHTML = '';
			new Compose(data);
			if (data.component) compose_helper.importForm(data.component);
		}
		if (request === 'form_save') {
			////////////////////////////////////////////
			// TO DO: implement usecases to select from, e.g. in setup.ini? 
			////////////////////////////////////////////
			successFn = function (data) {
				if (data) api.toast('form ' + data.name + ' has been saved');
			}
			if (!(payload = compose_helper.composeNewForm())) return;
			payload.request = 'form_save';
		}
		api.send(method, payload, successFn);
		document.getElementById('openmenu').checked = false;
	},
	user: (request, id = null) => {
		/*
		user tasks
		*/
		const methods = {
			'get': ['user_edit'],
			'post': ['user_save'],
			'delete': ['user_delete']
		};
		let successFn = function (data) {
				document.getElementById('main').innerHTML = '';
				new Assemble(data).initializeSection();
			},
			errorFn = null,
			method,
			payload = {
				'request': request,
				'id': id
			};
		for (let key in methods) {
			if (methods[key].includes(request)) {
				method = key;
				break;
			}
		}
		if (request === 'user_save') {
			successFn = function (data) {
				if (!data) {
					api.toast('user could not be saved');
					return;
				}
				api.toast('user ' + data.name + ' has been saved');
				api.user('user_edit', data.id);
			}
		}
		if (request === 'user_delete') {
			successFn = function (data) {
				if (data.id) {
					api.toast('user ' + data.name + ' could not be deleted');
					return;
				}
				api.toast('user ' + data.name + ' has been permanently deleted');
				api.user('user_edit', data.id);
			}
		}
		if (method === 'post') payload = _.getInputs('[data-usecase=user]', true);
		api.send(method, payload, successFn, errorFn, method === 'post');
		document.getElementById('openmenu').checked = false;
	},
	start: (request = 'user_current', login = null) => {
		// login and logout
		let payload = {
				'request': request,
			},
			successFn = function (data) {
				document.getElementById('main').innerHTML = '';
				api.menu();
				if (data.form) {
					document.querySelector('body>label').style.backgroundImage = "url(./media/bars.svg)";
					new Assemble(data).initializeSection();
					return;
				}
				document.querySelector('body>label').style.backgroundImage = "url('" + data.image + "')";
			};
		if (login) payload.login = login;
		if (document.querySelector('[data-usecase=user_current]')) payload = _.getInputs('[data-usecase=user_current]', true);
		api.send('post', payload, successFn, null, document.querySelector('[data-usecase=user_current]'));
		document.getElementById('openmenu').checked = false;
	},
	menu:() => {
		const successFn = function (data){
			assemble_helper.userMenu(data);
		}
		api.send('get', {request:'user_menu'}, successFn);
	}
}