<?php
/*
	extension-lib.inc

	Copyright (c) 2016 - 2020 Andreas Schmidhuber
	All rights reserved.

	XigmaNAS® is a registered trademark of Michael Zoon. (zoon01@xigmanas.com).
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice, this
	   list of conditions and the following disclaimer.
	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

require_once 'autoload.php';

use common\arr;
use common\uuid;

$extension_lib_version = '1.4';
/* Version history:
 * 1.4	2019.05.14	new function ext_load_package
 * 1.3	2018.12.01	function ext_load_config: removed -> $config_data = utf8_encode($config_data); reason: wrong national character encoding
 * 1.2	2018.08.13	function ext_check_version: append line break to the output message
 * 1.1	2017.02.28	save config in ext_remove_rc_commands and ext_create_rc_commands
 * 1.0	2017.02.27	includes changes for PHP 7.1
 */

/*	function ext_load_package
 *	load packages and compare already installed packages for updates
 *	params:
 *		$pkgName: plain package name
 * 		$pkgFileNameNeeded: unique string for pkg search to retrive the right package
 * 		$rootfolder: extension root location
 *	return:
 * 		package manifest array
 */
if(!function_exists('ext_load_package')):
	function ext_load_package($pkgName,$pkgFileNameNeeded,$rootfolder) {
		$manifest = ext_load_config("{$rootfolder}/bin/{$pkgName}/+MANIFEST");
		if($manifest !== false):
//			currently installed pkg
			$pkgInstalledVersion = "{$manifest['name']}-{$manifest['version']}";
		else:
//			retrieve available packages
			$pkgNeeded = exec("pkg search {$pkgName} | awk '/{$pkgFileNameNeeded}/ {print $1}'",$execOutput,$return_val);
//			create package file name
			$pkgFile = "{$rootfolder}/bin/All/{$pkgNeeded}.txz";
			if(!is_file($pkgFile)):
//				fetch necessary package
				exec("pkg fetch -y -o '{$rootfolder}/bin' {$pkgNeeded}",$execOutput,$return_val);
			endif;
//			extract package
			$return_val += mwexec("mkdir -p '{$rootfolder}/bin/{$pkgName}' && tar -xf '{$pkgFile}' -C '{$rootfolder}/bin/{$pkgName}'",true);
			$manifest = ext_load_config("{$rootfolder}/bin/{$pkgName}/+MANIFEST");
			if($manifest !== false):
				$pkgInstalledVersion = "{$manifest['name']}-{$manifest['version']}";
			endif;
		endif;
		echo 'Needed   : ',$pkgNeeded,"\n";
		echo 'Installed: ',$pkgInstalledVersion,"\n";
		return $manifest;
	}
endif;
/*	function ext_check_release
 *	checks current release against a minimal neccessary release
 *	params:
 *		$min_release: float string to compare with the current used release, e.g.: 9.3, 10.3.0.3.2853 => 10.3032853, 11.0
 *	return:
 * 		true	=> current release >= minimal release
 * 		false	=> current release < minimal release
 */
if(!function_exists('ext_check_release')):
	function ext_check_release($min_release) {
		global $config;

//		p.version = 10.3.0.3, p.revision = 2853
		$product_version = explode('.',get_product_version());
		$current_release = $product_version[0] . '.' . $product_version[1] . $product_version[2] . $product_version[3] . get_product_revision();
		if($current_release < floatval($min_release)):
//			release not supported
			return false;
		else:
//			release supported
			return true;
		endif;
	}
endif;
/*	function ext_create_rc_commands
 *	params:
 *		$appname:	extension name
 * 		$postinit:	postinit command
 * 		$shutdown:	shutdown command
 * 		$start_action:	comment e.g. Start, Run, Initialize
 * 		$stop_action:	comment e.g. Stop, Closedown
 * 	return:
 * 		$postinit:	uuid or false
 * 		$shutdown:	uuid or false
 */
if(!function_exists('ext_create_rc_commands')):
	function ext_create_rc_commands($appname,&$postinit = false,&$shutdown = false,$start_action = 'Start',$stop_action = 'Stop') {
		global $config;

//		new rc format
		if(ext_check_release(11.0)):
			unset($rc_param);
//			postinit command
			if(($postinit !== false) && !empty($postinit)):
				$rc_param = [];
				$rc_param['uuid'] = uuid::create_v4();
				$rc_param['name'] = "{$appname} Extension";
				$rc_param['value'] = $postinit;
				$rc_param['comment'] = "{$start_action} {$appname}";
				$rc_param['typeid'] = '2';
				$rc_param['enable'] = true;
				arr::make_branch($config,'rc','param');
				$config['rc']['param'][] = $rc_param;
				$postinit = $rc_param['uuid'];
				unset($rc_param);
			endif;
//			shutdown command
			if(($shutdown !== false) && !empty($shutdown)):
				$rc_param = [];
				$rc_param['uuid'] = uuid::create_v4();
				$rc_param['name'] = "{$appname} Extension";
				$rc_param['value'] = $shutdown;
				$rc_param['comment'] = "{$stop_action} {$appname}";
				$rc_param['typeid'] = '3';
				$rc_param['enable'] = true;
				arr::make_branch($config,'rc','param');
				$config['rc']['param'][] = $rc_param;
				$shutdown = $rc_param['uuid'];
				unset($rc_param);
			endif;
		else:
			if(($postinit !== false) && !empty($postinit)):
				$config['rc']['postinit']['cmd'][] = $postinit;
				$postinit = false;
			endif;
			if(($shutdown !== false) && !empty($shutdown)):
				$config['rc']['shutdown']['cmd'][] = $shutdown;
				$shutdown = false;
			endif;
		endif;
		write_config();
	}
