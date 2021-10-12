<?php

/**
 * This class handles permission profiles.
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
 * This class handles permission profiles.
 */
class PermissionProfile
{
	use Database;

	/**
	 * Returns all the permission profiles' names and high level details.
	 *
	 * @return array An array by profile id of permission profiles.
	 */
	public function get_all(): array
	{
		$profiles = [];
		$db = $this->db();

		$request = $db->query('', '
			SELECT id_profile, profile_name
			FROM {db_prefix}permission_profiles
			ORDER BY id_profile',
			[]
		);

		while ($row = $db->fetch_assoc($request))
		{
			// Format the label nicely.
			$name = $row['profile_name'];
			if (in_array($row['profile_name'], ['default', 'no_polls', 'reply_only', 'read_only']))
			{
				$name = (string) new Phrase('ManagePermissions:permissions_profile_' . $row['profile_name']);
			}

			$profiles[$row['id_profile']] = [
				'id' => $row['id_profile'],
				'name' => $name,
				'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
				'unformatted_name' => $row['profile_name'],
			];
		}
		$db->free_result($request);

		return $profiles;
	}
}
