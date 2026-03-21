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

include_once('./_markdown.php');
include_once('./_filehandler.php');

class UTILITY {

	/**
	 *       _             _             _
	 *   ___| |___ ___ ___|_|___ ___ _ _| |_ ___
	 *  |  _| | -_| .'|   | |   | . | | |  _|_ -|
	 *  |___|_|___|__,|_|_|_|_|_|  _|___|_| |___|
	 *                          |_|
	 * trim input data
	 */
	public static function cleanInputs($data){
		$clean_input = [];
		if(is_array($data)){
			foreach ($data as $k => $v){
				$clean_input[$k] = self::cleanInputs($v);
			}
		} else {
			if ($data) $clean_input = trim($data);
		}
		return $clean_input;
	}

	/**
	 *     _     _           
	 *   _| |___| |_ _ _ ___ 
	 *  | . | -_| . | | | . |
	 *  |___|___|___|___|_  |
	 *                  |___|
	 * displays error reports by var_dumping id debug mode is on per config
	 */
	public static function debug(...$vars){
		if (CONFIG['application']['debugging'])	var_dump(...$vars);
		else echo "there may have been an error, however debug mode has been turned off." . PHP_EOL;
	}

	/**
	 *   ___         _   _   _   _                           
	 *  |  _|___ ___| |_|_|_| |_| |___ ___ ___ ___ _____ ___ 
	 *  |  _| . |  _| . | | . | . | -_|   |   | .'|     | -_|
	 *  |_| |___|_| |___|_|___|___|___|_|_|_|_|__,|_|_|_|___|
	 *
	 * matches a string against CONFIG['forbidden']['names']
	 * optional override of a defined pattern if key matches config or append if not
	 * @param string $name
	 * @param array $pattern
	 * @return string|false matched pattern or not matched
	 */
	public static function forbiddenName($name, $extendedpattern = []){
		if (!$name || !trim($name)) return 'empty';
		foreach (CONFIG['forbidden']['names'] as $key => $pattern) {
			if (isset($extendedpattern[$key])) $pattern = $extendedpattern[$key];
			preg_match('/' . $pattern. '/m', $name, $match);
			if ($match) {
				return $pattern;
			}
		}
		foreach ($extendedpattern as $key => $pattern) {
			preg_match('/' . $pattern. '/m', $name, $match);
			if ($match) {
				return $pattern;
			}
		}
		return false;
	}

	/**
	 *   _   _   _   _                 _   _         
	 *  | |_|_|_| |_| |___ ___ ___ ___| |_|_|___ ___ 
	 *  |   | | . | . | -_|   | . | . |  _| | . |   |
	 *  |_|_|_|___|___|___|_|_|___|  _|_| |_|___|_|_|
	 *                            |_|
	 * returns an altered option for selections
	 * it ain't much but is honest work by having the same pattern over the whole api
	 * adjust frontent assemble/dialog accordingly to return the correct values
	 * 
	 * @param string $string input
	 * @return string output
	 */
	public static function hiddenOption($string){
		return $string . ' [X]';
	}

	/**
	 *   _   _         _   _ ___ _         
	 *  |_|_| |___ ___| |_|_|  _|_|___ ___ 
	 *  | | . | -_|   |  _| |  _| | -_|  _|
	 *  |_|___|___|_|_|_| |_|_| |_|___|_|  
	 *
	 * handles the identifier format globally
	 * if the passed identifier does not have a valid trailing base 36 unix timestamp the default date will be appended accordingly
	 * @param string passed $identifier
	 * @param string $default_date_to_add
	 * @param bool $return_string_before_timestamp returns first part of submitted identifier without timestamp appended
	 * @param bool $translate_timestamp returns Y-m-d H:i:s translation of timestamp
	 * @param bool $return_verified_timestamp returns the trailing timestamp if valid
	 * @return string|null
	 */
	public static function identifier($identifier = '', $default_date_to_add = '', $return_string_before_timestamp = false, $translate_timestamp = false, $return_verified_timestamp = false){
		if (!$identifier) return $identifier;

		// find `lorem ipsum #t8r3to` or `lorem ipsum #t8r3to dolor`
		// does *not* match `#t8r3to lorem ipsum` or `mambo #5`
		// current timestamp has a min length of 6 characters, 7 max within expected anthropocene
		preg_match('/(.+?)(?:\s*#([a-z0-9]{6,7}))($|[^a-z0-9])/', $identifier, $components);
		if ($components && isset($components[2]) && $components[2]){
			try {
				// try to convert to unixtime int
				@$unixtime = intval(base_convert($components[2], 36, 10));
				// narrow down to recent time
				if ($unixtime && 1755208800 < $unixtime && $unixtime < 3453317999) { // 2025-08-15 - 2079-06-06
					$datetime = new \DateTime();
					$datetime->setTimestamp($unixtime);
					// if no error has risen the identifier is likely valid

					if ($return_verified_timestamp) return '#' . $components[2]; // proper trailing timestamp with separator
					if ($translate_timestamp) return $datetime->format('Y-m-d H:i:s'); // translated Y-m-d H:i:s timestamp
					if ($return_string_before_timestamp){
						if (isset($components[1]) && $components[1]) return $components[1];
					}
					return $identifier;
				}
			}
			catch (\Exception $e){
			// do nothing, return null by default if checks are supposed to be applied. valid responses have been returned in advance
			}
		}
		if ($return_verified_timestamp) return null;
		if ($translate_timestamp) return null;
		if ($default_date_to_add) {
			$unixtime = strtotime($default_date_to_add);
			$identifier .= ' #' . base_convert($unixtime, 10, 36); // separator must be a valid character for urls, # and alike are forbidden
		}
		return $identifier;
	}

