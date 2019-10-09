<?php

/**
 * ManagePermissions handles all possible permission stuff.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;

/**
 * Dispatches to the right function based on the given subaction.
 * Checks the permissions, based on the sub-action.
 * Called by ?action=managepermissions.
 *
 * @uses ManagePermissions language file.
 */

function ModifyPermissions()
{
	global $txt, $context;

	loadLanguage('ManagePermissions+ManageMembers');

	// Format: 'sub-action' => array('function_to_call', 'permission_needed'),
	$subActions = [
		'board' => ['PermissionByBoard', 'manage_permissions'],
		'index' => ['PermissionIndex', 'manage_permissions'],
		'modify' => ['ModifyMembergroup', 'manage_permissions'],
		'modify2' => ['ModifyMembergroup2', 'manage_permissions'],
		'quick' => ['SetQuickGroups', 'manage_permissions'],
		'quickboard' => ['SetQuickBoards', 'manage_permissions'],
		'profiles' => ['EditPermissionProfiles', 'manage_permissions'],
	];

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && empty($subActions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : (allowedTo('manage_permissions') ? 'index' : 'settings');
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['permissions_title'],
		'description' => '',
		'tabs' => [
			'index' => [
				'description' => $txt['permissions_groups'],
			],
			'board' => [
				'description' => $txt['permission_by_board_desc'],
			],
			'profiles' => [
				'description' => $txt['permissions_profiles_desc'],
			],
		],
	];

	routing_integration_hook('integrate_manage_permissions', [&$subActions]);

	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Sets up the permissions by membergroup index page.
 * Called by ?action=managepermissions
 * Creates an array of all the groups with the number of members and permissions.
 *
 * @uses ManagePermissions language file.
 * @uses ManagePermissions template file.
 * @uses ManageBoards template, permission_index sub-template.
 */
function PermissionIndex()
{
	global $txt, $scripturl, $context, $settings, $modSettings, $smcFunc;

	$context['page_title'] = $txt['permissions_title'];

	loadLanguage('ManageMembers');

	// Load all the permissions. We'll need them in the template.
	loadAllPermissions();

	// Also load profiles, we may want to reset.
	loadPermissionProfiles();

	// Are we going to show the advanced options?
	$context['show_advanced_options'] = empty($context['admin_preferences']['app']);

	// Determine the number of ungrouped members.
	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE id_group = {int:regular_group}',
		[
			'regular_group' => 0,
		]
	);
	list ($num_members) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Fill the context variable with 'Guests' and 'Regular Members'.
	$context['groups'] = [
		-1 => [
			'id' => -1,
			'name' => $txt['membergroups_guests'],
			'num_members' => $txt['membergroups_guests_na'],
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => '',
			'link' => '',
			'help' => 'membergroup_guests',
			'color' => '',
			'icons' => '',
			'children' => [],
			'num_permissions' => [
				'allowed' => 0,
				// Can't deny guest permissions!
				'denied' => '(' . $txt['permissions_none'] . ')'
			],
			'access' => false
		],
		0 => [
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'num_members' => $num_members,
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => $scripturl . '?action=moderate;area=viewgroups;sa=members;group=0',
			'help' => 'membergroup_regular_members',
			'color' => '',
			'icons' => '',
			'children' => [],
			'num_permissions' => [
				'allowed' => 0,
				'denied' => 0
			],
			'access' => false
		],
	];
	$context['character_groups'] = [];

	$normalGroups = [];
	$characterGroups = [];

	// Query the database defined membergroups.
	$query = $smcFunc['db']->query('', '
		SELECT id_group, id_parent, group_name, online_color, icons, is_character
		FROM {db_prefix}membergroups
		ORDER BY id_parent = {int:not_inherited} DESC, group_name',
		[
			'not_inherited' => -2,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($query))
	{
		// If it's inherited, just add it as a child.
		if ($row['id_parent'] != -2)
		{
			if (isset($context['groups'][$row['id_parent']]))
				$context['groups'][$row['id_parent']]['children'][$row['id_group']] = $row['group_name'];
			continue;
		}

		$row['icons'] = explode('#', $row['icons']);
		$context['groups'][$row['id_group']] = [
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'num_members' => $row['id_group'] != 3 ? 0 : $txt['membergroups_guests_na'],
			'allow_delete' => $row['id_group'] > 4,
			'allow_modify' => $row['id_group'] > 1,
			'can_search' => $row['id_group'] != 3,
			'href' => $scripturl . '?action=moderate;area=viewgroups;sa=members;group=' . $row['id_group'],
			'help' => $row['id_group'] == 1 ? 'membergroup_administrator' : ($row['id_group'] == 3 ? 'membergroup_moderator' : ''),
			'color' => empty($row['online_color']) ? '' : $row['online_color'],
			'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $row['icons'][1] . '" alt="*">', $row['icons'][0]) : '',
			'children' => [],
			'num_permissions' => [
				'allowed' => $row['id_group'] == 1 ? '(' . $txt['permissions_all'] . ')' : 0,
				'denied' => $row['id_group'] == 1 ? '(' . $txt['permissions_none'] . ')' : 0
			],
			'access' => false,
		];

		if (!empty($row['is_character']))
		{
			$characterGroups[$row['id_group']] = $row['id_group'];
		}
		else
		{
			$normalGroups[$row['id_group']] = $row['id_group'];
		}
	}
	$smcFunc['db']->free_result($query);

	if (!empty($normalGroups))
	{
		// First, the easy one!
		$query = $smcFunc['db']->query('', '
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			[
				'normal_group_list' => $normalGroups,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db']->free_result($query);

		// This one is slower, but it's okay... careful not to count twice!
		$query = $smcFunc['db']->query('', '
			SELECT mg.id_group, COUNT(*) AS num_members
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			[
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db']->free_result($query);
	}

	if (!empty($characterGroups))
	{
		// First, the easy one!
		$query = $smcFunc['db']->query('', '
			SELECT main_char_group, COUNT(*) AS num_characters
			FROM {db_prefix}characters
			WHERE main_char_group IN ({array_int:character_group_list})
			GROUP BY main_char_group',
			[
				'character_group_list' => $characterGroups,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
			$context['groups'][$row['main_char_group']]['num_members'] += $row['num_characters'];
		$smcFunc['db']->free_result($query);

		// This one is slower, but it's okay... careful not to count twice!
		$query = $smcFunc['db']->query('', '
			SELECT mg.id_group, COUNT(*) AS num_characters
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}characters AS chars ON (chars.char_groups != {string:blank_string}
					AND chars.main_char_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, chars.char_groups) != 0)
			WHERE mg.id_group IN ({array_int:character_group_list})
			GROUP BY mg.id_group',
			[
				'character_group_list' => $characterGroups,
				'blank_string' => '',
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_characters'];
		$smcFunc['db']->free_result($query);
	}

	foreach ($context['groups'] as $id => $data)
	{
		if ($data['href'] != '')
			$context['groups'][$id]['link'] = '<a href="' . $data['href'] . '">' . $data['num_members'] . '</a>';
	}

	if (empty($_REQUEST['pid']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}permissions
			' . (empty($context['hidden_permissions']) ? '' : ' WHERE permission NOT IN ({array_string:hidden_permissions})') . '
			GROUP BY id_group, add_deny',
			[
				'hidden_permissions' => !empty($context['hidden_permissions']) ? $context['hidden_permissions'] : [],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
			if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
		$smcFunc['db']->free_result($request);

		// Get the "default" profile permissions too.
		$request = $smcFunc['db']->query('', '
			SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:default_profile}
			' . (empty($context['hidden_permissions']) ? '' : ' AND permission NOT IN ({array_string:hidden_permissions})') . '
			GROUP BY id_profile, id_group, add_deny',
			[
				'default_profile' => 1,
				'hidden_permissions' => !empty($context['hidden_permissions']) ? $context['hidden_permissions'] : [],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
		}
		$smcFunc['db']->free_result($request);
	}
	else
	{
		$_REQUEST['pid'] = (int) $_REQUEST['pid'];

		if (!isset($context['profiles'][$_REQUEST['pid']]))
			fatal_lang_error('no_access', false);

		// Change the selected tab to better reflect that this really is a board profile.
		$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';

		$request = $smcFunc['db']->query('', '
			SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}
			GROUP BY id_profile, id_group, add_deny',
			[
				'current_profile' => $_REQUEST['pid'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
		}
		$smcFunc['db']->free_result($request);

		$context['profile'] = [
			'id' => $_REQUEST['pid'],
			'name' => $context['profiles'][$_REQUEST['pid']]['name'],
		];
	}

	// Having done all the processing, sift out character groups.
	if (!empty($characterGroups))
	{
		foreach ($characterGroups as $group)
		{
			$context['character_groups'][$group] = $context['groups'][$group];
			unset($context['groups'][$group]);
		}
	}

	// We can modify any permission set apart from the read only, reply only and no polls ones as they are redefined.
	$context['can_modify'] = empty($_REQUEST['pid']) || $_REQUEST['pid'] == 1 || $_REQUEST['pid'] > 4;
	if (!$context['can_modify'])
	{
		session_flash('warning', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'));
	}

	// Load the proper template.
	$context['sub_template'] = 'admin_permissions';
	createToken('admin-mpq');
}

/**
 * Handle permissions by board... more or less. :P
 */
function PermissionByBoard()
{
	global $context, $txt, $smcFunc, $sourcedir, $cat_tree, $boardList, $boards;

	$context['page_title'] = $txt['permissions_boards'];
	$context['edit_all'] = isset($_GET['edit']);

	// Saving?
	if (!empty($_POST['save_changes']) && !empty($_POST['boardprofile']))
	{
		checkSession('request');
		validateToken('admin-mpb');

		$changes = [];
		foreach ($_POST['boardprofile'] as $pBoard => $profile)
		{
			$changes[(int) $profile][] = (int) $pBoard;
		}

		if (!empty($changes))
		{
			foreach ($changes as $profile => $boards)
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}boards
					SET id_profile = {int:current_profile}
					WHERE id_board IN ({array_int:board_list})',
					[
						'board_list' => $boards,
						'current_profile' => $profile,
					]
				);
		}

		$context['edit_all'] = false;
	}

	// Load all permission profiles.
	loadPermissionProfiles();

	// Get the board tree.
	require_once($sourcedir . '/Subs-Boards.php');

	getBoardTree();

	// Build the list of the boards.
	$context['categories'] = [];
	foreach ($cat_tree as $catid => $tree)
	{
		$context['categories'][$catid] = [
			'name' => &$tree['node']['name'],
			'id' => &$tree['node']['id'],
			'boards' => []
		];
		foreach ($boardList[$catid] as $boardid)
		{
			if (!isset($context['profiles'][$boards[$boardid]['profile']]))
				$boards[$boardid]['profile'] = 1;

			$context['categories'][$catid]['boards'][$boardid] = [
				'id' => &$boards[$boardid]['id'],
				'name' => &$boards[$boardid]['name'],
				'description' => &$boards[$boardid]['description'],
				'child_level' => &$boards[$boardid]['level'],
				'profile' => &$boards[$boardid]['profile'],
				'profile_name' => $context['profiles'][$boards[$boardid]['profile']]['name'],
			];
		}
	}

	$context['sub_template'] = 'admin_permissions_board_profiles';
	createToken('admin-mpb');
}

/**
 * Handles permission modification actions from the upper part of the
 * permission manager index.
 */
function SetQuickGroups()
{
	global $context, $smcFunc;

	checkSession();
	validateToken('admin-mpq', 'quick');

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Make sure only one of the quick options was selected.
	if ((!empty($_POST['predefined']) && ((isset($_POST['copy_from']) && $_POST['copy_from'] != 'empty') || !empty($_POST['permissions']))) || (!empty($_POST['copy_from']) && $_POST['copy_from'] != 'empty' && !empty($_POST['permissions'])))
		fatal_lang_error('permissions_only_one_option', false);

	if (empty($_POST['group']) || !is_array($_POST['group']))
		$_POST['group'] = [];

	// Only accept numeric values for selected membergroups.
	foreach ($_POST['group'] as $id => $group_id)
		$_POST['group'][$id] = (int) $group_id;
	$_POST['group'] = array_unique($_POST['group']);

	// And now character groups, before we merge them back in...
	// @todo can this mean character groups potentially could get perms they shouldn't have?
	if (empty($_POST['charactergroup']) || !is_array($_POST['charactergroup']))
		$_POST['charactergroup'] = [];

	foreach ($_POST['charactergroup'] as $id => $group_id)
		$_POST['charactergroup'][$id] = (int) $group_id;
	$_POST['charactergroup'] = array_unique($_POST['charactergroup']);

	$_POST['group'] = array_merge($_POST['group'], $_POST['charactergroup']);

	if (empty($_REQUEST['pid']))
		$_REQUEST['pid'] = 0;
	else
		$_REQUEST['pid'] = (int) $_REQUEST['pid'];

	// Fix up the old global to the new default!
	$bid = max(1, $_REQUEST['pid']);

	// No modifying the predefined profiles.
	if ($_REQUEST['pid'] > 1 && $_REQUEST['pid'] < 5)
		fatal_lang_error('no_access', false);

	// Clear out any cached authority.
	updateSettings(['settings_updated' => time()]);

	// No groups where selected.
	if (empty($_POST['group']))
		redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

	// Set a predefined permission profile.
	if (!empty($_POST['predefined']))
	{
		// Make sure it's a predefined permission set we expect.
		if (!in_array($_POST['predefined'], ['restrict', 'standard', 'moderator', 'maintenance']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		foreach ($_POST['group'] as $group_id)
		{
			if (!empty($_REQUEST['pid']))
				setPermissionLevel($_POST['predefined'], $group_id, $_REQUEST['pid']);
			else
				setPermissionLevel($_POST['predefined'], $group_id);
		}
	}
	// Set a permission profile based on the permissions of a selected group.
	elseif ($_POST['copy_from'] != 'empty')
	{
		// Just checking the input.
		if (!is_numeric($_POST['copy_from']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		// Make sure the group we're copying to is never included.
		$_POST['group'] = array_diff($_POST['group'], [$_POST['copy_from']]);

		// No groups left? Too bad.
		if (empty($_POST['group']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		if (empty($_REQUEST['pid']))
		{
			// Retrieve current permissions of group.
			$request = $smcFunc['db']->query('', '
				SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:copy_from}',
				[
					'copy_from' => $_POST['copy_from'],
				]
			);
			$target_perm = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$target_perm[$row['permission']] = $row['add_deny'];
			$smcFunc['db']->free_result($request);

			$inserts = [];
			foreach ($_POST['group'] as $group_id)
				foreach ($target_perm as $perm => $add_deny)
				{
					// No dodgy permissions please!
					if (!empty($context['illegal_permissions']) && in_array($perm, $context['illegal_permissions']))
						continue;
					if ($group_id == -1 && in_array($perm, $context['non_guest_permissions']))
						continue;

					if ($group_id != 1 && $group_id != 3)
						$inserts[] = [$perm, $group_id, $add_deny];
				}

			// Delete the previous permissions...
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:group_list})
					' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
				[
					'group_list' => $_POST['group'],
					'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : [],
				]
			);

			if (!empty($inserts))
			{
				// ..and insert the new ones.
				$smcFunc['db']->insert('',
					'{db_prefix}permissions',
					[
						'permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int',
					],
					$inserts,
					['permission', 'id_group']
				);
			}
		}

		// Now do the same for the board permissions.
		$request = $smcFunc['db']->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group = {int:copy_from}
				AND id_profile = {int:current_profile}',
			[
				'copy_from' => $_POST['copy_from'],
				'current_profile' => $bid,
			]
		);
		$target_perm = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$target_perm[$row['permission']] = $row['add_deny'];
		$smcFunc['db']->free_result($request);

		$inserts = [];
		foreach ($_POST['group'] as $group_id)
			foreach ($target_perm as $perm => $add_deny)
			{
				// Are these for guests?
				if ($group_id == -1 && in_array($perm, $context['non_guest_permissions']))
					continue;

				$inserts[] = [$perm, $group_id, $bid, $add_deny];
			}

		// Delete the previous global board permissions...
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:current_group_list})
				AND id_profile = {int:current_profile}',
			[
				'current_group_list' => $_POST['group'],
				'current_profile' => $bid,
			]
		);

		// And insert the copied permissions.
		if (!empty($inserts))
		{
			// ..and insert the new ones.
			$smcFunc['db']->insert('',
				'{db_prefix}board_permissions',
				['permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'],
				$inserts,
				['permission', 'id_group', 'id_profile']
			);
		}

		// Update any children out there!
		updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}
	// Set or unset a certain permission for the selected groups.
	elseif (!empty($_POST['permissions']))
	{
		// Unpack two variables that were transported.
		list ($permissionType, $permission) = explode('/', $_POST['permissions']);

		// Check whether our input is within expected range.
		if (!in_array($_POST['add_remove'], ['add', 'clear', 'deny']) || !in_array($permissionType, ['membergroup', 'board']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		if ($_POST['add_remove'] == 'clear')
		{
			if ($permissionType == 'membergroup')
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND permission = {string:current_permission}
						' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
					[
						'current_group_list' => $_POST['group'],
						'current_permission' => $permission,
						'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : [],
					]
				);
			else
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND id_profile = {int:current_profile}
						AND permission = {string:current_permission}',
					[
						'current_group_list' => $_POST['group'],
						'current_profile' => $bid,
						'current_permission' => $permission,
					]
				);
		}
		// Add a permission (either 'set' or 'deny').
		else
		{
			$add_deny = $_POST['add_remove'] == 'add' ? '1' : '0';
			$permChange = [];
			foreach ($_POST['group'] as $groupID)
			{
				if ($groupID == -1 && in_array($permission, $context['non_guest_permissions']))
					continue;

				if ($permissionType == 'membergroup' && $groupID != 1 && $groupID != 3 && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
					$permChange[] = [$permission, $groupID, $add_deny];
				elseif ($permissionType != 'membergroup')
					$permChange[] = [$permission, $groupID, $bid, $add_deny];
			}

			if (!empty($permChange))
			{
				if ($permissionType == 'membergroup')
					$smcFunc['db']->insert('replace',
						'{db_prefix}permissions',
						['permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'],
						$permChange,
						['permission', 'id_group']
					);
				// Board permissions go into the other table.
				else
					$smcFunc['db']->insert('replace',
						'{db_prefix}board_permissions',
						['permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'],
						$permChange,
						['permission', 'id_group', 'id_profile']
					);
			}
		}

		// Another child update!
		updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}

	redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
}

/**
 * Initializes the necessary to modify a membergroup's permissions.
 */
function ModifyMembergroup()
{
	global $context, $txt, $smcFunc, $modSettings;

	if (!isset($_GET['group']))
		fatal_lang_error('no_access', false);

	$context['group']['id'] = (int) $_GET['group'];

	// It's not likely you'd end up here with this setting disabled.
	if ($_GET['group'] == 1)
		redirectexit('action=admin;area=permissions');

	loadAllPermissions();
	loadPermissionProfiles();
	$context['hidden_perms'] = [];

	if ($context['group']['id'] > 0)
	{
		$result = $smcFunc['db']->query('', '
			SELECT group_name, id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			[
				'current_group' => $context['group']['id'],
			]
		);
		list ($context['group']['name'], $parent) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);

		// Cannot edit an inherited group!
		if ($parent != -2)
			fatal_lang_error('cannot_edit_permissions_inherited');
	}
	elseif ($context['group']['id'] == -1)
		$context['group']['name'] = $txt['membergroups_guests'];
	else
		$context['group']['name'] = $txt['membergroups_members'];

	$context['profile']['id'] = empty($_GET['pid']) ? 0 : (int) $_GET['pid'];

	// If this is a moderator and they are editing "no profile" then we only do boards.
	if ($context['group']['id'] == 3 && empty($context['profile']['id']))
	{
		// For sanity just check they have no general permissions.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:moderator_group}',
			[
				'moderator_group' => 3,
			]
		);

		$context['profile']['id'] = 1;
	}

	$context['permission_type'] = empty($context['profile']['id']) ? 'membergroup' : 'board';
	$context['profile']['can_modify'] = !$context['profile']['id'] || $context['profiles'][$context['profile']['id']]['can_modify'];

	// Set up things a little nicer for board related stuff...
	if ($context['permission_type'] == 'board')
	{
		$context['profile']['name'] = $context['profiles'][$context['profile']['id']]['name'];
		$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';
	}

	// Fetch the current permissions.
	$permissions = [
		'membergroup' => ['allowed' => [], 'denied' => []],
		'board' => ['allowed' => [], 'denied' => []]
	];

	// General permissions?
	if ($context['permission_type'] == 'membergroup')
	{
		$result = $smcFunc['db']->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}',
			[
				'current_group' => $_GET['group'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($result))
			$permissions['membergroup'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		$smcFunc['db']->free_result($result);
	}

	// Fetch current board permissions...
	$result = $smcFunc['db']->query('', '
		SELECT permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		[
			'current_group' => $context['group']['id'],
			'current_profile' => $context['permission_type'] == 'membergroup' ? 1 : $context['profile']['id'],
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($result))
		$permissions['board'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
	$smcFunc['db']->free_result($result);

	// Loop through each permission and set whether it's checked.
	foreach ($context['permissions'] as $permissionType => $tmp)
	{
		foreach ($tmp['columns'] as $position => $permissionGroups)
		{
			foreach ($permissionGroups as $permissionGroup => $permissionArray)
			{
				foreach ($permissionArray['permissions'] as $perm)
				{
					// Create a shortcut for the current permission.
					$curPerm = &$context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['permissions'][$perm['id']];

					if ($perm['has_own_any'])
					{
						$curPerm['any']['select'] = in_array($perm['id'] . '_any', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $permissions[$permissionType]['denied']) ? 'deny' : 'off');
						$curPerm['own']['select'] = in_array($perm['id'] . '_own', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $permissions[$permissionType]['denied']) ? 'deny' : 'off');
					}
					else
						$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'deny' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');

						// Keep the last value if it's hidden.
						if ($perm['hidden'] || $permissionArray['hidden'])
						{
							if ($perm['has_own_any'])
							{
								$context['hidden_perms'][] = [
									$permissionType,
									$perm['own']['id'],
									$curPerm['own']['select'],
								];
								$context['hidden_perms'][] = [
									$permissionType,
									$perm['any']['id'],
									$curPerm['any']['select'],
								];
							}
							else
								$context['hidden_perms'][] = [
									$permissionType,
									$perm['id'],
									$curPerm['select'],
								];
						}
				}
			}
		}
	}

	// Check that any group marked as non-hidden actually has some non-hidden permissions too.
	foreach ($context['permissions'] as $permissionType => $tmp)
	{
		foreach ($tmp['columns'] as $position => $permissionGroups)
		{
			foreach ($permissionGroups as $permissionGroup => $permissionArray)
			{
				if (empty($permissionArray['permissions']))
				{
					// If it's empty, also mark it as hidden.
					$context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['hidden'] = true;
					continue;
				}

				// Step through all the permissions in the group.
				$has_display_content = false;
				foreach ($permissionArray['permissions'] as $perm)
				{
					if (!$perm['hidden'])
					{
						$has_display_content = true;
						break;
					}
				}
				if (!$has_display_content)
				{
					$context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['hidden'] = true;
				}
			}
		}
	}

	$context['sub_template'] = 'admin_permission_edit';
	$context['page_title'] = $txt['permissions_modify_group'];

	createToken('admin-mp');
}

/**
 * This function actually saves modifications to a membergroup's board permissions.
 */
function ModifyMembergroup2()
{
	global $smcFunc, $context;

	checkSession();
	validateToken('admin-mp');

	loadIllegalPermissions();

	$_GET['group'] = (int) $_GET['group'];
	$_GET['pid'] = (int) $_GET['pid'];

	// Cannot modify predefined profiles.
	if ($_GET['pid'] > 1 && $_GET['pid'] < 5)
		fatal_lang_error('no_access', false);

	// Verify this isn't inherited.
	if ($_GET['group'] == -1 || $_GET['group'] == 0)
		$parent = -2;
	else
	{
		$result = $smcFunc['db']->query('', '
			SELECT id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			[
				'current_group' => $_GET['group'],
			]
		);
		list ($parent) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);
	}

	if ($parent != -2)
		fatal_lang_error('cannot_edit_permissions_inherited');

	$givePerms = ['membergroup' => [], 'board' => []];

	// Guest group, we need illegal, guest permissions.
	if ($_GET['group'] == -1)
	{
		loadIllegalGuestPermissions();
		$context['illegal_permissions'] = array_merge($context['illegal_permissions'], $context['non_guest_permissions']);
	}

	// Prepare all permissions that were set or denied for addition to the DB.
	if (isset($_POST['perm']) && is_array($_POST['perm']))
	{
		foreach ($_POST['perm'] as $perm_type => $perm_array)
		{
			if (is_array($perm_array))
			{
				foreach ($perm_array as $permission => $value)
					if ($value == 'on' || $value == 'deny')
					{
						// Don't allow people to escalate themselves!
						if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
							continue;

						$givePerms[$perm_type][] = [$_GET['group'], $permission, $value == 'deny' ? 0 : 1];
					}
			}
		}
	}

	// Insert the general permissions.
	if ($_GET['group'] != 3 && empty($_GET['pid']))
	{
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			[
				'current_group' => $_GET['group'],
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : [],
			]
		);

		if (!empty($givePerms['membergroup']))
		{
			$smcFunc['db']->insert('replace',
				'{db_prefix}permissions',
				['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$givePerms['membergroup'],
				['id_group', 'permission']
			);
		}
	}

	// Insert the boardpermissions.
	$profileid = max(1, $_GET['pid']);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		[
			'current_group' => $_GET['group'],
			'current_profile' => $profileid,
		]
	);
	if (!empty($givePerms['board']))
	{
		foreach ($givePerms['board'] as $k => $v)
			$givePerms['board'][$k][] = $profileid;
		$smcFunc['db']->insert('replace',
			'{db_prefix}board_permissions',
			['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int', 'id_profile' => 'int'],
			$givePerms['board'],
			['id_group', 'permission', 'id_profile']
		);
	}

	// Update any inherited permissions as required.
	updateChildPermissions($_GET['group'], $_GET['pid']);

	// Clear cached privs.
	updateSettings(['settings_updated' => time()]);

	redirectexit('action=admin;area=permissions;pid=' . $_GET['pid']);
}

/**
 * A screen to set some general settings for permissions.
 *
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the config_vars array if $return_config is true
 */
function GeneralPermissionSettings($return_config = false)
{
	global $context, $modSettings, $sourcedir, $txt, $scripturl, $smcFunc;

}

/**
 * Set the permission level for a specific profile, group, or group for a profile.
 * @internal
 *
 * @param string $level The level ('restrict', 'standard', etc.)
 * @param int $group The group to set the permission for
 * @param string|int $profile The ID of the permissions profile or 'null' if we're setting it for a group
 */
function setPermissionLevel($level, $group, $profile = 'null')
{
	global $smcFunc, $context;

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Levels by group... restrict, standard, moderator, maintenance.
	$groupLevels = [
		'board' => ['inherit' => []],
		'group' => ['inherit' => []]
	];
	// Levels by board... standard, publish, free.
	$boardLevels = ['inherit' => []];

	// Restrictive - ie. guests.
	$groupLevels['global']['restrict'] = [
		'search_posts',
		'view_stats',
		'who_view',
		'profile_identity_own',
	];
	$groupLevels['board']['restrict'] = [
		'poll_view',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'report_any',
	];

	// Standard - ie. members.  They can do anything Restrictive can.
	$groupLevels['global']['standard'] = array_merge($groupLevels['global']['restrict'], [
		'view_mlist',
		'likes_view',
		'likes_like',
		'mention',
		'pm_read',
		'pm_send',
		'profile_view',
		'profile_extra_own',
		'profile_signature_own',
		'profile_forum_own',
		'profile_website_own',
		'profile_password_own',
		'profile_displayed_name',
		'profile_upload_avatar',
		'profile_remote_avatar',
		'profile_remove_own',
		'report_user',
	]);
	$groupLevels['board']['standard'] = array_merge($groupLevels['board']['restrict'], [
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'post_attachment',
		'lock_own',
		'remove_own',
		'view_attachments',
	]);

	// Moderator - ie. moderators :P.  They can do what standard can, and more.
	$groupLevels['global']['moderator'] = array_merge($groupLevels['global']['standard'], [
		'access_mod_center',
		'issue_warning',
	]);
	$groupLevels['board']['moderator'] = array_merge($groupLevels['board']['standard'], [
		'make_sticky',
		'poll_edit_any',
		'delete_any',
		'modify_any',
		'lock_any',
		'remove_any',
		'move_any',
		'merge_any',
		'split_any',
		'poll_lock_any',
		'poll_remove_any',
		'poll_add_any',
		'approve_posts',
	]);

	// Maintenance - wannabe admins.  They can do almost everything.
	$groupLevels['global']['maintenance'] = array_merge($groupLevels['global']['moderator'], [
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'moderate_forum',
		'manage_membergroups',
		'manage_bans',
		'admin_forum',
		'manage_permissions',
		'edit_news',
		'profile_identity_any',
		'profile_extra_any',
		'profile_signature_any',
		'profile_website_any',
		'profile_displayed_name_any',
		'profile_password_any',
	]);
	$groupLevels['board']['maintenance'] = array_merge($groupLevels['board']['moderator'], [
	]);

	// Standard - nothing above the group permissions. (this SHOULD be empty.)
	$boardLevels['standard'] = [
	];

	// Locked - just that, you can't post here.
	$boardLevels['locked'] = [
		'poll_view',
		'report_any',
		'view_attachments',
	];

	// Publisher - just a little more...
	$boardLevels['publish'] = array_merge($boardLevels['locked'], [
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'delete_replies',
		'modify_replies',
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'poll_remove_own',
		'post_attachment',
		'lock_own',
		'remove_own',
	]);

	// Free for All - Scary.  Just scary.
	$boardLevels['free'] = array_merge($boardLevels['publish'], [
		'poll_lock_any',
		'poll_edit_any',
		'poll_add_any',
		'poll_remove_any',
		'make_sticky',
		'lock_any',
		'remove_any',
		'delete_any',
		'split_any',
		'merge_any',
		'modify_any',
		'approve_posts',
	]);

	call_integration_hook('integrate_load_permission_levels', [&$groupLevels, &$boardLevels]);

	// Make sure we're not granting someone too many permissions!
	foreach ($groupLevels['global'][$level] as $k => $permission)
	{
		if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
			unset($groupLevels['global'][$level][$k]);

		if ($group == -1 && in_array($permission, $context['non_guest_permissions']))
			unset($groupLevels['global'][$level][$k]);
	}
	if ($group == -1)
		foreach ($groupLevels['board'][$level] as $k => $permission)
			if (in_array($permission, $context['non_guest_permissions']))
				unset($groupLevels['board'][$level][$k]);

	// Reset all cached permissions.
	updateSettings(['settings_updated' => time()]);

	// Setting group permissions.
	if ($profile === 'null' && $group !== 'null')
	{
		$group = (int) $group;

		if (empty($groupLevels['global'][$level]))
			return;

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			[
				'current_group' => $group,
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : [],
			]
		);
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:default_profile}',
			[
				'current_group' => $group,
				'default_profile' => 1,
			]
		);

		$groupInserts = [];
		foreach ($groupLevels['global'][$level] as $permission)
			$groupInserts[] = [$group, $permission];

		$smcFunc['db']->insert('insert',
			'{db_prefix}permissions',
			['id_group' => 'int', 'permission' => 'string'],
			$groupInserts,
			['id_group']
		);

		$boardInserts = [];
		foreach ($groupLevels['board'][$level] as $permission)
			$boardInserts[] = [1, $group, $permission];

		$smcFunc['db']->insert('insert',
			'{db_prefix}board_permissions',
			['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
			$boardInserts,
			['id_profile', 'id_group']
		);
	}
	// Setting profile permissions for a specific group.
	elseif ($profile !== 'null' && $group !== 'null' && ($profile == 1 || $profile > 4))
	{
		$group = (int) $group;
		$profile = (int) $profile;

		if (!empty($groupLevels['global'][$level]))
		{
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group = {int:current_group}
					AND id_profile = {int:current_profile}',
				[
					'current_group' => $group,
					'current_profile' => $profile,
				]
			);
		}

		if (!empty($groupLevels['board'][$level]))
		{
			$boardInserts = [];
			foreach ($groupLevels['board'][$level] as $permission)
				$boardInserts[] = [$profile, $group, $permission];

			$smcFunc['db']->insert('insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
				$boardInserts,
				['id_profile', 'id_group']
			);
		}
	}
	// Setting profile permissions for all groups.
	elseif ($profile !== 'null' && $group === 'null' && ($profile == 1 || $profile > 4))
	{
		$profile = (int) $profile;

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}',
			[
				'current_profile' => $profile,
			]
		);

		if (empty($boardLevels[$level]))
			return;

		// Get all the groups...
		$query = $smcFunc['db']->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group NOT IN ({int:admin_group}, {int:moderator_group})
			ORDER BY group_name',
			[
				'admin_group' => 1,
				'moderator_group' => 3,
			]
		);
		while ($row = $smcFunc['db']->fetch_row($query))
		{
			$group = $row[0];

			$boardInserts = [];
			foreach ($boardLevels[$level] as $permission)
				$boardInserts[] = [$profile, $group, $permission];

			$smcFunc['db']->insert('insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
				$boardInserts,
				['id_profile', 'id_group']
			);
		}
		$smcFunc['db']->free_result($query);

		// Add permissions for ungrouped members.
		$boardInserts = [];
		foreach ($boardLevels[$level] as $permission)
			$boardInserts[] = [$profile, 0, $permission];

		$smcFunc['db']->insert('insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
				$boardInserts,
				['id_profile', 'id_group']
			);
	}
	// $profile and $group are both null!
	else
		fatal_lang_error('no_access', false);
}

