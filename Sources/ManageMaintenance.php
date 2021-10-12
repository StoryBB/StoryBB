<?php

/**
 * Forum maintenance. Important stuff.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Helper\Autocomplete;

/**
 * Main dispatcher, the maintenance access point.
 * This, as usual, checks permissions, loads language files, and forwards to the actual workers.
 */
function ManageMaintenance()
{
	global $txt, $context;

	// You absolutely must be an admin by here!
	isAllowedTo('admin_forum');

	// Need something to talk about?
	loadLanguage('ManageMaintenance');

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = [
		'title' => $txt['maintain_title'],
		'description' => $txt['maintain_info'],
		'tabs' => [
			'routine' => [],
			'members' => [],
			'topics' => [],
		],
	];

	// So many things you can do - but frankly I won't let you - just these!
	$subActions = [
		'routine' => [
			'function' => 'MaintainRoutine',
			'template' => 'admin_maintain_routine',
			'activities' => [
				'repair' => 'MaintainFindFixErrors',
				'recount' => 'AdminBoardRecount',
				'cleancache' => 'MaintainCleanCache',
				'cleantemplatecache' => 'MaintainCleanTemplateCache',
			],
		],
		'members' => [
			'function' => 'MaintainMembers',
			'template' => 'admin_maintain_members',
			'activities' => [
				'reattribute' => 'MaintainReattributePosts',
				'purgeinactive' => 'MaintainPurgeInactiveMembers',
				'recountposts' => 'MaintainRecountPosts',
			],
		],
		'topics' => [
			'function' => 'MaintainTopics',
			'template' => 'admin_maintain_topics',
			'activities' => [
				'massmove' => 'MaintainMassMoveTopics',
				'pruneold' => 'MaintainRemoveOldPosts',
				'olddrafts' => 'MaintainRemoveOldDrafts',
			],
		],
	];

	routing_integration_hook('integrate_manage_maintenance', [&$subActions]);

	// Yep, sub-action time!
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$subAction = $_REQUEST['sa'];
	else
		$subAction = 'routine';

	// Doing something special?
	if (isset($_REQUEST['activity']) && isset($subActions[$subAction]['activities'][$_REQUEST['activity']]))
		$activity = $_REQUEST['activity'];

	// Set a few things.
	$context['page_title'] = $txt['maintain_title'];
	$context['sub_action'] = $subAction;
	$context['sub_template'] = !empty($subActions[$subAction]['template']) ? $subActions[$subAction]['template'] : '';

	// Finally fall through to what we are doing.
	call_helper($subActions[$subAction]['function']);

	// Any special activity?
	if (isset($activity))
		call_helper($subActions[$subAction]['activities'][$activity]);

	// Create a maintenance token.  Kinda hard to do it any other way.
	createToken('admin-maint');
}

/**
 * Supporting function for the database maintenance area.
 */
function MaintainDatabase()
{
	global $context, $db_type, $modSettings, $smcFunc, $txt;
}

/**
 * Supporting function for the routine maintenance area.
 */
function MaintainRoutine()
{
}

/**
 * Supporting function for the members maintenance area.
 */
function MaintainMembers()
{
	global $context, $smcFunc, $txt;

	// Get membergroups - for deleting members and the like.
	$result = $smcFunc['db']->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		[
		]
	);
	$context['membergroups'] = [
		[
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		],
	];
	while ($row = $smcFunc['db']->fetch_assoc($result))
	{
		$context['membergroups'][] = [
			'id' => $row['id_group'],
			'name' => $row['group_name']
		];
	}
	$smcFunc['db']->free_result($result);

	Autocomplete::init('member', '#to');
	Autocomplete::init('character', '#to_char');
}

/**
 * Supporting function for the topics maintenance area.
 */
function MaintainTopics()
{
	global $context, $smcFunc, $txt, $sourcedir;

	// Let's load up the boards in case they are useful.
	$result = $smcFunc['db']->query('order_by_board_order', '
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:blank_redirect}',
		[
			'blank_redirect' => '',
		]
	);
	$context['categories'] = [];
	while ($row = $smcFunc['db']->fetch_assoc($result))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = [
				'name' => $row['cat_name'],
				'boards' => []
			];

		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level']
		];
	}
	$smcFunc['db']->free_result($result);

	$context['split_categories'] = array_chunk($context['categories'], ceil(count($context['categories']) / 2), true);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($context['categories']);
}

