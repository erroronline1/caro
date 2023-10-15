/*
//test assembling
import{getNextElementID} from '../js/assemble.js';
window.getNextElementID=getNextElementID;
import {api} from '../scripts.js';
api.getForms('qr','template', 'qr');
*/

//test composing
import {Compose, constructNewForm, dragNdrop} from '../js/compose.js';
import{getNextElementID} from '../js/assemble.js';
window.getNextElementID=getNextElementID;
window.Compose=Compose;
window.constructNewForm=constructNewForm;
window.dragNdrop=dragNdrop;
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

new Compose(createForm);

