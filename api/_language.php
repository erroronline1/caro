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

$language = INI['application']['defaultlanguage'];
if (array_key_exists('user', $_SESSION) && array_key_exists('language', $_SESSION['user']['app_settings'])) $language = $_SESSION['user']['app_settings']['language'];
$file = file_exists('language.' . $language . '.ini') ? 'language.' . $language . '.ini' : 'language.' . INI['application']['defaultlanguage'] . '.ini';

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
		$languagefile = !$forceDefault ? LANGUAGEFILE : parse_ini_file('language.' . INI['defaultlanguage'] . '.ini', true); 

		if (!array_key_exists($request[0], $languagefile) ||
			!array_key_exists($request[1], $languagefile[$request[0]]) ||
			(array_key_exists(2, $request) && !array_key_exists($request[2], $languagefile[$request[0]][$request[1]]))){
			return 'undefined language';
		}
		$patterns = [];
		$replacements = [];
		foreach($replace as $pattern => $replacement){
			$patterns[] = '/' . $pattern . '/';
			$replacements[] = $replacement;
		}
		if (array_key_exists(2, $request)) return preg_replace($patterns, $replacements, $languagefile[$request[0]][$request[1]][$request[2]]);
		return preg_replace($patterns, $replacements, $languagefile[$request[0]][$request[1]]);
	}

	/**
	 * returns languagefile as transfer for js frontend
	 * @return array LANGUAGEFILE
	 */
	public static function GETALL(){
		return LANGUAGEFILE;
	}
	
	/**
	 * returns a language specific chunk with whitespaces replaced with underscore as in request parameters
	 * @param str $request dot separated keys of LANGUAGEFILE
	 * @param array $replace replacement key=>value pairs to replace :placeholders
	 * @return str textchunk with replacements with whitespaces replaced with underscore as in request parameters
	 */
	public static function PROPERTY($request, $replace=[]){
		return str_replace(' ', '_', self::GET($request, $replace));
	}
}
?>