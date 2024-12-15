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

$language = CONFIG['application']['defaultlanguage'];
if (isset($_SESSION['user']) && isset($_SESSION['user']['app_settings']['language'])) $language = $_SESSION['user']['app_settings']['language'];
$file = file_exists('language.' . $language) ? 'language.' . $language : 'language.' . CONFIG['application']['defaultlanguage'];

define ('LANGUAGEFILE', parse_ini_file($file, true));

class LANG {
	/*
	language files have a context level and their chunks
	:tokens can be passed as a named array to be substituted (like nifty pdo prepared statements)
	chunks can be accessed by context.chunk with the period as separator (like nifty javascript objects)
	*/

	/**
	 * returns a language specific chunk
	 * @param str $request dot separated keys of LANGUAGEFILE
	 * @param array $replace replacement key=>value pairs to replace :placeholders
	 * @param str $forceDefault override user setting, especially on logout, otherwise first login attempts may fail
	 * @return str textchunk with replacements
	 */
	public static function GET($request, $replace = [], $forceDefault = false){
		$request = explode('.', $request);
		$languagefile = !$forceDefault ? LANGUAGEFILE : parse_ini_file('language.' . CONFIG['application']['defaultlanguage'], true); 

		if (!isset($languagefile[$request[0]]) ||
			!isset($languagefile[$request[0]][$request[1]]) ||
			(isset($request[2]) && !isset($languagefile[$request[0]][$request[1]][$request[2]]))){
			return 'undefined language';
		}
		$patterns = [];
		$replacements = [];
		foreach($replace as $pattern => $replacement){
			$patterns[] = '/' . $pattern . '/';
			$replacements[] = $replacement;
		}
		if (isset($request[2])) return preg_replace($patterns, $replacements, $languagefile[$request[0]][$request[1]][$request[2]]);
		return preg_replace($patterns, $replacements, $languagefile[$request[0]][$request[1]]);
	}

	/**
	 * returns slightly reduced languagefile as transfer for js frontend
	 * @return object
	 */
	public static function GETALL(){
		$return = LANGUAGEFILE;
		foreach(['skills', 'documentcontext', 'risk', 'regulatory'] as $unset) {
			unset($return[$unset]);
		}
		return $return;
	}
	
	/**
	 * returns a language specific chunk with whitespaces and periods replaced with underscore as in request parameters
	 * @param str $request dot separated keys of LANGUAGEFILE
	 * @param array $replace replacement key=>value pairs to replace :placeholders
	 * @return str textchunk with replacements with whitespaces replaced with underscore as in request parameters
	 */
	public static function PROPERTY($request, $replace=[]){
		return preg_replace('/[\s\.]/', '_', self::GET($request, $replace));
	}
}
?>