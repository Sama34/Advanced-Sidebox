<?php
/*
 * Plugin Name: Advanced Sidebox for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file removes any existing files from ASB 1.0
 */

define('IN_MYBB', 1);
require_once '../../../global.php';
global $config;

$removeFiles = array(
	'inc/languages/english/adv_sidebox.lang.php',
	'inc/plugins/adv_sidebox.php',
	'jscripts/adv_sidebox.js',
	"{$config['admin_dir']}/jscripts/adv_sidebox_acp.js",
	"{$config['admin_dir']}/styles/adv_sidebox_acp.css",
	'inc/plugins/asb/cleanup.php',
);

$removeFolders = array(
	'inc/plugins/adv_sidebox',
);

// delete the old adv_sidebox_xxx files and folders
foreach ($removeFiles as $filename) {
	$fullpath = MYBB_ROOT.$filename;

	if (file_exists($fullpath) &&
		!is_dir($fullpath)) {
		@unlink($fullpath);
	}
}
foreach ($removeFolders as $folder) {
	$fullpath = MYBB_ROOT.$folder;
	if (is_dir($fullpath)) {
		@my_rmdir_recursive($fullpath);
		@rmdir($fullpath);
	}
}

require_once MYBB_ROOT.$config['admin_dir'].'/inc/functions.php';
flash_message('All components of previous installation deleted', 'success');
admin_redirect("{$mybb->settings['bburl']}/{$config['admin_dir']}/index.php?module=config-plugins");

?>
