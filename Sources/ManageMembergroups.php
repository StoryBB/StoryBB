<?php

/**
 * This file is concerned with anything in the Manage Membergroups admin screen.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Autocomplete;

/**
 * Main dispatcher, the entrance point for all 'Manage Membergroup' actions.
 * It forwards to a function based on the given subaction, default being subaction 'index', or, without manage_membergroup
 * permissions, then 'settings'.
 * Called by ?action=admin;area=membergroups.
 * Requires the manage_membergroups or the admin_forum permission.
 *
 * @uses ManageMembergroups template.
 * @uses ManageMembers language file.
*/
function ModifyMembergroups()
{
	global $context, $txt, $sourcedir;

	$subActions = array(
		'add' => array('AddMembergroup', 'manage_membergroups'),
		'delete' => array('DeleteMembergroup', 'manage_membergroups'),
		'edit' => array('EditMembergroup', 'manage_membergroups'),
		'index' => array('MembergroupIndex', 'manage_membergroups'),
		'members' => array('MembergroupMembers', 'manage_membergroups', 'Groups.php'),
		'badges' => array('MembergroupBadges', 'admin_forum'),
	);

	// Default to sub action 'index' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_membergroups') ? 'index' : 'settings');

	// Is it elsewhere?
	if (isset($subActions[$_REQUEST['sa']][2]))
		require_once($sourcedir . '/' . $subActions[$_REQUEST['sa']][2]);

	// Do the permission check, you might not be allowed her.
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Language and template stuff, the usual.
	loadLanguage('ManageMembers');

	// Setup the admin tabs.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['membergroups_title'],
		'help' => 'membergroups',
		'description' => $txt['membergroups_description'],
	);

	call_integration_hook('integrate_manage_membergroups', array(&$subActions));

	// Call the right function.
	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Shows an overview of the current membergroups.
 * Called by ?action=admin;area=membergroups.
 * Requires the manage_membergroups permission.
 * Splits the membergroups in account and character based groups.
 * It also counts the number of members part of each membergroup.
 *
 * @uses ManageMembergroups template, main.
 */
function MembergroupIndex()
{
	global $txt, $scripturl, $context, $sourcedir;

	$context['page_title'] = $txt['membergroups_title'];

	// The first list shows the regular membergroups.
	$listOptions = array(
		'id' => 'regular_membergroups_list',
		'title' => $txt['membergroups_regular'],
		'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : '') . (isset($_REQUEST['sort3']) ? ';sort3=' . urlencode($_REQUEST['sort3']) : ''),
		'default_sort_col' => 'name',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Membergroups.php',
			'function' => 'list_getMembergroups',
			'params' => array(
				'regular',
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['membergroups_name'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $context, $txt)
					{
						static $template, $phpStr;
						if ($template === null)
						{
							$template = StoryBB\Template::load_partial('helpicon');
							$phpStr = StoryBB\Template::compile($template, [], 'partial-helpicon');
						}

						// Since the moderator group has no explicit members, no link is needed.
						if ($rowData['id_group'] == 3)
							$group_name = $rowData['group_name'];
						else
						{
							$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);
							$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', $scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
						}

						// Add a help option for moderator and administrator.
						if ($rowData['id_group'] == 1)
						{
							$group_name .= ' ' . StoryBB\Template::prepare($phpStr, [
								'help' => 'membergroup_administrator',
								'scripturl' => $scripturl,
								'txt' => $txt,
							]);
						}
						elseif ($rowData['id_group'] == 3)
						{
							$group_name .= ' ' . StoryBB\Template::prepare($phpStr, [
								'help' => 'membergroup_moderator',
								'scripturl' => $scripturl,
								'txt' => $txt,
							]);
						}

						return $group_name;
					},
				),
				'sort' => array(
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
				),
			),
			'icons' => array(
				'header' => array(
					'value' => $txt['membergroups_icons'],
				),
				'data' => array(
					'db' => 'icons',
				),
				'sort' => array(
					'default' => 'mg.icons',
					'reverse' => 'mg.icons DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						// No explicit members for the moderator group.
						return $rowData['id_group'] == 3 ? $txt['membergroups_guests_na'] : comma_format($rowData['num_members']);
					},
					'class' => 'centercol',
				),
				'sort' => array(
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'above_table_headers',
				'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// The second list shows the character membergroups.
	$listOptions = array(
		'id' => 'character_membergroups_list',
		'title' => $txt['membergroups_character'],
		'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort']) ? ';sort=' . urlencode($_REQUEST['sort']) : '') . (isset($_REQUEST['sort3']) ? ';sort3=' . urlencode($_REQUEST['sort3']) : ''),
		'default_sort_col' => 'name',
		'no_items_label' => $txt['no_character_groups'],
		'request_vars' => array(
			'sort' => 'sort2',
			'desc' => 'desc2',
		),
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Membergroups.php',
			'function' => 'list_getMembergroups',
			'params' => array(
				'character',
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['membergroups_name'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl)
					{
						// Since the moderator group has no explicit members, no link is needed.
						if ($rowData['id_group'] == 3)
							$group_name = $rowData['group_name'];
						else
						{
							$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);
							$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', $scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
						}

						return $group_name;
					},
				),
				'sort' => array(
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
				),
			),
			'icons' => array(
				'header' => array(
					'value' => $txt['membergroups_icons'],
				),
				'data' => array(
					'db' => 'icons',
				),
				'sort' => array(
					'default' => 'mg.icons',
					'reverse' => 'mg.icons DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						// No explicit members for the moderator group.
						return $rowData['id_group'] == 3 ? $txt['membergroups_guests_na'] : comma_format($rowData['num_members']);
					},
					'class' => 'centercol',
				),
				'sort' => array(
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'above_table_headers',
				'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;charactergroup">' . $txt['membergroups_add_group'] . '</a>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;charactergroup">' . $txt['membergroups_add_group'] . '</a>',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'membergroups_main';
}