/**
 * Find and fix all errors on the forum.
 */
function MaintainFindFixErrors()
{
	global $sourcedir;

	// Honestly, this should be done in the sub function.
	validateToken('admin-maint');

	require_once($sourcedir . '/RepairBoards.php');
	RepairBoards();
}

/**
 * Wipes the whole cache directory.
 * This only applies to StoryBB's own cache directory, though.
 */
function MaintainCleanCache()
{
	global $txt;

	checkSession();
	validateToken('admin-maint');

	// Just wipe the whole cache directory!
	clean_cache();

	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_cache']));
}

/**
 * Removes cached templates.
 */
function MaintainCleanTemplateCache()
{
	global $txt;

	checkSession();
	validateToken('admin-maint');

	// Just wipe the whole cache directory!
	StoryBB\Template\Cache::clean();

	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_template_cache']));
}

/**
 * Recount many forum totals that can be recounted automatically without harm.
 * it requires the admin_forum permission.
 * It shows the maintain_forum admin area.
 *
 * Totals recounted:
 * - fixes for topics with wrong num_replies.
 * - updates for num_posts and num_topics of all boards.
 * - recounts instant_messages but not unread_messages.
 * - repairs messages pointing to boards with topics pointing to other boards.
 * - updates the last message posted in boards and children.
 * - updates member count, latest member, topic count, and message count.
 *
 * The function redirects back to ?action=admin;area=maintain when complete.
 * It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
 */
function AdminBoardRecount()
{
	global $txt, $context, $modSettings, $sourcedir;
	global $time_start, $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('request');

	// validate the request or the loop
	if (!isset($_REQUEST['step']))
		validateToken('admin-maint');
	else
		validateToken('admin-boardrecount');

	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;
	$context['sub_template'] = 'not_done';

	// Try for as much time as possible.
	@set_time_limit(600);

	// Step the number of topics at a time so things don't time out...
	$request = $smcFunc['db']->query('', '
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		[
		]
	);
	list ($max_topics) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	$increment = min(max(50, ceil($max_topics / 4)), 2000);
	if (empty($_REQUEST['start']))
		$_REQUEST['start'] = 0;

	$total_steps = 8;

	// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
	if (empty($_REQUEST['step']))
	{
		$_REQUEST['step'] = 0;

		while ($_REQUEST['start'] < $max_topics)
		{
			// Recount approved messages
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
					CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
				[
					'is_approved' => 1,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}topics
					SET num_replies = {int:num_replies}
					WHERE id_topic = {int:id_topic}',
					[
						'num_replies' => $row['real_num_replies'],
						'id_topic' => $row['id_topic'],
					]
				);
			$smcFunc['db']->free_result($request);

			// Recount unapproved messages
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
					COUNT(mu.id_msg) AS real_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
				[
					'not_approved' => 0,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:unapproved_posts}
					WHERE id_topic = {int:id_topic}',
					[
						'unapproved_posts' => $row['real_unapproved_posts'],
						'id_topic' => $row['id_topic'],
					]
				);
			$smcFunc['db']->free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the post count of each board.
	if ($_REQUEST['step'] <= 1)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}boards
				SET num_posts = {int:num_posts}
				WHERE redirect = {string:redirect}',
				[
					'num_posts' => 0,
					'redirect' => '',
				]
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				[
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 1,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					[
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					]
				);
			$smcFunc['db']->free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the topic count of each board.
	if ($_REQUEST['step'] <= 2)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}boards
				SET num_topics = {int:num_topics}',
				[
					'num_topics' => 0,
				]
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				[
					'is_approved' => 1,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					[
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					]
				);
			$smcFunc['db']->free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved post count of each board.
	if ($_REQUEST['step'] <= 3)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}boards
				SET unapproved_posts = {int:unapproved_posts}',
				[
					'unapproved_posts' => 0,
				]
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				[
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 0,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					[
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					]
				);
			$smcFunc['db']->free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved topic count of each board.
	if ($_REQUEST['step'] <= 4)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}boards
				SET unapproved_topics = {int:unapproved_topics}',
				[
					'unapproved_topics' => 0,
				]
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				[
					'is_approved' => 0,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					[
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					]
				);
			$smcFunc['db']->free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Get all members with wrong number of personal messages.
	if ($_REQUEST['step'] <= 5)
	{
		$request = $smcFunc['db']->query('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.instant_messages) AS instant_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
			[
				'is_not_deleted' => 0,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
			updateMemberData($row['id_member'], ['instant_messages' => $row['real_num']]);
		$smcFunc['db']->free_result($request);

		$request = $smcFunc['db']->query('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.unread_messages) AS unread_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
			[
				'is_not_deleted' => 0,
				'is_not_read' => 0,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
			updateMemberData($row['id_member'], ['unread_messages' => $row['real_num']]);
		$smcFunc['db']->free_result($request);

		if (microtime(true) - $time_start > 3)
		{
			createToken('admin-boardrecount');
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

			$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . $context['session_var'] . '=' . $context['session_id'];
			$context['continue_percent'] = round(700 / $total_steps);

			return;
		}
	}

	// Any messages pointing to the wrong board?
	if ($_REQUEST['step'] <= 6)
	{
		while ($_REQUEST['start'] < $modSettings['maxMsgID'])
		{
			$request = $smcFunc['db']->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
				WHERE m.id_msg > {int:id_msg_min}
					AND m.id_msg <= {int:id_msg_max}',
				[
					'id_msg_min' => $_REQUEST['start'],
					'id_msg_max' => $_REQUEST['start'] + $increment,
				]
			);
			$boards = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$boards[$row['id_board']][] = $row['id_msg'];
			$smcFunc['db']->free_result($request);

			foreach ($boards as $board_id => $messages)
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}messages
					SET id_board = {int:id_board}
					WHERE id_msg IN ({array_int:id_msg_array})',
					[
						'id_msg_array' => $messages,
						'id_board' => $board_id,
					]
				);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $modSettings['maxMsgID']) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the latest message of each board.
	$request = $smcFunc['db']->query('', '
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		[
			'is_approved' => 1,
		]
	);
	$realBoardCounts = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	$smcFunc['db']->free_result($request);

	$request = $smcFunc['db']->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		[
		]
	);
	$resort_me = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	$smcFunc['db']->free_result($request);

	krsort($resort_me);

	$lastModifiedMsg = [];
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];

			// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					[
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					]
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}

	// Update all the basic statistics.
	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// Finally, update the latest event times.
	require_once($sourcedir . '/ScheduledTasks.php');
	CalculateNextTrigger();

	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_recount']));
	redirectexit('action=admin;area=maintain;sa=routine');
}

