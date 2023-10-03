var multiplecontainerID = 0

function getNextContainerID() {
	return "containerID" + ++multiplecontainerID;
}

const scroller = (e) => {
	/* event handler for horizontal scrolling of multiple panels */
	setTimeout(() => {
		let indicator = document.getElementById(e.target.attributes.id.value + "indicator");
		for (let panel = 0; panel < e.target.children.length; panel++) {
			if (panel == Math.floor(e.target.scrollLeft / e.target.clientWidth)) indicator.children[
				panel].firstChild.classList.add('sectionactive');
			else indicator.children[panel].firstChild.classList.remove('sectionactive');
		}
	}, 500)
};

class Assembly {
	/* 
	assembles forms and screen elements.
	deepest nesting of input object is three levels
	form:null or {attributes}
	items:[
		[card
			{element0},
			{element1}
		],
	]
	*/
	constructor(setup) {
		this.tiles = setup.tiles;
		this.multipleContainers = [];
		let container = null;


		if (setup.form) {
			container = document.createElement('form');
			container.method='post';
			Object.keys(setup.form).forEach(key => {
				container[key] = setup.form[key];
			});
		} else container = document.createElement('div');

		this.assembledTiles = new Set();
		this.processItems();

		container.append(...this.assembledTiles);
		document.getElementById('main').insertAdjacentElement('beforeend', container);
		for (let i = 0; i < this.multipleContainers.length; i++) {
			document.getElementById(this.multipleContainers[i]).addEventListener('scroll', scroller);
		}
		if (this.signaturePad) {
			initialize_SignaturePad();
		}
	}

	processItems() {
		this.multiplecontainerID = null;

		this.tiles.forEach(tile => {
			this.multipletiles = new Set();
			for (let i = 0; i < tile.length; i++) {
				this.elements = new Set();
				this.icon = tile[i].icon;
				this.fieldset = tile[i].fieldset;
				this.collapsed = tile[i].collapsed;
				this.attributes = tile[i].attributes;
				this.items = tile[i].items;
				this[tile[i].type]();
				if (tile.length < 2) {
					this.assembledTiles.add(this.single());
					continue;
				}
				this.multipletiles.add(this.single())
			}
			if (this.multipletiles.size) this.assembledTiles.add(this.multiple());
		});
	}
	single(classList = null) {
		const section = document.createElement('section'),
			icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
			use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
		if (classList) section.classList = classList;
		use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', 'media/sprite.svg#' + this.icon);
		icon.appendChild(use);
		section.appendChild(icon);
		if (this.fieldset === undefined) {
			section.append(...this.elements);
			return section;
		}
		const fieldset = document.createElement('fieldset'),
			legend = document.createElement('legend'),
			title = document.createTextNode(this.fieldset);
		legend.appendChild(title);
		fieldset.appendChild(legend);
		fieldset.append(...this.elements);
		section.appendChild(fieldset);
		return section;
	}
	multiple(classList = null) {
		const section = document.createElement('section'),
			container = document.createElement('div'),
			indicators = document.createElement('div');
		this.multiplecontainerID = getNextContainerID();
		this.multipleContainers.push(this.multiplecontainerID);
		if (classList) section.classList = classList;
		container.classList = 'container inset';
		container.id = this.multiplecontainerID;
		container.append(...this.multipletiles);
		section.appendChild(container);
		indicators.classList = 'containerindicator';
		indicators.id = this.multiplecontainerID + 'indicator';
		for (let i = 0; i < this.multipletiles.size; i++) {
			let icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
				use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
			icon.classList = 'sectionindicator';
			use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', 'media/sprite.svg#sectionindicator');
			icon.appendChild(use);
			indicators.appendChild(icon);
		}
		section.appendChild(indicators);
		return section;
	}

