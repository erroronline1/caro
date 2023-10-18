import {
	Assemble
} from './js/assemble.js';
import {
	_
} from './libraries/erroronline1.js';

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
		let successFn = function (data) {
			new Assemble(data).initializeSection();
		}
		api.send('get', {
			'request': 'getForms',
			'content': [which, ...andMore].join(',')
		}, successFn);
	},
	signIn: () => {

	},
	signOut: () => {

	}
}