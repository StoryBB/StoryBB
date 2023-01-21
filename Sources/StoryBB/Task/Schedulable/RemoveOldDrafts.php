<?php
/**
 * Cleaning up old drafts.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Cleaning up old drafts.
 */
class RemoveOldDrafts extends \StoryBB\Task\AbstractSchedulable implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_remove_old_drafts'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_remove_old_drafts'];
	}

	/**
	 * Clean up old drafts after a given amount of days.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $smcFunc, $sourcedir, $modSettings;

		if (empty($modSettings['drafts_keep_days']))
		{
			return true;
		}

		// Find all of the old drafts.
		$drafts = [];
		$request = $smcFunc['db']->query('', '
			SELECT id_draft
			FROM {db_prefix}user_drafts
			WHERE poster_time <= {int:poster_time_old}',
			[
				'poster_time_old' => time() - (86400 * $modSettings['drafts_keep_days']),
			]
		);

		while ($row = $smcFunc['db']->fetch_row($request))
		{
			$drafts[] = (int) $row[0];
		}
		$smcFunc['db']->free_result($request);

		// If we have old one, remove them
		if (!empty($drafts))
		{
			require_once($sourcedir . '/Drafts.php');
			DeleteDraft($drafts, false);
		}

		return true;
	}
}
