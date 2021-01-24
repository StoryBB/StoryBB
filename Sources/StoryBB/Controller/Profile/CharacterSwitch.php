<?php

/**
 * Switches characters.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

class CharacterSwitch extends AbstractProfileController
{
	public function display_action()
	{
		global $smcFunc, $modSettings;

		$memID = $this->params['u'];

		checkSession('get');

		$profile_redirect = isset($_GET['profile']);

		$char = isset($_GET['char']) ? (int) $_GET['char'] : 0;

		if (empty($char))
		{
			if ($profile_redirect)
			{
				redirectexit('action=profile;u=' . $memID);
			}
			else
			{
				die;
			}
		}
		// Let's check the user actually owns this character
		$result = $smcFunc['db']->query('', '
			SELECT id_character, id_member
			FROM {db_prefix}characters
			WHERE id_character = {int:id_character}
				AND id_member = {int:id_member}
				AND retired = 0',
			[
				'id_character' => $char,
				'id_member' => $memID,
			]
		);
		$found = $smcFunc['db']->num_rows($result) > 0;
		$smcFunc['db']->free_result($result);

		if (!$found)
		{
			if ($profile_redirect)
			{
				redirectexit('action=profile;u=' . $memID);
			}
			else
			{
				die;
			}
		}

		// So it's valid. Update the members table first of all.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}members
			SET current_character = {int:id_character}
			WHERE id_member = {int:id_member}',
			[
				'id_character' => $char,
				'id_member' => $memID,
			]
		);
		// Now the online log too.
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}log_online
			SET id_character = {int:id_character}
			WHERE id_member = {int:id_member}',
			[
				'id_character' => $char,
				'id_member' => $memID,
			]
		);
		// And last active
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}characters
			SET last_active = {int:last_active}
			WHERE id_character = {int:character}',
			[
				'last_active' => time(),
				'character' => $char,
			]
		);

		// If caching would have cached the user's record, nuke it.
		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
		{
			cache_put_data('user_settings-' . $memID, null, 60);
		}

		// Whatever they had in session for theme, disregard it.
		unset ($_SESSION['id_theme']);

		if ($profile_redirect)
		{
			redirectexit('action=profile;u=' . $memID . ';area=characters;char=' . $char);
		}
		else
		{
			die;
		}
	}
}