	/**
	 *     _                                     _     
	 *    |_|___ ___ ___       ___ ___ ___ ___ _| |___ 
	 *    | |_ -| . |   |     | -_|   |  _| . | . | -_|
	 *   _| |___|___|_|_|_____|___|_|_|___|___|___|___|
	 *  |___|           |_____|
	 * wrapper for easier harmonization of cross api behaviour
	 * 
	 * @param array $array input
	 * @param flags normal bitmasked json_encode flags
	 * 
	 * @return string output
	 */
	public static function json_encode($array = [], $flags = null){
		return json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $flags);
	}


	/**
	 *                                   _           _
	 *   ___ ___ ___ ___ ___ ___ ___ _ _| |___ ___ _| |
	 *  | . | .'|  _|_ -| -_| . | .'| | | | . | .'| . |
	 *  |  _|__,|_| |___|___|  _|__,|_  |_|___|__,|___|
	 *  |_|                 |_|     |___|
	 * prepares passed request parameters, mimics post data for put method
	 * preserves literal variable names without replacing whitepace, questionmark and alike with _
	 * empty file inputs are stripped from the payload
	 * 
	 * @return object with request parameters and their value
	 */
	public static function parsePayload(){
		switch($_SERVER['REQUEST_METHOD']){
			case "GET":
			case "DELETE":
				// according to https://stackoverflow.com/a/18209799/6087758
				$inputstream = preg_replace_callback(
					'/(^|(?<=&))[^=[&]+/',
					function($key) { return bin2hex(urldecode($key[0])); },
					$_SERVER['QUERY_STRING']
				);
				parse_str($inputstream, $post);
				$data = array();
				foreach ($post as $key => $val) {
					$data[hex2bin($key)] = urldecode($val);
				}
				break;
			case "POST":
			case "PUT":
			case "PATCH":
				$raw_data = file_get_contents('php://input');
				// linebreak depending on apache vs iis
				$linebreak = stristr("\r\n", $raw_data) ? "\r\n" : "\n";
				
				// unset proper setup for POST requests prior to this methods population
				$_FILES = [];

				// Fetch content and determine boundary
				$boundary = rtrim(substr($raw_data, 0, strpos($raw_data, $linebreak)));

				if (empty($boundary)){
					// according to https://stackoverflow.com/a/18209799/6087758
					$raw_data = preg_replace_callback(
						'/(^|(?<=&))[^=[&]+/',
						function($key) { return bin2hex(urldecode($key[0])); },
						$raw_data
					);
					parse_str($raw_data, $input);
					$data = [];
					foreach ($input as $key => $val) {
						$data[hex2bin($key)] = $val;
					}
				}
				else {
					// fetch each part
					$parts = array_slice(explode($boundary, $raw_data), 1);
					$data = array();

					foreach ($parts as $part){
						// linebreak depending on apache vs iis
						$linebreak = stristr("\r\n", $part) ? "\r\n" : "\n";

						// if this is the last part, break
						if (str_starts_with($part, "--")) break;

						// separate content from headers
						$part = ltrim($part, $linebreak);

						if (!$part) continue;

						if ($linebreak === "\n"){
							// iis
							list(, $raw_headers, $type, $body) = explode($linebreak, $part, 4);
							$body = ltrim($body);
						}
						else {
							// apache
							list($raw_headers, $type, $body) = explode($linebreak . $linebreak, $part, 3);
						}
						// retrieve type like Content-Type: application/pdf or '' if not file
						$type = explode(': ', $type);
						$type = isset($type[1]) ? $type[1] : '';

						// parse the headers list
						$raw_headers = explode($linebreak, $raw_headers);
						$headers = array();
						foreach ($raw_headers as $header){
							// list($name, $value) = explode(':', $header); this original does not work with input names containing colons
							preg_match("/^(.+?):(.+?)$/m", $header, $formerlyexploded);
							list(, $name, $value) = $formerlyexploded;
							$headers[strtolower($name)] = ltrim($value, ' ');
						}

						// parse the Content-Disposition to get the field name, etc.
						if (isset($headers['content-disposition'])){
							$filename = null;
							$tmp_name = null;
							preg_match(
								'/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
								$headers['content-disposition'],
								$matches
							);
							list(, , $name) = $matches;

							// parse file
							if (isset($matches[4])){
								// get filename
								$filename = $matches[4];
								$fieldname = preg_replace('/\[\]/', '', $matches[2]);

								// get tmp name
								$filename_parts = pathinfo( $filename );
								//$tmp_name = tempnam( ini_get('upload_tmp_dir'), $filename_parts['filename']);
								@$tmp_name = tempnam( sys_get_temp_dir(), preg_replace('/\W/', '', $filename_parts['filename']));
								
								// populate $_FILES with information, size may be off in multibyte situation
								$_files = [
									'error' => 0,
									'name' => $filename,
									'tmp_name' => $tmp_name,
									'size' => strlen( $body ),
									'type' => $type
								];
								
								$_FILES[$fieldname]['error'][] = $_files['error'];
								$_FILES[$fieldname]['name'][] = $_files['name'];
								$_FILES[$fieldname]['tmp_name'][] = $_files['tmp_name'];
								$_FILES[$fieldname]['size'][] = $_files['size'];
								$_FILES[$fieldname]['type'][] = $_files['type'];

								// place in temporary directory
								file_put_contents($tmp_name, $body);
							}
							// parse Field
							else {
								if (substr($name, -2) == '[]') { // is array
									$name = substr($name, 0, strlen($name) - 2);
									if (isset($data[$name])) $data[$name][] = substr($body, 0, strlen($body) - 2);
									else $data[$name] = [substr($body, 0, strlen($body) - 2)];
								}
								else $data[$name] = substr($body, 0, strlen($body) - 2);
							}
						}
					}
				}
				break;
			default:
				return [];
				break;
		}
		$payload = self::cleanInputs($data);
		return (object) $payload;
	}		
	
	/**
	 *                           _               _
	 *   ___ ___ ___ ___ ___ ___| |_ _ _ ___ ___| |_
	 *  | . |  _| . | . | -_|  _|  _| | |_ -| -_|  _|
	 *  |  _|_| |___|  _|___|_| |_| |_  |___|___|_|
	 *  |_|         |_|             |___|
	 * shorthand checking for a set property
	 * 
	 * @param object|array $object to look within
	 * @param string $property to look for
	 * 
	 * @return array|string|bool property value or false
	 */
	public static function propertySet($object, $property){
		if (gettype($object) === 'array') return $object[$property] ?? false;
		return (property_exists($object, $property) && boolval($object->{$property}) && $object->{$property} !== 'undefined') ? $object->{$property} : false;
	}


	/**
	 *       _   ___ ___ ___             _ _ 
	 *   _ _| |_|  _| . |_  |___ ___ ___|_|_|
	 *  | | |  _|  _| . |  _| .'|_ -|  _| | |
	 *  |___|_| |_| |___|___|__,|___|___|_|_|
	 *
	 * tries to replace utf8 characters from the config to their ascii cousins.
	 * kudos: https://stackoverflow.com/a/5950598/6087758
	 * iconv relies on locales that you may not have control over
	 * and this is more versatile than https://stackoverflow.com/q/158241/6087758 with  mb_convert_encoding to ISO-8859-15
	 */
	public static function utf82ascii($string){
		if (strpos($string = htmlentities($string, ENT_QUOTES, 'UTF-8'), '&') !== false){
			$string = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:caron|acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1', $string), ENT_QUOTES, 'UTF-8');
		}
		return $string;
	}

	/**
	 *             _                           _   
	 *   _ _ _ ___| |_ ___ ___ ___ _ _ ___ ___| |_ 
	 *  | | | | -_| . |  _| -_| . | | | -_|_ -|  _|
	 *  |_____|___|___|_| |___|_  |___|___|___|_|  
	 *                          |_| 
	 * does a web request and returns the response content as well as curl info
	 * 
	 * @param string $url
	 * @param string $method for PUT, DELETE, etc. - GET by default, POST by default if $postdata is provided
	 * @param array $headers e.g. ['Accept-Encoding: gzip, deflate', 'Accept-Language: en-US,en;q=0.5', 'Cache-Control: no-cache',]
	 * @param array $postdata e.g. ['username': 'string', 'password': 'string']
	 * 
	 * @return array associative with response and curl_getinfo metadata
	 */
	public static function webrequest($url, $method = null, $headers = [], $postdata = []){
		$request = curl_init();
		curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($request, CURLOPT_URL, $url);

		if ($method) curl_setopt($request, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		if ($headers) curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
		if ($postdata) curl_setopt($request, CURLOPT_POSTFIELDS, $postdata);

		if (CONFIG['system']['proxy']['proxy']) curl_setopt($request, CURLOPT_PROXY, CONFIG['proxy']['proxy']['system']);
		if (CONFIG['system']['proxy']['auth']) curl_setopt($request, CURLOPT_PROXYUSERPWD, CONFIG['proxy']['auth']['system']);

		$response = [
			'response' => curl_exec($request),
			'metadata' => curl_getinfo($request)
		];
		return $response;
	}
}

