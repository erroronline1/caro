/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

//import relevant functions and set global scope

import { Dialog, Toast, Assemble, assemble_helper } from "../js/assemble.js";
window.Dialog = Dialog;
window.Toast = Toast;
window.Assemble = Assemble;
window.assemble_helper = assemble_helper;

import { api } from "../js/api.js";
window.api = api;

import { Compose, MetaCompose, compose_helper } from "../js/compose.js";
window.Compose = Compose;
window.MetaCompose = MetaCompose;
window.compose_helper = compose_helper;

import { LANG } from "../js/language.js";
window.LANG = LANG;

api.application("post", "login");