/**
 * Re-attribute posts.
 */
function MaintainReattributePosts()
{
	global $sourcedir, $context, $txt, $smcFunc;

	checkSession();

	// Are we doing the member or a character?
	if (!isset($_POST['reattribute_type']) || $_POST['reattribute_type'] == 'member')
	{
		$memID = isset($_POST['to']) ? (int) $_POST['to'] : 0;
		$request = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member = {int:memID}',
			[
				'memID' => $memID,
			]
		);
		if ($smcFunc['db']->num_rows($request) == 0)
			fatal_lang_error('reattribute_cannot_find_member');

		$smcFunc['db']->free_result($request);

		// The OOC character ID can be looked up inside reattributePosts.
		$characterID = false;
	}
	else
	{
		// We're given a character ID - we need to find the member ID as well.
		$characterID = isset($_POST['to_char']) ? (int) $_POST['to_char'] : 0;

		$request = $smcFunc['db']->query('', '
			SELECT mem.id_member, chars.id_character
			FROM {db_prefix}characters AS chars
				INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
			WHERE id_character = {int:characterID}',
			[
				'characterID' => $characterID,
			]
		);
		if ($smcFunc['db']->num_rows($request) == 0)
			fatal_lang_error('reattribute_cannot_find_member');

		list ($memID, $characterID) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
	}

	$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
	$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

	// Now call the reattribute function.
	require_once($sourcedir . '/Subs-Members.php');
	reattributePosts($memID, $characterID, $email, $membername, !empty($_POST['posts']));

	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_reattribute_posts']));
}

/**
 * Removing old members. Done and out!
 * @todo refactor
 */
