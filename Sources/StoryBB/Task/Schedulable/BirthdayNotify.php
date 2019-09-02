<?php
/**
 * Queue up another run of the birthday email sender.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\Task;

/**
 * Queue up another run of the birthday email sender.
 */
class BirthdayNotify implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_birthdayemails'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_birthdayemails'];
	}

	/**
	 * Queue up another run of the birthday email sender.
	 * We do this this way rather than synchronously as a scheduled task
	 * because scheduled tasks run in the same main thread as a normal page load.
	 *
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		Task::queue_adhoc('StoryBB\\Task\\Adhoc\\BirthdayNotify');

		return true;
	}
}
