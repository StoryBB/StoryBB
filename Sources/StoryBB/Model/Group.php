<?php

/**
 * This class handles the database processing for a membergroup.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

use StoryBB\Phrase;
use StoryBB\Dependency\Database;

/**
 * This class handles the database processing for a membergroup.
 */
class Group
{
	use Database;

	const GUEST = -1;
	const UNGROUPED_ACCOUNT = 0;
	const ADMINISTRATOR = 1;
	const BOARD_MODERATOR = 3;

	const TYPE_PRIVATE = 0;
	const TYPE_PROTECTED = 1;
	const TYPE_REQUESTABLE = 2;
	const TYPE_JOINABLE = 3;

	const VISIBILITY_VISIBLE = 0;
	const VISIBILITY_VISBLE_EXCEPT_GROUP_KEY = 1;
	const VISIBILITY_INVISIBLE = 2;

	/**
	 * Identifies whether a given group is a character-based or account based.
	 *
	 * @param int $group The group ID
	 * @return bool True if the group is a character-based group
	 */
	public static function is_character_group(int $group): bool
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT is_character
			FROM {db_prefix}membergroups
			WHERE id_group = {int:group}
			LIMIT {int:limit}',
			[
				'group' => $group,
				'limit' => 1,
			]
		);
		$is_character_group = false;
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$is_character_group = !empty($row['is_character']);
		}
		$smcFunc['db']->free_result($request);

		return $is_character_group;
	}

	public function get_all_names(): array
	{
		$db = $this->db();

		$groups = [
			-1 => new Phrase('General:guest_title'),
			0 => new Phrase('Reports:full_member'),
		];
		// Get all the possible membergroups!
		$request = $db->query('', '
			SELECT id_group, group_name, online_color
			FROM {db_prefix}membergroups',
			[]
		);
		while ($row = $db->fetch_assoc($request))
		{
			$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
		}
		$db->free_result($request);

		return $groups;
	}
}
