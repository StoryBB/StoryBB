<?php

/**
 * This file contains the functions required to move topics from one board to
 * another board.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;

/**
 * This function allows to move a topic, making sure to ask the moderator
 * to give reason for topic move.
 * It must be called with a topic specified. (that is, global $topic must
 * be set... @todo fix this thing.)
 * If the member is the topic starter requires the move_own permission,
 * otherwise the move_any permission.
 * Accessed via ?action=movetopic.
 *
 * @uses the MoveTopic template, main sub-template.
 */
function MoveTopic()
{
	global $txt, $board, $board_info, $topic, $user_info, $context, $language, $scripturl, $smcFunc, $modSettings, $sourcedir;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	$request = $smcFunc['db']->query('', '
		SELECT t.id_member_started, ms.subject, t.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
		]
	);
	list ($id_member_started, $context['subject'], $context['is_approved']) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Can they see it - if not approved?
	if (!$context['is_approved'])
		isAllowedTo('approve_posts');

	$context['do_redirect_notice'] = $context['is_approved'] && empty($board_info['in_character']);

	// Permission check!
	// @todo
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == $user_info['id'])
		{
			isAllowedTo('move_own');
		}
		else
			isAllowedTo('move_any');
	}

	$context['move_any'] = $user_info['is_admin'] || $modSettings['topic_move_any'];
	$boards = [];

	if (!$context['move_any'])
	{
		$boards = array_diff(boardsAllowedTo('post_new'), [$board]);
		if (empty($boards))
		{
			// No boards? Too bad...
			fatal_lang_error('moveto_no_boards');
		}
	}

	$options = [
		'not_redirection' => true,
	];

	if (!empty($_SESSION['move_to_topic']) && $_SESSION['move_to_topic'] != $board)
		$options['selected_board'] = $_SESSION['move_to_topic'];

	if (!$context['move_any'])
		$options['included_boards'] = $boards;

	require_once($sourcedir . '/Subs-MessageIndex.php');
	$context['categories'] = getBoardList($options);

	$context['page_title'] = $txt['move_topic'];

	$context['linktree'][] = [
		'url' => $scripturl . '?topic=' . $topic . '.0',
		'name' => $context['subject'],
	];

	$context['linktree'][] = [
		'name' => $txt['move_topic'],
	];

	$context['back_to_topic'] = isset($_REQUEST['goback']);

	if ($user_info['language'] != $language)
	{
		loadLanguage('General', $language);
		$temp = $txt['movetopic_default'];
		loadLanguage('General');

		$txt['movetopic_default'] = $temp;
	}

	$context['sub_template'] = 'topic_move';

	moveTopicConcurrence();

	// Register this form and get a sequence number in $context.
	checkSubmitOnce('register');
}

/**
 * Execute the move of a topic.
 * It is called on the submit of MoveTopic.
 * This function logs that topics have been moved in the moderation log.
 * If the member is the topic starter requires the move_own permission,
 * otherwise requires the move_any permission.
 * Upon successful completion redirects to message index.
 * Accessed via ?action=movetopic2.
 *
 * @uses Subs-Post.php.
 */
