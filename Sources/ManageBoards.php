<?php

/**
 * Manage and maintain the boards and categories of the forum.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Autocomplete;
use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

/**
 * The main dispatcher; doesn't do anything, just delegates.
 * This is the main entry point for all the manageboards admin screens.
 * Called by ?action=admin;area=manageboards.
 * It checks the permissions, based on the sub-action, and calls a function based on the sub-action.
 *
 *  @uses ManageBoards language file.
 */
function ManageBoards()
{
	global $context, $txt;

	// Everything's gonna need this.
	loadLanguage('ManageBoards');

	// Format: 'sub-action' => array('function', 'permission')
	$subActions = [
		'board' => ['EditBoard', 'manage_boards'],
		'board2' => ['EditBoard2', 'manage_boards'],
		'cat' => ['EditCategory', 'manage_boards'],
		'cat2' => ['EditCategory2', 'manage_boards'],
		'main' => ['ManageBoardsMain', 'manage_boards'],
		'newcat' => ['EditCategory', 'manage_boards'],
		'newboard' => ['EditBoard', 'manage_boards'],
		'settings' => ['EditBoardSettings', 'admin_forum'],
	];

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['boards_and_cats'],
		'help' => '',
		'description' => $txt['boards_and_cats_desc'],
		'tabs' => [
			'main' => [
			],
			'newcat' => [
			],
			'settings' => [
				'description' => $txt['mboards_settings_desc'],
			],
		],
	];

	routing_integration_hook('integrate_manage_boards', [&$subActions]);

	// Default to sub action 'main' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_boards') ? 'main' : 'settings');

	// Have you got the proper permissions?
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * The main control panel thing, the screen showing all boards and categories.
 * Called by ?action=admin;area=manageboards or ?action=admin;area=manageboards;sa=move.
 * Requires manage_boards permission.
 * It also handles the interface for moving boards.
 *
 * @uses ManageBoards template, main sub-template.
 */
function ManageBoardsMain()
{
	global $txt, $context, $cat_tree, $boards, $boardList, $sourcedir;

	$context['sub_template'] = 'admin_boards';

	require_once($sourcedir . '/Subs-Boards.php');

	getBoardTree();

	$context['move_board'] = !empty($_REQUEST['move']) && isset($boards[(int) $_REQUEST['move']]) ? (int) $_REQUEST['move'] : 0;

	$context['categories'] = [];
	foreach ($cat_tree as $catid => $tree)
	{
		$context['categories'][$catid] = [
			'name' => &$tree['node']['name'],
			'id' => &$tree['node']['id'],
			'boards' => []
		];
		$move_cat = !empty($context['move_board']) && $boards[$context['move_board']]['category'] == $catid;
		foreach ($boardList[$catid] as $boardid)
		{
			$context['categories'][$catid]['boards'][$boardid] = [
				'id' => &$boards[$boardid]['id'],
				'name' => &$boards[$boardid]['name'],
				'url' => &$boards[$boardid]['url'],
				'description' => &$boards[$boardid]['description'],
				'child_level' => &$boards[$boardid]['level'],
				'move' => $move_cat && ($boardid == $context['move_board'] || isChildOf($boardid, $context['move_board'])),
				'permission_profile' => &$boards[$boardid]['profile'],
				'is_redirect' => !empty($boards[$boardid]['redirect']),
				'in_character' => !empty($boards[$boardid]['in_character']),
			];
		}
	}

	call_integration_hook('integrate_boards_main');

	$context['page_title'] = $txt['boards_and_cats'];
	$context['can_manage_permissions'] = allowedTo('manage_permissions');
}

/**
 * Modify a specific category.
 * (screen for editing and repositioning a category.)
 * Also used to show the confirm deletion of category screen
 * (sub-template confirm_category_delete).
 * Called by ?action=admin;area=manageboards;sa=cat
 * Requires manage_boards permission.
 *
 * @uses ManageBoards template, modify_category sub-template.
 */