/**
 * permission handling, checking if necessary permissions have been given
 */
class PERMISSION {

	/**
	 *   ___     _ _                                   _
	 *  |  _|_ _| | |_ _ ___ ___ ___ ___ ___ _ _ ___ _| |
	 *  |  _| | | | | | | .'| . | . |  _| . | | | -_| . |
	 *  |_| |___|_|_|_  |__,|  _|  _|_| |___|\_/|___|___|
	 *              |___|   |_| |_|
	 * check whether an approvalcolumn has been fully approved according to function
	 * @param string $function as defined within config.ini
	 * @param string|array $approvalcolumn 'approval'-column
	 * @return bool
	 * 
	 */
	public static function fullyapproved($function = '', $approvalcolumn = ''){
		if (gettype($approvalcolumn) === 'string') $approvalcolumn = $approvalcolumn ? json_decode($approvalcolumn, true) : [];
		$approved = true;
		foreach (self::permissionFor($function, true) as $permission){
			if (!$approvalcolumn || !isset($approvalcolumn[$permission])) $approved = false;
		}
		return $approved;
	}

	/**
	 *                 _ _
	 *   ___ ___ ___ _| |_|___ ___
	 *  | . | -_|   | . | |   | . |
	 *  |  _|___|_|_|___|_|_|_|_  |
	 *  |_|                   |___|
	 * check whether an approvalcolumn has pending approvals according to function
	 * check per user permission so there is only one count per unapproved element even on multiple permissions
	 * @param string $function as defined within config.ini
	 * @param string|array $approvalcolumn 'approval'-column
	 * @return array of pending approval permission
	 * 
	 */
	public static function pending($function = '', $approvalcolumn = ''){
		if (gettype($approvalcolumn) === 'string') $approvalcolumn = $approvalcolumn ? json_decode($approvalcolumn, true) : [];
		$pending = [];
		foreach (self::permissionFor($function, true) as $permission){
			if (!$approvalcolumn || array_intersect(['admin', $permission], $_SESSION['user']['permissions']) && !isset($approvalcolumn[$permission])) $pending[] = $permission;
		}
		return $pending;
	}
	
