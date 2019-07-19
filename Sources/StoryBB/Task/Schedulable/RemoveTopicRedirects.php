<?php
/**
 * Check for and remove move topic notices that have expired.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Check for and remove move topic notices that have expired.
 */
class RemoveTopicRedirects extends \StoryBB\Task\Schedulable
{
	/**
	 * Check for and remove move topic notices that have expired.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $smcFunc, $sourcedir;

		// init
		$topics = [];

		// Find all of the old MOVE topic notices that were set to expire
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE redirect_expires <= {int:redirect_expires}
				AND redirect_expires <> 0',
			array(
				'redirect_expires' => time(),
			)
		);

		while ($row = $smcFunc['db_fetch_row']($request))
			$topics[] = $row[0];
		$smcFunc['db_free_result']($request);

		// Zap, your gone
		if (count($topics) > 0)
		{
			require_once($sourcedir . '/RemoveTopic.php');
			removeTopics($topics, false, true);
		}

		return true;
	}
}
