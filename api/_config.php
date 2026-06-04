<?php
/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

$defaultconfig = parse_ini_file(__DIR__ . '/../api/config.ini', true, INI_SCANNER_TYPED);
if (is_file(__DIR__ . '/../api/config.env')){
	// config.env must contain the same nesting and keys as config.ini to override default settings
	$envconfig = parse_ini_file(__DIR__ . '/../api/config.env', true, INI_SCANNER_TYPED);

	/**
	 * recursively overwrite and append default config.ini parameters with environment settings
	 * @param array $config starting with $defaultconfig
	 * @param array $env starting with $envconfig
	 */
	function override($config, $env){
		foreach ($env as $key => $value){
			if (gettype($value) === 'array'){
				if (!isset($config[$key])) {
					$config[$key] = [];
				}
				$config[$key] = override($config[$key], $env[$key]);
			}
			else {
				$config[$key] = $env[$key];
			}
		}
		return $config;
	}
	$defaultconfig = override($defaultconfig, $envconfig);
}

define ('CONFIG', $defaultconfig);

// set runtime settings as per config
if (isset(CONFIG['calendar']['timezones']))	date_default_timezone_set(CONFIG['calendar']['timezones'][array_key_first(CONFIG['calendar']['timezones'])]);
?>