/**
 * Load permissions into $context['permissions'].
 * @internal
 */
function loadAllPermissions()
{
	global $context, $txt, $modSettings;

	// List of all the groups dependant on the currently selected view - for the order so it looks pretty, yea?
	// Note to Mod authors - you don't need to stick your permission group here if you don't mind StoryBB sticking it the last group of the page.
	$permissionGroups = [
		'membergroup' => [
			'general',
			'pm',
			'maintenance',
			'member_admin',
			'profile',
			'likes',
			'mentions',
		],
		'board' => [
			'general_board',
			'topic',
			'post',
			'poll',
			'notification',
			'attachment',
		],
	];

	/*   The format of this list is as follows:
		'membergroup' => array(
			'permissions_inside' => array(has_multiple_options, view_group),
		),
		'board' => array(
			'permissions_inside' => array(has_multiple_options, view_group),
		);
	*/
	$permissionList = [
		'membergroup' => [
			'view_stats' => [false, 'general'],
			'view_mlist' => [false, 'general'],
			'who_view' => [false, 'general'],
			'search_posts' => [false, 'general'],
			'pm_read' => [false, 'pm'],
			'pm_send' => [false, 'pm'],
			'pm_draft' => [false, 'pm'],
			'admin_forum' => [false, 'maintenance'],
			'manage_boards' => [false, 'maintenance'],
			'manage_attachments' => [false, 'maintenance'],
			'manage_smileys' => [false, 'maintenance'],
			'edit_news' => [false, 'maintenance'],
			'access_mod_center' => [false, 'maintenance'],
			'moderate_forum' => [false, 'member_admin'],
			'manage_membergroups' => [false, 'member_admin'],
			'manage_permissions' => [false, 'member_admin'],
			'manage_bans' => [false, 'member_admin'],
			'send_mail' => [false, 'member_admin'],
			'issue_warning' => [false, 'member_admin'],
			'profile_view' => [false, 'profile'],
			'profile_forum' => [true, 'profile'],
			'profile_extra' => [true, 'profile'],
			'profile_signature' => [true, 'profile'],
			'profile_website' => [true, 'profile'],
			'profile_upload_avatar' => [false, 'profile'],
			'profile_remote_avatar' => [false, 'profile'],
			'report_user' => [false, 'profile'],
			'profile_identity' => [true, 'profile_account'],
			'profile_displayed_name' => [true, 'profile_account'],
			'profile_password' => [true, 'profile_account'],
			'profile_remove' => [true, 'profile_account'],
			'view_warning' => [true, 'profile_account'],
			'likes_view' => [false, 'likes'],
			'likes_like' => [false, 'likes'],
			'mention' => [false, 'mentions'],
		],
		'board' => [
			'moderate_board' => [false, 'general_board'],
			'approve_posts' => [false, 'general_board'],
			'post_new' => [false, 'topic'],
			'post_unapproved_topics' => [false, 'topic'],
			'post_unapproved_replies' => [true, 'topic'],
			'post_reply' => [true, 'topic'],
			'post_draft' => [false, 'topic'],
			'merge_any' => [false, 'topic'],
			'split_any' => [false, 'topic'],
			'make_sticky' => [false, 'topic'],
			'move' => [true, 'topic', 'moderate'],
			'lock' => [true, 'topic', 'moderate'],
			'remove' => [true, 'topic', 'modify'],
			'modify_replies' => [false, 'topic'],
			'delete_replies' => [false, 'topic'],
			'announce_topic' => [false, 'topic'],
			'delete' => [true, 'post'],
			'modify' => [true, 'post'],
			'report_any' => [false, 'post'],
			'poll_view' => [false, 'poll'],
			'poll_vote' => [false, 'poll'],
			'poll_post' => [false, 'poll'],
			'poll_add' => [true, 'poll'],
			'poll_edit' => [true, 'poll'],
			'poll_lock' => [true, 'poll'],
			'poll_remove' => [true, 'poll'],
			'view_attachments' => [false, 'attachment'],
			'post_unapproved_attachments' => [false, 'attachment'],
			'post_attachment' => [false, 'attachment'],
		],
	];

	// All permission groups that will be shown in the left column on classic view.
	$leftPermissionGroups = [
		'general',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	];

	// We need to know what permissions we can't give to guests.
	loadIllegalGuestPermissions();

	// Some permissions are hidden if features are off.
	$hiddenPermissions = [];
	$relabelPermissions = []; // Permissions to apply a different label to.

	if ($modSettings['warning_settings'][0] == 0)
	{
		$hiddenPermissions[] = 'issue_warning';
		$hiddenPermissions[] = 'view_warning';
	}

	// Post moderation
	$relabelPermissions['post_new'] = 'auto_approve_topics';
	$relabelPermissions['post_reply'] = 'auto_approve_replies';
	$relabelPermissions['post_attachment'] = 'auto_approve_attachments';

	// Are attachments enabled?
	if (empty($modSettings['attachmentEnable']))
	{
		$hiddenPermissions[] = 'manage_attachments';
		$hiddenPermissions[] = 'view_attachments';
		$hiddenPermissions[] = 'post_unapproved_attachments';
		$hiddenPermissions[] = 'post_attachment';
	}
	elseif ($modSettings['attachmentEnable'] == 2)
	{
		$hiddenPermissions[] = 'post_unapproved_attachments';
		$hiddenPermissions[] = 'post_attachment';
	}

	// Hide Likes/Mentions permissions...
	if (empty($modSettings['enable_likes']))
	{
		$hiddenPermissions[] = 'likes_view';
		$hiddenPermissions[] = 'likes_like';
	}
	if (empty($modSettings['enable_mentions']))
	{
		$hiddenPermissions[] = 'mention';
	}

	// Provide a practical way to modify permissions.
	call_integration_hook('integrate_load_permissions', [&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions]);

	$context['permissions'] = [];
	$context['hidden_permissions'] = [];
	foreach ($permissionList as $permissionType => $permissionList)
	{
		$context['permissions'][$permissionType] = [
			'id' => $permissionType,
			'columns' => []
		];
		foreach ($permissionList as $permission => $permissionArray)
		{
			// If this is a guest permission we don't do it if it's the guest group.
			if (isset($context['group']['id']) && $context['group']['id'] == -1 && in_array($permission, $context['non_guest_permissions']))
				continue;

			// What groups will this permission be in?
			$own_group = $permissionArray[1];

			// First, Do these groups actually exist - if not add them.
			if (!isset($permissionGroups[$permissionType][$own_group]))
				$permissionGroups[$permissionType][$own_group] = true;

			// What column should this be located into?
			$position = !in_array($own_group, $leftPermissionGroups) ? 1 : 0;

			// If the groups have not yet been created be sure to create them.
			$bothGroups = ['own' => $own_group];

			foreach ($bothGroups as $group)
				if (!isset($context['permissions'][$permissionType]['columns'][$position][$group]))
					$context['permissions'][$permissionType]['columns'][$position][$group] = [
						'type' => $permissionType,
						'id' => $group,
						'name' => $txt['permissiongroup_' . $group],
						'icon' => isset($txt['permissionicon_' . $group]) ? $txt['permissionicon_' . $group] : $txt['permissionicon'],
						'help' => isset($txt['permissionhelp_' . $group]) ? $txt['permissionhelp_' . $group] : '',
						'hidden' => false,
						'permissions' => []
					];

			$context['permissions'][$permissionType]['columns'][$position][$own_group]['permissions'][$permission] = [
				'id' => $permission,
				'name' => !isset($relabelPermissions[$permission]) ? $txt['permissionname_' . $permission] : $txt[$relabelPermissions[$permission]],
				'show_help' => isset($txt['permissionhelp_' . $permission]),
				'note' => isset($txt['permissionnote_' . $permission]) ? $txt['permissionnote_' . $permission] : '',
				'has_own_any' => $permissionArray[0],
				'own' => [
					'id' => $permission . '_own',
					'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_own'] : ''
				],
				'any' => [
					'id' => $permission . '_any',
					'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_any'] : ''
				],
				'hidden' => in_array($permission, $hiddenPermissions),
			];

			if (in_array($permission, $hiddenPermissions))
			{
				if ($permissionArray[0])
				{
					$context['hidden_permissions'][] = $permission . '_own';
					$context['hidden_permissions'][] = $permission . '_any';
				}
				else
					$context['hidden_permissions'][] = $permission;
			}
		}
		ksort($context['permissions'][$permissionType]['columns']);

		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach ($context['permissions'][$permissionType]['columns'] as $column => $groups)
			foreach ($groups as $id => $group)
			{
				if (empty($group['permissions']))
					unset($context['permissions'][$permissionType]['columns'][$column][$id]);
				else
				{
					$foundNonHidden = false;
					foreach ($group['permissions'] as $permission)
						if (empty($permission['hidden']))
							$foundNonHidden = true;
					if (!$foundNonHidden)
						$context['permissions'][$permissionType]['columns'][$column][$id]['hidden'] = true;
				}
			}
	}
}

