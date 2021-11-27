<?php

/**
 * Displays the profile topics page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\App;
use StoryBB\Helper\Parser;
use StoryBB\Model\TopicPrefix;

class ShowPosts extends AbstractProfileController
{
	public function display_action()
	{
		global $txt, $user_info, $scripturl, $modSettings;
		global $context, $user_profile, $sourcedir, $smcFunc, $board;

		$url = App::container()->get('urlgenerator');

		// Some initial context.
		$memID = $this->params['u'];
		$context['start'] = (int) $_REQUEST['start'];
		$context['current_member'] = $memID;

		$context['page_title'] = $txt['showPosts'] . ' - ' . $user_profile[$memID]['real_name'];
		$context['is_topics'] = false;

		// Is the load average too high to allow searching just now?
		check_load_avg('show_posts');

		$context['sub_template'] = 'profile_show_posts';

		// If just deleting a message, do it and then redirect back.
		// @deprecated Can't find any place of this being called?
		if (isset($_GET['delete']))
		{
			checkSession('get');

			// We need msg info for logging.
			$request = $smcFunc['db']->query('', '
				SELECT subject, id_member, id_topic, id_board
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				[
					'id_msg' => (int) $_GET['delete'],
				]
			);
			$info = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			// Trying to remove a message that doesn't exist.
			if (empty($info))
				redirectexit('action=profile;u=' . $memID . ';area=posts;start=' . $_GET['start']);

			// We can be lazy, since removeMessage() will check the permissions for us.
			require_once($sourcedir . '/RemoveTopic.php');
			removeMessage((int) $_GET['delete']);

			// Add it to the mod log.
			if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != $user_info['id']))
				logAction('delete', ['topic' => $info[2], 'subject' => $info[0], 'member' => $info[1], 'board' => $info[3]]);

			// Back to... where we are now ;).
			redirectexit('action=profile;u=' . $memID . ';area=posts;start=' . $_GET['start']);
		}

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		{
			$_REQUEST['viewscount'] = '10';
		}

		$request = $smcFunc['db']->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . ($context['user']['is_owner'] ? '' : '
				AND m.approved = {int:is_approved}'),
			[
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			]
		);

		list ($msgCount) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$request = $smcFunc['db']->query('', '
			SELECT MIN(id_msg), MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . ($context['user']['is_owner'] ? '' : '
				AND m.approved = {int:is_approved}'),
			[
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			]
		);
		list ($min_msg_member, $max_msg_member) = $smcFunc['db']->fetch_row($request);
		$smcFunc['db']->free_result($request);

		$range_limit = '';

		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

		$maxIndex = $maxPerPage;

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=posts' . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = $context['start'];
		$reverse = $_REQUEST['start'] > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $maxPerPage + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : $maxPerPage;
			$start = $msgCount < $context['start'] + $maxPerPage + 1 || $msgCount < $context['start'] + $maxPerPage ? 0 : $msgCount - $context['start'] - $maxPerPage;
		}

		// Guess the range of messages to be shown.
		if ($msgCount > 1000)
		{
			$margin = floor(($max_msg_member - $min_msg_member) * (($start + $maxPerPage) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
		}

		// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
		$looped = false;
		while (true)
		{
			$request = $smcFunc['db']->query('', '
				SELECT
					b.id_board, b.name AS bname, b.slug AS board_slug, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . ($context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT {int:start}, {int:max}',
				[
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
					'start' => $start,
					'max' => $maxIndex,
				]
			);

			// Make sure we quit this loop.
			if ($smcFunc['db']->num_rows($request) === $maxIndex || $looped || $range_limit === '')
				break;
			$looped = true;
			$range_limit = '';
		}

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = [];
		$board_ids = ['own' => [], 'any' => []];
		$topic_ids = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			// Censor....
			censorText($row['body']);
			censorText($row['subject']);

			$topic_ids[$row['id_topic']] = $row['id_topic'];

			// Do the code.
			$row['body'] = Parser::parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

			// And the array...
			$context['posts'][$counter += $reverse ? -1 : 1] = [
				'body' => $row['body'],
				'counter' => $counter,
				'category' => [
					'name' => $row['cname'],
					'id' => $row['id_cat']
				],
				'board' => [
					'name' => $row['bname'],
					'id' => $row['id_board'],
					'link' => $url->generate('board', ['board_slug' => $row['board_slug']]),
				],
				'topic' => $row['id_topic'],
				'subject' => $row['subject'],
				'start' => 'msg' . $row['id_msg'],
				'time' => timeformat($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id' => $row['id_msg'],
				'can_reply' => false,
				'can_mark_notify' => !$context['user']['is_guest'],
				'can_delete' => false,
				'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
				'approved' => $row['approved'],
				'css_class' => $row['approved'] ? 'windowbg' : 'approvebg',
				'prefixes' => [],
			];

			if ($user_info['id'] == $row['id_member_started'])
				$board_ids['own'][$row['id_board']][] = $counter;
			$board_ids['any'][$row['id_board']][] = $counter;
		}
		$smcFunc['db']->free_result($request);

		if (!empty($topic_ids))
		{
			$prefixes = TopicPrefix::get_prefixes_for_topic_list($topic_ids);
			foreach ($context['posts'] as $key => $post)
			{
				if (isset($prefixes[$post['topic']]))
				{
					$context['posts'][$key]['prefixes'] = $prefixes[$post['topic']];
				}
			}
		}

		// All posts were retrieved in reverse order, get them right again.
		if ($reverse)
			$context['posts'] = array_reverse($context['posts'], true);

		// These are all the permissions that are different from board to board..
		$permissions = [
			'own' => [
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			],
			'any' => [
				'post_reply_any' => 'can_reply',
				'delete_any' => 'can_delete',
			]
		];

		// For every permission in the own/any lists...
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// Get the boards they can do this on...
				$boards = boardsAllowedTo($permission);

				// Hmm, they can do it on all boards, can they?
				if (!empty($boards) && $boards[0] == 0)
					$boards = array_keys($board_ids[$type]);

				// Now go through each board they can do the permission on.
				foreach ($boards as $board_id)
				{
					// There aren't any posts displayed from this board.
					if (!isset($board_ids[$type][$board_id]))
						continue;

					// Set the permission to true ;).
					foreach ($board_ids[$type][$board_id] as $counter)
						$context['posts'][$counter][$allowed] = true;
				}
			}
		}

		// Clean up after posts that cannot be deleted and quoted.
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
		foreach ($context['posts'] as $counter => $dummy)
		{
			$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
			$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
		}
	}
}
