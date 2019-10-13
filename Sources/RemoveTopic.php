<?php

/**
 * The contents of this file handle the deletion of topics, posts, and related
 * paraphernalia.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/*	The contents of this file handle the deletion of topics, posts, and related
	paraphernalia.  It has the following functions:

*/

/**
 * Completely remove an entire topic.
 * Redirects to the board when completed.
 */
function RemoveTopic2()
{
	global $user_info, $topic, $board, $sourcedir, $smcFunc;

	// Make sure they aren't being lead around by someone. (:@)
	checkSession('get');

	// This file needs to be included for sendNotifications().
	require_once($sourcedir . '/Subs-Post.php');

	// Trying to fool us around, are we?
	if (empty($topic))
		redirectexit();

	$request = $smcFunc['db']->query('', '
		SELECT t.id_member_started, ms.subject, t.approved, t.locked
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
		]
	);
	list ($starter, $subject, $approved, $locked) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	if ($starter == $user_info['id'] && !allowedTo('remove_any'))
		isAllowedTo('remove_own');
	else
		isAllowedTo('remove_any');

	// Can they see the topic?
	if (!$approved && $starter != $user_info['id'])
		isAllowedTo('approve_posts');

	// Ok, we got that far, but is it locked?
	if ($locked)
	{
		if (!($locked == 1 && $starter == $user_info['id'] || allowedTo('lock_any')))
			fatal_lang_error('cannot_remove_locked', 'user');
	}

	// Notify people that this topic has been removed.
	sendNotifications($topic, 'remove');

	removeTopics($topic);

	// Note, only log topic ID in native form if it's not gone forever.
	if (allowedTo('remove_any') || (allowedTo('remove_own') && $starter == $user_info['id']))
		logAction('remove', ['topic' => $topic, 'subject' => $subject, 'member' => $starter, 'board' => $board]);

	redirectexit('board=' . $board . '.0');
}

/**
 * Remove just a single post.
 * On completion redirect to the topic or to the board.
 */
