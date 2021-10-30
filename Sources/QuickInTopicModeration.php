<?php

/**
 * In-topic quick moderation.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

function QuickInTopicModeration()
{
	global $sourcedir, $topic, $board, $user_info, $smcFunc, $modSettings, $context;

	// Check the session = get or post.
	checkSession('request');

	require_once($sourcedir . '/RemoveTopic.php');

	if (empty($_REQUEST['msgs']))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);

	$messages = [];
	foreach ($_REQUEST['msgs'] as $dummy)
		$messages[] = (int) $dummy;

	// We are restoring messages. We handle this in another place.
	if (isset($_REQUEST['restore_selected']))
		redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_var'] . '=' . $context['session_id']);
	if (isset($_REQUEST['split_selection']))
	{
		$request = $smcFunc['db']->query('', '
			SELECT subject
			FROM {db_prefix}messages
			WHERE id_msg = {int:message}
			LIMIT 1',
			[
				'message' => min($messages),
			]
		);
		list($subname) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);
		$_SESSION['split_selection'][$topic] = $messages;
		redirectexit('action=splittopics;sa=selectTopics;topic=' . $topic . '.0;subname_enc=' . urlencode($subname) . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Allowed to delete any message?
	if (allowedTo('delete_any'))
		$allowed_all = true;
	// Allowed to delete replies to their messages?
	elseif (allowedTo('delete_replies'))
	{
		$request = $smcFunc['db']->query('', '
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => $topic,
			]
		);
		list ($starter) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$allowed_all = $starter == $user_info['id'];
	}
	else
		$allowed_all = false;

	// Make sure they're allowed to delete their own messages, if not any.
	if (!$allowed_all)
		isAllowedTo('delete_own');

	// Allowed to remove which messages?
	$request = $smcFunc['db']->query('', '
		SELECT id_msg, subject, id_member, poster_time
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:message_list})
			AND id_topic = {int:current_topic}' . (!$allowed_all ? '
			AND id_member = {int:current_member}' : '') . '
		LIMIT {int:limit}',
		[
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'message_list' => $messages,
			'limit' => count($messages),
		]
	);
	$messages = [];
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		if (!$allowed_all && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
			continue;

		$messages[$row['id_msg']] = [$row['subject'], $row['id_member']];
	}
	$smcFunc['db']->free_result($request);

	// Get the first message in the topic - because you can't delete that!
	$request = $smcFunc['db']->query('', '
		SELECT id_first_msg, id_last_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
		]
	);
	list ($first_message, $last_message) = $smcFunc['db']->fetch_row($request);
	$smcFunc['db']->free_result($request);

	// Delete all the messages we know they can delete. ($messages)
	foreach ($messages as $message => $info)
	{
		// Just skip the first message - if it's not the last.
		if ($message == $first_message && $message != $last_message)
			continue;
		// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
		elseif ($message == $first_message)
			$topicGone = true;

		removeMessage($message);

		// Log this moderation action ;).
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != $user_info['id']))
			logAction('delete', ['topic' => $topic, 'subject' => $info[0], 'member' => $info[1], 'board' => $board]);
	}

	redirectexit(!empty($topicGone) ? 'board=' . $board : 'topic=' . $topic . '.' . $_REQUEST['start']);
}
