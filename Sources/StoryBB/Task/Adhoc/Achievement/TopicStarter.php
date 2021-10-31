<?php
/**
 * Process achievements for topic starters.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc\Achievement;

use StoryBB\Achievement;

/**
 * Process achievements for topic starters.
 */
class TopicStarter extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task - loads up the info, puts the email in the queue and inserts any alerts as needed.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		$account = $this->_details['account'];
		$character = $this->_details['character'];

		$achievement = new Achievement;
		$achievement->trigger_award_achievement('AccountTopicStarter', $account, $character);
		$achievement->trigger_award_achievement('CharacterTopicStarter', $account, $character);
		$achievement->trigger_award_achievement('TopicStarter', $account, $character);

		return true;
	}
}