function DeleteMessage()
{
	global $user_info, $topic, $board, $modSettings, $smcFunc;

	checkSession('get');

	$_REQUEST['msg'] = (int) $_REQUEST['msg'];

	// Is $topic set?
	if (empty($topic) && isset($_REQUEST['topic']))
		$topic = (int) $_REQUEST['topic'];

	$request = $smcFunc['db']->query('', '
		SELECT t.id_member_started, m.id_member, m.subject, m.poster_time, m.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = {int:id_msg} AND m.id_topic = {int:current_topic})
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		]
	);
	list ($starter, $poster, $subject, $post_time, $approved) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Verify they can see this!
	if (!$approved && !empty($poster) && $poster != $user_info['id'])
		isAllowedTo('approve_posts');

	if ($poster == $user_info['id'])
	{
		if (!allowedTo('delete_own'))
		{
			if ($starter == $user_info['id'] && !allowedTo('delete_any'))
				isAllowedTo('delete_replies');
			elseif (!allowedTo('delete_any'))
				isAllowedTo('delete_own');
		}
		elseif (!allowedTo('delete_any') && ($starter != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $post_time + $modSettings['edit_disable_time'] * 60 < time())
			fatal_lang_error('modify_post_time_passed', false);
	}
	elseif ($starter == $user_info['id'] && !allowedTo('delete_any'))
		isAllowedTo('delete_replies');
	else
		isAllowedTo('delete_any');

	// If the full topic was removed go back to the board.
	$full_topic = removeMessage($_REQUEST['msg']);

	if (allowedTo('delete_any') && (!allowedTo('delete_own') || $poster != $user_info['id']))
		logAction('delete', ['topic' => $topic, 'subject' => $subject, 'member' => $poster, 'board' => $board]);

	// We want to redirect back to recent action.
	if (isset($_REQUEST['modcenter']))
		redirectexit('action=moderate;area=reportedposts');
	elseif (isset($_REQUEST['recent']))
		redirectexit('action=recent');
	elseif (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u']))
		redirectexit('action=profile;u=' . $_REQUEST['u'] . ';area=showposts;start=' . $_REQUEST['start']);
	elseif ($full_topic)
		redirectexit('board=' . $board . '.0');
	else
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * So long as you are sure... all old posts will be gone.
 * Used in ManageMaintenance.php to prune old topics.
 */
function RemoveOldTopics2()
{
	global $smcFunc, $txt;

	isAllowedTo('admin_forum');
	checkSession('post', 'admin');

	// No boards at all?  Forget it then :/.
	if (empty($_POST['boards']))
		redirectexit('action=admin;area=maintain;sa=topics');

	// This should exist, but we can make sure.
	$_POST['delete_type'] = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'nothing';

	// Custom conditions.
	$condition = '';
	$condition_params = [
		'boards' => array_keys($_POST['boards']),
		'poster_time' => time() - 3600 * 24 * $_POST['maxdays'],
	];

	// Just moved notice topics?
	// Note that this ignores redirection topics unless it's a non-expiring one
	if ($_POST['delete_type'] == 'moved')
	{
		$condition .= '
			AND t.locked = {int:locked}
			AND t.redirect_expires = {int:not_expiring}
			AND t.is_moved = {int:moved}';
		$condition_params['moved'] = 1;
		$condition_params['locked'] = 1;
		$condition_params['not_expiring'] = 0;
	}
	// Otherwise, maybe locked topics only?
	elseif ($_POST['delete_type'] == 'locked')
	{
		// Exclude moved/merged notices since we have another option for those...
		$condition .= '
			AND t.is_moved != {int:moved}
			AND t.locked = {int:locked}';
		$condition_params['moved'] = 1;
		$condition_params['locked'] = 1;
	}

	// Exclude stickies?
	if (isset($_POST['delete_old_not_sticky']))
	{
		$condition .= '
			AND t.is_sticky = {int:is_sticky}';
		$condition_params['is_sticky'] = 0;
	}

	// All we're gonna do here is grab the id_topic's and send them to removeTopics().
	$request = $smcFunc['db']->query('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
		WHERE
			m.poster_time < {int:poster_time}' . $condition . '
			AND t.id_board IN ({array_int:boards})',
		$condition_params
	);
	$topics = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$topics[] = $row['id_topic'];
	$smcFunc['db']->free_result($request);

	removeTopics($topics, false, true);

	// Log an action into the moderation log.
	logAction('pruned', ['days' => $_POST['maxdays']]);

	loadLanguage('ManageMaintenance');
	session_flash('success', sprintf($txt['maintain_done'], $txt['maintain_old']));
	redirectexit('action=admin;area=maintain;sa=topics');
}

/**
 * Removes the passed id_topic's. (permissions are NOT checked here!).
 *
 * @param array|int $topics The topics to remove (can be an id or an array of ids).
 * @param bool $decreasePostCount Whether to decrease the users' post counts
 * @param bool $ignoreSoftDelete Whether to ignore soft delete and just hard delete regardless
 * @param bool $updateBoardCount Whether to adjust topic counts for the boards
 */
function removeTopics($topics, $decreasePostCount = true, $ignoreSoftDelete = false, $updateBoardCount = true)
{
	global $sourcedir, $modSettings, $smcFunc, $user_info;

	// Nothing to do?
	if (empty($topics))
		return;
	// Only a single topic.
	if (is_numeric($topics))
		$topics = [$topics];

	// Decrease the post counts.
	if ($decreasePostCount)
	{
		$requestMembers = $smcFunc['db']->query('', '
			SELECT m.id_member, m.id_character, COUNT(*) AS posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_topic IN ({array_int:topics})
				AND b.count_posts = {int:do_count_posts}
				AND m.approved = {int:is_approved}
				AND m.deleted = {int:not_deleted}
			GROUP BY m.id_member, m.id_character',
			[
				'do_count_posts' => 0,
				'topics' => $topics,
				'is_approved' => 1,
				'not_deleted' => 0,
			]
		);
		if ($smcFunc['db']->num_rows($requestMembers) > 0)
		{
			while ($rowMembers = $smcFunc['db']->fetch_assoc($requestMembers))
			{
				updateMemberData($rowMembers['id_member'], ['posts' => 'posts - ' . $rowMembers['posts']]);
				updateCharacterData($rowMembers['id_character'], ['posts' => 'posts - ' . $rowMembers['posts']]);
			}
		}
		$smcFunc['db']->free_result($requestMembers);
	}

	// Soft deletion...
	if (!$ignoreSoftDelete)
	{
		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, t.id_board, t.unapproved_posts, t.approved, t.deleted, t.id_first_msg, m.id_member
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE id_topic IN ({array_int:topics})
			LIMIT {int:limit}',
			[
				'topics' => $topics,
				'limit' => count($topics),
			]
		);
		if ($smcFunc['db']->num_rows($request) > 0)
		{
			// Get topics that will be soft deleted.
			$softDeleted = [];
			$boardSoftDeleted = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				// The post has already been soft-deleted, leave it for hard deletion.
				if ($row['deleted'])
				{
					continue;
				}

				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				$softDeleted[] = $row['id_topic'];
				$boardSoftDeleted[] = $row['id_board'];

				// Set the deleted status for this topic - and make it not sticky.
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}topics
					SET deleted = {int:deleted}, is_sticky = {int:not_sticky}
					WHERE id_topic = {int:id_topic}',
					[
						'deleted' => $row['id_member'] != $user_info['id'] ? 2 : 1,
						'id_topic' => $row['id_topic'],
						'not_sticky' => 0,
					]
				);
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}messages
					SET deleted = {int:deleted}
					WHERE id_msg = {int:id_msg}',
					[
						'deleted' => $row['id_member'] != $user_info['id'] ? 2 : 1,
					]
				);
			}
			$smcFunc['db']->free_result($request);

			// Update the board stats for the boards we've just updated.
			update_topic_stats($softDeleted);
			update_board_stats(array_unique($boardSoftDeleted));

			// Close reports that are being soft deleted.
			if (!empty($softDeleted))
			{
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}log_reported
					SET closed = {int:is_closed}
					WHERE id_topic IN ({array_int:softDeleted})',
					[
						'softDeleted' => $softDeleted,
						'is_closed' => 1,
					]
				);
			}

			updateSettings(['last_mod_report_action' => time()]);

			require_once($sourcedir . '/Subs-ReportedContent.php');
			recountOpenReports('posts');

			// Topics that were soft-deleted don't need to be deleted, so subtract them.
			$topics = array_diff($topics, $softDeleted);
		}
		else
			$smcFunc['db']->free_result($request);
	}

	// Still topics left to delete?
	if (empty($topics))
		return;

	// Callback for search APIs to do their thing
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if ($searchAPI->supportsMethod('topicsRemoved'))
		$searchAPI->topicsRemoved($topics);

	$adjustBoards = [];

	// Find out how many posts we are deleting.
	$request = $smcFunc['db']->query('', '
		SELECT id_board, approved, deleted, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
			SUM(num_replies) AS num_replies
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_board, approved, deleted',
		[
			'topics' => $topics,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($adjustBoards[$row['id_board']]['num_posts']))
		{
			// @todo deleted counts?
			$adjustBoards[$row['id_board']] = [
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,

				'id_board' => $row['id_board']
			];
		}


		// Posts = (num_replies + 1) for each approved topic.
		$adjustBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
		$adjustBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

		// Add the topics to the right type.
		if ($row['approved'])
			$adjustBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
		else
			$adjustBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
	}
	$smcFunc['db']->free_result($request);

	if ($updateBoardCount)
	{
		// Decrease the posts/topics...
		foreach ($adjustBoards as $stats)
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

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
		}
	}
	// Remove Polls.
	$request = $smcFunc['db']->query('', '
		SELECT id_poll
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
			AND id_poll > {int:no_poll}
		LIMIT {int:limit}',
		[
			'no_poll' => 0,
			'topics' => $topics,
			'limit' => count($topics),
		]
	);
	$polls = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
		$polls[] = $row['id_poll'];
	$smcFunc['db']->free_result($request);

	if (!empty($polls))
	{
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}polls
			WHERE id_poll IN ({array_int:polls})',
			[
				'polls' => $polls,
			]
		);
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}poll_choices
			WHERE id_poll IN ({array_int:polls})',
			[
				'polls' => $polls,
			]
		);
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll IN ({array_int:polls})',
			[
				'polls' => $polls,
			]
		);
	}

	// Get rid of the attachment, if it exists.
	require_once($sourcedir . '/ManageAttachments.php');
	$attachmentQuery = [
		'attachment_type' => 0,
		'id_topic' => $topics,
	];
	removeAttachments($attachmentQuery, 'messages');

	// Delete possible search index entries.
	if (!empty($modSettings['search_custom_index_config']))
	{
		$customIndexSettings = sbb_json_decode($modSettings['search_custom_index_config'], true);

		$words = [];
		$messages = [];
		$request = $smcFunc['db']->query('', '
			SELECT id_msg, body
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$words = array_merge($words, text2words($row['body'], $customIndexSettings['bytes_per_word'], true));
			$messages[] = $row['id_msg'];
		}
		$smcFunc['db']->free_result($request);
		$words = array_unique($words);

		if (!empty($words) && !empty($messages))
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg IN ({array_int:message_list})',
				[
					'word_list' => $words,
					'message_list' => $messages,
				]
			);
	}

	// Delete anything related to the topic.
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topics})',
		[
			'topics' => $topics,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:topics})',
		[
			'topics' => $topics,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_topic IN ({array_int:topics})',
		[
			'topics' => $topics,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})',
		[
			'topics' => $topics,
		]
	);
	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}log_search_subjects
		WHERE id_topic IN ({array_int:topics})',
		[
			'topics' => $topics,
		]
	);

	// Maybe there's a mod that wants to delete topic related data of its own
	call_integration_hook('integrate_remove_topics', [$topics]);

	// Update the totals...
	updateStats('message');
	updateStats('topic');

	require_once($sourcedir . '/Subs-Post.php');
	$updates = [];
	foreach ($adjustBoards as $stats)
		$updates[] = $stats['id_board'];
	updateLastMessages($updates);
}

