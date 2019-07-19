<?php

/**
 * Supplementary functions used when building the message index (lists of topics)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Generates the query to determine the list of available boards for a user
 * Executes the query and returns the list
 *
 * @param array $boardListOptions An array of options for the board list
 * @return array An array of board info
 */
function getBoardList($boardListOptions = [])
{
	global $smcFunc, $sourcedir;

	if (isset($boardListOptions['excluded_boards']) && isset($boardListOptions['included_boards']))
		trigger_error('getBoardList(): Setting both excluded_boards and included_boards is not allowed.', E_USER_ERROR);

	$where = [];
	$where_parameters = [];
	if (isset($boardListOptions['excluded_boards']))
	{
		$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
		$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
	}

	if (isset($boardListOptions['included_boards']))
	{
		$where[] = 'b.id_board IN ({array_int:included_boards})';
		$where_parameters['included_boards'] = $boardListOptions['included_boards'];
	}

	if (!empty($boardListOptions['ignore_boards']))
		$where[] = '{query_wanna_see_board}';

	elseif (!empty($boardListOptions['use_permissions']))
		$where[] = '{query_see_board}';

	if (!empty($boardListOptions['not_redirection']))
	{
		$where[] = 'b.redirect = {string:blank_redirect}';
		$where_parameters['blank_redirect'] = '';
	}

	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)),
		$where_parameters
	);

	$return_value = [];
	if ($smcFunc['db_num_rows']($request) !== 0)
	{
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($return_value[$row['id_cat']]))
				$return_value[$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => [],
				);

			$return_value[$row['id_cat']]['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			);
		}
	}
	$smcFunc['db_free_result']($request);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($return_value);

	return $return_value;
}

/**
 * Make a string linking to all the child boards for a given board.
 *
 * @param array $board A board array
 * @return SafeString The links to all the children of this board, as a single string
 */
function child_boards($board)
{
	global $txt;

	// Sort the links into an array with new boards bold so it can be imploded.
	$children = [];
	/* Each child in each board's children has:
			id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
	foreach ($board['children'] as $child)
	{
		if (!$child['is_redirect'])
			$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a  ' . ($child['new'] ? 'class="new_posts" ' : '') . 'href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
		else
			$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

		// Has it posts awaiting approval?
		if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
			$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

		$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
	}
	
	return new \LightnCandy\Safestring(implode(', ', $children));
}
