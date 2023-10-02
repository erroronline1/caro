class Assembly {
	constructor(setup) {
		this.multipletiles = new Set();
		this.multiplecontainerID = null;
		for (let i = 0; i < setup.length; i++) {
			this.elements = new Set();
			this.icon = setup[i].icon;
			this.fieldset = setup[i].fieldset;
			this.collapsed = setup[i].collapsed;
			this.attributes = setup[i].attributes;
			this.items = setup[i].items;
			this[setup[i].type]();
			if (setup.length < 2) {
				document.getElementById('main').insertAdjacentElement('beforeend', this.single());
				return;
			}
			this.multipletiles.add(this.single())
		}
		document.getElementById('main').insertAdjacentElement('beforeend', this.multiple());
		document.getElementById(this.multiplecontainerID).addEventListener('scroll', scroller);
	}

	single(classList = null) {
		const section = document.createElement('section'),
			icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
			use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
		if (classList) section.classList = classList;
		use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', 'media/sprite.svg#'+this.icon);
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
			icon: '#read',
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
			icon: '#text',
			fieldset: 'Textfeld',
			attributes: {
				type: 'text',
				placeholder: 'Textfeld'
			}
		}*/
		const input = document.createElement('input');
		Object.keys(this.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				input[key] = () => {
					eval(this.attributes[key])
				};
			} else input[key] = this.attributes[key];
		});
		this.elements.add(input);
	}
	file() {
		/*{
			type: 'file',
			icon: '#upload',
			fieldset: 'Dateiupload',
			attributes: {
				id: 'fileupload',
				name: 'files[]',
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
		Object.keys(this.attributes).forEach(key => {
			input[key] = this.attributes[key];
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Datei auswählen...'));
		this.elements.add(input)
		this.elements.add(label);
	}
	select() {
		/*{
			type: 'select',
			icon: '#select',
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
		if (this.attributes !== undefined) Object.keys(this.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				select[key] = () => {
					eval(this.attributes[key])
				};
			} else select[key] = this.attributes[key];
		});
		Object.keys(this.items).forEach(key => {
			let option = document.createElement('option');
			Object.keys(this.items[key]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					option[attribute] = () => {
						eval(this.items[key][attribute])
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
			icon: '#text',
			fieldset: 'Textarea',
			attributes: {
				rows:8,
				value:'werte werden auf diese weise übergeben'
			}
		}*/
		const textarea = document.createElement('textarea');
		Object.keys(this.attributes).forEach(key => {
			if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(key) > -1) {
				textarea[key] = () => {
					eval(this.attributes[key])
				};
			} else if (key !== 'value') textarea[key] = this.attributes[key];
		});
		if ('value' in this.attributes) textarea.appendChild(document.createTextNode(this.attributes.value));
		this.elements.add(textarea);
	}
	checkbox(radio = null) {
		/*{
			type: 'checkbox', or 'radio'
			icon: '#checkbox',
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
				span = document.createElement('span');
			input.type = radio ? 'radio' : 'checkbox';
			label.classList = 'custominput';
			span.classList = 'checkmark';
			label.appendChild(document.createTextNode(checkbox));
			Object.keys(this.items[checkbox]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					input[attribute] = () => {
						eval(this.items[checkbox][attribute])
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
			icon: '#link',
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
				a = document.createElement('a');
			Object.keys(this.items[link]).forEach(attribute => {
				if (['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'].indexOf(
						attribute) > -1) {
					a[attribute] = () => {
						eval(this.items[link][attribute])
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
}