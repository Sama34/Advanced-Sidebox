<?php
/*
 * Advanced Sidebox Module
 *
 * Online Staff
 *
 * This module is part of the Advanced Sidebox  default module pack. It can be installed and uninstalled like any other module. Even though it is included in the original installation, it is not necessary and can be completely removed by deleting the containing folder (ie modules/thisfolder).
 *
 * If you delete this folder from the installation pack this module will never be installed (and everything should work just fine without it). Don't worry, if you decide you want it back you can always download them again. The best move would be to install the entire package and try them out. Then be sure that the packages you don't want are uninstalled and then delete those folders from your server.
 */

// this file may not be executed from outside of script
if(!defined("IN_MYBB") || !defined("ADV_SIDEBOX"))
{
	die("You need MyBB and Advanced Sidebox installed and properly initialized to use this script.");
}

/*
 * Advanced Sidebox module - staff online
 * module info
 */
function staff_online_box_asb_info()
{
	global $lang;

	if(!$lang->adv_sidebox)
	{
		$lang->load('adv_sidebox');
	}

 	return array
	(
		"name" => 'Online Staff',
		"description" => 'Display online staff members list',
		"version" => "1.4.3",
		"wrap_content" => true,
		"xmlhttp" => true,
		"settings" => array
			(
				"max_staff" => array
				(
					"sid"					=> "NULL",
					"name"				=> "max_staff",
					"title"				=> $lang->adv_sidebox_max_staff_title,
					"description"		=> $lang->adv_sidebox_max_staff_descr,
					"optionscode"	=> "text",
					"value"				=> '5'
				),
				"xmlhttp_on" => array
				(
					"sid"					=> "NULL",
					"name"				=> "xmlhttp_on",
					"title"				=> $lang->adv_sidebox_xmlhttp_on_title,
					"description"		=> $lang->adv_sidebox_xmlhttp_on_description,
					"optionscode"	=> "text",
					"value"				=> '0'
				)
			),
		"templates" => array
			(
				array
				(
					"title" 			=> "adv_sidebox_staff_online",
					"template"		=> "{\$online_staff}",
							"sid"		=> -1
				),
				array
				(
					"title" 			=> "adv_sidebox_staff_online_bit",
					"template"		=> "
							<tr>
								<td class=\"{\$bgcolor}\">
									<table cellspacing=\"0\" cellpadding=\"{\$theme[\'tablespace\']}\" width=\"100%\">
										<tr rowspan=\"2\">
											<td style=\"text-align: center;\" rowspan=\"2\"class=\"{\$bgcolor}\" width=\"30%\">
												<a href=\"{\$staff_profile_link}\"><img src=\"{\$staff_avatar_filename}\" alt=\"{\$staff_avatar_alt}\" title=\"{\$staff_avatar_title}\" width=\"{\$staff_avatar_dimensions}\"/></a>
											</td>
											<td style=\"text-align: center;\" class=\"{\$bgcolor}\" width=\"70%\">
												<a href=\"{\$staff_profile_link}\" title=\"{\$staff_link_title}\">{\$staff_username}</a>
											</td>
										</tr>
										<tr>
											<td style=\"text-align: center;\" rowspan=\"1\">
												{\$staff_badge}
											</td>
										</tr>
									</table>
								</td>
							</tr>
					",
					"sid"		=> -1
			)
		)
	);
}

function staff_online_box_asb_build_template($settings, $template_var, $width)
{
	global $$template_var;
	global $lang;

	if(!$lang->adv_sidebox)
	{
		$lang->load('adv_sidebox');
	}

	$all_online_staff = staff_online_box_asb_get_online_staff($settings, $width);

	if($all_online_staff)
	{
		$$template_var = $all_online_staff;
		return true;
	}
	else
	{
		$$template_var = <<<EOF
	<tr>
		<td class="trow1">{$lang->adv_sidebox_no_staff_online}</td>
	</tr>
EOF;
		return false;
	}
}

function staff_online_box_asb_xmlhttp($dateline, $settings, $width)
{
	$all_online_staff = staff_online_box_asb_get_online_staff($settings, $width);

	if($all_online_staff)
	{
		return $all_online_staff;
	}
	return 'nochange';
}

