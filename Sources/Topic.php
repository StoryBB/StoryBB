<?php

/**
 * This file takes care of actions on topics:
 * lock/unlock a topic, sticky/unsticky it,
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Model\TopicPrefix;

/**
 * Locks a topic... either by way of a moderator or the topic starter.
 * What this does:
 *  - locks a topic, toggles between locked/unlocked/admin locked.
 *  - only admins can unlock topics locked by other admins.
 *  - requires the lock_own or lock_any permission.
 *  - logs the action to the moderator log.
 *  - returns to the topic after it is done.
 *  - it is accessed via ?action=lock.
*/
function LockTopic()
{
	global $topic, $user_info, $sourcedir, $board, $smcFunc;

	// Just quit if there's no topic to lock.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// Get Subs-Post.php for sendNotifications.
	require_once($sourcedir . '/Subs-Post.php');

	// Find out who started the topic - in case User Topic Locking is enabled.
	$request = $smcFunc['db']->query('', '
		SELECT id_member_started, locked
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
		]
	);
	list ($starter, $locked) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Can you lock topics here, mister?
	$user_lock = !allowedTo('lock_any');
	if ($user_lock && $starter == $user_info['id'])
		isAllowedTo('lock_own');
	else
		isAllowedTo('lock_any');

	// Locking with high privileges.
	if ($locked == '0' && !$user_lock)
		$locked = '1';
	// Locking with low privileges.
	elseif ($locked == '0')
		$locked = '2';
	// Unlocking - make sure you don't unlock what you can't.
	elseif ($locked == '2' || ($locked == '1' && !$user_lock))
		$locked = '0';
	// You cannot unlock this!
	else
		fatal_lang_error('locked_by_admin', 'user');

	// Actually lock the topic in the database with the new value.
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}topics
		SET locked = {int:locked}
		WHERE id_topic = {int:current_topic}',
		[
			'current_topic' => $topic,
			'locked' => $locked,
		]
	);

	// If they are allowed a "moderator" permission, log it in the moderator log.
	if (!$user_lock)
		logAction($locked ? 'lock' : 'unlock', ['topic' => $topic, 'board' => $board]);
	// Notify people that this topic has been locked?
	sendNotifications($topic, empty($locked) ? 'unlock' : 'lock');

	// Back to the topic!
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . ';moderate');
}

/**
 * Sticky a topic.
 * Can't be done by topic starters - that would be annoying!
 * What this does:
 *  - stickies a topic - toggles between sticky and normal.
 *  - requires the make_sticky permission.
 *  - adds an entry to the moderator log.
 *  - when done, sends the user back to the topic.
 *  - accessed via ?action=sticky.
 */
function Sticky()
{
	global $topic, $board, $sourcedir, $smcFunc;

	if (($_GET['sa'] ?? '') == 'order')
	{
		return ReorderSticky();
	}

	// Make sure the user can sticky it, and they are stickying *something*.
	isAllowedTo('make_sticky');

	// You can't sticky a board or something!
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// We need Subs-Post.php for the sendNotifications() function.
	require_once($sourcedir . '/Subs-Post.php');

	// Is this topic already stickied, or no?
	$request = $smcFunc['db']->query('', '
		SELECT is_sticky
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
		]
	);
	list ($is_sticky) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Toggle the sticky value.... pretty simple ;).
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}topics
		SET is_sticky = {int:is_sticky}
		WHERE id_topic = {int:current_topic}',
		[
			'current_topic' => $topic,
			'is_sticky' => empty($is_sticky) ? 1 : 0,
		]
	);

	// Log this sticky action - always a moderator thing.
	logAction(empty($is_sticky) ? 'sticky' : 'unsticky', ['topic' => $topic, 'board' => $board]);
	// Notify people that this topic has been stickied?
	if (empty($is_sticky))
		sendNotifications($topic, 'sticky');

	// Take them back to the now stickied topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . ';moderate');
}

