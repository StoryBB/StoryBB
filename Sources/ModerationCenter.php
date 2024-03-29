<?php

/**
 * Moderation Center.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

/**
 * Entry point for the moderation center.
 *
 * @param bool $dont_call If true, doesn't call the function for the appropriate mod area
 */
function ModerationMain($dont_call = false)
{
	global $txt, $context, $scripturl, $modSettings, $user_info, $sourcedir, $options;

	// Don't run this twice... and don't conflict with the admin bar.
	if (isset($context['admin_area']))
		return;

	$context['can_moderate_boards'] = $user_info['mod_cache']['bq'] != '0=1';
	$context['can_moderate_groups'] = $user_info['mod_cache']['gq'] != '0=1';
	$context['can_moderate_approvals'] = !empty($user_info['mod_cache']['ap']);
	$context['can_moderate_users'] = allowedTo('moderate_forum');

	// Everyone using this area must be allowed here!
	if (!$context['can_moderate_boards'] && !$context['can_moderate_groups'] && !$context['can_moderate_approvals'] && !$context['can_moderate_users'])
		isAllowedTo('access_mod_center');

	// We're gonna want a menu of some kind.
	require_once($sourcedir . '/Subs-Menu.php');

	// Load the language, and the template.
	loadLanguage('ModerationCenter');
	loadCSSFile('admin.css', [], 'admin');

	$context['admin_preferences'] = !empty($options['admin_preferences']) ? sbb_json_decode($options['admin_preferences'], true) : [];
	$context['robot_no_index'] = true;

	// This is the menu structure - refer to Subs-Menu.php for the details.
	$moderation_areas = [
		'main' => [
			'title' => $txt['mc_main'],
			'areas' => [
				'index' => [
					'label' => $txt['moderation_center'],
					'function' => 'ModerationHome',
					'icon' => 'administration',
				],
				'notice' => [
					'file' => 'ModerationCenter.php',
					'function' => 'ShowNotice',
					'select' => 'index'
				],
			],
		],
		'logs' => [
			'title' => $txt['mc_logs'],
			'areas' => [
				'modlog' => [
					'label' => $txt['modlog_view'],
					'enabled' => $context['can_moderate_boards'],
					'file' => 'Modlog.php',
					'function' => 'ViewModlog',
					'icon' => 'logs',
				],
				'warnings' => [
					'label' => $txt['mc_warnings'],
					'enabled' => $modSettings['warning_settings'][0] == 1 && $context['can_moderate_boards'],
					'function' => 'ViewWarnings',
					'icon' => 'warning',
					'subsections' => [
						'log' => [$txt['mc_warning_log']],
						'templates' => [$txt['mc_warning_templates'], 'issue_warning'],
					],
				],
			],
		],
		'posts' => [
			'title' => $txt['mc_posts'],
			'enabled' => $context['can_moderate_boards'] || $context['can_moderate_approvals'],
			'areas' => [
				'postmod' => [
					'label' => $txt['mc_unapproved_posts'],
					'enabled' => $context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'posts',
					'custom_url' => $scripturl . '?action=moderate;area=postmod',
					'subsections' => [
						'posts' => [$txt['mc_unapproved_replies']],
						'topics' => [$txt['mc_unapproved_topics']],
					],
				],
				'attachmod' => [
					'label' => $txt['mc_unapproved_attachments'],
					'enabled' => $context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'post_moderation_attach',
					'custom_url' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
				],
				'reportedposts' => [
					'label' => $txt['mc_reported_posts'],
					'enabled' => $context['can_moderate_boards'],
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'reports',
					'subsections' => [
						'show' => [$txt['mc_reportedp_active']],
						'closed' => [$txt['mc_reportedp_closed']],
					],
				],
			],
		],
		'groups' => [
			'title' => $txt['mc_groups'],
			'enabled' => $context['can_moderate_groups'],
			'areas' => [
				'groups' => [
					'label' => $txt['mc_group_requests'],
					'file' => 'Groups.php',
					'function' => 'Groups',
					'icon' => 'members_request',
					'custom_url' => $scripturl . '?action=moderate;area=groups;sa=requests',
				],
				'viewgroups' => [
					'label' => $txt['mc_view_groups'],
					'file' => 'Groups.php',
					'function' => 'Groups',
					'icon' => 'membergroups',
				],
			],
		],
		'members' => [
			'title' => $txt['mc_members'],
			'enabled' => $context['can_moderate_users'] || ($modSettings['warning_settings'][0] == 1 && $context['can_moderate_boards']),
			'areas' => [
				'userwatch' => [
					'label' => $txt['mc_watched_users_title'],
					'enabled' => $modSettings['warning_settings'][0] == 1 && $context['can_moderate_boards'],
					'function' => 'ViewWatchedUsers',
					'icon' => 'members_watched',
					'subsections' => [
						'member' => [$txt['mc_watched_users_member']],
						'post' => [$txt['mc_watched_users_post']],
					],
				],
				'reportedmembers' => [
					'label' => $txt['mc_reported_members_title'],
					'enabled' => $context['can_moderate_users'],
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'members_watched',
					'subsections' => [
						'open' => [$txt['mc_reportedp_active']],
						'closed' => [$txt['mc_reportedp_closed']],
					],
				],
			],
		]
	];

	// I don't know where we're going - I don't know where we've been...
	$menuOptions = [
		'action' => 'moderate',
		'disable_url_session_check' => true,
	];
	$mod_include_data = createMenu($moderation_areas, $menuOptions);
	unset($moderation_areas);

	// We got something - didn't we? DIDN'T WE!
	if ($mod_include_data == false)
		fatal_lang_error('no_access', false);

	// Retain the ID information in case required by a subaction.
	$context['moderation_menu_id'] = $context['max_menu_id'];
	$context['moderation_menu_name'] = 'menu_data_' . $context['moderation_menu_id'];

	// @todo: html in here is not good
	$context[$context['moderation_menu_name']]['tab_data'] = [
		'title' => $txt['moderation_center'],
		'help' => '',
		'description' => '
			<strong>' . $txt['hello_guest'] . ' ' . $context['user']['name'] . '!</strong>
			<br><br>
			' . $txt['mc_description']];

	// What a pleasant shortcut - even tho we're not *really* on the admin screen who cares...
	$context['admin_area'] = $mod_include_data['current_area'];

	// Build the link tree.
	$context['linktree'][] = [
		'url' => $scripturl . '?action=moderate',
		'name' => $txt['moderation_center'],
	];
	if (isset($mod_include_data['current_area']) && $mod_include_data['current_area'] != 'index')
		$context['linktree'][] = [
			'url' => $scripturl . '?action=moderate;area=' . $mod_include_data['current_area'],
			'name' => $mod_include_data['label'],
		];
	if (!empty($mod_include_data['current_subsection']) && $mod_include_data['subsections'][$mod_include_data['current_subsection']][0] != $mod_include_data['label'])
		$context['linktree'][] = [
			'url' => $scripturl . '?action=moderate;area=' . $mod_include_data['current_area'] . ';sa=' . $mod_include_data['current_subsection'],
			'name' => $mod_include_data['subsections'][$mod_include_data['current_subsection']][0],
		];

	// Now - finally - the bit before the encore - the main performance of course!
	if (!$dont_call)
	{
		if (isset($mod_include_data['file']))
			require_once($sourcedir . '/' . $mod_include_data['file']);

		call_helper($mod_include_data['function']);
	}
}

/**
 * This function basically is the home page of the moderation center.
 */
function ModerationHome()
{
	global $txt, $context, $options;

	loadJavaScriptFile('admin.js', [], 'sbb_admin');

	$context['page_title'] = $txt['moderation_center'];
	$context['sub_template'] = 'modcenter_home';

	// Handle moderators notes.
	ModBlockNotes();

	// Load what blocks the user actually can see...
	$valid_blocks = [];

	if ($context['can_moderate_groups'])
		$valid_blocks['g'] = 'GroupRequests';
	if ($context['can_moderate_boards'])
	{
		$valid_blocks['r'] = 'ReportedPosts';
		$valid_blocks['w'] = 'WatchedUsers';
	}
	if ($context['can_moderate_users'])
	{
		// This falls under the category of moderating users as well...
		if (!$context['can_moderate_boards'])
			$valid_blocks['w'] = 'WatchedUsers';

		$valid_blocks['rm'] = 'ReportedMembers';
	}

	call_integration_hook('integrate_mod_centre_blocks', [&$valid_blocks]);

	$context['mod_blocks'] = [];
	foreach ($valid_blocks as $block)
	{
		$block = 'ModBlock' . $block;
		if (function_exists($block))
			$context['mod_blocks'][] = $block();
	}

	$context['admin_prefs'] = !empty($options['admin_preferences']) ? sbb_json_decode($options['admin_preferences'], true) : [];
}

/**
 * Show a list of the most active watched users.
 */
function ModBlockWatchedUsers()
{
	global $context, $smcFunc, $scripturl, $modSettings;

	if (($watched_users = cache_get_data('recent_user_watches', 240)) === null)
	{
		$modSettings['warning_watch'] = empty($modSettings['warning_watch']) ? 1 : $modSettings['warning_watch'];
		$request = $smcFunc['db']->query('', '
			SELECT id_member, real_name, last_login
			FROM {db_prefix}members
			WHERE warning >= {int:warning_watch}
			ORDER BY last_login DESC
			LIMIT 10',
			[
				'warning_watch' => $modSettings['warning_watch'],
			]
		);
		$watched_users = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$watched_users[] = $row;
		$smcFunc['db']->free_result($request);

		cache_put_data('recent_user_watches', $watched_users, 240);
	}

	$context['watched_users'] = [];
	foreach ($watched_users as $user)
	{
		$context['watched_users'][] = [
			'id' => $user['id_member'],
			'name' => $user['real_name'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $user['id_member'] . '">' . $user['real_name'] . '</a>',
			'href' => $scripturl . '?action=profile;u=' . $user['id_member'],
			'last_login' => !empty($user['last_login']) ? timeformat($user['last_login']) : '',
		];
	}

	return 'modcenter_watched_users';
}

/**
 * Show an area for the moderator to type into.
 */
function ModBlockNotes()
{
	global $context, $smcFunc, $scripturl, $txt, $user_info;

	// Are we saving a note?
	if (isset($_GET['modnote']) && isset($_POST['makenote']) && isset($_POST['new_note']))
	{
		checkSession();
		validateToken('mod-modnote-add');

		$_POST['new_note'] = StringLibrary::escape(trim($_POST['new_note']));
		// Make sure they actually entered something.
		if (!empty($_POST['new_note']))
		{
			// Insert it into the database then!
			$smcFunc['db']->insert('',
				'{db_prefix}log_comments',
				[
					'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
					'body' => 'string', 'log_time' => 'int',
				],
				[
					$user_info['id'], $user_info['name'], 'modnote', '', $_POST['new_note'], time(),
				],
				['id_comment']
			);

			// Clear the cache.
			cache_put_data('moderator_notes', null, 240);
			cache_put_data('moderator_notes_total', null, 240);
		}

		// Everything went better than expected!
		session_flash('success', $txt['report_action_message_saved']);

		// Redirect otherwise people can resubmit.
		redirectexit('action=moderate');
	}

	// Bye... bye...
	if (isset($_GET['notes']) && isset($_GET['delete']) && is_numeric($_GET['delete']))
	{
		checkSession('get');
		validateToken('mod-modnote-del', 'get');

		// No sneaky stuff now!
		if (!allowedTo('admin_forum'))
		{
			// Is this your note?
			$get_owner = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}log_comments
				WHERE id_comment = {int:note}
					AND comment_type = {literal:modnote}
					AND id_member = {int:user}',
				[
					'note' => $_GET['delete'],
					'user' => $user_info['id'],
				]
			);

			$note_owner = $smcFunc['db']->num_rows($get_owner);
			$smcFunc['db']->free_result($get_owner);

			if (empty($note_owner))
				fatal_lang_error('mc_notes_delete_own', false);
		}

		// Lets delete it.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment = {int:note}
				AND comment_type = {literal:modnote}',
			[
				'note' => $_GET['delete'],
			]
		);

		// Clear the cache.
		cache_put_data('moderator_notes', null, 240);
		cache_put_data('moderator_notes_total', null, 240);

		// Tell them the message was deleted.
		session_flash('success', $txt['report_action_message_deleted']);

		redirectexit('action=moderate');
	}

	// How many notes in total?
	if (($moderator_notes_total = cache_get_data('moderator_notes_total', 240)) === null)
	{
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {literal:modnote}',
			[
			]
		);
		list ($moderator_notes_total) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		cache_put_data('moderator_notes_total', $moderator_notes_total, 240);
	}

	// Grab the current notes. We can only use the cache for the first page of notes.
	$offset = isset($_GET['notes']) && isset($_GET['start']) ? $_GET['start'] : 0;
	if ($offset != 0 || ($moderator_notes = cache_get_data('moderator_notes', 240)) === null)
	{
		$request = $smcFunc['db']->query('', '
			SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
				lc.log_time, lc.body, lc.id_comment AS id_note
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {literal:modnote}
			ORDER BY id_comment DESC
			LIMIT {int:offset}, 10',
			[
				'offset' => $offset,
			]
		);
		$moderator_notes = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$moderator_notes[] = $row;
		$smcFunc['db']->free_result($request);

		if ($offset == 0)
			cache_put_data('moderator_notes', $moderator_notes, 240);
	}

	// Lets construct a page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=index;notes', $_GET['start'], $moderator_notes_total, 10);
	$context['start'] = $_GET['start'];

	$context['notes'] = [];
	foreach ($moderator_notes as $note)
	{
		$context['notes'][] = [
			'author' => [
				'id' => $note['id_member'],
				'link' => $note['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $note['id_member'] . '">' . $note['member_name'] . '</a>') : $note['member_name'],
			],
			'time' => timeformat($note['log_time']),
			'text' => Parser::parse_bbc($note['body']),
			'delete_href' => $scripturl . '?action=moderate;area=index;notes;delete=' . $note['id_note'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'can_delete' => allowedTo('admin_forum') || $note['id_member'] == $user_info['id'],
		];
	}

	// Couple tokens for add/delete modnotes
	createToken('mod-modnote-add');
	createToken('mod-modnote-del', 'get');

	return 'notes';
}

/**
 * Show a list of the most recent reported posts.
 */
function ModBlockReportedPosts()
{
	global $context, $user_info, $scripturl, $smcFunc;

	// Got the info already?
	$cachekey = md5(json_encode($user_info['mod_cache']['bq']));
	$context['reported_posts'] = [];
	if ($user_info['mod_cache']['bq'] == '0=1')
		return 'modcenter_reported_posts';

	if (($reported_posts = cache_get_data('reported_posts_' . $cachekey, 90)) === null)
	{
		// By George, that means we in a position to get the reports, jolly good.
		$request = $smcFunc['db']->query('', '
			SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject,
				lr.num_reports, COALESCE(mem.real_name, lr.membername) AS author_name,
				COALESCE(mem.id_member, 0) AS id_author
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
				AND lr.id_board != {int:not_a_reported_post}
				AND lr.closed = {int:not_closed}
				AND lr.ignore_all = {int:not_ignored}
			ORDER BY lr.time_updated DESC
			LIMIT 10',
			[
				'not_a_reported_post' => 0,
				'not_closed' => 0,
				'not_ignored' => 0,
			]
		);
		$reported_posts = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$reported_posts[] = $row;
		$smcFunc['db']->free_result($request);

		// Cache it.
		cache_put_data('reported_posts_' . $cachekey, $reported_posts, 90);
	}

	$context['reported_posts'] = [];
	foreach ($reported_posts as $row)
	{
		$context['reported_posts'][] = [
			'id' => $row['id_report'],
			'topic_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'report_href' => $scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $row['id_report'],
			'report_link' => '<a href="' . $scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $row['id_report'] . '">' . $row['subject'] . '</a>',
			'author' => [
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
			],
			'subject' => $row['subject'],
			'num_reports' => $row['num_reports'],
		];
	}

	return 'modcenter_reported_posts';
}

/**
 * Show a list of all the group requests they can see.
 */
function ModBlockGroupRequests()
{
	global $context, $user_info, $scripturl, $smcFunc;

	$context['group_requests'] = [];
	// Make sure they can even moderate someone!
	if ($user_info['mod_cache']['gq'] == '0=1')
		return 'group_requests_block';

	// What requests are outstanding?
	$request = $smcFunc['db']->query('', '
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, mem.member_name, mg.group_name, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . ($user_info['mod_cache']['gq'] == '1=1' || $user_info['mod_cache']['gq'] == '0=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq']) . '
			AND lgr.status = {int:status_open}
		ORDER BY lgr.id_request DESC
		LIMIT 10',
		[
			'status_open' => 0,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$context['group_requests'][] = [
			'id' => $row['id_request'],
			'request_href' => $scripturl . '?action=groups;sa=requests;gid=' . $row['id_group'],
			'member' => [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			],
			'group' => [
				'id' => $row['id_group'],
				'name' => $row['group_name'],
			],
			'time_submitted' => timeformat($row['time_applied']),
		];
	}
	$smcFunc['db']->free_result($request);

	return 'modcenter_group_requests';
}

/**
 * Show a list of the most recent reported posts.
 */
function ModBlockReportedMembers()
{
	global $context, $scripturl, $smcFunc;

	// Got the info already?
	$cachekey = md5(json_encode((int) allowedTo('moderate_forum')));
	$context['reported_users'] = [];
	if (!allowedTo('moderate_forum'))
		return 'modcenter_reported_users';

	if (($reported_users = cache_get_data('reported_users_' . $cachekey, 90)) === null)
	{
		// By George, that means we in a position to get the reports, jolly good.
		$request = $smcFunc['db']->query('', '
			SELECT lr.id_report, lr.id_member,
				lr.num_reports, COALESCE(mem.real_name, lr.membername) AS user_name,
				COALESCE(mem.id_member, 0) AS id_user
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE lr.id_board = {int:not_a_reported_post}
				AND lr.closed = {int:not_closed}
				AND lr.ignore_all = {int:not_ignored}
			ORDER BY lr.time_updated DESC
			LIMIT 10',
			[
				'not_a_reported_post' => 0,
				'not_closed' => 0,
				'not_ignored' => 0,
			]
		);
		$reported_users = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$reported_users[] = $row;
		$smcFunc['db']->free_result($request);

		// Cache it.
		cache_put_data('reported_users_' . $cachekey, $reported_users, 90);
	}

	$context['reported_users'] = [];
	foreach ($reported_users as $row)
	{
		$context['reported_users'][] = [
			'id' => $row['id_report'],
			'report_href' => $scripturl . '?action=moderate;area=reportedmembers;report=' . $row['id_report'],
			'user' => [
				'id' => $row['id_user'],
				'name' => $row['user_name'],
				'link' => $row['id_user'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_user'] . '">' . $row['user_name'] . '</a>' : $row['user_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_user'],
			],
			'num_reports' => $row['num_reports'],
		];
	}

	return 'modcenter_reported_users';
}

/**
 * Browse all the reported users...
 */
function ReportedMembers()
{
	global $txt, $context, $scripturl, $smcFunc;

	// Set an empty var for the server response.
	$context['report_member_action'] = '';

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = [
		'title' => $txt['mc_reported_members'],
		'help' => '',
		'description' => $txt['mc_reported_members_desc'],
	];

	isAllowedTo('moderate_forum');

	// Are they wanting to view a particular report?
	if (!empty($_REQUEST['report']))
		return MemberReport();

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_members'];
	$context['sub_template'] = 'reported_members';

	// Are we viewing open or closed reports?
	$context['view_closed'] = isset($_GET['sa']) && $_GET['sa'] == 'closed' ? 1 : 0;

	// Are we doing any work?
	if ((isset($_GET['ignore']) || isset($_GET['close'])) && isset($_GET['rid']))
	{
		checkSession('get');
		$_GET['rid'] = (int) $_GET['rid'];

		// Update the report...
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}log_reported
			SET ' . (isset($_GET['ignore']) ? 'ignore_all = {int:ignore_all}' : 'closed = {int:closed}') . '
			WHERE id_report = {int:id_report}',
			[
				'ignore_all' => isset($_GET['ignore']) ? (int) $_GET['ignore'] : 0,
				'closed' => isset($_GET['close']) ? (int) $_GET['close'] : 0,
				'id_report' => $_GET['rid'],
			]
		);

		// Get the board, topic and message for this report
		$request = $smcFunc['db']->query('', '
			SELECT id_member, membername
			FROM {db_prefix}log_reported
			WHERE id_report = {int:id_report}',
			[
				'id_report' => $_GET['rid'],
			]
		);

		// Set up the data for the log...
		$extra = ['report' => $_GET['rid']];
		list($extra['member'], $extra['membername']) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		// Stick this in string format for consistency
		$extra['member'] = (string) $extra['member'];

		// Tell the user about it.
		$context['report_member_action'] = isset($_GET['ignore']) ? (!empty($_GET['ignore']) ? 'ignore' : 'unignore') : (!empty($_GET['close']) ? 'close' : 'open');

		// Log this action
		logAction($context['report_member_action'] . '_user_report', $extra);

		// Time to update.
		updateSettings(['last_mod_report_action' => time()]);
		recountOpenReports('members');
	}
	elseif (isset($_POST['close']) && isset($_POST['close_selected']))
	{
		checkSession();

		// All the ones to update...
		$toClose = [];
		foreach ($_POST['close'] as $rid)
			$toClose[] = (int) $rid;

		if (!empty($toClose))
		{
			// Get the data for each of these reports
			$request = $smcFunc['db']->query('', '
				SELECT id_report, id_member, membername
				FROM {db_prefix}log_reported
				WHERE id_report IN ({array_int:report_list})',
				[
					'report_list' => $toClose,
				]
			);

			$logs = [];
			while ($reports = $smcFunc['db']->fetch_assoc($request))
			{
				$logs[] = [
					'action' => 'close_user_report',
					'log_type' => 'moderate',
					'extra' => [
						'report' => $reports['id_report'],
						'membername' => $reports['membername'],
						'member' => (string) $reports['id_member'],
					],
				];
			}

			$smcFunc['db']->free_result($request);

			// Log the closing of all the reports
			logActions($logs);

			$smcFunc['db']->query('', '
				UPDATE {db_prefix}log_reported
				SET closed = {int:is_closed}
				WHERE id_report IN ({array_int:report_list})',
				[
					'report_list' => $toClose,
					'is_closed' => 1,
				]
			);

			// Time to update.
			updateSettings(['last_mod_report_action' => time()]);
			recountOpenReports('members');
		}

		// Go on and tell the result.
		$context['report_member_action'] = 'close_all';
	}

	// How many entries are we viewing?
	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND lr.id_board = {int:not_a_reported_post}',
		[
			'view_closed' => $context['view_closed'],
			'not_a_reported_post' => 0,
		]
	);
	list ($context['total_reports']) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// So, that means we can page index, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reportedmembers' . ($context['view_closed'] ? ';sa=closed' : ''), $_GET['start'], $context['total_reports'], 10);
	$context['start'] = $_GET['start'];

	// By George, that means we in a position to get the reports, golly good.
	$request = $smcFunc['db']->query('', '
		SELECT lr.id_report, lr.id_member, lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			COALESCE(mem.real_name, lr.membername) AS user_name, COALESCE(mem.id_member, 0) AS id_user
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.closed = {int:view_closed}
			AND lr.id_board = {int:not_a_reported_post}
		ORDER BY lr.time_updated DESC
		LIMIT {int:limit}, {int:max}',
		[
			'view_closed' => $context['view_closed'],
			'not_a_reported_post' => 0,
			'limit' => $context['start'],
			'max' => 10,
		]
	);
	$context['reports'] = [];
	$report_ids = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$report_ids[] = $row['id_report'];
		$context['reports'][$row['id_report']] = [
			'id' => $row['id_report'],
			'report_href' => $scripturl . '?action=moderate;area=reportedmembers;report=' . $row['id_report'],
			'user' => [
				'id' => $row['id_user'],
				'name' => $row['user_name'],
				'link' => $row['id_user'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_user'] . '">' . $row['user_name'] . '</a>' : $row['user_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_user'],
			],
			'comments' => [],
			'time_started' => timeformat($row['time_started']),
			'last_updated' => timeformat($row['time_updated']),
			'num_reports' => $row['num_reports'],
			'closed' => $row['closed'],
			'ignore' => $row['ignore_all']
		];
	}
	$smcFunc['db']->free_result($request);

	// Now get all the people who reported it.
	if (!empty($report_ids))
	{
		$request = $smcFunc['db']->query('', '
			SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment,
				COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lrc.membername) AS reporter
			FROM {db_prefix}log_reported_comments AS lrc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
			WHERE lrc.id_report IN ({array_int:report_list})',
			[
				'report_list' => $report_ids,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['reports'][$row['id_report']]['comments'][] = [
				'id' => $row['id_comment'],
				'message' => $row['comment'],
				'time' => timeformat($row['time_sent']),
				'member' => [
					'id' => $row['id_member'],
					'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
					'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				],
			];
		}
		$smcFunc['db']->free_result($request);
	}

	$context['report_manage_bans'] = allowedTo('manage_bans');
}

