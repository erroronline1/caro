const _serviceWorker = {
	worker: null,
	permission: null,
	register: async function () {
		if ('serviceWorker' in navigator) {
			this.worker = await navigator.serviceWorker.register('./service-worker.js');
			this.permission = await window.Notification.requestPermission();
			navigator.serviceWorker.ready.then(registration => {
				setInterval(() => {
					if (registration) _serviceWorker.postMessage('getnotifications');
				}, 300000);
				navigator.serviceWorker.addEventListener('message', (message) => {
					this.onMessage(message.data);
				});
			});
		} else
			throw new Error('No Service Worker support!');
	},
	requestNotificationPermission: async function () {
		const permission = await window.Notification.requestPermission();
		// value of permission can be 'granted', 'default', 'denied'
		// granted: user has accepted the request
		// default: user has dismissed the notification permission popup by clicking on x
		// denied: user has denied the request.
		if (permission !== 'granted') {
			throw new Error('Permission not granted for Notification');
		}
	},
	showLocalNotification: function (title, body) {
		const options = {
			'body': body,
			'icon': './media/favicon/android/android-launchericon-192-192.png',
			// here you can add more properties like icon, image, vibrate, etc.
		};
		if (this.worker.active) this.worker.showNotification(title, options);
	},
	postMessage: function (message) {
		this.worker.active.postMessage(message);
	},
	onPostCache: function () {
		const buttons = document.querySelectorAll('[type=submit]');
		for (const element of buttons) {
			element.disabled = true;
		};
	},
	onMessage: function (data) {
		if ('unnotified' in data) {
			if (parseInt(data.unnotified, 10)) {
				let body = data.unnotified > 1 ? LANG.GET('message.new_messages', {
					':amount': data.unnotified
				}) : LANG.GET('message.new_message');
				this.showLocalNotification(LANG.GET('menu.message_header'), body);
			}
		}
		if ('unseen' in data) {
			document.querySelector('nav div').innerHTML = '&nbsp;';
			if (parseInt(data.unseen, 10)) {
				const container = document.createElement('span'),
					message = document.createElement('img');
				message.src = './media/envelope.svg';
				container.setAttribute('data-unreadmessages', data.unseen);
				container.append(message);
				document.querySelector('nav div').append(container);
			}
			for (const mail of document.querySelectorAll('[data-unreadmessages]')) mail.setAttribute('data-unreadmessages', data.unseen);
		}
	}
}

const orderClient = {
	addProduct: (...data) => {
		// order to be taken into account in order.php "productsearch" method as well!
		const nodes = document.querySelectorAll('[data-type=collapsed]'),
			newNode = nodes[nodes.length - 1].cloneNode(true),
			transfer = ['', ...data]; // first item is supposed to be quantity

		newNode.children[newNode.children.length - 1].remove();
		for (let i = 0; i < newNode.children.length; i += 2) {
			if ('value' in newNode.children[i]) newNode.children[i].value = transfer[i / 2];
			newNode.children[i].required = 'required';
		}
		const deleteButton = document.createElement('button');
		deleteButton.appendChild(document.createTextNode(LANG.GET('order.add_delete')));
		deleteButton.addEventListener('pointerdown', (e) => {
			newNode.remove()
		});
		newNode.append(deleteButton);

		nodes[nodes.length - 1].parentNode.insertBefore(newNode, nodes[nodes.length - 1]);
	},
	cloneNew: (node) => {
		const newNode = node.cloneNode(true),
			deleteButton = document.createElement('button');
		newNode.children[node.children.length - 1].remove();
		deleteButton.appendChild(document.createTextNode(LANG.GET('order.add_delete')));
		deleteButton.addEventListener('pointerdown', (e) => {
			newNode.remove()
		});
		newNode.append(deleteButton);
		node.parentNode.insertBefore(newNode, node);
		for (let i = 0; i < node.children.length; i++) {
			if (i !== 3) { // distributor remains, see order.php
				node.children[i].value = '';
			}
		}
	},
	required: (node) => {
		let hasValue = false;
		// check if any field has a value
		for (let i = 0; i < node.children.length; i++) {
			if (node.children[i].value) {
				hasValue = true;
				break;
			}
		}
		// apply required if so, delete if not
		for (let i = 0; i < node.children.length; i++) {
			if (hasValue) node.children[i].setAttribute('required', '');
			else node.children[i].removeAttribute('required');
		}
	},
	toClipboard: (node) => {
		node.select();
		node.setSelectionRange(0, 99999); // For mobile devices
		navigator.clipboard.writeText(node.value);
		api.toast(LANG.GET('general.copied_to_clipboard'));
	},
	filter: (type = undefined) => {
		let display, hide, not = '';
		hide = document.querySelectorAll('article[data-type=cart]');
		if (type) {
			if (type === 'ordered') not = ':not([data-received]):not([data-archived])';
			if (type === 'received') not = ':not([data-archived])';
			display = document.querySelectorAll(`article[data-type=cart][data-${type}]${not}`);
			console.log(`article[data-type=cart][data-${type}]${not}`);
		} else {
			display = document.querySelectorAll('article[data-type=cart]:not([data-ordered]):not([data-received]):not([data-archived])');
		}

		hide.forEach(article => {
			article.style.display = 'none';
		});
		display.forEach(article => {
			article.style.display = 'block';
		});
	}
};

const toolModule = {
	stlviewer: null,
	initStlViewer: function (file) {
		const canvas = document.getElementById('stlviewer_canvas');
		canvas.replaceChildren();
		this.stlviewer = new StlViewer(canvas, {
			models: [{
				id: 0,
				filename: file
			}]
		});
	}
};