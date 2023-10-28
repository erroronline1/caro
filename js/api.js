import {
	Assemble
} from './assemble.js';
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

	getForms: (which = 'template', ...andMore) => {
		/*
		get form elements from database.
		notice only the first requested form will appear. later duplicates will be ignored.
		*/
		const successFn = function (data) {
			new Assemble(data).initializeSection();
		}
		api.send('get', {
			'request': 'getForms',
			'content': [which, ...andMore].join(',')
		}, successFn);
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
				console.trace();
				console.log(request, method, data);
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