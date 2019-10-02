<?php
/**
 * Check for and remove accounts that haven't validated.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\Model\Account;

/**
 * Check for and remove move topic notices that have expired.
 */
class RemoveUnapprovedAccounts implements \StoryBB\Task\Schedulable
{
	/**
	 * Get the human-readable name for this task.
	 * @return string The human readable name.
	 */
	public function get_name(): string
	{
		global $txt;
		return $txt['scheduled_task_remove_unapproved_accts'];
	}

	/**
	 * Get the human-readable description for this task.
	 * @return string The task description.
	 */
	public function get_description(): string
	{
		global $txt;
		return $txt['scheduled_task_desc_remove_unapproved_accts'];
	}

	/**
	 * Check for and remove unapproved accounts that haven't approved after a while.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $smcFunc, $modSettings;

		if (empty($modSettings['remove_unapproved_accounts_days']))
		{
			return true;
		}

		$date_registered = time() - ((int) $modSettings['remove_unapproved_accounts_days'] * 86400);

		$smcFunc['db']->query('', '
			DELETE FROM {db_prefix}members
			WHERE is_activated IN ({int:unactivated}, {int:unactivatedbanned})
			AND date_registered <= {int:date_registered}',
			[
				'unactivated' => Account::ACCOUNT_NOTACTIVATED,
				'unactivatedbanned' => Account::ACCOUNT_BANNED + Account::ACCOUNT_NOTACTIVATED,
				'date_registered' => $date_registered,
			]
		);

		return true;
	}
}