function EditCategory()
{
	global $txt, $context, $cat_tree, $boardList, $boards, $sourcedir;

	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');
	getBoardTree();

	// id_cat must be a number.... if it exists.
	$_REQUEST['cat'] = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;

	// Start with one - "In first place".
	$context['category_order'] = [
		[
			'id' => 0,
			'name' => $txt['mboards_order_first'],
			'selected' => !empty($_REQUEST['cat']) ? $cat_tree[$_REQUEST['cat']]['is_first'] : false,
			'true_name' => ''
		]
	];

	// If this is a new category set up some defaults.
	if ($_REQUEST['sa'] == 'newcat')
	{
		$context['category'] = [
			'id' => 0,
			'name' => $txt['mboards_new_cat_name'],
			'editable_name' => StringLibrary::escape($txt['mboards_new_cat_name']),
			'description' => '',
			'can_collapse' => true,
			'is_new' => true,
			'is_empty' => true
		];
	}
	// Category doesn't exist, man... sorry.
	elseif (!isset($cat_tree[$_REQUEST['cat']]))
		redirectexit('action=admin;area=manageboards');
	else
	{
		$context['category'] = [
			'id' => $_REQUEST['cat'],
			'name' => $cat_tree[$_REQUEST['cat']]['node']['name'],
			'editable_name' => $cat_tree[$_REQUEST['cat']]['node']['name'],
			'description' => un_preparsecode($cat_tree[$_REQUEST['cat']]['node']['description']),
			'can_collapse' => !empty($cat_tree[$_REQUEST['cat']]['node']['can_collapse']),
			'children' => [],
			'is_empty' => empty($cat_tree[$_REQUEST['cat']]['children'])
		];

		foreach ($boardList[$_REQUEST['cat']] as $child_board)
			$context['category']['children'][] = str_repeat('-', $boards[$child_board]['level']) . ' ' . $boards[$child_board]['name'];
	}

	$editorOptions = [
		'id' => 'cat_desc',
		'value' => $context['category']['description'],
		'labels' => [
			'post_button' => $txt['save'],
		],
		// add height and width for the editor
		'height' => '175px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => false,
	];
	create_control_richedit($editorOptions);

	$prevCat = 0;
	foreach ($cat_tree as $catid => $tree)
	{
		if ($catid == $_REQUEST['cat'] && $prevCat > 0)
			$context['category_order'][$prevCat]['selected'] = true;
		elseif ($catid != $_REQUEST['cat'])
			$context['category_order'][$catid] = [
				'id' => $catid,
				'name' => $txt['mboards_order_after'] . $tree['node']['name'],
				'selected' => false,
				'true_name' => $tree['node']['name']
			];
		$prevCat = $catid;
	}
	if (!isset($_REQUEST['delete']))
	{
		$context['sub_template'] = 'admin_boards_category_edit';
		$context['page_title'] = $_REQUEST['sa'] == 'newcat' ? $txt['mboards_new_cat_name'] : $txt['catEdit'];
	}
	else
	{
		$context['sub_template'] = 'admin_boards_category_delete';
		$context['page_title'] = $txt['mboards_delete_cat'];
	}

	// Create a special token.
	createToken('admin-bc-' . $_REQUEST['cat']);
	$context['token_check'] = 'admin-bc-' . $_REQUEST['cat'];

	call_integration_hook('integrate_edit_category');
}

/**
 * Function for handling a submitted form saving the category.
 * (complete the modifications to a specific category.)
 * It also handles deletion of a category.
 * It requires manage_boards permission.
 * Called by ?action=admin;area=manageboards;sa=cat2
 * Redirects to ?action=admin;area=manageboards.
 */