/**
 * This function handles adding a membergroup and setting some initial properties.
 * Called by ?action=admin;area=membergroups;sa=add.
 * It requires the manage_membergroups permission.
 * Allows to use a predefined permission profile or copy one from another group.
 * Redirects to action=admin;area=membergroups;sa=edit;group=x.
 *
 * @uses the new_group sub template of ManageMembergroups.
 */
function AddMembergroup()
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc;

	// A form was submitted, we can start adding.
	if (isset($_POST['group_name']) && trim($_POST['group_name']) != '')
	{
		// Are we inheriting? Account groups can't inherit from character groups, and vice versa.
		if (!empty($_POST['perm_type']) && $_POST['perm_type'] == 'inherit')
		{
			$is_character = StoryBB\Model\Group::is_character_group(isset($_POST['inheritperm']) ? (int) $_POST['inheritperm'] : 0);

			if ($is_character && empty($_POST['group_level']))
			{
				fatal_lang_error('membergroup_cannot_inherit_character', false);
			}
			elseif (!$is_character && !empty($_POST['group_level']))
			{
				fatal_lang_error('membergroup_cannot_inherit_account', false);
			}
		}

		checkSession();
		validateToken('admin-mmg');

		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

		call_integration_hook('integrate_pre_add_membergroup', []);

		$id_group = $smcFunc['db_insert']('',
			'{db_prefix}membergroups',
			array(
				'description' => 'string', 'group_name' => 'string-80',
				'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int', 'is_character' => 'int',
			),
			array(
				'', $smcFunc['htmlspecialchars']($_POST['group_name'], ENT_QUOTES),
				'1#icon.png', '', $_POST['group_type'], !empty($_POST['group_level']) ? 1 : 0,
			),
			array('id_group'),
			1
		);

		call_integration_hook('integrate_add_membergroup', array($id_group, $postCountBasedGroup));

		// You cannot set permissions for post groups as they are disabled.
		if ($postCountBasedGroup)
			$_POST['perm_type'] = '';

		if ($_POST['perm_type'] == 'predefined')
		{
			// Set default permission level.
			require_once($sourcedir . '/ManagePermissions.php');
			setPermissionLevel($_POST['level'], $id_group, 'null');
		}
		// Copy or inherit the permissions!
		elseif ($_POST['perm_type'] == 'copy' || $_POST['perm_type'] == 'inherit')
		{
			$copy_id = $_POST['perm_type'] == 'copy' ? (int) $_POST['copyperm'] : (int) $_POST['inheritperm'];

			// Are you a powerful admin?
			if (!allowedTo('admin_forum'))
			{
				$request = $smcFunc['db_query']('', '
					SELECT group_type
					FROM {db_prefix}membergroups
					WHERE id_group = {int:copy_from}
					LIMIT {int:limit}',
					array(
						'copy_from' => $copy_id,
						'limit' => 1,
					)
				);
				list ($copy_type) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// Protected groups are... well, protected!
				if ($copy_type == 1)
					fatal_lang_error('membergroup_does_not_exist');
			}

			// Don't allow copying of a real priviledged person!
			require_once($sourcedir . '/ManagePermissions.php');
			loadIllegalPermissions();

			// Copy the main permissions - but only if it's not copying to a character group.
			if (empty($_POST['group_level']))
			{
				$request = $smcFunc['db_query']('', '
					SELECT permission, add_deny
					FROM {db_prefix}permissions
					WHERE id_group = {int:copy_from}',
					array(
						'copy_from' => $copy_id,
					)
				);
				$inserts = [];
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					if (empty($context['illegal_permissions']) || !in_array($row['permission'], $context['illegal_permissions']))
						$inserts[] = array($id_group, $row['permission'], $row['add_deny']);
				}
				$smcFunc['db_free_result']($request);

				if (!empty($inserts))
					$smcFunc['db_insert']('insert',
						'{db_prefix}permissions',
						array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
						$inserts,
						array('id_group', 'permission')
					);
			}

			$request = $smcFunc['db_query']('', '
				SELECT id_profile, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $copy_id,
				)
			);
			$inserts = [];
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$inserts[] = array($id_group, $row['id_profile'], $row['permission'], $row['add_deny']);
			$smcFunc['db_free_result']($request);

			if (!empty($inserts))
				$smcFunc['db_insert']('insert',
					'{db_prefix}board_permissions',
					array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$inserts,
					array('id_group', 'id_profile', 'permission')
				);

			// Also get some membergroup information if we're copying and not copying from guests...
			if ($copy_id > 0 && $_POST['perm_type'] == 'copy')
			{
				$request = $smcFunc['db_query']('', '
					SELECT online_color, max_messages, icons
					FROM {db_prefix}membergroups
					WHERE id_group = {int:copy_from}
					LIMIT 1',
					array(
						'copy_from' => $copy_id,
					)
				);
				$group_info = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);

				// ...and update the new membergroup with it.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}membergroups
					SET
						online_color = {string:online_color},
						max_messages = {int:max_messages},
						icons = {string:icons}
					WHERE id_group = {int:current_group}',
					array(
						'max_messages' => $group_info['max_messages'],
						'current_group' => $id_group,
						'online_color' => $group_info['online_color'],
						'icons' => $group_info['icons'],
					)
				);
			}
			// If inheriting say so...
			elseif ($_POST['perm_type'] == 'inherit')
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}membergroups
					SET id_parent = {int:copy_from}
					WHERE id_group = {int:current_group}',
					array(
						'copy_from' => $copy_id,
						'current_group' => $id_group,
					)
				);
			}
		}

		// Make sure all boards selected are stored in a proper array.
		$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? [] : $_POST['boardaccess'];
		$changed_boards['allow'] = [];
		$changed_boards['deny'] = [];
		$changed_boards['ignore'] = [];
		foreach ($accesses as $group_id => $action)
			$changed_boards[$action][] = (int) $group_id;

		foreach (array('allow', 'deny') as $board_action)
		{
			// Only do this if they have special access requirements.
			if (!empty($changed_boards[$board_action]))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
					WHERE id_board IN ({array_int:board_list})',
					array(
						'board_list' => $changed_boards[$board_action],
						'blank_string' => '',
						'group_id_string' => (string) $id_group,
						'comma_group' => ',' . $id_group,
						'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
					)
				);
		}

		// If this is joinable then set it to show group membership in people's profiles.
		if (empty($modSettings['show_group_membership']) && $_POST['group_type'] > 1)
			updateSettings(array('show_group_membership' => 1));

		// Rebuild the group cache.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// We did it.
		logAction('add_group', array('group' => $smcFunc['htmlspecialchars']($_POST['group_name'])), 'admin');

		// Go change some more settings.
		redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
	}

	// Just show the 'add membergroup' screen.
	$context['page_title'] = $txt['membergroups_new_group'];
	$context['sub_template'] = 'admin_membergroups_add';
	$context['character_group'] = isset($_REQUEST['charactergroup']) || !empty($_REQUEST['group_level']);
	$context['undefined_group'] = !isset($_REQUEST['generalgroup']) && !isset($_REQUEST['charactergroup']);
	$context['allow_protected'] = allowedTo('admin_forum');

	loadLanguage('ManagePermissions');

	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name, is_character
		FROM {db_prefix}membergroups
		WHERE (id_group NOT IN ({int:admin_group}, {int:moderator_group}))' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY group_name',
		array(
			'moderator_group' => 3,
			'admin_group' => 1,
			'is_protected' => 1,
		)
	);
	$context['groups'] = [];
	$context['character_groups'] = [];
	$context['account_groups'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['groups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
		$context[$row['is_character'] ? 'character_groups' : 'account_groups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	$smcFunc['db_free_result']($result);

	$request = $smcFunc['db_query']('', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		ORDER BY board_order',
		array(
		)
	);
	$context['num_boards'] = $smcFunc['db_num_rows']($request);

	$context['categories'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// This category hasn't been set up yet..
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => []
			);

		// Set this board up, and let the template know when it's a child.  (indent them..)
		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'allow' => false,
			'deny' => false
		);

	}
	$smcFunc['db_free_result']($request);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = [];
	foreach ($context['categories'] as $category)
	{
		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));

		// Include a list of boards per category for easy toggling.
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
	}

	createToken('admin-mmg');
}

