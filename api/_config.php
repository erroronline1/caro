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

$defaultconfig = parse_ini_file('config.ini', true, INI_SCANNER_TYPED);
if (is_file('config.env')){
	// config.env must contain the same nesting and keys as config.ini to override default settings
	$envconfig = parse_ini_file('config.env', true, INI_SCANNER_TYPED);

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