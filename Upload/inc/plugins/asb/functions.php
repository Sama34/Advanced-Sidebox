<?php
/*
 * Plugin Name: Advanced Sidebox for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * functions for the forum-side
 */

/**
 * avoid wasted execution by determining when and if code is necessary
 *
 * @return bool success/fail
 */
function asbDoStartupChecks()
{
	global $mybb, $theme;

	// if the EXCLUDE list isn't empty and this theme is listed...
	$excludedArray = asbGetExcludedThemes();
	if ($excludedArray &&
		in_array($theme['tid'], $excludedArray)) {
		// no side boxes for you
		return false;
	}

	/*
	 * if the current user is not a guest, admin has allowed disabling side box
	 * display and the user has chosen to do so then do not display
	 */
	if ($mybb->settings['asb_allow_user_disable'] &&
		$mybb->user['uid'] != 0 &&
		$mybb->user['show_sidebox'] == 0) {
		return false;
	}

	/*
	 * if this is a mobile device, and admin has
	 * disabled side boxes for mobile...
	 *
	 * credit: http://stackoverflow.com/users/1304523/justin-docanto
	 */
	if ($mybb->settings['asb_disable_for_mobile'] &&
		preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER['HTTP_USER_AGENT'])) {
		return false;
	}

	return true;
}

/**
 * get the tids of any excluded themes
 *
 * @return array|bool excluded themes or false
 */
function asbGetExcludedThemes($sql=false)
{
	global $mybb;

	$returnVal = unserialize($mybb->settings['asb_exclude_theme']);
	if (!is_array($returnVal) ||
		empty($returnVal)) {
		$returnVal = false;
	}

	if ($sql) {
		if ($returnVal) {
			$returnVal = ' AND pid NOT IN('.implode(',', $returnVal).')';
		} else {
			$returnVal = '';
		}
	}
	return $returnVal;
}

/**
 * add all the parts of the script to build a unique name
 *
 * @param  array script environment info
 * @return string filename marked up for asb
 */
function asbBuildScriptFilename($script='')
{
	if ($script instanceof ScriptInfo) {
		$script = $script->get('data');
	}

	// no info means use the MyBB values
	if (!is_array($script) ||
		empty($script)) {
		global $mybb;
		$script = array(
			'filename' => THIS_SCRIPT,
			'action' => $mybb->input['action'],
			'page' => $mybb->input['page'],
		);
	}

	$script = array_map('trim', $script);

	// if there is nothing to work with...
	if (!$script['filename']) {
		return;
	}

	// build each piece
	$filename = $script['filename'];
	foreach (array('action', 'page') as $key) {
		if (!$script[$key]) {
			continue;
		}
		$filename .= "&{$key}={$script[$key]}";
	}
	return $filename;
}

/**
 * get the correct cached script info using the script parameters
 *
 * @param  array asb cache data
 * @param  bool true indicates that side boxes and templates
 * 	should be loaded along with the other info
 * @return array script info
 */
function asbGetCurrentScript($asb, $getAll=false)
{
	global $mybb;

	if (is_array($asb['scripts'][THIS_SCRIPT]) &&
		!empty($asb['scripts'][THIS_SCRIPT])) {
		$returnArray = $asb['scripts'][THIS_SCRIPT];
	}

	foreach (array('action', 'page') as $key) {
		$mybb->input[$key] = trim($mybb->input[$key]);
		if (!$mybb->input[$key]) {
			continue;
		}

		$filename = THIS_SCRIPT."&{$key}={$mybb->input[$key]}";
		if (!is_array($asb['scripts'][$filename]) ||
			empty($asb['scripts'][$filename])) {
			continue;
		}
		$returnArray = $asb['scripts'][$filename];
	}

	if (empty($returnArray) ||
		!is_array($returnArray)) {
		return;
	}

	// merge any globally visible (script-wise) side boxes with this script
	$returnArray['template_vars'] = array_merge((array) $asb['scripts']['global']['template_vars'], (array) $returnArray['template_vars']);
	$returnArray['extra_scripts'] = (array) $asb['scripts']['global']['extra_scripts'] + (array) $returnArray['extra_scripts'];
	$returnArray['js'] = (array) $asb['scripts']['global']['js'] + (array) $returnArray['js'];

	// the template handler does not need side boxes and templates
	if (!$getAll) {
		return $returnArray;
	}

	// asb_start() and asb_initialize() do
	$returnArray['sideboxes'][0] = asbMergeSideBoxList($asb, (array) $asb['scripts']['global']['sideboxes'][0], (array) $returnArray['sideboxes'][0]);
	$returnArray['sideboxes'][1] = asbMergeSideBoxList($asb, (array) $asb['scripts']['global']['sideboxes'][1], (array) $returnArray['sideboxes'][1]);
	$returnArray['templates'] = array_merge((array) $asb['scripts']['global']['templates'], (array) $returnArray['templates']);
	return $returnArray;
}