	/**
	 *                     _         _         ___
	 *   ___ ___ ___ _____|_|___ ___|_|___ ___|  _|___ ___
	 *  | . | -_|  _|     | |_ -|_ -| | . |   |  _| . |  _|
	 *  |  _|___|_| |_|_|_|_|___|___|_|___|_|_|_| |___|_|
	 *  |_|
	 * returns a boolean if user is authorized for requested app-function, array of permissions if $returnvalues argument is true
	 * @param string $function as defined within config.ini
	 * @param bool $returnvalues
	 * @param bool|array $default
	 * @return bool|array
	 */
	public static function permissionFor($function = '', $returnvalues = false, $default = ['admin']){
		if (!isset($_SESSION['user']) || !isset($_SESSION['user']['permissions'])) return [];
		if (isset(CONFIG['permissions'][$function])){
			if (!$returnvalues) {
				if (!$default) $default = [];
				// limited functions don't include admin by default
				if (in_array($function, ['productslimited'])) return boolval(array_intersect([...preg_split('/\W+/', CONFIG['permissions'][$function])], $_SESSION['user']['permissions']));
				return boolval(array_intersect([...$default, ...preg_split('/\W+/', CONFIG['permissions'][$function])], $_SESSION['user']['permissions']));
			}
			return preg_split('/\W+/', CONFIG['permissions'][$function]);
		}
		UTILITY::debug('permission ' . $function . ' not found in config.ini file');
	}

