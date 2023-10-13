import {
	Assemble
} from './js/assemble.js';
import {
	_
} from './libraries/erroronline1.js';

import {
	Compose
} from './js/compose.js'

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
			new Assemble(data).initializeContainer();
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

const createForm = {
	"content": [
		[{
				"type": "text",
				"collapsed": true,
				"content": "choose available elements from this panel. set your parameters and add fields. change your order by dragging the elements."
			},
			{
				"type": "compose_links",
				"description": "create a link block"
			}, {
				"type": "compose_radio",
				"description": "create a single selection block"
			}, {
				"type": "compose_checkbox",
				"description": "create a multiple selection block"
			}, {
				"type": "compose_select",
				"description": "create a dropdown"
			}, {
				"type": "compose_text",
				"description": "create an informative text block"
			}, {
				"type": "compose_textinput",
				"description": "create a single line text input"
			}, {
				"type": "compose_textarea",
				"description": "create a multi line text input"
			}, {
				"type": "compose_numberinput",
				"description": "create a number input"
			}, {
				"type": "compose_dateinput",
				"description": "create a date input"
			}, {
				"type": "compose_file",
				"description": "create a file upload"
			}, {
				"type": "compose_photo",
				"description": "create a photo upload"
			}, {
				"type": "compose_signature",
				"description": "create a signature pad"
			}, {
				"type": "compose_qr",
				"description": "create a qr-scanner field"
			},

		]
	]
};

export function create() {
	let c = new Compose(createForm);
}