/**
 * merge global and script specific side box lists while maintaining display order
 *
 * @param  array asb cache data
 * @param  array two or more arrays of side box ids => module names
 * @return array an array with the merged and sorted arrays
 */
function asbMergeSideBoxList($asb)
{
	// allow for variable amount of arguments
	$args = func_get_args();

	// if there aren't at least two arrays to merge...
	if (count($args) <= 2) {
		// return the single array if it exists
		if ($args[1]) {
			return $args[1];
		}
		// or an empty array if all else fails
		return array();
	}

	// remove the cache data from the arg list
	array_shift($args);

	// merge all the passed arrays
	$mergedArray = array();
	foreach ($args as $sideboxes) {
		foreach ($sideboxes as $sidebox => $module) {
			$mergedArray[$sidebox] = $module;
		}
	}

	// now sort them according to the original side box's display order
	$returnArray = array();
	foreach ($asb['sideboxes'] as $sidebox => $module) {
		if (isset($mergedArray[$sidebox])) {
			$returnArray[$sidebox] = $module['box_type'];
		}
	}
	return $returnArray;
}

/**
 * standard check of all user groups against an allowable list
 *
 * @param  array allowed groups
 * @return bool allowed/not
 */
function asbCheckUserPermissions($allowedGroups)
{
	// no groups = all groups says wildy
	if (empty($allowedGroups)) {
		return true;
	}

	// array-ify the list if necessary
	if (!is_array($allowedGroups)) {
		$allowedGroups = explode(',', $allowedGroups);
	}

	global $mybb;
	if ($mybb->user['uid'] == 0) {
		// guests don't require as much work ;-)
		return in_array(0, $allowedGroups);
	}

	// get all the user's groups in one array
	$usersGroups = array($mybb->user['usergroup']);
	if ($mybb->user['additionalgroups']) {
		$additionalGroups = explode(',', $mybb->user['additionalgroups']);
		$usersGroups = array_merge($usersGroups, $additionalGroups);
	}

	/*
	 * if any overlaps occur then they will be in $testArray,
	 * empty returns true/false so !empty = true for allow and false for disallow
	 */
	$testArray = array_intersect($usersGroups, $allowedGroups);
	return !empty($testArray);
}

/**
 * use the sidebox info to produce its template
 *
 * @param  SideboxObject|array side box
 * @return string|bool html or false
 */
function asbBuildSideBoxContent($thisBox)
{
	// need good info
	if ($thisBox instanceof SideboxObject) {
		$data = $thisBox->get('data');
	} else if (is_array($thisBox) &&
		!empty($thisBox)) {
		$data = $thisBox;
	} else {
		return false;
	}

	// build our info
	foreach (array('id', 'box_type', 'wrap_content', 'title', 'title_link') as $key) {
		if (isset($data[$key])) {
			$$key = $data[$key];
		}
	}

	// build the template variable
	$content = '{$'."{$box_type}_{$id}".'}';

	// if we are building header and expander...
	if ($wrap_content) {
		global $mybb, $templates, $theme, $collapsed;

		// element info
		$sidebox['expcolimage_id'] = "{$box_type}_{$id}_img";
		$sidebox['expdisplay_id'] = "{$box_type}_{$id}_e";
		$sidebox['name'] = "{$id}_{$box_type}_".TIME_NOW;
		$sidebox['class'] = $sidebox['id'] = "{$box_type}_main_{$id}";
		$sidebox['content'] = $content;
		$sidebox['title'] = $title;
		if ($title_link) {
			$sidebox['title'] = <<<EOF
<a href="{$title_link}">{$title}</a>
EOF;
		}

		if ($mybb->settings['asb_show_expanders']) {
			// check if this side box is either expanded or collapsed and hide it as necessary.
			$expdisplay = '';
			$collapsed_name = "{$box_type}_{$id}_c";
			if (isset($collapsed[$collapsed_name]) &&
				$collapsed[$collapsed_name] == 'display: show;') {
				$expcolimage = 'collapse_collapsed.png';
				$expdisplay = 'display: none;';
				$expaltext = '[+]';
			} else {
				$expcolimage = 'collapse.png';
				$expaltext = '[-]';
			}
			eval("\$expander = \"{$templates->get('asb_expander')}\";");
		}
		eval("\$content = \"{$templates->get('asb_wrapped_sidebox')}\";");
	}

	// if there is anything to return
	if ($content) {
		// give it up
		return <<<EOF

		<!-- start side box #{$id} - box type {$box_type} -->
		{$content}
		<!-- end side box #{$id} - box type {$box_type} -->
EOF;
	}

	// otherwise return failure
	return false;
}