	/**
	 *                     _         _         _     
	 *   ___ ___ ___ _____|_|___ ___|_|___ ___|_|___ 
	 *  | . | -_|  _|     | |_ -|_ -| | . |   | |   |
	 *  |  _|___|_| |_|_|_|_|___|___|_|___|_|_|_|_|_|
	 *  |_|
	 * 
	 * returns a boolean if user matches with passed authorizations
	 * @param array|string $auth as either array or comma separated string
	 * @return bool
	 */
	public static function permissionIn($auth){
		if (!$auth) return true;
		if (gettype($auth) === 'string') $auth = preg_split('/\W+/', $auth);
		return array_intersect(['admin', ...$auth], $_SESSION['user']['permissions']);
	}

	/**
	 *   ___ _ _ _                 _                 
	 *  |  _|_| | |_ ___ ___ ___ _| |_ _ ___ ___ ___ 
	 *  |  _| | |  _| -_|  _| -_| . | | |_ -| -_|  _|
	 *  |_| |_|_|_| |___|_| |___|___|___|___|___|_|  
	 *
	 * user filter
	 * skips system user and patients by default if not specified otherwise
	 * @param array $user database row
	 * @param array $filter with id, permission or unit as arrays
	 * @return bool
	 */
	public static function filteredUser($user, $filter = ['id' => [1], 'permission' => ['patient']]) {
		if (isset($filter['id']) && isset($user['id'])) {
			if (in_array(intval($user['id']), array_map(fn($id) => intval($id), $filter['id']))) return true;
		}
		if (isset($filter['permission']) && isset($user['permissions'])) {
			if (gettype($user['permissions']) !== 'array') $user['permissions'] = explode(',', $user['permissions'] ? : '');
			if ($user['permissions'] && array_intersect($user['permissions'], $filter['permission'])) return true;
		}
		if (isset($filter['unit']) && isset($user['units'])) {
			if (gettype($user['units']) !== 'array') $user['units'] = explode(',', $user['units'] ? : '');
			if ($user['units'] && array_intersect($user['units'], $filter['unit'])) return true;
		}
		return false;
	}

}


class SEARCH{
	/**
	 *                               _
	 *   ___ _ _ ___ ___ ___ ___ ___|_|___ ___ ___
	 *  | -_|_'_| . |  _| -_|_ -|_ -| | . |   |_ -|
	 *  |___|_,_|  _|_| |___|___|___|_|___|_|_|___|
	 *          |_|
	 * splits a search string into terms with conditions
	 * @param string $search requested search string
	 * @return array array of named arrays containing operator, column, term and pregterm with wildcard replacement
	 */
	public static function expressions($search){
		preg_match_all('/([+-]{0,1})([\w\.]+:){0,1}((?:["\'])(.+?)(?:["\'])|\S+)/', $search ? : '', $expressions, PREG_SET_ORDER);
		$result = [];
		foreach($expressions as $expression){
			// remove some leading or trailing characters on regular searches
			// for it may be common to look for "surName, givenName" and surnames are unlikely to end on comma
			$expression[3] = preg_replace('/^[,;]|[,;]$/', '', $expression[3]);
			// assign regular expression unless explicit quoted 
			$term = $expression[4] ?? $expression[3];
			// assign column if applicable
			$column = isset($expression[2]) ? substr($expression[2], 0, -1) : null;
			
			// filter terms resulting in unnecessary huge result lists
			if (in_array($term, ['+', '-'])) continue; // most probably a typo 
			if ((strlen($term) < 2 && $term !== '%' && !$column) // still allows explicit int(bool) and general system searches
				|| preg_match('/^[*?]*$/m', $term) // drop term with only asterisk and question mark wildcards
				) continue;

			$result[] = [
				'operator' => $expression[1] ? : null,
				'column' => $column,
				'term' => $term,
				'pregterm' => preg_replace(['/\?/', '/\*/'], ['.', '.{0,}'], $term),
				'sqlterm' => preg_replace(['/\?/', '/\*/'], ['_', '%'], $term),
			];
		}
		return $result;		
	}