/**
 * Deleting a membergroup by URL (not implemented).
 * Called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
 * Requires the manage_membergroups permission.
 * Redirects to ?action=admin;area=membergroups.
 *
 * @todo look at this
 */
function DeleteMembergroup()
{
	global $sourcedir;

	checkSession('get');

	require_once($sourcedir . '/Subs-Membergroups.php');
	$result = deleteMembergroups((int) $_REQUEST['group']);
	// Need to throw a warning if it went wrong, but this is the only one we have a message for...
	if ($result === 'group_cannot_delete_sub')
		fatal_lang_error('membergroups_cannot_delete_paid', false);

	// Go back to the membergroup index.
	redirectexit('action=admin;area=membergroups;');
}

/**
 * Editing a membergroup.
 * Screen to edit a specific membergroup.
 * Called by ?action=admin;area=membergroups;sa=edit;group=x.
 * It requires the manage_membergroups permission.
 * Also handles the delete button of the edit form.
 * Redirects to ?action=admin;area=membergroups.
 *
 * @uses the edit_group sub template of ManageMembergroups.
 */
function EditMembergroup()
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc, $settings;

	$_REQUEST['group'] = isset($_REQUEST['group']) && $_REQUEST['group'] > 0 ? (int) $_REQUEST['group'] : 0;

	loadLanguage('ManagePermissions');

	// Make sure this group is editable.
	if (!empty($_REQUEST['group']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}' . (allowedTo('admin_forum') ? '' : '
				AND group_type != {int:is_protected}') . '
			LIMIT {int:limit}',
			array(
				'current_group' => $_REQUEST['group'],
				'is_protected' => 1,
				'limit' => 1,
			)
		);
		list ($_REQUEST['group']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Now, do we have a valid id?
	if (empty($_REQUEST['group']))
		fatal_lang_error('membergroup_does_not_exist', false);

	// People who can manage boards are a bit special.
	require_once($sourcedir . '/Subs-Members.php');
	$board_managers = groupsAllowedTo('manage_boards', null);
	$context['can_manage_boards'] = in_array($_REQUEST['group'], $board_managers['allowed']);

	// Can this group moderate any boards?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_board)
		FROM {db_prefix}moderator_groups
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $_REQUEST['group'],
		)
	);

	// Why don't we have a $smcFunc['db_result'] function?
	$result = $smcFunc['db_fetch_row']($request);
	$context['is_moderator_group'] = ($result[0] > 0);
	$smcFunc['db_free_result']($request);

	// The delete this membergroup button was pressed.
	if (isset($_POST['delete']))
	{
		checkSession();
		validateToken('admin-mmg');

		require_once($sourcedir . '/Subs-Membergroups.php');
		$result = deleteMembergroups($_REQUEST['group']);
		// Need to throw a warning if it went wrong, but this is the only one we have a message for...
		if ($result === 'group_cannot_delete_sub')
			fatal_lang_error('membergroups_cannot_delete_paid', false);

		redirectexit('action=admin;area=membergroups;');
	}
	// A form was submitted with the new membergroup settings.
	elseif (isset($_POST['save']))
	{
		// Are we inheriting? Account groups can't inherit from character groups, and vice versa.
		$current_group_is_character = StoryBB\Model\Group::is_character_group((int) $_REQUEST['group']);
		if (isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2)
		{
			$is_character = StoryBB\Model\Group::is_character_group((int) $_POST['group_inherit']);

			if ($is_character && !$current_group_is_character)
			{
				fatal_lang_error('membergroup_cannot_inherit_character', false);
			}
			elseif (!$is_character && $current_group_is_character)
			{
				fatal_lang_error('membergroup_cannot_inherit_account', false);
			}
		}

		// Validate the session.
		checkSession();
		validateToken('admin-mmg');

		// Can they really inherit from this group?
		if ($_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
		{
			$request = $smcFunc['db_query']('', '
				SELECT group_type
				FROM {db_prefix}membergroups
				WHERE id_group = {int:inherit_from}
				LIMIT {int:limit}',
				array(
					'inherit_from' => $_POST['group_inherit'],
					'limit' => 1,
				)
			);
			list ($inherit_type) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Set variables to their proper value.
		$_POST['max_messages'] = isset($_POST['max_messages']) ? (int) $_POST['max_messages'] : 0;

		$_POST['icons'] = '';
		if (!empty($_POST['has_badge']) && !empty($_POST['icon_count']) && $_POST['icon_count'] > 0 && !empty($_POST['icon_image']))
		{
			$_POST['icons'] = min((int) $_POST['icon_count'], 99) . '#' . $_POST['icon_image'];
		}

		$_POST['group_desc'] = isset($_POST['group_desc']) && ($_REQUEST['group'] == 1 || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? trim($_POST['group_desc']) : '';
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];
		$_POST['group_hidden'] = empty($_POST['group_hidden']) || $_REQUEST['group'] == 3 ? 0 : (int) $_POST['group_hidden'];
		$_POST['group_inherit'] = $_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && (empty($inherit_type) || $inherit_type != 1) ? (int) $_POST['group_inherit'] : -2;
		$_POST['group_tfa_force'] = (empty($modSettings['tfa_mode']) || $modSettings['tfa_mode'] != 2 || empty($_POST['group_tfa_force'])) ? 0 : 1;

		//@todo Don't set online_color for the Moderators group?

		// Do the update of the membergroup settings.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}membergroups
			SET group_name = {string:group_name}, online_color = {string:online_color},
				max_messages = {int:max_messages}, icons = {string:icons},
				description = {string:group_desc}, group_type = {int:group_type}, hidden = {int:group_hidden},
				id_parent = {int:group_inherit}, tfa_required = {int:tfa_required}
			WHERE id_group = {int:current_group}',
			array(
				'max_messages' => $_POST['max_messages'],
				'group_type' => $_POST['group_type'],
				'group_hidden' => $_POST['group_hidden'],
				'group_inherit' => $_POST['group_inherit'],
				'current_group' => (int) $_REQUEST['group'],
				'group_name' => $smcFunc['htmlspecialchars']($_POST['group_name']),
				'online_color' => $_POST['online_color'],
				'icons' => $_POST['icons'],
				'group_desc' => $_POST['group_desc'],
				'tfa_required' => $_POST['group_tfa_force'],
			)
		);

		call_integration_hook('integrate_save_membergroup', array((int) $_REQUEST['group']));

		// Time to update the boards this membergroup has access to.
		if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
		{
			$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? [] : $_POST['boardaccess'];

			// If they can manage boards, the rules are a bit different. They can see everything.
			if ($context['can_manage_boards'])
			{
				$accesses = [];
				$request = $smcFunc['db_query']('', '
					SELECT id_board
					FROM {db_prefix}boards');
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$accesses[(int) $row['id_board']] = 'allow';
				$smcFunc['db_free_result']($request);
			}

			$changed_boards['allow'] = [];
			$changed_boards['deny'] = [];
			$changed_boards['ignore'] = [];
			foreach ($accesses as $group_id => $action)
				$changed_boards[$action][] = (int) $group_id;

			foreach (array('allow', 'deny') as $board_action)
			{
				// Find all board this group is in, but shouldn't be in.
				$request = $smcFunc['db_query']('', '
					SELECT id_board, {raw:column}
					FROM {db_prefix}boards
					WHERE FIND_IN_SET({string:current_group}, {raw:column}) != 0' . (empty($changed_boards[$board_action]) ? '' : '
						AND id_board NOT IN ({array_int:board_access_list})'),
					array(
						'current_group' => (int) $_REQUEST['group'],
						'board_access_list' => $changed_boards[$board_action],
						'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET {raw:column} = {string:member_group_access}
						WHERE id_board = {int:current_board}',
						array(
							'current_board' => $row['id_board'],
							'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($_REQUEST['group']))),
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						)
					);
				$smcFunc['db_free_result']($request);

				// Add the membergroup to all boards that hadn't been set yet.
				if (!empty($changed_boards[$board_action]))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
						WHERE id_board IN ({array_int:board_list})
							AND FIND_IN_SET({int:current_group}, {raw:column}) = 0',
						array(
							'board_list' => $changed_boards[$board_action],
							'blank_string' => '',
							'current_group' => (int) $_REQUEST['group'],
							'group_id_string' => (string) (int) $_REQUEST['group'],
							'comma_group' => ',' . $_REQUEST['group'],
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						)
					);
			}
		}

		if ($_REQUEST['group'] != 3)
		{
			// Making it a hidden group? If so remove everyone with it as primary group (Actually, just make them additional).
			if ($_POST['group_hidden'] == 2)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member, additional_groups
					FROM {db_prefix}members
					WHERE id_group = {int:current_group}
						AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
					array(
						'current_group' => (int) $_REQUEST['group'],
					)
				);
				$updates = [];
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$updates[$row['additional_groups']][] = $row['id_member'];
				$smcFunc['db_free_result']($request);

				foreach ($updates as $additional_groups => $memberArray)
				{
					$new_groups = (!empty($additional_groups) ? $additional_groups . ',' : '') . $_REQUEST['group']; // We already validated this a while ago.
					updateMemberData($memberArray, array('additional_groups' => $new_groups));
				}

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}members
					SET id_group = {int:regular_member}
					WHERE id_group = {int:current_group}',
					array(
						'regular_member' => 0,
						'current_group' => $_REQUEST['group'],
					)
				);

				// Hidden groups can't moderate boards
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}moderator_groups
					WHERE id_group = {int:current_group}',
					array(
						'current_group' => $_REQUEST['group'],
					)
				);
			}

			// Either way, let's check our "show group membership" setting is correct.
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}membergroups
				WHERE group_type > {int:non_joinable}',
				array(
					'non_joinable' => 1,
				)
			);
			list ($have_joinable) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Do we need to update the setting?
			if ((empty($modSettings['show_group_membership']) && $have_joinable) || (!empty($modSettings['show_group_membership']) && !$have_joinable))
				updateSettings(array('show_group_membership' => $have_joinable ? 1 : 0));
		}

		// Do we need to set inherited permissions?
		if ($_POST['group_inherit'] != -2 && $_POST['group_inherit'] != $_POST['old_inherit'])
		{
			require_once($sourcedir . '/ManagePermissions.php');
			updateChildPermissions($_POST['group_inherit']);
		}

		// Finally, moderators!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}group_moderators
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_REQUEST['group'],
			)
		);
		if (!empty($_POST['group_moderators']) && is_array($_POST['group_moderators']) && $_REQUEST['group'] != 3)
		{
			$group_moderators = [];

			$moderators = [];
			foreach ($_POST['group_moderators'] as $moderator)
			{
				$moderator = (int) $moderator;
				if (!empty($moderator))
				{
					$moderators[] = $moderator;
				}
			}

			if (!empty($moderators))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:moderators})
					LIMIT {int:num_moderators}',
					array(
						'moderators' => $moderators,
						'num_moderators' => count($moderators),
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$group_moderators[] = $row['id_member'];
				$smcFunc['db_free_result']($request);
			}

			// Make sure we don't have any duplicates first...
			$group_moderators = array_unique($group_moderators);

			// Found some?
			if (!empty($group_moderators))
			{
				$mod_insert = [];
				foreach ($group_moderators as $moderator)
					$mod_insert[] = array($_REQUEST['group'], $moderator);

				$smcFunc['db_insert']('insert',
					'{db_prefix}group_moderators',
					array('id_group' => 'int', 'id_member' => 'int'),
					$mod_insert,
					array('id_group', 'id_member')
				);
			}
		}

		// We've definitely changed some group stuff.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// Log the edit.
		logAction('edited_group', array('group' => $smcFunc['htmlspecialchars']($_POST['group_name'])), 'admin');

		redirectexit('action=admin;area=membergroups');
	}

	// Fetch the current group information.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, is_character, description, online_color, max_messages, icons, group_type, hidden, id_parent, tfa_required
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}
		LIMIT 1',
		array(
			'current_group' => (int) $_REQUEST['group'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('membergroup_does_not_exist', false);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$row['icons'] = explode('#', $row['icons']);

	$context['group'] = array(
		'id' => $_REQUEST['group'],
		'name' => $row['group_name'],
		'is_character' => $row['is_character'],
		'description' => $smcFunc['htmlspecialchars']($row['description'], ENT_QUOTES),
		'editable_name' => $row['group_name'],
		'color' => $row['online_color'],
		'max_messages' => $row['max_messages'],
		'has_badge' => !empty($row['icons'][0]),
		'badge_enabled' => true,
		'icon_count' => (int) $row['icons'][0],
		'icon_image' => isset($row['icons'][1]) ? $row['icons'][1] : '',
		'type' => $row['group_type'],
		'hidden' => $row['hidden'],
		'inherited_from' => $row['id_parent'],
		'allow_delete' => !in_array($_REQUEST['group'], [1, 3]),
		'allow_protected' => allowedTo('admin_forum'),
		'tfa_required' => $row['tfa_required'],
	);

	// Get any moderators for this group
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}group_moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_group = {int:current_group}',
		array(
			'current_group' => $_REQUEST['group'],
		)
	);
	$context['group']['moderators'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['group']['moderators'][$row['id_member']] = $row['real_name'];
	$smcFunc['db_free_result']($request);

	Autocomplete::init('member', '#group_moderators', 0, array_keys($context['group']['moderators']));

	// Get a list of boards this membergroup is allowed to see.
	$context['boards'] = [];
	if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
	{
		$request = $smcFunc['db_query']('', '
			SELECT b.id_cat, c.name as cat_name, b.id_board, b.name, b.child_level,
			FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access, FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY board_order',
			array(
				'current_group' => (int) $_REQUEST['group'],
			)
		);
		$context['categories'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// This category hasn't been set up yet..
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => []
				);

			// Set this board up, and let the template know when it's a child.  (indent them..)
			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'allow' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
				'deny' => !(empty($row['cannot_access']) || $row['cannot_access'] == 'f'),
			);
		}
		$smcFunc['db_free_result']($request);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = [];
		foreach ($context['categories'] as $category)
		{
			$temp_boards[] = array(
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards'])
			);
			$temp_boards = array_merge($temp_boards, array_values($category['boards']));

			// Include a list of boards per category for easy toggling.
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}
	}

	// Get a list of all the image formats we can select.
	$imageExts = array('png', 'jpg', 'jpeg', 'bmp', 'gif');

	// Scan the directory.
	$context['possible_icons'] = [];
	if ($files = scandir($settings['default_theme_dir'] . '/images/membericons'))
	{
		// Loop through every file in the directory.
		foreach ($files as $value)
		{
			// Grab the image extension.
			$ext = pathinfo($settings['default_theme_dir'] . '/images/membericons/' . $value, PATHINFO_EXTENSION);

			// If the extension is not empty, and it is valid
			if (!empty($ext) && in_array($ext, $imageExts))
			{
				// Get the size of the image.
				$image_info = getimagesize($settings['default_theme_dir'] . '/images/membericons/' . $value);

				// If this image doesn't have a size or the size is unreasonable large, don't use it.
				if ($image_info == false || $image_info[0] > 1024 || $image_info[1] > 1024)
					continue;

				// Else it's valid. Add it in.
				else
					$context['possible_icons'][] = $value;
			}
		}
	}

	if (empty($context['possible_icons']))
	{
		$context['group']['has_badge'] = false;
		$context['group']['badge_enabled'] = false;
	}

	// Insert our JS, if we have possible icons.
	if (!empty($context['possible_icons']))
		loadJavaScriptFile('icondropdown.js', array('validate' => true), 'sbb_icondropdown');

	// Finally, get all the groups this could be inherited off.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group != {int:current_group}' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
			AND id_group NOT IN (1, 3)
			AND id_parent = {int:not_inherited}',
		array(
			'current_group' => (int) $_REQUEST['group'],
			'not_inherited' => -2,
			'is_protected' => 1,
		)
	);
	$context['inheritable_groups'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['inheritable_groups'][$row['id_group']] = $row['group_name'];
	$smcFunc['db_free_result']($request);

	call_integration_hook('integrate_view_membergroup');

	$context['sub_template'] = 'admin_membergroups_edit';
	$context['page_title'] = $txt['membergroups_edit_group'];

	createToken('admin-mmg');
}

