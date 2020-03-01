<?php
/**
 * Check for and remove move topic notices that have expired.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Check for and remove move topic notices that have expired.
 */
class RemoveTopicRedirects implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_remove_topic_redirect'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_remove_topic_redirect'];
	}

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
		$request = $smcFunc['db']->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE redirect_expires <= {int:redirect_expires}
				AND redirect_expires <> 0',
			[
				'redirect_expires' => time(),
			]
		);

		while ($row = $smcFunc['db']->fetch_row($request))
			$topics[] = $row[0];
		$smcFunc['db']->free_result($request);

		// Zap, your gone
		if (count($topics) > 0)
		{
			require_once($sourcedir . '/RemoveTopic.php');
			removeTopics($topics, false, true);
		}

		return true;
	}
}
