import {
	_
} from '../libraries/erroronline1.js';

export const api = {
	send: (method, payload, successFn, errorFn = null, form_data = false) => {
		_.api(method, 'api/api.php', payload, form_data)
			.then(data => {
				successFn(data);
			})
			.catch((error) => {
				if (errorFn != null) errorFn(error);
				else api.notif(error);
			});
	},
	notif: function (error) {
		/*if (typeof error !== 'undefined') {
			_.el('growlNotif').innerHTML = error;
			_.el('growlNotif').classList.add('show');
			window.setTimeout(api.notif, 3000);
		} else _.el('growlNotif').classList.remove('show');*/
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
			if (!(payload = compose_helper.composeNewForm())) return;
			payload.request = 'form_save';
		}
		api.send(method, payload, successFn);
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
		if (method === 'post') payload = _.getInputs('[data-usecase=user]', true);
		if (method === 'post' || method === 'delete') {
			successFn = function (data) {
				api.user('user_edit', data.id);
			}
		}
		api.send(method, payload, successFn, null, method === 'post');
	},
	signIn: () => {

	},
	signOut: () => {

	}
}