function MaintainPurgeInactiveMembers()
{
	global $sourcedir, $context, $smcFunc, $txt;

	$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
	if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
	{
		checkSession();
		validateToken('admin-maint');

		$groups = [];
		foreach ($_POST['groups'] as $id => $dummy)
			$groups[] = (int) $id;
		$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
		$where_vars = [
			'time_limit' => $time_limit,
		];
		if ($_POST['del_type'] == 'activated')
		{
			$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
			$where_vars['is_activated'] = 0;
		}
		else
			$where = 'mem.last_login < {int:time_limit} AND (mem.last_login != 0 OR mem.date_registered < {int:time_limit})';

		// Need to get *all* groups then work out which (if any) we avoid.
		$request = $smcFunc['db']->query('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups',
			[
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Avoid this one?
			if (!in_array($row['id_group'], $groups))
			{
				$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
				$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
			}
		}
		$smcFunc['db']->free_result($request);

		// If we have ungrouped unselected we need to avoid those guys.
		if (!in_array(0, $groups))
		{
			$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
			$where_vars['blank_add_groups'] = '';
		}

		// Select all the members we're about to murder/remove...
		$request = $smcFunc['db']->query('', '
			SELECT mem.id_member, COALESCE(m.id_member, 0) AS is_mod
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
			WHERE ' . $where,
			$where_vars
		);
		$members = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!$row['is_mod'] || !in_array(3, $groups))
				$members[] = $row['id_member'];
		}
		$smcFunc['db']->free_result($request);

		require_once($sourcedir . '/Subs-Members.php');
		deleteMembers($members);
	}

	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_members']));
	createToken('admin-maint');
}

/**
 * Removing old posts doesn't take much as we really pass through.
 */
function MaintainRemoveOldPosts()
{
	global $sourcedir;

	validateToken('admin-maint');

	// Actually do what we're told!
	require_once($sourcedir . '/RemoveTopic.php');
	RemoveOldTopics2();
}

/**
 * Removing old drafts
 */
function MaintainRemoveOldDrafts()
{
	global $sourcedir, $smcFunc, $txt;

	validateToken('admin-maint');

	$drafts = [];

	// Find all of the old drafts
	$request = $smcFunc['db']->query('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		[
			'poster_time_old' => time() - (86400 * $_POST['draftdays']),
		]
	);

	while ($row = $smcFunc['db']->fetch_row($request))
		$drafts[] = (int) $row[0];
	$smcFunc['db']->free_result($request);

	// If we have old drafts, remove them
	if (count($drafts) > 0)
	{
		require_once($sourcedir . '/Drafts.php');
		DeleteDraft($drafts, false);
	}

	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_old_drafts']));
}

/**
 * Moves topics from one board to another.
 *
 * @uses not_done template to pause the process.
 */
