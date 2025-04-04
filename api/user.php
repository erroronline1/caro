<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *     _     ___         _ _       _
	 *   _| |___|  _|___ _ _| | |_ ___|_|___
	 *  | . | -_|  _| .'| | | |  _| . | |  _|
	 *  |___|___|_| |__,|___|_|_| |  _|_|___|
	 *                            |_|
	 * create a default user profile picture from initials
	 * @param string $name username
	 * 
	 * @return string image data
	 */
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
	
	/**
	 *               ___ _ _
	 *   ___ ___ ___|  _|_| |___
	 *  | . |  _| . |  _| | | -_|
	 *  |  _|_| |___|_| |_|_|___|
	 *  |_|
	 * display user profile and set application settings
	 */
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
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.reset_photo'))) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user['name'])); 
					$_FILES[$this->_lang->PROPERTY('user.take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				if(isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name']) {
					if ($user['image'] && $user['id'] > 1) UTILITY::delete('../' . $user['image']);

					$user['image'] = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.take_photo')], UTILITY::directory('users'), ['profilepic_' . $user['name']])[0];
					UTILITY::alterImage($user['image'], CONFIG['limits']['user_image'], UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}
				// process settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];
				$user['app_settings']['forceDesktop'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_force_desktop'));
				$user['app_settings']['homeoffice'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_homeoffice'));
				$user['app_settings']['language'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_language')) ? : 'en';
				$user['app_settings']['theme'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_theme'));
				$user['app_settings']['autocomplete_forth'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_autocomplete_forth'));
				$user['app_settings']['autocomplete_back'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_autocomplete_back'));

				if ($primaryUnit = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_primary_unit'))){
					$user['app_settings']['primaryUnit'] = array_search($primaryUnit, $this->_lang->_USER['units']);
				}
				if ($primaryRecordState = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_primary_recordstate'))){
					if ($primaryRecordState === $this->_lang->GET('record.casestate_filter_all'))
						unset($user['app_settings']['primaryRecordState']);
					else
						$user['app_settings']['primaryRecordState'] = array_search($primaryRecordState, $this->_lang->_USER['casestate']['casedocumentation']);
				}
				foreach($user['app_settings'] as $key => $value){
					if (!$value) unset($user['app_settings'][$key]);
				}

				// update user
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_put', [
					'values' => [
						':id' => $user['id'],
						':name' => $user['name'],
						':permissions' => $user['permissions'],
						':units' => $user['units'],
						':token' => $user['token'],
						':orderauth' => $user['orderauth'],
						':image' => $user['image'],
						':app_settings' => UTILITY::json_encode($user['app_settings']),
						':skills' => $user['skills']
					]
				]) !== false) {
					$this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => $this->_lang->GET('user.user_saved', [':name' => $user['name']]),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => $this->_lang->GET('user.user_not_saved'),
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

				// import calendar to process timesheet data
				$calendar = new CALENDARUTILITY($this->_pdo);
				$timesheet_stats = $calendar->timesheetSummary([$user]);//, '2024-05-01'));
				$usertimesheet = array_search($user['id'], array_column($timesheet_stats, '_id'));
				if ($usertimesheet !== false) $timesheet_stats = $timesheet_stats[$usertimesheet];

				// resolve application settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];

				// resolve permissions
				$permissions = [];
				foreach(explode(',', $user['permissions']) as $level){
					$permissions[] = $this->_lang->GET('permissions.' . $level);
				}

				// resolve units
				$units = $primary_unit = [];
				foreach(explode(',', $user['units']) as $unit){
					if (!$unit) continue;
					$primary_unit[$this->_lang->GET('units.' . $unit)] = ['name' => $this->_lang->PROPERTY('user.settings_primary_unit')];
					$units[] = $this->_lang->GET('units.' . $unit);
				}
				if(isset($user['app_settings']['primaryUnit'])) $primary_unit[$this->_lang->GET('units.' . $user['app_settings']['primaryUnit'])]['checked'] = true;

				// resolve primary case states for default view within records
				$primary_casestates = [$this->_lang->GET('record.casestate_filter_all') => ['name' => $this->_lang->PROPERTY('user.settings_primary_recordstate')]];
				foreach($this->_lang->_USER['casestate']['casedocumentation'] as $state => $translation){
					$primary_casestates[$translation] = ['name' => $this->_lang->PROPERTY('user.settings_primary_recordstate')];
				}
				if(isset($user['app_settings']['primaryRecordState'])) $primary_casestates[$this->_lang->GET('casestate.casedocumentation.' . $user['app_settings']['primaryRecordState'])]['checked'] = true;
				else $primary_casestates[$this->_lang->GET('record.casestate_filter_all')]['checked'] = true;

				// gather current skills
				$user['skills'] = explode(',', $user['skills'] ?  : '');
				$skillmatrix = '';
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					if ($duty === '_LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($skill === '_DESCRIPTION') continue;
						foreach($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
							$skillmatrix .= in_array($duty . '.' . $skill . '.' . $level, $user['skills']) ? " \n" . $this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription . ': ' . $leveldescription: '';
						}
					}
				}

				// predictable data
				$user_data = [
					[
						[
							'type' => 'collapsible',
							'attributes' => [
								'class' => "em16"
							],
							'content' => [
								[
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->GET('user.display_user')
									],
									'content' => $this->_lang->GET('user.name') . ': ' . $user['name'] . "\n" .
										$this->_lang->GET('user.display_permissions') . ': ' . implode(', ', $permissions) . "\n" .
										($units ? $this->_lang->GET('user.units') . ': ' . implode(', ', $units) . "\n" : '') .
										($user['orderauth'] ? " \n" . $this->_lang->GET('user.display_orderauth'): '') .
										(isset($user['app_settings']['initialovertime']) && $_SESSION['user']['app_settings']['initialovertime'] ? " \n \n" . $this->_lang->GET('user.settings_initial_overtime') . ': ' . $user['app_settings']['initialovertime'] : '') .
										(isset($user['app_settings']['weeklyhours']) && $_SESSION['user']['app_settings']['weeklyhours'] ? " \n" . $this->_lang->GET('user.settings_weekly_hours') . ': ' . $user['app_settings']['weeklyhours'] : '') .
										(isset($timesheet_stats['_overtime']) ? " \n" . $this->_lang->GET('calendar.timesheet.export.sheet_overtime', [':number' => round($timesheet_stats['_overtime'], 2)]) : '') .
										(isset($user['app_settings']['annualvacation']) && $_SESSION['user']['app_settings']['annualvacation'] ? " \n \n" . $this->_lang->GET('user.settings_annual_vacation') . ': ' . $user['app_settings']['annualvacation'] : '') .
										(isset($timesheet_stats['_leftvacation']) ? " \n" . $this->_lang->GET('calendar.timesheet.export.sheet_left_vacation', [':number' => $timesheet_stats['_leftvacation']]) : '') .
										($skillmatrix ? " \n" . $skillmatrix : '')
								]
							]
						]
					]
				];

				// append user training with expiry info 
				$alltrainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
					'replacements' => [
						':ids' => $user['id'] ? : 0
					]
				]);
				$usertrainings = [];
				foreach ($alltrainings as $row){
					$attributes = ['name' => $this->_lang->GET('user.display_training') . ' ' . $row['name'] . ' ' . $row['date']];
					if ($row['expires']){
						$expire = new DateTime($row['expires'], new DateTimeZone(CONFIG['application']['timezone']));
						if ($expire < $this->_currentdate) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training_renewal'] . ' days');
							if ($expire < $this->_currentdate) $attributes['class'] = 'orange';
						}
					}
					$usertrainings[] = [
						'type' => 'textsection',
						'content' => $this->_lang->GET('user.add_training_expires') . ' ' . $row['expires'],
						'attributes' => $attributes
					];
					if ($row['file_path']) $usertrainings[] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => ['href' => './api/api.php/file/stream/' . $row['file_path']]
						]
					];
				}
				if ($usertrainings) $user_data[] = [
						[
							'type' => 'collapsible',
							'attributes' => [
								'class' => "em16"
							],
							'content' => $usertrainings
						]
					];

				// append user sessions
				$usersessions = SQLQUERY::EXECUTE($this->_pdo, 'application_get_user_sessions', [
					'values' => [
						':user_id' => $user['id'] ? : 0
					]
				]);
				$sessions = [];
				foreach($usersessions as $session){
					$sessions[] = $session['date'];
				}
				if ($sessions) $user_data[] = [
					[
						'type' => 'collapsible',
						'attributes' => [
							'class' => "em16"
						],
						'content' => [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('user.sessions', [':days' => CONFIG['lifespan']['sessions']])
								],
								'content' => implode("\n", $sessions)
							]
						]
					]
				];

				$result['render'] = ['content' => [
						$user_data,
						[
							[
								'type' => 'photo',
								'attributes' => [
									'name' => $this->_lang->GET('user.take_photo')
								],
								'hint' => $this->_lang->GET('user.take_photo_hint')
							],
						]
					],
					'form' => [
						'data-usecase' => 'user',
						'action' => "javascript:api.user('put', 'profile')"
					]
				];

				// append image options
				if ($user['image']) {
					$result['render']['content'][1] = [
						[
							[
								'type' => 'image',
								'description' => $this->_lang->GET('user.export_user_image'),
								'attributes' => [
									'name' => $user['name'] . '_pic',
									'url' => $user['image']
								]
							]
						],
						$result['render']['content'][1]
					];
					$result['render']['content'][1][1][] = [
						'type' => 'checkbox',
						'content' => [
							$this->_lang->GET('user.reset_photo') => []
						]
					];
				}
				// retrieve language options
				$languages = [];
				foreach(glob('language.*.json') as $file){
					$lang = explode('.', $file);
					$languages[$lang[1]] = (isset($user['app_settings']['language']) && $user['app_settings']['language'] === $lang[1]) ? ['selected' => true] : [];
				}
				// preset available themes
				$theme = [];
				foreach(glob('../*.css') as $file){
					$name = pathinfo($file)['filename'];
					if (in_array($name, ['style'])) continue;
					$theme[ucfirst($name)] = (!isset($user['app_settings']['theme']) || $user['app_settings']['theme'] === $name) ? ['checked' => true, 'value' => $name] : ['value' => $name];
				}
				// append application settings
				$result['render']['content'][] = [
					[
						'type' => 'checkbox',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings')
						],
						'content' => [
							$this->_lang->GET('user.settings_force_desktop') => isset($user['app_settings']['forceDesktop']) ? ['checked' => true] : [],
							$this->_lang->GET('user.settings_homeoffice') => isset($user['app_settings']['homeoffice']) ? ['checked' => true] : [],
						]
					], [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings_language')
						],
						'content' => $languages,
						'hint' => $this->_lang->GET('user.settings_language_hint', [':lang' => CONFIG['application']['defaultlanguage']])
					], [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings_theme')
						],
						'content' => $theme
					],[
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings_autocomplete_forth'),
							'value' => isset($user['app_settings']['autocomplete_forth']) ? $user['app_settings']['autocomplete_forth'] : 'Alt',
							'onkeydown' => 'event.preventDefault(); this.value = event.key',
							'onkeyup' => 'event.preventDefault()'
						]
					],[
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings_autocomplete_back'),
							'value' => isset($user['app_settings']['autocomplete_back']) ? $user['app_settings']['autocomplete_back'] : 'AltGraph',
							'onkeydown' => 'event.preventDefault(); this.value = event.key',
							'onkeyup' => 'event.preventDefault()'
						]
					]
				];
				// append primary unit selection for orders
				if ($units) {
					$result['render']['content'][count($result['render']['content'])-1][] = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings_primary_unit')
						],
						'content' => $primary_unit
					];
				}
				// append primary case state selection for records
				$result['render']['content'][count($result['render']['content'])-1][] = [
					'type' => 'radio',
					'attributes' => [
						'name' => $this->_lang->GET('user.settings_primary_recordstate')
					],
					'hint' => $this->_lang->GET('user.settings_hint'),
					'content' => $primary_casestates
				];


				$this->response($result);
				break;
		}
	}

	/**
	 *
	 *   _ _ ___ ___ ___
	 *  | | |_ -| -_|  _|
	 *  |___|___|___|_|
	 *
	 * edit users
	 */
	public function user(){
		if (!PERMISSION::permissionFor('users')) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$permissions = [];
				$units = [];
				//set up user properties
				$user = [
					'name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.name')),
					'permissions' => '',
					'units' => '',
					'token' => '',
					'orderauth' => '',
					'image' => '',
					'app_settings' => [],
					'skills' => []
				];

				//check forbidden names
				$nametaken = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => '',
						':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.name'))
					]
				]);
				$nametaken = $nametaken ? $nametaken[0] : null;
				if(UTILITY::forbiddenName($user['name']) || $nametaken) $this->response(['response' => ['msg' => $this->_lang->GET('user.error_forbidden_name', [':name' => $user['name']]), 'type' => 'error']]);
		
				// checked permission levels
				if ($setpermissions = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.permissions'))){
					foreach(explode(' | ', $setpermissions) as $setpermission){
						$permissions[] = array_search($setpermission, $this->_lang->_USER['permissions']);
					}
				}
				$user['permissions'] = implode(',', $permissions);

				// checked organizational units
				if ($setunits = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.units'))){
					foreach(explode(' | ', $setunits) as $setunit){
						$units[] = array_search($setunit, $this->_lang->_USER['units']);
					}
				}
				$user['units'] = implode(',', $units);

				// gather timesheet setup
				$annualvacation = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_annual_vacation'));
				$user['app_settings']['annualvacation'] = $annualvacation ? : '';
				// check formats
				$settingentries = explode('\n', $user['app_settings']['annualvacation']);
				natsort($settingentries);
				foreach($settingentries as $line){
					// match ISO 8601 start date of contract settings, days of annual vacation or weekly hours
					preg_match('/(\d{4}.\d{2}.\d{2}).+?([\d,\.]+)/', $line, $lineentry);
					// append datetime value and contract value
					if ($line && (!isset($lineentry[1]) || !isset($lineentry[2]))) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('user.timesheet_format_error'),
							'type' => 'error'
						]]);
				}

				$weeklyhours = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_weekly_hours'));
				$user['app_settings']['weeklyhours'] = $weeklyhours ? : '';
				// check formats
				$settingentries = explode('\n', $user['app_settings']['weeklyhours']);
				natsort($settingentries);
				foreach($settingentries as $line){
					// match ISO 8601 start date of contract settings, days of annual vacation or weekly hours
					preg_match('/(\d{4}.\d{2}.\d{2}).+?([\d,\.]+)/', $line, $lineentry);
					// append datetime value and contract value
					if ($line && (!isset($lineentry[1]) || !isset($lineentry[2]))) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('user.timesheet_format_error'),
							'type' => 'error'
						]]);
				}
				$user['app_settings']['initialovertime'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_initial_overtime'));

				// gather user skills
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					if ($duty === '_LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($level = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('skills.' . $duty . '._DESCRIPTION') . '_' . $this->_lang->PROPERTY('skills.' . $duty . '.' . $skill))){
							if ($level != 0) $user['skills'][] = $duty . '.' . $skill . '.' . $level;
						}
					}
				}

				// generate order auth
				if(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization')) == $this->_lang->GET('user.order_authorization_generate')){
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
				if(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.token_renew'))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}

				// save and convert image or create default
				if (!(isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name'])) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user['name'])); 
					$_FILES[$this->_lang->PROPERTY('user.take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				$user['image'] = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.take_photo')], UTILITY::directory('users'), ['profilepic_' . $user['name']])[0];
				UTILITY::alterImage($user['image'], CONFIG['limits']['user_image'], UTILITY_IMAGE_REPLACE);
				$user['image'] = substr($user['image'], 3);

				// add user training if provided
				$training = [];
				if ($training[':name'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training'))){
					$training[':user_id'] = $user['id'];
					$date = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
					$training[':date'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_date')) ? : $date->format('Y-m-d');
					$training[':expires'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_expires')) ? : '2079-06-06';
					$training[':experience_points'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_experience_points')) ? : 0;
					$training[':file_path'] = '';
					$training[':evaluation'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_evaluation')) ? UTILITY::json_encode([
						'user' => $_SESSION['user']['name'],
						'date' => $this->_currentdate->format('Y-m-d H:i'),
						'content' => [$this->_lang->PROPERTY('user.add_training_evaluation') => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_evaluation'))]
					]): null;
						if (isset($_FILES[$this->_lang->PROPERTY('user.add_training_document')]) && $_FILES[$this->_lang->PROPERTY('user.add_training_document')]['tmp_name']) {
						$training[':file_path'] = substr(UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.add_training_document')], UTILITY::directory('users'), [$user['id'] . '_' . $user['name']], [$training[':name'] . '_' . $training[':date'] . '_' . $training[':expires']], false)[0], 1);
					}
					SQLQUERY::EXECUTE($this->_pdo, 'user_training_post', [
						'values' => $training
					]);
				}

				// insert user into database
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_post', [
					'values' => [
						':name' => $user['name'],
						':permissions' => $user['permissions'],
						':units' => $user['units'],
						':token' => $user['token'],
						':orderauth' => $user['orderauth'],
						':image' => $user['image'],
						':app_settings' => UTILITY::json_encode($user['app_settings']),
						':skills' => implode(',', $user['skills'])
					]
				])) {
					// create welcome message
					$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
					$appname = 'Caro App';
					$roles = [
						'supervisor' => [],
						'qmo' => [],
						'prrc' => [],
						'admin' => [],						
					];
					// construct permission- and unit-contact persons
					foreach ($users as $registered){
						if ($registered['id'] < 2){
							$appname = $registered['name'];
							continue;
						}
						$registered['permissions'] = explode(',', $registered['permissions']);
						$registered['units'] = explode(',', $registered['units']);
						foreach($roles as $key => &$values){
							if (in_array($key, $registered['permissions'])) {
								if ($key !== 'supervisor' || ($key === 'supervisor' && array_intersect($units, $registered['units'])))
									$values[] = $registered['name'];
							}
						}
					}
					foreach($roles as $key => &$values){
						$values = array_unique($values);
						$values = array_map(fn($v) => '<a href="javascript:void(0);" onclick="_client.message.newMessage(\''. $this->_lang->GET('order.message_orderer', [':orderer' => $v]) .'\', \'' . $v . '\', \'\', {}, [])">' . $v . '</a>', $values);
					}

					$message = [
						':name' => $user['name'],
						':appname' => $appname,
						':supervisor' => implode(', ', $roles['supervisor']),
						':qmo' => implode(', ', $roles['qmo']),
						':prrc' => implode(', ', $roles['prrc']),
						':register' => '<a href="javascript:void(0);" onclick="api.message(\'get\', \'register\')">' . $this->_lang->GET('menu.communication.register', [], true) . '</a>',
						':landingpage' => '<a href="javascript:void(0);" onclick="api.application(\'get\', \'start\')">' . $this->_lang->GET('menu.application.start', [], true) . '</a>',
						':profile' => '<a href="javascript:void(0);" onclick="api.user(\'get\', \'profile\')">' . $this->_lang->GET('menu.application.user_profile', [], true) . '</a>',
						':admin' => implode(', ', $roles['admin'])
					];
					$this->alertUserGroup(['user' => [$user['name']]], preg_replace(['/\r/'], [''], $this->_lang->GET('user.welcome_message', $message, true)));
					
					$this->response([
					'response' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => $this->_lang->GET('user.user_saved', [':name' => $user['name']]),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('user.user_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'PUT':
				$permissions = [];
				$units = [];
		
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => intval($this->_requestedID),
						':name' => ''
					]
				]);
				$user = $user ? $user[0] : null;
				// prepare user-array to update, return error if not found
				if (!$user) $this->response(null, 406);
				
				// check forbidden names
				$updateName = !($user['name'] == UTILITY::propertySet($this->_payload, $this->_lang->GET('user.name')));
				$user['name'] = UTILITY::propertySet($this->_payload, $this->_lang->GET('user.name'));
				$nametaken = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => '',
						':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.name'))
					]
				]);
				$nametaken = $nametaken ? $nametaken[0] : null;
				if(UTILITY::forbiddenName($user['name']) || ($nametaken && $nametaken['id'] !== $user['id'])) $this->response(['response' => ['msg' => $this->_lang->GET('user.error_forbidden_name', [':name' => $user['name']]), 'type' => 'error']]);
				
				// chain checked permission levels
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('permissions.' . $level))) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);

				// chain checked organizational units
				foreach($this->_lang->_USER['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('units.' . $unit))) {
						$units[] = $unit;
					}
				}
				$user['units'] = implode(',', $units);

				// update timesheet settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];
				$annualvacation = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_annual_vacation'));
				$user['app_settings']['annualvacation'] = $annualvacation ? : '';
				// check formats
				$settingentries = explode('\n', $user['app_settings']['annualvacation']);
				natsort($settingentries);
				foreach($settingentries as $line){
					// match ISO 8601 start date of contract settings, days of annual vacation or weekly hours
					preg_match('/(\d{4}.\d{2}.\d{2}).+?([\d,\.]+)/', $line, $lineentry);
					// append datetime value and contract value
					if ($line && (!isset($lineentry[1]) || !isset($lineentry[2]))) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('user.timesheet_format_error'),
							'type' => 'error'
						]]);
				}

				$weeklyhours = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_weekly_hours'));
				$user['app_settings']['weeklyhours'] = $weeklyhours ? : '';
				$user['app_settings']['initialovertime'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings_initial_overtime'));
				// check formats
				$settingentries = explode('\n', $user['app_settings']['weeklyhours']);
				natsort($settingentries);
				foreach($settingentries as $line){
					// match ISO 8601 start date of contract settings, days of annual vacation or weekly hours
					preg_match('/(\d{4}.\d{2}.\d{2}).+?([\d,\.]+)/', $line, $lineentry);
					// append datetime value and contract value
					if ($line && (!isset($lineentry[1]) || !isset($lineentry[2]))) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('user.timesheet_format_error'),
							'type' => 'error'
						]]);
				}

				// update skills
				$user['skills'] = [];
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					if ($duty === '_LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($level = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('skills.' . $duty . '._DESCRIPTION') . '_' . $this->_lang->PROPERTY('skills.' . $duty . '.' . $skill))){
							if ($level != 0) $user['skills'][] = $duty . '.' . $skill . '.' . $level;
						}
					}
				}

				// generate order auth
				if(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization')) == $this->_lang->GET('user.order_authorization_revoke')){
					$user['orderauth'] = '';
				}
				if(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization')) == $this->_lang->GET('user.order_authorization_generate')){
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
				if(UTILITY::propertySet($this->_payload, str_replace(' ', '_', $this->_lang->GET('user.token_renew')))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}

				// save and convert image or create default
				if ((!(isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name']) && $updateName) || UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.reset_photo'))) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user['name'])); 
					$_FILES[$this->_lang->PROPERTY('user.take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				if (isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name']) {
					if ($user['image'] && $user['id'] > 1) UTILITY::delete('../' . $user['image']);
					$user['image'] = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.take_photo')], UTILITY::directory('users'), ['profilepic_' . $user['name']])[0];
					UTILITY::alterImage($user['image'], CONFIG['limits']['user_image'], UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}

				// add user training
				$training = [];
				if ($training[':name'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training'))){
					$training[':user_id'] = $user['id'];
					$date = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
					$training[':date'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_date')) ? : $date->format('Y-m-d');
					$training[':expires'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_expires')) ? : '2079-06-06';
					$training[':experience_points'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_experience_points')) ? : 0;
					$training[':file_path'] = '';
					$training[':evaluation'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_evaluation')) ? UTILITY::json_encode([
						'user' => $_SESSION['user']['name'],
						'date' => $this->_currentdate->format('Y-m-d H:i'),
						'content' => [$this->_lang->PROPERTY('user.add_training_evaluation') => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_evaluation'))]
					]): null;
						if (isset($_FILES[$this->_lang->PROPERTY('user.add_training_document')]) && $_FILES[$this->_lang->PROPERTY('user.add_training_document')]['tmp_name']) {
						$training[':file_path'] = substr(UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.add_training_document')], UTILITY::directory('users'), [$user['id'] . '_' . $user['name']], [$training[':name'] . '_' . $training[':date'] . '_' . $training[':expires']], false)[0], 1);
					}
					SQLQUERY::EXECUTE($this->_pdo, 'user_training_post', [
						'values' => $training
					]);
				}

				// delete checked user trainings
				if ($delete_training = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.delete_training'))){
					$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
						'replacements' => [
							':ids' => $user['id'] ? : 0
						]
					]);
					foreach ($trainings as $row){
						if (in_array($row['id'], $delete_training)){
							if ($row['file_path']) UTILITY::delete(['.' . $row['file_path']]);
							SQLQUERY::EXECUTE($this->_pdo, 'user_training_delete', [
								'values' => [
									':id' => $row['id']
								]
							]);
						}
					}
				}

				// update user
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_put', [
					'values' => [
						':id' => $user['id'],
						':name' => $user['name'],
						':permissions' => $user['permissions'],
						':units' => $user['units'],
						':token' => $user['token'],
						':orderauth' => $user['orderauth'],
						':image' => $user['image'],
						':app_settings' => UTILITY::json_encode($user['app_settings']),
						':skills' => implode(',', $user['skills'])
					]
				]) !== false) {
					$this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => $this->_lang->GET('user.user_saved', [':name' => $user['name']]),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'id' => $user['id'],
						'msg' => $this->_lang->GET('user.user_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'GET':
				$datalist = [];
				$options = ['...' . $this->_lang->GET('user.existing_user_new') => (!$this->_requestedID) ? ['selected' => true] : []];
				$result = [];
				
				// prepare existing users lists
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				foreach($user as $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = ($row['name'] === $this->_requestedID) ? ['selected' => true] : [];
					if ($row['name'] === $this->_requestedID) $this->_requestedID = $row['id'];
				}
				ksort($options);
		
				// select single user based on id or name
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => intval($this->_requestedID) ? : '',
						':name' => ''
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
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$user['id'] && $this->_requestedID !== '...' . $this->_lang->GET('user.existing_user_new')) $result['response'] = ['msg' => $this->_lang->GET('user.error_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// gather available permissions
				$permissions = [];
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $user['permissions'])) ? ['checked' => true] : [];
				}
				// gather available units
				$units = [];
				foreach($this->_lang->_USER['units'] as $unit => $description){
					$units[$description] = in_array($unit, explode(',', $user['units'])) ? ['checked' => true] : [];
				}

				// gather application settings, especially for timesheet settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];

				// gather ans construct skill matrix
				$user['skills'] = explode(',', $user['skills'] ?  : '');
				// also see audit.php
				$skillmatrix = [
					[
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.add_training')
							],
						], [
							'type' => 'date',
							'attributes' => [
								'name' => $this->_lang->GET('user.add_training_date')
							],
						], [
							'type' => 'date',
							'attributes' => [
								'name' => $this->_lang->GET('user.add_training_expires')
							],
						], [
							'type' => 'number',
							'attributes' => [
								'name' => $this->_lang->GET('user.add_training_experience_points')
							],
						], [
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET("user.add_training_evaluation")
							],
							'content' => [
								$this->_lang->GET('user.add_training_evaluation_unreasonable') => []
							]
						], [
							'type' => 'file',
							'attributes' => [
								'name' => $this->_lang->GET('user.add_training_document')
							],
							'hint' => $this->_lang->GET('user.add_training_hint')
						]
					]
				];

				// gather user trainings
				$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
					'replacements' => [
						':ids' => $user['id'] ? : 0
					]
				]);
				foreach ($trainings as $row){
					$attributes = ['name' => $this->_lang->GET('user.display_training') . ' ' . $row['name'] . ' ' . $row['date']];
					if ($row['expires']){
						$expire = new DateTime($row['expires'], new DateTimeZone(CONFIG['application']['timezone']));
						if ($expire < $this->_currentdate) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training_renewal'] . ' days');
							if ($expire < $this->_currentdate) $attributes['class'] = 'orange';
						}
					}
					$skillmatrix[0][] = [
						'type' => 'textsection',
						'content' => $this->_lang->GET('user.add_training_expires') . ' ' . $row['expires'],
						'attributes' => $attributes
					];
					if ($row['file_path']) $skillmatrix[0][] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => ['href' => './api/api.php/file/stream/' . $row['file_path']]
						]
					];
					$skillmatrix[0][] = [
						'type' => 'checkbox',
						'content' => [
							$this->_lang->GET('user.delete_training') . '[]' => ['value' => $row['id']]
						]
					];
				}

				// create skill matrix
				$skilldatalistwithlabel = [];
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					$skillselection = [];
					if ($duty === '_LEVEL') {
						foreach($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
							$skilldatalistwithlabel[] = $leveldescription;
						}
						continue;
					}
					foreach ($skills as $skill => $skilldescription){
						if ($skill === '_DESCRIPTION') continue;
						$userlevel = 0;
						foreach($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
							if (in_array($duty . '.' . $skill . '.' . $level, $user['skills'])) $userlevel = $level;
						}
						$skillselection[] = [
							'type' => 'range',
							'attributes' => [
								'name' => $skills['_DESCRIPTION'] . ' ' . $skilldescription,
								'min' => 0,
								'max' => count($this->_lang->_USER['skills']['_LEVEL']) - 1,
								'value' => strval($userlevel),
							],
							'datalist' => array_search($skill, array_keys($skills)) < 2 ? $skilldatalistwithlabel : null
						];
					}
					$skillmatrix[] = [
						...$skillselection
					];
				}

				$result['render'] = ['content' => [
					[
						[
							'type' => 'select',
							'attributes' => [
								'name' => $this->_lang->GET('user.existing_user_select'),
								'onchange' => "api.user('get', 'user', this.value)"
							],
							'content' => $options
						],
						[
							'type' => 'search',
							'attributes' => [
								'name' => $this->_lang->GET('user.existing_user'),
								'onkeypress' => "if (event.key === 'Enter') {api.user('get', 'user', this.value); return false;}"
							],
							'datalist' => array_values(array_unique($datalist))
						]
					],[
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.name'),
								'required' => true,
								'value' => $user['name'] ? : ''
							]
						],
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('user.permissions')
							],
							'content' => $permissions,
							'hint' => $this->_lang->GET('user.permissions_hint')
						],
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('user.units')
							],
							'content' => $units,
							'hint' => $this->_lang->GET('user.units_hint')
						]
					], [
						[
							'type' => 'photo',
							'attributes' => [
								'name' => $this->_lang->GET('user.take_photo')
							],
							'hint' => $this->_lang->GET('user.take_photo_hint')
						],
					], [
						[
							'type' => 'radio',
							'attributes' => [
								'name' => $this->_lang->GET('user.order_authorization')
							],
							'content' => [
								$this->_lang->GET('user.order_authorization_generate') => [],
								$this->_lang->GET('user.order_authorization_revoke') => []
							]
						]
					], [
						[
							[
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('user.settings_initial_overtime'),
									'value' => isset($user['app_settings']['initialovertime']) ? $user['app_settings']['initialovertime'] : 0
								],
								'hint' => $this->_lang->GET('user.settings_initial_overtime_hint')
							],
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('user.settings_weekly_hours'),
									'value' => isset($user['app_settings']['weeklyhours']) ? $user['app_settings']['weeklyhours'] : ''
								],
								'hint' => $this->_lang->GET('user.settings_weekly_hours_hint')
							]
						], [
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('user.settings_annual_vacation'),
									'value' => isset($user['app_settings']['annualvacation']) ? $user['app_settings']['annualvacation'] : ''
								],
								'hint' => $this->_lang->GET('user.settings_annual_vacation_hint')
							]
						]
					],
					$skillmatrix,
					[
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('user.token')
							],
							'content' => [
								$this->_lang->GET('user.token_renew') => []
							]
						],
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('user.delete_button'),
								'type' => 'button', // apparently defaults to submit otherwise
								'onclick' => $user['id'] ? "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('user.delete_confirm_header', [':name' => $user['name']]) ."', options:{".
									"'".$this->_lang->GET('user.delete_confirm_cancel')."': false,".
									"'".$this->_lang->GET('user.delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.user('delete', 'user', ". $user['id'] . ")})" : '',
								'disabled' => $user['id'] < 2
							]
						]
					]],
					'form' => [
						'data-usecase' => 'user',
						'action' => $user['id'] ? "javascript:api.user('put', 'user', '" . $user['id'] . "')" : "javascript:api.user('post', 'user')"
					]];

					// append image options
					if ($user['image']) {
								$result['render']['content'][2] = [
							[
								[
									'type' => 'image',
									'description' => $this->_lang->GET('user.export_user_image'),
									'attributes' => [
										'name' => $user['name'] . '_pic',
										'url' => $user['image']
									]
								]
							],
							$result['render']['content'][2]
						];
						$result['render']['content'][2][1][] = [
							'type' => 'checkbox',
							'content' => [
								$this->_lang->GET('user.reset_photo') => []
							]
						];
					}

					// append order auth options
					if ($user['orderauth']) $result['render']['content'][3]=[
						[
							[
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('user.order_authorization_current'),
									'value' => $user['orderauth'],
									'readonly' => true
								]
							]
						],
						$result['render']['content'][3]
					];

					// append login token options
					if ($user['token']) $result['render']['content'][6]=[
						[
							[
								'type' => 'image',
								'description' => $this->_lang->GET('user.export_qr_token'),
								'attributes' => [
									'name' => $user['name'] . '_token',
									'url' => 'data:image/png;base64, ' . base64_encode($this->token($user['token'], $user['name']))
								],
								'dimensions' => [
									'width' => 1024,
									'height' => ceil(1024 / 85.6 * 53.9) // see $this->token()
								]
							]
						],
						$result['render']['content'][6]
					];

				$this->response($result);
				break;

			case 'DELETE':
				// prefetch to return proper name after deletion
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => intval($this->_requestedID),
						':name' => ''
					]
				]);
				$user = $user[0];
				if ($user['id'] < 2) $this->response([], 401);

				// delete user image
				if ($user['image'] && $user['id'] > 1) UTILITY::delete('../' . $user['image']);

				// delete training attachments (certificates)
				$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
					'replacements' => [
						':ids' => $user['id'] ? : 0
					]
				]);
				foreach ($trainings as $row){
					if ($row['file_path']) UTILITY::delete('.' . $row['file_path']);
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'user_delete', [
					'values' => [
						':id' => $user['id']
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.user_deleted', [':name' => $user['name']]),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.user_not_deleted', [':name' => $user['name']]),
						'id' => $user['id'],
						'type' => 'error'
					]]);
				break;
		}
	}

	/**
	 *   _       _           
	 *  | |_ ___| |_ ___ ___ 
	 *  |  _| . | '_| -_|   |
	 *  |_| |___|_,_|___|_|_|
	 *
	 * returns an image in credit card format containing a token qr and the user name
	 */
	private function token($CODE, $STRING){
		if (!PERMISSION::permissionFor('users')) return null;

		require_once('../libraries/TCPDF/tcpdf_barcodes_2d.php');
		require_once('../libraries/TCPDF/include/barcodes/qrcode.php');
		$qrcode = new TCPDF2DBarcode($CODE, 'QRCODE,' . CONFIG['limits']['qr_errorlevel']);
		$pngcode = imagecreatefromstring($qrcode->getBarcodePngData());

		$card = [85.6, 53.9];
		$dimensions = [1024, ceil(1024 / $card[0] * $card[1])];
		$margin = [
			'qr' => [
				'x' => 50,
				'y' => 100,
			]
		];
		$image = imagecreatetruecolor($dimensions[0], $dimensions[1]);
		$background_color = imagecolorallocate($image, 255, 255, 255);
		imagefill($image, 0, 0, $background_color);
		imagecolortransparent($image, $background_color);
		imagecopyresampled($image, $pngcode, $margin['qr']['x'], $margin['qr']['y'], 0, 0, $dimensions[1] - $margin['qr']['x'] * 4, $dimensions[1] - $margin['qr']['y'] * 2, $qrcode->getBarcodeArray()['num_cols'] * 3, $qrcode->getBarcodeArray()['num_rows'] * 3);

		$text_color = imagecolorallocate($image, 46, 52, 64); // nord dark
		$font_size = 36;
		$l = 0;
		foreach(preg_split('/\s+/m', $STRING) as $line){
			imagefttext($image, $font_size, 0, $dimensions[1] - $margin['qr']['x'], $margin['qr']['y'] + $font_size + ($font_size * 1.5 * $l++), $text_color, '../media/UbuntuMono-R.ttf', $line);
		}
		ob_start();
		imagepng($image);
		$image = ob_get_contents();
		ob_end_clean();
		return $image;
	}
}
?>