	text() {
		/* {
			type: 'text',
			icon: 'read',
			collapsed: true,
			items: [
				'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
			]
		}*/
		const div = document.createElement('div'),
			content = document.createTextNode(this.items[0]);
		div.appendChild(content);
		if (this.collapsed) {
			div.classList = "collapsed";
			div.onpointerdown = function () {
				this.classList.toggle('expanded')
			};
		}
		this.elements.add(div);
	}
	input() {
		/*{
			type: 'input',
			icon: 'text',
			fieldset: 'Textfeld',
			attributes: {
				type: 'text',
				placeholder: 'Textfeld'
			}
		}*/
		const input = document.createElement('input');
		let execute;
		Object.keys(this.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				execute = this.attributes[key]; // because of this scope
				input[key] = () => {
					eval(execute)
				};
			} else input[key] = this.attributes[key];
		});
		this.elements.add(input);
	}
	file() {
		/*{
			type: 'file',
			icon: 'upload',
			fieldset: 'Dateiupload',
			attributes: {
				id: 'fileupload',
				name: 'files[]' [] or not decides for multiple
			}
		}*/
		const input = document.createElement('input'),
			label = document.createElement('label');
		input.type = 'file';
		input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' oder ändern...' : 'Datei auswählen...'
		};
		Object.keys(this.attributes).forEach(key => {
			input[key] = this.attributes[key];
			if (this.attributes[key].indexOf('[]') > 0) input.multiple = true;
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Datei auswählen...'));
		this.elements.add(input)
		this.elements.add(label);
	}
	photo() {
		/*{
			type: 'file',
			icon: 'camera',
			fieldset: 'Kamera',
			attributes: {
				id: 'camera',
				name: 'photo'
			}
		}*/
		const input = document.createElement('input'),
			label = document.createElement('label');
		input.type = 'file';
		input.accept = 'image/*';
		input.capture = true;
		input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' oder ändern...' : 'Photo aufnehmen...'
		};
		Object.keys(this.attributes).forEach(key => {
			input[key] = this.attributes[key];
			if (this.attributes[key].indexOf('[]') > 0) input.multiple = true;
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Photo aufnehmen...'));
		this.elements.add(input)
		this.elements.add(label);
	}
	select() {
		/*{
			type: 'select',
			icon: 'select',
			fieldset: 'Liste',
			items: {
				'Listeneintrag1': {
					value: 'eins'
				},
				'Listeneintrag2': {
					value: 'zwei',
					selected: true
				}
			}
		}*/
		const select = document.createElement('select');
		let execute;
		if (this.attributes !== undefined) Object.keys(this.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				execute = this.attributes[key]; // because of this scope
				select[key] = () => {
					eval(execute)
				};
			} else select[key] = this.attributes[key];
		});
		Object.keys(this.items).forEach(key => {
			let option = document.createElement('option');
			Object.keys(this.items[key]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					execute = this.items[key][attribute];
					option[attribute] = () => {
						eval(execute)
					};
				} else option[attribute] = this.items[key][attribute];
			});
			option.appendChild(document.createTextNode(key));
			select.appendChild(option)
		});
		this.elements.add(select)
	}
	textarea() {
		/*{
			type: 'textarea',
			icon: 'text',
			fieldset: 'Textarea',
			attributes: {
				rows:8,
				value:'werte werden auf diese weise übergeben'
			}
		}*/
		const textarea = document.createElement('textarea');
		let execute;
		Object.keys(this.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				execute = this.attributes[key]; // because of this scope
				textarea[key] = () => {
					eval(execute)
				};
			} else if (key !== 'value') textarea[key] = this.attributes[key];
		});
		if ('value' in this.attributes) textarea.appendChild(document.createTextNode(this.attributes.value));
		this.elements.add(textarea);
	}
	checkbox(radio = null) {
		/*{
			type: 'checkbox', or 'radio'
			icon: 'checkbox',
			//fieldset:'Checkboxes',
			items: {
				'Checkbox 1': {
					name: 'ch1'
				},
				'Checkbox 2': {
					name: 'ch1'
				}
			}
		}*/
		Object.keys(this.items).forEach(checkbox => {
			let label = document.createElement('label'),
				input = document.createElement('input'),
				span = document.createElement('span'),
				execute;
			input.type = radio ? 'radio' : 'checkbox';
			label.classList = 'custominput';
			span.classList = 'checkmark';
			label.appendChild(document.createTextNode(checkbox));
			Object.keys(this.items[checkbox]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					execute = this.items[checkbox][attribute]; // because of this scope
					input[attribute] = () => {
						eval(execute)
					};
				} else input[attribute] = this.items[checkbox][attribute];
			});
			label.appendChild(input);
			label.appendChild(span);
			this.elements.add(label);
		});
	}
	radio() {
		this.checkbox('radioinstead');
	}
	links() {
		/*{
			type: 'links',
			icon: 'link',
			//fieldset:'Links',
			items: {
				'Link 1': {
					href: '#'
				},
				'Link 2': {
					href: '#',
					onpointerdown: 'alert(\'hello\')'
				}
			}
		}*/
		const div = document.createElement('div'),
			ul = document.createElement('ul');
		Object.keys(this.items).forEach(link => {
			let li = document.createElement('li'),
				a = document.createElement('a'),
				execute;
			Object.keys(this.items[link]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					execute = this.items[link][attribute] // because of this scope
					a[attribute] = () => {
						eval(execute);
					};
				} else a[attribute] = this.items[link][attribute];
			})
			a.appendChild(document.createTextNode(link));
			li.appendChild(a);
			ul.appendChild(li);
		});
		div.appendChild(ul)
		this.elements.add(div);
	}
	signature() {
		/*{
			type: 'signature',
			icon:'signature',
			fieldset:'Unterschrift'
		} */
		const canvas = document.createElement('canvas');
		canvas.id = 'canvas',
			this.elements.add(canvas);
		this.signaturePad = true;
	}
}