	/**
	 *   ___ _ _ _           
	 *  |  _|_| | |_ ___ ___ 
	 *  |  _| | |  _| -_|  _|
	 *  |_| |_|_|_| |___|_|  
	 *
	 * iterates over terms and checks if mandatory, excluded or any search strings are found
	 * usable for data not prefiltered via sql query
	 * @param array $terms
	 * @param array $expressions
	 * 
	 * @return bool
	 */
	public static function filter($search, $terms){
		$expressions = self::expressions($search);
		$any = $mandatory = false;
		foreach ($expressions as $expression){
			foreach($terms as $term){
				preg_match('/' . preg_quote($expression['pregterm'], '/') . '/iu', $term ? : '', $matches);
				if ($expression['operator'] === '-' && $matches) return false; // this term should not have been included
				elseif ($expression['operator'] === '+' && $matches) $mandatory = true; // this term must have been included
				elseif ($matches) $any = true;
			}
			if ($any && $mandatory) return true;
		}
		// mandatory has not been requested
		if (!array_filter($expressions, fn($o) => $o['operator'] === '+')) $mandatory = true;
		if ($any && $mandatory) return true;
		return false;
	}

	/**
	 *           ___ _
	 *   ___ ___|  _|_|___ ___
	 *  |  _| -_|  _| |   | -_|
	 *  |_| |___|_| |_|_|_|___|
	 *
	 * apply column conditions if applicable, sort by weighted column matching
	 * @param string $search search string
	 * @param array $results initial result from sql-queries and whatnot
	 * @param array $weighted columns to observe for the most terms matched, sorts most matches to top

	 * @return array
	 */
	public static function refine($search, $results, $weighted){
		$expressions = self::expressions($search);
		$columns = [];
		// reduce expressions by leading operators
		// reduce to column conditions for $result should have been filtered out in advance
		foreach($expressions as $index => &$expression){
			if ($expression['operator'] === '-') unset($expressions[$index]); // has - operator, already filtered out before calling this method
			if (!isset($expression['column']) || !$expression['column']) continue;
			$columns[] = $expression;
			unset($expressions[$index]); // avoid double term filtering
		}

		// remove duplicates, for whatever reason they might exist
		$results = array_unique($results, SORT_REGULAR);

		// unset rows not matching the column condition some_result_column:hasvalue or -some_result_column:hasvalue
		// also if column is not found!
		foreach($columns as $column){
			foreach($results as $r_index => $row){
				if (!isset($row[$column['column']])) {
					unset($results[$r_index]);
					continue;
				}
				preg_match('/' .  preg_quote($column['pregterm'], '/') . '/imu', $row[$column['column']], $match);
				if (($match && $column['operator'] === '-') || (!$match && $column['operator'] !== '-')) unset($results[$r_index]);
			}
		}

		// push to top if more terms match the result row
		usort($results, function ($a, $b) use ($expressions, $weighted){
			$a_matches = $b_matches = 0;
			foreach($expressions as $expression){

				foreach($weighted as $column) {
					if (!isset($a[$column])) continue;
					if (preg_match('/' . preg_quote($expression['pregterm'], '/') . '/imu', $a[$column] ? : '', $matches)) {
						$a_matches++;
						break;
					}
				}
				foreach($weighted as $column) {
					if (!isset($b[$column])) continue;
					if (preg_match('/' . preg_quote($expression['pregterm'], '/') . '/imu', $b[$column] ? : '', $matches)) {
						$b_matches++;
						break;
					}
				}
			}
			return $a_matches <=> $b_matches;
		});
		$results = array_reverse($results);

		return $results;
	}
}

class IPTC {
	// kudos https://www.php.net/manual/en/function.iptcembed.php#85887
	private $meta = [];
	private $hasmeta = false;
	private $file = false;

