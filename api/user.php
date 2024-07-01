<?php
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

// add, edit and delete users
require_once('./_calendarutility.php');

class USER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function profile(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $_SESSION['user']['id'],
						':name' => $_SESSION['user']['name']
					]
				]);
				$user = $user ? $user[0] : null;
				// prepare user-array to update, return error if not found
				if (!$user) $this->response(null, 406);

				// convert image
				// save and convert image
				if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_reset_photo'))) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user['name'])); 
					$_FILES[LANG::PROPERTY('user.edit_take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				if(array_key_exists(LANG::PROPERTY('user.edit_take_photo'), $_FILES) && $_FILES[LANG::PROPERTY('user.edit_take_photo')]['tmp_name']) {
					if ($user['image'] && $user['id'] > 1) UTILITY::delete('../' . $user['image']);

					$user['image'] = UTILITY::storeUploadedFiles([LANG::PROPERTY('user.edit_take_photo')], UTILITY::directory('users'), ['profilepic_' . $user['name']])[0];
					UTILITY::resizeImage($user['image'], INI['limits']['user_image'], UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}
				// process settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];
				$user['app_settings']['forceDesktop'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_force_desktop'));
				$user['app_settings']['homeoffice'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_homeoffice'));
				$user['app_settings']['language'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_language')) ? : 'en';
				$user['app_settings']['theme'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_theme'));
				if ($primaryUnit = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_primary_unit'))){
					$user['app_settings']['primaryUnit'] = array_search($primaryUnit, LANGUAGEFILE['units']);
				}
				
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_put', [
					'values' => [
						':id' => $user['id'],
						':name' => $user['name'],
						':permissions' => $user['permissions'],
						':units' => $user['units'],
						':token' => $user['token'],
						':orderauth' => $user['orderauth'],
						':image' => $user['image'],
						':app_settings' => json_encode($user['app_settings']),
						':skills' => $user['skills']
					]
				]) !== false) $this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => LANG::GET('user.edit_user_saved', [':name' => $user['name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => LANG::GET('user.edit_user_not_saved'),
						'type' => 'error'
					]]);

				break;
			case 'GET':
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $_SESSION['user']['id'],
						':name' => $_SESSION['user']['name']
					]
				]);
				$user = $user ? $user[0] : null;
				// prepare user-array to update, return error if not found
				if (!$user) $this->response(null, 406);

				$calendar = new CALENDARUTILITY($this->_pdo);
				$timesheet_stats = $calendar->timesheetSummary([$user]);//, '2024-05-01'));
				$usertimesheet = array_search($user['id'], array_column($timesheet_stats, '_id'));
				if ($usertimesheet !== false) $timesheet_stats = $timesheet_stats[$usertimesheet];

				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];

				$permissions = [];
				foreach(explode(',', $user['permissions']) as $level){
					$permissions[] = LANG::GET('permissions.' . $level);
				}

				$units = $primary_unit = [];
				foreach(explode(',', $user['units']) as $unit){
					$primary_unit[LANG::GET('units.' . $unit)] = ['name' => LANG::PROPERTY('user.settings_primary_unit')];
					$units[] = LANG::GET('units.' . $unit);
				}
				if(array_key_exists('primaryUnit', $user['app_settings'])) $primary_unit[LANG::GET('units.' . $user['app_settings']['primaryUnit'])]['checked'] = true;

				$user['skills'] = explode(',', $user['skills'] ?  : '');
				$skillmatrix = '';
				foreach (LANGUAGEFILE['skills'] as $duty => $skills){
					if ($duty === 'LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($skill === '_DESCRIPTION') continue;
						foreach(LANGUAGEFILE['skills']['LEVEL'] as $level => $leveldescription){
							$skillmatrix .= in_array($duty . '.' . $skill . '.' . $level, $user['skills']) ? " \n" . LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription . ': ' . $leveldescription: '';
						}
					}
				}

				$result['render'] = ['content' => [
						[
							['type' => 'textblock',
							'description' => LANG::GET('user.display_user'),
							'content' => LANG::GET('user.edit_name') . ': ' . $user['name'] . "\n" .
								LANG::GET('user.display_permissions') . ': ' . implode(', ', $permissions) . "\n" .
								LANG::GET('user.edit_units') . ': ' . implode(', ', $units) . "\n" .
								($user['orderauth'] ? " \n" . LANG::GET('user.display_orderauth'): '') .
								(array_key_exists('initialovertime', $user['app_settings']) && $_SESSION['user']['app_settings']['initialovertime'] ? " \n \n" . LANG::GET('user.settings_initial_overtime') . ': ' . $user['app_settings']['initialovertime'] : '') .
								(array_key_exists('weeklyhours', $user['app_settings']) && $_SESSION['user']['app_settings']['weeklyhours'] ? " \n" . LANG::GET('user.settings_weekly_hours') . ': ' . str_replace(';', "\n", $user['app_settings']['weeklyhours']) : '') .
								(array_key_exists('_overtime', $timesheet_stats) ? " \n" . LANG::GET('calendar.export_sheet_overtime', [':number' => round($timesheet_stats['_overtime'], 2)]) : '') .
								(array_key_exists('annualvacation', $user['app_settings']) && $_SESSION['user']['app_settings']['annualvacation'] ? " \n \n" . LANG::GET('user.settings_annual_vacation') . ': ' . str_replace(';', "\n", $user['app_settings']['annualvacation']) : '') .
								(array_key_exists('_leftvacation', $timesheet_stats) ? " \n" . LANG::GET('calendar.export_sheet_left_vacation', [':number' => $timesheet_stats['_leftvacation']]) : '') .
								($skillmatrix ? " \n" . $skillmatrix : '')
							]
						],[
							[
								'type' => 'photo',
								'attributes' => [
									'name' => LANG::GET('user.edit_take_photo')
								],
								'hint' => LANG::GET('user.edit_take_photo_hint')
							],
						]
					],
					'form' => [
						'data-usecase' => 'user',
						'action' => "javascript:api.user('put', 'profile')"
					]
				];

				if ($user['image']) $result['render']['content'][1] = [
					[
						[
							'type' => 'image',
							'description' => LANG::GET('user.edit_export_user_image'),
							'attributes' => [
								'name' => $user['name'] . '_pic',
								'url' => $user['image']
							]
						]
					],
					[
						[
							'type' => 'checkbox',
							'content' => [
								LANG::GET('user.edit_reset_photo') => []
							]
						]
					],
				$result['render']['content'][1]
				];

				$languages = [];
				foreach(glob('language.*.ini') as $file){
					$lang = explode('.', $file);
					$languages[$lang[1]] = (array_key_exists('language', $user['app_settings']) && $user['app_settings']['language'] === $lang[1]) ? ['selected' => true] : [];
				}
				$result['render']['content'][] = [
					[
						'type' => 'checkbox',
						'description' => LANG::GET('user.settings'),
						'content' => [
							LANG::GET('user.settings_force_desktop') => [],
							LANG::GET('user.settings_homeoffice') => [],
						]
					], [
						'type' => 'select',
						'attributes' => [
							'name' => LANG::GET('user.settings_language')
						],
						'content' => $languages
					], [
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('user.settings_theme')
						],
						'content' => [
							LANG::GET('user.settings_theme_light') => (!array_key_exists('theme', $user['app_settings']) || $user['app_settings']['theme'] === 'light') ? ['checked' => true, 'value' => 'light'] : ['value' => 'light'],
							LANG::GET('user.settings_theme_aurora') => (array_key_exists('theme', $user['app_settings']) && $user['app_settings']['theme'] === 'aurora') ? ['checked' => true, 'value' => 'aurora'] : ['value' => 'aurora'],
							LANG::GET('user.settings_theme_dark') => (array_key_exists('theme', $user['app_settings']) && $user['app_settings']['theme'] === 'dark') ? ['checked' => true, 'value' => 'dark'] : ['value' => 'dark'],
						]
					], [
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('user.settings_primary_unit')
						],
						'hint' => LANG::GET('user.settings_hint'),
						'content' => $primary_unit
					]
				];
				if (array_key_exists('forceDesktop', $user['app_settings']) && $user['app_settings']['forceDesktop']) $result['render']['content'][count($result['render']['content'])-1][0]['content'][LANG::GET('user.settings_force_desktop')] = ['checked' => true];
				if (array_key_exists('homeoffice', $user['app_settings']) && $user['app_settings']['homeoffice']) $result['render']['content'][count($result['render']['content'])-1][0]['content'][LANG::GET('user.settings_homeoffice')] = ['checked' => true];

				$storedfiles = UTILITY::listFiles(UTILITY::directory('users'), 'asc');
				$userfiles = [];
				foreach ($storedfiles as $file){
					if (explode('_', pathinfo($file)['filename'])[0] == $user['id']) {
						$userfiles[pathinfo($file)['basename']] = ['href' => substr($file, 1)];
					}
				}
				if ($userfiles) {
					array_push($result['render']['content'][0], 
					['type' => 'br'],
					[
						'type' => 'links',
						'content' => $userfiles
					]);
				}

				$this->response($result);
				break;
		}
	}

	public function user(){
		if (!PERMISSION::permissionFor('users')) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$permissions = [];
				$units = [];
				$user = [
					'name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_name')),
					'permissions' => '',
					'units' => '',
					'token' => '',
					'orderauth' => '',
					'image' => '',
					'app_settings' => [],
					'skills' => []
				];
		
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $user['name'], $matches)) $this->response(['response' => ['msg' => LANG::GET('user.error_forbidden_name', [':name' => $user['name']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('permissions.' . $level))) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);

				// chain checked organizational units
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('permissions.' . $unit))) {
						$units[] = $unit;
					}
				}
				$user['units'] = implode(',', $units);

				$annualvacation = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_annual_vacation'));
				$user['app_settings']['annualvacation'] = $annualvacation ? str_replace("\n", ';', $annualvacation) : '';
				$weeklyhours = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_weekly_hours'));
				$user['app_settings']['weeklyhours'] = $weeklyhours ? str_replace("\n", ';', $weeklyhours) : '';
				$user['app_settings']['initialovertime'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_initial_overtime'));

				foreach (LANGUAGEFILE['skills'] as $duty => $skills){
					if ($duty === 'LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($level = UTILITY::propertySet($this->_payload, LANG::PROPERTY('skills.' . $duty . '._DESCRIPTION') . '_' . LANG::PROPERTY('skills.' . $duty . '.' . $skill))){
							if ($level != 0) $user['skills'][] = $duty . '.' . $skill . '.' . $level;
						}
					}
				}

				// generate order auth
				if(UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_order_authorization')) == LANG::GET('user.edit_order_authorization_generate')){
					$orderauths = [];
					$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
					foreach ($users as $row){
						$orderauths[] = $row['orderauth'];
					}
					do {
						$user['orderauth'] = random_int(10000, max(99999, count($users)*100)); 
					} while (in_array($user['orderauth'], $orderauths));
				}

				// generate token
				if(UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_token_renew'))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}

				// save and convert image or create default
				if (!(array_key_exists(LANG::PROPERTY('user.edit_take_photo'), $_FILES) && $_FILES[LANG::PROPERTY('user.edit_take_photo')]['tmp_name'])) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user['name'])); 
					$_FILES[LANG::PROPERTY('user.edit_take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				$user['image'] = UTILITY::storeUploadedFiles([LANG::PROPERTY('user.edit_take_photo')], UTILITY::directory('users'), ['profilepic_' . $user['name']])[0];
				UTILITY::resizeImage($user['image'], INI['limits']['user_image'], UTILITY_IMAGE_REPLACE);
				$user['image'] = substr($user['image'], 3);

				// add user documents
				if (array_key_exists(LANG::PROPERTY('user.edit_add_document'), $_FILES) && $_FILES[LANG::PROPERTY('user.edit_add_document')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('user.edit_add_document')], UTILITY::directory('users'), [$user['id'] . '_' . $user['name'] . '_' . date('YmdHis')], [UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_add_document_rename'))]);
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'user_post', [
					'values' => [
						':name' => $user['name'],
						':permissions' => $user['permissions'],
						':units' => $user['units'],
						':token' => $user['token'],
						':orderauth' => $user['orderauth'],
						':image' => $user['image'],
						':app_settings' => json_encode($user['app_settings']),
						':skills' => implode(',', $user['skills'])
					]
				])) $this->response([
					'response' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => LANG::GET('user.edit_user_saved', [':name' => $user['name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => LANG::GET('user.edit_user_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'PUT':
				$permissions = [];
				$units = [];
		
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => intval($this->_requestedID),
						':name' => strval($this->_requestedID)
					]
				]);
				$user = $user ? $user[0] : null;
				// prepare user-array to update, return error if not found
				if (!$user) $this->response(null, 406);
				
				$updateName = !($user['name'] == UTILITY::propertySet($this->_payload, LANG::GET('user.edit_name')));
				$user['name'] = UTILITY::propertySet($this->_payload, LANG::GET('user.edit_name'));

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $user['name'], $matches)) $this->response(['response' => ['msg' => LANG::GET('user.error_forbidden_name', [':name' => $user['name']]), 'type' => 'error']]);
				}
				
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('permissions.' . $level))) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);

				// chain checked organizational units
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('units.' . $unit))) {
						$units[] = $unit;
					}
				}
				$user['units'] = implode(',', $units);
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];
				$annualvacation = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_annual_vacation'));
				$user['app_settings']['annualvacation'] = $annualvacation ? str_replace("\n", ';', $annualvacation) : '';
				$weeklyhours = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_weekly_hours'));
				$user['app_settings']['weeklyhours'] = $weeklyhours ? str_replace("\n", ';', $weeklyhours) : '';
				$user['app_settings']['initialovertime'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.settings_initial_overtime'));

				$user['skills'] = [];
				foreach (LANGUAGEFILE['skills'] as $duty => $skills){
					if ($duty === 'LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($level = UTILITY::propertySet($this->_payload, LANG::PROPERTY('skills.' . $duty . '._DESCRIPTION') . '_' . LANG::PROPERTY('skills.' . $duty . '.' . $skill))){
							if ($level != 0) $user['skills'][] = $duty . '.' . $skill . '.' . $level;
						}
					}
				}
				// generate order auth
				if(UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_order_authorization')) == LANG::GET('user.edit_order_authorization_revoke')){
					$user['orderauth'] = '';
				}
				if(UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_order_authorization')) == LANG::GET('user.edit_order_authorization_generate')){
					$orderauths = [];
					$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
					foreach ($users as $row){
						$orderauths[] = $row['orderauth'];
					}
					do {
						$user['orderauth'] = random_int(10000, max(99999, count($users)*100));
					} while (in_array($user['orderauth'], $orderauths));
				}

				// generate token
				if(UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('user.edit_token_renew')))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}
				// save and convert image or create default
				if (!(array_key_exists(LANG::PROPERTY('user.edit_take_photo'), $_FILES) && $_FILES[LANG::PROPERTY('user.edit_take_photo')]['tmp_name']) && $updateName) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user['name'])); 
					$_FILES[LANG::PROPERTY('user.edit_take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				if (array_key_exists(LANG::PROPERTY('user.edit_take_photo'), $_FILES) && $_FILES[LANG::PROPERTY('user.edit_take_photo')]['tmp_name']) {
					if ($user['image'] && $user['id'] > 1) UTILITY::delete('../' . $user['image']);
					$user['image'] = UTILITY::storeUploadedFiles([LANG::PROPERTY('user.edit_take_photo')], UTILITY::directory('users'), ['profilepic_' . $user['name']])[0];
					UTILITY::resizeImage($user['image'], INI['limits']['user_image'], UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}

				// add user documents
				if (array_key_exists(LANG::PROPERTY('user.edit_add_document'), $_FILES) && $_FILES[LANG::PROPERTY('user.edit_add_document')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('user.edit_add_document')], UTILITY::directory('users'), [$user['id'] . '_' . $user['name'] . '_' . date('YmdHis')], [UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_add_document_rename'))]);
				}
		
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_put', [
					'values' => [
						':id' => $user['id'],
						':name' => $user['name'],
						':permissions' => $user['permissions'],
						':units' => $user['units'],
						':token' => $user['token'],
						':orderauth' => $user['orderauth'],
						':image' => $user['image'],
						':app_settings' => json_encode($user['app_settings']),
						':skills' => implode(',', $user['skills'])
					]
				]) !== false) $this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => LANG::GET('user.edit_user_saved', [':name' => $user['name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => LANG::GET('user.edit_user_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'GET':
				$datalist = [];
				$options = ['...' . LANG::GET('user.edit_existing_user_new') => (!$this->_requestedID) ? ['selected' => true] : []];
				$result = [];
				
				// prepare existing users lists
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				foreach($user as $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = ($row['name'] === $this->_requestedID) ? ['selected' => true] : [];
				}
		
				// select single user based on id or name
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => intval($this->_requestedID) ? : '',
						':name' => strval($this->_requestedID) ? : ''
					]
				]);
				$user = $user ? $user[0] : null;
				if (!$user){ $user = [
					'id' => null,
					'name' => '',
					'permissions' => '',
					'units' => '',
					'token' => '',
					'orderauth' => '',
					'image' => '',
					'app_settings' => '',
					'skills' => ''
				];}
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$user['id'] && $this->_requestedID !== '...' . LANG::GET('user.edit_existing_user_new')) $result['response'] = ['msg' => LANG::GET('user.error_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// display form for adding a new user with ini related permissions
				$permissions = [];
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $user['permissions'])) ? ['checked' => true] : [];
				}
				$units = [];
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					$units[$description] = in_array($unit, explode(',', $user['units'])) ? ['checked' => true] : [];
				}
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];

				$user['skills'] = explode(',', $user['skills'] ?  : '');
				$skilldatalistwithlabel = $skilldatalist = [];
				$skilldatalistnum = 0;
				$skillmatrix = [];
				foreach (LANGUAGEFILE['skills'] as $duty => $skills){
					$skillselection = [];
					if ($duty === 'LEVEL') {
						$skilldatalistnum ++;
						foreach(LANGUAGEFILE['skills']['LEVEL'] as $level => $leveldescription){
							$skilldatalistwithlabel[] = ['value' => $level, 'label' => $leveldescription];
							$skilldatalist[] = ['value' => $level];
						}
						$skilldatalistwithlabel = [
							'type' => 'datalist',
							'content' => $skilldatalistwithlabel,
							'attributes' => [
								'id' => 'skillmarkerswithlabels' . $skilldatalistnum,
								'class' => 'rangedatalist'
							]
						];
						continue;
					}
					foreach ($skills as $skill => $skilldescription){
						if ($skill === '_DESCRIPTION') continue;
						$userlevel = 0;
						foreach(LANGUAGEFILE['skills']['LEVEL'] as $level => $leveldescription){
							if (in_array($duty . '.' . $skill . '.' . $level, $user['skills'])) $userlevel = $level;
						}
						$skillselection[] = [
							'type' => 'range',
							'attributes' => [
								'name' => $skills['_DESCRIPTION'] . ' ' . $skilldescription,
								'min' => 0,
								'max' => count(LANGUAGEFILE['skills']['LEVEL']) - 1,
								'value' => strval($userlevel),
								'list' => array_search($skill, array_keys($skills)) < 2 ? 'skillmarkerswithlabels' . $skilldatalistnum : ''
							],
						];
						if (array_search($skill, array_keys($skills)) < 2) $skillselection[] = $skilldatalistwithlabel;
					}
					$skillmatrix[] = [
						...$skillselection
					];
				}

				$result['render'] = ['content' => [
					[
						[
							'type' => 'datalist',
							'content' => array_values(array_unique($datalist)),
							'attributes' => [
								'id' => 'users'
							]
						],
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('user.edit_existing_user_select'),
								'onchange' => "api.user('get', 'user', this.value)"
							],
							'content' => $options
						],
						[
							'type' => 'search',
							'attributes' => [
								'name' => LANG::GET('user.edit_existing_user'),
								'list' => 'users',
								'onkeypress' => "if (event.key === 'Enter') {api.user('get', 'user', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'text',
							'attributes' => [
								'name' => LANG::GET('user.edit_name'),
								'required' => true,
								'value' => $user['name'] ? : ''
							]
						],
						[
							'type' => 'checkbox',
							'description' => LANG::GET('user.edit_permissions'),
							'content' => $permissions,
							'hint' => LANG::GET('user.edit_permissions_hint')
						],
						[
							'type' => 'checkbox',
							'description' => LANG::GET('user.edit_units'),
							'content' => $units,
							'hint' => LANG::GET('user.edit_units_hint')
						]
					],[
						[
							[
								'type' => 'text',
								'attributes' => [
									'name' => LANG::GET('user.settings_initial_overtime'),
									'value' => array_key_exists('initialovertime', $user['app_settings']) ? $user['app_settings']['initialovertime'] : 0
								],
								'hint' => LANG::GET('user.settings_initial_overtime_hint')
							],
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('user.settings_weekly_hours'),
									'value' => array_key_exists('weeklyhours', $user['app_settings']) ? str_replace(';', "\n", $user['app_settings']['weeklyhours']) : ''
								],
								'hint' => LANG::GET('user.settings_weekly_hours_hint')
							]
						], [
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('user.settings_annual_vacation'),
									'value' => array_key_exists('annualvacation', $user['app_settings']) ? str_replace(';', "\n", $user['app_settings']['annualvacation']) : ''
								],
								'hint' => LANG::GET('user.settings_annual_vacation_hint')
							]
						]
					],
					[
						[
							'type' => 'photo',
							'attributes' => [
								'name' => LANG::GET('user.edit_take_photo')
							],
							'hint' => LANG::GET('user.edit_take_photo_hint')
						],
					],[
						[
							'type' => 'file',
							'attributes' => [
								'name' => LANG::GET('user.edit_add_document')
							],
							'hint' => LANG::GET('user.edit_add_document_hint')
						], [
							'type' => 'text',
							'attributes' => [
								'name' => LANG::GET('user.edit_add_document_rename')
							],
							'hint' => LANG::GET('user.edit_add_document_rename_hint')
						]
					],[
						[
							'type' => 'radio',
							'attributes' => [
								'name' => LANG::GET('user.edit_order_authorization')
							],
							'content' => [
								LANG::GET('user.edit_order_authorization_generate') => [],
								LANG::GET('user.edit_order_authorization_revoke') => []
							]
						]
					],
						$skillmatrix
					,[
						[
							'type' => 'checkbox',
							'description' => LANG::GET('user.edit_token'),
							'content' => [
								LANG::GET('user.edit_token_renew') => []
							]
						],
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => LANG::GET('user.edit_delete_button'),
								'type' => 'button', // apparently defaults to submit otherwise
								'onpointerup' => $user['id'] ? "new Dialog({type: 'confirm', header: '". LANG::GET('user.edit_delete_confirm_header', [':name' => $user['name']]) ."', options:{".
									"'".LANG::GET('user.edit_delete_confirm_cancel')."': false,".
									"'".LANG::GET('user.edit_delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.user('delete', 'user', ". $user['id'] . ")})" : '',
								'disabled' => $user['id'] < 2
							]
						]
					]],
					'form' => [
						'data-usecase' => 'user',
						'action' => $user['id'] ? "javascript:api.user('put', 'user', '" . $user['id'] . "')" : "javascript:api.user('post', 'user')"
					]];

					if ($user['image']) $result['render']['content'][3] = [
						[
							[
								'type' => 'image',
								'description' => LANG::GET('user.edit_export_user_image'),
								'attributes' => [
									'name' => $user['name'] . '_pic',
									'url' => $user['image']
								]
							]
						],
						$result['render']['content'][3]
					];

					$storedfiles = UTILITY::listFiles(UTILITY::directory('users'), 'asc');
					$userfiles = [];
					foreach ($storedfiles as $file){
						if (explode('_', pathinfo($file)['filename'])[0] == $user['id']) {
							$userfiles[pathinfo($file)['basename']] = ['href' => substr($file, 1)];
						}
					}
					if ($userfiles) {
						array_push($result['render']['content'][4], 
						['type' => 'br'],
						[
							'type' => 'links',
							'content' => $userfiles
						]);
					}

					if ($user['orderauth']) $result['render']['content'][5]=[
						[
							[
								'type' => 'text',
								'attributes' => [
									'name' => LANG::GET('user.edit_order_authorization_current'),
									'value' => $user['orderauth']
								]
							]
						],
						$result['render']['content'][5]
					];
					if ($user['token']) $result['render']['content'][7]=[
						[
							[
								'type' => 'image',
								'description' => LANG::GET('user.edit_export_qr_token'),
								'attributes' => [
								'name' => $user['name'] . '_token',
								'qrcode' => $user['token']]
							]
						],
						$result['render']['content'][7]
					];

				$this->response($result);
				break;

			case 'DELETE':
				// prefetch to return proper name after deletion
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => intval($this->_requestedID),
						':name' => strval($this->_requestedID)
					]
				]);
				$user = $user[0];
				if ($user['id'] < 2) $this->response([], 401);
				if ($user['image'] && $user['id'] > 1) UTILITY::delete('../' . $user['image']);

				if (SQLQUERY::EXECUTE($this->_pdo, 'user_delete', [
					'values' => [
						':id' => $user['id']
					]
				])) $this->response([
					'response' => [
						'msg' => LANG::GET('user.edit_user_deleted', [':name' => $user['name']]),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('user.edit_user_not_deleted', [':name' => $user['name']]),
						'id' => $user['id'],
						'type' => 'error'
					]]);
				break;
		}
	}

	private function defaultPic($name){
		$names = explode(' ', $name);
		$initials = strtoupper(substr($names[0], 0, 1));
		if (count($names) >1) $initials .= strtoupper(substr($names[count($names) - 1], 0, 1));

		$image = imagecreatetruecolor(256, 256);
		$font_size = round(256 / 2);
		$y = round(256 / 2 + $font_size / 2.4);
		$x= round(256 / 2 - $font_size *.33 * strlen($initials));
		$background_color = imagecolorallocate($image, 163, 190, 140); // nord green
		imagefill($image, 0, 0, $background_color);
		$text_color = imagecolorallocate($image, 46, 52, 64); // nord dark
		imagefttext($image, $font_size, 0, $x, $y, $text_color, '../media/UbuntuMono-R.ttf', $initials);
		ob_start();
		imagepng($image);
		$image = ob_get_contents();
		ob_end_clean();
		return $image;
	}
}
?>