/**
 * Act as an entrace for all group related activity.
 * @todo As for most things in this file, this needs to be moved somewhere appropriate?
 */
function ModerateGroups()
{
	global $context, $user_info;

	// You need to be allowed to moderate groups...
	if ($user_info['mod_cache']['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Setup the subactions...
	$subActions = [
		'requests' => 'GroupRequests',
		'view' => 'ViewGroups',
	];

	if (!isset($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'view';
	$context['sub_action'] = $_GET['sa'];

	// Call the relevant function.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * Show a notice sent to a user.
 */
function ShowNotice()
{
	global $smcFunc, $txt, $context;

	$context['page_title'] = $txt['show_notice'];
	StoryBB\Template::set_layout('popup');
	$context['sub_template'] = 'modcenter_notice_show';
	StoryBB\Template::remove_all_layers();

	// @todo Assumes nothing needs permission more than accessing moderation center!
	$id_notice = (int) $_GET['nid'];
	$request = $smcFunc['db']->query('', '
		SELECT body, subject
		FROM {db_prefix}log_member_notices
		WHERE id_notice = {int:id_notice}',
		[
			'id_notice' => $id_notice,
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
		fatal_lang_error('no_access', false);
	list ($context['notice_body'], $context['notice_subject']) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	$context['notice_body'] = Parser::parse_bbc($context['notice_body'], false);
}

/**
 * View watched users.
 */
function ViewWatchedUsers()
{
	global $modSettings, $context, $txt, $scripturl, $sourcedir;

	// Some important context!
	$context['page_title'] = $txt['mc_watched_users_title'];
	$context['view_posts'] = isset($_GET['sa']) && $_GET['sa'] == 'post';
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

	// Get some key settings!
	$modSettings['warning_watch'] = empty($modSettings['warning_watch']) ? 1 : $modSettings['warning_watch'];

	// Put some pretty tabs on cause we're gonna be doing hot stuff here...
	$context[$context['moderation_menu_name']]['tab_data'] = [
		'title' => $txt['mc_watched_users_title'],
		'help' => '',
		'description' => $txt['mc_watched_users_desc'],
	];

	// First off - are we deleting?
	if (!empty($_REQUEST['delete']))
	{
		checkSession(!is_array($_REQUEST['delete']) ? 'get' : 'post');

		$toDelete = [];
		if (!is_array($_REQUEST['delete']))
			$toDelete[] = (int) $_REQUEST['delete'];
		else
			foreach ($_REQUEST['delete'] as $did)
				$toDelete[] = (int) $did;

		if (!empty($toDelete))
		{
			require_once($sourcedir . '/RemoveTopic.php');
			// If they don't have permission we'll let it error - either way no chance of a security slip here!
			foreach ($toDelete as $did)
				removeMessage($did);
		}
	}

	// Start preparing the list by grabbing relevant permissions.
	if (!$context['view_posts'])
	{
		$approve_query = '';
		$delete_boards = [];
	}
	else
	{
		// Still obey permissions!
		$approve_boards = boardsAllowedTo('approve_posts');
		$delete_boards = boardsAllowedTo('delete_any');

		if ($approve_boards == [0])
			$approve_query = '';
		elseif (!empty($approve_boards))
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		// Nada, zip, etc...
		else
			$approve_query = ' AND 1=0';
	}

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = [
		'id' => 'watch_user_list',
		'title' => $txt['mc_watched_users_title'] . ' - ' . ($context['view_posts'] ? $txt['mc_watched_users_post'] : $txt['mc_watched_users_member']),
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'no_items_label' => $context['view_posts'] ? $txt['mc_watched_users_no_posts'] : $txt['mc_watched_users_none'],
		'base_href' => $scripturl . '?action=moderate;area=userwatch;sa=' . ($context['view_posts'] ? 'post' : 'member'),
		'default_sort_col' => $context['view_posts'] ? '' : 'member',
		'get_items' => [
			'function' => $context['view_posts'] ? 'list_getWatchedUserPosts' : 'list_getWatchedUsers',
			'params' => [
				$approve_query,
				$delete_boards,
			],
		],
		'get_count' => [
			'function' => $context['view_posts'] ? 'list_getWatchedUserPostsCount' : 'list_getWatchedUserCount',
			'params' => [
				$approve_query,
			],
		],
		// This assumes we are viewing by user.
		'columns' => [
			'member' => [
				'header' => [
					'value' => $txt['mc_watched_users_member'],
				],
				'data' => [
					'sprintf' => [
						'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
						'params' => [
							'id' => false,
							'name' => false,
						],
					],
				],
				'sort' => [
					'default' => 'real_name',
					'reverse' => 'real_name DESC',
				],
			],
			'warning' => [
				'header' => [
					'value' => $txt['mc_watched_users_warning'],
				],
				'data' => [
					'function' => function($member) use ($scripturl)
					{
						return allowedTo('issue_warning') ? '<a href="' . $scripturl . '?action=profile;area=issue_warning;u=' . $member['id'] . '">' . $member['warning'] . '%</a>' : $member['warning'] . '%';
					},
				],
				'sort' => [
					'default' => 'warning',
					'reverse' => 'warning DESC',
				],
			],
			'posts' => [
				'header' => [
					'value' => $txt['posts'],
				],
				'data' => [
					'sprintf' => [
						'format' => '<a href="' . $scripturl . '?action=profile;area=posts;u=%1$d">%2$s</a>',
						'params' => [
							'id' => false,
							'posts' => false,
						],
					],
				],
				'sort' => [
					'default' => 'posts',
					'reverse' => 'posts DESC',
				],
			],
			'last_login' => [
				'header' => [
					'value' => $txt['mc_watched_users_last_login'],
				],
				'data' => [
					'db' => 'last_login',
				],
				'sort' => [
					'default' => 'last_login',
					'reverse' => 'last_login DESC',
				],
			],
			'last_post' => [
				'header' => [
					'value' => $txt['mc_watched_users_last_post'],
				],
				'data' => [
					'function' => function($member) use ($scripturl)
					{
						if ($member['last_post_id'])
							return '<a href="' . $scripturl . '?msg=' . $member['last_post_id'] . '">' . $member['last_post'] . '</a>';
						else
							return $member['last_post'];
					},
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=moderate;area=userwatch;sa=post',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => [
				$context['session_var'] => $context['session_id'],
			],
		],
		'additional_rows' => [
			$context['view_posts'] ? [
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="delete_selected" value="' . $txt['quickmod_delete_selected'] . '">',
				'class' => 'floatright',
			] : [],
		],
	];

	// If this is being viewed by posts we actually change the columns to call a template each time.
	if ($context['view_posts'])
	{
		StoryBB\Template::add_helper(['create_button' => 'create_button']);
		$listOptions['columns'] = [
			'posts' => [
				'data' => [
					'function' => function($post)
					{
						global $scripturl, $context, $txt;
						$template = StoryBB\Template::load_partial('modcenter_user_watch_post');
						$phpStr = StoryBB\Template::compile($template, [], 'modcenter_user_watch_post');
						return new \LightnCandy\SafeString(StoryBB\Template::prepare($phpStr, [
							'context' => $context,
							'scripturl' => $scripturl,
							'txt' => $txt,
							'post' => $post,
						]));
					},
				],
			],
		];
	}

	// Create the watched user list.
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'watch_user_list';
}

/**
 * Callback for createList().
 * @param string $approve_query Not used here
 * @return int The number of users on the watch list
 */
function list_getWatchedUserCount($approve_query)
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}',
		[
			'warning_watch' => $modSettings['warning_watch'],
		]
	);
	list ($totalMembers) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $totalMembers;
}

/**
 * Callback for createList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort things
 * @param string $approve_query A query for approving things. Not used here.
 * @param string $dummy Not used here.
 */
function list_getWatchedUsers($start, $items_per_page, $sort, $approve_query, $dummy)
{
	global $smcFunc, $txt, $modSettings, $user_info;

	$request = $smcFunc['db']->query('', '
		SELECT id_member, real_name, last_login, posts, warning
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		[
			'warning_watch' => $modSettings['warning_watch'],
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		]
	);
	$watched_users = [];
	$members = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$watched_users[$row['id_member']] = [
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'last_login' => $row['last_login'] ? timeformat($row['last_login']) : $txt['never'],
			'last_post' => $txt['not_applicable'],
			'last_post_id' => 0,
			'warning' => $row['warning'],
			'posts' => $row['posts'],
		];
		$members[] = $row['id_member'];
	}
	$smcFunc['db']->free_result($request);

	if (!empty($members))
	{
		// First get the latest messages from these users.
		$request = $smcFunc['db']->query('', '
			SELECT m.id_member, MAX(m.id_msg) AS last_post_id
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			[
				'member_list' => $members,
				'is_approved' => 1,
			]
		);
		$latest_posts = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$latest_posts[$row['id_member']] = $row['last_post_id'];

		if (!empty($latest_posts))
		{
			// Now get the time those messages were posted.
			$request = $smcFunc['db']->query('', '
				SELECT id_member, poster_time
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:message_list})',
				[
					'message_list' => $latest_posts,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				$watched_users[$row['id_member']]['last_post'] = timeformat($row['poster_time']);
				$watched_users[$row['id_member']]['last_post_id'] = $latest_posts[$row['id_member']];
			}

			$smcFunc['db']->free_result($request);
		}

		$request = $smcFunc['db']->query('', '
			SELECT MAX(m.poster_time) AS last_post, MAX(m.id_msg) AS last_post_id, m.id_member
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			[
				'member_list' => $members,
				'is_approved' => 1,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$watched_users[$row['id_member']]['last_post'] = timeformat($row['last_post']);
			$watched_users[$row['id_member']]['last_post_id'] = $row['last_post_id'];
		}
		$smcFunc['db']->free_result($request);
	}

	return $watched_users;
}

/**
 * Callback for createList().
 *
 * @param string $approve_query A query to pull only approved items
 * @return int The total number of posts by watched users
 */
function list_getWatchedUserPostsCount($approve_query)
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE mem.warning >= {int:warning_watch}
				AND {query_see_board}
				' . $approve_query,
		[
			'warning_watch' => $modSettings['warning_watch'],
		]
	);
	list ($totalMemberPosts) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $totalMemberPosts;
}

/**
 * Callback for createList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results (not used here)
 * @param string $approve_query A query to only pull approved items
 * @param int[] $delete_boards An array containing the IDs of boards we can delete posts in
 * @return array An array of info about posts by watched users
 */
function list_getWatchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards)
{
	global $smcFunc, $scripturl, $modSettings;

	$request = $smcFunc['db']->query('', '
		SELECT m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
			m.approved, mem.real_name, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning >= {int:warning_watch}
			AND {query_see_board}
			' . $approve_query . '
		ORDER BY m.id_msg DESC
		LIMIT {int:start}, {int:max}',
		[
			'warning_watch' => $modSettings['warning_watch'],
			'start' => $start,
			'max' => $items_per_page,
		]
	);
	$member_posts = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['subject'] = censorText($row['subject']);
		$row['body'] = censorText($row['body']);

		$member_posts[$row['id_msg']] = [
			'id' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'author_link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'subject' => $row['subject'],
			'body' => Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']),
			'poster_time' => timeformat($row['poster_time']),
			'approved' => $row['approved'],
			'can_delete' => $delete_boards == [0] || in_array($row['id_board'], $delete_boards),
		];
	}
	$smcFunc['db']->free_result($request);

	return $member_posts;
}

