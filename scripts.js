import Assembly from './assembly.js';
import {_} from './libraries/erroronline1.js';

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

	getForm: (which='template') => {
		let successFn = function (data) {
			new Assembly(data);
		}
		api.send('get', {
			'request': 'getForm',
			'content': which
		}, successFn);
	},
	signIn: () => {

	},
	signOut: () => {

	}
}