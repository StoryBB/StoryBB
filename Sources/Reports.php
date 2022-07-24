<?php

/**
 * This file is exclusively for generating reports to help assist forum
 * administrators keep track of their forum configuration and state. The
 * core report generation is done in two areas. Firstly, a report "generator"
 * will fill context with relevant data. Secondly, the choice of sub-template
 * will determine how this data is shown to the user
 *
 * Functions ending with "Report" are responsible for generating data for reporting.
 * They are all called from ReportsMain.
 * Never access the context directly, but use the data handling functions to do so.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;

/**
 * Handling function for generating reports.
 * Requires the admin_forum permission.
 * Loads the Reports template and language files.
 * Decides which type of report to generate, if this isn't passed
 * through the querystring it will set the report_type sub-template to
 * force the user to choose which type.
 * When generating a report chooses which sub_template to use.
 * Will call the relevant report generation function.
 * If generating report will call finishTables before returning.
 * Accessed through ?action=admin;area=reports.
 */
function ReportsMain()
{
	global $txt, $context;

	// Only admins, only EVER admins!
	isAllowedTo('admin_forum');

	// Let's get our things running...
	loadLanguage('Reports');

	$context['page_title'] = $txt['generate_reports'];

	// These are the types of reports which exist - and the functions to generate them.
	$context['report_types'] = [
		'board_perms' => 'BoardPermissionsReport',
		'staff' => 'StaffReport',
	];

	routing_integration_hook('integrate_report_types');
	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['generate_reports'],
		'help' => '',
		'description' => $txt['generate_reports_desc'],
	];

	$is_first = 0;
	foreach ($context['report_types'] as $k => $temp)
		$context['report_types'][$k] = [
			'id' => $k,
			'title' => isset($txt['gr_type_' . $k]) ? $txt['gr_type_' . $k] : $k,
			'description' => isset($txt['gr_type_desc_' . $k]) ? $txt['gr_type_desc_' . $k] : null,
			'function' => $temp,
			'is_first' => $is_first++ == 0,
		];

	// If they haven't chosen a report type which is valid, send them off to the report type chooser!
	if (empty($_REQUEST['sa']) || !isset($context['report_types'][$_REQUEST['sa']]))
	{
		$context['sub_template'] = 'report_type';
		return;
	}
	$context['report_type'] = $_REQUEST['sa'];

	$context['sub_template'] = 'report';

	// Make the page title more descriptive.
	$context['page_title'] .= ' - ' . (isset($txt['gr_type_' . $context['report_type']]) ? $txt['gr_type_' . $context['report_type']] : $context['report_type']);

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_report_buttons');

	// Now generate the data.
	$context['report_types'][$context['report_type']]['function']();

	// Finish the tables before exiting - this is to help the templates a little more.
	finishTables();
}

/**
 * Generate a report on the current permissions by board and membergroup.
 * functions ending with "Report" are responsible for generating data
 * for reporting.
 * they are all called from ReportsMain.
 * never access the context directly, but use the data handling
 * functions to do so.
 */
