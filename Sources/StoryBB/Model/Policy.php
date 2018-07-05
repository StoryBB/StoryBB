<?php

/**
 * This class handles policies in the system.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles attachments.
 */
class Policy
{
	const POLICY_NOTACCEPTED = 0;
	const POLICY_PREVIOUSLYACCEPTED = 1;
	const POLICY_CURRENTLYACCEPTED = 2;

	/**
	 * Get all the policies needed for registration.
	 *
	 * @return array Return an array of policies required for registration
	 */
	public static function get_policies_for_registration()
	{
		global $smcFunc, $user_info, $language;

		$policies = [];
		$final_policies = [];
		$versions_in_order = [$user_info['language'], $language, 'english'];

		// Fetch all the policies.
		$request = $smcFunc['db_query']('', '
			SELECT pt.policy_type, p.language, p.title
			FROM {db_prefix}policy_types AS pt
				INNER JOIN {db_prefix}policy AS p ON (p.policy_type = pt.id_policy_type)
			WHERE pt.show_reg = 1
				AND p.language IN ({array_string:language})
			ORDER BY p.id_policy, p.language
			',
			[
				'language' => $versions_in_order,
			]
		);
		while($row = $smcFunc['db_fetch_assoc']($request))
		{
			$policies[$row['policy_type']][$row['language']] = $row['title'];
		}
		$smcFunc['db_free_result']($request);

		// Sift out which ones we care about for this user.
		foreach ($policies as $policy_type => $policy_languages)
		{
			foreach ($versions_in_order as $version)
			{
				if (isset($policy_languages[$version]))
				{
					$final_policies[$policy_type] = $policy_languages[$version];
					break;
				}
			}
		}

		return $final_policies;
	}
}
