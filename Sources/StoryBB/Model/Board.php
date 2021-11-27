<?php

/**
 * This class handles boards.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

use StoryBB\Dependency\Database;

/**
 * This class handles boards.
 */
class Board
{
	use Database;

	public function slug_exists(string $slug, ?int $ignore): bool
	{
		$db = $this->db();

		$request = $db->query('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE slug LIKE {string:slug}
				AND id_board != {int:ignore}',
			[
				'slug' => $slug,
				'ignore' => $ignore ?: 0,
			]
		);
		$row = $db->fetch_row($request);
		$db->free_result($request);

		return !empty($row);
	}
}
