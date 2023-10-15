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
			"description": "what to do",
			"content": "choose available elements from this panel. set your parameters and add fields. advanced attributes (href, value, events, etc) have to be set in json-format with double-quotes. change your order by dragging the elements. during composing indicators for containers are not available."
		}, {
			"type": "compose_text",
		}, {
			"type": "compose_textinput",
		}, {
			"type": "compose_textarea",
		}, {
			"type": "compose_numberinput",
		}, {
			"type": "compose_dateinput",
		}, {
			"type": "compose_links",
		}, {
			"type": "compose_radio",
		}, {
			"type": "compose_checkbox",
		}, {
			"type": "compose_select",
		}, {
			"type": "compose_file",
		}, {
			"type": "compose_photo",
		}, {
			"type": "compose_signature",
		}, {
			"type": "compose_qr",
		}, ],
		[{
			"type": "trash",
			"description": "drop panel here to delete"
		}]
	]
};

export function create() {
	let c = new Compose(createForm);
}