function MoveTopic2()
{
	global $txt, $board, $topic, $scripturl, $sourcedir, $context;
	global $board, $language, $user_info, $smcFunc;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	// You can't choose to have a redirection topic and use an empty reason.
	if (isset($_POST['postRedirect']) && (!isset($_POST['reason']) || trim($_POST['reason']) == ''))
		fatal_lang_error('movetopic_no_reason', false);

	moveTopicConcurrence();

	// Make sure this form hasn't been submitted before.
	checkSubmitOnce('check');

	$request = $smcFunc['db']->query('', '
		SELECT id_member_started, id_first_msg, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
		]
	);
	list ($id_member_started, $id_first_msg, $context['is_approved']) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Can they see it?
	if (!$context['is_approved'])
		isAllowedTo('approve_posts');

	// Can they move topics on this board?
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == $user_info['id'])
			isAllowedTo('move_own');
		else
			isAllowedTo('move_any');
	}

	checkSession();
	require_once($sourcedir . '/Subs-Post.php');

	// The destination board must be numeric.
	$_POST['toboard'] = (int) $_POST['toboard'];

	// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
	$request = $smcFunc['db']->query('', '
		SELECT b.count_posts, b.name, m.subject
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE {query_see_board}
			AND b.id_board = {int:to_board}
			AND b.redirect = {string:blank_redirect}
		LIMIT 1',
		[
			'current_topic' => $topic,
			'to_board' => $_POST['toboard'],
			'blank_redirect' => '',
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
		fatal_lang_error('no_board');
	list ($pcounter, $board_name, $subject) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Remember this for later.
	$_SESSION['move_to_topic'] = $_POST['toboard'];

	// Rename the topic...
	if (isset($_POST['reset_subject'], $_POST['custom_subject']) && $_POST['custom_subject'] != '')
	{
		$_POST['custom_subject'] = strtr(StringLibrary::htmltrim(StringLibrary::escape($_POST['custom_subject'])), ["\r" => '', "\n" => '', "\t" => '']);
		// Keep checking the length.
		if (StringLibrary::strlen($_POST['custom_subject']) > 100)
			$_POST['custom_subject'] = StringLibrary::substr($_POST['custom_subject'], 0, 100);

		// If it's still valid move onwards and upwards.
		if ($_POST['custom_subject'] != '')
		{
			if (isset($_POST['enforce_subject']))
			{
				// Get a response prefix, but in the forum's default language.
				if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
				{
					if ($language === $user_info['language'])
						$context['response_prefix'] = $txt['response_prefix'];
					else
					{
						loadLanguage('General', $language, false);
						$context['response_prefix'] = $txt['response_prefix'];
						loadLanguage('General');
					}
					cache_put_data('response_prefix', $context['response_prefix'], 600);
				}

				$smcFunc['db']->query('', '
					UPDATE {db_prefix}messages
					SET subject = {string:subject}
					WHERE id_topic = {int:current_topic}',
					[
						'current_topic' => $topic,
						'subject' => $context['response_prefix'] . $_POST['custom_subject'],
					]
				);
			}

			$smcFunc['db']->query('', '
				UPDATE {db_prefix}messages
				SET subject = {string:custom_subject}
				WHERE id_msg = {int:id_first_msg}',
				[
					'id_first_msg' => $id_first_msg,
					'custom_subject' => $_POST['custom_subject'],
				]
			);

			// Fix the subject cache.
			updateStats('subject', $topic, $_POST['custom_subject']);
		}
	}

	// Create a link to this in the old board.
	// @todo Does this make sense if the topic was unapproved before? I'd just about say so.
	if (isset($_POST['postRedirect']))
	{
		// Should be in the boardwide language.
		if ($user_info['language'] != $language)
			loadLanguage('General', $language);

		$_POST['reason'] = StringLibrary::escape($_POST['reason'], ENT_QUOTES);
		preparsecode($_POST['reason']);

		// Add a URL onto the message.
		$_POST['reason'] = strtr($_POST['reason'], [
			$txt['movetopic_auto_board'] => '[url=' . $scripturl . '?board=' . $_POST['toboard'] . '.0]' . $board_name . '[/url]',
			$txt['movetopic_auto_topic'] => '[iurl]' . $scripturl . '?topic=' . $topic . '.0[/iurl]'
		]);

		// auto remove this MOVED redirection topic in the future?
		$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

		// redirect to the MOVED topic from topic list?
		$redirect_topic = isset($_POST['redirect_topic']) ? $topic : 0;

		$msgOptions = [
			'subject' => $txt['moved'] . ': ' . $subject,
			'body' => $_POST['reason'],
			'smileys_enabled' => 1,
		];
		$topicOptions = [
			'board' => $board,
			'lock_mode' => 1,
			'mark_as_read' => true,
			'redirect_expires' => $redirect_expires,
			'redirect_topic' => $redirect_topic,
			'is_moved' => 1,
		];
		$posterOptions = [
			'id' => $user_info['id'],
			'update_post_count' => empty($pcounter),
		];
		createPost($msgOptions, $topicOptions, $posterOptions);
	}

	$request = $smcFunc['db']->query('', '
		SELECT count_posts
		FROM {db_prefix}boards
		WHERE id_board = {int:current_board}
		LIMIT 1',
		[
			'current_board' => $board,
		]
	);
	list ($pcounter_from) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	if ($pcounter_from != $pcounter)
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND approved = {int:is_approved}',
			[
				'current_topic' => $topic,
				'is_approved' => 1,
			]
		);
		$posters = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($posters[$row['id_member']]))
				$posters[$row['id_member']] = 0;

			$posters[$row['id_member']]++;
		}
		$smcFunc['db']->free_result($request);

		foreach ($posters as $id_member => $posts)
		{
			// The board we're moving from counted posts, but not to.
			if (empty($pcounter_from))
				updateMemberData($id_member, ['posts' => 'posts - ' . $posts]);
			// The reverse: from didn't, to did.
			else
				updateMemberData($id_member, ['posts' => 'posts + ' . $posts]);
		}
	}

	// Do the move (includes statistics update needed for the redirect topic).
	moveTopics($topic, $_POST['toboard']);

	// Log that they moved this topic.
	if (!allowedTo('move_own') || $id_member_started != $user_info['id'])
		logAction('move', ['topic' => $topic, 'board_from' => $board, 'board_to' => $_POST['toboard']]);
	// Notify people that this topic has been moved?
	sendNotifications($topic, 'move');

	// Why not go back to the original board in case they want to keep moving?
	if (!isset($_REQUEST['goback']))
		redirectexit('board=' . $board . '.0');
	else
		redirectexit('topic=' . $topic . '.0');
}