function BoardPermissionsReport()
{
	global $txt, $smcFunc;

	// Get as much memory as possible as this can be big.
	App::setMemoryLimit('256M');

	// Fetch all the board names.
	$request = $smcFunc['db']->query('', '
		SELECT id_board, name, id_profile
		FROM {db_prefix}boards
		ORDER BY id_board'
	);
	$profiles = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$boards[$row['id_board']] = [
			'name' => $row['name'],
			'profile' => $row['id_profile'],
			'mod_groups' => [],
		];
		$profiles[] = $row['id_profile'];
	}
	$smcFunc['db']->free_result($request);

	// Get the ids of any groups allowed to moderate this board
	// Limit it to any boards and/or groups we're looking at
	$request = $smcFunc['db']->query('', '
		SELECT id_board, id_group
		FROM {db_prefix}moderator_groups'
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$boards[$row['id_board']]['mod_groups'][] = $row['id_group'];
	}
	$smcFunc['db']->free_result($request);

	// Get all the possible membergroups, except admin!
	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group != {int:admin_group}
		ORDER BY group_name',
		[
			'admin_group' => 1,
		]
	);
	$member_groups = ['col' => '', -1 => $txt['membergroups_guests'], 0 => $txt['membergroups_members']];

	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$member_groups[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db']->free_result($request);

	// Make sure that every group is represented - plus in rows!
	setKeys('rows', $member_groups);

	// Certain permissions should not really be shown.
	$disabled_permissions = [];

	call_integration_hook('integrate_reports_boardperm', [&$disabled_permissions]);

	// Cache every permission setting, to make sure we don't miss any allows.
	$permissions = [];
	$board_permissions = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_profile, id_group, add_deny, permission
		FROM {db_prefix}board_permissions
		WHERE id_profile IN ({array_int:profile_list})
		ORDER BY id_profile, permission',
		[
			'profile_list' => $profiles,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (in_array($row['permission'], $disabled_permissions))
		{
			continue;
		}

		foreach ($boards as $id => $board)
		{
			if ($board['profile'] == $row['id_profile'])
			{
				$board_permissions[$id][$row['id_group']][$row['permission']] = $row['add_deny'];
			}
		}

		// Make sure we get every permission.
		if (!isset($permissions[$row['permission']]))
		{
			// This will be reused on other boards.
			$permissions[$row['permission']] = [
				'title' => isset($txt['board_perms_name_' . $row['permission']]) ? $txt['board_perms_name_' . $row['permission']] : $row['permission'],
			];
		}
	}
	$smcFunc['db']->free_result($request);

	// Now cycle through the board permissions array... lots to do ;)
	foreach ($board_permissions as $board => $groups)
	{
		// Create the table for this board first.
		newTable($boards[$board]['name'], 'x', 'all', 100, 'center', 200, 'left');

		// Add the header row - shows all the membergroups.
		addData($member_groups);

		// Add the separator.
		addSeparator($txt['board_perms_permission']);

		// Here cycle through all the detected permissions.
		foreach ($permissions as $ID_PERM => $perm_info)
		{
			// Default data for this row.
			$curData = ['col' => $perm_info['title']];

			// Now cycle each membergroup in this set of permissions.
			foreach (array_keys($member_groups) as $id_group)
			{
				// Don't overwrite the key column!
				if ($id_group === 'col')
				{
					continue;
				}

				$group_permissions = isset($groups[$id_group]) ? $groups[$id_group] : [];

				// Do we have any data for this group?
				if (isset($group_permissions[$ID_PERM]))
				{
					// Set the data for this group to be the local permission.
					$curData[$id_group] = $group_permissions[$ID_PERM];
				}
				// Is it inherited from Moderator?
				elseif (in_array($id_group, $boards[$board]['mod_groups']) && !empty($groups[3]) && isset($groups[3][$ID_PERM]))
				{
					$curData[$id_group] = $groups[3][$ID_PERM];
				}
				// Otherwise means it's set to disallow..
				else
				{
					$curData[$id_group] = 'x';
				}

				// Now actually make the data for the group look right.
				if (empty($curData[$id_group]))
					$curData[$id_group] = '<span class="red">' . $txt['board_perms_deny'] . '</span>';
				elseif ($curData[$id_group] == 1)
					$curData[$id_group] = '<span style="color: darkgreen;">' . $txt['board_perms_allow'] . '</span>';
				else
					$curData[$id_group] = 'x';

				// Embolden those permissions different from global (makes it a lot easier!)
				if (@$board_permissions[0][$id_group][$ID_PERM] != @$group_permissions[$ID_PERM])
					$curData[$id_group] = '<strong>' . $curData[$id_group] . '</strong>';
			}

			// Now add the data for this permission.
			addData($curData);
		}
	}
}

/**
 * Report for showing all the forum staff members - quite a feat!
 * functions ending with "Report" are responsible for generating data
 * for reporting.
 * they are all called from ReportsMain.
 * never access the context directly, but use the data handling
 * functions to do so.
 */
