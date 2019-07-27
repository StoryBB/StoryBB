<?php

/**
 * This class handles policies in the system.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
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
	public static function get_policies_for_registration(): array
	{
		global $smcFunc, $user_info, $language;

		$policies = [];
		$final_policies = [];
		$versions_in_order = [$user_info['language'], $language, 'en-us'];

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

	/**
	 * List of all the policies, language versions etc.
	 *
	 * @return array An array of all policies in the system, subdivided by policy type and language.
	 */
	public static function get_policy_list(): array
	{
		global $smcFunc;

		$policies = [];

		// First, policy types, forming the backbone of what gets returned.
		$request = $smcFunc['db_query']('', '
			SELECT pt.id_policy_type, pt.policy_type, pt.require_acceptance, pt.show_footer, pt.show_reg, pt.show_help
			FROM {db_prefix}policy_types AS pt
			ORDER BY pt.id_policy_type');
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['no_language'] = [];
			$policies[$row['id_policy_type']] = $row;
		}
		$smcFunc['db_free_result']($request);

		// Next up, fetch the different versions that we have.
		$request = $smcFunc['db_query']('', '
			SELECT p.id_policy, p.policy_type, p.language, p.title, p.description, p.last_revision, pr.last_change
			FROM {db_prefix}policy AS p
				INNER JOIN {db_prefix}policy_revision AS pr ON (p.last_revision = pr.id_revision)
			ORDER BY p.id_policy');
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($policies[$row['policy_type']]))
			{
				continue; // We don't know this policy type?
			}
			$row['last_change_format'] = timeformat($row['last_change']);
			$policies[$row['policy_type']]['versions'][$row['language']] = $row;
		}
		$smcFunc['db_free_result']($request);

		// Now identify any ones that don't have a specific language attached.
		$languages = array_keys(getLanguages());
		foreach ($policies as $policy_type => $policy_list)
		{
			foreach ($languages as $language)
			{
				if (!isset($policy_list['versions'][$language]))
				{
					$policies[$policy_type]['no_language'][] = $language;
				}
			}
		}

		// And we're done.
		return $policies;
	}

	/**
	 * Get a specific policy revision.
	 *
	 * @param int $revision_id The revision ID to fetch
	 * @return array Poilcy with that revision, empty array if not found.
	 */
	public static function get_policy_revision(int $revision_id): array
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT id_policy, last_change, short_revision_note, revision_text, edit_id_member, edit_member_name
			FROM {db_prefix}policy_revision
			WHERE id_revision = {int:revision_id}',
			[
				'revision_id' => $revision_id,
			]
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row))
		{
			return [];
		}
		return $row;
	}

	/**
	 * Update the general policy details.
	 *
	 * @param int $policy_type The policy type to update
	 * @param string $language The language to update for the same policy
	 * @param array $details Fields to be updated in the policy
	 */
	public static function update_policy(int $policy_type, string $language, array $details)
	{
		global $smcFunc, $context;

		// First update the stuff at policy-type level.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}policy_types
			SET show_reg = {int:show_reg},
				show_help = {int:show_help},
				show_footer = {int:show_footer}
			WHERE id_policy_type = {int:policy_type}',
			[
				'policy_type' => $policy_type,
				'show_reg' => !empty($details['show_reg']) ? 1 : 0,
				'show_help' => !empty($details['show_help']) ? 1 : 0,
				'show_footer' => !empty($details['show_footer']) ? 1 : 0,
			]
		);

		// Now the stuff for this policy version.
		$clauses = [];
		if (isset($details['title']))
		{
			$clauses[] = 'title = {string:title}';
		}
		if (isset($details['description']))
		{
			$clauses[] = 'description = {string:description}';
		}
		if (!empty($clauses))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}policy
				SET ' . implode(', ', $clauses) . '
				WHERE policy_type = {int:policy_type}
					AND language = {string:language}',
				array_merge($details, [
					'policy_type' => $policy_type,
					'language' => $language,
				])
			);
			if ($smcFunc['db']->affected_rows() == 0)
			{
				// Hmm, we didn't change a row? Guess we're adding a new language we didn't already have.
				$smcFunc['db_insert']('insert',
					'{db_prefix}policy',
					['policy_type' => 'int', 'language' => 'string', 'title' => 'string', 'description' => '', 'last_revision' => 'int'],
					[$policy_type, $language, !empty($details['title']) ? $details['title'] : '', !empty($details['description']) ? $details['description'] : '', 0],
					['id_policy']
				);
			}

			// And we're updating the policy text itself.
			if (!empty($details['policy_text']))
			{
				// First we need to know which policy it is.
				$request = $smcFunc['db_query']('', '
					SELECT id_policy
					FROM {db_prefix}policy
					WHERE policy_type = {int:policy_type}
						AND language = {string:language}',
					[
						'policy_type' => $policy_type,
						'language' => $language,
					]
				);
				$row = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);
				if (empty($row))
				{
					return;
				}

				if (empty($details['edit_id_member']))
				{
					$details['edit_id_member'] = $context['user']['id'];
				}
				if (empty($details['edit_member_name']))
				{
					$details['edit_member_name'] = $context['user']['name'];
				}
				if (!isset($details['policy_edit']))
				{
					$details['policy_edit'] = '';
				}

				// Now insert the new revision.
				$revision_id = $smcFunc['db_insert']('insert',
					'{db_prefix}policy_revision',
					[
						'id_policy' => 'int', 'last_change' => 'int', 'short_revision_note' => 'string', 'revision_text' => 'string',
						'edit_id_member' => 'int', 'edit_member_name' => 'string'
					],
					[
						$row['id_policy'], time(), $details['policy_edit'], $details['policy_text'],
						$details['edit_id_member'], $details['edit_member_name']
					],
					['id_revision'],
					1
				);
				// Now update the policy table to point to the new entry.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}policy
					SET last_revision = {int:revision_id}
					WHERE id_policy = {int:id_policy}',
					[
						'id_policy' => $row['id_policy'],
						'revision_id' => $revision_id,
					]
				);

				// And make sure to clear our cache of it.
				cache_put_data('footer_links', null);
			}
		}
	}

	/**
	 * Work out which policy/ies the current user has not agreed the latest version of.
	 *
	 * @return array List of policies
	 */
	public static function get_unagreed_policies()
	{
		global $smcFunc, $user_info, $language;

		$policies = [];
		$final_policies = [];
		$versions_in_order = [$user_info['language'], $language, 'en-us'];

		// Fetch all the policies.
		$request = $smcFunc['db_query']('', '
			SELECT p.id_policy, pt.policy_type, p.language, p.title, p.last_revision, MAX(pa.id_revision) AS last_acceptance
			FROM {db_prefix}policy_types AS pt
				INNER JOIN {db_prefix}policy AS p ON (p.policy_type = pt.id_policy_type)
				LEFT JOIN {db_prefix}policy_acceptance AS pa ON (pa.id_policy = p.id_policy AND pa.id_member = {int:member})
			WHERE pt.show_reg = 1
				AND p.language IN ({array_string:language})
			GROUP BY p.id_policy, p.language, pt.policy_type, p.title, p.last_revision
			ORDER BY p.id_policy, p.language
			',
			[
				'member' => $user_info['id'],
				'language' => $versions_in_order,
			]
		);
		while($row = $smcFunc['db_fetch_assoc']($request))
		{
			$policies[$row['policy_type']][$row['language']] = $row;
		}
		$smcFunc['db_free_result']($request);

		// Having fetched all possible policies for this user, let's figure out whether they have agreed to anything.
		foreach ($policies as $policy_type => $languages)
		{
			foreach ($languages as $language_name => $language_version_details)
			{
				if ($language_version_details['last_acceptance'] >= $language_version_details['last_revision'])
				{
					// We have an acceptance for this policy type, so we don't need to worry about it any more.
					unset ($policies[$policy_type]);
					continue 2;
				}
			}
		}

		// Let's now sift out whatever might be left to get the things we care about.
		foreach ($policies as $policy_type => $languages)
		{
			foreach ($versions_in_order as $this_language)
			{
				if (isset($languages[$this_language]))
				{
					$revisions[] = $languages[$this_language]['last_revision'];
					$final_policies[$policy_type] = [
						'title' => $languages[$this_language]['title'],
						'revision_note' => '',
					];
					break;
				}
			}
		}

		if (!empty($revisions))
		{
			$request = $smcFunc['db_query']('', '
				SELECT pt.policy_type, pr.short_revision_note
				FROM {db_prefix}policy_revision AS pr
					INNER JOIN {db_prefix}policy AS p ON (pr.id_policy = p.id_policy)
					INNER JOIN {db_prefix}policy_types AS pt ON (p.policy_type = pt.id_policy_type)
				WHERE pr.id_revision IN ({array_int:revisions})',
				[
					'revisions' => $revisions,
				]
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$final_policies[$row['policy_type']]['revision_note'] = $row['short_revision_note'];
			}
			$smcFunc['db_free_result']($request);
		}

		return $final_policies;
	}

	/**
	 * Mark the current user as having agreed to a policy.
	 *
	 * @param array $agreed List of policy types agreed to
	 * @param string $user_language The user's language to match policies against
	 * @param int $user_id The user who is agreeing a policy
	 */
	public static function agree_to_policy(array $agreed, string $user_language, int $user_id)
	{
		global $smcFunc, $user_info;

		if (empty($agreed))
		{
			return;
		}

		$policies = [];
		$final_policies = [];
		$versions_in_order = [$user_language, 'en-us'];

		// Fetch all the policies.
		$request = $smcFunc['db_query']('', '
			SELECT pt.policy_type, p.id_policy, p.language, p.last_revision
			FROM {db_prefix}policy_types AS pt
				INNER JOIN {db_prefix}policy AS p ON (p.policy_type = pt.id_policy_type)
			WHERE pt.show_reg = 1
				AND p.language IN ({array_string:language})
				AND pt.policy_type IN ({array_string:agreed})
			ORDER BY p.id_policy, p.language
			',
			[
				'language' => $versions_in_order,
				'agreed' => $agreed,
			]
		);
		while($row = $smcFunc['db_fetch_assoc']($request))
		{
			$policies[$row['policy_type']][$row['language']] = $row;
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

		// Now make that into a set of rows to insert into the acceptance table.
		$rows = [];
		foreach ($final_policies as $policy_type => $policy)
		{
			$rows[] = [$policy['id_policy'], $user_id, $policy['last_revision'], time()];
		}
		if (!empty($rows))
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}policy_acceptance',
				['id_policy' => 'int', 'id_member' => 'int', 'id_revision' => 'int', 'acceptance_time' => 'int'],
				$rows,
				['id_policy', 'id_member', 'id_revision']
			);
		}
	}

	/**
	 * Reset all users' policy acceptance state, except the admin(s).
	 *
	 * @param array $exclude User IDs to exclude from resetting policy acceptance state
	 */
	public static function reset_acceptance(array $exclude = [])
	{
		global $smcFunc;

		// Make sure there is something in the array even if it is harmless.
		$exclude[] = 0;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET policy_acceptance = {int:previouslyaccepted}
			WHERE policy_acceptance = {int:currentlyaccepted}
				AND id_member NOT IN ({array_int:exclude})',
			[
				'previouslyaccepted' => self::POLICY_PREVIOUSLYACCEPTED,
				'currentlyaccepted' => self::POLICY_CURRENTLYACCEPTED,
				'exclude' => $exclude,
			]
		);
	}

	/**
	 * Get which links should be in the footer.
	 *
	 * @return array A list of links for the footer.
	 */
	public static function get_footer_policies()
	{
		global $smcFunc, $language, $user_info, $scripturl;

		if (($footer_links = cache_get_data('footer_links', 300)) === null)
		{
			$footer_links = [];

			$request = $smcFunc['db_query']('', '
				SELECT p.id_policy, pt.policy_type, p.language, p.title
				FROM {db_prefix}policy_types AS pt
					INNER JOIN {db_prefix}policy AS p ON (p.policy_type = pt.id_policy_type)
				WHERE pt.show_footer = 1');
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$footer_links[$row['policy_type']][$row['language']] = [
					'link' => $scripturl . '?action=help;sa=' . $row['policy_type'],
					'title' => $row['title'],
				];
			}
			$smcFunc['db_free_result']($request);

			cache_put_data('footer_links', $footer_links, 300);
		}

		$versions = [$user_info['language'], $language, 'en-us'];

		foreach ($footer_links as $policy_type => $languages)
		{
			foreach ($versions as $version)
			{
				if (isset($languages[$version]))
				{
					$lang_footer_links[$policy_type] = $languages[$version];
					break;
				}
			}
		}

		return $lang_footer_links;
	}
}