/**
 * Initialize a form with inline permissions settings.
 * It loads a context variables for each permission.
 * This function is used by several settings screens to set specific permissions.
 * @internal
 *
 * @param array $permissions The permissions to display inline
 * @param array $excluded_groups The IDs of one or more groups to exclude
 *
 * @uses ManagePermissions language
 * @uses ManagePermissions template.
 */
function init_inline_permissions($permissions, $excluded_groups = [])
{
	global $context, $txt, $modSettings, $smcFunc;

	loadLanguage('ManagePermissions');
	$context['can_change_permissions'] = allowedTo('manage_permissions');

	// Nothing to initialize here.
	if (!$context['can_change_permissions'])
		return;

	// Load the permission settings for guests
	foreach ($permissions as $permission)
		$context[$permission] = [
			-1 => [
				'id' => -1,
				'name' => $txt['membergroups_guests'],
				'status' => 'off',
			],
			0 => [
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'status' => 'off',
			],
		];

	$request = $smcFunc['db']->query('', '
		SELECT id_group, CASE WHEN add_deny = {int:denied} THEN {string:deny} ELSE {string:on} END AS status, permission
		FROM {db_prefix}permissions
		WHERE id_group IN (-1, 0)
			AND permission IN ({array_string:permissions})',
		[
			'denied' => 0,
			'permissions' => $permissions,
			'deny' => 'deny',
			'on' => 'on',
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$context[$row['permission']][$row['id_group']]['status'] = $row['status'];
	$smcFunc['db']->free_result($request);

	$request = $smcFunc['db']->query('', '
		SELECT mg.id_group, mg.group_name, COALESCE(p.add_deny, -1) AS status, p.permission
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission IN ({array_string:permissions}))
		WHERE mg.id_group NOT IN (1, 3)
			AND mg.id_parent = {int:not_inherited}
		ORDER BY mg.group_name',
		[
			'not_inherited' => -2,
			'permissions' => $permissions,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Initialize each permission as being 'off' until proven otherwise.
		foreach ($permissions as $permission)
			if (!isset($context[$permission][$row['id_group']]))
				$context[$permission][$row['id_group']] = [
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'status' => 'off',
				];

		$context[$row['permission']][$row['id_group']]['status'] = empty($row['status']) ? 'deny' : ($row['status'] == 1 ? 'on' : 'off');
	}
	$smcFunc['db']->free_result($request);

	// Make sure we honor the "illegal guest permissions"
	loadIllegalGuestPermissions();

	// Some permissions cannot be given to certain groups. Remove the groups.
	foreach ($excluded_groups as $group)
	{
		foreach ($permissions as $permission)
		{
			if (isset($context[$permission][$group]))
				unset($context[$permission][$group]);
		}
	}

	// Are any of these permissions that guests can't have?
	$non_guest_perms = array_intersect(str_replace(['_any', '_own'], '', $permissions), $context['non_guest_permissions']);
	foreach ($non_guest_perms as $permission)
	{
		if (isset($context[$permission][-1]))
			unset($context[$permission][-1]);
	}

	// Create the token for the separate inline permission verification.
	createToken('admin-mp');
}

/**
 * Save the permissions of a form containing inline permissions.
 * @internal
 *
 * @param array $permissions The permissions to save
 */
function save_inline_permissions($permissions)
{
	global $context, $smcFunc;

	// No permissions? Not a great deal to do here.
	if (!allowedTo('manage_permissions'))
		return;

	// Almighty session check, verify our ways.
	checkSession();
	validateToken('admin-mp');

	// Check they can't do certain things.
	loadIllegalPermissions();

	$insertRows = [];
	foreach ($permissions as $permission)
	{
		if (!isset($_POST[$permission]))
			continue;

		foreach ($_POST[$permission] as $id_group => $value)
		{
			if (in_array($value, ['on', 'deny']) && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
				$insertRows[] = [(int) $id_group, $permission, $value == 'on' ? 1 : 0];
		}
	}

	// Remove the old permissions...
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE permission IN ({array_string:permissions})
		' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		[
			'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : [],
			'permissions' => $permissions,
		]
	);

	// ...and replace them with new ones.
	if (!empty($insertRows))
		$smcFunc['db']->insert('insert',
			'{db_prefix}permissions',
			['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
			$insertRows,
			['id_group', 'permission']
		);

	// Do a full child update.
	updateChildPermissions([], -1);

	// Just in case we cached this.
	updateSettings(['settings_updated' => time()]);
}

/**
 * Load permissions profiles.
 */
function loadPermissionProfiles()
{
	global $context, $txt, $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT id_profile, profile_name
		FROM {db_prefix}permission_profiles
		ORDER BY id_profile',
		[
		]
	);
	$context['profiles'] = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		// Format the label nicely.
		if (isset($txt['permissions_profile_' . $row['profile_name']]))
			$name = $txt['permissions_profile_' . $row['profile_name']];
		else
			$name = $row['profile_name'];

		$context['profiles'][$row['id_profile']] = [
			'id' => $row['id_profile'],
			'name' => $name,
			'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
			'unformatted_name' => $row['profile_name'],
		];
	}
	$smcFunc['db']->free_result($request);
}