function StaffReport()
{
	global $sourcedir, $txt, $smcFunc;

	require_once($sourcedir . '/Subs-Members.php');

	// Fetch all the board names.
	$request = $smcFunc['db']->query('', '
		SELECT id_board, name
		FROM {db_prefix}boards',
		[
		]
	);
	$boards = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$boards[$row['id_board']] = $row['name'];
	$smcFunc['db']->free_result($request);

	// Get every moderator.
	$request = $smcFunc['db']->query('', '
		SELECT mods.id_board, mods.id_member
		FROM {db_prefix}moderators AS mods',
		[
		]
	);
	$moderators = [];
	$local_mods = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$moderators[$row['id_member']][] = $row['id_board'];
		$local_mods[$row['id_member']] = $row['id_member'];
	}
	$smcFunc['db']->free_result($request);

	// Get any additional boards they can moderate through group-based board moderation
	$request = $smcFunc['db']->query('', '
		SELECT mem.id_member, modgs.id_board
		FROM {db_prefix}members AS mem
			INNER JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_group = mem.id_group OR FIND_IN_SET(modgs.id_group, mem.additional_groups) != 0)',
		[
		]
	);

	// Add each board/member to the arrays, but only if they aren't already there
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Either we don't have them as a moderator at all or at least not as a moderator of this board
		if (!array_key_exists($row['id_member'], $moderators) || !in_array($row['id_board'], $moderators[$row['id_member']]))
			$moderators[$row['id_member']][] = $row['id_board'];

		// We don't have them listed as a moderator yet
		if (!array_key_exists($row['id_member'], $local_mods))
			$local_mods[$row['id_member']] = $row['id_member'];
	}

	// Get a list of global moderators (i.e. members with moderation powers).
	$global_mods = array_intersect(membersAllowedTo('moderate_board', 0), membersAllowedTo('approve_posts', 0), membersAllowedTo('remove_any', 0), membersAllowedTo('modify_any', 0));

	// How about anyone else who is special?
	$allStaff = array_merge(membersAllowedTo('admin_forum'), membersAllowedTo('manage_membergroups'), membersAllowedTo('manage_permissions'), $local_mods, $global_mods);

	// Make sure everyone is there once - no admin less important than any other!
	$allStaff = array_unique($allStaff);

	// Get all the possible membergroups!
	$request = $smcFunc['db']->query('', '
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups',
		[
		]
	);
	$groups = [0 => $txt['full_member']];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
	$smcFunc['db']->free_result($request);

	// All the fields we'll show.
	$staffSettings = [
		'position' => $txt['report_staff_position'],
		'moderates' => $txt['report_staff_moderates'],
		'posts' => $txt['report_staff_posts'],
		'last_login' => $txt['report_staff_last_login'],
	];

	// Do it in columns, it's just easier.
	setKeys('cols');

	// Get each member!
	$request = $smcFunc['db']->query('', '
		SELECT id_member, real_name, id_group, posts, last_login
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:staff_list})
		ORDER BY real_name',
		[
			'staff_list' => $allStaff,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Each member gets their own table!.
		newTable($row['real_name'], '', 'left', 'auto', 'left', 200, 'center');

		// First off, add in the side key.
		addData($staffSettings);

		// Create the main data array.
		$staffData = [
			'position' => isset($groups[$row['id_group']]) ? $groups[$row['id_group']] : $groups[0],
			'posts' => $row['posts'],
			'last_login' => timeformat($row['last_login']),
			'moderates' => [],
		];

		// What do they moderate?
		if (in_array($row['id_member'], $global_mods))
			$staffData['moderates'] = '<em>' . $txt['report_staff_all_boards'] . '</em>';
		elseif (isset($moderators[$row['id_member']]))
		{
			// Get the names
			foreach ($moderators[$row['id_member']] as $board)
				if (isset($boards[$board]))
					$staffData['moderates'][] = $boards[$board];

			$staffData['moderates'] = implode(', ', $staffData['moderates']);
		}
		else
			$staffData['moderates'] = '<em>' . $txt['report_staff_no_boards'] . '</em>';

		// Next add the main data.
		addData($staffData);
	}
	$smcFunc['db']->free_result($request);
}

/**
 * This function creates a new table of data, most functions will only use it once.
 * The core of this file, it creates a new, but empty, table of data in
 * context, ready for filling using addData().
 * Fills the context variable current_table with the ID of the table created.
 * Keeps track of the current table count using context variable table_count.
 *
 * @param string $title Title to be displayed with this data table.
 * @param string $default_value Value to be displayed if a key is missing from a row.
 * @param string $shading Should the left, top or both (all) parts of the table beshaded?
 * @param string $width_normal The width of an unshaded column (auto means not defined).
 * @param string $align_normal The alignment of data in an unshaded column.
 * @param string $width_shaded The width of a shaded column (auto means not defined).
 * @param string $align_shaded The alignment of data in a shaded column.
 */
function newTable($title = '', $default_value = '', $shading = 'all', $width_normal = 'auto', $align_normal = 'center', $width_shaded = 'auto', $align_shaded = 'auto')
{
	global $context;

	// Set the table count if needed.
	if (empty($context['table_count']))
		$context['table_count'] = 0;

	// Create the table!
	$context['tables'][$context['table_count']] = [
		'title' => $title,
		'default_value' => $default_value,
		'shading' => [
			'left' => $shading == 'all' || $shading == 'left',
			'top' => $shading == 'all' || $shading == 'top',
		],
		'width' => [
			'normal' => $width_normal,
			'shaded' => $width_shaded,
		],
		/* Align usage deprecated due to HTML5 */
		'align' => [
			'normal' => $align_normal,
			'shaded' => $align_shaded,
		],
		'data' => [],
	];

	$context['current_table'] = $context['table_count'];

	// Increment the count...
	$context['table_count']++;
}