function EditCategory2()
{
	global $sourcedir, $context;

	checkSession();
	validateToken('admin-bc-' . $_REQUEST['cat']);

	require_once($sourcedir . '/Subs-Categories.php');
	require_once($sourcedir . '/Subs-Post.php');

	$_POST['cat'] = (int) $_POST['cat'];

	// Add a new category or modify an existing one..
	if (isset($_POST['edit']) || isset($_POST['add']))
	{
		$catOptions = [];

		if (isset($_POST['cat_order']))
			$catOptions['move_after'] = (int) $_POST['cat_order'];

		// Change "This & That" to "This &amp; That" but don't change "&cent" to "&amp;cent;"...
		$catOptions['cat_name'] = StringLibrary::escape($_POST['cat_name'] ?? '', ENT_QUOTES);
		$catOptions['cat_desc'] = StringLibrary::escape($_POST['cat_desc'] ?? '', ENT_QUOTES);
		preparsecode($catOptions['cat_desc']);

		$catOptions['is_collapsible'] = isset($_POST['collapse']);

		if (isset($_POST['add']))
			createCategory($catOptions);
		else
			modifyCategory($_POST['cat'], $catOptions);
	}
	// If they want to delete - first give them confirmation.
	elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['empty']))
	{
		EditCategory();
		return;
	}
	// Delete the category!
	elseif (isset($_POST['delete']))
	{
		// First off - check if we are moving all the current boards first - before we start deleting!
		if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1)
		{
			if (empty($_POST['cat_to']))
				fatal_lang_error('mboards_delete_error');

			deleteCategories([$_POST['cat']], (int) $_POST['cat_to']);
		}
		else
			deleteCategories([$_POST['cat']]);
	}

	redirectexit('action=admin;area=manageboards');
}

/**
 * Modify a specific board...
 * screen for editing and repositioning a board.
 * called by ?action=admin;area=manageboards;sa=board
 * uses the modify_board sub-template of the ManageBoards template.
 * requires manage_boards permission.
 * also used to show the confirm deletion of category screen (sub-template confirm_board_delete).
 */
