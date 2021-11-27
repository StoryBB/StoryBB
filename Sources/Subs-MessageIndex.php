<?php

/**
 * Supplementary functions used when building the message index (lists of topics)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\App;

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

	$url = App::container()->get('urlgenerator');

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

	$request = $smcFunc['db']->query('order_by_board_order', '
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.slug AS board_slug, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)),
		$where_parameters
	);

	$return_value = [];
	if ($smcFunc['db']->num_rows($request) !== 0)
	{
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			if (!isset($return_value[$row['id_cat']]))
				$return_value[$row['id_cat']] = [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => [],
				];

			$return_value[$row['id_cat']]['boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'url' => $url->generate('board', ['board_slug' => $row['board_slug']]),
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			];
		}
	}
	$smcFunc['db']->free_result($request);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($return_value);

	return $return_value;
}
