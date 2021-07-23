<?php

/**
 * Supporting features for integrations.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Hook\Integratable;

use InvalidArgumentException;

trait BoardDetails
{
	public function is_character_board(int $board): bool
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT in_character
			FROM {db_prefix}boards
			WHERE id_board = {int:board}',
			[
				'board' => $board,
			]
		);
		$row = $smcFunc['db']->fetch_assoc($request);
		if (empty($row))
		{
			throw new InvalidArgumentException('Invalid board id');
		}
		$smcFunc['db']->free_result($request);

		return !empty($row['in_character']);
	}
}
