<?php
/**
 * Weekly maintenance tasks.
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
 * Weekly maintenance.
 */
class WeeklyMaintenance extends \StoryBB\Task\Schedulable
{
	/**
	 * Weekly maintenance.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		$this->prune_empty_settings();
		$this->prune_logs();
		$this->prune_pending_paid_subs();
		$this->prune_sessions();

		// Update the regex of top level domains with the IANA's latest official list. No point making a function here.
		Task::queue_adhoc('StoryBB\\Task\\Adhoc\\UpdateTldRegex');

		return true;
	}

	/**
	 * Prunes a list of settings that if set to 0 in the settings table should be removed to save space.
	 */
	protected function prune_empty_settings()
	{
		global $smcFunc;

		// Delete some settings that needn't be set if they are otherwise empty.
		$emptySettings = array(
			'warning_mute', 'warning_moderate', 'warning_watch', 'warning_show', 'disableCustomPerPage',
			'paid_currency_code', 'paid_currency_symbol', 'paid_email_to', 'paid_email', 'paid_enabled', 'paypal_email',
			'search_enable_captcha', 'search_floodcontrol_time',
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})
				AND (value = {string:zero_value} OR value = {string:blank_value})',
			array(
				'zero_value' => '0',
				'blank_value' => '',
				'setting_list' => $emptySettings,
			)
		);

		// Some settings we never want to keep - they are just there for temporary purposes.
		$deleteAnywaySettings = array(
			'attachment_full_notified',
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})',
			array(
				'setting_list' => $deleteAnywaySettings,
			)
		);
	}

	/**
	 * Prunes the error/moderation/ban/report/scheduled tasks logs as per the settings.
	 */
	protected function prune_logs()
	{
		global $modSettings, $smcFunc;

		if (empty($modSettings['pruningOptions']) || strpos($modSettings['pruningOptions'], ',') === false)
		{
			return;
		}

		list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog']) = explode(',', $modSettings['pruningOptions']);

		if (!empty($modSettings['pruneErrorLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneErrorLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_errors
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}

		if (!empty($modSettings['pruneModLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneModLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_actions
				WHERE log_time < {int:log_time}
					AND id_log = {int:moderation_log}',
				array(
					'log_time' => $t,
					'moderation_log' => 1,
				)
			);
		}

		if (!empty($modSettings['pruneBanLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneBanLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_banned
				WHERE log_time < {int:log_time}',
				array(
					'log_time' => $t,
				)
			);
		}

		if (!empty($modSettings['pruneReportLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneReportLog'] * 86400;

			// This one is more complex then the other logs.  First we need to figure out which reports are too old.
			$reports = [];
			$result = $smcFunc['db_query']('', '
				SELECT id_report
				FROM {db_prefix}log_reported
				WHERE time_started < {int:time_started}
					AND closed = {int:closed}
					AND ignore_all = {int:not_ignored}',
				array(
					'time_started' => $t,
					'closed' => 1,
					'not_ignored' => 0,
				)
			);

			while ($row = $smcFunc['db_fetch_row']($result))
				$reports[] = $row[0];

			$smcFunc['db_free_result']($result);

			if (!empty($reports))
			{
				// Now delete the reports...
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_reported
					WHERE id_report IN ({array_int:report_list})',
					array(
						'report_list' => $reports,
					)
				);
				// And delete the comments for those reports...
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_reported_comments
					WHERE id_report IN ({array_int:report_list})',
					array(
						'report_list' => $reports,
					)
				);
			}
		}

		if (!empty($modSettings['pruneScheduledTaskLog']))
		{
			// Figure out when our cutoff time is.  1 day = 86400 seconds.
			$t = time() - $modSettings['pruneScheduledTaskLog'] * 86400;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_scheduled_tasks
				WHERE time_run < {int:time_run}',
				array(
					'time_run' => $t,
				)
			);
		}
	}

	/**
	 * Cleans up any paid subscriptions that never got activated.
	 */
	protected function prune_pending_paid_subs()
	{
		global $smcFunc;

		// Get rid of any paid subscriptions that were never actioned.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE end_time = {int:no_end_time}
				AND status = {int:not_active}
				AND start_time < {int:start_time}
				AND payments_pending < {int:payments_pending}',
			array(
				'no_end_time' => 0,
				'not_active' => 0,
				'start_time' => time() - 60,
				'payments_pending' => 1,
			)
		);
	}

	/**
	 * Provides some manual forcible garbage collection on sessions as some PHP builds don't.
	 */
	protected function prune_sessions()
	{
		global $smcFunc;

		// Some OS's don't seem to clean out their sessions.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			array(
				'last_update' => time() - 86400,
			)
		);
	}
}
