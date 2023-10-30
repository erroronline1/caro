//import relevant functions and set global scope

import {
    Assemble,
    assemble_helper
} from '../js/assemble.js';
window.Assemble = Assemble;
window.assemble_helper = assemble_helper;

import {
    api
} from '../js/api.js';
window.api = api;

import {
    Compose,
    MetaCompose,
    compose_helper
} from '../js/compose.js';
window.Compose = Compose;
window.MetaCompose = MetaCompose;
window.compose_helper = compose_helper;

api.start();