/**
 * Adds an array of data into an existing table.
 * if there are no existing tables, will create one with default
 * attributes.
 * if custom_table isn't specified, it will use the last table created,
 * if it is specified and doesn't exist the function will return false.
 * if a set of keys have been specified, the function will check each
 * required key is present in the incoming data. If this data is missing
 * the current tables default value will be used.
 * if any key in the incoming data begins with '#sep#', the function
 * will add a separator across the table at this point.
 * once the incoming data has been sanitized, it is added to the table.
 *
 * @param array $inc_data The data to include
 * @param null|string $custom_table = null The ID of a custom table to put the data in
 * @return void|false Doesn't return anything unless we've specified an invalid custom_table
 */
function addData($inc_data, $custom_table = null)
{
	global $context;

	// No tables? Create one even though we are probably already in a bad state!
	if (empty($context['table_count']))
		newTable();

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$custom_table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// If we have keys, sanitise the data...
	if (!empty($context['keys']))
	{
		// Basically, check every key exists!
		foreach (array_keys($context['keys']) as $key)
		{
			$data[$key] = [
				'v' => empty($inc_data[$key]) ? $context['tables'][$table]['default_value'] : $inc_data[$key],
			];
			// Special "hack" the adding separators when doing data by column.
			if (substr($key, 0, 5) == '#sep#')
				$data[$key]['separator'] = true;
		}
	}
	else
	{
		$data = $inc_data;
		foreach ($data as $key => $value)
		{
			$data[$key] = [
				'v' => $value,
			];
			if (substr($key, 0, 5) == '#sep#')
				$data[$key]['separator'] = true;
		}
	}

	// Is it by row?
	if (empty($context['key_method']) || $context['key_method'] == 'rows')
	{
		// Add the data!
		$context['tables'][$table]['data'][] = $data;
	}
	// Otherwise, tricky!
	else
	{
		foreach ($data as $key => $item)
			$context['tables'][$table]['data'][$key][] = $item;
	}
}

/**
 * Add a separator row, only really used when adding data by rows.
 *
 * @param string $title The title of the separator
 * @param null|string $custom_table The ID of the custom table
 *
 * @return void|bool Returns false if there are no tables
 */
function addSeparator($title = '', $custom_table = null)
{
	global $context;

	// No tables - return?
	if (empty($context['table_count']))
		return;

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// Plumb in the separator
	$context['tables'][$table]['data'][] = [0 => [
		'separator' => true,
		'v' => $title
	]];
}

/**
 * This does the necessary count of table data before displaying them.
 * is (unfortunately) required to create some useful variables for templates.
 * foreach data table created, it will count the number of rows and
 * columns in the table.
 * will also create a max_width variable for the table, to give an
 * estimate width for the whole table * * if it can.
 */
function finishTables()
{
	global $context;

	if (empty($context['tables']))
		return;

	// Loop through each table counting up some basic values, to help with the templating.
	foreach ($context['tables'] as $id => $table)
	{
		$context['tables'][$id]['id'] = $id;
		$context['tables'][$id]['row_count'] = count($table['data']);
		$curElement = current($table['data']);
		$context['tables'][$id]['column_count'] = count($curElement);

		// Work out the rough width - for templates like the print template. Without this we might get funny tables.
		if ($table['shading']['left'] && $table['width']['shaded'] != 'auto' && $table['width']['normal'] != 'auto')
			$context['tables'][$id]['max_width'] = $table['width']['shaded'] + ($context['tables'][$id]['column_count'] - 1) * $table['width']['normal'];
		elseif ($table['width']['normal'] != 'auto')
			$context['tables'][$id]['max_width'] = $context['tables'][$id]['column_count'] * $table['width']['normal'];
		else
			$context['tables'][$id]['max_width'] = 'auto';
	}
}

/**
 * Set the keys in use by the tables - these ensure entries MUST exist if the data isn't sent.
 *
 * sets the current set of "keys" expected in each data array passed to
 * addData. It also sets the way we are adding data to the data table.
 * method specifies whether the data passed to addData represents a new
 * column, or a new row.
 * keys is an array whose keys are the keys for data being passed to
 * addData().
 * if reverse is set to true, then the values of the variable "keys"
 * are used as opposed to the keys(!
 *
 * @param string $method The method. Can be 'rows' or 'columns'
 * @param array $keys The keys
 * @param bool $reverse Whether we want to use the values as the keys
 */
function setKeys($method = 'rows', $keys = [], $reverse = false)
{
	global $context;

	// Do we want to use the keys of the keys as the keys? :P
	if ($reverse)
		$context['keys'] = array_flip($keys);
	else
		$context['keys'] = $keys;

	// Rows or columns?
	$context['key_method'] = $method == 'rows' ? 'rows' : 'cols';
}
