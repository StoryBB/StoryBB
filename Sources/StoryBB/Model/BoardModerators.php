<?php

/**
 * This class handles board moderators.
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
 * This class handles board moderators.
 */
class BoardModerators
{
	use Database;

	/**
	 * Return all the board moderators for all boards.
	 *
	 * @return array An array by board id of all board moderators.
	 */
	public function get_all(): array
	{
		$db = $this->db();

		$moderators = [];

		$request = $db->query('', '
			SELECT mods.id_board, mods.id_member, mem.real_name
			FROM {db_prefix}moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)',
			[]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$moderators[$row['id_board']][$row['id_member']] = $row['real_name'];
		}
		$db->free_result($request);

		return $moderators;
	}
}