/**
 * Add/Edit/Delete profiles.
 */
function EditPermissionProfiles()
{
	global $context, $txt, $smcFunc;

	// Setup the template, first for fun.
	$context['page_title'] = $txt['permissions_profile_edit'];
	$context['sub_template'] = 'admin_permissions_profile_edit';

	// If we're creating a new one do it first.
	if (isset($_POST['create']) && trim($_POST['profile_name']) != '')
	{
		checkSession();
		validateToken('admin-mpp');

		$_POST['copy_from'] = (int) $_POST['copy_from'];
		$_POST['profile_name'] = StringLibrary::escape($_POST['profile_name']);

		// Insert the profile itself.
		$profile_id = $smcFunc['db']->insert('',
			'{db_prefix}permission_profiles',
			[
				'profile_name' => 'string',
			],
			[
				$_POST['profile_name'],
			],
			['id_profile'],
			1
		);

		// Load the permissions from the one it's being copied from.
		$request = $smcFunc['db']->query('', '
			SELECT id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:copy_from}',
			[
				'copy_from' => $_POST['copy_from'],
			]
		);
		$inserts = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$inserts[] = [$profile_id, $row['id_group'], $row['permission'], $row['add_deny']];
		$smcFunc['db']->free_result($request);

		if (!empty($inserts))
			$smcFunc['db']->insert('insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$inserts,
				['id_profile', 'id_group', 'permission']
			);
	}
	// Renaming?
	elseif (isset($_POST['rename']))
	{
		checkSession();
		validateToken('admin-mpp');

		// Just showing the boxes?
		if (!isset($_POST['rename_profile']))
			$context['show_rename_boxes'] = true;
		else
		{
			foreach ($_POST['rename_profile'] as $id => $value)
			{
				$value = StringLibrary::escape($value);

				if (trim($value) != '' && $id > 4)
					$smcFunc['db']->query('', '
						UPDATE {db_prefix}permission_profiles
						SET profile_name = {string:profile_name}
						WHERE id_profile = {int:current_profile}',
						[
							'current_profile' => (int) $id,
							'profile_name' => $value,
						]
					);
			}
		}
	}
	// Deleting?
	elseif (isset($_POST['delete']) && !empty($_POST['delete_profile']))
	{
		checkSession();
		validateToken('admin-mpp');

		$profiles = [];
		foreach ($_POST['delete_profile'] as $profile)
			if ($profile > 4)
				$profiles[] = (int) $profile;

		// Verify it's not in use...
		$request = $smcFunc['db']->query('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_profile IN ({array_int:profile_list})
			LIMIT 1',
			[
				'profile_list' => $profiles,
			]
		);
		if ($smcFunc['db']->num_rows($request) != 0)
			fatal_lang_error('no_access', false);
		$smcFunc['db']->free_result($request);

		// Oh well, delete.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}permission_profiles
			WHERE id_profile IN ({array_int:profile_list})',
			[
				'profile_list' => $profiles,
			]
		);
	}

	// Clearly, we'll need this!
	loadPermissionProfiles();

	// Work out what ones are in use.
	$request = $smcFunc['db']->query('', '
		SELECT id_profile, COUNT(id_board) AS board_count
		FROM {db_prefix}boards
		GROUP BY id_profile',
		[
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
		if (isset($context['profiles'][$row['id_profile']]))
		{
			$context['profiles'][$row['id_profile']]['in_use'] = true;
			$context['profiles'][$row['id_profile']]['boards'] = $row['board_count'];
			$context['profiles'][$row['id_profile']]['boards_text'] = $row['board_count'] > 1 ? sprintf($txt['permissions_profile_used_by_many'], $row['board_count']) : $txt['permissions_profile_used_by_' . ($row['board_count'] ? 'one' : 'none')];
		}
	$smcFunc['db']->free_result($request);

	// What can we do with these?
	$context['can_edit_something'] = false;
	foreach ($context['profiles'] as $id => $profile)
	{
		// Can't delete special ones.
		$context['profiles'][$id]['can_edit'] = isset($txt['permissions_profile_' . $profile['unformatted_name']]) ? false : true;
		if ($context['profiles'][$id]['can_edit'])
			$context['can_edit_something'] = true;

		// You can only delete it if you can edit it AND it's not in use.
		$context['profiles'][$id]['can_delete'] = $context['profiles'][$id]['can_edit'] && empty($profile['in_use']) ? true : false;
	}

	createToken('admin-mpp');
}

/**
 * This function updates the permissions of any groups based off this group.
 *
 * @param null|array $parents The parent groups
 * @param null|int $profile the ID of a permissions profile to update
 * @return void|false Returns nothing if successful or false if there are no child groups to update
 */
function updateChildPermissions($parents, $profile = null)
{
	global $smcFunc;

	// All the parent groups to sort out.
	if (!is_array($parents))
		$parents = [$parents];

	// Find all the children of this group.
	$request = $smcFunc['db']->query('', '
		SELECT id_parent, id_group
		FROM {db_prefix}membergroups
		WHERE id_parent != {int:not_inherited}
			' . (empty($parents) ? '' : 'AND id_parent IN ({array_int:parent_list})'),
		[
			'parent_list' => $parents,
			'not_inherited' => -2,
		]
	);
	$children = [];
	$parents = [];
	$child_groups = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$children[$row['id_parent']][] = $row['id_group'];
		$child_groups[] = $row['id_group'];
		$parents[] = $row['id_parent'];
	}
	$smcFunc['db']->free_result($request);

	$parents = array_unique($parents);

	// Not a sausage, or a child?
	if (empty($children))
		return false;

	// First off, are we doing general permissions?
	if ($profile < 1 || $profile === null)
	{
		// Fetch all the parent permissions.
		$request = $smcFunc['db']->query('', '
			SELECT id_group, permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:parent_list})',
			[
				'parent_list' => $parents,
			]
		);
		$permissions = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = [$child, $row['permission'], $row['add_deny']];
		$smcFunc['db']->free_result($request);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:child_groups})',
			[
				'child_groups' => $child_groups,
			]
		);

		// Finally insert.
		if (!empty($permissions))
		{
			$smcFunc['db']->insert('insert',
				'{db_prefix}permissions',
				['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$permissions,
				['id_group', 'permission']
			);
		}
	}

	// Then, what about board profiles?
	if ($profile != -1)
	{
		$profileQuery = $profile === null ? '' : ' AND id_profile = {int:current_profile}';

		// Again, get all the parent permissions.
		$request = $smcFunc['db']->query('', '
			SELECT id_profile, id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:parent_groups})
				' . $profileQuery,
			[
				'parent_groups' => $parents,
				'current_profile' => $profile !== null && $profile ? $profile : 1,
			]
		);
		$permissions = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = [$child, $row['id_profile'], $row['permission'], $row['add_deny']];
		$smcFunc['db']->free_result($request);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:child_groups})
				' . $profileQuery,
			[
				'child_groups' => $child_groups,
				'current_profile' => $profile !== null && $profile ? $profile : 1,
			]
		);

		// Do the insert.
		if (!empty($permissions))
		{
			$smcFunc['db']->insert('insert',
				'{db_prefix}board_permissions',
				['id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
				$permissions,
				['id_group', 'id_profile', 'permission']
			);
		}
	}
}

