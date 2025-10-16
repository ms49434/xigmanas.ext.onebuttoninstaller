<?php
/*
	onebuttoninstaller-install.php

	Copyright (c) 2020 - 2025 Michael Zoon
	All rights reserved.

	Copyright (c) 2015 - 2020 Andreas Schmidhuber
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
require_once 'config.inc';

$app = [
	'name' => 'OneButtonInstaller',
	'version' => 'v0.4.3',
	'config.name' => 'onebuttoninstaller',
	'repository.path' => 'ms49434/xigmanas.ext.'
];
$app['repository.url'] = 'https://github.com/' . $app['repository.path'] . $app['config.name'];
$app['repository.raw'] = 'https://raw.github.com/' . $app['repository.path'] . $app['config.name'];

$arch = $g['arch'];
$platform = $g['platform'];
//	no check necessary since the extension is for all archictectures/platforms/releases => N: warnings for livecd && liveusb within the pages
//	if(($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64" && $arch != "rpi" && $arch != "rpi2")):
//		echo "\f{$arch} is an unsupported architecture!\n"; exit(1);
//	endif;
//	if($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb"):
//		echo "\funsupported platform!\n";  exit(1);
//	endif;
//	install extension
global $input_errors;
global $savemsg;

//	get directory where the installer script resides
$install_dir = dirname(__FILE__);
//	create stripped version name
$vs = str_replace('.','',$app['version']);
//	fetch release archive
$return_val = mwexec("fetch --no-verify-hostname --no-verify-peer -vo {$install_dir}/master.zip '{$app['repository.url']}/releases/download/{$app['version']}/{$app['config.name']}-{$vs}.zip'",false);
if($return_val == 0):
	$return_val = mwexec("tar -xf {$install_dir}/master.zip -C {$install_dir} --exclude='.git*' --strip-components 2",true);
	if($return_val == 0):
		exec("rm {$install_dir}/master.zip");
		exec("chmod -R 775 {$install_dir}");
		require_once "{$install_dir}/ext/extension-lib.inc";
		$config_file = "{$install_dir}/ext/{$app['config.name']}.conf";
		if(is_file("{$install_dir}/version.txt")):
			$file_version = exec("cat {$install_dir}/version.txt");
		else:
			$file_version = 'n/a';
		endif;
		$savemsg = sprintf(gettext("Update to version %s completed!"),$file_version);
	else:
		$input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"),"master.zip corrupt /");
		return;
	endif;
else:
	$input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"),'master.zip');
	return;
endif;
//	uninstall old OBI files < v0.4 and remove the application section from config.xml
if(array_key_exists('onebuttoninstaller',$config) && is_array($config['onebuttoninstaller'])):
	mwexec("rm -Rf /usr/local/www/onebuttoninstaller*",false);
	mwexec("rm -Rf /usr/local/www/ext/onebuttoninstaller",false);
	mwexec("rm -Rf {$install_dir}/locale-obi",false);
	mwexec("rm -Rf {$install_dir}/log",false);
	if(is_link("/usr/local/share/locale-obi")):
		unlink("/usr/local/share/locale-obi");
	endif;
	$configuration = $config['onebuttoninstaller'];
	unset($config['onebuttoninstaller']);
	write_config();
endif;
//	install / update application on NAS4Free
$new_installation = false;
if(!is_array($configuration)):
//	from an old OBI < 0.4 installation
	$configuration = ext_load_config($config_file);
	if(!is_array($configuration)):
//		from an OBI >= 0.4 installation
		$configuration = [];
		$new_installation = true;
//		new installation
	endif;
endif;
$configuration['appname'] = $app['name'];
$configuration['version'] = exec("cat {$install_dir}/version.txt");
$configuration['rootfolder'] = $install_dir;
$configuration['postinit'] = "/usr/local/bin/php-cgi -f {$install_dir}/{$app['config.name']}-start.php";
$configuration['shutdown'] = "/usr/local/bin/php-cgi -f {$install_dir}/{$app['config.name']}-stop.php";
//	remove start/stop commands and existing old rc format entries
ext_remove_rc_commands($app['config.name']);
$configuration['rc_uuid_start'] = $configuration['postinit'];
$configuration['rc_uuid_stop'] = $configuration['shutdown'];
ext_create_rc_commands($app['name'],$configuration['rc_uuid_start'],$configuration['rc_uuid_stop']);
ext_save_config($config_file,$configuration);
require_once "{$install_dir}/{$app['config.name']}-start.php";
if($new_installation):
	echo "\nInstallation completed, use WebGUI | Extensions | {$app['name']} to configure the application!\n";
endif;
//	finally fetch the most recent extensions list to get the latest changes if not already in the master release
$return_val = mwexec("fetch --no-verify-hostname --no-verify-peer -o {$install_dir}/extensions.txt {$app['repository.raw']}/master/{$app['config.name']}/extensions.txt",false);
