<?php
/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

// add, edit and delete users
require_once('./_calendarutility.php');

class USER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_prefilledTrainingUser = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);
		if (array_intersect(['patient'], $_SESSION['user']['permissions']) && 
			!in_array(REQUEST[1], ['profile'])
		) $this->response([], 401);

		$this->_requestedID = REQUEST[2] ?? null;
		$this->_prefilledTrainingUser = REQUEST[3] ?? null;
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
		// avoid umlauts messing up imagettftext
		$name = UTILITY::utf82ascii($name);
		// explode and take first and last initial
		$names = explode(' ', $name);
		$initials = strtoupper(substr($names[0], 0, 1));
		if (count($names) > 1) $initials .= strtoupper(substr($names[count($names) - 1], 0, 1));

		$image = imagecreatetruecolor(256, 256);
		$font_size = round(256 / 2);
		$y = round(256 / 2 + $font_size / 2.4);
		$x= round(256 / 2 - $font_size *.33 * strlen($initials));
		$background_color = imagecolorallocate($image, 163, 190, 140); // nord green
		imagefill($image, 0, 0, $background_color);
		$text_color = imagecolorallocate($image, 46, 52, 64); // nord dark
		imagettftext($image, $font_size, 0, $x, $y, $text_color, '../media/UbuntuMono-R.ttf', $initials);
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
			case 'PATCH':
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $_SESSION['user']['id'],
						':name' => $_SESSION['user']['name']
					]
				]);
				$user = $user ? $user[0] : null;
				// prepare user-array to update, return error if not found
				if (!$user) $this->response(null, 406);

				//set up user properties
				$user = [
					':id' => $user['id'],
					':name' => $user['name'],
					':permissions' => $user['permissions'],
					':units' => $user['units'],
					':token' => $user['token'],
					':orderauth' => $user['orderauth'],
					':image' => $user['image'],
					':app_settings' => isset($user['app_settings']) ? json_decode($user['app_settings'], true) : [],
					':skills' => $user['skills'],
				];

				// convert image
				// save and convert image
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.reset_photo'))) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user[':name'])); 
					$_FILES[$this->_lang->PROPERTY('user.take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				if (isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name']) {
					if ($user[':image'] && $user[':id'] > 1) UTILITY::delete('../' . $user['image']);

					$user[':image'] = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.take_photo')], UTILITY::directory('users'), ['profilepic_' . $user[':name']])[0];
					UTILITY::alterImage($user[':image'], CONFIG['limits']['user_image'], UTILITY_IMAGE_REPLACE);
					$user[':image'] = substr($user[':image'], 3);
				}
				// process settings
				$user[':app_settings']['forceDesktop'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.force_desktop'));
				$user[':app_settings']['homeoffice'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.homeoffice'));
				$user[':app_settings']['language'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.language')) ? : CONFIG['application']['defaultlanguage'];
				$user[':app_settings']['theme'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.theme'));
				$user[':app_settings']['masonry'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.masonry'));
				$user[':app_settings']['autodeleteMessages'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.autodelete_messages'));
				$user[':app_settings']['autocomplete_forth'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.autocomplete_forth'));
				$user[':app_settings']['autocomplete_back'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.autocomplete_back'));
				$user[':app_settings']['autocomplete_swipe'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.autocomplete_swipe'));
				$user[':app_settings']['dateformat'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.dateformat'));
				$user[':app_settings']['location'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.location'));
				$user[':app_settings']['timezone'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.timezone'));

				// unset defaults
				if ($user[':app_settings']['language'] === CONFIG['application']['defaultlanguage']) $user[':app_settings']['language'] = null;
				if ($user[':app_settings']['dateformat'] === array_key_first(CONFIG['calendar']['dateformats'])) $user[':app_settings']['dateformat'] = null;
				if ($user[':app_settings']['location'] === array_key_first(CONFIG['locations'])) $user[':app_settings']['location'] = null;
				if ($user[':app_settings']['timezone'] === array_key_first(CONFIG['calendar']['timezones'])) $user[':app_settings']['timezone'] = null;

				if ($primaryUnit = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.primary_unit'))){
					$user[':app_settings']['primaryUnit'] = array_search($primaryUnit, $this->_lang->_USER['units']);
				}
				if ($primaryRecordState = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.primary_recordstate'))){
					if ($primaryRecordState === $this->_lang->GET('record.casestate_filter_all'))
						unset($user[':app_settings']['primaryRecordState']);
					else
						$user[':app_settings']['primaryRecordState'] = array_search($primaryRecordState, $this->_lang->_USER['casestate']['casedocumentation']);
				}
				if ($orderLayout = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.order_layout'))){
					if ($orderLayout === $this->_lang->GET('user.settings.order_layout_full'))
						unset($user[':app_settings']['orderLayout']);
					else
						switch($orderLayout){
							// in case other options may become implemented also see utility.js _client.order.approved()
							case $this->_lang->GET('user.settings.order_layout_table'):
								$user[':app_settings']['orderLayout'] = 'table';
								break;
							case $this->_lang->GET('user.settings.order_layout_tile'):
								$user[':app_settings']['orderLayout'] = 'tile';
								break;
						}
				}
				// sanitize app settings for empty values
				foreach ($user[':app_settings'] as $key => $value){
					if (!$value || $value === '0') unset($user[':app_settings'][$key]);
				}
				$settings = $user[':app_settings'];
				$user[':app_settings'] = $user[':app_settings'] ? UTILITY::json_encode($user[':app_settings']) : null;

				// update user
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_post', [
					'values' => $user
				]) !== false) {
					$this->response([
						'response' => [
							'id' => $user[':id'],
							'msg' => $this->_lang->GET('user.user_saved', [':name' => $user[':name']]),
							'type' => 'success'
						],
						'data' => $settings
					]);
				}
				else $this->response([
					'response' => [
						'id' => $user[':id'],
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
				$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
				$timesheet_stats = $calendar->timesheetSummary([$user]);//, '2024-05-01'));
				$usertimesheet = array_search($user['id'], array_column($timesheet_stats, '_id'));
				if ($usertimesheet !== false) $timesheet_stats = $timesheet_stats[$usertimesheet];

				// resolve application settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];

				// resolve permissions
				$permissions = [];
				foreach (explode(',', $user['permissions']) as $level){
					$permissions[] = $this->_lang->GET('permissions.' . $level);
				}

				// resolve units
				$units = $primary_unit = [];
				foreach (explode(',', $user['units']) as $unit){
					if (!$unit) continue;
					$primary_unit[$this->_lang->GET('units.' . $unit)] = ['name' => $this->_lang->PROPERTY('user.settings.primary_unit')];
					$units[] = $this->_lang->GET('units.' . $unit);
				}
				if (isset($user['app_settings']['primaryUnit'])) $primary_unit[$this->_lang->GET('units.' . $user['app_settings']['primaryUnit'])]['checked'] = true;

				// resolve primary case states for default view within records
				$primary_casestates = [$this->_lang->GET('record.casestate_filter_all') => ['name' => $this->_lang->PROPERTY('user.settings.primary_recordstate')]];
				foreach ($this->_lang->_USER['casestate']['casedocumentation'] as $translation){
					$primary_casestates[$translation] = ['name' => $this->_lang->PROPERTY('user.settings.primary_recordstate')];
				}
				if (isset($user['app_settings']['primaryRecordState'])) $primary_casestates[$this->_lang->GET('casestate.casedocumentation.' . $user['app_settings']['primaryRecordState'])]['checked'] = true;
				else $primary_casestates[$this->_lang->GET('record.casestate_filter_all')]['checked'] = true;

				// gather current skills
				$user['skills'] = explode(',', $user['skills'] ? : '');
				$skillmatrix = '';
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					if ($duty === '_LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($skill === '_DESCRIPTION') continue;
						foreach ($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
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
										(isset($user['app_settings']['weeklyhours']) && $_SESSION['user']['app_settings']['weeklyhours'] ? " \n" . $this->_lang->GET('user.settings.weekly_hours') . ': ' . $user['app_settings']['weeklyhours'] : '') .
										(isset($timesheet_stats['_overtime']) ? " \n" . $this->_lang->GET('calendar.timesheet.export.sheet_overtime', [':number' => round($timesheet_stats['_overtime'], 2)]) : '') .
										(isset($user['app_settings']['annualvacation']) && $_SESSION['user']['app_settings']['annualvacation'] ? " \n \n" . $this->_lang->GET('user.settings.annual_vacation') . ': ' . $user['app_settings']['annualvacation'] : '') .
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
					$attributes = ['data-type' => 'skill', 'name' => $this->_lang->GET('user.training.display') . ' ' . $row['name'] . ' ' . $this->convertFromServerTime($row['date'])];
					if ($row['expires']){
						$expire = new \DateTime($row['expires']);
						if ($expire < $this->_date['servertime']) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training']['renewal'] . ' days');
							if ($expire < $this->_date['servertime']) $attributes['class'] = 'orange';
						}
					}
					if ($row['planned']){
						$row['planned'] = json_decode($row['planned'], true);
						$planned = $this->_lang->GET('audit.userskills.training_scheduled', [
							':user' => $row['planned']['user'],
							':date' => $this->convertFromServerTime($row['planned']['date'], true),
							':scheduled' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['planned']['content']), $row['planned']['content']))
						]);
					} else $planned = '';

					$usertrainings[] = [
						'type' => 'textsection',
						'content' => ($row['expires'] ? $this->_lang->GET('user.training.add_expires') . ' ' . $this->convertFromServerTime($row['expires']) : '')
							. ($planned ? ($row['expires'] ? " \n" : '') . $planned : ''),
						'attributes' => $attributes
					];
					if ($row['file_path']) $usertrainings[] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => UTILITY::link(['href' => './api/api.php/file/stream/' . $row['file_path'], 'download' => pathinfo($row['file_path'])['basename']])
						]
					];
				}
				if ($usertrainings && !array_intersect(['patient'], $_SESSION['user']['permissions'])) {
					$user_data[] = [
						[
							'type' => 'collapsible',
							'attributes' => [
								'class' => "em16"
							],
							'content' => $usertrainings
						]
					];
				}

				// append user sessions
				$usersessions = SQLQUERY::EXECUTE($this->_pdo, 'application_get_user_sessions', [
					'values' => [
						':user_id' => $user['id'] ? : 0
					]
				]);
				$sessions = [];
				foreach ($usersessions as $session){
					$sessions[] = $this->convertFromServerTime($session['date']);
				}
				if ($sessions && !array_intersect(['patient'], $_SESSION['user']['permissions'])) $user_data[] = [
					[
						'type' => 'collapsible',
						'attributes' => [
							'class' => "em16"
						],
						'content' => [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('user.sessions', [':days' => CONFIG['lifespan']['session']['records']])
								],
								'content' => implode("\n", $sessions)
							]
						]
					]
				];
				$response['render'] = [
					'content' => array_intersect(['patient'], $_SESSION['user']['permissions']) ? $user_data[0] : [$user_data],
					'form' => [
						'data-usecase' => 'user',
						'action' => "javascript:api.user('patch', 'profile')"
					]
				];

				if (!array_intersect(['patient'], $_SESSION['user']['permissions'])) {
					$response['render']['content'][] = [
							[
								'type' => 'photo',
								'attributes' => [
									'name' => $this->_lang->GET('user.take_photo')
								],
								'hint' => $this->_lang->GET('user.take_photo_hint')
							],
						];
						

					// append image options
					if ($user['image']) {
						$response['render']['content'][1] = [
							[
								[
									'type' => 'image',
									'description' => $this->_lang->GET('user.export_user_image'),
									'attributes' => [
										'name' => pathinfo($user['image'])['basename'],
										'url' => './api/api.php/file/stream/' . $user['image']
									]
								]
							],
							$response['render']['content'][1]
						];
						$response['render']['content'][1][1][] = [
							'type' => 'checkbox',
							'content' => [
								$this->_lang->GET('user.reset_photo') => []
							]
						];
					}
				}

				// retrieve language options
				$languages = [];
				foreach (glob('language.*.json') as $file){
					$lang = explode('.', $file);
					$languages[$lang[1]] = ((isset($user['app_settings']['language']) && $user['app_settings']['language'] === $lang[1]) || (!isset($user['app_settings']['language']) && $lang[1] == CONFIG['application']['defaultlanguage'])) ? ['selected' => true] : [];
				}
				// preset available themes
				$theme = [];
				foreach (glob('../*.css') as $file){
					$name = pathinfo($file)['filename'];
					if (in_array($name, ['style'])) continue;
					$theme[ucfirst($name)] = (!isset($user['app_settings']['theme']) || $user['app_settings']['theme'] === $name) ? ['checked' => true, 'value' => $name] : ['value' => $name];
				}
				// available timezones
				$timezones = [];
				foreach (CONFIG['calendar']['timezones'] as $tz => $name){
					$timezones[$name] = (isset($user['app_settings']['timezone']) && $user['app_settings']['timezone'] === $tz) ? ['selected' => true, 'value' => $tz] : ['value' => $tz]; 
				}
				// available date formats
				$dateformats = [];
				foreach (CONFIG['calendar']['dateformats'] as $df => $name){
					$dateformats[$name] = (isset($user['app_settings']['dateformat']) && $user['app_settings']['dateformat'] === $df) ? ['selected' => true, 'value' => $df] : ['value' => $df]; 
				}
				// available states / holiday options
				$locations = [];
				foreach (CONFIG['locations'] as $location => $void){
					$locations[$location] = (isset($user['app_settings']['location']) && $user['app_settings']['location'] === $location) ? ['selected' => true, 'value' => $location] : ['value' => $location]; 
				}
				// append application settings
				$applicationSettings = [];

				// append primary unit selection for orders
				if ($units && !array_intersect(['patient'], $_SESSION['user']['permissions'])) {
					$applicationSettings[] = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings.primary_unit')
						],
						'content' => $primary_unit
					];
				}

				if (!array_intersect(['patient'], $_SESSION['user']['permissions'])) {
					array_push($applicationSettings, ...[
						[
							// append primary case state selection for records
							'type' => 'radio',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.primary_recordstate')
							],
							'content' => $primary_casestates
						], [
							'type' => 'hr'
						], [
							// append application setting
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.header')
							],
							'content' => [
								$this->_lang->GET('user.settings.force_desktop') => isset($user['app_settings']['forceDesktop']) ? ['checked' => true] : [],
								$this->_lang->GET('user.settings.homeoffice') => isset($user['app_settings']['homeoffice']) ? ['checked' => true] : [],
								$this->_lang->GET('user.settings.masonry') => isset($user['app_settings']['masonry']) ? ['checked' => true] : [],
							]
						], [
							// append preferred order layout
							'type' => 'radio',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.order_layout')
							],
							'content' => [
								$this->_lang->GET('user.settings.order_layout_full') => !isset($user['app_settings']['orderLayout']) ? ['checked' => true] : [],
								$this->_lang->GET('user.settings.order_layout_table') => isset($user['app_settings']['orderLayout']) && $user['app_settings']['orderLayout'] === 'table' ? ['checked' => true] : [],
								$this->_lang->GET('user.settings.order_layout_tile') => isset($user['app_settings']['orderLayout']) && $user['app_settings']['orderLayout'] === 'tile' ? ['checked' => true] : [],
							]
						], [
							'type' => 'hr'
						], [

							'type' => 'range',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.autodelete_messages'),
								'min' => 0,
								'max' => 4 * 6,
								'step' => '1',
								'value' => isset($user['app_settings']['autodeleteMessages']) ? strval($user['app_settings']['autodeleteMessages']) : '0',
							],
							'datalist' => array_map(Fn($m) => $m * 4, range(0, 6))
						], [
							'type' => 'hr'
						], [
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.autocomplete_forth'),
								'value' => $user['app_settings']['autocomplete_forth'] ?? 'Alt',
								'onkeydown' => 'event.preventDefault(); this.value = event.key',
								'onkeyup' => 'event.preventDefault()'
							]
						], [
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.autocomplete_back'),
								'value' => $user['app_settings']['autocomplete_back'] ?? 'AltGraph',
								'onkeydown' => 'event.preventDefault(); this.value = event.key',
								'onkeyup' => 'event.preventDefault()'
							]
						], [
							'type' => 'checkbox',
							'content' => [
								$this->_lang->GET('user.settings.autocomplete_swipe') =>  isset($user['app_settings']['autocomplete_swipe']) ? ['checked' => true] : [],
							]
						], [
							'type' => 'hr'
						]
					]);
				}

				array_push($applicationSettings, ...[
					[
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings.theme')
						],
						'content' => $theme
					], [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings.language')
						],
						'content' => $languages,
						'hint' => $this->_lang->GET('user.settings.language_hint', [':lang' => CONFIG['application']['defaultlanguage']])
					]
				]);

				if (count($dateformats) > 1 || array_intersect(['admin'], $_SESSION['user']['permissions'])){
					$applicationSettings[] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings.dateformat')
						],
						'content' => $dateformats
					];
				}
				if (count($locations) > 1 || array_intersect(['admin'], $_SESSION['user']['permissions']) && !array_intersect(['patient'], $_SESSION['user']['permissions'])){
					$applicationSettings[] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings.location'),
							'onchange' => "new _client.Dialog({type:'alert', header:'" . $this->_lang->GET('user.settings.location_change_alert') . "'})"
						],
						'content' => $locations
					];
				}
				if (count($timezones) > 1 || array_intersect(['admin'], $_SESSION['user']['permissions'])){
					$applicationSettings[] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('user.settings.timezone')
						],
						'content' => $timezones,
						'hint' => $this->_lang->GET('user.settings.timezone_hint')
					];
				}

				$applicationSettings[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('user.settings.hint')
					]
				];

				$response['render']['content'][] = $applicationSettings;

				$this->response($response);
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
			case 'PUT':
				$permissions = $units = $user = [];
				if ($this->_requestedID) {
					$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
						'replacements' => [
							':id' => intval($this->_requestedID),
							':name' => ''
						]
					]);
					$user = $user ? $user[0] : null;
					// prepare user-array to update, return error if not found
					if (!$user) $this->response(null, 406);
				}

				$submittedName = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.name'));
				$nameUpdate = !isset($user['name']) || $user['name'] !== $submittedName;

				//set up user properties
				$user = [
					':id' => $user['id'] ?? null,
					':name' => $submittedName,
					':permissions' => null,
					':units' => null,
					':token' => $user['token'] ?? '',
					':orderauth' => $user['orderauth'] ?? '',
					':image' => $user['image'] ?? '',
					':app_settings' => isset($user['app_settings']) ? json_decode($user['app_settings'], true) : [],
					':skills' => [],
				];

				//check forbidden names
				$nametaken = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => '',
						':name' => $submittedName
					]
				]);
				$nametaken = $nametaken ? $nametaken[0] : null;
				if (UTILITY::forbiddenName($user[':name']) || ($nametaken && $nametaken['id'] !== $user[':id'])) $this->response(['response' => ['msg' => $this->_lang->GET('user.error_forbidden_name', [':name' => $user[':name']]), 'type' => 'error']]);
		
				// checked permission levels
				if ($setpermissions = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.permissions'))){
					foreach (explode(' | ', $setpermissions) as $setpermission){
						$permissions[] = array_search($setpermission, $this->_lang->_USER['permissions']);
					}
				}
				$user[':permissions'] = implode(',', $permissions);

				// checked organizational units
				if ($setunits = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.units'))){
					foreach (explode(' | ', $setunits) as $setunit){
						$units[] = array_search($setunit, $this->_lang->_USER['units']);
					}
				}
				$user[':units'] = implode(',', $units);

				// gather timesheet setup
				$annualvacation = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.annual_vacation'));
				$user[':app_settings']['annualvacation'] = $annualvacation ? : '';
				$weeklyhours = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.weekly_hours'));
				$user[':app_settings']['weeklyhours'] = $weeklyhours ? : '';
				// check formats according to _calendarutility.php
				foreach (['weeklyhours', 'annualvacation'] as $setting){
					if (isset($user[':app_settings'][$setting])){
						$settingentries = explode('\n', $user[':app_settings'][$setting]);
						natsort($settingentries);
						foreach ($settingentries as $line){
							// match ISO 8601 start date of contract settings, days of annual vacation or weekly hours
							preg_match('/(\d{4}.\d{2}.\d{2}).+?([\d,\.]+)/', $line, $lineentry);
							// append datetime value and contract value
							if ($line && (!isset($lineentry[1]) || !isset($lineentry[2]))) $this->response([
								'response' => [
									'msg' => $this->_lang->GET('user.timesheet_format_error'),
									'type' => 'error'
								]]);
						}
					}
				}

				// set custom idle timeout
				$idle_prolonging_factor = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.settings.idle'));
				if ($idle_prolonging_factor > 0){
					$user[':app_settings']['idle'] = ($idle_prolonging_factor + 1) * CONFIG['lifespan']['session']['idle'];
				}
				else unset ($user[':app_settings']['idle']);

				// sanitize app settings for empty values
				foreach ($user[':app_settings'] as $key => $value){
					if (!$value) unset($user[':app_settings'][$key]);
				}
				$user[':app_settings'] = $user[':app_settings'] ? UTILITY::json_encode($user[':app_settings']) : null;

				// gather user skills
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					if ($duty === '_LEVEL') continue;
					foreach ($skills as $skill => $skilldescription){
						if ($level = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('skills.' . $duty . '._DESCRIPTION') . ' ' . $this->_lang->PROPERTY('skills.' . $duty . '.' . $skill))){
							if ($level != 0) $user[':skills'][] = $duty . '.' . $skill . '.' . $level;
						}
					}
				}
				$user[':skills'] = $user[':skills'] ? implode(',', $user[':skills']) : null;

				// generate order auth
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization')) == $this->_lang->GET('user.order_authorization_revoke')){
					$user[':orderauth'] = '';
				}
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization')) == $this->_lang->GET('user.order_authorization_generate')){
					$orderauths = [];
					$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
					foreach ($users as $row){
						$orderauths[] = $row['orderauth'];
					}
					do {
						$user[':orderauth'] = random_int(10000, max(99999, count($users)*100)); 
					} while (in_array($user[':orderauth'], $orderauths));
				}

				// generate token
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.token_renew'))){
					$user[':token'] = hash('sha256', $user[':name'] . random_int(100000,999999) . time());
				}

				// save and convert image or create default
				if ((!(isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name']) && $nameUpdate) || UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.reset_photo'))) {
					$tempPhoto = tmpfile();
					fwrite($tempPhoto, $this->defaultPic($user[':name'])); 
					$_FILES[$this->_lang->PROPERTY('user.take_photo')] = [
						'name' => 'defaultpic.png',
						'type' => 'image/png',
						'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
					];
				}
				if (isset($_FILES[$this->_lang->PROPERTY('user.take_photo')]) && $_FILES[$this->_lang->PROPERTY('user.take_photo')]['tmp_name']) {
					if ($user[':image'] && $user[':id'] > 1) UTILITY::delete('../' . $user[':image']);
					$user[':image'] = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.take_photo')], UTILITY::directory('users'), ['profilepic_' . $user[':name']])[0];
					UTILITY::alterImage($user[':image'], CONFIG['limits']['user_image'], UTILITY_IMAGE_REPLACE);
					$user[':image'] = substr($user[':image'], 3);
				}

				// insert user into database
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_post', [
					'values' => $user
				]) || UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.reset_photo'))) {
					if (!$user[':id']){
						// create welcome message
						$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
						$appname = 'Caro App';
						$roles = [
							'supervisor' => [],
							'qmo' => [],
							'prrc' => [],
							'admin' => [],
							'hazardous_materials' => []			
						];
						// construct permission- and unit-contact persons
						foreach ($users as $registered){
							if ($registered['id'] < 2){
								$appname = $registered['name'];
								continue;
							}
							$registered['permissions'] = explode(',', $registered['permissions']);
							$registered['units'] = explode(',', $registered['units']);
							foreach ($roles as $key => &$values){
								if (in_array($key, $registered['permissions'])) {
									if ($key !== 'supervisor' || ($key === 'supervisor' && array_intersect($units, $registered['units'])))
										$values[] = $registered['name'];
								}
							}
						}
						foreach ($roles as $key => &$values){
							$values = array_unique($values);
							$values = array_map(fn($v) => '<a href="javascript:void(0);" onclick="_client.message.newMessage(\''. $this->_lang->GET('order.message_orderer', [':orderer' => $v]) .'\', \'' . $v . '\', \'\', {}, [])">' . $v . '</a>', $values);
						}

						$message = [
							':name' => $user[':name'],
							':appname' => $appname,
							':supervisor' => implode(', ', $roles['supervisor']),
							':qmo' => implode(', ', $roles['qmo']),
							':prrc' => implode(', ', $roles['prrc']),
							':hazardous_materials' => implode(', ', $roles['hazardous_materials']),
							':register' => '<a href="javascript:void(0);" onclick="api.message(\'get\', \'register\')">' . $this->_lang->GET('message.navigation.register', [], true) . '</a>',
							':landingpage' => '<a href="javascript:void(0);" onclick="api.application(\'get\', \'about\')">' . $this->_lang->GET('application.navigation.about', [], true) . '</a>',
							':profile' => '<a href="javascript:void(0);" onclick="api.user(\'get\', \'profile\')">' . $this->_lang->GET('application.navigation.user_profile', [], true) . '</a>',
							':admin' => implode(', ', $roles['admin'])
						];
						$this->alertUserGroup(['user' => [$user[':name']]], preg_replace(['/\r/'], [''], $this->_lang->GET('user.welcome_message', $message, true)));
					}
					$this->response([
					'response' => [
						'id' => $user[':id'] ? : $this->_pdo->lastInsertId(),
						'msg' => $this->_lang->GET('user.user_saved', [':name' => $user[':name']]),
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
			case 'GET':
				$datalist = [];
				$options = ['...' . $this->_lang->GET('user.existing_user_new') => (!$this->_requestedID) ? ['selected' => true] : []];
				$response = [];
				$max_idle_prolonging_factor = 12;

				// prepare existing users lists
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				foreach ($user as $row) {
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
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$user['id'] && $this->_requestedID !== '...' . $this->_lang->GET('user.existing_user_new')) $response['response'] = ['msg' => $this->_lang->GET('user.error_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// gather available permissions
				$permissions = [];
				foreach ($this->_lang->_USER['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $user['permissions'])) ? ['checked' => true] : [];
				}
				// gather available units
				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $description){
					if ($unit === 'common') continue;
					$units[$description] = in_array($unit, explode(',', $user['units'])) ? ['checked' => true] : [];
				}

				// gather application settings, especially for timesheet settings
				$user['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];

				// gather ans construct skill matrix
				$user['skills'] = explode(',', $user['skills'] ? : '');
				// also see audit.php
				$skillmatrix = [
					[
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('user.training.add_training'),
								'onclick' => "api.user('get', 'training', 'null', " . $user['id'] . ")"
							]
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
					$attributes = ['data-type' => 'skill', 'name' => $this->_lang->GET('user.training.display') . ' ' . $row['name'] . ' ' . $this->convertFromServerTime($row['date'])];
					if ($row['expires']){
						$expire = new \DateTime($row['expires']);
						if ($expire < $this->_date['servertime']) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training']['renewal'] . ' days');
							if ($expire < $this->_date['servertime']) $attributes['class'] = 'orange';
						}
					}
					if ($row['planned']){
						$row['planned'] = json_decode($row['planned'], true);
						$planned = $this->_lang->GET('audit.userskills.training_scheduled', [
							':user' => $row['planned']['user'],
							':date' => $this->convertFromServerTime($row['planned']['date'], true),
							':scheduled' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['planned']['content']), $row['planned']['content']))
						]);
					} else $planned = '';

					$skillmatrix[0][] = [
						'type' => 'textsection',
						'content' => ($row['expires'] ? $this->_lang->GET('user.training.add_expires') . ' ' . $this->convertFromServerTime($row['expires']) : '')
							. ($planned ? ($row['expires'] ? " \n" : '') . $planned : ''),
						'attributes' => $attributes
					];
					if ($row['file_path']) $skillmatrix[0][] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => UTILITY::link(['href' => './api/api.php/file/stream/' . $row['file_path'], 'download' => pathinfo($row['file_path'])['basename']])
						]
					];
					$skillmatrix[0][] = [
						'type' => 'deletebutton',
						'attributes' => [
							'value' => $this->_lang->GET('user.training.delete'),
							'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('user.training.delete_confirm_header', [':name' => $row['name']]) ."', options:{".
								"'" . $this->_lang->GET('user.training.delete_confirm_cancel') . "': false,".
								"'" . $this->_lang->GET('user.training.delete_confirm_ok') . "': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {this.disabled = true; api.user('delete', 'training', ". $row['id'] . ");}})"
						]
					];
					if ($planned){
						$skillmatrix[0][] = [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('user.training.edit'),
								'class' => 'inlinebutton',
								'onclick' => "this.disabled = true; api.user('get', 'training', " . $row['id'] . ");"
							]
						];
					}
				}

				// create skill matrix
				$skilldatalistwithlabel = [];
				foreach ($this->_lang->_USER['skills'] as $duty => $skills){
					$skillselection = [];
					if ($duty === '_LEVEL') {
						foreach ($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
							$skilldatalistwithlabel[] = $leveldescription;
						}
						continue;
					}
					foreach ($skills as $skill => $skilldescription){
						if ($skill === '_DESCRIPTION') continue;
						$userlevel = 0;
						foreach ($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
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

				$response['render'] = ['content' => [
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
								'onkeydown' => "if (event.key === 'Enter') {api.user('get', 'user', this.value); return false;}"
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
						],
						[
							'type' => 'range',
							'attributes' => [
								'name' => $this->_lang->GET('user.settings.idle'),
								// having a datalist the range input takes its available steps only
								'value' => strval(isset($user['app_settings']['idle']) ? ($user['app_settings']['idle'] / CONFIG['lifespan']['session']['idle'] - 1) : 0),
							],
							'hint' => $this->_lang->GET('user.settings.idle_hint', [':idle' => CONFIG['lifespan']['session']['idle']]),
							'datalist' => [...array_map(fn($v) => intval(CONFIG['lifespan']['session']['idle']) * $v, range(1, $max_idle_prolonging_factor, 1)), 4 * 3600]
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
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('user.settings.weekly_hours'),
									'value' => $user['app_settings']['weeklyhours'] ?? ''
								],
								'hint' => $this->_lang->GET('user.settings.weekly_hours_hint')
							]
						], [
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('user.settings.annual_vacation'),
									'value' =>$user['app_settings']['annualvacation'] ?? ''
								],
								'hint' => $this->_lang->GET('user.settings.annual_vacation_hint')
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
								$response['render']['content'][2] = [
							[
								[
									'type' => 'image',
									'description' => $this->_lang->GET('user.export_user_image'),
									'attributes' => [
										'name' => pathinfo($user['image'])['basename'],
										'url' => './api/api.php/file/stream/' . $user['image']
									]
								]
							],
							$response['render']['content'][2]
						];
						$response['render']['content'][2][1][] = [
							'type' => 'checkbox',
							'content' => [
								$this->_lang->GET('user.reset_photo') => []
							]
						];
					}

					// append order auth options
					if ($user['orderauth']) $response['render']['content'][3]=[
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
						$response['render']['content'][3]
					];

					// append login token options
					if ($user['token']) $response['render']['content'][6]=[
						[
							[
								'type' => 'image',
								'description' => $this->_lang->GET('user.export_qr_token'),
								'attributes' => [
									'name' => $user['name'] . '_token.png',
									'url' => 'data:image/png;base64, ' . base64_encode($this->token($user['token'], $user['name']))
								],
								'dimensions' => [
									'width' => 1024,
									'height' => ceil(1024 / 85.6 * 53.9) // see $this->token()
								]
							]
						],
						$response['render']['content'][6]
					];

				$this->response($response);
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
						'type' => 'deleted'
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

		require_once('../vendor/TCPDF/tcpdf_barcodes_2d.php');
		require_once('../vendor/TCPDF/include/barcodes/qrcode.php');
		$qrcode = new \TCPDF2DBarcode($CODE, 'QRCODE,' . CONFIG['limits']['qr_errorlevel']);
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
		foreach (preg_split('/\s+/m', $STRING) as $line){
			imagefttext($image, $font_size, 0, $dimensions[1] - $margin['qr']['x'], $margin['qr']['y'] + $font_size + ($font_size * 1.5 * $l++), $text_color, '../media/UbuntuMono-R.ttf', $line);
		}
		ob_start();
		imagepng($image);
		$image = ob_get_contents();
		ob_end_clean();
		return $image;
	}

	/**
	 *   _           _     _         
	 *  | |_ ___ ___|_|___|_|___ ___ 
	 *  |  _|  _| .'| |   | |   | . |
	 *  |_| |_| |__,|_|_|_|_|_|_|_  |
	 *                          |___|
	 * handles user trainings
	 */
	public function training(){
		if (!PERMISSION::permissionFor('regulatory')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
			case 'PUT':
				$training = [];
				if ($this->_requestedID && $this->_requestedID !== 'null'){
					$training = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$training = $training ? $training[0] : [];
				}

				$training = [
					':id' => $training['id'] ?? null,
					':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.add_training')),
					':user_id' => $training['user_id'] ?? null,
					':date' => $this->convertToServerTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.add_date'))) ? : null,
					':expires' => $this->convertToServerTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.add_expires'))) ? : null,
					':experience_points' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.add_experience_points')) ? : 0,
					':file_path' => null,
					':evaluation' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.add_evaluation')) ? UTILITY::json_encode([
						'user' => $_SESSION['user']['name'],
						'date' => $this->_date['servertime']->format('Y-m-d H:i'),
						'content' => [$this->_lang->GET('user.training.add_evaluation', [], true) => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.add_evaluation'))]
					]): null,
					':planned' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.schedule_timespan')) ? UTILITY::json_encode([
						'user' => $_SESSION['user']['name'],
						'date' => $this->_date['servertime']->format('Y-m-d H:i'),
						'content' => [$this->_lang->GET('user.training.schedule_timespan', [], true) => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.schedule_timespan'))]
					]): null
				];
				// if date is set it can not be planned
				if ($training[':date']) {
					$training[':planned'] = null;
				}
				// must have name and either date or planned
				if (!$training[':name'] || !($training[':date'] || $training[':planned'])) $this->response([], 406);

				$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

				if (isset($training['user_name'])) $usernames = [$training['user_name']];
				else $usernames = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.training.name'));
				if (!($usernames && $usernames = preg_split('/([,;]\s{0,})/', $usernames))) $this->response([], 406);

				$notfound = [];
				foreach ($usernames as $username){
					if (!$username) continue;
					if ($user = array_search($username, array_column($users, 'name'))){
						$user = $users[$user];
						$training[':user_id'] = $user['id'];
						// upload files only if date is set
						if ($training[':date'] && isset($_FILES[$this->_lang->PROPERTY('user.training.add_document')]) && $_FILES[$this->_lang->PROPERTY('user.training.add_document')]['tmp_name']) {
							$training[':file_path'] = substr(UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.training.add_document')], UTILITY::directory('users'), [$user['id'] . '_' . $user['name']], [$training[':name'] . '_' . $training[':date'] . '_' . $training[':expires']], false)[0], 1);
						}
						SQLQUERY::EXECUTE($this->_pdo, 'user_training_post', [
							'values' => $training
						]);
					}
					else $notfound[] = $username;
				}
				if (count($notfound) !== count($usernames)) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.training.save_success') . (count($notfound) ? ' ' . $this->_lang->GET('user.training.not_found', [':names' => implode(', ', $notfound)]) :''),
						'type' => count($notfound) ? 'info' : 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.training.not_found', [':names' => implode(', ', $notfound)]),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$prefill = [
					'user' => '',
					'training' => '',
					'timespan' => '',
				];
				$datalist = ['user' => [], 'training' => []];
				// prepare existing users lists
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				foreach ($user as $row) {
					if (PERMISSION::filteredUser($row)) continue;
					$datalist['user'][] = $row['name'];
					if ($this->_prefilledTrainingUser && $row['id'] == $this->_prefilledTrainingUser) $prefill['user'] = $row['name'];
				}

				$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
					'replacements' => [
						':ids' => implode(',', array_column($user, 'id'))
					]
				]);
				foreach ($trainings as $training){
					$datalist['training'][] = $training['name'];
				}
				$datalist['training'] = array_unique($datalist['training']);
				sort($datalist['training']);


				// prefill scheduled training if id is submitted
				if ($this->_requestedID && $this->_requestedID !== 'null'){
					$training = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					if ($training = $training ? $training[0] : null){
						if ($user_id = array_search($training['user_id'], array_column($user, 'id'))){
							$prefill['user'] = $user[$user_id]['name'];
							$prefill['training'] = $training['name'];

							$training['planned'] = json_decode($training['planned'] ? : '', true);
							if ($training['planned']) $prefill['timespan'] = implode(', ', array_values($training['planned']['content']));
						}
					}
				}

				$response = ['render' => ['content' => [
					[
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.name'),
								'value' => $prefill['user']
							],
							'hint' => $this->_lang->GET('user.training.name_hint'),
							'datalist' => $datalist['user']
						], [
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.add_training'),
								'value' => $prefill['training']
							],
							'datalist' => $datalist['training']
						], [
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.schedule_timespan'),
								'value' => $prefill['timespan']
							],
							'hint' => $this->_lang->GET('user.training.schedule_timespan_hint'),
						], [
							'type' => 'hr'
						], [
							'type' => 'date',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.add_date')
							],
						], [
							'type' => 'date',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.add_expires')
							],
						], [
							'type' => 'number',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.add_experience_points')
							],
						], [
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET("user.training.add_evaluation")
							],
							'content' => [
								$this->_lang->GET('user.training.add_evaluation_unreasonable') => []
							]
						], [
							'type' => 'file',
							'attributes' => [
								'name' => $this->_lang->GET('user.training.add_document')
							],
							'hint' => $this->_lang->GET('user.training.add_hint')
						]
					]
				]]];
				if ($prefill['user']) {
					$response['render']['content'][0][0]['attributes']['readonly'] = true;
					unset($response['render']['content'][0][0]['hint']);
				}

				$this->response($response);
				break;
			case 'DELETE':
				$training = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);

				if ($training = $training ? $training[0] : null){
					if ($training['file_path']) UTILITY::delete(['.' . $training['file_path']]);
					SQLQUERY::EXECUTE($this->_pdo, 'user_training_delete', [
						'values' => [
							':id' => $training['id']
						]
					]);

					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.training.delete_confirmed'),
						'type' => 'deleted'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.training.delete_failed'),
						'type' => 'error'
					]]);

				break;
		}
	}
}
?>