/**
 * Remove a specific message (including permission checks).
 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
 * - uses boardurl to determine these two things.
 *
 * @param int $message The message id
 * @param bool $decreasePostCount Whether to decrease users' post counts
 * @return bool Whether the operation succeeded
 */
function removeMessage($message, $decreasePostCount = true)
{
	global $board, $sourcedir, $modSettings, $user_info, $smcFunc;

	if (empty($message) || !is_numeric($message))
		return false;

	$request = $smcFunc['db']->query('', '
		SELECT
			m.id_member, m.id_character, m.poster_time, m.subject,' . (empty($modSettings['search_custom_index_config']) ? '' : ' m.body,') . '
			m.approved, t.id_topic, t.id_first_msg, t.id_last_msg, t.num_replies, t.id_board,
			t.id_member_started AS id_member_poster,
			b.count_posts, m.deleted AS message_deleted, t.deleted AS topic_deleted
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE m.id_msg = {int:id_msg}
		LIMIT 1',
		[
			'id_msg' => $message,
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
		return false;
	$row = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	if (empty($board) || $row['id_board'] != $board)
	{
		$delete_any = boardsAllowedTo('delete_any');

		if (!in_array(0, $delete_any) && !in_array($row['id_board'], $delete_any))
		{
			$delete_own = boardsAllowedTo('delete_own');
			$delete_own = in_array(0, $delete_own) || in_array($row['id_board'], $delete_own);
			$delete_replies = boardsAllowedTo('delete_replies');
			$delete_replies = in_array(0, $delete_replies) || in_array($row['id_board'], $delete_replies);

			if ($row['id_member'] == $user_info['id'])
			{
				if (!$delete_own)
				{
					if ($row['id_member_poster'] == $user_info['id'])
					{
						if (!$delete_replies)
							fatal_lang_error('cannot_delete_replies', 'permission');
					}
					else
						fatal_lang_error('cannot_delete_own', 'permission');
				}
				elseif (($row['id_member_poster'] != $user_info['id'] || !$delete_replies) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
			}
			elseif ($row['id_member_poster'] == $user_info['id'])
			{
				if (!$delete_replies)
					fatal_lang_error('cannot_delete_replies', 'permission');
			}
			else
				fatal_lang_error('cannot_delete_any', 'permission');
		}

		// Can't delete an unapproved message, if you can't see it!
		if (!$row['approved'] && $row['id_member'] != $user_info['id'] && !(in_array(0, $delete_any) || in_array($row['id_board'], $delete_any)))
		{
			$approve_posts = boardsAllowedTo('approve_posts');
			if (!in_array(0, $approve_posts) && !in_array($row['id_board'], $approve_posts))
				return false;
		}
	}
	else
	{
		// Check permissions to delete this message.
		if ($row['id_member'] == $user_info['id'])
		{
			if (!allowedTo('delete_own'))
			{
				if ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
					isAllowedTo('delete_replies');
				elseif (!allowedTo('delete_any'))
					isAllowedTo('delete_own');
			}
			elseif (!allowedTo('delete_any') && ($row['id_member_poster'] != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
		}
		elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
			isAllowedTo('delete_replies');
		else
			isAllowedTo('delete_any');

		if (!$row['approved'] && $row['id_member'] != $user_info['id'] && !allowedTo('delete_own'))
			isAllowedTo('approve_posts');
	}

	// If the person deleting the message isn't the owner, mark as 'deleted by moderator'.
	$deleted_by_moderator = $row['id_member'] != $user_info['id'];

	// Delete the *whole* topic, but only if the topic consists of one message.
	if ($row['id_first_msg'] == $message)
	{
		if (empty($board) || $row['id_board'] != $board)
		{
			$remove_any = boardsAllowedTo('remove_any');
			$remove_any = in_array(0, $remove_any) || in_array($row['id_board'], $remove_any);
			if (!$remove_any)
			{
				$remove_own = boardsAllowedTo('remove_own');
				$remove_own = in_array(0, $remove_own) || in_array($row['id_board'], $remove_own);
			}

			if ($row['id_member'] != $user_info['id'] && !$remove_any)
				fatal_lang_error('cannot_remove_any', 'permission');
			elseif (!$remove_any && !$remove_own)
				fatal_lang_error('cannot_remove_own', 'permission');
		}
		else
		{
			// Check permissions to delete a whole topic.
			if ($row['id_member'] != $user_info['id'])
				isAllowedTo('remove_any');
			elseif (!allowedTo('remove_any'))
				isAllowedTo('remove_own');
		}

		// ...if there is only one post.
		if (!empty($row['num_replies']))
			fatal_lang_error('delFirstPost', false);

		removeTopics($row['id_topic']);
		return true;
	}

	// Deleting an already deleted message can not lower anyone's post count.
	if ($row['message_deleted'])
		$decreasePostCount = false;

	// This is the last post, update the last post on the board.
	if ($row['id_last_msg'] == $message)
	{
		// Find the last message, set it, and decrease the post count.
		$request = $smcFunc['db']->query('', '
			SELECT id_msg, id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:id_topic}
				AND id_msg != {int:id_msg}
				AND deleted = {int:not_deleted}
			ORDER BY approved DESC, id_msg DESC
			LIMIT 1',
			[
				'id_topic' => $row['id_topic'],
				'id_msg' => $message,
				'not_deleted' => 0,
			]
		);
		$row2 = $smcFunc['db']->fetch_assoc($request);
		$smcFunc['db']->free_result($request);

		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topics
			SET
				id_last_msg = {int:id_last_msg},
				id_member_updated = {int:id_member_updated}' . ($row['approved'] ? ',
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : ',
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			[
				'id_last_msg' => $row2['id_msg'],
				'id_member_updated' => $row2['id_member'],
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			]
		);
	}
	// Only decrease post counts.
	else
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topics
			SET ' . ($row['approved'] ? '
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : '
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			[
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			]
		);

	$permadelete = true;

	// This message has not already been soft-deleted, let's fix that.
	if (!$row['message_deleted'])
	{
		// Mark as deleted.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}messages
			SET
				deleted = {int:deleted}
			WHERE id_msg = {int:id_msg}',
			[
				'id_msg' => $message,
				'deleted' => $deleted_by_moderator ? 2 : 1,
			]
		);

		// Make sure we update the search subject index.
		updateStats('subject', $row['id_topic'], $row['subject']);

		// If it wasn't approved don't keep it in the queue.
		if (!$row['approved'])
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg = {int:id_msg}
					AND id_attach = {int:id_attach}',
				[
					'id_msg' => $message,
					'id_attach' => 0,
				]
			);

		$permadelete = false;
	}

	$smcFunc['db']->query('', '
		UPDATE {db_prefix}boards
		SET ' . ($row['approved'] ? '
			num_posts = CASE WHEN num_posts = {int:no_posts} THEN 0 ELSE num_posts - 1 END' : '
			unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
		WHERE id_board = {int:id_board}',
		[
			'no_posts' => 0,
			'no_unapproved' => 0,
			'id_board' => $row['id_board'],
		]
	);

	// If the poster was registered and the board this message was on incremented
	// the member's posts when it was posted, decrease his or her post count.
	if (!empty($row['id_member']) && $decreasePostCount && empty($row['count_posts']) && $row['approved'])
	{
		updateMemberData($row['id_member'], ['posts' => '-']);
		updateCharacterData($row['id_character'], ['posts' => '-']);
	}

	// Only soft-remove posts if they're not already deleted.
	if ($permadelete)
	{
		// Callback for search APIs to do their thing
		require_once($sourcedir . '/Search.php');
		$searchAPI = findSearchAPI();
		if ($searchAPI->supportsMethod('postRemoved'))
			$searchAPI->postRemoved($message);

		// Remove the message!
		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			[
				'id_msg' => $message,
			]
		);

		if (!empty($modSettings['search_custom_index_config']))
		{
			$customIndexSettings = sbb_json_decode($modSettings['search_custom_index_config'], true);
			$words = text2words($row['body'], $customIndexSettings['bytes_per_word'], true);
			if (!empty($words))
				$smcFunc['db']->query('', '
					DELETE FROM {db_prefix}log_search_words
					WHERE id_word IN ({array_int:word_list})
						AND id_msg = {int:id_msg}',
					[
						'word_list' => $words,
						'id_msg' => $message,
					]
				);
		}

		// Delete attachment(s) if they exist.
		require_once($sourcedir . '/ManageAttachments.php');
		$attachmentQuery = [
			'attachment_type' => 0,
			'id_msg' => $message,
		];
		removeAttachments($attachmentQuery);

		// Allow mods to remove message related data of their own (likes, maybe?)
		call_integration_hook('integrate_remove_message', [$message]);
	}

	// Update the pesky statistics.
	updateStats('message');
	updateStats('topic');

	// And now to update the last message of each board we messed with.
	require_once($sourcedir . '/Subs-Post.php');
	updateLastMessages($row['id_board']);

	// Close any moderation reports for this message.
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}log_reported
		SET closed = {int:is_closed}
		WHERE id_msg = {int:id_msg}',
		[
			'is_closed' => 1,
			'id_msg' => $message,
		]
	);
	if ($smcFunc['db']->affected_rows() != 0)
	{
		require_once($sourcedir . '/ModerationCenter.php');
		updateSettings(['last_mod_report_action' => time()]);
		recountOpenReports('posts');
	}

	return false;
}