/**
 * Allows configuration of the membergroup badge order.
 */
function MembergroupBadges()
{
	global $smcFunc, $context, $txt, $settings;

	$context['groups'] = [
		'accounts' => [],
		'characters' => [],
	];

	if (isset($_POST['group']) && is_array($_POST['group']))
	{
		checkSession();
		$order = 1;
		foreach ($_POST['group'] as $group) {
			$group = (int) $group;
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}membergroups
				SET badge_order = {int:order}
				WHERE id_group = {int:group}',
				[
					'order' => $order,
					'group' => $group,
				]
			);
			$order++;
		}
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, online_color, icons, is_character
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
		ORDER BY badge_order',
		[
			'moderator_group' => 3
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['parsed_icons'] = '';
		if (!empty($row['icons']))
		{
			list($qty, $badge) = explode('#', $row['icons']);
			if (!empty($qty))
				$row['parsed_icons'] = str_repeat('<img src="' . $settings['default_images_url'] . '/membericons/' . $badge . '" alt="*">', $qty);
		}
		$context['groups'][$row['is_character'] ? 'characters' : 'accounts'][$row['id_group']] = $row;
	}
	$smcFunc['db_free_result']($request);

	$context['page_title'] = $txt['badges'];
	$context['sub_template'] = 'admin_membergroups_badges';
	loadJavascriptFile('jquery-ui-1.12.1-sortable.min.js', ['default_theme' => true]);
	addInlineJavascript('
	$(\'.sortable\').sortable({handle: ".handle"});', true);
}