/**
 * Entry point for viewing warning related stuff.
 */
function ViewWarnings()
{
	global $context, $txt;

	$subActions = [
		'log' => ['ViewWarningLog'],
		'templateedit' => ['ModifyWarningTemplate', 'issue_warning'],
		'templates' => ['ViewWarningTemplates', 'issue_warning'],
	];

	call_integration_hook('integrate_warning_log_actions', [&$subActions]);

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && (empty($subActions[$_REQUEST['sa']][1]) || allowedTo($subActions[$_REQUEST['sa']])) ? $_REQUEST['sa'] : 'log';

	// Some of this stuff is overseas, so to speak.
	loadLanguage('Profile');

	// Setup the admin tabs.
	$context[$context['moderation_menu_name']]['tab_data'] = [
		'title' => $txt['mc_warnings'],
		'description' => $txt['mc_warnings_description'],
	];

	// Call the right function.
	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Simply put, look at the warning log!
 */
function ViewWarningLog()
{
	global $modSettings, $context, $txt, $scripturl, $sourcedir;

	// Setup context as always.
	$context['page_title'] = $txt['mc_warning_log_title'];

	loadLanguage('Modlog');

	// If we're coming from a search, get the variables.
	if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
	{
		$search_params = base64_decode(strtr($_REQUEST['params'], [' ' => '+']));
		$search_params = sbb_json_decode($search_params, true);
	}

	// This array houses all the valid search types.
	$searchTypes = [
		'member' => ['sql' => 'member_name_col', 'label' => $txt['profile_warning_previous_issued']],
		'recipient' => ['sql' => 'recipient_name', 'label' => $txt['mc_warnings_recipient']],
	];

	// Do the column stuff!
	$sort_types = [
		'member' => 'member_name_col',
		'recipient' => 'recipient_name',
	];

	// Setup the direction stuff...
	$context['order'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'member';

	if (!isset($search_params['string']) || (!empty($_REQUEST['search']) && $search_params['string'] != $_REQUEST['search']))
		$search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
	else
		$search_params_string = $search_params['string'];

	if (isset($_REQUEST['search_type']) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
		$search_params_type = isset($_REQUEST['search_type']) && isset($searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[$context['order']]) ? $context['order'] : 'member');
	else
		$search_params_type = $search_params['type'];

	$search_params = [
		'string' => $search_params_string,
		'type' => $search_params_type,
	];

	$context['url_start'] = '?action=moderate;area=warnings;sa=log;sort=' . $context['order'];

	// Setup the search context.
	$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(json_encode($search_params));
	$context['search'] = [
		'string' => $search_params['string'],
		'type' => $search_params['type'],
		'label' => $searchTypes[$search_params_type]['label'],
	];

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = [
		'id' => 'warning_list',
		'title' => $txt['mc_warning_log_title'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'no_items_label' => $txt['mc_warnings_none'],
		'base_href' => $scripturl . '?action=moderate;area=warnings;sa=log;' . $context['session_var'] . '=' . $context['session_id'],
		'default_sort_col' => 'time',
		'get_items' => [
			'function' => 'list_getWarnings',
		],
		'get_count' => [
			'function' => 'list_getWarningCount',
		],
		// This assumes we are viewing by user.
		'columns' => [
			'issuer' => [
				'header' => [
					'value' => $txt['profile_warning_previous_issued'],
				],
				'data' => [
					'db' => 'issuer_link',
				],
				'sort' => [
					'default' => 'member_name_col',
					'reverse' => 'member_name_col DESC',
				],
			],
			'recipient' => [
				'header' => [
					'value' => $txt['mc_warnings_recipient'],
				],
				'data' => [
					'db' => 'recipient_link',
				],
				'sort' => [
					'default' => 'recipient_name',
					'reverse' => 'recipient_name DESC',
				],
			],
			'time' => [
				'header' => [
					'value' => $txt['profile_warning_previous_time'],
				],
				'data' => [
					'db' => 'time',
				],
				'sort' => [
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				],
			],
			'reason' => [
				'header' => [
					'value' => $txt['profile_warning_previous_reason'],
				],
				'data' => [
					'function' => function($rowData) use ($scripturl, $txt)
					{
						$output = '
							<div class="floatleft">
								' . $rowData['reason'] . '
							</div>';

						if (!empty($rowData['id_notice']))
							$output .= '
								&nbsp;<a href="' . $scripturl . '?action=moderate;area=notice;nid=' . $rowData['id_notice'] . '" onclick="return reqOverlayDiv(this.href, \'' . $txt['show_notice'] . '\', \'warn.png\');" target="_blank" rel="noopener" title="' . $txt['profile_warning_previous_notice'] . '" class="main_icons filter centericon"></a>';
						return $output;
					},
				],
			],
			'points' => [
				'header' => [
					'value' => $txt['profile_warning_previous_level'],
				],
				'data' => [
					'db' => 'counter',
				],
			],
		],
		'form' => [
			'href' => $scripturl . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => [
				$context['session_var'] => $context['session_id'],
				'params' => false
			],
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value' => '
					' . $txt['modlog_search'] . ':
					<input type="text" name="search" size="18" value="' . StringLibrary::escape($context['search']['string']) . '">
					<input type="submit" name="is_search" value="' . $txt['modlog_go'] . '">',
				'class' => 'floatright',
			],
		],
	];

	// Create the watched user list.
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'warning_list';
}

/**
 * Callback for createList().
 * @return int The total number of warnings that have been issued
 */
function list_getWarningCount()
{
	global $smcFunc;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:warning}',
		[
			'warning' => 'warning',
		]
	);
	list ($totalWarns) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $totalWarns;
}