function EditBoard()
{
	global $txt, $context, $cat_tree, $boards, $boardList;
	global $sourcedir, $smcFunc, $modSettings, $scripturl;

	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');
	getBoardTree();

	// For editing the profile we'll need this.
	loadLanguage('ManagePermissions');
	loadLanguage('ManageMembers');
	require_once($sourcedir . '/ManagePermissions.php');
	loadPermissionProfiles();

	// People with manage-boards are special.
	require_once($sourcedir . '/Subs-Members.php');
	$groups = groupsAllowedTo('manage_boards', null);
	$context['board_managers'] = $groups['allowed']; // We don't need *all* this in $context.

	// id_board must be a number....
	$_REQUEST['boardid'] = isset($_REQUEST['boardid']) ? (int) $_REQUEST['boardid'] : 0;
	if (!isset($boards[$_REQUEST['boardid']]))
	{
		$_REQUEST['boardid'] = 0;
		$_REQUEST['sa'] = 'newboard';
	}

	if ($_REQUEST['sa'] == 'newboard')
	{
		// Category doesn't exist, man... sorry.
		if (empty($_REQUEST['cat']))
			redirectexit('action=admin;area=manageboards');

		// Some things that need to be setup for a new board.
		$curBoard = [
			'member_groups' => [0, -1],
			'deny_groups' => [],
			'category' => (int) $_REQUEST['cat']
		];
		$context['board_order'] = [];
		$context['board'] = [
			'is_new' => true,
			'id' => 0,
			'name' => StringLibrary::escape($txt['mboards_new_board_name'], ENT_QUOTES),
			'description' => '',
			'count_posts' => 1,
			'posts' => 0,
			'topics' => 0,
			'theme' => 0,
			'profile' => 1,
			'override_theme' => 0,
			'in_character' => 1,
			'redirect' => '',
			'category' => (int) $_REQUEST['cat'],
			'no_children' => true,
			'board_sort' => 'last_post_desc',
			'board_sort_force' => 0,
		];
	}
	else
	{
		// Just some easy shortcuts.
		$curBoard = &$boards[$_REQUEST['boardid']];
		$context['board'] = $boards[$_REQUEST['boardid']];
		$context['board']['name'] = $context['board']['name'];
		$context['board']['description'] = un_preparsecode($context['board']['description']);
		$context['board']['no_children'] = empty($boards[$_REQUEST['boardid']]['tree']['children']);
		$context['board']['is_recycle'] = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) && $modSettings['recycle_board'] == $context['board']['id'];

		[$default_board_sort_order, $default_board_sort_direction, $board_sort_force] = explode(';', $boards[$_REQUEST['boardid']]['board_sort']);
		$context['board']['board_sort'] = $default_board_sort_order . '_' . ($default_board_sort_direction == 'asc' ? 'asc' : 'desc');
		$context['board']['board_sort_force'] = !empty($board_sort_force) ? 1 : 0;
	}

	$editorOptions = [
		'id' => 'desc',
		'value' => $context['board']['description'],
		'labels' => [
			'post_button' => $txt['save'],
		],
		// add height and width for the editor
		'height' => '175px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => false,
	];
	create_control_richedit($editorOptions);

	// As we may have come from the permissions screen keep track of where we should go on save.
	$context['redirect_location'] = isset($_GET['rid']) && $_GET['rid'] == 'permissions' ? 'permissions' : 'boards';

	// We might need this to hide links to certain areas.
	$context['can_manage_permissions'] = allowedTo('manage_permissions');
	$context['permission_profile_desc'] = $context['can_manage_permissions'] ? sprintf($txt['permission_profile_desc'], $scripturl . '?action=admin;area=permissions;sa=profiles;' . $context['session_var'] . '=' . $context['session_id']) : strip_tags($txt['permission_profile_desc']);

	// Default membergroups.
	$context['groups'] = [
		-1 => [
			'id' => '-1',
			'name' => $txt['parent_guests_only'],
			'allow' => in_array('-1', $curBoard['member_groups']),
			'deny' => in_array('-1', $curBoard['deny_groups']),
		],
		0 => [
			'id' => '0',
			'name' => $txt['parent_members_only'],
			'allow' => in_array('0', $curBoard['member_groups']),
			'deny' => in_array('0', $curBoard['deny_groups']),
		]
	];
	// As much as we want all the things, we also want them separately.
	$context['groups_account'] = $context['groups'];
	$context['groups_character'] = [];
	$context['groups_post'] = [];

	// Load membergroups.
	$request = $smcFunc['db']->query('', '
		SELECT group_name, id_group, is_character
		FROM {db_prefix}membergroups
		WHERE id_group NOT IN ({int:admin_group}, {int:moderator_group})
		ORDER BY group_name',
		[
			'admin_group' => 1,
			'moderator_group' => 3,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if ($_REQUEST['sa'] == 'newboard')
			$curBoard['member_groups'][] = $row['id_group'];

		$group_type = $row['is_character'] ? 'groups_character' : 'groups_account';
		$context['groups'][(int) $row['id_group']] = $context[$group_type][(int) $row['id_group']] = [
			'id' => $row['id_group'],
			'name' => trim($row['group_name']),
			'allow' => in_array($row['id_group'], $curBoard['member_groups']),
			'deny' => in_array($row['id_group'], $curBoard['deny_groups']),
		];
	}
	$smcFunc['db']->free_result($request);

	// Category doesn't exist, man... sorry.
	if (!isset($boardList[$curBoard['category']]))
		redirectexit('action=admin;area=manageboards');

	foreach ($boardList[$curBoard['category']] as $boardid)
	{
		if ($boardid == $_REQUEST['boardid'])
		{
			$context['board_order'][] = [
				'id' => $boardid,
				'name' => str_repeat('-', $boards[$boardid]['level']) . ' (' . $txt['mboards_current_position'] . ')',
				'children' => $boards[$boardid]['tree']['children'],
				'no_children' => empty($boards[$boardid]['tree']['children']),
				'is_child' => false,
				'selected' => true
			];
		}
		else
		{
			$context['board_order'][] = [
				'id' => $boardid,
				'name' => str_repeat('-', $boards[$boardid]['level']) . ' ' . $boards[$boardid]['name'],
				'is_child' => empty($_REQUEST['boardid']) ? false : isChildOf($boardid, $_REQUEST['boardid']),
				'selected' => false
			];
		}
	}

	// Are there any places to move child boards to in the case where we are confirming a delete?
	if (!empty($_REQUEST['boardid']))
	{
		$context['can_move_children'] = false;
		$context['children'] = $boards[$_REQUEST['boardid']]['tree']['children'];

		foreach ($context['board_order'] as $lBoard)
			if ($lBoard['is_child'] == false && $lBoard['selected'] == false)
				$context['can_move_children'] = true;
	}

	// Get other available categories.
	$context['categories'] = [];
	foreach ($cat_tree as $catID => $tree)
		$context['categories'][] = [
			'id' => $catID == $curBoard['category'] ? 0 : $catID,
			'name' => $tree['node']['name'],
			'selected' => $catID == $curBoard['category']
		];

	$request = $smcFunc['db']->query('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_board = {int:current_board}',
		[
			'current_board' => $_REQUEST['boardid'],
		]
	);
	$context['board']['moderators'] = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$context['board']['moderators'][$row['id_member']] = $row['real_name'];
	$smcFunc['db']->free_result($request);

	// Get all the groups assigned as moderators
	$request = $smcFunc['db']->query('', '
		SELECT id_group
		FROM {db_prefix}moderator_groups
		WHERE id_board = {int:current_board}',
		[
			'current_board' => $_REQUEST['boardid'],
		]
	);
	$context['board']['moderator_groups'] = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$context['board']['moderator_groups'][$row['id_group']] = $row['id_group'];
	$smcFunc['db']->free_result($request);

	// Get all the themes...
	$request = $smcFunc['db']->query('', '
		SELECT id_theme AS id, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}',
		[
			'name' => 'name',
		]
	);
	$context['themes'] = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$context['themes'][] = $row;
	$smcFunc['db']->free_result($request);

	if (!isset($_REQUEST['delete']))
	{
		$context['sub_template'] = 'admin_boards_edit';
		$context['page_title'] = $txt['boardsEdit'];
		Autocomplete::init('member', '#moderators', 0, array_keys($context['board']['moderators']));
		Autocomplete::init('group', '#moderator_groups', 0, array_keys($context['board']['moderator_groups']));
	}
	else
	{
		$context['sub_template'] = 'admin_boards_delete';
		$context['page_title'] = $txt['mboards_delete_board'];
	}

	$context['board_sort_options'] = [
		'subject_asc' => $txt['board_sort_subject_asc'],
		'subject_desc' => $txt['board_sort_subject_desc'],
		'starter_asc' => $txt['board_sort_starter_asc'],
		'starter_desc' => $txt['board_sort_starter_desc'],
		'last_poster_asc' => $txt['board_sort_last_poster_asc'],
		'last_poster_desc' => $txt['board_sort_last_poster_desc'],
		'first_post_asc' => $txt['board_sort_first_post_asc'],
		'first_post_desc' => $txt['board_sort_first_post_desc'],
		'last_post_asc' => $txt['board_sort_last_post_asc'],
		'last_post_desc' => $txt['board_sort_last_post_desc'],
	];

	// Create a special token.
	createToken('admin-be-' . $_REQUEST['boardid']);
	$context['token_check'] = 'admin-be-' . $_REQUEST['boardid'];

	call_integration_hook('integrate_edit_board');
}

