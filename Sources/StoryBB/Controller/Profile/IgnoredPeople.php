<?php

/**
 * Displays the ignored people/accounts page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Helper\Autocomplete;

class IgnoredPeople extends AbstractProfileController
{
	public function display_action()
	{
		global $txt;
		global $context, $user_profile, $memberContext, $smcFunc;

		$memID = $this->params['u'];

		// For making changes!
		$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
		foreach ($ignoreArray as $key => $value)
			if (empty($value))
				unset($ignoreArray[$key]);

		$saved = false;

		// Removing a member from the ignore list?
		if (isset($_GET['remove']))
		{
			checkSession('get');

			// Heh, I'm lazy, do it the easy way...
			foreach ($ignoreArray as $key => $id_remove)
				if ($id_remove == (int) $_GET['remove'])
				{
					unset($ignoreArray[$key]);
					$saved = true;
				}

			// Make the changes.
			$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
			updateMemberData($memID, ['pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']]);

			if ($saved)
			{
				session_flash('success', sprintf($context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_updated_else'], $context['member']['name']));
			}
			else
			{
				session_flash('error', $txt['could_not_remove_person']);
			}

			// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
			redirectexit('action=profile;area=ignored_people;u=' . $memID);
		}

		// Initialise the list of members we're ignoring.
		$ignored = [];

		if (!empty($ignoreArray))
		{
			$result = $smcFunc['db']->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:ignore_list})
				ORDER BY real_name
				LIMIT {int:ignore_list_count}',
				[
					'ignore_list' => $ignoreArray,
					'ignore_list_count' => substr_count($user_profile[$memID]['pm_ignore_list'], ',') + 1,
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($result))
				$ignored[] = $row['id_member'];
			$smcFunc['db']->free_result($result);
		}

		$context['ignore_count'] = count($ignored);

		// Load all the members up.
		loadMemberData($ignored, false, 'profile');

		// Setup the context for each buddy.
		$context['ignore_list'] = [];
		foreach ($ignored as $ignore_member)
		{
			loadMemberContext($ignore_member);
			$context['ignore_list'][$ignore_member] = $memberContext[$ignore_member];
		}

		Autocomplete::init('member', '#new_ignore');

		$context['sub_template'] = 'profile_ignore';
	}

	public function post_action()
	{
		global $user_profile, $txt;

		$memID = $this->params['u'];

		if (isset($_POST['new_ignore']))
		{
			// For making changes!
			$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
			foreach ($ignoreArray as $key => $value)
				if (empty($value))
					unset($ignoreArray[$key]);

			$saved = false;

			// Prepare the string for extraction...
			$new_ignore = !empty($_POST['new_ignore']) ? (array) $_POST['new_ignore'] : [];
			foreach ($new_ignore as $k => $v)
			{
				$v = (int) $v;
				if ($v <= 0)
					unset ($new_ignore[$k]);
				else
					$new_ignore[$k] = $v;
			}
			$new_ignore = array_diff($new_ignore, $ignoreArray);

			call_integration_hook('integrate_add_ignore', [$memID, &$new_ignore]);

			if (!empty($new_ignore))
			{
				$saved = true;
				$ignoreArray = array_merge($ignoreArray, $new_ignore);

				// Now update the current users buddy list.
				$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
				updateMemberData($memID, ['pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']]);
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

		// Back to the list of pityful people!
		redirectexit('action=profile;area=ignored_people;u=' . $memID);
	}
}
