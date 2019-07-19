<?php
/**
 * Send out a weekly email of all subscribed topics.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Send out a weekly email of all subscribed topics.
 */
class WeeklyDigest extends \StoryBB\Task\Schedulable\DailyDigest
{
	/** @var int $is_weekly Whether this digest is running a daily (0) or weekly (1) since logic is almost identical */
	protected $is_weekly = 1;

	/** @var string $subject_line Which entry in $txt to use as the subject for digest emails */
	protected $subject_line = 'digest_subject_weekly';

	/** @var string $intro_line Which entry in $txt to use as the intro text for digest emails */
	protected $intro_line = 'digest_intro_weekly';

	/**
	 * Mark all current items in the digest log as having been sent.
	 */
	protected function mark_done()
	{
		global $smcFunc;

		// Clear any only weekly ones, and stop us from sending weekly again.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_digest
			WHERE daily != {int:not_daily}',
			array(
				'not_daily' => 0,
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_digest
			SET daily = {int:daily_value}
			WHERE daily = {int:not_daily}',
			array(
				'daily_value' => 2,
				'not_daily' => 0,
			)
		);
	}
}