/**
 * retrieve any detected modules
 *
 * @return array SideboxExternalModule
 */
function asbGetAllModules()
{
	$returnArray = array();

	// load all detected modules
	foreach (new DirectoryIterator(ASB_MODULES_DIR) as $file) {
		if (!$file->isFile() ||
			$file->isDot() ||
			$file->isDir()) {
			continue;
		}

		$extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);

		// only PHP files
		if ($extension != 'php') {
			continue;
		}

		// extract the baseName from the module file name
		$filename = $file->getFilename();
		$module = substr($filename, 0, strlen($filename) - 4);

		// attempt to load the module
		$returnArray[$module] = new SideboxExternalModule($module);
	}
	return $returnArray;
}

/**
 * retrieve all custom boxes
 *
 * @return array CustomSidebox
 */
function asbGetAllCustomBoxes()
{
	global $db;

	// get any custom boxes
	$returnArray = array();

	$query = $db->simple_select('asb_custom_sideboxes');
	if ($db->num_rows($query) > 0) {
		while ($data = $db->fetch_array($query)) {
			$returnArray['asb_custom_'.$data['id']] = new CustomSidebox($data);
		}
	}
	return $returnArray;
}

/**
 * retrieve all side boxes
 *
 * @param  string script filter
 * @return array SideboxObject
 */
function asbGetAllSideBoxes($allowedScript='')
{
	global $db;

	// get any side boxes
	$returnArray = array();

	$query = $db->simple_select('asb_sideboxes', '*', '', array('order_by' => 'display_order', 'order_dir' => 'ASC'));
	if ($db->num_rows($query) > 0) {
		while ($data = $db->fetch_array($query)) {
			$sidebox = new SideboxObject($data);

			if ($allowedScript) {
				$scripts = $sidebox->get('scripts');
				if (!empty($scripts) &&
					!in_array($allowedScript, $scripts)) {
					continue;
				}
			}

			// create the object and build basic data
			$returnArray[$data['id']] = $sidebox;
		}
	}
	return $returnArray;
}

/**
 * retrieve all script definitions
 *
 * @return array script data
 */
function asbGetAllScripts()
{
	global $db;

	// get all the active scripts' info
	$returnArray = array();

	$query = $db->simple_select('asb_script_info', '*', "active='1'");
	if ($db->num_rows($query) > 0) {
		while ($script = $db->fetch_array($query)) {
			$filename = asbBuildScriptFilename($script);
			$returnArray[$filename] = $script;
		}
	}
	return $returnArray;
}

/**
 * rebuilds the theme exclude list ACP setting
 *
 * @return string|bool html or false
 */
function asbGetAllThemes($full=false)
{
	global $db;

	static $themeList;

	if (!is_array($themeList)) {
		if ($full != true) {
			$excludedThemes = asbGetExcludedThemes(true);
		}

		// get all the themes that are not MasterStyles
		$query = $db->simple_select('themes', 'tid, name', "NOT pid='0'{$excludedThemes}");

		$themeList = array();
		while ($thisTheme = $db->fetch_array($query)) {
			$themeList[$thisTheme['tid']] = $thisTheme['name'];
		}
	}

	return $themeList;
}

?>