/**
 * Moves one or more topics to a specific board. (doesn't check permissions.)
 * Determines the source boards for the supplied topics
 * Handles the moving of mark_read data
 * Updates the posts count of the affected boards
 *
 * @param int|int[] $topics The ID of a single topic to move or an array containing the IDs of multiple topics to move
 * @param int $toBoard The ID of the board to move the topics to
 */
function moveTopics($topics, $toBoard)
{
	global $sourcedir, $user_info, $modSettings, $smcFunc;

	// Empty array?
	if (empty($topics))
		return;

	// Only a single topic.
	if (is_numeric($topics))
		$topics = [$topics];

	$fromBoards = [];

	// Destination board empty or equal to 0?
	if (empty($toBoard))
		return;

	// Are we moving to the recycle board?
	$isRecycleDest = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $toBoard;

	// Callback for search APIs to do their thing
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if ($searchAPI->supportsMethod('topicsMoved'))
		$searchAPI->topicsMoved($topics, $toBoard);

	// Determine the source boards...
	$request = $smcFunc['db']->query('', '
		SELECT id_board, approved, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
			SUM(num_replies) AS num_replies
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_board, approved',
		[
			'topics' => $topics,
		]
	);
	// Num of rows = 0 -> no topics found. Num of rows > 1 -> topics are on multiple boards.
	if ($smcFunc['db']->num_rows($request) == 0)
		return;
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($fromBoards[$row['id_board']]['num_posts']))
		{
			$fromBoards[$row['id_board']] = [
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
				'id_board' => $row['id_board']
			];
		}
		// Posts = (num_replies + 1) for each approved topic.
		$fromBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
		$fromBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

		// Add the topics to the right type.
		if ($row['approved'])
			$fromBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
		else
			$fromBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
	}
	$smcFunc['db']->free_result($request);

	// Move over the mark_read data. (because it may be read and now not by some!)
	$SaveAServer = max(0, $modSettings['maxMsgID'] - 50000);
	$request = $smcFunc['db']->query('', '
		SELECT lmr.id_member, lmr.id_msg, t.id_topic, COALESCE(lt.unwatched, 0) AS unwatched
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board
				AND lmr.id_msg > t.id_first_msg AND lmr.id_msg > {int:protect_lmr_msg})
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = lmr.id_member)
		WHERE t.id_topic IN ({array_int:topics})
			AND lmr.id_msg > COALESCE(lt.id_msg, 0)',
		[
			'protect_lmr_msg' => $SaveAServer,
			'topics' => $topics,
		]
	);
	$log_topics = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$log_topics[] = [$row['id_topic'], $row['id_member'], $row['id_msg'], (is_null($row['unwatched']) ? 0 : $row['unwatched'])];

		// Prevent queries from getting too big. Taking some steam off.
		if (count($log_topics) > 500)
		{
			$smcFunc['db']->insert('replace',
				'{db_prefix}log_topics',
				['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
				$log_topics,
				['id_topic', 'id_member']
			);

			$log_topics = [];
		}
	}
	$smcFunc['db']->free_result($request);

	// Now that we have all the topics that *should* be marked read, and by which members...
	if (!empty($log_topics))
	{
		// Insert that information into the database!
		$smcFunc['db']->insert('replace',
			'{db_prefix}log_topics',
			['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
			$log_topics,
			['id_topic', 'id_member']
		);
	}

	// Update the number of posts on each board.
	$totalTopics = 0;
	$totalPosts = 0;
	$totalUnapprovedTopics = 0;
	$totalUnapprovedPosts = 0;
	foreach ($fromBoards as $stats)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}boards
			SET
				num_posts = CASE WHEN {int:num_posts} > num_posts THEN 0 ELSE num_posts - {int:num_posts} END,
				num_topics = CASE WHEN {int:num_topics} > num_topics THEN 0 ELSE num_topics - {int:num_topics} END,
				unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END,
				unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END
			WHERE id_board = {int:id_board}',
			[
				'id_board' => $stats['id_board'],
				'num_posts' => $stats['num_posts'],
				'num_topics' => $stats['num_topics'],
				'unapproved_posts' => $stats['unapproved_posts'],
				'unapproved_topics' => $stats['unapproved_topics'],
			]
		);
		$totalTopics += $stats['num_topics'];
		$totalPosts += $stats['num_posts'];
		$totalUnapprovedTopics += $stats['unapproved_topics'];
		$totalUnapprovedPosts += $stats['unapproved_posts'];
	}
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}boards
		SET
			num_topics = num_topics + {int:total_topics},
			num_posts = num_posts + {int:total_posts},' . ($isRecycleDest ? '
			unapproved_posts = {int:no_unapproved}, unapproved_topics = {int:no_unapproved}' : '
			unapproved_posts = unapproved_posts + {int:total_unapproved_posts},
			unapproved_topics = unapproved_topics + {int:total_unapproved_topics}') . '
		WHERE id_board = {int:id_board}',
		[
			'id_board' => $toBoard,
			'total_topics' => $totalTopics,
			'total_posts' => $totalPosts,
			'total_unapproved_topics' => $totalUnapprovedTopics,
			'total_unapproved_posts' => $totalUnapprovedPosts,
			'no_unapproved' => 0,
		]
	);

	// Move the topic.  Done.  :P
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}topics
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',
			unapproved_posts = {int:no_unapproved}, approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		[
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
			'no_unapproved' => 0,
		]
	);

	// If this was going to the recycle bin, check what messages are being recycled, and remove them from the queue.
	if ($isRecycleDest && ($totalUnapprovedTopics || $totalUnapprovedPosts))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
				and approved = {int:not_approved}',
			[
				'topics' => $topics,
				'not_approved' => 0,
			]
		);
		$approval_msgs = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$approval_msgs[] = $row['id_msg'];
		$smcFunc['db']->free_result($request);

		// Empty the approval queue for these, as we're going to approve them next.
		if (!empty($approval_msgs))
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg IN ({array_int:message_list})
					AND id_attach = {int:id_attach}',
				[
					'message_list' => $approval_msgs,
					'id_attach' => 0,
				]
			);

		// Get all the current max and mins.
		$request = $smcFunc['db']->query('', '
			SELECT id_topic, id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			]
		);
		$topicMaxMin = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topicMaxMin[$row['id_topic']] = [
				'min' => $row['id_first_msg'],
				'max' => $row['id_last_msg'],
			];
		}
		$smcFunc['db']->free_result($request);

		// Check the MAX and MIN are correct.
		$request = $smcFunc['db']->query('', '
			SELECT id_topic, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_topic',
			[
				'topics' => $topics,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// If not, update.
			if ($row['first_msg'] != $topicMaxMin[$row['id_topic']]['min'] || $row['last_msg'] != $topicMaxMin[$row['id_topic']]['max'])
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}topics
					SET id_first_msg = {int:first_msg}, id_last_msg = {int:last_msg}
					WHERE id_topic = {int:selected_topic}',
					[
						'first_msg' => $row['first_msg'],
						'last_msg' => $row['last_msg'],
						'selected_topic' => $row['id_topic'],
					]
				);
		}
		$smcFunc['db']->free_result($request);
	}

	$smcFunc['db']->query('', '
		UPDATE {db_prefix}messages
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		[
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
		]
	);
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}log_reported
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		[
			'id_board' => $toBoard,
			'topics' => $topics,
		]
	);

	// Mark target board as seen, if it was already marked as seen before.
	$request = $smcFunc['db']->query('', '
		SELECT (COALESCE(lb.id_msg, 0) >= b.id_msg_updated) AS isSeen
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE b.id_board = {int:id_board}',
		[
			'current_member' => $user_info['id'],
			'id_board' => $toBoard,
		]
	);
	list ($isSeen) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	if (!empty($isSeen) && !$user_info['is_guest'])
	{
		$smcFunc['db']->insert('replace',
			'{db_prefix}log_boards',
			['id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'],
			[$toBoard, $user_info['id'], $modSettings['maxMsgID']],
			['id_board', 'id_member']
		);
	}

	// Update the cache?
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
		foreach ($topics as $topic_id)
			cache_put_data('topic_board-' . $topic_id, null, 120);

	require_once($sourcedir . '/Subs-Post.php');

	$updates = array_keys($fromBoards);
	$updates[] = $toBoard;

	updateLastMessages(array_unique($updates));

	// Update 'em pesky stats.
	updateStats('topic');
	updateStats('message');
}

/**
 * Called after a topic is moved to update $board_link and $topic_link to point to new location
 */
function moveTopicConcurrence()
{
	global $board, $topic, $smcFunc, $scripturl;

	if (isset($_GET['current_board']))
		$move_from = (int) $_GET['current_board'];

	if (empty($move_from) || empty($board) || empty($topic))
		return true;

	if ($move_from == $board)
		return true;
	else
	{
		$request = $smcFunc['db']->query('', '
			SELECT m.subject, b.name
			FROM {db_prefix}topics as t
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE t.id_topic = {int:topic_id}
			LIMIT 1',
			[
				'topic_id' => $topic,
			]
		);
		list($topic_subject, $board_name) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
		$board_link = '<a href="' . $scripturl . '?board=' . $board . '.0">' . $board_name . '</a>';
		$topic_link = '<a href="' . $scripturl . '?topic=' . $topic . '.0">' . $topic_subject . '</a>';
		fatal_lang_error('topic_already_moved', false, [$topic_link, $board_link]);
	}
}
