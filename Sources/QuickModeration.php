<?php

/**
 * Handles moderation from the message index.
 * @todo refactor this...
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

function QuickModeration()
{
	global $sourcedir, $board, $user_info, $modSettings, $smcFunc, $context;

	// Check the session = get or post.
	checkSession('request');

	// Lets go straight to the restore area.
	if (isset($_REQUEST['qaction']) && $_REQUEST['qaction'] == 'restore' && !empty($_REQUEST['topics']))
		redirectexit('action=restoretopic;topics=' . implode(',', $_REQUEST['topics']) . ';' . $context['session_var'] . '=' . $context['session_id']);

	if (isset($_SESSION['topicseen_cache']))
		$_SESSION['topicseen_cache'] = [];

	// This is going to be needed to send off the notifications and for updateLastMessages().
	require_once($sourcedir . '/Subs-Post.php');

	// Remember the last board they moved things to.
	if (isset($_REQUEST['move_to']))
		$_SESSION['move_to_topic'] = $_REQUEST['move_to'];

	// Only a few possible actions.
	$possibleActions = [];

	if (!empty($board))
	{
		$boards_can = [
			'make_sticky' => allowedTo('make_sticky') ? [$board] : [],
			'move_any' => allowedTo('move_any') ? [$board] : [],
			'move_own' => allowedTo('move_own') ? [$board] : [],
			'remove_any' => allowedTo('remove_any') ? [$board] : [],
			'remove_own' => allowedTo('remove_own') ? [$board] : [],
			'lock_any' => allowedTo('lock_any') ? [$board] : [],
			'lock_own' => allowedTo('lock_own') ? [$board] : [],
			'merge_any' => allowedTo('merge_any') ? [$board] : [],
			'approve_posts' => allowedTo('approve_posts') ? [$board] : [],
		];

		$redirect_url = 'board=' . $board . '.' . $_REQUEST['start'];
	}
	else
	{
		/**
		 * @todo Ugly. There's no getting around this, is there?
		 * @todo Maybe just do this on the actions people want to use?
		 */
		$boards_can = boardsAllowedTo(['make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'lock_any', 'lock_own', 'merge_any', 'approve_posts'], true, false);

		$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : (isset($_SESSION['old_url']) ? $_SESSION['old_url'] : '');
	}

	// Are we enforcing the "no moving topics to boards where you can't post new ones" rule?
	if (!$user_info['is_admin'] && !$modSettings['topic_move_any'])
	{
		// Don't count this board, if it's specified
		if (!empty($board))
		{
			$boards_can['post_new'] = array_diff(boardsAllowedTo('post_new'), [$board]);
		}
		else
		{
			$boards_can['post_new'] = boardsAllowedTo('post_new');
		}

		if (empty($boards_can['post_new']))
		{
			$boards_can['move_any'] = $boards_can['move_own'] = [];
		}
	}

	if (!$user_info['is_guest'])
		$possibleActions[] = 'markread';
	if (!empty($boards_can['make_sticky']))
		$possibleActions[] = 'sticky';
	if (!empty($boards_can['move_any']) || !empty($boards_can['move_own']))
		$possibleActions[] = 'move';
	if (!empty($boards_can['remove_any']) || !empty($boards_can['remove_own']))
		$possibleActions[] = 'remove';
	if (!empty($boards_can['lock_any']) || !empty($boards_can['lock_own']))
		$possibleActions[] = 'lock';
	if (!empty($boards_can['merge_any']))
		$possibleActions[] = 'merge';
	if (!empty($boards_can['approve_posts']))
		$possibleActions[] = 'approve';

	// Two methods: $_REQUEST['actions'] (id_topic => action), and $_REQUEST['topics'] and $_REQUEST['qaction'].
	// (if action is 'move', $_REQUEST['move_to'] or $_REQUEST['move_tos'][$topic] is used.)
	if (!empty($_REQUEST['topics']))
	{
		// If the action isn't valid, just quit now.
		if (empty($_REQUEST['qaction']) || !in_array($_REQUEST['qaction'], $possibleActions))
			redirectexit($redirect_url);

		// Merge requires all topics as one parameter and can be done at once.
		if ($_REQUEST['qaction'] == 'merge')
		{
			// Merge requires at least two topics.
			if (empty($_REQUEST['topics']) || count($_REQUEST['topics']) < 2)
				redirectexit($redirect_url);

			require_once($sourcedir . '/SplitTopics.php');
			return MergeExecute($_REQUEST['topics']);
		}

		// Just convert to the other method, to make it easier.
		foreach ($_REQUEST['topics'] as $topic)
			$_REQUEST['actions'][(int) $topic] = $_REQUEST['qaction'];
	}

	// Weird... how'd you get here?
	if (empty($_REQUEST['actions']))
		redirectexit($redirect_url);

	// Validate each action.
	$temp = [];
	foreach ($_REQUEST['actions'] as $topic => $action)
	{
		if (in_array($action, $possibleActions))
			$temp[(int) $topic] = $action;
	}
	$_REQUEST['actions'] = $temp;

	if (!empty($_REQUEST['actions']))
	{
		// Find all topics...
		$request = $smcFunc['db']->query('', '
			SELECT id_topic, id_member_started, id_board, locked, approved, unapproved_posts
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:action_topic_ids})
			LIMIT {int:limit}',
			[
				'action_topic_ids' => array_keys($_REQUEST['actions']),
				'limit' => count($_REQUEST['actions']),
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!empty($board))
			{
				if ($row['id_board'] != $board || (!$row['approved'] && !allowedTo('approve_posts')))
					unset($_REQUEST['actions'][$row['id_topic']]);
			}
			else
			{
				// Don't allow them to act on unapproved posts they can't see...
				if (!$row['approved'] && !in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))
					unset($_REQUEST['actions'][$row['id_topic']]);
				// Goodness, this is fun.  We need to validate the action.
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'sticky' && !in_array(0, $boards_can['make_sticky']) && !in_array($row['id_board'], $boards_can['make_sticky']))
					unset($_REQUEST['actions'][$row['id_topic']]);
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'move' && !in_array(0, $boards_can['move_any']) && !in_array($row['id_board'], $boards_can['move_any']) && ($row['id_member_started'] != $user_info['id'] || (!in_array(0, $boards_can['move_own']) && !in_array($row['id_board'], $boards_can['move_own']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'remove' && !in_array(0, $boards_can['remove_any']) && !in_array($row['id_board'], $boards_can['remove_any']) && ($row['id_member_started'] != $user_info['id'] || (!in_array(0, $boards_can['remove_own']) && !in_array($row['id_board'], $boards_can['remove_own']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
				// @todo $locked is not set, what are you trying to do? (taking the change it is supposed to be $row['locked'])
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'lock' && !in_array(0, $boards_can['lock_any']) && !in_array($row['id_board'], $boards_can['lock_any']) && ($row['id_member_started'] != $user_info['id'] || $row['locked'] == 1 || (!in_array(0, $boards_can['lock_own']) && !in_array($row['id_board'], $boards_can['lock_own']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
				// If the topic is approved then you need permission to approve the posts within.
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'approve' && (!$row['unapproved_posts'] || (!in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
			}
		}
		$smcFunc['db']->free_result($request);
	}

	$stickyCache = [];
	$moveCache = [0 => [], 1 => []];
	$removeCache = [];
	$lockCache = [];
	$markCache = [];
	$approveCache = [];

	// Separate the actions.
	foreach ($_REQUEST['actions'] as $topic => $action)
	{
		$topic = (int) $topic;

		if ($action == 'markread')
			$markCache[] = $topic;
		elseif ($action == 'sticky')
			$stickyCache[] = $topic;
		elseif ($action == 'move')
		{
			require_once($sourcedir . '/MoveTopic.php');
			moveTopicConcurrence();

			// $moveCache[0] is the topic, $moveCache[1] is the board to move to.
			$moveCache[1][$topic] = (int) (isset($_REQUEST['move_tos'][$topic]) ? $_REQUEST['move_tos'][$topic] : $_REQUEST['move_to']);

			if (empty($moveCache[1][$topic]))
				continue;

			$moveCache[0][] = $topic;
		}
		elseif ($action == 'remove')
			$removeCache[] = $topic;
		elseif ($action == 'lock')
			$lockCache[] = $topic;
		elseif ($action == 'approve')
			$approveCache[] = $topic;
	}

	if (empty($board))
		$affectedBoards = [];
	else
		$affectedBoards = [$board => [0, 0]];

	// Do all the stickies...
	if (!empty($stickyCache))
	{
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}topics
			SET is_sticky = CASE WHEN is_sticky = {int:is_not_sticky} THEN 1 ELSE 0 END
			WHERE id_topic IN ({array_int:sticky_topic_ids})',
			[
				'sticky_topic_ids' => $stickyCache,
				'is_not_sticky' => 0,
			]
		);

		// Get the board IDs and Sticky status
		$request = $smcFunc['db']->query('', '
			SELECT id_topic, id_board, is_sticky
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:sticky_topic_ids})
			LIMIT {int:limit}',
			[
				'sticky_topic_ids' => $stickyCache,
				'limit' => count($stickyCache),
			]
		);
		$stickyCacheBoards = [];
		$stickyCacheStatus = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$stickyCacheBoards[$row['id_topic']] = $row['id_board'];
			$stickyCacheStatus[$row['id_topic']] = empty($row['is_sticky']);
		}
		$smcFunc['db']->free_result($request);
	}

	// Move sucka! (this is, by the by, probably the most complicated part....)
	if (!empty($moveCache[0]))
	{
		// I know - I just KNOW you're trying to beat the system.  Too bad for you... we CHECK :P.
		$request = $smcFunc['db']->query('', '
			SELECT t.id_topic, t.id_board, b.count_posts
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			WHERE t.id_topic IN ({array_int:move_topic_ids})' . (!empty($board) && !allowedTo('move_any') ? '
				AND t.id_member_started = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			[
				'current_member' => $user_info['id'],
				'move_topic_ids' => $moveCache[0],
				'limit' => count($moveCache[0])
			]
		);
		$moveTos = [];
		$moveCache2 = [];
		$countPosts = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$to = $moveCache[1][$row['id_topic']];

			if (empty($to))
				continue;

			// Does this topic's board count the posts or not?
			$countPosts[$row['id_topic']] = empty($row['count_posts']);

			if (!isset($moveTos[$to]))
				$moveTos[$to] = [];

			$moveTos[$to][] = $row['id_topic'];

			// For reporting...
			$moveCache2[] = [$row['id_topic'], $row['id_board'], $to];
		}
		$smcFunc['db']->free_result($request);

		$moveCache = $moveCache2;

		require_once($sourcedir . '/MoveTopic.php');

		// Do the actual moves...
		foreach ($moveTos as $to => $topics)
			moveTopics($topics, $to);

		// Does the post counts need to be updated?
		if (!empty($moveTos))
		{
			$topicRecounts = [];
			$request = $smcFunc['db']->query('', '
				SELECT id_board, count_posts
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:move_boards})',
				[
					'move_boards' => array_keys($moveTos),
				]
			);

			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				$cp = empty($row['count_posts']);

				// Go through all the topics that are being moved to this board.
				foreach ($moveTos[$row['id_board']] as $topic)
				{
					// If both boards have the same value for post counting then no adjustment needs to be made.
					if ($countPosts[$topic] != $cp)
					{
						// If the board being moved to does count the posts then the other one doesn't so add to their post count.
						$topicRecounts[$topic] = $cp ? '+' : '-';
					}
				}
			}

			$smcFunc['db']->free_result($request);

			if (!empty($topicRecounts))
			{
				$members = [];

				// Get all the members who have posted in the moved topics.
				$request = $smcFunc['db']->query('', '
					SELECT id_member, id_topic
					FROM {db_prefix}messages
					WHERE id_topic IN ({array_int:moved_topic_ids})',
					[
						'moved_topic_ids' => array_keys($topicRecounts),
					]
				);

				while ($row = $smcFunc['db']->fetch_assoc($request))
				{
					if (!isset($members[$row['id_member']]))
						$members[$row['id_member']] = 0;

					if ($topicRecounts[$row['id_topic']] === '+')
						$members[$row['id_member']] += 1;
					else
						$members[$row['id_member']] -= 1;
				}

				$smcFunc['db']->free_result($request);

				// And now update them member's post counts
				foreach ($members as $id_member => $post_adj)
					updateMemberData($id_member, ['posts' => 'posts + ' . $post_adj]);

			}
		}
	}

	// Now delete the topics...
	if (!empty($removeCache))
	{
		// They can only delete their own topics. (we wouldn't be here if they couldn't do that..)
		$result = $smcFunc['db']->query('', '
			SELECT id_topic, id_board
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:removed_topic_ids})' . (!empty($board) && !allowedTo('remove_any') ? '
				AND id_member_started = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			[
				'current_member' => $user_info['id'],
				'removed_topic_ids' => $removeCache,
				'limit' => count($removeCache),
			]
		);

		$removeCache = [];
		$removeCacheBoards = [];
		while ($row = $smcFunc['db']->fetch_assoc($result))
		{
			$removeCache[] = $row['id_topic'];
			$removeCacheBoards[$row['id_topic']] = $row['id_board'];
		}
		$smcFunc['db']->free_result($result);

		// Maybe *none* were their own topics.
		if (!empty($removeCache))
		{
			// Gotta send the notifications *first*!
			foreach ($removeCache as $topic)
			{
				// Only log the topic ID if it's not in the recycle board.
				logAction('remove', [(empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $removeCacheBoards[$topic] ? 'topic' : 'old_topic_id') => $topic, 'board' => $removeCacheBoards[$topic]]);
				sendNotifications($topic, 'remove');
			}

			require_once($sourcedir . '/RemoveTopic.php');
			removeTopics($removeCache);
		}
	}

	// Approve the topics...
	if (!empty($approveCache))
	{
		// We need unapproved topic ids and their authors!
		$request = $smcFunc['db']->query('', '
			SELECT id_topic, id_member_started
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:approve_topic_ids})
				AND approved = {int:not_approved}
			LIMIT {int:limit}',
			[
				'approve_topic_ids' => $approveCache,
				'not_approved' => 0,
				'limit' => count($approveCache),
			]
		);
		$approveCache = [];
		$approveCacheMembers = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$approveCache[] = $row['id_topic'];
			$approveCacheMembers[$row['id_topic']] = $row['id_member_started'];
		}
		$smcFunc['db']->free_result($request);

		// Any topics to approve?
		if (!empty($approveCache))
		{
			// Handle the approval part...
			approveTopics($approveCache);

			// Time for some logging!
			foreach ($approveCache as $topic)
				logAction('approve_topic', ['topic' => $topic, 'member' => $approveCacheMembers[$topic]]);
		}
	}

	// And (almost) lastly, lock the topics...
	if (!empty($lockCache))
	{
		$lockStatus = [];

		// Gotta make sure they CAN lock/unlock these topics...
		if (!empty($board) && !allowedTo('lock_any'))
		{
			// Make sure they started the topic AND it isn't already locked by someone with higher priv's.
			$result = $smcFunc['db']->query('', '
				SELECT id_topic, locked, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:locked_topic_ids})
					AND id_member_started = {int:current_member}
					AND locked IN (2, 0)
				LIMIT {int:limit}',
				[
					'current_member' => $user_info['id'],
					'locked_topic_ids' => $lockCache,
					'limit' => count($lockCache),
				]
			);
			$lockCache = [];
			$lockCacheBoards = [];
			while ($row = $smcFunc['db']->fetch_assoc($result))
			{
				$lockCache[] = $row['id_topic'];
				$lockCacheBoards[$row['id_topic']] = $row['id_board'];
				$lockStatus[$row['id_topic']] = empty($row['locked']);
			}
			$smcFunc['db']->free_result($result);
		}
		else
		{
			$result = $smcFunc['db']->query('', '
				SELECT id_topic, locked, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:locked_topic_ids})
				LIMIT {int:limit}',
				[
					'locked_topic_ids' => $lockCache,
					'limit' => count($lockCache)
				]
			);
			$lockCacheBoards = [];
			while ($row = $smcFunc['db']->fetch_assoc($result))
			{
				$lockStatus[$row['id_topic']] = empty($row['locked']);
				$lockCacheBoards[$row['id_topic']] = $row['id_board'];
			}
			$smcFunc['db']->free_result($result);
		}

		// It could just be that *none* were their own topics...
		if (!empty($lockCache))
		{
			// Alternate the locked value.
			$smcFunc['db']->query('', '
				UPDATE {db_prefix}topics
				SET locked = CASE WHEN locked = {int:is_locked} THEN ' . (allowedTo('lock_any') ? '1' : '2') . ' ELSE 0 END
				WHERE id_topic IN ({array_int:locked_topic_ids})',
				[
					'locked_topic_ids' => $lockCache,
					'is_locked' => 0,
				]
			);
		}
	}

	if (!empty($markCache))
	{
		$smcFunc['db']->query('', '
			SELECT id_topic, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:selected_topics})
				AND id_member = {int:current_user}',
			[
				'selected_topics' => $markCache,
				'current_user' => $user_info['id'],
			]
		);
		$logged_topics = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$logged_topics[$row['id_topic']] = $row['unwatched'];
		$smcFunc['db']->free_result($request);

		$markArray = [];
		foreach ($markCache as $topic)
			$markArray[] = [$modSettings['maxMsgID'], $user_info['id'], $topic, (isset($logged_topics[$topic]) ? $logged_topics[$topic] : 0)];

		$smcFunc['db']->insert('replace',
			'{db_prefix}log_topics',
			['id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'],
			$markArray,
			['id_member', 'id_topic']
		);
	}

	foreach ($moveCache as $topic)
	{
		// Didn't actually move anything!
		if (!isset($topic[0]))
			break;

		logAction('move', ['topic' => $topic[0], 'board_from' => $topic[1], 'board_to' => $topic[2]]);
		sendNotifications($topic[0], 'move');
	}
	foreach ($lockCache as $topic)
	{
		logAction($lockStatus[$topic] ? 'lock' : 'unlock', ['topic' => $topic, 'board' => $lockCacheBoards[$topic]]);
		sendNotifications($topic, $lockStatus[$topic] ? 'lock' : 'unlock');
	}
	foreach ($stickyCache as $topic)
	{
		logAction($stickyCacheStatus[$topic] ? 'unsticky' : 'sticky', ['topic' => $topic, 'board' => $stickyCacheBoards[$topic]]);
		sendNotifications($topic, 'sticky');
	}

	updateStats('topic');
	updateStats('message');

	if (!empty($affectedBoards))
		updateLastMessages(array_keys($affectedBoards));

	redirectexit($redirect_url);
}