function MaintainMassMoveTopics()
{
	global $smcFunc, $sourcedir, $context, $txt;

	// Only admins.
	isAllowedTo('admin_forum');

	checkSession('request');
	validateToken('admin-maint');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_post_data'] = '';
	$context['continue_get_data'] = '';
	$context['sub_template'] = 'not_done';
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['start_time'] = time();

	// First time we do this?
	$id_board_from = isset($_REQUEST['id_board_from']) ? (int) $_REQUEST['id_board_from'] : 0;
	$id_board_to = isset($_REQUEST['id_board_to']) ? (int) $_REQUEST['id_board_to'] : 0;
	$max_days = isset($_REQUEST['maxdays']) ? (int) $_REQUEST['maxdays'] : 0;
	$locked = isset($_POST['move_type_locked']) || isset($_GET['locked']);
	$sticky = isset($_POST['move_type_sticky']) || isset($_GET['sticky']);

	// No boards then this is your stop.
	if (empty($id_board_from) || empty($id_board_to))
		return;

	// The big WHERE clause
	$conditions = 'WHERE t.id_board = {int:id_board_from}
		AND t.is_moved = {int:moved}';

	// DB parameters
	$params = [
		'id_board_from' => $id_board_from,
		'moved' => 1,
	];

	// Only moving topics not posted in for x days?
	if (!empty($max_days))
	{
		$conditions .= '
			AND m.poster_time < {int:poster_time}';
		$params['poster_time'] = time() - 3600 * 24 * $max_days;
	}

	// Moving locked topics?
	if ($locked)
	{
		$conditions .= '
			AND t.locked = {int:locked}';
		$params['locked'] = 1;
	}

	// What about sticky topics?
	if ($sticky)
	{
		$conditions .= '
			AND t.is_sticky = {int:sticky}';
		$params['sticky'] = 1;
	}

	// How many topics are we converting?
	if (!isset($_REQUEST['totaltopics']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)' .
			$conditions,
			$params
		);
		list ($total_topics) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
	}
	else
		$total_topics = (int) $_REQUEST['totaltopics'];

	// Seems like we need this here.
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';max_days=' . $max_days;

	if ($locked)
		$context['continue_get_data'] .= ';locked';

	if ($sticky)
		$context['continue_get_data'] .= ';sticky';

	$context['continue_get_data'] .= ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

	// We have topics to move so start the process.
	if (!empty($total_topics))
	{
		while ($context['start'] <= $total_topics)
		{
			// Lets get the topics.
			$request = $smcFunc['db']->query('', '
				SELECT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
				' . $conditions . '
				LIMIT 10',
				$params
			);

			// Get the ids.
			$topics = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
				$topics[] = $row['id_topic'];

			// Just return if we don't have any topics left to move.
			if (empty($topics))
			{
				cache_put_data('board-' . $id_board_from, null, 120);
				cache_put_data('board-' . $id_board_to, null, 120);
				session_flash('success', sprintf($txt['maintain_done'], $txt['move_topics_maintenance']));
				redirectexit('action=admin;area=maintain;sa=topics');
			}

			// Lets move them.
			require_once($sourcedir . '/MoveTopic.php');
			moveTopics($topics, $id_board_to);

			// We've done at least ten more topics.
			$context['start'] += 10;

			// Lets wait a while.
			if (time() - $context['start_time'] > 3)
			{
				// What's the percent?
				$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

				// Let the template system do it's thang.
				return;
			}
		}
	}

	// Don't confuse admins by having an out of date cache.
	cache_put_data('board-' . $id_board_from, null, 120);
	cache_put_data('board-' . $id_board_to, null, 120);

	session_flash('success', sprintf($txt['maintain_done'], $txt['move_topics_maintenance']));
	redirectexit('action=admin;area=maintain;sa=topics');
}

/**
 * Recalculate all members post counts
 * it requires the admin_forum permission.
 *
 * - recounts all posts for members found in the message table
 * - updates the members post count record in the members table
 * - honors the boards post count flag
 * - does not count posts in the recycle bin
 * - zeros post counts for all members with no posts in the message table
 * - runs as a delayed loop to avoid server overload
 * - uses the not_done template in Admin.template
 *
 * The function redirects back to action=admin;area=maintain;sa=members when complete.
 * It is accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
 */