	// as opposed to the original scriptlet, options are not set as constants
	// non indented tags are shown within nemo file manager by default
	// other tags are still readeable
	// files can be embedded
	private $iptcTag = [
			'IPTC_OBJECT_NAME' => '005',
			'IPTC_EDIT_STATUS' => '007',
			'IPTC_PRIORITY' => '010',
			'IPTC_CATEGORY' => '015',
			'IPTC_SUPPLEMENTAL_CATEGORY' => '020',
			'IPTC_FIXTURE_IDENTIFIER' => '022',
		'IPTC_KEYWORDS' => '025',
			'IPTC_RELEASE_DATE' => '030',
			'IPTC_RELEASE_TIME' => '035',
			'IPTC_SPECIAL_INSTRUCTIONS' => '040',
			'IPTC_REFERENCE_SERVICE' => '045',
			'IPTC_REFERENCE_DATE' => '047',
			'IPTC_REFERENCE_NUMBER' => '050',
			'IPTC_CREATED_DATE' => '055',
			'IPTC_CREATED_TIME' => '060',
			'IPTC_ORIGINATING_PROGRAM' => '065',
			'IPTC_PROGRAM_VERSION' => '070',
			'IPTC_OBJECT_CYCLE' => '075',
		'IPTC_BYLINE' => '080',
			'IPTC_BYLINE_TITLE' => '085',
			'IPTC_CITY' => '090',
			'IPTC_PROVINCE_STATE' => '095',
			'IPTC_COUNTRY_CODE' => '100',
			'IPTC_COUNTRY' => '101',
			'IPTC_ORIGINAL_TRANSMISSION_REFERENCE' => '103',
			'IPTC_HEADLINE' => '105',
			'IPTC_CREDIT' => '110',
			'IPTC_SOURCE' => '115',
		'IPTC_COPYRIGHT_STRING' => '116',
		'IPTC_CAPTION' => '120',
			'IPTC_LOCAL_CAPTION' => '121',
	];
	
	/**
	 * embed metadata to jpeg files
	 * WARNING: embedding binary data does not seem to work under IIS because of reasons
	 * also length is restricted to something at least under windows
	 */
	public function __construct($filename) {
		$size = getimagesize($filename, $info);
		$this->hasmeta = isset($info['APP13']);
		if($this->hasmeta)
			$this->meta = iptcparse($info['APP13']);
		$this->file = $filename;
	}

	public function set($tag, $data) {
		$this->meta['2#' . $this->iptcTag[$tag]] = Array( $data );
		$this->hasmeta = true;
	}

	public function get($tag) {
		return isset($this->meta['2#' . $this->iptcTag[$tag]]) ? $this->meta['2#' . $this->iptcTag[$tag]][0] : false;
	}
	
	public function dump() {
		print_r($this->meta);
	}

	private function binary() {
		$iptc_new = '';
		foreach (array_keys($this->meta) as $s) {
			$tag = str_replace('2#', '', $s);
			$iptc_new .= $this->iptc_maketag(2, $tag, $this->meta[$s][0]);
		}
		return $iptc_new;
	}

	private function iptc_maketag($rec, $dat, $val) {
		$len = strlen($val);
		if ($len < 0x8000) {
				return chr(0x1c).chr($rec).chr($dat).
				chr($len >> 8).
				chr($len & 0xff).
				$val;
		} else {
				return chr(0x1c).chr($rec).chr($dat).
				chr(0x80).chr(0x04).
				chr(($len >> 24) & 0xff).
				chr(($len >> 16) & 0xff).
				chr(($len >> 8 ) & 0xff).
				chr(($len ) & 0xff).
				$val;
		}
	}
	
	public function write($mode = 0) {
		if(!function_exists('iptcembed')) return false;
		$content = iptcembed($this->binary(), $this->file, $mode);
		$filename = $this->file;
		
		@unlink($filename); #delete if exists
		
		$fp = fopen($filename, 'wb');
		fwrite($fp, $content);
		fclose($fp);
	}    
	
	#requires GD library installed
	public  function removeAllTags() {
		$this->hasmeta = false;
		$this->meta = [];
		$img = imagecreatefromstring(implode(file($this->file)));
		@unlink($this->file); #delete if exists
		imagejpeg($img, $this->file, 100);
	}
}

/**
 * this class does create and continue small blockchains from the passed array of named arrays and recent block
 * it appends the hash from the previous hash and the hash from the recent addition
 * 
 * the chain can be verified too
 */