/**
 * Move back a topic from the limbo state.
 */
function RestoreTopic()
{
	global $smcFunc, $user_info;

	// Check session.
	checkSession('get');

	$topics_to_restore = [];
	$messages_to_restore = [];
	$boards_to_restore = [];

	$topics_updated = [];
	$boards_updated = [];

	// Restoring messages?
	if (!empty($_REQUEST['msgs']))
	{
		$msgs = explode(',', $_REQUEST['msgs']);
		foreach ($msgs as $k => $msg)
			$msgs[$k] = (int) $msg;

		// Get the message details.
		$request = $smcFunc['db']->query('', '
			SELECT m.id_topic, m.id_msg, m.id_board, m.subject, m.id_member,
				t.id_first_msg, t.deleted AS topic_deleted, m.deleted AS message_deleted, b.count_posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_msg IN ({array_int:messages})',
			[
				'messages' => $msgs,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Keep a list of all boards we're working on here.
			$boards_to_restore[$row['id_board']] = $row['count_posts'];

			// Restoring the first post means topic.
			if ($row['id_msg'] == $row['id_first_msg'] && $row['topic_deleted'])
			{
				$topics_to_restore[$row['id_topic']] = $row['id_msg'];
				continue;
			}
			elseif(!$row['topic_deleted'] && $row['message_deleted'])
			{
				$messages_to_restore[] = $row['id_msg'];
			}
		}
		$smcFunc['db']->free_result($request);
	}

	// Now any topics?
	if (!empty($_REQUEST['topics']))
	{
		$topics = explode(',', $_REQUEST['topics']);
		foreach ($topics as $k => $topic)
		{
			$topics[$k] = (int) $topic;
		}

		$request = $smcFunc['db']->query('', '
			SELECT m.id_topic, m.id_msg, m.id_board, m.subject, m.id_member,
				t.deleted AS topic_deleted, b.count_posts
			FROM {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE t.id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Keep a list of all boards we're working on here.
			$boards_to_restore[$row['id_board']] = $row['count_posts'];

			// Restoring the first post means topic.
			if ($row['topic_deleted'])
			{
				$topics_to_restore[$row['id_topic']] = $row['id_msg'];
				continue;
			}
		}
		$smcFunc['db']->free_result($request);
	}

	// Now we can check permissions.
	if (!$user_info['is_admin'])
	{
		$boards_allowed = boardsAllowedTo('delete_any');
		$boards_missing = array_diff($boards_allowed, array_keys($boards_to_restore));
		if ($boards_missing)
		{
			fatal_lang_error('cannot_restore_post', false);
		}
	}

	if (!empty($topics_to_restore))
	{
		// First, fix the topics' status.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topics
			SET deleted = {int:not_deleted}
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => array_keys($topics_to_restore),
				'not_deleted' => 0,
			]
		);
		// And the opening messages.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}messages
			SET deleted = {int:not_deleted}
			WHERE id_msg IN ({array_int:topics})',
			[
				'topics' => array_values($topics_to_restore),
				'not_deleted' => 0,
			]
		);

		// Now let's get the data for these topics.
		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, t.id_board, t.id_first_msg, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic IN ({array_int:topics})',
			[
				'topics' => array_keys($topics_to_restore),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Let's see if the board that we are returning to has post count enabled.
			$count_posts = $boards_to_restore[$row['id_board']];

			if (empty($count_posts))
			{
				// Let's get the members that need their post count restored.
				$request2 = $smcFunc['db']->query('', '
					SELECT id_member, id_character, COUNT(id_msg) AS post_count
					FROM {db_prefix}messages
					WHERE id_topic = {int:topic}
						AND approved = {int:is_approved}
						AND deleted = {int:not_deleted}

					GROUP BY id_member, id_character',
					[
						'topic' => $row['id_topic'],
						'is_approved' => 1,
						'not_deleted' => 0,
					]
				);

				while ($member = $smcFunc['db']->fetch_assoc($request2))
				{
					updateMemberData($member['id_member'], ['posts' => 'posts + ' . $member['post_count']]);
					updateCharacterData($member['id_character'], ['posts' => 'posts + ' . $member['post_count']]);
				}
				$smcFunc['db']->free_result($request2);
			}

			// Update the search logic.
			updateStats('subject', $row['id_topic'], $row['subject']);

			// Log it.
			$topics_updated[$row['id_topic']] = true;
			$boards_updated[$row['id_board']] = true;
			logAction('restore_topic', ['topic' => $row['id_topic'], 'board' => $row['id_board']]);
		}
		$smcFunc['db']->free_result($request);
	}

	if (!empty($messages_to_restore))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}messages
			SET deleted = {int:not_deleted}
			WHERE id_msg IN ({array_int:messages_to_restore})',
			[
				'messages_to_restore' => $messages_to_restore,
				'not_deleted' => 0,
			]
		);

		$request = $smcFunc['db']->query('', '
			SELECT m.id_board, m.subject, m.id_topic
			FROM {db_prefix}messages AS m
			WHERE id_msg IN ({array_int:messages_to_restore})',
			[
				'messages_to_restore' => $messages_to_restore,
			]
		);
		$counted_topics = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($counted_topics[$row['id_topic']]))
			{
				$counted_topics[$row['id_topic']] = true;

				// Let's see if the board that we are returning to has post count enabled.
				$count_posts = $boards_to_restore[$row['id_board']];

				if (empty($count_posts))
				{
					// Let's get the members that need their post count restored.
					$request2 = $smcFunc['db']->query('', '
						SELECT id_member, id_character, COUNT(id_msg) AS post_count
						FROM {db_prefix}messages
						WHERE id_topic = {int:topic}
							AND approved = {int:is_approved}
							AND deleted = {int:not_deleted}

						GROUP BY id_member, id_character',
						[
							'topic' => $row['id_topic'],
							'is_approved' => 1,
							'not_deleted' => 0,
						]
					);

					while ($member = $smcFunc['db']->fetch_assoc($request2))
					{
						updateMemberData($member['id_member'], ['posts' => 'posts + ' . $member['post_count']]);
						updateCharacterData($member['id_character'], ['posts' => 'posts + ' . $member['post_count']]);
					}
					$smcFunc['db']->free_result($request2);
				}
			}

			// Log it.
			$topics_updated[$row['id_topic']] = true;
			$boards_updated[$row['id_board']] = true;
            logAction('restore_posts', ['topic' => $topic, 'subject' => $row['subject'], 'board' => $row['id_board']]);
		}
		$smcFunc['db']->free_result($request);
	}

	// Now to fix up the various pointers in those topics + boards.
	update_topic_stats(array_keys($topics_updated));

	// Now to fix up the board stats.
	update_board_stats(array_keys($boards_updated));

	// Update stats.
	updateStats('topic');
	updateStats('message');

	// Just send them to the index if they get here.
	redirectexit();
}

