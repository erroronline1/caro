// personal usecase framework by error on line 1 (erroronline.one)
// this is not underscore.js!

Array.prototype.contains = function (values) {
	return _.contains(this, values);
} // to use intitive with arrays
String.prototype.contains = function (values) {
	return _.contains(this, values);
} // to use intitive with string

var httpResponse = {
	400: 'Bad Request',
	401: 'Unauthorized',
	403: 'Forbidden',
	408: 'Request Timeout',
	500: 'Internal Server Error',
	503: 'Service Unavailable',
	507: 'Insufficient Storage'
};

export const _ = {
	el: function (x) { // shortcut for readabilities sake: _.el('element')
		return document.getElementById(x);
	},
	contains: function (obj, values) { // searches if at least one element of values (string or array) occurs in obj (string or array)
		return Array.isArray(values) ?
			values.some(value => obj.includes(value)) :
			obj.includes(values);
	},
	api: async function (method, destination, payload, form_data = false) {
		method = method.toUpperCase();
		let query = '';
		if (method == 'GET') {
			query = '?',
				Object.keys(payload).forEach(key => {
					query += '&' + key + '=' + payload[key];
				});
		}
		const response = await fetch(destination + query, {
			method: method, // *GET, POST, PUT, DELETE, etc.
			cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
			body: (method == 'GET' ? null : (form_data ? payload : JSON.stringify(payload))) // body data type must match "Content-Type" header
		}).then(response => {
			if (response.ok) return response.json();
			else throw new Error('server responded ' + response.status + ': ' + httpResponse[response.status]);
		});
		return response;
		/* use like 
			_.api(method, url, payload-object)
				.then(data => { do something with data })
				.catch((error) => { do something with error });
		*/
	},
	insertChars: function (characters, input) { // fills or deletes text on cursor position (textareas or inputs) 
		let el = document.getElementById(input)
		oldCPos = el.selectionStart;
		if (characters == '\b') { // backspace to delete
			el.value = el.value.substring(0, el.selectionStart - 1) + el.value.substring(el.selectionStart, el.value.length);
			el.selectionStart = el.selectionEnd = oldCPos - 1;
		} else {
			el.value = el.value.substring(0, el.selectionStart) + characters + el.value.substring(el.selectionStart, el.value.length);
			el.selectionStart = el.selectionEnd = oldCPos + characters.length;
		}
		el.focus();
	},
	dragNdrop: {
		// add following to draggable elements:
		// draggable="true" ondragstart="_.dragNdrop.drag(event)" ondragover="_.dragNdrop.allowDrop(event)" ondrop="_.dragNdrop.drop(event,this)"
		// or call following method passing the object itself (not just the id)
		add2DragCollection: function (element) {
			element.setAttribute('draggable', 'true');
			element.setAttribute('ondragstart', '_.dragNdrop.drag(event)');
			element.setAttribute('ondragover', '_.dragNdrop.allowDrop(event)');
			element.setAttribute('ondrop', '_.dragNdrop.drop_insertbefore(event,this)');
		},
		allowDrop: function (evnt) {
			evnt.preventDefault();
		},
		drag: function (evnt) {
			evnt.dataTransfer.setData("text", evnt.currentTarget.id)
		},
		drop_insertbefore: function (evnt, that) {
			evnt.preventDefault();
			const data = evnt.dataTransfer.getData("text");
			document.getElementById(data).parentNode.insertBefore(document.getElementById(data), that);
		},
		drop_delete: function (evnt) {
			const data = evnt.dataTransfer.getData("text");
			document.getElementById(data).remove();
		},

	},
	getInputs: function (querySelector, form_data = false) {
		let fields;
		if (form_data) {
			fields = new FormData(document.querySelector(querySelector));
		} else {
			fields = {};
			let inputs = document.querySelector(querySelector);
			Object.keys(inputs).forEach(input => {
				if (inputs[input].type == 'radio' && inputs[input].checked)
					fields[inputs[input].name] = inputs[input].value;
				else if (inputs[input].type == 'checkbox') {
					if (inputs[input].name.contains('[]')) {
						inputs[input].name = inputs[input].name.replace('[]', '');
						if (typeof fields[inputs[input].name] === 'object' && inputs[input].value) fields[inputs[input].name].push(inputs[input].checked ? inputs[input].value : 0)
						else if (inputs[input].value) fields[inputs[input].name] = [inputs[input].checked ? inputs[input].value : 0];
					} else fields[inputs[input].name] = inputs[input].checked ? inputs[input].value : 0;
				} else if (['text', 'tel', 'date', 'number', 'hidden', 'email'].contains(inputs[input].type)) {
					if (inputs[input].name.contains('[]')) {
						inputs[input].name = inputs[input].name.replace('[]', '');
						if (typeof fields[inputs[input].name] === 'object' && inputs[input].value) fields[inputs[input].name].push(inputs[input].value)
						else if (inputs[input].value) fields[inputs[input].name] = [inputs[input].value];
					} else fields[inputs[input].name] = inputs[input].value;
				}
			});
		}
		return fields;
	},
	sleep: function (delay) {
		// use from async function with await _.sleep(ms)
		return new Promise(resolve => setTimeout(resolve, delay));
	}
}