function ReorderSticky()
{
	global $board, $txt, $context, $smcFunc, $scripturl, $modSettings;

	// @todo Does this need a new permission?
	isAllowedTo('make_sticky');

	// You can't sticky that doesn't exist!
	if (empty($board))
	{
		fatal_lang_error('no_access', false);
	}

	if (isset($_POST['order']) && is_array($_POST['order']))
	{
		checkSession();

		// Whatever happens, we need to get the sticky topics in this board.
		$topics = [];
		$request = $smcFunc['db']->query('', '
			SELECT id_topic, is_sticky
			FROM {db_prefix}topics AS t
			WHERE t.id_board = {int:board}
				AND t.is_sticky > 0
			ORDER BY t.is_sticky DESC, t.id_last_msg DESC',
			[
				'board' => $board,
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$topics[$row['id_topic']] = array(
				'old' => (int) $row['is_sticky'],
			);
		}
		$smcFunc['db']->free_result($request);

		if (empty($topics))
		{
			fatal_lang_error('cannot_reorder_sticky', false);
		}

		// So, we have an ordering. Let's do something with that.
		$max = count($topics);
		foreach ($_POST['order'] as $k => $v)
		{
			if (isset($topics[$v]))
			{
				// K here is the item position starting at 0 on a scale of 0 to n-1.
				// Doing it this way means whatever happens we don't mess it up or unsticky things accidentally.
				$k = (int) $k; 
				$topics[$v]['new'] = max($max - (int) $k, 1);
			}
		}

		// Did we miss any? Or any bad data somehow?
		foreach ($topics as $id => $info)
		{
			// Make sure we don't leave them behind here either but push them down the list. It shouldn't happen but you can never be too sure.
			if (!isset($topics[$id]['new']))
			{
				$info['new'] = 1;
			}
			// While we're at it, is this one actually changing? Don't query it if it isn't.
			if ($info['old'] == $info['new'])
			{
				unset($topics[$id]);
			}
		}

		if (!empty($topics))
		{
			foreach ($topics as $id => $info)
			{
				$smcFunc['db']->query('', '
					UPDATE {db_prefix}topics
					SET is_sticky = {int:new}
					WHERE id_topic = {int:topic}',
					array(
						'new' => $info['new'],
						'topic' => $id,
					)
				);
			}
		}

		// And we're done. Back to the board.
		redirectexit('board=' . $board);
	}
	else
	{
		// Get all the sticky topics so we can display the list.
		$context['sticky_topics'] = [];
		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, t.is_sticky, mf.subject AS first_subject, ml.subject AS last_subject,
			t.locked, t.id_redirect_topic, t.id_poll, t.num_replies, t.num_views,
			t.id_last_msg, t.id_first_msg,
			mf.poster_name AS first_member_name,
			mf.id_member AS first_id_member, cf.id_character AS first_character,
			ml.id_member AS last_id_member, cl.id_character AS last_character,
			COALESCE(cf.character_name, memf.real_name, mf.poster_name) AS first_display_name,
 			cf.avatar AS first_member_avatar, memf.email_address AS first_member_mail,
 			COALESCE(af.id_attach, 0) AS first_member_id_attach, af.filename AS first_member_filename,
 			af.attachment_type AS first_member_attach_type,
 			mf.poster_time AS first_poster_time,
 			ml.poster_name AS last_member_name,
 			cl.avatar As last_member_avatar, meml.email_address AS last_member_mail,
			COALESCE(cl.character_name, meml.real_name, ml.poster_name) AS last_display_name,
 			COALESCE(al.id_attach, 0) AS last_member_id_attach, al.filename AS last_member_filename,
 			al.attachment_type AS last_member_attach_type,
 			ml.poster_time AS last_poster_time
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}characters AS cf ON (cf.id_character = mf.id_character)
				LEFT JOIN {db_prefix}characters AS cl ON (cl.id_character = ml.id_character)
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_character = mf.id_character AND af.attachment_type = 1)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_character = ml.id_character AND al.attachment_type = 1)
			WHERE t.id_board = {int:board}
				AND t.is_sticky > 0
			ORDER BY t.is_sticky DESC, t.id_last_msg DESC',
			[
				'board' => $board,
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$context['sticky_topics'][$row['id_topic']] = array_merge($row, [
				'id' => $row['id_topic'],
				'first_post' => [
					'id' => $row['id_first_msg'],
					'member' => [
						'username' => $row['first_member_name'],
						'name' => $row['first_display_name'],
						'id' => $row['first_id_member'],
						'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
						'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . (!empty($row['first_character']) ? ';area=characters;char=' . $row['first_character'] : '') . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name'],
					],
					'time' => timeformat($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'subject' => $row['first_subject'],
					'preview' => '',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>',
				],
				'last_post' => [
					'id' => $row['id_last_msg'],
					'member' => [
						'username' => $row['last_member_name'],
						'name' => $row['last_display_name'],
						'id' => $row['last_id_member'],
						'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
						'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . (!empty($row['last_character']) ? ';area=characters;char=' . $row['last_character'] : '') . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name'],
					],
					'time' => timeformat($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'subject' => $row['last_subject'],
					'preview' => '',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg'] . '#msg' . $row['id_last_msg']),
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg'] . '#msg' . $row['id_last_msg']) . '" rel="nofollow">' . $row['last_subject'] . '</a>'
				],
				'is_sticky' => !empty($row['is_sticky']),
				'is_locked' => !empty($row['locked']),
				'is_redirect' => !empty($row['id_redirect_topic']),
				'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
				'is_posted_in' => false,
				'is_watched' => false,
				'subject' => $row['first_subject'],
				'new' => false,
				'new_from' => 0,
				'newtime' => 0,
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.new#new',
				'pages' => '',
				'replies' => comma_format($row['num_replies']),
				'views' => comma_format($row['num_views']),
				'approved' => 0,
				'unapproved_posts' => 0,
				'css_class' => 'windowbg',
			]);

			// Last post member avatar
			$context['sticky_topics'][$row['id_topic']]['last_post']['member']['avatar'] = set_avatar_data([
				'avatar' => $row['last_member_avatar'],
				'email' => $row['last_member_mail'],
				'filename' => !empty($row['last_member_filename']) ? $row['last_member_filename'] : '',
			]);

			// First post member avatar
			$context['sticky_topics'][$row['id_topic']]['first_post']['member']['avatar'] = set_avatar_data([
				'avatar' => $row['first_member_avatar'],
				'email' => $row['first_member_mail'],
				'filename' => !empty($row['first_member_filename']) ? $row['first_member_filename'] : '',
			]);
		}
		$smcFunc['db']->free_result($request);

		if (empty($context['sticky_topics']))
		{
			fatal_lang_error('cannot_reorder_sticky', false);
		}

		$prefixes = TopicPrefix::get_prefixes_for_topic_list(array_keys($context['sticky_topics']));
		foreach ($prefixes as $prefix_topic => $prefixes)
		{
			$context['sticky_topics'][$prefix_topic]['prefixes'] = $prefixes;
		}

		$context['page_title'] = $txt['order_sticky'];
		$context['linktree'][] = [
			'name' => $txt['order_sticky'],
		];

		$context['sub_template'] = 'msgIndex_reorder';

		loadJavaScriptFile('jquery-ui-1.12.1-sortable.min.js', ['default_theme' => true]);
		addInlineJavascript('
		$(\'.sortable\').sortable({handle: ".draggable-handle", items: "> .windowbg"});', true);

		$context['this_url'] = $scripturl . '?action=sticky;sa=order;board=' . $board . '.0';
	}
}
