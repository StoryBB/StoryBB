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
 * This class handles groups that are designated board moderators.
 */
class BoardGroupModerators
{
	use Database;

	/**
	 * Return all the board moderators (which are groups) for all boards.
	 *
	 * @return array An array by board id of all board group moderators.
	 */
	public function get_all(): array
	{
		$db = $this->db();

		$moderators = [];

		$request = $db->query('', '
			SELECT modgs.id_board, modgs.id_group, memg.group_name
			FROM {db_prefix}moderator_groups AS modgs
				INNER JOIN {db_prefix}membergroups AS memg ON (memg.id_group = modgs.id_group)',
			[
			]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$moderators[$row['id_board']][$row['id_group']] = $row['group_name'];
		}
		$db->free_result($request);

		return $moderators;
	}
}
