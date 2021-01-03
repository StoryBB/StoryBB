<?php
/**
 * Remove read alerts after a reasonable period.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\Task;

/**
 * Remove read alerts after a reasonable period.
 */
class RemoveOldAlerts implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_remove_old_alerts'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_remove_old_alerts'];
	}

	/**
	 * Remove read alerts after a reasonable period.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $smcFunc;

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}user_alerts
			WHERE is_read > 0
			AND is_read < {int:time}',
			[
				'time' => time() - (86400 * 7),
			]
		);

		// Log we've done it...
		return true;
	}
}
