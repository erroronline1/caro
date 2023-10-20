/*
//test assembling
import{getNextElementID} from '../js/assemble.js';
window.getNextElementID=getNextElementID;
import {api} from '../scripts.js';
api.getForms('qr','template', 'qr');
*/

//test composing
import {
    Compose,
    constructNewForm,
    dragNdrop,
    importForm,
    assembleNewElementCallback
} from '../js/compose.js';
import {
    getNextElementID
} from '../js/assemble.js';
window.getNextElementID = getNextElementID;
window.Compose = Compose;
window.constructNewForm = constructNewForm;
window.dragNdrop = dragNdrop;
window.assembleNewElementCallback = assembleNewElementCallback;
const createForm = {
    "content": [
        [{
            "type": "text",
            "description": "what to do",
            "content": "choose available elements from this panel. set your parameters and add fields. advanced attributes (href, value, events, etc) have to be set in json-format with double-quotes. change your order by dragging the elements. during composing indicators for containers are not available. dragging is available on devies with mice only."
        }, {
            "form": true,
            "type": "compose_text",
            "description": "add an informative text"
        }, {
            "form": true,
            "type": "compose_textinput",
            "description": "add a single line text input"
        }, {
            "form": true,
            "type": "compose_textarea",
            "description": "add a multiline text input"
        }, {
            "form": true,
            "type": "compose_numberinput",
            "description": "add a number input"
        }, {
            "form": true,
            "type": "compose_dateinput",
            "description": "add a date input"
        }, {
            "form": true,
            "type": "compose_links",
            "description": "add a list of links"
        }, {
            "form": true,
            "type": "compose_radio",
            "description": "add a set of single selection options"
        }, {
            "form": true,
            "type": "compose_checkbox",
            "description": "add a set of multiple selection options"
        }, {
            "form": true,
            "type": "compose_select",
            "description": "add a dropdown"
        }, {
            "form": true,
            "type": "compose_file",
            "description": "add a file upload"
        }, {
            "form": true,
            "type": "compose_photo",
            "description": "add a photo upload"
        }, {
            "form": true,
            "type": "compose_signature",
            "description": "add a signature pad"
        }, {
            "form": true,
            "type": "compose_qr",
            "description": "add a qr scanner field"
        }, ],
        [{
            "type": "button",
            "attributes": {
                "value": "♺ generate/update form object",
                "onclick": "console.log(constructNewForm())"
            }
        }],
        [{
            "type": "trash",
            "description": "drop panel here to delete"
        }]
    ]
};

new Compose(createForm);

const oldform = {
    "form": {},
    "content": [
        [{
            "type": "radio",
            "description": "strength",
            "content": {
                "1": {},
                "2": {},
                "3": {},
                "4": {},
                "5": {}
            }
        }],
        [{
            "type": "select",
            "description": "strength",
            "content": {
                "1": {},
                "2": {},
                "3": {},
                "4": {},
                "5": {},
                "6": {}
            }
        }, {
            "type": "textinput",
            "description": "comment",
            "attributes": {
                "placeholder": "enter comment here if categories do not fit"
            }
        }]
    ]
};
importForm(oldform);