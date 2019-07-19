<?php
/**
 * Base class for every scheduled task which also functions as a sort of interface as well.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

/**
 * Erase logs in the system after a given amount of days.
 */
class ScrubLogs extends \StoryBB\Task\Schedulable
{
	/** @var $banned_ips The IPs we have listed as banned */
	protected $banned_ips;

	/**
	 * Find all the ranges of banned IPs in the system so that logs won't be automatically purged for these.
	 *
	 * @return array An array of banned IP ranges where each item is an array of low/high IPs in the range
	 */
	protected function get_banned_ips(): array
	{
		global $smcFunc;

		// Before we go any further, we need the banned IPs so that we can exclude them going forward.
		$banned_ips = [];
		$request = $smcFunc['db_query']('', '
			SELECT ip_low, ip_high
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bi.id_ban_group = bg.id_ban_group)
			WHERE bg.expire_time IS NULL or bg.expire_time > {int:now}',
			[
				'now' => time(),
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$banned_ips[] = $row;
		}
		$smcFunc['db_free_result']($request);

		return $banned_ips;
	}

	/**
	 * Erase logs in the system after a given amount of days.
	 *
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		global $modSettings, $smcFunc;
		$now = time();

		$standard_period = isset($modSettings['retention_policy_standard']) ? (int) $modSettings['retention_policy_standard'] : 90;
		$standard_timestamp = $now - ($standard_period * 86400);

		$sensitive_period = isset($modSettings['retention_policy_sensitive']) ? (int) $modSettings['retention_policy_sensitive'] : 15;
		$sensitive_timestamp = $now - ($sensitive_period * 86400);

		// The flood control table keeps IPs - so we have to clean it. Anything more than a day old shouldn't exist anyway...
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_floodcontrol
			WHERE log_time < {int:log_time}',
			[
				'log_time' => $now - 86400,
			]
		);

		// Ditto the online log but because historically admins have done odd things, give it a day plus an hour.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_online
			WHERE log_time < {int:log_time}',
			[
				'log_time' => $now - 86400 - 3600,
			]
		);

		// Now fix the error log, first session IDs.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_errors
			SET session = {empty}
			WHERE log_time < {int:log_time}',
			[
				'log_time' => $sensitive_timestamp, 
			]
		);

		// Now we go to town and cleaning up IPs in various places.
		$this->banned_ips = $this->get_banned_ips();

		$this->erase_log_table('log_actions', 'id_action', 'ip', 'log_time', $standard_timestamp);
		$this->erase_log_table('log_banned', 'id_ban_log', 'ip', 'log_time', $standard_timestamp);
		$this->erase_log_table('log_errors', 'id_error', 'ip', 'log_time', $sensitive_timestamp);
		$this->erase_log_table('log_reported_comments', 'id_comment', 'member_ip', 'time_sent', $standard_timestamp);
		$this->erase_log_table('members', 'id_member', 'member_ip', 'last_login', $standard_timestamp);
		$this->erase_log_table('members', 'id_member', 'member_ip2', 'last_login', $standard_timestamp);
		$this->erase_log_table('member_logins', 'id_login', 'ip', 'time', $standard_timestamp);
		$this->erase_log_table('member_logins', 'id_login', 'ip2', 'time', $standard_timestamp);
		$this->erase_log_table('messages', 'id_msg', 'poster_ip', 'poster_time', $standard_timestamp);

		return true;
	}

	/**
	 * Erase the relevant contents of a single log table.
	 *
	 * @param string $table_name The name of a generic log table to clean out (minus {db_prefix})
	 * @param string $id_column The name of the column that is the primary key of the table
	 * @param string $ip_column The name of the column that contains the IP address to be filtered
	 * @param string $time_column The name of the column that contains the timestamp to check against
	 * @param int $timestamp The timestamp of the most recent log entries to keep
	 */
	protected function erase_log_table(string $table_name, string $id_column, string $ip_column, string $time_column, int $timestamp)
	{
		global $smcFunc;

		// Find any rows that might be relevant, check they're not banned, and add them to the list.
		$erase = [];
		$request = $smcFunc['db_query']('', '
			SELECT {raw:id_column}, {raw:ip_column}
			FROM {db_prefix}' . $table_name . '
			WHERE {raw:time_column} < {int:log_time}',
			[
				'id_column' => $id_column,
				'ip_column' => $ip_column,
				'time_column' => $time_column,
				'log_time' => $timestamp, 
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			foreach ($this->banned_ips as $ban)
			{
				if ($row[$ip_column] >= $ban['ip_low'] && $row[$ip_column] <= $ban['ip_high'])
				{
					continue 2;
				}
			}

			$erase[] = $row[$id_column];
		}
		$smcFunc['db_free_result']($request);

		// Now the actual deletion.
		if (empty($erase))
			return;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}' . $table_name . '
			SET {raw:ip_column} = NULL
			WHERE {raw:id_column} IN ({array_int:erase})',
			[
				'id_column' => $id_column,
				'ip_column' => $ip_column,
				'erase' => $erase,
			]
		);
	}
}
