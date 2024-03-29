<?php

/**
 * This file currently only contains one function to collect the data needed to
 * show a list of boards for the board index and the message index.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;
use StoryBB\Helper\Parser;
use StoryBB\Model\TopicPrefix;

/**
 * Fetches a list of boards and (optional) categories including
 * statistical information, child boards and moderators.
 * 	- Used by both the board index (main data) and the message index (child
 * boards).
 * 	- Depending on the include_categories setting returns an associative
 * array with categories->boards->child_boards or an associative array
 * with boards->child_boards.
 * @param array $boardIndexOptions An array of boardindex options
 * @return array An array of information for displaying the boardindex
 */

function getBoardIndex($boardIndexOptions)
{
	global $smcFunc, $scripturl, $user_info, $modSettings, $txt;
	global $settings, $options, $context, $sourcedir;

	require_once($sourcedir . '/Subs-Boards.php');

	$url = App::container()->get('urlgenerator');

	// For performance, track the latest post while going through the boards.
	if (!empty($boardIndexOptions['set_latest_post']))
		$latest_post = [
			'timestamp' => 0,
			'ref' => 0,
		];

	// Find all boards and categories, as well as related information.  This will be sorted by the natural order of boards and categories, which we control.
	$result_boards = $smcFunc['db']->query('', '
		SELECT' . ($boardIndexOptions['include_categories'] ? '
			c.id_cat, c.name AS cat_name, c.description AS cat_desc,' : '') . '
			b.id_board, b.name AS board_name, b.slug AS board_slug, b.description,
			CASE WHEN b.redirect != {string:blank_string} THEN 1 ELSE 0 END AS is_redirect,
			b.num_posts, b.num_topics, b.unapproved_posts, b.unapproved_topics, b.id_parent,
			COALESCE(m.poster_time, 0) AS poster_time, COALESCE(mem.member_name, m.poster_name) AS poster_name,
			m.subject, m.id_topic, chars.id_character, COALESCE(chars.character_name, mem.real_name, m.poster_name) AS real_name,
			' . ($user_info['is_guest'] ? ' 1 AS is_read, 0 AS new_from,' : '
			(CASE WHEN COALESCE(lb.id_msg, 0) >= b.id_msg_updated THEN 1 ELSE 0 END) AS is_read, COALESCE(lb.id_msg, -1) + 1 AS new_from,' . ($boardIndexOptions['include_categories'] ? '
			c.can_collapse,' : '')) . '
			COALESCE(mem.id_member, 0) AS id_member, chars.avatar, m.id_msg,  mem.email_address, chars.avatar, COALESCE(am.id_attach, 0) AS member_id_attach, am.filename AS member_filename, am.attachment_type AS member_attach_type
		FROM {db_prefix}boards AS b' . ($boardIndexOptions['include_categories'] ? '
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' : '') . '
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
			LEFT JOIN {db_prefix}characters AS chars ON (m.id_character = chars.id_character)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}attachments AS am ON (am.id_character = m.id_character AND am.attachment_type = 1)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})') . '
		WHERE {query_see_board}' . (empty($boardIndexOptions['countChildPosts']) ? (empty($boardIndexOptions['base_level']) ? '' : '
			AND b.child_level >= {int:child_level}') : '
			AND b.child_level BETWEEN ' . $boardIndexOptions['base_level'] . ' AND ' . ($boardIndexOptions['base_level'] + 1)) . '
			ORDER BY ' . (!empty($boardIndexOptions['include_categories']) ? 'c.cat_order, ' : '') . 'b.child_level, b.board_order',
		[
			'current_member' => $user_info['id'],
			'child_level' => $boardIndexOptions['base_level'],
			'blank_string' => '',
		]
	);

	// Start with an empty array.
	if ($boardIndexOptions['include_categories'])
		$categories = [];
	else
		$this_category = [];
	$boards = [];
	$topics = [];

	// Run through the categories and boards (or only boards)....
	while ($row_board = $smcFunc['db']->fetch_assoc($result_boards))
	{
		// Perhaps we are ignoring this board?
		$ignoreThisBoard = in_array($row_board['id_board'], $user_info['ignoreboards']);
		$row_board['is_read'] = !empty($row_board['is_read']) || $ignoreThisBoard ? '1' : '0';

		// Add parent boards to the $boards list later used to fetch moderators
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'])
			$boards[] = $row_board['id_board'];

		if ($boardIndexOptions['include_categories'])
		{
			// Haven't set this category yet.
			if (empty($categories[$row_board['id_cat']]))
			{
				$categories[$row_board['id_cat']] = [
					'id' => $row_board['id_cat'],
					'name' => $row_board['cat_name'],
					'description' => Parser::parse_bbc($row_board['cat_desc'], false, 'cat' . $row_board['id_cat']),
					'is_collapsed' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1 && !empty($options['collapse_category_' . $row_board['id_cat']]),
					'can_collapse' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1,
					'href' => $scripturl . '#c' . $row_board['id_cat'],
					'boards' => [],
					'new' => false,
					'css_class' => '',
				];
				$categories[$row_board['id_cat']]['link'] = '<a id="c' . $row_board['id_cat'] . '"></a>' . (!$context['user']['is_guest'] ? '<a href="' . $scripturl . '?action=unread;c=' . $row_board['id_cat'] . '" title="' . sprintf($txt['new_posts_in_category'], strip_tags($row_board['cat_name'])) . '">' . $row_board['cat_name'] . '</a>' : $row_board['cat_name']);
			}

			// If this board has new posts in it (and isn't the recycle bin!) then the category is new.
			if (empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $row_board['id_board'])
				$categories[$row_board['id_cat']]['new'] |= empty($row_board['is_read']) && $row_board['poster_name'] != '';

			// Avoid showing category unread link where it only has redirection boards.
			$categories[$row_board['id_cat']]['show_unread'] = !empty($categories[$row_board['id_cat']]['show_unread']) ? 1 : !$row_board['is_redirect'];

			// Let's save some typing.  Climbing the array might be slower, anyhow.
			$this_category = &$categories[$row_board['id_cat']]['boards'];
		}

		// This is a parent board.
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'])
		{
			// Is this a new board, or just another moderator?
			if (!isset($this_category[$row_board['id_board']]))
			{
				// Not a child.
				$isChild = false;

				$this_category[$row_board['id_board']] = [
					'new' => empty($row_board['is_read']),
					'id' => $row_board['id_board'],
					'type' => $row_board['is_redirect'] ? 'redirect' : 'board',
					'name' => $row_board['board_name'],
					'description' => Parser::parse_bbc($row_board['description'], false, 'brd' . $row_board['id_board']),
					'moderators' => [],
					'moderator_groups' => [],
					'link_moderators' => [],
					'link_moderator_groups' => [],
					'children' => [],
					'link_children' => [],
					'children_new' => false,
					'topics' => $row_board['num_topics'],
					'posts' => $row_board['num_posts'],
					'is_redirect' => !empty($row_board['is_redirect']),
					'unapproved_topics' => $row_board['unapproved_topics'],
					'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
					'can_approve_posts' => !empty($user_info['mod_cache']['ap']) && ($user_info['mod_cache']['ap'] == [0] || in_array($row_board['id_board'], $user_info['mod_cache']['ap'])),
					'href' => $url->generate('board', ['board_slug' => $row_board['board_slug']]),
					'link' => '<a href="' . $url->generate('board', ['board_slug' => $row_board['board_slug']]) . '">' . $row_board['board_name'] . '</a>',
					'board_class' => 'off',
					'css_class' => '',
				];

				// We can do some of the figuring-out-what-icon now.
				// For certain types of thing we also set up what the tooltip is.
				if ($this_category[$row_board['id_board']]['is_redirect'])
				{
					$this_category[$row_board['id_board']]['board_class'] = 'redirect';
					$this_category[$row_board['id_board']]['board_tooltip'] = $txt['redirect_board'];
				}
				elseif ($this_category[$row_board['id_board']]['new'] || $context['user']['is_guest'])
				{
					// If we're showing to guests, we want to give them the idea that something interesting is going on!
					$this_category[$row_board['id_board']]['board_class'] = 'on';
					$this_category[$row_board['id_board']]['board_tooltip'] = $txt['new_posts'];
				}
				else
				{
					$this_category[$row_board['id_board']]['board_tooltip'] = $txt['old_posts'];
				}
			}
		}
		// Found a child board.... make sure we've found its parent and the child hasn't been set already.
		elseif (isset($this_category[$row_board['id_parent']]['children']) && !isset($this_category[$row_board['id_parent']]['children'][$row_board['id_board']]))
		{
			// A valid child!
			$isChild = true;

			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']] = [
				'id' => $row_board['id_board'],
				'name' => $row_board['board_name'],
				'description' => $row_board['description'],
				'short_description' => shorten_subject(strip_tags($row_board['description']), 128),
				'new' => empty($row_board['is_read']) && $row_board['poster_name'] != '',
				'topics' => (int) $row_board['num_topics'],
				'posts' => (int) $row_board['num_posts'],
				'is_redirect' => !empty($row_board['is_redirect']),
				'unapproved_topics' => (int) $row_board['unapproved_topics'],
				'unapproved_posts' => (int) $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
				'can_approve_posts' => !empty($user_info['mod_cache']['ap']) && ($user_info['mod_cache']['ap'] == [0] || in_array($row_board['id_board'], $user_info['mod_cache']['ap'])),
				'href' => $url->generate('board', ['board_slug' => $row_board['board_slug']]),
				'link' => '<a href="' . $url->generate('board', ['board_slug' => $row_board['board_slug']]) . '">' . $row_board['board_name'] . '</a>'
			];

			// Counting child board posts is... slow :/.
			if (!empty($boardIndexOptions['countChildPosts']) && !$row_board['is_redirect'])
			{
				$this_category[$row_board['id_parent']]['posts'] += $row_board['num_posts'];
				$this_category[$row_board['id_parent']]['topics'] += $row_board['num_topics'];
			}

			// Does this board contain new boards?
			$this_category[$row_board['id_parent']]['children_new'] |= empty($row_board['is_read']);

			// Update the icon if appropriate
			if ($this_category[$row_board['id_parent']]['children_new'] && $this_category[$row_board['id_parent']]['board_class'] == 'off')
			{
				$this_category[$row_board['id_parent']]['board_class'] = 'on2';
				$this_category[$row_board['id_parent']]['board_tooltip'] = $txt['new_posts'];
			}

			// This is easier to use in many cases for the theme....
			$this_category[$row_board['id_parent']]['link_children'][] = &$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['link'];
		}
		// Child of a child... just add it on...
		elseif (!empty($boardIndexOptions['countChildPosts']))
		{
			if (!isset($parent_map))
				$parent_map = [];

			if (!isset($parent_map[$row_board['id_parent']]))
				foreach ($this_category as $id => $board)
				{
					if (!isset($board['children'][$row_board['id_parent']]))
						continue;

					$parent_map[$row_board['id_parent']] = [&$this_category[$id], &$this_category[$id]['children'][$row_board['id_parent']]];
					$parent_map[$row_board['id_board']] = [&$this_category[$id], &$this_category[$id]['children'][$row_board['id_parent']]];

					break;
				}

			if (isset($parent_map[$row_board['id_parent']]) && !$row_board['is_redirect'])
			{
				$parent_map[$row_board['id_parent']][0]['posts'] += $row_board['num_posts'];
				$parent_map[$row_board['id_parent']][0]['topics'] += $row_board['num_topics'];
				$parent_map[$row_board['id_parent']][1]['posts'] += $row_board['num_posts'];
				$parent_map[$row_board['id_parent']][1]['topics'] += $row_board['num_topics'];

				continue;
			}

			continue;
		}
		// Found a child of a child - skip.
		else
			continue;

		// Prepare the subject, and make sure it's not too long.
		censorText($row_board['subject']);
		$row_board['short_subject'] = shorten_subject($row_board['subject'], 24);
		$this_last_post = [
			'id' => $row_board['id_msg'],
			'time' => $row_board['poster_time'] > 0 ? timeformat($row_board['poster_time']) : $txt['not_applicable'],
			'timestamp' => forum_time(true, $row_board['poster_time']),
			'subject' => $row_board['short_subject'],
			'member' => [
				'id' => $row_board['id_member'],
				'username' => $row_board['poster_name'] != '' ? $row_board['poster_name'] : $txt['not_applicable'],
				'name' => $row_board['real_name'],
				'href' => $row_board['poster_name'] != '' && !empty($row_board['id_member']) ? $scripturl . '?action=profile;u=' . $row_board['id_member'] . (!empty($row_board['id_character']) ? ';area=characters;char=' . $row_board['id_character'] : '') : '',
				'link' => $row_board['poster_name'] != '' ? (!empty($row_board['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_board['id_member'] . (!empty($row_board['id_character']) ? ';area=characters;char=' . $row_board['id_character'] : '') . '">' . $row_board['real_name'] . '</a>' : $row_board['real_name']) : $txt['not_applicable'],
			],
			'start' => 'msg' . $row_board['new_from'],
			'topic' => $row_board['id_topic']
		];

		$topics[] = $row_board['id_topic'];

		$this_last_post['member']['avatar'] = set_avatar_data([
			'avatar' => $row_board['avatar'],
			'email' => $row_board['email_address'],
			'filename' => !empty($row_board['member_filename']) ? $row_board['member_filename'] : '',
			'display_name' => !empty($row_board['id_character']) ? $row_board['real_name'] : '',
			'is_guest' => empty($row_board['id_character']),
		]);

		// Provide the href and link.
		if ($row_board['subject'] != '')
		{
			$this_last_post['href'] = $scripturl . '?topic=' . $row_board['id_topic'] . '.msg' . ($user_info['is_guest'] ? $row_board['id_msg'] : $row_board['new_from']) . (empty($row_board['is_read']) ? ';boardseen' : '') . '#new';
			$this_last_post['link'] = '<a href="' . $this_last_post['href'] . '" title="' . $row_board['subject'] . '">' . $row_board['short_subject'] . '</a>';
		}
		else
		{
			$this_last_post['href'] = '';
			$this_last_post['link'] = $txt['not_applicable'];
		}

		// Set the last post in the parent board.
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'] || ($isChild && !empty($row_board['poster_time']) && $this_category[$row_board['id_parent']]['last_post']['timestamp'] < forum_time(true, $row_board['poster_time'])))
			$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'] = $this_last_post;
		// Just in the child...?
		if ($isChild)
		{
			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['last_post'] = $this_last_post;

			// If there are no posts in this board, it really can't be new...
			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['new'] &= $row_board['poster_name'] != '';
		}
		// No last post for this board?  It's not new then, is it..?
		elseif ($row_board['poster_name'] == '')
			$this_category[$row_board['id_board']]['new'] = false;

		// Determine a global most recent topic.
		if (!empty($boardIndexOptions['set_latest_post']) && !empty($row_board['poster_time']) && $row_board['poster_time'] > $latest_post['timestamp'] && !$ignoreThisBoard)
			$latest_post = [
				'timestamp' => $row_board['poster_time'],
				'ref' => &$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'],
			];
	}
	$smcFunc['db']->free_result($result_boards);

	$prefixes = TopicPrefix::get_prefixes_for_topic_list($topics);
	// echo '<div style="margin-left:100px"><pre>';
	if ($boardIndexOptions['include_categories'])
	{
		// print_r($categories);
		foreach ($categories as $category_id => $this_boards)
		{
			foreach ($this_boards['boards'] as $board_id => $this_board)
			{
				$categories[$category_id]['boards'][$board_id]['last_post']['prefixes'] = [];
				if (!empty($this_board['last_post']['topic']) && isset($prefixes[$this_board['last_post']['topic']]))
				{
					$categories[$category_id]['boards'][$board_id]['last_post']['prefixes'] = $prefixes[$this_board['last_post']['topic']];
				}
			}
		}
	}
	else
	{
		foreach ($this_category as $board_id => $this_board)
		{
			$this_category[$board_id]['last_post']['prefixes'] = [];
			if (!empty($this_board['last_post']['topic']) && isset($prefixes[$this_board['last_post']['topic']]))
			{
				$this_category[$board_id]['last_post']['prefixes'] = $prefixes[$this_board['last_post']['topic']];
			}
		}
		//var_dump($this_category);
	}
	// echo '</pre></div>';

	// Fetch the board's moderators and moderator groups
	$boards = array_unique($boards);
	$moderators = getBoardModerators($boards);
	$groups = getBoardModeratorGroups($boards);
	if ($boardIndexOptions['include_categories'])
	{
		foreach ($categories as $k => $category)
		{
			foreach ($category['boards'] as $j => $board)
			{
				if (!empty($moderators[$board['id']]))
				{
					$categories[$k]['boards'][$j]['moderators'] = $moderators[$board['id']];
					foreach ($moderators[$board['id']] as $moderator)
						$categories[$k]['boards'][$j]['link_moderators'][] = $moderator['link'];
				}
				if (!empty($groups[$board['id']]))
				{
					$categories[$k]['boards'][$j]['moderator_groups'] = $groups[$board['id']];
					foreach ($groups[$board['id']] as $group)
					{
						$categories[$k]['boards'][$j]['link_moderators'][] = $group['link'];
						$categories[$k]['boards'][$j]['link_moderator_groups'][] = $group['link'];
					}
				}
			}
		}
	}
	else
	{
		foreach ($this_category as $k => $board)
		{
			if (!empty($moderators[$board['id']]))
			{
				$this_category[$k]['moderators'] = $moderators[$board['id']];
				foreach ($moderators[$board['id']] as $moderator)
					$this_category[$k]['link_moderators'][] = $moderator['link'];
			}
			if (!empty($groups[$board['id']]))
			{
				$this_category[$k]['moderator_groups'] = $groups[$board['id']];
				foreach ($groups[$board['id']] as $group)
				{
					$this_category[$k]['link_moderators'][] = $group['link'];
					$this_category[$k]['link_moderator_groups'][] = $group['link'];
				}
			}
		}
	}

	if ($boardIndexOptions['include_categories'])
		sortCategories($categories);
	else
		sortBoards($this_category);

	// By now we should know the most recent post...if we wanna know it that is.
	if (!empty($boardIndexOptions['set_latest_post']) && !empty($latest_post['ref']))
		$context['latest_post'] = $latest_post['ref'];

	// I can't remember why but trying to make a ternary to get this all in one line is actually a Very Bad Idea.
	if ($boardIndexOptions['include_categories'])
		call_integration_hook('integrate_getboardtree', [$boardIndexOptions, &$categories]);
	else
		call_integration_hook('integrate_getboardtree', [$boardIndexOptions, &$this_category]);

	return $boardIndexOptions['include_categories'] ? $categories : $this_category;
}
