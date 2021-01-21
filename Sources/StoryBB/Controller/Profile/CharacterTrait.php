<?php

/**
 * Abstract profile controller (hybrid style)
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

trait CharacterTrait
{
	public function init_character()
	{
		global $user_profile, $context, $scripturl, $modSettings, $smcFunc, $txt, $user_info;

		$memID = $this->params['u'];

		$char_id = isset($_GET['char']) ? (int) $_GET['char'] : 0;
		if (!isset($user_profile[$memID]['characters'][$char_id])) {
			// character doesn't exist... bye.
			redirectexit('action=profile;u=' . $memID);
		}

		$context['character'] = $user_profile[$memID]['characters'][$char_id];
		$context['character']['editable'] = $context['user']['is_owner'] || allowedTo('admin_forum');
		$context['user']['can_admin'] = allowedTo('admin_forum');

		$context['character']['retire_eligible'] = !$context['character']['is_main'];
		if ($context['user']['is_owner'] && $user_info['id_character'] == $context['character']['id_character'])
		{
			$context['character']['retire_eligible'] = false; // Can't retire if you're logged in as them
		}

		$context['linktree'][] = [
			'name' => $txt['chars_menu_title'],
			'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . '#user_char_list',
		];
		$context['linktree'][] = [
			'name' => $context['character']['character_name'],
			'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=profile;char=' . $char_id,
		];

		return $char_id;
	}
}