function update_topic_stats(array $topics_updated)
{
	global $smcFunc;

	if (empty($topics_updated))
	{
		return;
	}

	$topics_stats = [];

	$request = $smcFunc['db']->query('', '
		SELECT m.id_topic, MIN(m.id_msg) AS id_first_msg, MAX(m.id_msg) AS id_last_msg, COUNT(m.id_msg) AS message_count, m.approved, m.deleted, t.deleted AS deleted_topic
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
		WHERE id_topic IN ({array_int:topics_updated})
		GROUP BY m.id_topic, m.approved, m.deleted, t.deleted
		ORDER BY approved ASC',
		[
			'topics_updated' => $topics_updated,
		]
	);

	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($topics_stats[$row['id_topic']]))
		{
			$topics_stats[$row['id_topic']] = [
				'num_replies' => 0,
				'unapproved_posts' => 0,
				'deleted_replies' => 0,
				'id_first_msg' => 9999999999,
				'id_last_msg' => 0,
				'topic_deleted' => 0,
			];
		}

		$topics_stats[$row['id_topic']]['topic_deleted'] = $row['deleted_topic'];


		if ($row['id_first_msg'] < $topics_stats[$row['id_topic']]['id_first_msg'])
		{
			$topics_stats[$row['id_topic']]['id_first_msg'] = $row['id_first_msg'];
		}

		if ($row['deleted'])
		{
			// This gets us the total number of deleted messages in the topic (including the first message if it's deleted).
			$topics_stats[$row['id_topic']]['deleted_replies'] += $row['message_count'];
		}
		elseif (!$row['approved'])
		{
			$topics_stats[$row['id_topic']]['unapproved_posts'] += $row['message_count'];
		}
		else
		{
			$topics_stats[$row['id_topic']]['num_replies'] += $row['message_count'];
			// We only care about the last message for the non-deleted approved posts.
			$topics_stats[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		}
	}
	$smcFunc['db']->free_result($request);

	foreach ($topics_stats as $topic_id => $topic)
	{
		// First, adjust for the numbers depending on the topic being deleted.
		if ($topic['topic_deleted'])
		{
			// If the topic is deleted, one of the deleted replies is really the first message.
			$topics_stats[$topic_id]['deleted_replies'] = max(0, $topic['deleted_replies'] - 1);
		}
		else
		{
			// If it's not deleted, one of the regular 'replies' is not a reply either.
			$topics_stats[$topic_id]['num_replies'] = max(0, $topic['num_replies'] - 1);
		}

		// And just in case something went weird with the id_last_msg...
		if (empty($topic['id_last_msg']))
		{
			$topics_stats[$topic_id]['id_last_msg'] = $topic['id_first_msg'];
		}

		// Now actually do the update.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topics
			SET id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:topic}',
			[
				'topic' => $topic_id,
				'num_replies' => $topic['num_replies'],
				'unapproved_posts' => $topic['unapproved_posts'],
				'deleted_replies' => $topic['deleted_replies'],
				'id_first_msg' => $topic['id_first_msg'],
				'id_last_msg' => $topic['id_last_msg'],
			]
		);
	}
}