/**
 * Callback for createList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of data about warning log entries
 */
function list_getWarnings($start, $items_per_page, $sort)
{
	global $smcFunc, $scripturl;

	$request = $smcFunc['db']->query('', '
		SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name_col,
			COALESCE(mem2.id_member, 0) AS id_recipient, COALESCE(mem2.real_name, lc.recipient_name) AS recipient_name,
			lc.log_time, lc.body, lc.id_notice, lc.counter
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = lc.id_recipient)
		WHERE lc.comment_type = {string:warning}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		[
			'warning' => 'warning',
			'start' => $start,
			'max' => $items_per_page,
			'sort' => $sort,
		]
	);
	$warnings = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$warnings[] = [
			'issuer_link' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name_col'] . '</a>') : $row['member_name_col'],
			'recipient_link' => $row['id_recipient'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_recipient'] . '">' . $row['recipient_name'] . '</a>') : $row['recipient_name'],
			'time' => timeformat($row['log_time']),
			'reason' => $row['body'],
			'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
			'id_notice' => $row['id_notice'],
		];
	}
	$smcFunc['db']->free_result($request);

	return $warnings;
}

/**
 * Load all the warning templates.
 */
function ViewWarningTemplates()
{
	global $smcFunc, $modSettings, $context, $txt, $scripturl, $sourcedir, $user_info;

	// Submitting a new one?
	if (isset($_POST['add']))
		return ModifyWarningTemplate();
	elseif (isset($_POST['delete']) && !empty($_POST['deltpl']))
	{
		checkSession();
		validateToken('mod-wt');

		// Log the actions.
		$request = $smcFunc['db']->query('', '
			SELECT recipient_name
			FROM {db_prefix}log_comments
			WHERE id_comment IN ({array_int:delete_ids})
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			[
				'delete_ids' => $_POST['deltpl'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => $user_info['id'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
			logAction('delete_warn_template', ['template' => $row['recipient_name']]);
		$smcFunc['db']->free_result($request);

		// Do the deletes.
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment IN ({array_int:delete_ids})
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			[
				'delete_ids' => $_POST['deltpl'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => $user_info['id'],
			]
		);
	}

	// Setup context as always.
	$context['page_title'] = $txt['mc_warning_templates_title'];

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = [
		'id' => 'warning_template_list',
		'title' => $txt['mc_warning_templates_title'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'no_items_label' => $txt['mc_warning_templates_none'],
		'base_href' => $scripturl . '?action=moderate;area=warnings;sa=templates;' . $context['session_var'] . '=' . $context['session_id'],
		'default_sort_col' => 'title',
		'get_items' => [
			'function' => 'list_getWarningTemplates',
		],
		'get_count' => [
			'function' => 'list_getWarningTemplateCount',
		],
		// This assumes we are viewing by user.
		'columns' => [
			'title' => [
				'header' => [
					'value' => $txt['mc_warning_templates_name'],
				],
				'data' => [
					'sprintf' => [
						'format' => '<a href="' . $scripturl . '?action=moderate;area=warnings;sa=templateedit;tid=%1$d">%2$s</a>',
						'params' => [
							'id_comment' => false,
							'title' => false,
							'body' => false,
						],
					],
				],
				'sort' => [
					'default' => 'template_title',
					'reverse' => 'template_title DESC',
				],
			],
			'creator' => [
				'header' => [
					'value' => $txt['mc_warning_templates_creator'],
				],
				'data' => [
					'db' => 'creator',
				],
				'sort' => [
					'default' => 'creator_name',
					'reverse' => 'creator_name DESC',
				],
			],
			'time' => [
				'header' => [
					'value' => $txt['mc_warning_templates_time'],
				],
				'data' => [
					'db' => 'time',
				],
				'sort' => [
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				],
			],
			'delete' => [
				'header' => [
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				],
				'data' => [
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="deltpl[]" value="' . $rowData['id_comment'] . '">';
					},
					'class' => 'centercol',
				],
			],
		],
		'form' => [
			'href' => $scripturl . '?action=moderate;area=warnings;sa=templates',
			'token' => 'mod-wt',
		],
		'additional_rows' => [
			[
				'position' => 'bottom_of_list',
				'value' => '&nbsp;<input type="submit" name="delete" value="' . $txt['mc_warning_template_delete'] . '" data-confirm="' . $txt['mc_warning_template_delete_confirm'] . '" class="you_sure">',
			],
			[
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="add" value="' . $txt['mc_warning_template_add'] . '">',
			],
		],
	];

	// Create the watched user list.
	createToken('mod-wt');
	createList($listOptions);

	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'warning_template_list';
}

/**
  * Callback for createList().
  * @return int The total number of warning templates
  */
function list_getWarningTemplateCount()
{
	global $smcFunc, $user_info;

	$request = $smcFunc['db']->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:warntpl}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})',
		[
			'warntpl' => 'warntpl',
			'generic' => 0,
			'current_member' => $user_info['id'],
		]
	);
	list ($totalWarns) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	return $totalWarns;
}

