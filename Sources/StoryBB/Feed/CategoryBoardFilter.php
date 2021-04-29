<?php

/**
 * Optimisations for board/category filtering.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Feed;

trait CategoryBoardFilter
{
	protected $optimize_msg = '';
	protected $query_this_board = '';

	protected function get_category_filter(): void
	{
		global $smcFunc, $board, $board_info, $scripturl, $modSettings;

		// Set soem defaults.
		$this->query_this_board = 1;
		$this->optimize_msg = [
			'highest' => 'm.id_msg <= b.id_last_msg',
		];

		$csv_handler = function(string $csv): array {
			$array = explode(',', $csv);
			return array_filter(array_map('intval', $array));
		};

		if (!empty($_REQUEST['c']) && empty($board))
		{
			$categories = $csv_handler($_REQUEST['c']);

			if (count($categories) == 1)
			{
				$request = $smcFunc['db']->query('', '
					SELECT name
					FROM {db_prefix}categories
					WHERE id_cat = {int:current_category}',
					[
						'current_category' => $categories[0],
					]
				);
				[$category_title] = $smcFunc['db']->fetch_row($request);
				$smcFunc['db']->free_result($request);

				$this->feed['title'] = $category_title . ' - ' . $this->feed['title'];
			}

			$categories[] = 0; // Make sure the list isn't empty for the next part.

			$request = $smcFunc['db']->query('', '
				SELECT b.id_board, b.num_posts
				FROM {db_prefix}boards AS b
				WHERE b.id_cat IN ({array_int:current_category_list})
					AND {query_see_board}',
				[
					'current_category_list' => $categories,
				]
			);
			$total_cat_posts = 0;
			$boards = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				$boards[] = $row['id_board'];
				$total_cat_posts += $row['num_posts'];
			}
			$smcFunc['db']->free_result($request);

			if (!empty($boards))
			{
				$this->query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';
			}

			// Try to limit the number of messages we look through.
			if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
			{
				$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 400 - $_GET['limit'] * 5);
			}
		}
		elseif (!empty($_REQUEST['boards']))
		{
			$boards = $csv_handler($_REQUEST['boards']);
			$boards[] = 0; // Make sure the list isn't empty for the next part.

			$request = $smcFunc['db']->query('', '
				SELECT b.id_board, b.num_posts, b.name
				FROM {db_prefix}boards AS b
				WHERE b.id_board IN ({array_int:board_list})
					AND {query_see_board}
				LIMIT {int:limit}',
				[
					'board_list' => $_REQUEST['boards'],
					'limit' => count($_REQUEST['boards']),
				]
			);

			// Either the board specified doesn't exist or you have no access.
			$num_boards = $smcFunc['db']->num_rows($request);
			if ($num_boards == 0)
			{
				fatal_lang_error('no_board');
			}

			$total_posts = 0;
			$boards = [];
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if ($num_boards == 1)
				{
					$this->feed['title'] = $row['name'] . ' - ' . $this->feed['title'];
				}

				$boards[] = $row['id_board'];
				$total_posts += $row['num_posts'];
			}
			$smcFunc['db']->free_result($request);

			if (!empty($boards))
			{
				$this->query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';
			}

			// The more boards, the more we're going to look through...
			if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			{
				$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 500 - $_GET['limit'] * 5);
			}
		}
		elseif (!empty($board))
		{
			$request = $smcFunc['db']->query('', '
				SELECT num_posts
				FROM {db_prefix}boards
				WHERE id_board = {int:current_board}
				LIMIT 1',
				[
					'current_board' => $board,
				]
			);
			list ($total_posts) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			$this->feed['title'] = $board_info['name'] . ' - ' . $this->feed['title'];
			$this->feed['source'] = $scripturl . '?board=' . $board . '.0';

			$this->query_this_board = 'b.id_board = ' . $board;

			// Try to look through just a few messages, if at all possible.
			if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
			{
				$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $_GET['limit'] * 5);
			}
		}
		else
		{
			$this->query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != ' . $modSettings['recycle_board'] : '');
			$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $_GET['limit'] * 5);
		}
	}

	public function get_vars(): array
	{
		return ['c', 'boards', 'board'];
	}
}
