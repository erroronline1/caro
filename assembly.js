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
	form:null or {attributes} / nothing creates just a div e.g. just for text and links
	content:[
		[card
			{element0},
			{element1}
		],
	]
	*/
	constructor(setup) {
		this.content = setup.content;
		this.multipleContainers = [];
		this.multiplecontainerID = null;
		let container;

		if (setup.form) {
			container = document.createElement('form');
			container.method = 'post';
			container.enctype = "multipart/form-data";
			Object.keys(setup.form).forEach(key => {
				container[key] = setup.form[key];
			});
			this.content.push([{
				type: 'submit',
				attributes: {
					value: 'absenden'
				}
			}]);
		} else container = document.createElement('div');

		this.assembledTiles = new Set();
		this.processContent();

		container.append(...this.assembledTiles);

		document.getElementById('main').insertAdjacentElement('beforeend', container);
		for (let i = 0; i < this.multipleContainers.length; i++) {
			document.getElementById(this.multipleContainers[i]).addEventListener('scroll', scroller);
		}
		if (this.signaturePad) {
			initialize_SignaturePad();
		}
	}

	processContent() {
		this.content.forEach(tile => {
			this.multipletiles = new Set();
			for (let i = 0; i < tile.length; i++) {
				this.elements = new Set();
				this.tile = tile[i];
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
	single() {
		const section = document.createElement('section');
		section.setAttribute('data-type', this.tile.type);

		if ([undefined, null, false].indexOf(this.tile.description) > -1) {
			section.append(...this.elements);
			return section;
		}
		const fieldset = this.fieldset();
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
			let indicator = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
				circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
			indicator.classList = 'sectionindicator';
			indicator.setAttributeNS(null, "viewbox", "0 0 10 10");
			circle.setAttributeNS(null, "cx", "5");
			circle.setAttributeNS(null, "cy", "5");
			circle.setAttributeNS(null, "r", "4");
			indicator.appendChild(circle);
			indicators.appendChild(indicator);
		}
		section.appendChild(indicators);
		return section;
	}

	fieldset() {
		const fieldset = document.createElement('fieldset'),
			legend = document.createElement('legend'),
			title = document.createTextNode(this.tile.description);
		legend.appendChild(title);
		fieldset.appendChild(legend);
		return fieldset
	}
	text() {
		/* {
			type: 'text',
			collapsed: true,
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
		}*/
		const div = document.createElement('div'),
			content = document.createTextNode(this.tile.content);
		div.appendChild(content);
		if (this.tile.collapsed) {
			div.classList = "collapsed";
			div.onpointerdown = function () {
				this.classList.toggle('expanded')
			};
		}
		this.elements.add(div);
	}
	input(type) {
		/*{
			type: "textinput",
			description: 'text input',
			attributes: {
				placeholder: 'text input'
			}
		}*/
		const input = document.createElement('input');
		input.type = type;


		/////////////// todo: sanitation of names
		input.name = this.tile.description;
		/////////////////////
		/*
		todo: icons via css (data-type selector) reattempt
		read form (erroronline1,js)
		backend...
		blockchain
		js qr-code reader

		*/

		let execute;
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				execute = this.tile.attributes[key]; // because of this scope
				input[key] = () => {
					eval(execute)
				};
			} else input[key] = this.tile.attributes[key];
		});
		this.elements.add(input);
	}
	textinput() {
		this.input('text');
	}
	numberinput() {
		this.input('number');
	}
	dateinput() {
		this.input('date');
	}
	submit() {
		this.input('submit');
	}

	file() {
		/*{
			type: 'file',
			description: 'file upload',
			attributes: {
				multiple: true
			}
		}*/
		const input = document.createElement('input'),
			label = document.createElement('label');
		input.type = 'file';
		input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' oder ändern...' : 'Datei auswählen...'
		};
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			input[key] = this.tile.attributes[key];
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Datei auswählen...'));
		this.elements.add(input)
		this.elements.add(label);
	}
	photo() {
		/*{
			type: 'photo',
			description: 'photo upload',
			attributes: {
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
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			input[key] = this.tile.attributes[key];
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Photo aufnehmen...'));
		this.elements.add(input)
		this.elements.add(label);
	}
	select() {
		/*{
			type: 'select',
			description: 'list',
			content: {
				'entry one': {
					value: '1'
				},
				'entry two': {
					value: '2',
					selected: true
				}
			}
		}*/
		const select = document.createElement('select');
		let execute;
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				execute = this.tile.attributes[key]; // because of this scope
				select[key] = () => {
					eval(execute)
				};
			} else select[key] = this.tile.attributes[key];
		});
		Object.keys(this.tile.content).forEach(key => {
			let option = document.createElement('option');
			Object.keys(this.tile.content[key]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					execute = this.tile.content[key][attribute];
					option[attribute] = () => {
						eval(execute)
					};
				} else option[attribute] = this.tile.content[key][attribute];
			});
			option.appendChild(document.createTextNode(key));
			select.appendChild(option)
		});
		this.elements.add(select)
	}
	textarea() {
		/*{
			type: 'textarea',
			description: 'textarea',
			attributes: {
				rows:8,
				value:'values can be passed with this pseudo attribute'
			}
		}*/
		const textarea = document.createElement('textarea');
		let execute;
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				execute = this.tile.attributes[key]; // because of this scope
				textarea[key] = () => {
					eval(execute)
				};
			} else if (key !== 'value') textarea[key] = this.tile.attributes[key];
		});
		if ('value' in this.tile.attributes) textarea.appendChild(document.createTextNode(this.tile.attributes.value));
		this.elements.add(textarea);
	}
	checkbox(radio = null) {
		/*{
			type: 'checkbox', or 'radio'
			description:'checkboxes',
			content: {
				'Checkbox 1': {
					name: 'ch1'
				},
				'Checkbox 2': {
					name: 'ch1'
				}
			}
		}*/
		Object.keys(this.tile.content).forEach(checkbox => {
			let label = document.createElement('label'),
				input = document.createElement('input'),
				span = document.createElement('span'),
				execute;
			input.type = radio ? 'radio' : 'checkbox';
			label.classList = 'custominput';
			span.classList = 'checkmark';
			label.appendChild(document.createTextNode(checkbox));
			Object.keys(this.tile.content[checkbox]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					execute = this.tile.content[checkbox][attribute]; // because of this scope
					input[attribute] = () => {
						eval(execute)
					};
				} else input[attribute] = this.tile.content[checkbox][attribute];
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
			description:'links',
			content: {
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
		Object.keys(this.tile.content).forEach(link => {
			let li = document.createElement('li'),
				a = document.createElement('a'),
				execute;
			Object.keys(this.tile.content[link]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					execute = this.tile.content[link][attribute] // because of this scope
					a[attribute] = () => {
						eval(execute);
					};
				} else a[attribute] = this.tile.content[link][attribute];
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
			description:'signature'
		} */
		const canvas = document.createElement('canvas');
		canvas.id = 'canvas',
			this.elements.add(canvas);
		//this tile does not process attributes, therefore they can be reassigned
		this.tile.attributes = {
			"value": "Unterschrift löschen",
			"onpointerdown": "signaturePad.clear()"
		};
		this.input('button');
		this.signaturePad = true;
	}
}