/**
 * Make changes to/delete a board.
 * (function for handling a submitted form saving the board.)
 * It also handles deletion of a board.
 * Called by ?action=admin;area=manageboards;sa=board2
 * Redirects to ?action=admin;area=manageboards.
 * It requires manage_boards permission.
 */
function EditBoard2()
{
	global $sourcedir, $smcFunc, $context;

	$_POST['boardid'] = (int) $_POST['boardid'];
	checkSession();
	validateToken('admin-be-' . $_REQUEST['boardid']);

	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Post.php');

	// Mode: modify aka. don't delete.
	if (isset($_POST['edit']) || isset($_POST['add']))
	{
		$boardOptions = [];

		// Move this board to a new category?
		if (!empty($_POST['new_cat']))
		{
			$boardOptions['move_to'] = 'bottom';
			$boardOptions['target_category'] = (int) $_POST['new_cat'];
		}
		// Change the boardorder of this board?
		elseif (!empty($_POST['placement']) && !empty($_POST['board_order']))
		{
			if (!in_array($_POST['placement'], ['before', 'after', 'child']))
				fatal_lang_error('mangled_post', false);

			$boardOptions['move_to'] = $_POST['placement'];
			$boardOptions['target_board'] = (int) $_POST['board_order'];
		}

		// Checkboxes....
		$boardOptions['in_character'] = !empty($_POST['in_character']);
		$boardOptions['posts_count'] = isset($_POST['count']);
		$boardOptions['override_theme'] = isset($_POST['override_theme']);
		$boardOptions['board_theme'] = (int) $_POST['boardtheme'];
		$boardOptions['access_groups'] = [];
		$boardOptions['deny_groups'] = [];

		$board_sort_options = ['subject', 'starter', 'last_poster', 'first_post', 'last_post'];
		$boardOptions['board_sort'] = '';
		if (!empty($_POST['board_sort']))
		{
			foreach ($board_sort_options as $board_sort)
			{
				if ($_POST['board_sort'] == $board_sort . '_asc')
				{
					$boardOptions['board_sort'] = $board_sort . ';asc;' . (!empty($_POST['board_sort_force']) ? '1' : '0');
				}
				elseif ($_POST['board_sort'] == $board_sort . '_desc')
				{
					$boardOptions['board_sort'] = $board_sort . ';desc;' . (!empty($_POST['board_sort_force']) ? '1' : '0');
				}
			}
		}

		if (!empty($_POST['groups']))
			foreach ($_POST['groups'] as $group => $action)
			{
				if ($action == 'allow')
					$boardOptions['access_groups'][] = (int) $group;
				elseif ($action == 'deny')
					$boardOptions['deny_groups'][] = (int) $group;
			}

		// People with manage-boards are special.
		require_once($sourcedir . '/Subs-Members.php');
		$board_managers = groupsAllowedTo('manage_boards', null);
		$board_managers = array_diff($board_managers['allowed'], [1]); // We don't need to list admins anywhere.
		// Firstly, we can't ever deny them.
		$boardOptions['deny_groups'] = array_diff($boardOptions['deny_groups'], $board_managers);
		// Secondly, make sure those with super cow powers (like apt-get, or in this case manage boards) are upgraded.
		$boardOptions['access_groups'] = array_unique(array_merge($boardOptions['access_groups'], $board_managers));

		if (strlen(implode(',', $boardOptions['access_groups'])) > 255 || strlen(implode(',', $boardOptions['deny_groups'])) > 255)
			fatal_lang_error('too_many_groups', false);

		// Do not allow HTML tags. Parse the string.
		$boardOptions['board_name'] = StringLibrary::escape($_POST['board_name'], ENT_QUOTES);
		$boardOptions['board_description'] = StringLibrary::escape($_POST['desc'] ?? '', ENT_QUOTES);
		preparsecode($boardOptions['board_description']);

		if (isset($_POST['moderators']) && is_array($_POST['moderators']))
		{
			$moderators = [];
			foreach ($_POST['moderators'] as $moderator)
			{
				$moderator = (int) $moderator;
				if (!empty($moderator))
				{
					$moderators[$moderator] = $moderator;
				}
			}
			$boardOptions['moderators'] = $moderators;
		}

		if (isset($_POST['moderator_groups']) && is_array($_POST['moderator_groups']))
		{
			$moderator_groups = [];
			foreach ($_POST['moderator_groups'] as $moderator_group)
			{
				$moderator_group = (int) $moderator_group;
				if (!empty($moderator_group))
				{
					$moderator_groups[$moderator_group] = $moderator_group;
				}
			}
			$boardOptions['moderator_groups'] = $moderator_groups;
		}

		// Are they doing redirection?
		$boardOptions['redirect'] = !empty($_POST['redirect_enable']) && isset($_POST['redirect_address']) && trim($_POST['redirect_address']) != '' ? trim($_POST['redirect_address']) : '';

		// Profiles...
		$boardOptions['profile'] = $_POST['profile'];
		$boardOptions['inherit_permissions'] = $_POST['profile'] == -1;

		// We need to know what used to be case in terms of redirection.
		if (!empty($_POST['boardid']))
		{
			$request = $smcFunc['db']->query('', '
				SELECT redirect, num_posts
				FROM {db_prefix}boards
				WHERE id_board = {int:current_board}',
				[
					'current_board' => $_POST['boardid'],
				]
			);
			list ($oldRedirect, $numPosts) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			// If we're turning redirection on check the board doesn't have posts in it - if it does don't make it a redirection board.
			if ($boardOptions['redirect'] && empty($oldRedirect) && $numPosts)
				unset($boardOptions['redirect']);
			// Reset the redirection count when switching on/off.
			elseif (empty($boardOptions['redirect']) != empty($oldRedirect))
				$boardOptions['num_posts'] = 0;
			// Resetting the count?
			elseif ($boardOptions['redirect'] && !empty($_POST['reset_redirect']))
				$boardOptions['num_posts'] = 0;
		}

		// Create a new board...
		if (isset($_POST['add']))
		{
			// New boards by default go to the bottom of the category.
			if (empty($_POST['new_cat']))
				$boardOptions['target_category'] = (int) $_POST['cur_cat'];
			if (!isset($boardOptions['move_to']))
				$boardOptions['move_to'] = 'bottom';

			createBoard($boardOptions);
		}

		// ...or update an existing board.
		else
			modifyBoard($_POST['boardid'], $boardOptions);
	}
	elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['no_children']))
	{
		EditBoard();
		return;
	}
	elseif (isset($_POST['delete']))
	{
		// First off - check if we are moving all the current child boards first - before we start deleting!
		if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1)
		{
			if (empty($_POST['board_to']))
				fatal_lang_error('mboards_delete_board_error');

			deleteBoards([$_POST['boardid']], (int) $_POST['board_to']);
		}
		else
			deleteBoards([$_POST['boardid']], 0);
	}

	if (isset($_REQUEST['rid']) && $_REQUEST['rid'] == 'permissions')
		redirectexit('action=admin;area=permissions;sa=board;' . $context['session_var'] . '=' . $context['session_id']);
	else
		redirectexit('action=admin;area=manageboards');
}

