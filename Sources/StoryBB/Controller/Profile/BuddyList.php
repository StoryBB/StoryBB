<?php

/**
 * Displays the buddy list page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Autocomplete;

class BuddyList extends AbstractProfileController
{
	public function display_action()
	{
		global $txt, $scripturl, $settings;
		global $context, $user_profile, $memberContext, $smcFunc;

		$memID = $this->params['u'];

		$context['sub_template'] = 'profile_buddy';

		// For making changes!
		$buddiesArray = explode(',', $user_profile[$memID]['buddy_list']);
		foreach ($buddiesArray as $k => $dummy)
			if ($dummy == '')
				unset($buddiesArray[$k]);

		$saved = false;

		// Removing a buddy?
		if (isset($_GET['remove']))
		{
			checkSession('get');

			call_integration_hook('integrate_remove_buddy', [$memID]);

			// Heh, I'm lazy, do it the easy way...
			foreach ($buddiesArray as $key => $buddy)
				if ($buddy == (int) $_GET['remove'])
				{
					unset($buddiesArray[$key]);
					$saved = true;
				}

			// Make the changes.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, ['buddy_list' => $user_profile[$memID]['buddy_list']]);

			if ($saved)
			{
				session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
			}
			else
			{
				session_flash('error', $txt['could_not_remove_person']);
			}

			// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
			redirectexit('action=profile;area=buddies;u=' . $memID);
		}

		// Get all the users "buddies"...
		$buddies = [];

		// Gotta load the custom profile fields names.
		$request = $smcFunc['db']->query('', '
			SELECT col_name, field_name, field_desc, field_type, bbc, enclose
			FROM {db_prefix}custom_fields
			WHERE active = {int:active}
				AND private < {int:private_level}
				AND in_character = {int:not_in_character}',
			[
				'active' => 1,
				'private_level' => 2,
				'not_in_character' => 0,
			]
		);

		$context['custom_pf'] = [];
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
			if (!isset($disabled_fields[$row['col_name']]))
				$context['custom_pf'][$row['col_name']] = [
					'label' => $row['field_name'],
					'type' => $row['field_type'],
					'bbc' => !empty($row['bbc']),
					'enclose' => $row['enclose'],
				];

		$smcFunc['db']->free_result($request);

		if (!empty($buddiesArray))
		{
			$result = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:buddy_list})
				ORDER BY real_name
				LIMIT {int:buddy_list_count}',
				[
					'buddy_list' => $buddiesArray,
					'buddy_list_count' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($result))
				$buddies[] = $row['id_member'];
			$smcFunc['db']->free_result($result);
		}

		$context['buddy_count'] = count($buddies);

		// Load all the members up.
		loadMemberData($buddies, false, 'profile');

		// Setup the context for each buddy.
		$context['buddies'] = [];
		foreach ($buddies as $buddy)
		{
			loadMemberContext($buddy, true);
			$context['buddies'][$buddy] = $memberContext[$buddy];

			foreach ($context['buddies'][$buddy]['characters'] as $character)
			{
				if ($character['is_main'])
				{
					$context['buddies'][$buddy]['options'] = [];
					$fields = [];
					foreach ($character['custom_fields'] as $placement => $fields)
					{
						foreach ($fields as $field)
						{
							$fields[$field['col_name']] = $field['value'];
						}
					}

					foreach (array_keys($context['custom_pf']) as $field)
					{
						$context['buddies'][$buddy]['options'][] = $fields[$field] ?? '';
					}
					break;
				}
			}
		}

		$context['columns_colspan'] = count($context['custom_pf']) + 3;

		Autocomplete::init('member', '#new_buddy');

		call_integration_hook('integrate_view_buddies', [$memID]);
	}

	public function post_action()
	{
		global $user_profile, $txt;

		$memID = $this->params['u'];

		// For making changes!
		$buddiesArray = explode(',', $user_profile[$memID]['buddy_list']);
		foreach ($buddiesArray as $key => $value)
			if (empty($value))
				unset($buddiesArray[$k]);

		if (isset($_POST['new_buddy']))
		{
			// Prepare the string for extraction...
			$new_buddy = !empty($_POST['new_buddy']) ? (array) $_POST['new_buddy'] : [];
			foreach ($new_buddy as $k => $v)
			{
				$v = (int) $v;
				if ($v <= 0)
					unset ($new_buddy[$k]);
				else
					$new_buddy[$k] = $v;
			}
			$new_buddy = array_diff($new_buddy, $buddiesArray);

			call_integration_hook('integrate_add_buddies', [$memID, &$new_buddy]);

			if (!empty($new_buddy))
			{
				$saved = true;
				$buddiesArray = array_merge($buddiesArray, $new_buddy);

				// Now update the current users buddy list.
				$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
				updateMemberData($memID, ['buddy_list' => $user_profile[$memID]['buddy_list']]);
			}

			if ($saved)
			{
				session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
			}
			else
			{
				session_flash('error', $txt['could_not_add_person']);
			}
		}

		// Back to the buddy list!
		redirectexit('action=profile;area=buddies;u=' . $memID);
	}
}
