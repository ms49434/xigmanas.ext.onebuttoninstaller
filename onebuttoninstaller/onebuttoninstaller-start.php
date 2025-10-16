<?php
/*
	onebuttoninstaller-start.php

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

$fqfn = [
	sprintf('/usr/local/share/locale-%s',$app['config.name']),
	sprintf('/usr/local/www/%s.php',$app['config.name']),
	sprintf('/usr/local/www/%s-config.php',$app['config.name']),
	sprintf("/usr/local/www/%s-update_extension.php",$app['config.name']),
	sprintf("/usr/local/www/ext/%s",$app['config.name'])
];
foreach($fqfn as $f):
	if(is_link($f)):
		unlink($f);
	endif;
endforeach;
mwexec('rmdir -p /usr/local/www/ext');
$rootfolder = dirname(__FILE__);
$return_val = 0;
//	create links to extension files
$return_val += mwexec("ln -sw {$rootfolder}/locale-{$app['config.name']} /usr/local/share/",true);
$return_val += mwexec("ln -sw {$rootfolder}/{$app['config.name']}.php /usr/local/www/{$app['config.name']}.php",true);
$return_val += mwexec("ln -sw {$rootfolder}/{$app['config.name']}-config.php /usr/local/www/{$app['config.name']}-config.php",true);
$return_val += mwexec("ln -sw {$rootfolder}/{$app['config.name']}-update_extension.php /usr/local/www/{$app['config.name']}-update_extension.php",true);
$return_val += mwexec("mkdir -p /usr/local/www/ext",true);
$return_val += mwexec("ln -sw {$rootfolder}/ext /usr/local/www/ext/{$app['config.name']}",true);
//	check for product name and eventually rename translation files for new product name (XigmaNAS)
$domain = strtolower(get_product_name());
if($domain <> "nas4free"):
	$return_val += mwexec("find {$rootfolder}/locale-{$app['config.name']} -name nas4free.mo -execdir mv nas4free.mo {$domain}.mo \;",true);
endif;
if($return_val == 0):
	exec("logger {$app['config.name']}-extension: started");
else:
	exec("logger {$app['config.name']}-extension: error(s) during startup, failed with return value = {$return_val}");
endif;
