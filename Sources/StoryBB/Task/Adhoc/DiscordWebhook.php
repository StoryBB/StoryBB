<?php
/**
 * This file contains background notification code for any create post action.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

use StoryBB\Hook\Integratable\CharacterDetails;
use StoryBB\Integration\Discord;

/**
 * This file contains background notification code for any create post action.
 */
class DiscordWebhook extends \StoryBB\Task\Adhoc
{
	use CharacterDetails;

	public function execute()
	{
		$discord = new Discord();

		// Get a fallback for posters.
		$posted_by = $this->get_character_details(0, 0);

		if (isset($this->_details['msgid']))
		{
			$posted_by_ids = $this->get_post_owner($this->_details['msgid']);
			if (isset($posted_by_ids['id_member'], $posted_by_ids['id_character']))
			{
				$posted_by = $this->get_character_details((int) $posted_by_ids['id_member'], (int) $posted_by_ids['id_character']);
			}
		}

		$discord->send_webhook($this->_details['config']['webhook_url'], $this->_details['message'], $posted_by['username'], $posted_by['avatar'], $this->_details['embeds']);

		return true;
	}
}
