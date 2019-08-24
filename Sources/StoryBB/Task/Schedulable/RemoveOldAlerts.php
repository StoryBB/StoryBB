<?php
/**
 * Remove read alerts after a reasonable period.
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
 * Remove read alerts after a reasonable period.
 */
class RemoveOldAlerts extends \StoryBB\Task\Schedulable
{
	/**
	 * Remove read alerts after a reasonable period.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
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