function update_board_stats(array $boards_updated)
{
	global $smcFunc, $sourcedir;

	if (empty($boards_updated))
	{
		return;
	}

	$board_stats = [];
	$request = $smcFunc['db']->query('', '
		SELECT id_board, COUNT(*) AS num_topics, SUM(num_replies) AS num_replies, SUM(unapproved_posts) AS unapproved_posts, SUM(deleted_replies) AS deleted_replies, SUM(deleted) AS total_deleted, approved, deleted
		FROM {db_prefix}topics
		WHERE id_board IN ({array_int:boards})
		GROUP BY id_board, approved, deleted',
		[
			'boards' => $boards_updated,
			'not_deleted' => 0,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!isset($board_stats[$row['id_board']]))
		{
			$board_stats[$row['id_board']] = [
				'num_topics' => 0,
				'num_posts' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
				'deleted_posts' => 0,
				'deleted_topics' => 0,
			];
		}

		if ($row['deleted'])
		{
			// Deleted is a bit tricky. The posts themselves, that's pretty trivial.
			$board_stats[$row['id_board']]['deleted_posts'] += $row['deleted_replies'];
			// Deleted topics on the other hand is a bit complicated because we don't have the actual topic count.
			// We have the deleted score * number of rows, so we need to divide back (because 1 = author deleted, 2 = mod deleted)
			$board_stats[$row['id_board']]['deleted_topics'] += $row['total_deleted'] / $row['deleted'];
			// And deleted topics don't have posts that count towards non-deleted stats.
		}
		elseif (!$row['approved'])
		{
			// If the topics aren't deleted, we need to scoop up those that aren't approved. (We don't care if both approved + deleted, deleted wins)
			$board_stats[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
			$board_stats[$row['id_board']]['num_posts'] += $row['num_replies']; // Unapproved topics can have approved replies.
			$board_stats[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts']; // We don't count the additional post that makes up an unapproved topic's first post, that's an unapproved topic.
		}
		else
		{
			// Approved topics for this board.
			$board_stats[$row['id_board']]['num_topics'] += $row['num_topics'];
			$board_stats[$row['id_board']]['num_posts'] += $row['num_replies'] + $row['num_topics']; // The first post in a topic is not a reply...
			$board_stats[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts']; // Even approved topics can have unapproved posts.
		}
	}
	$smcFunc['db']->free_result($request);

	foreach ($board_stats as $board_id => $board)
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}boards
			SET num_topics = {int:num_topics},
				num_posts = {int:num_posts},
				unapproved_posts = {int:unapproved_posts},
				unapproved_topics = {int:unapproved_topics},
				deleted_posts = {int:deleted_posts},
				deleted_topics = {int:deleted_topics}
			WHERE id_board = {int:board}',
			[
				'num_topics' => $board['num_topics'],
				'num_posts' => $board['num_posts'],
				'unapproved_posts' => $board['unapproved_posts'],
				'unapproved_topics' => $board['unapproved_topics'],
				'board' => $board_id,
			]
		);
	}

	require_once($sourcedir . '/Subs-Post.php');
	updateLastMessages(array_keys($boards_updated));
}