function MaintainRecountPosts()
{
	global $txt, $context, $modSettings, $smcFunc;

	// You have to be allowed in here
	isAllowedTo('admin_forum');
	checkSession('request');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_get_data'] = '';
	$context['sub_template'] = 'not_done';

	// init
	$increment = 200;
	$_REQUEST['start'] = !isset($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	// Ask for some extra time, on big boards this may take a bit
	@set_time_limit(600);

	// Only run this query if we don't have the total number of members that have posted
	if (!isset($_SESSION['total_members']))
	{
		validateToken('admin-maint');

		$request = $smcFunc['db']->query('', '
			SELECT COUNT(DISTINCT m.id_member)
			FROM {db_prefix}messages AS m
			JOIN {db_prefix}boards AS b on m.id_board = b.id_board
			WHERE m.id_member != 0
				AND b.count_posts = 0',
			[
			]
		);

		// save it so we don't do this again for this task
		list ($_SESSION['total_members']) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
	}
	else
		validateToken('admin-recountposts');

	// Lets get a group of members and determine their post count (from the boards that have post count enabled of course).
	$request = $smcFunc['db']->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ m.id_member, COUNT(m.id_member) AS posts
		FROM {db_prefix}messages AS m 
			INNER JOIN {db_prefix}boards AS b ON m.id_board = b.id_board
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			' . (!empty($modSettings['recycle_enable']) ? ' AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member
		LIMIT {int:start}, {int:number}',
		[
			'start' => $_REQUEST['start'],
			'number' => $increment,
			'recycle' => $modSettings['recycle_board'],
			'zero' => 0,
		]
	);
	$total_rows = $smcFunc['db']->num_rows($request);

	// Update the post count for this group
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET posts = {int:posts}
			WHERE id_member = {int:row}',
			[
				'row' => $row['id_member'],
				'posts' => $row['posts'],
			]
		);
	}
	$smcFunc['db']->free_result($request);

	// Now to fix the post counts for characters.
	$request = $smcFunc['db']->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ m.id_character, COUNT(m.id_character) AS posts
		FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
		WHERE m.id_character != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_character
		LIMIT {int:start}, {int:number}',
		[
			'start' => $_REQUEST['start'],
			'number' => $increment,
			'recycle' => $modSettings['recycle_board'],
			'zero' => 0,
		]
	);
	$total_rows = $smcFunc['db']->num_rows($request);

	// Update the post count for this group
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET posts = {int:posts}
			WHERE id_character = {int:row}',
			[
				'row' => $row['id_character'],
				'posts' => $row['posts'],
			]
		);
	}
	$smcFunc['db']->free_result($request);

	// Continue?
	if ($total_rows == $increment)
	{
		$_REQUEST['start'] += $increment;
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['continue_percent'] = round(100 * $_REQUEST['start'] / $_SESSION['total_members']);

		createToken('admin-recountposts');
		$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-recountposts_token_var'] . '" value="' . $context['admin-recountposts_token'] . '">';

		if (function_exists('apache_reset_timeout'))
			apache_reset_timeout();
		return;
	}

	// final steps ... made more difficult since we don't yet support sub-selects on joins
	// place all members who have posts in the message table in a temp table
	$createTemporary = $smcFunc['db']->query('', '
		CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountposts (
			id_member mediumint(8) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_member)
		)
		SELECT m.id_member
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON m.id_board = b.id_board
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			' . (!empty($modSettings['recycle_enable']) ? ' AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member',
		[
			'zero' => 0,
			'string_zero' => '0',
			'db_error_skip' => true,
			'recycle' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
		]
	) !== false;

	if ($createTemporary)
	{
		// outer join the members table on the temporary table finding the members that have a post count but no posts in the message table
		$request = $smcFunc['db']->query('', '
			SELECT mem.id_member, mem.posts
			FROM {db_prefix}members AS mem
			LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
			ON res.id_member = mem.id_member
			WHERE res.id_member IS null
				AND mem.posts != {int:zero}',
			[
				'zero' => 0,
			]
		);

		// set the post count to zero for any delinquents we may have found
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}members
				SET posts = {int:zero}
				WHERE id_member = {int:row}',
				[
					'row' => $row['id_member'],
					'zero' => 0,
				]
			);
		}
		$smcFunc['db']->free_result($request);
	}

	// now to redo the deliquents on the post count front for characters
	$createTemporaryChar = $smcFunc['db']->query('', '
		CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountcharposts (
			id_character int(10) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_member)
		)
		SELECT m.id_character
		FROM ({db_prefix}messages AS m,{db_prefix}boards AS b)
		WHERE m.id_character != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_character',
		[
			'zero' => 0,
			'string_zero' => '0',
			'db_error_skip' => true,
			'recycle' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
		]
	) !== false;

	if ($createTemporaryChar)
	{
		// outer join the characters table on the temporary table finding the members that have a post count but no posts in the message table
		$request = $smcFunc['db']->query('', '
			SELECT chars.id_character, chars.posts
			FROM {db_prefix}characters AS chars
			LEFT OUTER JOIN {db_prefix}tmp_maint_recountcharposts AS res
			ON res.id_character = chars.id_character
			WHERE res.id_character IS null
				AND chars.posts != {int:zero}',
			[
				'zero' => 0,
			]
		);

		// set the post count to zero for any delinquents we may have found
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}characters
				SET posts = {int:zero}
				WHERE id_character = {int:row}',
				[
					'row' => $row['id_character'],
					'zero' => 0,
				]
			);
		}
		$smcFunc['db']->free_result($request);
	}

	// all done
	unset($_SESSION['total_members']);
	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_recountposts']));
	redirectexit('action=admin;area=maintain;sa=members');
}