class BLOCKCHAIN {
	/**
	 * add a block to the chain, initiate the chain if necessary
	 * @param null|array $chain
	 * @param array $block
	 * @return array
	 */
	public static function add($chain = [], $block = []){
		if (!$chain || !isset($chain[count($chain) - 1]['hash'])) {
			$chain[] = [
				'hash' => hash('sha256', bin2hex(random_bytes(18))) // create genesis data
			];
		}
		$previous_hash = $chain[count($chain) - 1]['hash'];

		$block_hash = hash('sha256', UTILITY::json_encode($block));
		$block['hash'] = hash('sha256', $previous_hash . $block_hash);

		$chain[] = $block;
		return $chain;
	}

	/**
	 *   _   _         _       _       _     _ _             _ ___ _       _ 
	 *  | |_| |___ ___| |_ ___| |_ ___|_|___|_|_|_ _ ___ ___|_|  _|_|___ _| |
	 *  | . | | . |  _| '_|  _|   | .'| |   |_ _| | | -_|  _| |  _| | -_| . |
	 *  |___|_|___|___|_,_|___|_|_|__,|_|_|_|_|_|\_/|___|_| |_|_| |_|___|___|
	 * 
	 * verify chain by validating hashes. if $details is set to true the chain is returned with raw data and additional verification information
	 * @param object $_pdo established connection
	 * @param array $chain
	 * @param bool $details to return details on checks
	 * @return bool|array
	 */
	public static function verified($_pdo, $chain = [], $details = false){
		$_filehandler = new FILEHANDLER($_pdo);
		$report = [];
		if (!$chain) return false;
		// initiate report with genesis block
		$report[] = ['content' => "Genesis block\n> " . UTILITY::json_encode($chain[0]), 'verification' => "\n* " . (isset($chain[0]['hash']) ? "Randomized hash" : "**This block is not secured!**")];
		// iterate through available blocks
		for($i = 1; $i < count($chain); $i++){
			$block = $chain[$i];
			$blockreport = ['content' => 'Block ' . $i . "  \n> " . UTILITY::json_encode($block), 'verification' => ''];

			if (!isset($chain[$i - 1]['hash'])){
				if (!$details) return false;
				$blockreport['verification'] .= "\n* **This block is not secured!**";
			}
			else {
				$previous_hash = $chain[$i - 1]['hash'];

				$current_hash = $block['hash'];
				unset($block['hash']);
				$block_hash = hash('sha256', UTILITY::json_encode($block));
				if ($current_hash !== hash('sha256', $previous_hash . $block_hash)) {
					if (!$details) return false;
					$blockreport['verification'] .= "\n* **previous hash " . $previous_hash . ' and content hash ' . $block_hash . ' did not resolve to required current hash ' . $current_hash . "!**";
				}
				else $blockreport['verification'] .= "\n* previous hash " . $previous_hash . ' and content hash ' . $block_hash . ' do resolve to required current hash ' . $current_hash . ".";

				if (isset($block['attachments'])){
					$attachments = gettype($block['attachments']) === 'string' ? json_decode($block['attachments'], true) : $block['attachments'];
					foreach ($attachments as $file => $hash){
						// provide file, write from database if this fileserver strategy is selected per config
						$_filehandler->serve(path: $file, stream: false);
						// verify file hash
						$filehash = hash_file('sha256', $file);
						if (is_file($file)){
							if ($filehash !== $hash){
								if (!$details) return false;
								$blockreport['verification'] .= "\n* " . $file . ' hash ' . $filehash . ' **did not match** ' . $hash;
							}
							else $blockreport['verification'] .= "\n* " . $file . ' **has** hash ' . $filehash;
						}
						else $blockreport['verification'] .= "\n* " . $file . ' **has not been found**';
					}
				}
			}
			$report[] = $blockreport;
		}
		if (!$details) return true;
		return $report;
	}

	/**
	 * merges two chains, including previous hash information for backwards checks
	 * this may have to be done manually or with future methods if necessary in case of serious concerns, but at least there is no data loss
	 * @param array $chain
	 * @param array $append chain to append
	 * @return bool|array false if validation fails, else $chain 
	 */
	public static function merge($chain = [], $append = []){
		if (!self::verified($append)) return false;
		for($i = 1; $i < count($append); $i++){
			$block = $append[$i];
			$block['merged'] = ['former_hash' => $block['hash']];
			// append genesis block to first merged block
			if ($i < 2) {
				$block['merged']['genesis'] = $append[0];
			}
			unset($block['hash']);
			$chain = self::add($chain, $block);
		}
		return $chain;
	}
}
?>