/**
 * Callback for createList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array An arrray of info about the available warning templates
 */
function list_getWarningTemplates($start, $items_per_page, $sort)
{
	global $smcFunc, $scripturl, $user_info;

	$request = $smcFunc['db']->query('', '
		SELECT lc.id_comment, COALESCE(mem.id_member, 0) AS id_member,
			COALESCE(mem.real_name, lc.member_name) AS creator_name, recipient_name AS template_title,
			lc.log_time, lc.body
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.comment_type = {string:warntpl}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		[
			'warntpl' => 'warntpl',
			'generic' => 0,
			'current_member' => $user_info['id'],
		]
	);
	$templates = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$templates[] = [
			'id_comment' => $row['id_comment'],
			'creator' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['creator_name'] . '</a>') : $row['creator_name'],
			'time' => timeformat($row['log_time']),
			'title' => $row['template_title'],
			'body' => StringLibrary::escape($row['body']),
		];
	}
	$smcFunc['db']->free_result($request);

	return $templates;
}

/**
 * Edit a warning template.
 */
function ModifyWarningTemplate()
{
	global $smcFunc, $context, $txt, $user_info, $sourcedir;

	$context['id_template'] = isset($_REQUEST['tid']) ? (int) $_REQUEST['tid'] : 0;
	$context['is_edit'] = $context['id_template'];

	// Standard template things.
	$context['page_title'] = $context['is_edit'] ? $txt['mc_warning_template_modify'] : $txt['mc_warning_template_add'];
	$context['sub_template'] = 'modcenter_warning_template_edit';
	$context[$context['moderation_menu_name']]['current_subsection'] = 'templates';

	// Defaults.
	$context['template_data'] = [
		'title' => '',
		'body' => $txt['mc_warning_template_body_default'],
		'personal' => false,
		'can_edit_personal' => true,
	];

	// If it's an edit load it.
	if ($context['is_edit'])
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_member, id_recipient, recipient_name AS template_title, body
			FROM {db_prefix}log_comments
			WHERE id_comment = {int:id}
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			[
				'id' => $context['id_template'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => $user_info['id'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['template_data'] = [
				'title' => $row['template_title'],
				'body' => StringLibrary::escape($row['body']),
				'personal' => $row['id_recipient'],
				'can_edit_personal' => $row['id_member'] == $user_info['id'],
			];
		}
		$smcFunc['db']->free_result($request);
	}

	// Wait, we are saving?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('mod-wt');

		// To check the BBC is pretty good...
		require_once($sourcedir . '/Subs-Post.php');

		// Bit of cleaning!
		$_POST['template_body'] = trim($_POST['template_body']);
		$_POST['template_title'] = trim($_POST['template_title']);

		// Need something in both boxes.
		if (!empty($_POST['template_body']) && !empty($_POST['template_title']))
		{
			// Safety first.
			$_POST['template_title'] = StringLibrary::escape($_POST['template_title']);

			// Clean up BBC.
			preparsecode($_POST['template_body']);
			// But put line breaks back!
			$_POST['template_body'] = strtr($_POST['template_body'], ['<br>' => "\n"]);

			// Is this personal?
			$recipient_id = !empty($_POST['make_personal']) ? $user_info['id'] : 0;

			// If we are this far it's save time.
			if ($context['is_edit'])
			{
				// Simple update...
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}log_comments
					SET id_recipient = {int:personal}, recipient_name = {string:title}, body = {string:body}
					WHERE id_comment = {int:id}
						AND comment_type = {string:warntpl}
						AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})'.
						($recipient_id ? ' AND id_member = {int:current_member}' : ''),
					[
						'personal' => $recipient_id,
						'title' => $_POST['template_title'],
						'body' => $_POST['template_body'],
						'id' => $context['id_template'],
						'warntpl' => 'warntpl',
						'generic' => 0,
						'current_member' => $user_info['id'],
					]
				);

				// If it wasn't visible and now is they've effectively added it.
				if ($context['template_data']['personal'] && !$recipient_id)
					logAction('add_warn_template', ['template' => $_POST['template_title']]);
				// Conversely if they made it personal it's a delete.
				elseif (!$context['template_data']['personal'] && $recipient_id)
					logAction('delete_warn_template', ['template' => $_POST['template_title']]);
				// Otherwise just an edit.
				else
					logAction('modify_warn_template', ['template' => $_POST['template_title']]);
			}
			else
			{
				$smcFunc['db']->insert('',
					'{db_prefix}log_comments',
					[
						'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
						'recipient_name' => 'string-255', 'body' => 'string-65535', 'log_time' => 'int',
					],
					[
						$user_info['id'], $user_info['name'], 'warntpl', $recipient_id,
						$_POST['template_title'], $_POST['template_body'], time(),
					],
					['id_comment']
				);

				logAction('add_warn_template', ['template' => $_POST['template_title']]);
			}

			// Get out of town...
			redirectexit('action=moderate;area=warnings;sa=templates');
		}
		else
		{
			$context['warning_errors'] = [];
			$context['template_data']['title'] = !empty($_POST['template_title']) ? $_POST['template_title'] : '';
			$context['template_data']['body'] = !empty($_POST['template_body']) ? $_POST['template_body'] : $txt['mc_warning_template_body_default'];
			$context['template_data']['personal'] = !empty($_POST['make_personal']);
			if (empty($_POST['template_title']))
				$context['warning_errors'][] = $txt['mc_warning_template_error_no_title'];
			if (empty($_POST['template_body']))
				$context['warning_errors'][] = $txt['mc_warning_template_error_no_body'];
		}
	}

	createToken('mod-wt');
}
