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

class LANG {
	/*
	language files have a context level and their chunks
	:tokens can be passed as a named array to be substituted (like nifty pdo prepared statements)
	chunks can be accessed by context.chunk with the period as separator (like nifty javascript objects)
	*/

	/**
	 * contains the preloaded language file as a named array
	 */
	public $_DEFAULT = [];
	public $_USER = [];

	/**
	 * initalize languagefiles 
	 */
	public function __construct(){
		$file = file_get_contents('./language.' . CONFIG['application']['defaultlanguage'] . '.json');
		$this->_DEFAULT = json_decode($file, true);
		
		if (isset($_SESSION['user']) && isset($_SESSION['user']['app_settings']['language'])){
			$language = $_SESSION['user']['app_settings']['language'];
			if ($file = file_get_contents('./language.' . $language . '.json'))	$this->_USER = json_decode($file, true);
			else $this->_USER = $this->_DEFAULT;
		}
		else $this->_USER = $this->_DEFAULT;
	}

	/**
	 * returns a language specific chunk
	 * @param string $request dot separated keys of LANGUAGEFILE
	 * @param array $replace replacement key=>value pairs to replace :placeholders
	 * @param string $forceDefault override user setting, especially on logout, otherwise first login attempts may fail
	 * @return string textchunk with replacements
	 */
	public function GET($request, $replace = [], $forceDefault = false){
		$request = explode('.', $request);
		$languagefile = !$forceDefault ? $this->_USER : $this->_DEFAULT; 

		if ($chunk = self::find($request, $languagefile)) {
			$patterns = [];
			$replacements = [];
			foreach($replace as $pattern => $replacement){
				$patterns[] = '/' . $pattern . '/';
				$replacements[] = $replacement;
			}
			return preg_replace($patterns, $replacements, $chunk);
		}
		return 'undefined language';
	}
	/**
	 * recursively find the language chunk independent of nesting depth
	 * @param array $chain exploded request
	 * @param array $lang LANGUAGEFILE or passed subset
	 */
	private static function find($chain, $lang){
		$key = array_shift($chain);
		if (isset($lang[$key])){
			if (gettype($lang[$key]) === 'array') {
				if (!$chain) return false;
				return self::find($chain, $lang[$key]);
			}
			else return $lang[$key];
		}
	}

	/**
	 * returns slightly reduced languagefile as transfer for js frontend
	 * @return object
	 */
	public function GETALL(){
		$return = $this->_USER;
		foreach(['skills', 'documentcontext', 'risk', 'regulatory'] as $unset) {
			unset($return[$unset]);
		}
		return $return;
	}
	
	/**
	 * returns a language specific chunk with whitespaces and periods replaced with underscore as in request parameters
	 * @param string $request dot separated keys of languagefile
	 * @param array $replace replacement key=>value pairs to replace :placeholders
	 * @return string textchunk with replacements and whitespaces replaced with underscore as in request parameters
	 */
	public function PROPERTY($request, $replace = []){
		return preg_replace('/[\s\.]/', '_', $this->GET($request, $replace));
	}
}
?>