/**
 * A screen to set a few general board and category settings.
 *
 * @uses modify_general_settings sub-template.
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or the array of config vars if $return_config is true
 */
function EditBoardSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl, $smcFunc;

	// Load the boards list - for the recycle bin!
	$request = $smcFunc['db']->query('order_by_board_order', '
		SELECT b.id_board, b.name AS board_name, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE redirect = {string:empty_string}',
		[
			'empty_string' => '',
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$recycle_boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
	$smcFunc['db']->free_result($request);

	if (!empty($recycle_boards))
	{
		require_once($sourcedir . '/Subs-Boards.php');
		sortBoards($recycle_boards);
		$recycle_boards = [''] + $recycle_boards;
	}
	else
		$recycle_boards = [''];

	// Here and the board settings...
	$config_vars = [
			// Other board settings.
			['check', 'countChildPosts'],
			['check', 'recycle_enable', 'onclick' => 'document.getElementById(\'recycle_board\').disabled = !this.checked;'],
			['select', 'recycle_board', $recycle_boards],
			['check', 'allow_ignore_boards'],
	];

	settings_integration_hook('integrate_modify_board_settings', [&$config_vars]);

	if ($return_config)
		return [$txt['boards_and_cats'] . ' - ' . $txt['settings'], $config_vars];

	// Needed for the settings template.
	require_once($sourcedir . '/ManageServer.php');

	$context['post_url'] = $scripturl . '?action=admin;area=manageboards;save;sa=settings';

	$context['page_title'] = $txt['boards_and_cats'] . ' - ' . $txt['settings'];

	// Add some javascript stuff for the recycle box.
	addInlineJavaScript('
	document.getElementById("recycle_board").disabled = !document.getElementById("recycle_enable").checked;', true);

	// Warn the admin against selecting the recycle topic without selecting a board.
	$context['force_form_onsubmit'] = 'if(document.getElementById(\'recycle_enable\').checked && document.getElementById(\'recycle_board\').value == 0) { return confirm(\'' . $txt['recycle_board_unselected_notice'] . '\');} return true;';

	// Doing a save?
	if (isset($_GET['save']))
	{
		checkSession();

		settings_integration_hook('integrate_save_board_settings');

		saveDBSettings($config_vars);
		session_flash('success', $txt['settings_saved']);
		redirectexit('action=admin;area=manageboards;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
	$context['settings_title'] = $txt['settings'];
}
