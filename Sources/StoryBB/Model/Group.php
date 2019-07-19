<?php

/**
 * This class handles the database processing for a membergroup.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles the database processing for a membergroup.
 */
class Group
{
	/**
	 * Identifies whether a given group is a character-based or account based.
	 *
	 * @param int $group The group ID
	 * @return bool True if the group is a character-based group
	 */
	public static function is_character_group(int $group): bool
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT is_character
			FROM {db_prefix}membergroups
			WHERE id_group = {int:group}
			LIMIT {int:limit}',
			array(
				'group' => $group,
				'limit' => 1,
			)
		);
		$is_character_group = false;
		if ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$is_character_group = !empty($row['is_character']);
		}
		$smcFunc['db_free_result']($request);

		return $is_character_group;
	}
}