function staff_online_box_asb_get_online_staff($settings, $width)
{
	global $db, $mybb, $templates, $lang, $cache, $theme;

	// get our setting value
	$max_rows = (int) $settings['max_staff']['value'];

	// if max_rows is set to 0 then show nothing
	if(!$max_rows)
	{
		return false;
	}

	// store our users and groups here
	$usergroups = array();
	$users = array();

	// get all the groups admin has specified should be shown on showteam.php
	$query = $db->simple_select("usergroups", "gid, title, usertitle, image", "showforumteam=1", array('order_by' => 'disporder'));
	while($usergroup = $db->fetch_array($query))
	{
		// store them in our array
		$usergroups[$usergroup['gid']] = $usergroup;
	}

	// get all the users of those specific groups
	$groups_in = implode(",", array_keys($usergroups));

	// if there were no groups . . .
	if(!$groups_in)
	{
		// there is nothing to show
		return false;
	}

	// set the time based on ACP settings
	$timesearch = TIME_NOW - $mybb->settings['wolcutoff'];

	// get all the users that are in staff groups that have been online within the allowed cutoff time
	$query = $db->query("
		SELECT
			s.sid, s.ip, s.uid, s.time, s.location,
			u.username, u.invisible, u.usergroup, u.displaygroup, u.avatar
		FROM
			" . TABLE_PREFIX . "sessions s
		LEFT JOIN
			" . TABLE_PREFIX . "users u ON (s.uid=u.uid)
		WHERE
			(displaygroup IN ($groups_in) OR (displaygroup='0' AND usergroup IN ($groups_in))) AND s.time > '$timesearch'
		ORDER BY
			u.username ASC, s.time DESC
	");

	// loop through our users
	while($user = $db->fetch_array($query))
	{
		// if displaygroup is not 0 (display primary group) . . .
		if($user['displaygroup'] != 0)
		{
			// then use this group
			$group = $user['displaygroup'];
		}
		else
		{
			// otherwise use the primary group
			$group = $user['usergroup'];
		}

		// if this user group is in a staff group then add the info to the list
		if($usergroups[$group])
		{
			$usergroups[$group]['user_list'][$user['uid']] = $user;
		}
	}

	// make sure we start from nothing
	$grouplist = '';
	$counter = 1;

	// loop through each user group
	foreach($usergroups as $usergroup)
	{
		// if there are no users or we have reached our limit . . .
		if(!isset($usergroup['user_list']) || $counter > $max_rows)
		{
			// skip an iteration
			continue;
		}

		// we use this for the alternating table row bgcolor
		$bgcolor = '';

		// loop through all users
		foreach($usergroup['user_list'] as $user)
		{
			// if we are over our limit
			if($counter > $max_rows)
			{
				// don't add any more
				continue;
			}

			// prepare the info
			// alt and title for image are the same
			$staff_avatar_alt = $staff_avatar_title = $user['username'] . '\'s avatar';

			// If the user has an avatar then display it . . .
			if($user['avatar'] != "")
			{
				$staff_avatar_filename = $user['avatar'];
			}
			else
			{
				// . . . otherwise force the default avatar.
				$staff_avatar_filename = "{$theme['imgdir']}/default_avatar.gif";
			}

			// avatar properties
			$staff_avatar_dimensions = '100%';

			// user name link properties
			$staff_link_title = $user['username'];
			$staff_username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);

			// link (for avatar and user name)
			$staff_profile_link = get_profile_link($user['uid']);

			// badge alt and title are the same
			$staff_badge_alt = $staff_badge_title = $usergroup['usertitle'];

			// if the user's group has a badge image . . .
			if($usergroup['image'])
			{
				// store it (if nothing is store alt property will display group default usertitle)
				$staff_badge_filename = $usergroup['image'];

				$staff_badge = "<img src=\"{$staff_badge_filename}\" alt=\"{$staff_badge_alt}\" title=\"{$staff_badge_title}\" width=\"{$staff_badge_width}\"/>";
			}
			else
			{
				$staff_badge = "{$staff_badge_alt}";
			}

			// give us an alternating bgcolor
			$bgcolor = alt_trow();

			// incremenet the counter
			++$counter;

			// add this row to the table
			eval("\$online_staff .= \"" . $templates->get("adv_sidebox_staff_online_bit") . "\";");
		}
	}

	// if there were staff members online . . .
	if($online_staff)
	{
		// show them
		eval("\$all_online_staff = \"" . $templates->get("adv_sidebox_staff_online") . "\";");
		return $all_online_staff;
	}
	else
	{
		// otherwise apologize profusely
		return false;
	}
}

?>
