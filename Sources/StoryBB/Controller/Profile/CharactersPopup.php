<?php

/**
 * Displays the summary profile page.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Profile;

use StoryBB\Model\Alert;
use StoryBB\Template;

class CharactersPopup extends AbstractProfileController
{
	public function display_action()
	{
		global $context, $db_show_debug, $cur_profile, $scripturl;

		// We do not want to output debug information here.
		$db_show_debug = false;

		// We only want to output our little layer here.
		Template::set_layout('raw');
		Template::remove_all_layers();
		$context['sub_template'] = 'profile_character_popup';

		$context['current_characters'] = $cur_profile['characters'];
		$context['display_topic_tracker'] = false;

		foreach ($context['current_characters'] as $id_character => $character)
		{
			if (!$character['is_main'])
			{
				$context['display_topic_tracker'] = true;
			}

			if (!$character['is_main'] && !$character['retired'])
			{
				$context['current_characters'][$id_character]['sheet_url'] = $scripturl . '?action=profile;area=character_sheet;u=' . $context['user']['id'] . ';char=' . $id_character;
			}
		}
	}
}
