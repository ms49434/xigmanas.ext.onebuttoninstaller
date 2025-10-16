<?php
/*
	onebuttoninstaller-update_extension.php

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
require_once 'auth.inc';
require_once 'guiconfig.inc';

$app = [
	'name' => 'OneButtonInstaller',
	'version' => 'v0.4.3',
	'config.name' => 'onebuttoninstaller',
	'repository.path' => 'ms49434/xigmanas.ext.'
];
$app['repository.url'] = 'https://github.com/' . $app['repository.path'] . $app['config.name'];
$app['repository.raw'] = 'https://raw.github.com/' . $app['repository.path'] . $app['config.name'];

$config_file = "ext/{$app['config.name']}/{$app['config.name']}.conf";

require_once "ext/{$app['config.name']}/extension-lib.inc";

$domain = strtolower(get_product_name());
$localeOSDirectory = "/usr/local/share/locale";
$localeExtDirectory = "/usr/local/share/locale-{$app['config.name']}";
bindtextdomain($domain,$localeExtDirectory);
$configuration = ext_load_config($config_file);
if(!is_array($configuration)):
	$input_errors[] = sprintf(gettext('Configuration file %s not found!'),"{$app['config.name']}.conf");
	$configuration = [];
endif;
//	default configuration
$configuration['rootfolder'] ??= null;
$configuration['enable'] ??= false;
$configuration['storage_path'] ??= null;
$configuration['path_check'] ??= false;
$configuration['appname'] ??= $app['name'];
$configuration['version'] ??= $app['version'];
$configuration['show_beta'] ??= true;
$configuration['re_install'] ??= false;
$configuration['auto_update'] ??= false;
$configuration['postinit'] ??= '';
$configuration['shutdown'] ??= '';
$configuration['rc_uuid_start'] ??= '';
$configuration['rc_uuid_stop'] ??= '';

if(!isset($configuration['rootfolder']) && !is_dir($configuration['rootfolder'] )):
	$input_errors[] = gettext('Extension installed with fault');
endif;
$pgtitle = [gettext('Extensions'),gettext($configuration['appname']) . ' ' . $configuration['version'],gettext('Maintenance')];
if(is_file("{$configuration['rootfolder']}/oneload")):
	require_once "{$configuration['rootfolder']}/oneload";
endif;
$return_val = mwexec("fetch --no-verify-hostname --no-verify-peer -o {$configuration['rootfolder']}/version_server.txt {$app['repository.raw']}/master/{$app['config.name']}/version.txt",false);
if($return_val == 0):
	$server_version = exec("cat {$configuration['rootfolder']}/version_server.txt");
	if($server_version != $configuration['version']):
		$savemsg .= sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"),$server_version,gettext('Update Extension'));
	endif;
	mwexec("fetch --no-verify-hostname --no-verify-peer -o {$configuration['rootfolder']}/release_notes.txt {$app['repository.raw']}/master/{$app['config.name']}/release_notes.txt",false);
else:
	$server_version = gettext('Unable to retrieve version from server!');
endif;
if(isset($_POST['ext_remove']) && $_POST['ext_remove']):
//	remove start/stop commands
	ext_remove_rc_commands($app['config.name']);
//	remove extension pages/links
	require_once "{$configuration['rootfolder']}/{$app['config.name']}-stop.php";
	header("Location: index.php");
endif;
if(isset($_POST['ext_update']) && $_POST['ext_update']):
//	download installer & install
	$return_val = mwexec("fetch --no-verify-hostname --no-verify-peer -vo {$configuration['rootfolder']}/{$app['config.name']}-install.php '{$app['repository.raw']}/master/{$app['config.name']}/{$app['config.name']}-install.php'",false);
	if($return_val == 0):
		require_once "{$configuration['rootfolder']}/{$app['config.name']}-install.php";
		header('Refresh:8');
	else:
		$input_errors[] = sprintf(gettext('Download of installation file %s failed, installation aborted!'),"{$app['config.name']}-install.php");
	endif;
endif;
bindtextdomain($domain,$localeOSDirectory);
include 'fbegin.inc';
bindtextdomain($domain,$localeExtDirectory);
?>
<form action="<?php echo $app['config.name']; ?>-update_extension.php" method="post" name="iform" id="iform" onsubmit="spinner()">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td class="tabnavtbl">
				<ul id="tabnav">
<?php
					if($configuration['enable']):
?>
						<li class="tabinact"><a href="onebuttoninstaller.php"><span><?=gettext("Install");?></span></a></li>
<?php
					endif;
?>
					<li class="tabinact"><a href="onebuttoninstaller-config.php"><span><?=gettext("Configuration");?></span></a></li>
					<li class="tabact"><a href="onebuttoninstaller-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
				</ul>
			</td>
		</tr>
		<tr>
			<td class="tabcont">
<?php
				if(!empty($input_errors)):
					print_input_errors($input_errors);
				endif;
				if(!empty($savemsg)):
					print_info_box($savemsg);
				endif;
?>
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
<?php
					html_titleline(gettext('Extension Update'));
					html_text('ext_version_current',gettext('Installed version'),$configuration['version']);
					html_text('ext_version_server',gettext('Latest version'),$server_version);
					html_separator();
?>
				</table>
				<div id="update_remarks">
<?php
					html_remark('note_remove',gettext('Note'),gettext("Removing {$configuration['appname']} integration from the system will leave the installation folder untouched - remove the files using Windows Explorer, FTP or some other tool of your choice. <br /><b>Please note: this page will no longer be available.</b> You'll have to re-run {$configuration['appname']} extension installation to get it back onto your system."));
?>
					<br />
					<input id="ext_update" name="ext_update" type="submit" class="formbtn" value="<?=gettext('Update Extension');?>" onclick="return confirm('<?=gettext('The selected operation will be completed. Please do not click any other buttons!');?>')" />
					<input id="ext_remove" name="ext_remove" type="submit" class="formbtn" value="<?=gettext('Remove Extension');?>" onclick="return confirm('<?=gettext('Do you really want to remove the extension from the system?');?>')" />
				</div>
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
<?php
					html_separator();
					html_separator();
					html_titleline(gettext('Extension') . ' ' . gettext('Release Notes'));
?>
					<tr>
						<td class="listt">
							<div>
								<textarea style="width: 100%;" id="content" name="content" class="listcontent" cols="1" rows="25" readonly="readonly"><?php
									unset($lines);
									exec("/bin/cat {$configuration['rootfolder']}/release_notes.txt",$lines);
									foreach($lines as $line):
										echo $line,"\n";
									endforeach;
								?></textarea>
							</div>
						</td>
					</tr>
				</table>
<?php
				include 'formend.inc';
?>
			</td>
		</tr>
	</table>
</form>
<?php
include 'fend.inc';