/**
 * Load permissions someone cannot grant.
 */
function loadIllegalPermissions()
{
	global $context;

	$context['illegal_permissions'] = [];
	if (!allowedTo('admin_forum'))
		$context['illegal_permissions'][] = 'admin_forum';
	if (!allowedTo('manage_membergroups'))
		$context['illegal_permissions'][] = 'manage_membergroups';
	if (!allowedTo('manage_permissions'))
		$context['illegal_permissions'][] = 'manage_permissions';

	call_integration_hook('integrate_load_illegal_permissions');
}

/**
 * Loads the permissions that can not be given to guests.
 * Stores the permissions in $context['non_guest_permissions'].
*/
function loadIllegalGuestPermissions()
{
	global $context;

	$context['non_guest_permissions'] = [
		'access_mod_center',
		'admin_forum',
		'announce_topic',
		'approve_posts',
		'delete',
		'delete_replies',
		'edit_news',
		'issue_warning',
		'likes_like',
		'lock',
		'make_sticky',
		'manage_attachments',
		'manage_bans',
		'manage_boards',
		'manage_membergroups',
		'manage_permissions',
		'manage_smileys',
		'merge_any',
		'moderate_board',
		'moderate_forum',
		'modify',
		'modify_replies',
		'move',
		'pm_autosave_draft',
		'pm_draft',
		'pm_read',
		'pm_send',
		'poll_add',
		'poll_edit',
		'poll_lock',
		'poll_remove',
		'post_autosave_draft',
		'post_draft',
		'profile_displayed_name',
		'profile_extra',
		'profile_forum',
		'profile_identity',
		'profile_website',
		'profile_password',
		'profile_remove',
		'profile_remote_avatar',
		'profile_signature',
		'profile_upload_avatar',
		'profile_warning',
		'remove',
		'report_any',
		'report_user',
		'send_mail',
		'split_any',
	];

	call_integration_hook('integrate_load_illegal_guest_permissions');
}
