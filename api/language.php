<?php
define ('LANGUAGEFILE', parse_ini_file('language.' . INI['language'] . '.ini', true));

class LANG {
	/*
	language files have a context level and their chunks
	:tokens can be passed as a named array to be substituted (like nifty pdo prepared statements)
	chunks can be accessed by context.chunk with the period as separator (like nifty javascript objects)
	*/

	public static function GET($request, $replace=[]){
		$request=explode('.', $request);
		if (!array_key_exists($request[0], LANGUAGEFILE) ||
			!array_key_exists($request[1], LANGUAGEFILE[$request[0]]) ||
			(array_key_exists(2, $request) && !array_key_exists($request[2], LANGUAGEFILE[$request[0]][$request[1]]))){
			return 'undefined language';
		}
		$patterns = [];
		$replacements = [];
		foreach($replace as $pattern => $replacement){
			$patterns[] = '/' . $pattern . '/';
			$replacements[] = $replacement;
		}
		if (array_key_exists(2, $request)) return preg_replace($patterns, $replacements, LANGUAGEFILE[$request[0]][$request[1]][$request[2]]);
		return preg_replace($patterns, $replacements, LANGUAGEFILE[$request[0]][$request[1]]);
	}

	public static function PROPERTY($request, $replace=[]){
		return str_replace(' ', '_', self::GET($request, $replace));
	}

	public static function GETALL(){
		return LANGUAGEFILE;
	}
}

?>