endif;
/*	function ext_remove_rc_commands
 *	remove postinit / shutdown commands from rc array for < 11.x and >= 11.x series.
 *	params:
 *		$config_name:	string to identify the rc command entry in rc|postinit/shutdown|cmd or rc|param|value
 */
if(!function_exists('ext_remove_rc_commands')):
	function ext_remove_rc_commands($config_name) {
		global $config;

		$config_has_changed = false;
		if(array_key_exists('rc',$config) && is_array($config['rc'])):
//			remove existing old rc format entries
			if(array_key_exists('postinit',$config['rc']) && is_array($config['rc']['postinit']) && array_key_exists('cmd',$config['rc']['postinit']) && is_array( $config['rc']['postinit']['cmd'])):
				$rc_param = &$config['rc']['postinit']['cmd'];
				$rc_param_count = count($rc_param);
				for($i = 0;$i < $rc_param_count;$i++):
					if(preg_match("/{$config_name}/",$rc_param[$i])):
						unset($rc_param[$i]);
						$config_has_changed = true;
					endif;
				endfor;
				unset($rc_param);
			endif;
//			remove existing old rc format entries
			if(array_key_exists('shutdown',$config['rc']) && is_array($config['rc']['shutdown']) && array_key_exists('cmd',$config['rc']['shutdown']) && is_array( $config['rc']['shutdown']['cmd'])):
				$rc_param = &$config['rc']['shutdown']['cmd'];
				$rc_param_count = count($rc_param);
				for($i = 0;$i < $rc_param_count;$i++):
					if(preg_match("/{$config_name}/",$rc_param[$i])):
						unset($rc_param[$i]);
						$config_has_changed = true;
					endif;
				endfor;
				unset($rc_param);
			endif;
//			remove existing entries for new rc format
			if(array_key_exists('param',$config['rc']) && is_array($config['rc']['param'])):
				$rc_param = &$config['rc']['param'];
				$rc_param_count = count($rc_param);
				for($i = 0;$i < $rc_param_count;$i++):
					if(array_key_exists('value',$rc_param[$i]) && is_scalar($rc_param[$i]['value'])):
						if(preg_match("/{$config_name}/",$rc_param[$i]['value'])):
							unset($rc_param[$i]);
							$config_has_changed = true;
						endif;
					endif;
				endfor;
				unset($rc_param);
			endif;
		endif;
		if($config_has_changed):
			write_config();
		endif;
	}
endif;
/*	function ext_check_version
 *	checks an extension if a new version exists against the currently installed version
 *	params:
 *		$test_filename:		filename including the full path which holds the version number
 * 		$extension_name:	extension name in URL format to retrive from Github
 * 		$current_version:	string of current installed version
 * 		$button_name:		gettext("name") to create a note for user action, e.g. Maintenance, Update
 * 	return:
 * 		empty:	no new version found
 * 		or string:	message for user action on new version found
 * 		or false: 	Github version could not be retrieved
 */
if(!function_exists('ext_check_version')):
	function ext_check_version($test_filename,$extension_name,$current_version,$button_name) {
		global $app;

		$return_value = false;
//		test if file exists or is older than 24 hours
		if(!is_file($test_filename) || filemtime($test_filename) < (time() - 86400)):
			$fetch_file = escapeshellarg($test_filename);
			$fetch_url = escapeshellarg(sprintf('%s/master/%s/version.txt',$app['repository.raw'],$extension_name));
			$fetch_cmd = sprintf('fetch --no-verify-hostname --no-verify-peer -o %s %s',$fetch_file,$fetch_url);
			$return_val = mwexec($fetch_cmd,false);
			if($return_val == 0):
				$cat_file = escapeshellarg($test_filename);
				$cat_cmd = sprintf('cat %s',$cat_file);
				$server_version = exec($cat_cmd);
				if($server_version != $current_version):
					$return_value = sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"),$server_version,$button_name) . '<br />';
				endif;
			endif;
		endif;
		return $return_value;
	}
endif;
/*	function ext_load_config
 *	loads a json formatted array from a standalone file
 *	params:
 *		$config_file:	config file including full path
 * 	return:
 *		data array:	json formatted array
 * 		or false:	config file not found or content not retrievable
 */
if(!function_exists('ext_load_config')):
	function ext_load_config($config_file,$assoc = true,$depth = 512,$options = 0) {
		if(is_file($config_file)):
			$config_data = file_get_contents($config_file);
			if($config_data === false):
				return false;
			endif;
			$config_data = json_decode($config_data,$assoc,$depth,$options);
			if(is_array($config_data)):
				return $config_data;
			else:
				return false;
			endif;
		else:
			return false;
		endif;
	}
endif;
/*	function ext_save_config
 *	loads a json formatted array from a standalone file
 *	params:
 *		$config_file:	config file including full path
 * 		$config_data:	php array with configuration data
 * 	return:
 * 		error condition
 */
if (!function_exists('ext_save_config')):
	function ext_save_config($config_file,$config_data){
		header("Content-Type: application/json; UTF-8\n");
		file_put_contents($config_file,json_encode($config_data,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ));
		return json_last_error();
	}
endif;
